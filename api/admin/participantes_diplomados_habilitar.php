<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function normalizarEstadoDiplomado(?string $valor): string
{
    return strtoupper(trim((string) $valor));
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    if (($_SESSION['rol'] ?? '') !== 'administrador') {
        http_response_code(403);
        throw new Exception('No tienes permisos para habilitar participantes.');
    }

    $dbLocal = siget_local();
    $dbExterna = siget_externa();

    $idEstudiante = (int) ($_POST['id_estudiante'] ?? 0);
    $idConfiguracion = (int) ($_POST['id_configuracion'] ?? 0);
    $observacion = trim($_POST['observacion'] ?? '');

    if ($idEstudiante <= 0) {
        throw new Exception('Participante no válido.');
    }

    if ($idConfiguracion <= 0) {
        throw new Exception('Configuración de Fase 1 no válida.');
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Validar configuración activa de Fase 1
    |--------------------------------------------------------------------------
    */

    $stmtConfiguracion = $dbLocal->prepare("
        SELECT
            c.id AS id_configuracion,
            c.id_programa,
            c.gestion,
            c.tipo_trabajo,
            c.fecha_inicio_entrega,
            c.fecha_limite_entrega,
            c.fecha_limite_revision,
            c.fecha_devolucion_observaciones,
            c.nota_minima,
            c.estado AS estado_configuracion,

            f.numero_fase,
            f.nombre_fase,

            p.nombre_programa,
            p.gestion_externa
        FROM programa_fase_config c
        INNER JOIN fases f
            ON f.id = c.id_fase
        INNER JOIN programa p
            ON p.id = c.id_programa
        WHERE c.id = :id_configuracion
          AND c.estado = 'ACTIVO'
          AND f.numero_fase = 1
          AND f.estado = 1
        LIMIT 1
    ");

    $stmtConfiguracion->execute([
        ':id_configuracion' => $idConfiguracion
    ]);

    $configuracion = $stmtConfiguracion->fetch(PDO::FETCH_ASSOC);

    if (!$configuracion) {
        throw new Exception('No existe una configuración activa de Fase 1.');
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Validar inscripción interna vinculada al programa
    |--------------------------------------------------------------------------
    */

    $stmtInscripcionInterna = $dbLocal->prepare("
        SELECT
            i.id AS id_inscripcion_interna,
            i.id_inscripcion_externa,
            i.id_programa,
            i.id_estudiante,

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
        WHERE i.id_estudiante = :id_estudiante
          AND i.id_programa = :id_programa
          AND i.id_inscripcion_externa IS NOT NULL
        ORDER BY i.id DESC
        LIMIT 1
    ");

    $stmtInscripcionInterna->execute([
        ':id_estudiante' => $idEstudiante,
        ':id_programa' => $configuracion['id_programa']
    ]);

    $inscripcionInterna = $stmtInscripcionInterna->fetch(PDO::FETCH_ASSOC);

    if (!$inscripcionInterna) {
        throw new Exception(
            'El participante no tiene una inscripción sincronizada para este diplomado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Revalidar condiciones desde la BD externa
    |--------------------------------------------------------------------------
    */

    $stmtInscripcionExterna = $dbExterna->prepare("
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

    $stmtInscripcionExterna->execute([
        ':id_inscripcion_externa' => $inscripcionInterna['id_inscripcion_externa']
    ]);

    $inscripcionExterna = $stmtInscripcionExterna->fetch(PDO::FETCH_ASSOC);

    if (!$inscripcionExterna) {
        throw new Exception('No se encontró la inscripción en la base externa.');
    }

    $estadoAcademico = normalizarEstadoDiplomado(
        $inscripcionExterna['estado_academico'] ?? ''
    );

    $estadoCartera = normalizarEstadoDiplomado(
        $inscripcionExterna['estado_cartera'] ?? ''
    );

    $inscripcionActiva = (int) ($inscripcionExterna['estado'] ?? 0) === 1;

    if (!$inscripcionActiva) {
        throw new Exception('La inscripción externa no está activa.');
    }

    if ($estadoAcademico !== 'CONCLUIDO') {
        throw new Exception(
            'No se puede habilitar. Estado académico actual: ' . $estadoAcademico . '.'
        );
    }

    if (!in_array($estadoCartera, ['VIGENTE', 'EXENTO_DE_DEUDA'], true)) {
        throw new Exception(
            'No se puede habilitar. Estado de cartera actual: ' . $estadoCartera . '.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Crear o reactivar habilitación individual de Fase 1
    |--------------------------------------------------------------------------
    */

    $dbLocal->beginTransaction();

    $stmtHabilitacion = $dbLocal->prepare("
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

    $stmtHabilitacion->execute([
        ':id_configuracion' => $idConfiguracion,
        ':id_estudiante' => $idEstudiante
    ]);

    $habilitacionExistente = $stmtHabilitacion->fetch(PDO::FETCH_ASSOC);

    $observacionFinal = $observacion !== ''
        ? $observacion
        : 'Habilitación inicial de Fase 1 autorizada por Administración.';

    if ($habilitacionExistente) {
        $idHabilitacion = (int) $habilitacionExistente['id'];

        if (normalizarEstadoDiplomado($habilitacionExistente['estado']) === 'ACTIVO') {
            $dbLocal->commit();

            echo json_encode([
                'success' => true,
                'message' => 'El participante ya tiene habilitada la Fase 1.',
                'ya_habilitado' => true,
                'id_habilitacion' => $idHabilitacion
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $stmtActualizar = $dbLocal->prepare("
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

        $stmtActualizar->execute([
            ':fecha_inicio_entrega' => $configuracion['fecha_inicio_entrega'],
            ':fecha_limite_entrega' => $configuracion['fecha_limite_entrega'],
            ':fecha_limite_revision' => $configuracion['fecha_limite_revision'],
            ':fecha_devolucion_observaciones' => $configuracion['fecha_devolucion_observaciones'],
            ':observacion' => $observacionFinal,
            ':creado_por' => $_SESSION['id'],
            ':id' => $idHabilitacion
        ]);

        $mensaje = 'La Fase 1 fue habilitada nuevamente para el participante.';

    } else {
        $stmtInsertar = $dbLocal->prepare("
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

        $stmtInsertar->execute([
            ':id_configuracion' => $idConfiguracion,
            ':id_estudiante' => $idEstudiante,
            ':fecha_inicio_entrega' => $configuracion['fecha_inicio_entrega'],
            ':fecha_limite_entrega' => $configuracion['fecha_limite_entrega'],
            ':fecha_limite_revision' => $configuracion['fecha_limite_revision'],
            ':fecha_devolucion_observaciones' => $configuracion['fecha_devolucion_observaciones'],
            ':observacion' => $observacionFinal,
            ':creado_por' => $_SESSION['id']
        ]);

        $idHabilitacion = (int) $dbLocal->lastInsertId();

        $mensaje = 'Fase 1 habilitada correctamente para el participante.';
    }

    $dbLocal->commit();

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'ya_habilitado' => false,
        'id_habilitacion' => $idHabilitacion,
        'participante' => trim(
            ($inscripcionInterna['nombres'] ?? '') . ' ' .
            ($inscripcionInterna['apellido_paterno'] ?? '') . ' ' .
            ($inscripcionInterna['apellido_materno'] ?? '')
        ),
        'fase' => 'Registro y Presentación de Propuesta'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (isset($dbLocal) && $dbLocal instanceof PDO && $dbLocal->inTransaction()) {
        $dbLocal->rollBack();
    }

    if (http_response_code() < 400) {
        http_response_code(500);
    }

    echo json_encode([
        'success' => false,
        'message' => 'No se pudo habilitar la Fase 1.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}