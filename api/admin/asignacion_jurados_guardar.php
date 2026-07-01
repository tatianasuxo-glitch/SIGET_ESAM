<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderAsignacionGuardar(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderAsignacionGuardar(false, 'Tu sesión finalizó. Ingresa nuevamente al sistema.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderAsignacionGuardar(false, 'Acceso restringido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderAsignacionGuardar(false, 'No se pudo establecer la conexión local.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responderAsignacionGuardar(false, 'Método no permitido.');
}

$idAdministrador = (int) $_SESSION['id'];
$idTrabajo = (int) ($_POST['id_trabajo'] ?? 0);
$idDocente = (int) ($_POST['id_docente'] ?? 0);
$observacion = trim((string) ($_POST['observacion'] ?? ''));

if ($idTrabajo <= 0 || $idDocente <= 0) {
    http_response_code(422);
    responderAsignacionGuardar(false, 'Selecciona un trabajo y un jurado válido.');
}

try {
    $db = $conexion;
    $db->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Validar trabajo de Fase 1 revisado por Administración
    |--------------------------------------------------------------------------
    */
    $stmtTrabajo = $db->prepare("
        SELECT
            t.id AS id_trabajo,
            t.id_estudiante,
            t.id_configuracion,
            t.estado_aprobacion,
            t.titulo_trabajo,

            pfc.id_programa,
            pfc.gestion,

            f.numero_fase,

            i.id AS id_inscripcion,

            CONCAT_WS(
                ' ',
                e.nombres,
                e.apellido_paterno,
                e.apellido_materno
            ) AS estudiante

        FROM trabajos t
        INNER JOIN programa_fase_config pfc
            ON pfc.id = t.id_configuracion
        INNER JOIN fases f
            ON f.id = pfc.id_fase
        INNER JOIN inscripciones i
            ON i.id_estudiante = t.id_estudiante
            AND i.id_programa = pfc.id_programa
        INNER JOIN usuarios e
            ON e.id = t.id_estudiante

        WHERE t.id = :id_trabajo
        LIMIT 1
    ");

    $stmtTrabajo->execute([
        ':id_trabajo' => $idTrabajo,
    ]);

    $trabajo = $stmtTrabajo->fetch(PDO::FETCH_ASSOC);

    if (!$trabajo) {
        throw new RuntimeException('No se encontró el trabajo seleccionado.');
    }

    $estadoTrabajo = strtoupper(trim((string) $trabajo['estado_aprobacion']));

    if ((int) $trabajo['numero_fase'] !== 1) {
        throw new RuntimeException('Solo se puede asignar jurado después de revisar la Fase 1.');
    }

    if (!in_array($estadoTrabajo, ['APROBADO', 'REVISADO'], true)) {
        throw new RuntimeException(
            'La Fase 1 debe estar marcada como Revisada antes de asignar jurado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Validar que el usuario seleccionado sea docente o tutor
    |--------------------------------------------------------------------------
    */
    $stmtDocente = $db->prepare("
        SELECT
            u.id,
            u.usuario,
            CONCAT_WS(
                ' ',
                u.nombres,
                u.apellido_paterno,
                u.apellido_materno
            ) AS nombre_completo,
            GROUP_CONCAT(
                DISTINCT r.nombre_rol
                ORDER BY r.nombre_rol
                SEPARATOR ' / '
            ) AS roles

        FROM usuarios u
        INNER JOIN usuario_rol ur
            ON ur.id_usuario = u.id
        INNER JOIN rol r
            ON r.id = ur.id_role

        WHERE u.id = :id_docente
          AND ur.id_role IN (3, 4)

        GROUP BY
            u.id,
            u.usuario,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno

        LIMIT 1
    ");

    $stmtDocente->execute([
        ':id_docente' => $idDocente,
    ]);

    $docente = $stmtDocente->fetch(PDO::FETCH_ASSOC);

    if (!$docente) {
        throw new RuntimeException(
            'El usuario seleccionado no está registrado como docente o tutor.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Buscar configuración activa de Fase 2
    |--------------------------------------------------------------------------
    */
    $stmtFaseDos = $db->prepare("
        SELECT
            pfc.id AS id_configuracion_fase_2,
            pfc.fecha_inicio_entrega,
            pfc.fecha_limite_entrega,
            pfc.fecha_limite_revision,
            pfc.fecha_devolucion_observaciones,
            pfc.nota_minima,
            pfc.tipo_trabajo,

            f.id AS id_fase_2,
            f.numero_fase,
            f.nombre_fase

        FROM programa_fase_config pfc
        INNER JOIN fases f
            ON f.id = pfc.id_fase

        WHERE pfc.id_programa = :id_programa
          AND pfc.gestion = :gestion
          AND f.numero_fase = 2
          AND pfc.estado = 'ACTIVO'
          AND f.estado = 1

        LIMIT 1
    ");

    $stmtFaseDos->execute([
        ':id_programa' => $trabajo['id_programa'],
        ':gestion' => $trabajo['gestion'],
    ]);

    $faseDos = $stmtFaseDos->fetch(PDO::FETCH_ASSOC);

    if (!$faseDos) {
        throw new RuntimeException(
            'No existe una configuración activa para la Fase 2 de este programa y gestión.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Crear o actualizar postulación técnica
    |--------------------------------------------------------------------------
    | Se usa únicamente para vincular la asignación de jurado con la inscripción.
    | No exige CI, carta de captación ni documentos previos.
    |--------------------------------------------------------------------------
    */
    $stmtPostulacion = $db->prepare("
        SELECT id
        FROM titulacion_postulaciones
        WHERE id_inscripcion = :id_inscripcion
        LIMIT 1
    ");

    $stmtPostulacion->execute([
        ':id_inscripcion' => $trabajo['id_inscripcion'],
    ]);

    $postulacion = $stmtPostulacion->fetch(PDO::FETCH_ASSOC);

    if ($postulacion) {
        $idPostulacion = (int) $postulacion['id'];

        $stmtActualizarPostulacion = $db->prepare("
            UPDATE titulacion_postulaciones
            SET
                estado_documental = 'NO_APLICA',
                estado_inscripcion = 'APROBADA',
                estado_jurado = 'ASIGNADO',
                estado_proceso = 'JURADO_ASIGNADO',
                aprobado_por = :id_administrador,
                aprobado_el = NOW()
            WHERE id = :id_postulacion
        ");

        $stmtActualizarPostulacion->execute([
            ':id_administrador' => $idAdministrador,
            ':id_postulacion' => $idPostulacion,
        ]);
    } else {
        $stmtCrearPostulacion = $db->prepare("
            INSERT INTO titulacion_postulaciones (
                id_inscripcion,
                estado_documental,
                estado_inscripcion,
                estado_jurado,
                estado_proceso,
                aprobado_por,
                aprobado_el,
                creado_por
            ) VALUES (
                :id_inscripcion,
                'NO_APLICA',
                'APROBADA',
                'ASIGNADO',
                'JURADO_ASIGNADO',
                :id_administrador,
                NOW(),
                :id_administrador
            )
        ");

        $stmtCrearPostulacion->execute([
            ':id_inscripcion' => $trabajo['id_inscripcion'],
            ':id_administrador' => $idAdministrador,
        ]);

        $idPostulacion = (int) $db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Mantener historial de jurados
    |--------------------------------------------------------------------------
    | Si existía uno anterior activo, queda como REASIGNADO.
    |--------------------------------------------------------------------------
    */
    $stmtReasignarAnterior = $db->prepare("
        UPDATE jurado_asignaciones
        SET estado = 'REASIGNADO'
        WHERE id_postulacion = :id_postulacion
          AND estado = 'ASIGNADO'
          AND id_docente <> :id_docente
    ");

    $stmtReasignarAnterior->execute([
        ':id_postulacion' => $idPostulacion,
        ':id_docente' => $idDocente,
    ]);

    $stmtJuradoActual = $db->prepare("
        SELECT id
        FROM jurado_asignaciones
        WHERE id_postulacion = :id_postulacion
          AND id_docente = :id_docente
          AND estado = 'ASIGNADO'
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmtJuradoActual->execute([
        ':id_postulacion' => $idPostulacion,
        ':id_docente' => $idDocente,
    ]);

    $juradoActual = $stmtJuradoActual->fetch(PDO::FETCH_ASSOC);

    if ($juradoActual) {
        $stmtActualizarJurado = $db->prepare("
            UPDATE jurado_asignaciones
            SET
                rol_jurado = 'JURADO',
                observacion = :observacion,
                asignado_por = :id_administrador,
                fecha_asignacion = NOW(),
                estado = 'ASIGNADO'
            WHERE id = :id_jurado
        ");

        $stmtActualizarJurado->execute([
            ':observacion' => $observacion ?: null,
            ':id_administrador' => $idAdministrador,
            ':id_jurado' => $juradoActual['id'],
        ]);
    } else {
        $stmtInsertarJurado = $db->prepare("
            INSERT INTO jurado_asignaciones (
                id_postulacion,
                id_docente,
                rol_jurado,
                estado,
                observacion,
                asignado_por,
                fecha_asignacion
            ) VALUES (
                :id_postulacion,
                :id_docente,
                'JURADO',
                'ASIGNADO',
                :observacion,
                :id_administrador,
                NOW()
            )
        ");

        $stmtInsertarJurado->execute([
            ':id_postulacion' => $idPostulacion,
            ':id_docente' => $idDocente,
            ':observacion' => $observacion ?: null,
            ':id_administrador' => $idAdministrador,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Habilitar Fase 2 para el participante
    |--------------------------------------------------------------------------
    */
    $stmtBuscarFaseDosEstudiante = $db->prepare("
        SELECT id
        FROM fase_estudiante_config
        WHERE id_estudiante = :id_estudiante
          AND id_configuracion = :id_configuracion
        LIMIT 1
    ");

    $stmtBuscarFaseDosEstudiante->execute([
        ':id_estudiante' => $trabajo['id_estudiante'],
        ':id_configuracion' => $faseDos['id_configuracion_fase_2'],
    ]);

    $faseDosEstudiante = $stmtBuscarFaseDosEstudiante->fetch(PDO::FETCH_ASSOC);

    $mensajeHabilitacion =
        'Fase 2 habilitada después de asignar jurado: ' .
        $docente['nombre_completo'] . '.';

    if ($faseDosEstudiante) {
        $stmtActualizarFaseDos = $db->prepare("
            UPDATE fase_estudiante_config
            SET
                estado = 'ACTIVO',
                fecha_inicio_entrega = :fecha_inicio_entrega,
                fecha_limite_entrega = :fecha_limite_entrega,
                fecha_limite_revision = :fecha_limite_revision,
                fecha_devolucion_observaciones = :fecha_devolucion_observaciones,
                observacion = :observacion,
                creado_por = :id_administrador
            WHERE id = :id_fase_estudiante
        ");

        $stmtActualizarFaseDos->execute([
            ':fecha_inicio_entrega' => $faseDos['fecha_inicio_entrega'],
            ':fecha_limite_entrega' => $faseDos['fecha_limite_entrega'],
            ':fecha_limite_revision' => $faseDos['fecha_limite_revision'],
            ':fecha_devolucion_observaciones' => $faseDos['fecha_devolucion_observaciones'],
            ':observacion' => $mensajeHabilitacion,
            ':id_administrador' => $idAdministrador,
            ':id_fase_estudiante' => $faseDosEstudiante['id'],
        ]);
    } else {
        $stmtCrearFaseDos = $db->prepare("
            INSERT INTO fase_estudiante_config (
                id_configuracion,
                id_estudiante,
                fecha_inicio_entrega,
                fecha_limite_entrega,
                fecha_limite_revision,
                fecha_devolucion_observaciones,
                estado,
                observacion,
                creado_por
            ) VALUES (
                :id_configuracion,
                :id_estudiante,
                :fecha_inicio_entrega,
                :fecha_limite_entrega,
                :fecha_limite_revision,
                :fecha_devolucion_observaciones,
                'ACTIVO',
                :observacion,
                :id_administrador
            )
        ");

        $stmtCrearFaseDos->execute([
            ':id_configuracion' => $faseDos['id_configuracion_fase_2'],
            ':id_estudiante' => $trabajo['id_estudiante'],
            ':fecha_inicio_entrega' => $faseDos['fecha_inicio_entrega'],
            ':fecha_limite_entrega' => $faseDos['fecha_limite_entrega'],
            ':fecha_limite_revision' => $faseDos['fecha_limite_revision'],
            ':fecha_devolucion_observaciones' => $faseDos['fecha_devolucion_observaciones'],
            ':observacion' => $mensajeHabilitacion,
            ':id_administrador' => $idAdministrador,
        ]);
    }

    $db->commit();

    responderAsignacionGuardar(
        true,
        'Jurado asignado y Fase 2 habilitada correctamente.',
        [
            'jurado' => $docente['nombre_completo'],
            'estudiante' => $trabajo['estudiante'],
            'fase_2' => $faseDos['nombre_fase'],
        ]
    );

} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);

    responderAsignacionGuardar(
        false,
        'No fue posible asignar el jurado y habilitar la Fase 2.'
    );
}