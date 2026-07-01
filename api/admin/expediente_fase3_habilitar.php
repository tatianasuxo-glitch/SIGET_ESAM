<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderFase3(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

function normalizarFechaFase3(string $fecha): ?string
{
    $fecha = trim($fecha);

    if ($fecha === '') {
        return null;
    }

    $fecha = str_replace('T', ' ', $fecha);

    $formatos = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
    ];

    foreach ($formatos as $formato) {
        $objeto = DateTime::createFromFormat($formato, $fecha);

        if ($objeto instanceof DateTime) {
            return $objeto->format('Y-m-d H:i:s');
        }
    }

    return null;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderFase3(false, 'Tu sesión finalizó. Ingresa nuevamente.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderFase3(false, 'Acceso restringido.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responderFase3(false, 'Método no permitido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderFase3(false, 'No se pudo establecer la conexión local.');
}

$idAdministrador = (int) $_SESSION['id'];
$idInscripcion = (int) ($_POST['id_inscripcion'] ?? 0);

$fechaInicio = normalizarFechaFase3(
    (string) ($_POST['fecha_inicio_entrega'] ?? '')
);

$fechaLimiteEntrega = normalizarFechaFase3(
    (string) ($_POST['fecha_limite_entrega'] ?? '')
);

$fechaLimiteRevision = normalizarFechaFase3(
    (string) ($_POST['fecha_limite_revision'] ?? '')
);

$fechaDevolucion = normalizarFechaFase3(
    (string) ($_POST['fecha_devolucion_observaciones'] ?? '')
);

$observacion = trim((string) ($_POST['observacion'] ?? ''));

if ($idInscripcion <= 0) {
    http_response_code(422);
    responderFase3(false, 'No se identificó la inscripción del participante.');
}

if (
    !$fechaInicio ||
    !$fechaLimiteEntrega ||
    !$fechaLimiteRevision ||
    !$fechaDevolucion
) {
    http_response_code(422);
    responderFase3(
        false,
        'Debes registrar las cuatro fechas requeridas para habilitar Fase 3.'
    );
}

if (
    strtotime($fechaInicio) >= strtotime($fechaLimiteEntrega) ||
    strtotime($fechaLimiteEntrega) >= strtotime($fechaLimiteRevision) ||
    strtotime($fechaLimiteRevision) >= strtotime($fechaDevolucion)
) {
    http_response_code(422);
    responderFase3(
        false,
        'Las fechas deben respetar este orden: inicio de entrega, límite de entrega, límite de revisión y devolución de observaciones.'
    );
}

try {
    $db = $conexion;

    $stmtContexto = $db->prepare("
        SELECT
            i.id AS id_inscripcion,
            i.id_estudiante,
            i.id_programa,

            p.gestion_externa,

            tp.id AS id_postulacion

        FROM inscripciones i
        INNER JOIN programa p
            ON p.id = i.id_programa
        LEFT JOIN titulacion_postulaciones tp
            ON tp.id_inscripcion = i.id

        WHERE i.id = :id_inscripcion
        LIMIT 1
    ");

    $stmtContexto->execute([
        ':id_inscripcion' => $idInscripcion,
    ]);

    $contexto = $stmtContexto->fetch(PDO::FETCH_ASSOC);

    if (!$contexto) {
        http_response_code(404);
        responderFase3(false, 'No se encontró la inscripción solicitada.');
    }

    /*
    |--------------------------------------------------------------------------
    | Fase 2 debe estar validada antes de habilitar Fase 3
    |--------------------------------------------------------------------------
    */
    $stmtFase2 = $db->prepare("
        SELECT
            t.id,
            t.estado_aprobacion

        FROM trabajos t
        INNER JOIN programa_fase_config pfc
            ON pfc.id = t.id_configuracion
        INNER JOIN fases f
            ON f.id = pfc.id_fase

        WHERE t.id_estudiante = :id_estudiante
          AND pfc.id_programa = :id_programa
          AND f.numero_fase = 2
          AND t.id = (
                SELECT MAX(t2.id)
                FROM trabajos t2
                WHERE t2.id_estudiante = t.id_estudiante
                  AND t2.id_configuracion = t.id_configuracion
          )

        ORDER BY t.id DESC
        LIMIT 1
    ");

    $stmtFase2->execute([
        ':id_estudiante' => $contexto['id_estudiante'],
        ':id_programa' => $contexto['id_programa'],
    ]);

    $fase2 = $stmtFase2->fetch(PDO::FETCH_ASSOC);

    $estadoFase2 = strtoupper(trim((string) ($fase2['estado_aprobacion'] ?? '')));

    if (!in_array($estadoFase2, ['REVISADO', 'APROBADO'], true)) {
        http_response_code(422);
        responderFase3(
            false,
            'No es posible habilitar Fase 3. La Fase 2 debe estar validada por el jurado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Debe existir jurado asignado para Fases 2 y 3
    |--------------------------------------------------------------------------
    */
    $tieneJurado = false;

    if (!empty($contexto['id_postulacion'])) {
        $stmtJurado = $db->prepare("
            SELECT ja.id
            FROM jurado_asignaciones ja
            WHERE ja.id_postulacion = :id_postulacion
              AND UPPER(TRIM(COALESCE(ja.estado, ''))) IN ('ASIGNADO', 'ACTIVO')
            ORDER BY ja.fecha_asignacion DESC, ja.id DESC
            LIMIT 1
        ");

        $stmtJurado->execute([
            ':id_postulacion' => $contexto['id_postulacion'],
        ]);

        $tieneJurado = (bool) $stmtJurado->fetchColumn();
    }

    if (!$tieneJurado) {
        $stmtJuradoRespaldo = $db->prepare("
            SELECT td.id_docente
            FROM trabajo_docente td
            INNER JOIN trabajos t
                ON t.id = td.id_trabajo
            INNER JOIN programa_fase_config pfc
                ON pfc.id = t.id_configuracion
            INNER JOIN fases f
                ON f.id = pfc.id_fase

            WHERE t.id_estudiante = :id_estudiante
              AND pfc.id_programa = :id_programa
              AND f.numero_fase = 2
              AND UPPER(TRIM(COALESCE(td.tipo_asignacion, ''))) IN (
                  'JURADO',
                  'EVALUADOR',
                  'REVISOR'
              )

            ORDER BY td.fecha_asignacion DESC
            LIMIT 1
        ");

        $stmtJuradoRespaldo->execute([
            ':id_estudiante' => $contexto['id_estudiante'],
            ':id_programa' => $contexto['id_programa'],
        ]);

        $tieneJurado = (bool) $stmtJuradoRespaldo->fetchColumn();
    }

    if (!$tieneJurado) {
        http_response_code(422);
        responderFase3(
            false,
            'No es posible habilitar Fase 3 porque el participante no tiene un jurado asignado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Configuración activa de Fase 3 del programa
    |--------------------------------------------------------------------------
    */
    $sqlFase3 = "
        SELECT
            pfc.id AS id_configuracion,
            pfc.gestion
        FROM programa_fase_config pfc
        INNER JOIN fases f
            ON f.id = pfc.id_fase

        WHERE pfc.id_programa = :id_programa
          AND f.numero_fase = 3
          AND UPPER(TRIM(COALESCE(pfc.estado, ''))) = 'ACTIVO'
    ";

    $paramsFase3 = [
        ':id_programa' => $contexto['id_programa'],
    ];

    if (!empty($contexto['gestion_externa'])) {
        $sqlFase3 .= " AND CAST(pfc.gestion AS CHAR) = :gestion";
        $paramsFase3[':gestion'] = (string) $contexto['gestion_externa'];
    }

    $sqlFase3 .= " ORDER BY pfc.id DESC LIMIT 1";

    $stmtFase3 = $db->prepare($sqlFase3);
    $stmtFase3->execute($paramsFase3);

    $configuracionFase3 = $stmtFase3->fetch(PDO::FETCH_ASSOC);

    if (!$configuracionFase3) {
        http_response_code(422);
        responderFase3(
            false,
            'No existe una configuración activa de Fase 3 para este programa y gestión.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | No permitir habilitar si ya existe una entrega de Fase 3
    |--------------------------------------------------------------------------
    */
    $stmtTrabajoFase3 = $db->prepare("
        SELECT t.id
        FROM trabajos t
        WHERE t.id_estudiante = :id_estudiante
          AND t.id_configuracion = :id_configuracion
        ORDER BY t.id DESC
        LIMIT 1
    ");

    $stmtTrabajoFase3->execute([
        ':id_estudiante' => $contexto['id_estudiante'],
        ':id_configuracion' => $configuracionFase3['id_configuracion'],
    ]);

    if ($stmtTrabajoFase3->fetchColumn()) {
        http_response_code(422);
        responderFase3(
            false,
            'Fase 3 ya tiene un documento registrado. No es necesario habilitarla nuevamente.'
        );
    }

    $db->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Crear o actualizar habilitación individual de Fase 3
    |--------------------------------------------------------------------------
    */
    $stmtHabilitacionActual = $db->prepare("
        SELECT id
        FROM fase_estudiante_config
        WHERE id_estudiante = :id_estudiante
          AND id_configuracion = :id_configuracion
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmtHabilitacionActual->execute([
        ':id_estudiante' => $contexto['id_estudiante'],
        ':id_configuracion' => $configuracionFase3['id_configuracion'],
    ]);

    $idHabilitacion = $stmtHabilitacionActual->fetchColumn();

    $mensajeObservacion = $observacion !== ''
        ? $observacion
        : 'Fase 3 habilitada por Administración Académica. El participante debe presentar la versión final del trabajo.';

    if ($idHabilitacion) {
        $stmtActualizarHabilitacion = $db->prepare("
            UPDATE fase_estudiante_config
            SET
                estado = 'ACTIVO',
                fecha_inicio_entrega = :fecha_inicio_entrega,
                fecha_limite_entrega = :fecha_limite_entrega,
                fecha_limite_revision = :fecha_limite_revision,
                fecha_devolucion_observaciones = :fecha_devolucion_observaciones,
                observacion = :observacion,
                creado_por = :creado_por,
                fecha_actualizacion = NOW()
            WHERE id = :id_habilitacion
        ");

        $stmtActualizarHabilitacion->execute([
            ':fecha_inicio_entrega' => $fechaInicio,
            ':fecha_limite_entrega' => $fechaLimiteEntrega,
            ':fecha_limite_revision' => $fechaLimiteRevision,
            ':fecha_devolucion_observaciones' => $fechaDevolucion,
            ':observacion' => $mensajeObservacion,
            ':creado_por' => $idAdministrador,
            ':id_habilitacion' => $idHabilitacion,
        ]);
    } else {
        $stmtCrearHabilitacion = $db->prepare("
            INSERT INTO fase_estudiante_config (
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
            ) VALUES (
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

        $stmtCrearHabilitacion->execute([
            ':id_configuracion' => $configuracionFase3['id_configuracion'],
            ':id_estudiante' => $contexto['id_estudiante'],
            ':fecha_inicio_entrega' => $fechaInicio,
            ':fecha_limite_entrega' => $fechaLimiteEntrega,
            ':fecha_limite_revision' => $fechaLimiteRevision,
            ':fecha_devolucion_observaciones' => $fechaDevolucion,
            ':observacion' => $mensajeObservacion,
            ':creado_por' => $idAdministrador,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Actualizar estado general de postulación cuando existe registro
    |--------------------------------------------------------------------------
    */
    if (!empty($contexto['id_postulacion'])) {
        $stmtActualizarPostulacion = $db->prepare("
            UPDATE titulacion_postulaciones
            SET
                estado_proceso = 'FASE_3_HABILITADA',
                estado_jurado = 'ASIGNADO',
                actualizado_el = NOW()
            WHERE id = :id_postulacion
        ");

        $stmtActualizarPostulacion->execute([
            ':id_postulacion' => $contexto['id_postulacion'],
        ]);
    }

    $db->commit();

    responderFase3(
        true,
        'Fase 3 fue habilitada correctamente. El participante ya puede subir la versión final.',
        [
            'id_inscripcion' => $idInscripcion,
            'id_configuracion_fase_3' => (int) $configuracionFase3['id_configuracion'],
        ]
    );

} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);

    responderFase3(
        false,
        'No fue posible habilitar Fase 3. Verifica la configuración de fases y vuelve a intentarlo.'
    );
}