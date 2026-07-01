<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderGuardarRevision(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

function normalizarAccionAdministrativa(string $accion): ?string
{
    $accion = strtoupper(trim($accion));

    return match ($accion) {
        'VALIDAR', 'REVISAR', 'APROBAR' => 'VALIDAR',
        'OBSERVAR', 'RECHAZAR', 'DEVOLVER' => 'OBSERVAR',
        default => null,
    };
}

function crearEntregaAdministrativa(PDO $db, array $trabajo): array
{
    $stmtEntrega = $db->prepare("
        SELECT id, numero_version
        FROM trabajo_entregas
        WHERE id_trabajo = :id_trabajo
          AND es_vigente = 1
        ORDER BY numero_version DESC, id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $stmtEntrega->execute([
        ':id_trabajo' => (int) $trabajo['id_trabajo'],
    ]);

    $entrega = $stmtEntrega->fetch(PDO::FETCH_ASSOC);

    if ($entrega) {
        return $entrega;
    }

    $nombreArchivo = $trabajo['ruta_archivo']
        ? basename((string) $trabajo['ruta_archivo'])
        : null;

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
            enviado_el,
            actualizado_el
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
            :guardado_el,
            :enviado_el,
            NOW()
        )
    ");

    $fechaBase = $trabajo['fecha_presentacion'] ?: date('Y-m-d H:i:s');

    $stmtCrearEntrega->execute([
        ':id_trabajo' => (int) $trabajo['id_trabajo'],
        ':titulo_trabajo' => $trabajo['titulo_trabajo'],
        ':nombre_original' => $nombreArchivo,
        ':archivo_servidor' => $nombreArchivo,
        ':ruta_archivo' => $trabajo['ruta_archivo'],
        ':guardado_por' => (int) $trabajo['id_estudiante'],
        ':guardado_el' => $fechaBase,
        ':enviado_el' => $fechaBase,
    ]);

    return [
        'id' => (int) $db->lastInsertId(),
        'numero_version' => 1,
    ];
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderGuardarRevision(false, 'Tu sesión finalizó. Ingresa nuevamente al sistema.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderGuardarRevision(false, 'Acceso restringido.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responderGuardarRevision(false, 'Método no permitido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderGuardarRevision(false, 'No se pudo establecer la conexión local.');
}

$idAdministrador = (int) $_SESSION['id'];
$idTrabajo = (int) ($_POST['id_trabajo'] ?? 0);
$idDocente = (int) ($_POST['id_docente'] ?? 0);
$accionSolicitada = (string) ($_POST['accion'] ?? '');
$accion = normalizarAccionAdministrativa($accionSolicitada);
$comentario = trim((string) ($_POST['comentario'] ?? ''));

if ($idTrabajo <= 0 || $accion === null) {
    http_response_code(422);
    responderGuardarRevision(false, 'Los datos de revisión no son válidos.');
}

if ($accion === 'OBSERVAR' && mb_strlen($comentario) < 5) {
    http_response_code(422);
    responderGuardarRevision(
        false,
        'Registra observaciones de al menos 5 caracteres para el participante.'
    );
}

if ($accion === 'VALIDAR' && $idDocente <= 0) {
    http_response_code(422);
    responderGuardarRevision(
        false,
        'Selecciona el jurado responsable para las Fases 2 y 3.'
    );
}

try {
    $db = $conexion;
    $db->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Trabajo de Fase 1 en revisión
    |--------------------------------------------------------------------------
    */
    $stmtTrabajo = $db->prepare("
        SELECT
            t.id AS id_trabajo,
            t.id_estudiante,
            t.id_configuracion,
            t.titulo_trabajo,
            t.ruta_archivo,
            t.fecha_presentacion,
            t.estado_aprobacion,

            pfc.id_programa,
            pfc.gestion,

            f.numero_fase,

            i.id AS id_inscripcion,

            CONCAT_WS(
                ' ',
                u.nombres,
                u.apellido_paterno,
                u.apellido_materno
            ) AS estudiante

        FROM trabajos t
        INNER JOIN programa_fase_config pfc
            ON pfc.id = t.id_configuracion
        INNER JOIN fases f
            ON f.id = pfc.id_fase
        INNER JOIN usuarios u
            ON u.id = t.id_estudiante
        INNER JOIN inscripciones i
            ON i.id_estudiante = t.id_estudiante
            AND i.id_programa = pfc.id_programa
        WHERE t.id = :id_trabajo
        LIMIT 1
        FOR UPDATE
    ");

    $stmtTrabajo->execute([
        ':id_trabajo' => $idTrabajo,
    ]);

    $trabajo = $stmtTrabajo->fetch(PDO::FETCH_ASSOC);

    if (!$trabajo) {
        throw new RuntimeException('No se encontró la propuesta seleccionada.');
    }

    if ((int) $trabajo['numero_fase'] !== 1) {
        throw new RuntimeException('Este módulo solo revisa propuestas de la Fase 1.');
    }

    $estadoActualTrabajo = strtoupper(
    trim((string) $trabajo['estado_aprobacion'])
);

    if (!in_array($estadoActualTrabajo, ['EN_REVISION', 'CORREGIDO'], true)) {
        $db->rollBack();

        responderGuardarRevision(
            false,
            'Solo se pueden revisar trabajos en revisión o con versión corregida.'
        );
    }

    $entrega = crearEntregaAdministrativa($db, $trabajo);

    /*
    |--------------------------------------------------------------------------
    | 2. Observación: mantiene Fase 1 activa para reenvío
    |--------------------------------------------------------------------------
    */
if ($accion === 'OBSERVAR') {
    $stmtTrabajoObservado = $db->prepare("
        UPDATE trabajos
        SET
            estado_aprobacion = 'OBSERVADO',
            calificacion_final = NULL,
            comentario_revision = :comentario,
            fecha_revision = NOW(),
            actualizado_el = NOW()
        WHERE id = :id_trabajo
    ");

    $stmtTrabajoObservado->execute([
        ':comentario' => $comentario,
        ':id_trabajo' => (int) $trabajo['id_trabajo'],
    ]);

    $stmtEntregaObservada = $db->prepare("
        UPDATE trabajo_entregas
        SET
            estado_entrega = 'OBSERVADO',
            actualizado_el = NOW()
        WHERE id = :id_entrega
    ");

    $stmtEntregaObservada->execute([
        ':id_entrega' => (int) $entrega['id'],
    ]);

    $stmtHistorialObservado = $db->prepare("
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
            NULL,
            :id_revisor,
            'OBSERVADO',
            NULL,
            :comentario,
            0,
            'ADMINISTRACION',
            NOW()
        )
    ");

    $stmtHistorialObservado->execute([
        ':id_entrega' => (int) $entrega['id'],
        ':id_revisor' => $idAdministrador,
        ':comentario' => $comentario,
    ]);

    $stmtFaseUnoActiva = $db->prepare("
        UPDATE fase_estudiante_config
        SET
            estado = 'ACTIVO',
            observacion = :observacion,
            fecha_actualizacion = NOW()
        WHERE id_estudiante = :id_estudiante
          AND id_configuracion = :id_configuracion
    ");

    $stmtFaseUnoActiva->execute([
        ':observacion' => 'Observaciones de Administración: ' . $comentario,
        ':id_estudiante' => (int) $trabajo['id_estudiante'],
        ':id_configuracion' => (int) $trabajo['id_configuracion'],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Crear solicitud para Correcciones y plazos
    |--------------------------------------------------------------------------
    */
    $stmtFaseEstudiante = $db->prepare("
        SELECT id
        FROM fase_estudiante_config
        WHERE id_estudiante = :id_estudiante
          AND id_configuracion = :id_configuracion
        LIMIT 1
        FOR UPDATE
    ");

    $stmtFaseEstudiante->execute([
        ':id_estudiante' => (int) $trabajo['id_estudiante'],
        ':id_configuracion' => (int) $trabajo['id_configuracion'],
    ]);

    $faseEstudiante = $stmtFaseEstudiante->fetch(PDO::FETCH_ASSOC);

    if (!$faseEstudiante) {
        throw new RuntimeException(
            'No se encontró la configuración individual de Fase 1 para crear la corrección.'
        );
    }

    $stmtControlActivo = $db->prepare("
        SELECT id
        FROM control_reentregas
        WHERE id_trabajo = :id_trabajo
          AND estado IN (
              'PENDIENTE_AUTORIZACION',
              'AUTORIZADA',
              'REENTREGADA'
          )
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $stmtControlActivo->execute([
        ':id_trabajo' => (int) $trabajo['id_trabajo'],
    ]);

    $controlActivo = $stmtControlActivo->fetch(PDO::FETCH_ASSOC);

    if (!$controlActivo) {
        $stmtUltimoCiclo = $db->prepare("
            SELECT COALESCE(MAX(ciclo), 0) AS ultimo_ciclo
            FROM control_reentregas
            WHERE id_trabajo = :id_trabajo
        ");

        $stmtUltimoCiclo->execute([
            ':id_trabajo' => (int) $trabajo['id_trabajo'],
        ]);

        $ultimoCiclo = (int) $stmtUltimoCiclo->fetchColumn();

        $stmtCrearControl = $db->prepare("
            INSERT INTO control_reentregas (
                id_trabajo,
                id_fase_estudiante_config,
                ciclo,
                estado,
                motivo,
                creado_el,
                actualizado_el
            ) VALUES (
                :id_trabajo,
                :id_fase_estudiante_config,
                :ciclo,
                'PENDIENTE_AUTORIZACION',
                :motivo,
                NOW(),
                NOW()
            )
        ");

        $stmtCrearControl->execute([
            ':id_trabajo' => (int) $trabajo['id_trabajo'],
            ':id_fase_estudiante_config' => (int) $faseEstudiante['id'],
            ':ciclo' => $ultimoCiclo + 1,
            ':motivo' => 'Observación de Fase 1: ' . $comentario,
        ]);
    }

    $db->commit();

    responderGuardarRevision(
        true,
        'La propuesta fue observada. Ahora aparece en Correcciones y plazos para autorizar la nueva fecha de entrega.'
    );
}
    $stmtDocente = $db->prepare("
        SELECT
            u.id,
            CONCAT_WS(
                ' ',
                u.nombres,
                u.apellido_paterno,
                u.apellido_materno
            ) AS nombre_completo
        FROM usuarios u
        INNER JOIN usuario_rol ur
            ON ur.id_usuario = u.id
        WHERE u.id = :id_docente
          AND ur.id_role IN (3, 4)
          AND COALESCE(u.estado_cuenta, 'ACTIVO') <> 'INACTIVO'
        GROUP BY
            u.id,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno
        LIMIT 1
    ");

    $stmtDocente->execute([
        ':id_docente' => $idDocente,
    ]);

    $jurado = $stmtDocente->fetch(PDO::FETCH_ASSOC);

    if (!$jurado) {
        throw new RuntimeException(
            'El usuario seleccionado no está habilitado como jurado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Configuración activa de Fase 2
    |--------------------------------------------------------------------------
    */
    $stmtFaseDos = $db->prepare("
        SELECT
            pfc.id AS id_configuracion_fase_2,
            pfc.fecha_inicio_entrega,
            pfc.fecha_limite_entrega,
            pfc.fecha_limite_revision,
            pfc.fecha_devolucion_observaciones,

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
        ':id_programa' => (int) $trabajo['id_programa'],
        ':gestion' => $trabajo['gestion'],
    ]);

    $faseDos = $stmtFaseDos->fetch(PDO::FETCH_ASSOC);

    if (!$faseDos) {
        throw new RuntimeException(
            'No existe una configuración activa para la Fase 2 de este programa.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Postulación técnica: una por inscripción
    |--------------------------------------------------------------------------
    */
    $stmtPostulacion = $db->prepare("
        SELECT id
        FROM titulacion_postulaciones
        WHERE id_inscripcion = :id_inscripcion
        LIMIT 1
        FOR UPDATE
    ");

    $stmtPostulacion->execute([
        ':id_inscripcion' => (int) $trabajo['id_inscripcion'],
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
                aprobado_el = NOW(),
                actualizado_el = NOW()
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
                creado_por,
                creado_el,
                actualizado_el
            ) VALUES (
                :id_inscripcion,
                'NO_APLICA',
                'APROBADA',
                'ASIGNADO',
                'JURADO_ASIGNADO',
                :id_administrador,
                NOW(),
                :id_administrador,
                NOW(),
                NOW()
            )
        ");

        $stmtCrearPostulacion->execute([
            ':id_inscripcion' => (int) $trabajo['id_inscripcion'],
            ':id_administrador' => $idAdministrador,
        ]);

        $idPostulacion = (int) $db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Jurado único para Fases 2 y 3
    |--------------------------------------------------------------------------
    | Reasignaciones posteriores conservan historial.
    |--------------------------------------------------------------------------
    */
    $stmtReasignarAnterior = $db->prepare("
        UPDATE jurado_asignaciones
        SET
            estado = 'REASIGNADO',
            actualizado_el = NOW()
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
        ORDER BY fecha_asignacion DESC, id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $stmtJuradoActual->execute([
        ':id_postulacion' => $idPostulacion,
        ':id_docente' => $idDocente,
    ]);

    $juradoActual = $stmtJuradoActual->fetch(PDO::FETCH_ASSOC);

    $observacionJurado = $comentario !== ''
        ? $comentario
        : 'Jurado responsable de las Fases 2 y 3.';

    if ($juradoActual) {
        $stmtActualizarJurado = $db->prepare("
            UPDATE jurado_asignaciones
            SET
                rol_jurado = 'JURADO',
                estado = 'ASIGNADO',
                observacion = :observacion,
                asignado_por = :id_administrador,
                fecha_asignacion = NOW(),
                actualizado_el = NOW()
            WHERE id = :id_jurado
        ");

        $stmtActualizarJurado->execute([
            ':observacion' => $observacionJurado,
            ':id_administrador' => $idAdministrador,
            ':id_jurado' => (int) $juradoActual['id'],
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
                fecha_asignacion,
                actualizado_el
            ) VALUES (
                :id_postulacion,
                :id_docente,
                'JURADO',
                'ASIGNADO',
                :observacion,
                :id_administrador,
                NOW(),
                NOW()
            )
        ");

        $stmtInsertarJurado->execute([
            ':id_postulacion' => $idPostulacion,
            ':id_docente' => $idDocente,
            ':observacion' => $observacionJurado,
            ':id_administrador' => $idAdministrador,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Validar Fase 1 y habilitar Fase 2
    |--------------------------------------------------------------------------
    */
    $comentarioFinal = $comentario !== ''
        ? $comentario
        : 'Propuesta validada por Administración Académica.';

    $stmtTrabajoValidado = $db->prepare("
        UPDATE trabajos
        SET
            estado_aprobacion = 'REVISADO',
            calificacion_final = NULL,
            comentario_revision = :comentario,
            fecha_revision = NOW(),
            actualizado_el = NOW()
        WHERE id = :id_trabajo
    ");

    $stmtTrabajoValidado->execute([
        ':comentario' => $comentarioFinal,
        ':id_trabajo' => (int) $trabajo['id_trabajo'],
    ]);

    $stmtEntregaValidada = $db->prepare("
        UPDATE trabajo_entregas
        SET
            estado_entrega = 'APROBADO',
            actualizado_el = NOW()
        WHERE id = :id_entrega
    ");

    $stmtEntregaValidada->execute([
        ':id_entrega' => (int) $entrega['id'],
    ]);

    $stmtHistorialValidado = $db->prepare("
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
            NULL,
            :id_revisor,
            'REVISADO',
            NULL,
            :comentario,
            0,
            'ADMINISTRACION',
            NOW()
        )
    ");

    $stmtHistorialValidado->execute([
        ':id_entrega' => (int) $entrega['id'],
        ':id_revisor' => $idAdministrador,
        ':comentario' => $comentarioFinal,
    ]);

    $stmtCerrarFaseUno = $db->prepare("
        UPDATE fase_estudiante_config
        SET
            estado = 'APROBADO',
            observacion = :observacion,
            fecha_actualizacion = NOW()
        WHERE id_estudiante = :id_estudiante
          AND id_configuracion = :id_configuracion
    ");

    $stmtCerrarFaseUno->execute([
        ':observacion' => 'Fase 1 validada por Administración. Jurado asignado: ' . $jurado['nombre_completo'] . '.',
        ':id_estudiante' => (int) $trabajo['id_estudiante'],
        ':id_configuracion' => (int) $trabajo['id_configuracion'],
    ]);

    $stmtFaseDosEstudiante = $db->prepare("
        SELECT id
        FROM fase_estudiante_config
        WHERE id_estudiante = :id_estudiante
          AND id_configuracion = :id_configuracion
        LIMIT 1
        FOR UPDATE
    ");

    $stmtFaseDosEstudiante->execute([
        ':id_estudiante' => (int) $trabajo['id_estudiante'],
        ':id_configuracion' => (int) $faseDos['id_configuracion_fase_2'],
    ]);

    $faseDosEstudiante = $stmtFaseDosEstudiante->fetch(PDO::FETCH_ASSOC);

    $observacionFaseDos =
        'Fase 2 habilitada. Jurado responsable para Fases 2 y 3: ' .
        $jurado['nombre_completo'] . '.';

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
                fecha_actualizacion = NOW()
            WHERE id = :id_fase_estudiante
        ");

        $stmtActualizarFaseDos->execute([
            ':fecha_inicio_entrega' => $faseDos['fecha_inicio_entrega'],
            ':fecha_limite_entrega' => $faseDos['fecha_limite_entrega'],
            ':fecha_limite_revision' => $faseDos['fecha_limite_revision'],
            ':fecha_devolucion_observaciones' => $faseDos['fecha_devolucion_observaciones'],
            ':observacion' => $observacionFaseDos,
            ':id_fase_estudiante' => (int) $faseDosEstudiante['id'],
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
                :id_administrador,
                NOW(),
                NOW()
            )
        ");

        $stmtCrearFaseDos->execute([
            ':id_configuracion' => (int) $faseDos['id_configuracion_fase_2'],
            ':id_estudiante' => (int) $trabajo['id_estudiante'],
            ':fecha_inicio_entrega' => $faseDos['fecha_inicio_entrega'],
            ':fecha_limite_entrega' => $faseDos['fecha_limite_entrega'],
            ':fecha_limite_revision' => $faseDos['fecha_limite_revision'],
            ':fecha_devolucion_observaciones' => $faseDos['fecha_devolucion_observaciones'],
            ':observacion' => $observacionFaseDos,
            ':id_administrador' => $idAdministrador,
        ]);
    }

    $db->commit();

    responderGuardarRevision(
        true,
        'Propuesta validada. Jurado asignado para Fases 2 y 3, y Fase 2 habilitada correctamente.',
        [
            'jurado' => $jurado['nombre_completo'],
            'fase_2_habilitada' => true,
        ]
    );
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);

    responderGuardarRevision(
        false,
        $e->getMessage() ?: 'No fue posible guardar la revisión administrativa.'
    );
}
