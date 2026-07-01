<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderEvaluacionGuardar(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

function estadoCanonicoEvaluacion(?string $estado): string
{
    $estado = strtoupper(trim((string) $estado));

    $estado = str_replace(
        ['Á', 'É', 'Í', 'Ó', 'Ú', ' '],
        ['A', 'E', 'I', 'O', 'U', '_'],
        $estado
    );

    return match ($estado) {
        'BORRADOR', '' => 'PENDIENTE',
        'EN_REVISION' => 'EN_REVISION',
        'CORREGIDO', 'CORREGIDA' => 'CORREGIDO',
        'RECHAZADO', 'RECHAZADA', 'OBSERVADO', 'OBSERVADA' => 'RECHAZADO',
        'REVISADO', 'REVISADA', 'APROBADO', 'APROBADA' => 'REVISADO',
        default => $estado,
    };
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderEvaluacionGuardar(false, 'Tu sesión finalizó. Ingresa nuevamente al sistema.');
}

$rol = strtolower(trim((string) ($_SESSION['rol'] ?? '')));

if (!in_array($rol, ['docente', 'tutor'], true)) {
    http_response_code(403);
    responderEvaluacionGuardar(false, 'Acceso restringido para jurados.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderEvaluacionGuardar(false, 'No se pudo establecer la conexión local.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responderEvaluacionGuardar(false, 'Método no permitido.');
}

$idJurado = (int) $_SESSION['id'];
$idTrabajo = (int) ($_POST['id_trabajo'] ?? 0);
$accion = strtoupper(trim((string) ($_POST['accion'] ?? '')));
$comentario = trim((string) ($_POST['comentario'] ?? ''));

/*
|--------------------------------------------------------------------------
| Fase 2 no genera nota ni aprobación final.
|--------------------------------------------------------------------------
*/
$accionesValidas = [
    'APROBAR' => 'REVISADO',
    'REVISAR' => 'REVISADO',
    'VALIDAR' => 'REVISADO',
    'REVISADO' => 'REVISADO',

    'RECHAZAR' => 'RECHAZADO',
    'RECHAZADO' => 'RECHAZADO',
];

if ($idTrabajo <= 0 || !isset($accionesValidas[$accion])) {
    http_response_code(422);
    responderEvaluacionGuardar(false, 'Los datos de la revisión no son válidos.');
}

$nuevoEstado = $accionesValidas[$accion];

if ($nuevoEstado === 'RECHAZADO' && $comentario === '') {
    http_response_code(422);
    responderEvaluacionGuardar(
        false,
        'Debes escribir las observaciones antes de rechazar el documento.'
    );
}

try {
    $db = $conexion;
    $db->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Validar jurado, trabajo y Fase 2
    |--------------------------------------------------------------------------
    */
    $stmtTrabajo = $db->prepare("
        SELECT
            t.id AS id_trabajo,
            t.id_estudiante,
            t.id_configuracion,
            t.titulo_trabajo,
            t.ruta_archivo,
            t.estado_aprobacion,

            pfc.id_programa,
            pfc.gestion,

            f.numero_fase,

            i.id AS id_inscripcion,

            ja.id AS id_asignacion_jurado,

            fec.id AS id_fase_estudiante_config,

            CONCAT_WS(
                ' ',
                estudiante.nombres,
                estudiante.apellido_paterno,
                estudiante.apellido_materno
            ) AS estudiante

        FROM trabajos t
        INNER JOIN programa_fase_config pfc
            ON pfc.id = t.id_configuracion
        INNER JOIN fases f
            ON f.id = pfc.id_fase
        INNER JOIN inscripciones i
            ON i.id_estudiante = t.id_estudiante
            AND i.id_programa = pfc.id_programa
        INNER JOIN titulacion_postulaciones tp
            ON tp.id_inscripcion = i.id
        INNER JOIN jurado_asignaciones ja
            ON ja.id_postulacion = tp.id
            AND ja.id_docente = :id_jurado
            AND ja.estado = 'ASIGNADO'
        INNER JOIN usuarios estudiante
            ON estudiante.id = t.id_estudiante
        LEFT JOIN fase_estudiante_config fec
            ON fec.id_estudiante = t.id_estudiante
            AND fec.id_configuracion = t.id_configuracion
        WHERE t.id = :id_trabajo
        LIMIT 1
    ");

    $stmtTrabajo->execute([
        ':id_jurado' => $idJurado,
        ':id_trabajo' => $idTrabajo,
    ]);

    $trabajo = $stmtTrabajo->fetch(PDO::FETCH_ASSOC);

    if (!$trabajo) {
        throw new RuntimeException(
            'No tienes asignado este trabajo o el participante no tiene jurado activo.'
        );
    }

    if ((int) $trabajo['numero_fase'] !== 2) {
        throw new RuntimeException(
            'Este módulo solo permite revisar documentos de la Fase 2.'
        );
    }

    if (empty($trabajo['id_fase_estudiante_config'])) {
        throw new RuntimeException(
            'No se encontró la configuración individual de esta fase para el participante.'
        );
    }

    $estadoActual = estadoCanonicoEvaluacion($trabajo['estado_aprobacion']);

    $estadosEvaluables = [
    'PENDIENTE',
    'EN_REVISION',
    'CORREGIDO',
    'REVISADO',
    'RECHAZADO',
];

if (!in_array($estadoActual, $estadosEvaluables, true)) {
    throw new RuntimeException(
        'Este documento no se encuentra disponible para evaluación o modificación.'
    );
}
    /*
    |--------------------------------------------------------------------------
    | Buscar o crear la entrega vigente
    |--------------------------------------------------------------------------
    */
    $stmtEntrega = $db->prepare("
        SELECT id
        FROM trabajo_entregas
        WHERE id_trabajo = :id_trabajo
          AND es_vigente = 1
        ORDER BY numero_version DESC, id DESC
        LIMIT 1
    ");

    $stmtEntrega->execute([
        ':id_trabajo' => $idTrabajo,
    ]);

    $entrega = $stmtEntrega->fetch(PDO::FETCH_ASSOC);

    if ($entrega) {
        $idEntrega = (int) $entrega['id'];
    } else {
        $rutaArchivo = (string) ($trabajo['ruta_archivo'] ?? '');
        $nombreArchivo = $rutaArchivo !== '' ? basename($rutaArchivo) : null;

        $stmtCrearEntrega = $db->prepare("
            INSERT INTO trabajo_entregas (
                id_trabajo,
                numero_version,
                titulo_trabajo,
                nombre_original,
                archivo_servidor,
                ruta_archivo,
                estado_entrega,
                es_vigente,
                guardado_por,
                guardado_el,
                enviado_el
            ) VALUES (
                :id_trabajo,
                1,
                :titulo_trabajo,
                :nombre_original,
                :archivo_servidor,
                :ruta_archivo,
                'EN_REVISION',
                1,
                :guardado_por,
                NOW(),
                NOW()
            )
        ");

        $stmtCrearEntrega->execute([
            ':id_trabajo' => $idTrabajo,
            ':titulo_trabajo' => $trabajo['titulo_trabajo'],
            ':nombre_original' => $nombreArchivo,
            ':archivo_servidor' => $nombreArchivo,
            ':ruta_archivo' => $rutaArchivo ?: null,
            ':guardado_por' => $trabajo['id_estudiante'],
        ]);

        $idEntrega = (int) $db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | Actualizar entrega y trabajo principal
    |--------------------------------------------------------------------------
    */
    $stmtActualizarEntrega = $db->prepare("
        UPDATE trabajo_entregas
        SET
            estado_entrega = :estado_entrega,
            actualizado_el = NOW()
        WHERE id = :id_entrega
    ");

    $stmtActualizarEntrega->execute([
        ':estado_entrega' => $nuevoEstado,
        ':id_entrega' => $idEntrega,
    ]);

    $stmtActualizarTrabajo = $db->prepare("
        UPDATE trabajos
        SET
            estado_aprobacion = :estado,
            calificacion_final = NULL,
            comentario_revision = :comentario,
            fecha_revision = NOW(),
            actualizado_el = NOW()
        WHERE id = :id_trabajo
    ");

    $stmtActualizarTrabajo->execute([
        ':estado' => $nuevoEstado,
        ':comentario' => $comentario ?: null,
        ':id_trabajo' => $idTrabajo,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Mantener vínculo jurado-trabajo
    |--------------------------------------------------------------------------
    */
    $stmtTrabajoDocente = $db->prepare("
        INSERT INTO trabajo_docente (
            id_trabajo,
            id_docente,
            tipo_asignacion
        ) VALUES (
            :id_trabajo,
            :id_docente,
            'JURADO'
        )
        ON DUPLICATE KEY UPDATE
            fecha_asignacion = fecha_asignacion
    ");

    $stmtTrabajoDocente->execute([
        ':id_trabajo' => $idTrabajo,
        ':id_docente' => $idJurado,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Historial de revisión interno
    |--------------------------------------------------------------------------
    */
    $stmtRevision = $db->prepare("
        INSERT INTO trabajo_revisiones (
            id_entrega,
            id_jurado_asignacion,
            id_revisor,
            decision,
            calificacion,
            comentario,
            es_revision_final,
            origen,
            fecha_revision
        ) VALUES (
            :id_entrega,
            :id_jurado_asignacion,
            :id_revisor,
            :decision,
            NULL,
            :comentario,
            0,
            'JURADO',
            NOW()
        )
    ");

    $stmtRevision->execute([
        ':id_entrega' => $idEntrega,
        ':id_jurado_asignacion' => $trabajo['id_asignacion_jurado'],
        ':id_revisor' => $idJurado,
        ':decision' => $nuevoEstado,
        ':comentario' => $comentario ?: null,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Si el jurado rechaza, Administración debe autorizar la reentrega.
    |--------------------------------------------------------------------------
    */
    if ($nuevoEstado === 'RECHAZADO') {
        $stmtUltimoControl = $db->prepare("
            SELECT
                id,
                ciclo,
                estado
            FROM control_reentregas
            WHERE id_trabajo = :id_trabajo
            ORDER BY ciclo DESC, id DESC
            LIMIT 1
            FOR UPDATE
        ");

        $stmtUltimoControl->execute([
            ':id_trabajo' => $idTrabajo,
        ]);

        $ultimoControl = $stmtUltimoControl->fetch(PDO::FETCH_ASSOC);

        $puedeActualizarControl = $ultimoControl
            && in_array(
                strtoupper((string) $ultimoControl['estado']),
                ['PENDIENTE_AUTORIZACION', 'AUTORIZADA'],
                true
            );

        if ($puedeActualizarControl) {
            $stmtActualizarControl = $db->prepare("
                UPDATE control_reentregas
                SET
                    estado = 'PENDIENTE_AUTORIZACION',
                    fecha_autorizacion = NULL,
                    fecha_limite_correccion = NULL,
                    fecha_reentrega = NULL,
                    motivo = :motivo,
                    observacion_cierre = NULL,
                    autorizado_por = NULL,
                    cerrado_por = NULL
                WHERE id = :id_control
            ");

            $stmtActualizarControl->execute([
                ':motivo' => $comentario,
                ':id_control' => $ultimoControl['id'],
            ]);
        } else {
            $nuevoCiclo = $ultimoControl
                ? ((int) $ultimoControl['ciclo'] + 1)
                : 1;

            $stmtCrearControl = $db->prepare("
                INSERT INTO control_reentregas (
                    id_trabajo,
                    id_fase_estudiante_config,
                    ciclo,
                    estado,
                    motivo
                ) VALUES (
                    :id_trabajo,
                    :id_fase_estudiante_config,
                    :ciclo,
                    'PENDIENTE_AUTORIZACION',
                    :motivo
                )
            ");

            $stmtCrearControl->execute([
                ':id_trabajo' => $idTrabajo,
                ':id_fase_estudiante_config' => $trabajo['id_fase_estudiante_config'],
                ':ciclo' => $nuevoCiclo,
                ':motivo' => $comentario,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cierre del ciclo de corrección
    |--------------------------------------------------------------------------
    | Si el jurado valida una versión que el estudiante ya reentregó,
    | el ciclo queda FINALIZADO. Esto evita que permanezca como REENTREGADA.
    |
    | Si el jurado valida antes de que Administración autorice o antes de que
    | el estudiante reenvíe, las solicitudes abiertas quedan CERRADAS.
    |--------------------------------------------------------------------------
    */
    if ($nuevoEstado === 'REVISADO') {
        $stmtFinalizarReentrega = $db->prepare("
            UPDATE control_reentregas
            SET
                estado = 'FINALIZADA',
                observacion_cierre = 'Versión corregida validada por el jurado.',
                cerrado_por = :id_jurado
            WHERE id_trabajo = :id_trabajo
              AND estado = 'REENTREGADA'
        ");

        $stmtFinalizarReentrega->execute([
            ':id_jurado' => $idJurado,
            ':id_trabajo' => $idTrabajo,
        ]);

        $stmtCerrarSolicitudesAbiertas = $db->prepare("
            UPDATE control_reentregas
            SET
                estado = 'CERRADA',
                observacion_cierre = 'Cerrada automáticamente: el jurado validó el avance antes de recibir una nueva versión corregida.',
                cerrado_por = :id_jurado
            WHERE id_trabajo = :id_trabajo
              AND estado IN ('PENDIENTE_AUTORIZACION', 'AUTORIZADA')
        ");

        $stmtCerrarSolicitudesAbiertas->execute([
            ':id_jurado' => $idJurado,
            ':id_trabajo' => $idTrabajo,
        ]);
    }

    $db->commit();

    if ($nuevoEstado === 'REVISADO') {
        $mensaje = 'Avance de Fase 2 revisado correctamente. La aprobación final corresponde a la Fase 3.';
    } else {
        $mensaje = 'Documento rechazado. Las observaciones fueron enviadas y Administración deberá autorizar una nueva entrega.';
    }

    responderEvaluacionGuardar(true, $mensaje, [
        'estado' => $nuevoEstado,
        'estudiante' => $trabajo['estudiante'],
    ]);

} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);

    responderEvaluacionGuardar(
        false,
        $e->getMessage() ?: 'No fue posible guardar la revisión del jurado.'
    );
}