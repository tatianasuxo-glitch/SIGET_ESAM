<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function estadoNormalizadoMasivo(?string $valor): string
{
    return strtoupper(trim((string) $valor));
}

function obtenerNombreCompletoMasivo(array $fila): string
{
    return trim(
        ($fila['nombres'] ?? '') . ' ' .
        ($fila['apellido_paterno'] ?? '') . ' ' .
        ($fila['apellido_materno'] ?? '')
    );
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido.');
    }

    if (($_SESSION['rol'] ?? '') !== 'administrador') {
        http_response_code(403);
        throw new Exception('No tienes permisos para habilitar participantes.');
    }

    $idsRecibidos = $_POST['id_inscripciones_internas'] ?? [];

    if (!is_array($idsRecibidos)) {
        $idsRecibidos = [$idsRecibidos];
    }

    $idsInscripciones = [];

    foreach ($idsRecibidos as $id) {
        $id = (int) $id;

        if ($id > 0) {
            $idsInscripciones[$id] = $id;
        }
    }

    $idsInscripciones = array_values($idsInscripciones);

    if (count($idsInscripciones) === 0) {
        throw new Exception('Debes seleccionar al menos un participante.');
    }

    $observacionGeneral = trim($_POST['observacion'] ?? '');

    $dbLocal = siget_local();
    $dbExterna = siget_externa();

    $resumen = [
        'solicitados' => count($idsInscripciones),
        'habilitados' => 0,
        'reactivados' => 0,
        'ya_habilitados' => 0,
        'omitidos' => 0
    ];

    $detalles = [];

    $stmtInscripcionInterna = $dbLocal->prepare("
        SELECT
            i.id AS id_inscripcion_interna,
            i.id_estudiante,
            i.id_programa,
            i.id_inscripcion_externa,

            u.nombres,
            u.apellido_paterno,
            u.apellido_materno,
            u.ci,

            p.nombre_programa,
            p.gestion_externa
        FROM inscripciones i
        INNER JOIN usuarios u
            ON u.id = i.id_estudiante
        INNER JOIN programa p
            ON p.id = i.id_programa
        WHERE i.id = :id_inscripcion_interna
          AND i.id_inscripcion_externa IS NOT NULL
          AND LOWER(p.tipo) LIKE '%diplomado%'
        LIMIT 1
    ");

    $stmtConfiguracion = $dbLocal->prepare("
        SELECT
            c.id AS id_configuracion,
            c.id_programa,
            c.gestion,
            c.fecha_inicio_entrega,
            c.fecha_limite_entrega,
            c.fecha_limite_revision,
            c.fecha_devolucion_observaciones
        FROM programa_fase_config c
        INNER JOIN fases f
            ON f.id = c.id_fase
        WHERE c.id_programa = :id_programa
          AND c.gestion = :gestion
          AND c.estado = 'ACTIVO'
          AND f.numero_fase = 1
          AND f.estado = 1
        ORDER BY c.id DESC
        LIMIT 1
    ");

    $stmtExterna = $dbExterna->prepare("
        SELECT
            id_inscripcion_externa,
            estado_cartera,
            estado_academico,
            estado_acceso,
            motivo_bloqueo,
            estado
        FROM ext_inscripciones
        WHERE id_inscripcion_externa = :id_inscripcion_externa
        LIMIT 1
    ");

    $stmtHabilitacionExistente = $dbLocal->prepare("
        SELECT
            id,
            estado
        FROM fase_estudiante_config
        WHERE id_configuracion = :id_configuracion
          AND id_estudiante = :id_estudiante
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $stmtInsertarHabilitacion = $dbLocal->prepare("
        INSERT INTO fase_estudiante_config
        (
            id_configuracion,
            id_estudiante,
            fecha_inicio_entrega,
            fecha_limite_entrega,
            fecha_limite_revision,
            fecha_devolucion_observaciones,
            estado,
            observacion,
            creado_por,
            fecha_creacion,
            fecha_actualizacion
        )
        VALUES
        (
            :id_configuracion,
            :id_estudiante,
            :fecha_inicio_entrega,
            :fecha_limite_entrega,
            :fecha_limite_revision,
            :fecha_devolucion_observaciones,
            'ACTIVO',
            :observacion,
            :creado_por,
            NOW(),
            NOW()
        )
    ");

    $stmtReactivarHabilitacion = $dbLocal->prepare("
        UPDATE fase_estudiante_config
        SET
            fecha_inicio_entrega = :fecha_inicio_entrega,
            fecha_limite_entrega = :fecha_limite_entrega,
            fecha_limite_revision = :fecha_limite_revision,
            fecha_devolucion_observaciones = :fecha_devolucion_observaciones,
            estado = 'ACTIVO',
            observacion = :observacion,
            creado_por = :creado_por,
            fecha_actualizacion = NOW()
        WHERE id = :id
    ");

    $dbLocal->beginTransaction();

    foreach ($idsInscripciones as $idInscripcionInterna) {
        try {
            $stmtInscripcionInterna->execute([
                ':id_inscripcion_interna' => $idInscripcionInterna
            ]);

            $inscripcion = $stmtInscripcionInterna->fetch(PDO::FETCH_ASSOC);

            if (!$inscripcion) {
                $resumen['omitidos']++;
                $detalles[] = [
                    'id_inscripcion_interna' => $idInscripcionInterna,
                    'resultado' => 'omitido',
                    'motivo' => 'No se encontró una inscripción interna sincronizada de diplomado.'
                ];
                continue;
            }

            $nombreCompleto = obtenerNombreCompletoMasivo($inscripcion);

            $stmtConfiguracion->execute([
                ':id_programa' => $inscripcion['id_programa'],
                ':gestion' => $inscripcion['gestion_externa']
            ]);

            $configuracion = $stmtConfiguracion->fetch(PDO::FETCH_ASSOC);

            if (!$configuracion) {
                $resumen['omitidos']++;
                $detalles[] = [
                    'id_inscripcion_interna' => $idInscripcionInterna,
                    'participante' => $nombreCompleto,
                    'resultado' => 'omitido',
                    'motivo' => 'No existe una configuración activa de Fase 1 para su diplomado y gestión.'
                ];
                continue;
            }

            $stmtExterna->execute([
                ':id_inscripcion_externa' => $inscripcion['id_inscripcion_externa']
            ]);

            $externa = $stmtExterna->fetch(PDO::FETCH_ASSOC);

            if (!$externa) {
                $resumen['omitidos']++;
                $detalles[] = [
                    'id_inscripcion_interna' => $idInscripcionInterna,
                    'participante' => $nombreCompleto,
                    'resultado' => 'omitido',
                    'motivo' => 'No se encontró la inscripción en la base externa.'
                ];
                continue;
            }

            $estadoAcademico = estadoNormalizadoMasivo($externa['estado_academico'] ?? '');
            $estadoCartera = estadoNormalizadoMasivo($externa['estado_cartera'] ?? '');
            $inscripcionExternaActiva = (int) ($externa['estado'] ?? 0) === 1;

            if (!$inscripcionExternaActiva) {
                $resumen['omitidos']++;
                $detalles[] = [
                    'id_inscripcion_interna' => $idInscripcionInterna,
                    'participante' => $nombreCompleto,
                    'resultado' => 'omitido',
                    'motivo' => 'La inscripción externa no está activa.'
                ];
                continue;
            }

            if ($estadoAcademico !== 'CONCLUIDO') {
                $resumen['omitidos']++;
                $detalles[] = [
                    'id_inscripcion_interna' => $idInscripcionInterna,
                    'participante' => $nombreCompleto,
                    'resultado' => 'omitido',
                    'motivo' => 'Estado académico actual: ' . ($estadoAcademico ?: 'NO DEFINIDO') . '.'
                ];
                continue;
            }

            if (!in_array($estadoCartera, ['VIGENTE', 'EXENTO_DE_DEUDA'], true)) {
                $resumen['omitidos']++;
                $detalles[] = [
                    'id_inscripcion_interna' => $idInscripcionInterna,
                    'participante' => $nombreCompleto,
                    'resultado' => 'omitido',
                    'motivo' => 'Estado de cartera actual: ' . ($estadoCartera ?: 'NO DEFINIDO') . '.'
                ];
                continue;
            }

            $stmtHabilitacionExistente->execute([
                ':id_configuracion' => $configuracion['id_configuracion'],
                ':id_estudiante' => $inscripcion['id_estudiante']
            ]);

            $habilitacion = $stmtHabilitacionExistente->fetch(PDO::FETCH_ASSOC);

            $observacion = $observacionGeneral !== ''
                ? $observacionGeneral
                : 'Fase 1 habilitada masivamente por Administración.';

            $parametrosFechas = [
                ':fecha_inicio_entrega' => $configuracion['fecha_inicio_entrega'],
                ':fecha_limite_entrega' => $configuracion['fecha_limite_entrega'],
                ':fecha_limite_revision' => $configuracion['fecha_limite_revision'],
                ':fecha_devolucion_observaciones' => $configuracion['fecha_devolucion_observaciones'],
                ':observacion' => $observacion,
                ':creado_por' => $_SESSION['id']
            ];

            if ($habilitacion) {
                if (estadoNormalizadoMasivo($habilitacion['estado'] ?? '') === 'ACTIVO') {
                    $resumen['ya_habilitados']++;
                    $detalles[] = [
                        'id_inscripcion_interna' => $idInscripcionInterna,
                        'participante' => $nombreCompleto,
                        'resultado' => 'ya_habilitado'
                    ];
                    continue;
                }

                $stmtReactivarHabilitacion->execute(
                    $parametrosFechas + [
                        ':id' => $habilitacion['id']
                    ]
                );

                $resumen['reactivados']++;
                $detalles[] = [
                    'id_inscripcion_interna' => $idInscripcionInterna,
                    'participante' => $nombreCompleto,
                    'resultado' => 'reactivado'
                ];

                continue;
            }

            $stmtInsertarHabilitacion->execute(
                $parametrosFechas + [
                    ':id_configuracion' => $configuracion['id_configuracion'],
                    ':id_estudiante' => $inscripcion['id_estudiante']
                ]
            );

            $resumen['habilitados']++;
            $detalles[] = [
                'id_inscripcion_interna' => $idInscripcionInterna,
                'participante' => $nombreCompleto,
                'resultado' => 'habilitado'
            ];

        } catch (Throwable $errorParticipante) {
            $resumen['omitidos']++;
            $detalles[] = [
                'id_inscripcion_interna' => $idInscripcionInterna,
                'resultado' => 'omitido',
                'motivo' => $errorParticipante->getMessage()
            ];
        }
    }

    $dbLocal->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Proceso masivo de habilitación finalizado.',
        'resumen' => $resumen,
        'detalles' => $detalles
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    if (isset($dbLocal) && $dbLocal instanceof PDO && $dbLocal->inTransaction()) {
        $dbLocal->rollBack();
    }

    $codigo = http_response_code();

    if ($codigo < 400) {
        http_response_code(500);
    }

    echo json_encode([
        'success' => false,
        'message' => 'No se pudo realizar la habilitación masiva de Fase 1.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
