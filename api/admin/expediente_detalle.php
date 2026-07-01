<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderExpediente(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

function normalizarEstadoExpediente(?string $estado): string
{
    $estado = strtoupper(trim((string) $estado));

    $estado = str_replace(
        ['Á', 'É', 'Í', 'Ó', 'Ú', ' '],
        ['A', 'E', 'I', 'O', 'U', '_'],
        $estado
    );

    $equivalencias = [
        'APROBADO' => 'REVISADO',
        'APROBADA' => 'REVISADO',
        'REVISADO' => 'REVISADO',
        'REVISADA' => 'REVISADO',

        'RECHAZADO' => 'OBSERVADO',
        'RECHAZADA' => 'OBSERVADO',
        'OBSERVADO' => 'OBSERVADO',
        'OBSERVADA' => 'OBSERVADO',

        'CORREGIDO' => 'CORREGIDO',
        'CORREGIDA' => 'CORREGIDO',

        'EN_REVISION' => 'EN_REVISION',
        'BORRADOR' => 'BORRADOR',
        'HABILITADA' => 'HABILITADA',
        'PENDIENTE' => 'PENDIENTE',
    ];

    return $equivalencias[$estado] ?? $estado;
}

function etiquetaEstadoExpediente(string $estado): string
{
    $etiquetas = [
        'PENDIENTE' => 'Pendiente de habilitación',
        'HABILITADA' => 'Habilitada',
        'BORRADOR' => 'Borrador',
        'EN_REVISION' => 'En revisión',
        'OBSERVADO' => 'Con observaciones',
        'CORREGIDO' => 'Versión corregida',
        'REVISADO' => 'Validado',
    ];

    return $etiquetas[$estado] ?? 'Sin estado';
}

function resolverAccionFase(
    int $numeroFase,
    string $estado,
    bool $tieneTrabajo,
    bool $tieneJurado,
    bool $fase2Revisada
): array {
    $accion = 'SIN_ACCION';
    $texto = 'Sin acciones pendientes';
    $responsable = 'Administración Académica';

    if ($numeroFase === 1) {
        if (!$tieneTrabajo || $estado === 'HABILITADA' || $estado === 'BORRADOR') {
            $accion = 'ESPERAR_PROPUESTA';
            $texto = 'Esperar propuesta inicial del participante';
            $responsable = 'Participante';
        } elseif ($estado === 'EN_REVISION') {
            $accion = 'REVISAR_FASE_1';
            $texto = 'Revisar propuesta de Fase 1';
        } elseif ($estado === 'OBSERVADO') {
            $accion = 'ESPERAR_CORRECCION_F1';
            $texto = 'Esperar corrección de propuesta';
            $responsable = 'Participante';
        } elseif ($estado === 'REVISADO') {
            if (!$tieneJurado) {
                $accion = 'ASIGNAR_JURADO';
                $texto = 'Asignar jurado y habilitar Fase 2';
            } else {
                $accion = 'FASE_1_COMPLETADA';
                $texto = 'Fase 1 completada';
            }
        }
    }

    if ($numeroFase === 2) {
        if (!$tieneTrabajo || $estado === 'HABILITADA' || $estado === 'BORRADOR') {
            $accion = 'ESPERAR_MONOGRAFIA';
            $texto = 'Esperar entrega de monografía';
            $responsable = 'Participante';
        } elseif ($estado === 'EN_REVISION' || $estado === 'CORREGIDO') {
            $accion = 'REVISAR_FASE_2';
            $texto = 'Jurado debe revisar la monografía';
            $responsable = 'Jurado';
        } elseif ($estado === 'OBSERVADO') {
            $accion = 'GESTIONAR_CORRECCION';
            $texto = 'Gestionar corrección y plazo';
        } elseif ($estado === 'REVISADO') {
            $accion = 'HABILITAR_FASE_3';
            $texto = 'Habilitar Fase 3';
        }
    }

    if ($numeroFase === 3) {
        if (!$tieneTrabajo) {
            if ($fase2Revisada) {
                $accion = 'HABILITAR_FASE_3';
                $texto = 'Habilitar Fase 3';
            } else {
                $accion = 'ESPERAR_FASE_2';
                $texto = 'Pendiente de validación de Fase 2';
            }
        } elseif ($estado === 'HABILITADA' || $estado === 'BORRADOR') {
            $accion = 'ESPERAR_VERSION_FINAL';
            $texto = 'Esperar versión final del participante';
            $responsable = 'Participante';
        } elseif ($estado === 'EN_REVISION' || $estado === 'CORREGIDO') {
            $accion = 'EVALUACION_FINAL';
            $texto = 'Jurado debe emitir valoración final';
            $responsable = 'Jurado';
        } elseif ($estado === 'OBSERVADO') {
            $accion = 'GESTIONAR_CORRECCION';
            $texto = 'Gestionar corrección y plazo';
        } elseif ($estado === 'REVISADO') {
            $accion = 'CIERRE_EMPRASTE';
            $texto = 'Registrar cierre y código de empaste';
        }
    }

    return [
        'codigo' => $accion,
        'texto' => $texto,
        'responsable' => $responsable,
    ];
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderExpediente(false, 'Tu sesión finalizó. Ingresa nuevamente.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderExpediente(false, 'Acceso restringido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderExpediente(false, 'No se pudo establecer la conexión local.');
}

$idInscripcion = (int) ($_GET['id_inscripcion'] ?? 0);

if ($idInscripcion <= 0) {
    http_response_code(422);
    responderExpediente(false, 'No se identificó la inscripción del participante.');
}

try {
    $db = $conexion;

    /*
    |--------------------------------------------------------------------------
    | Contexto general del participante y programa
    |--------------------------------------------------------------------------
    */
    $stmtContexto = $db->prepare("
        SELECT
            i.id AS id_inscripcion,
            i.id_inscripcion_externa,
            i.estado_cartera,
            i.estado_academico,
            i.estado_acceso,
            i.observacion_cartera,
            i.observacion_academica,
            i.motivo_bloqueo,

            u.id AS id_estudiante,
            u.codigo_participante_externo,
            u.ci,
            u.usuario,
            u.correo,
            u.celular,
            u.profesion_postgrado,

            CONCAT_WS(
                ' ',
                u.nombres,
                u.apellido_paterno,
                u.apellido_materno
            ) AS estudiante,

            p.id AS id_programa,
            p.codigo_programa_externo,
            p.nombre_programa,
            p.tipo AS tipo_programa,
            p.gestion_externa,
            p.version_programa_externa,
            p.id_sede_externa,

            COALESCE(se.nombre_sede, 'Sin sede registrada') AS nombre_sede,
            COALESCE(se.ciudad, '') AS ciudad_sede,

            tp.id AS id_postulacion,
            tp.estado_documental,
            tp.estado_inscripcion,
            tp.estado_jurado,
            tp.estado_proceso,
            tp.observacion_documental,
            tp.observacion_inscripcion

        FROM inscripciones i
        INNER JOIN usuarios u
            ON u.id = i.id_estudiante
        INNER JOIN programa p
            ON p.id = i.id_programa
        LEFT JOIN siget_externa.ext_sedes se
            ON se.id_sede = p.id_sede_externa
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
        responderExpediente(false, 'No se encontró la inscripción solicitada.');
    }

    /*
    |--------------------------------------------------------------------------
    | Jurado asignado una sola vez para Fases 2 y 3
    |--------------------------------------------------------------------------
    */
    $jurado = null;

    if (!empty($contexto['id_postulacion'])) {
        $stmtJurado = $db->prepare("
            SELECT
                ja.id AS id_asignacion,
                ja.id_docente,
                ja.rol_jurado,
                ja.estado AS estado_asignacion,
                ja.observacion,
                ja.fecha_asignacion,

                CONCAT_WS(
                    ' ',
                    uj.nombres,
                    uj.apellido_paterno,
                    uj.apellido_materno
                ) AS nombre_completo,

                uj.usuario,
                uj.correo,
                uj.celular

            FROM jurado_asignaciones ja
            INNER JOIN usuarios uj
                ON uj.id = ja.id_docente
            WHERE ja.id_postulacion = :id_postulacion
              AND UPPER(TRIM(COALESCE(ja.estado, ''))) IN ('ASIGNADO', 'ACTIVO')
            ORDER BY ja.fecha_asignacion DESC, ja.id DESC
            LIMIT 1
        ");

        $stmtJurado->execute([
            ':id_postulacion' => $contexto['id_postulacion'],
        ]);

        $jurado = $stmtJurado->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | Respaldo para asignaciones anteriores guardadas en trabajo_docente
    |--------------------------------------------------------------------------
    */
    if (!$jurado) {
        $stmtJuradoRespaldo = $db->prepare("
            SELECT
                td.id_docente,
                td.tipo_asignacion,
                td.fecha_asignacion,

                CONCAT_WS(
                    ' ',
                    uj.nombres,
                    uj.apellido_paterno,
                    uj.apellido_materno
                ) AS nombre_completo,

                uj.usuario,
                uj.correo,
                uj.celular

            FROM trabajo_docente td
            INNER JOIN trabajos t
                ON t.id = td.id_trabajo
            INNER JOIN programa_fase_config pfc
                ON pfc.id = t.id_configuracion
            INNER JOIN fases f
                ON f.id = pfc.id_fase
            INNER JOIN usuarios uj
                ON uj.id = td.id_docente

            WHERE t.id_estudiante = :id_estudiante
              AND pfc.id_programa = :id_programa
              AND f.numero_fase IN (2, 3)
              AND UPPER(TRIM(COALESCE(td.tipo_asignacion, ''))) IN (
                  'JURADO',
                  'EVALUADOR',
                  'REVISOR'
              )

            ORDER BY td.fecha_asignacion DESC, td.id_docente DESC
            LIMIT 1
        ");

        $stmtJuradoRespaldo->execute([
            ':id_estudiante' => $contexto['id_estudiante'],
            ':id_programa' => $contexto['id_programa'],
        ]);

        $jurado = $stmtJuradoRespaldo->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | Configuración de Fases 1, 2 y 3 para el programa
    |--------------------------------------------------------------------------
    */
    $sqlFases = "
        SELECT
            pfc.id AS id_configuracion,
            pfc.gestion,
            pfc.tipo_trabajo,
            pfc.fecha_inicio_entrega,
            pfc.fecha_limite_entrega,
            pfc.fecha_limite_revision,
            pfc.fecha_devolucion_observaciones,
            pfc.nota_minima,
            pfc.estado AS estado_configuracion,

            f.id AS id_fase,
            f.numero_fase,
            f.nombre_fase,

            fec.id AS id_habilitacion,
            fec.estado AS estado_habilitacion,
            fec.fecha_inicio_entrega AS fecha_inicio_individual,
            fec.fecha_limite_entrega AS fecha_limite_individual,
            fec.fecha_limite_revision AS fecha_revision_individual,
            fec.fecha_devolucion_observaciones AS fecha_devolucion_individual,
            fec.observacion AS observacion_habilitacion

        FROM programa_fase_config pfc
        INNER JOIN fases f
            ON f.id = pfc.id_fase
        LEFT JOIN fase_estudiante_config fec
            ON fec.id_configuracion = pfc.id
            AND fec.id_estudiante = :id_estudiante

        WHERE pfc.id_programa = :id_programa
          AND UPPER(TRIM(COALESCE(pfc.estado, ''))) = 'ACTIVO'
    ";

    $parametrosFases = [
        ':id_estudiante' => $contexto['id_estudiante'],
        ':id_programa' => $contexto['id_programa'],
    ];

    if (!empty($contexto['gestion_externa'])) {
        $sqlFases .= " AND CAST(pfc.gestion AS CHAR) = :gestion";
        $parametrosFases[':gestion'] = (string) $contexto['gestion_externa'];
    }

    $sqlFases .= " ORDER BY f.numero_fase ASC";

    $stmtFases = $db->prepare($sqlFases);
    $stmtFases->execute($parametrosFases);

    $fasesBase = $stmtFases->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Consultas reutilizables para el detalle de cada fase
    |--------------------------------------------------------------------------
    */
    $stmtTrabajo = $db->prepare("
        SELECT
            t.id,
            t.titulo_trabajo,
            t.fecha_presentacion,
            t.estado_aprobacion,
            t.calificacion_final,
            t.comentario_revision,
            t.fecha_revision,
            t.ruta_archivo,
            t.actualizado_el
        FROM trabajos t
        WHERE t.id_estudiante = :id_estudiante
          AND t.id_configuracion = :id_configuracion
        ORDER BY t.id DESC
        LIMIT 1
    ");

    $stmtEntrega = $db->prepare("
        SELECT
            te.id,
            te.numero_version,
            te.titulo_trabajo,
            te.nombre_original,
            te.archivo_servidor,
            te.ruta_archivo,
            te.mime_type,
            te.tamano_bytes,
            te.estado_entrega,
            te.es_vigente,
            te.guardado_el,
            te.enviado_el,
            te.actualizado_el
        FROM trabajo_entregas te
        WHERE te.id_trabajo = :id_trabajo
        ORDER BY
            te.es_vigente DESC,
            te.numero_version DESC,
            te.id DESC
        LIMIT 1
    ");

    $stmtRevision = $db->prepare("
        SELECT
            tr.id,
            tr.decision,
            tr.calificacion,
            tr.comentario,
            tr.es_revision_final,
            tr.origen,
            tr.fecha_revision,

            CONCAT_WS(
                ' ',
                ur.nombres,
                ur.apellido_paterno,
                ur.apellido_materno
            ) AS revisor

        FROM trabajo_revisiones tr
        LEFT JOIN usuarios ur
            ON ur.id = tr.id_revisor
        WHERE tr.id_entrega = :id_entrega
        ORDER BY tr.fecha_revision DESC, tr.id DESC
        LIMIT 1
    ");

    $stmtControl = $db->prepare("
        SELECT
            cr.id,
            cr.ciclo,
            cr.estado,
            cr.fecha_autorizacion,
            cr.fecha_limite_correccion,
            cr.fecha_reentrega,
            cr.motivo,
            cr.observacion_cierre,
            cr.creado_el,
            cr.actualizado_el
        FROM control_reentregas cr
        WHERE cr.id_trabajo = :id_trabajo
        ORDER BY cr.ciclo DESC, cr.id DESC
        LIMIT 1
    ");

    $fases = [];

    foreach ($fasesBase as $fase) {
        $trabajo = null;
        $entrega = null;
        $revision = null;
        $controlReentrega = null;

        $stmtTrabajo->execute([
            ':id_estudiante' => $contexto['id_estudiante'],
            ':id_configuracion' => $fase['id_configuracion'],
        ]);

        $trabajo = $stmtTrabajo->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($trabajo) {
            $stmtEntrega->execute([
                ':id_trabajo' => $trabajo['id'],
            ]);

            $entrega = $stmtEntrega->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($entrega) {
                $stmtRevision->execute([
                    ':id_entrega' => $entrega['id'],
                ]);

                $revision = $stmtRevision->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            $stmtControl->execute([
                ':id_trabajo' => $trabajo['id'],
            ]);

            $controlReentrega = $stmtControl->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $estado = 'PENDIENTE';

        if ($trabajo) {
            $estado = normalizarEstadoExpediente(
                $trabajo['estado_aprobacion'] ?? ''
            );
        } elseif (
            strtoupper(trim((string) ($fase['estado_habilitacion'] ?? ''))) === 'ACTIVO'
        ) {
            $estado = 'HABILITADA';
        }

        $fases[] = [
            'id_configuracion' => (int) $fase['id_configuracion'],
            'id_fase' => (int) $fase['id_fase'],
            'numero_fase' => (int) $fase['numero_fase'],
            'nombre_fase' => $fase['nombre_fase'],
            'tipo_trabajo' => $fase['tipo_trabajo'],

            'estado_configuracion' => $fase['estado_configuracion'],
            'estado_habilitacion' => $fase['estado_habilitacion'],
            'id_habilitacion' => $fase['id_habilitacion'],

            'fecha_inicio_entrega' => $fase['fecha_inicio_individual']
                ?: $fase['fecha_inicio_entrega'],

            'fecha_limite_entrega' => $fase['fecha_limite_individual']
                ?: $fase['fecha_limite_entrega'],

            'fecha_limite_revision' => $fase['fecha_revision_individual']
                ?: $fase['fecha_limite_revision'],

            'fecha_devolucion_observaciones' => $fase['fecha_devolucion_individual']
                ?: $fase['fecha_devolucion_observaciones'],

            'observacion_habilitacion' => $fase['observacion_habilitacion'],

            'estado' => $estado,
            'estado_etiqueta' => etiquetaEstadoExpediente($estado),

            'trabajo' => $trabajo,
            'entrega' => $entrega,
            'ultima_revision' => $revision,
            'control_reentrega' => $controlReentrega,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Identificar si Fase 2 fue validada, para definir la acción de Fase 3
    |--------------------------------------------------------------------------
    */
    $fase2Revisada = false;

    foreach ($fases as $fase) {
        if (
            (int) $fase['numero_fase'] === 2 &&
            $fase['estado'] === 'REVISADO'
        ) {
            $fase2Revisada = true;
            break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Acciones y responsable por fase
    |--------------------------------------------------------------------------
    */
    foreach ($fases as &$fase) {
        $accion = resolverAccionFase(
            (int) $fase['numero_fase'],
            $fase['estado'],
            !empty($fase['trabajo']),
            !empty($jurado),
            $fase2Revisada
        );

        if (
            in_array((int) $fase['numero_fase'], [2, 3], true) &&
            !empty($jurado) &&
            $accion['responsable'] === 'Jurado'
        ) {
            $accion['responsable'] = $jurado['nombre_completo']
                ?? 'Jurado asignado';
        }

        $fase['accion'] = $accion;
    }

    unset($fase);

    /*
    |--------------------------------------------------------------------------
    | Próxima acción global del expediente
    |--------------------------------------------------------------------------
    */
    $prioridades = [
        'REVISAR_FASE_1',
        'ASIGNAR_JURADO',
        'GESTIONAR_CORRECCION',
        'HABILITAR_FASE_3',
        'CIERRE_EMPRASTE',
        'HABILITAR_FASE_2',
        'EVALUACION_FINAL',
        'REVISAR_FASE_2',
    ];

    $siguienteAccion = null;

    foreach ($prioridades as $codigo) {
        foreach ($fases as $fase) {
            if (($fase['accion']['codigo'] ?? '') === $codigo) {
                $siguienteAccion = [
                    'fase' => $fase['numero_fase'],
                    'codigo' => $codigo,
                    'texto' => $fase['accion']['texto'],
                    'responsable' => $fase['accion']['responsable'],
                ];

                break 2;
            }
        }
    }

    if (!$siguienteAccion) {
        $siguienteAccion = [
            'fase' => null,
            'codigo' => 'SIN_ACCION',
            'texto' => 'No existen acciones pendientes.',
            'responsable' => 'Administración Académica',
        ];
    }

    responderExpediente(true, 'Expediente cargado correctamente.', [
        'contexto' => $contexto,
        'jurado' => $jurado,
        'fases' => $fases,
        'siguiente_accion' => $siguienteAccion,
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    responderExpediente(
        false,
        'No fue posible cargar el expediente individual de titulación.'
    );
}