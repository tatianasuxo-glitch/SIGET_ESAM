<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderRevision(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

function estadoNormalizadoRevision(?string $estado): string
{
    $estado = strtoupper(trim((string) $estado));

    $estado = str_replace(
        ['Á', 'É', 'Í', 'Ó', 'Ú', ' '],
        ['A', 'E', 'I', 'O', 'U', '_'],
        $estado
    );

    return match ($estado) {
        'EN_REVISION' => 'EN_REVISION',
        'CORREGIDO', 'CORREGIDA' => 'CORREGIDO',
        'OBSERVADO', 'OBSERVADA', 'RECHAZADO', 'RECHAZADA' => 'OBSERVADO',
        'APROBADO', 'APROBADA', 'REVISADO', 'REVISADA' => 'REVISADO',
        'BORRADOR' => 'BORRADOR',
        default => $estado,
    };
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderRevision(false, 'Tu sesión finalizó. Ingresa nuevamente al sistema.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderRevision(false, 'Acceso restringido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderRevision(false, 'No se pudo establecer la conexión local.');
}

$filtro = strtolower(trim((string) ($_GET['estado'] ?? 'pendientes')));

$mapaFiltros = [
    'pendientes' => 'EN_REVISION',
    'observados' => 'OBSERVADO',
    'revisados' => 'REVISADO',
    'todos' => null,
];

if (!array_key_exists($filtro, $mapaFiltros)) {
    $filtro = 'pendientes';
}

try {
    $db = $conexion;

    /*
    |--------------------------------------------------------------------------
    | Jurados disponibles para validar una nueva propuesta de Fase 1
    |--------------------------------------------------------------------------
    */
    $stmtJurados = $db->query("
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
            ) AS nombre_rol
        FROM usuarios u
        INNER JOIN usuario_rol ur
            ON ur.id_usuario = u.id
        INNER JOIN rol r
            ON r.id = ur.id_role
        WHERE ur.id_role IN (3, 4)
          AND COALESCE(u.estado_cuenta, 'ACTIVO') <> 'INACTIVO'
        GROUP BY
            u.id,
            u.usuario,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno
        ORDER BY nombre_completo ASC
    ");

    $jurados = $stmtJurados->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Filtros: solo trabajos de Fase 1 enviados por el participante
    |--------------------------------------------------------------------------
    */
    $condiciones = [
        't.id_configuracion IS NOT NULL',
        'f.numero_fase = 1',
        "UPPER(TRIM(COALESCE(t.estado_aprobacion, ''))) <> 'BORRADOR'",
        't.id = (
            SELECT MAX(t2.id)
            FROM trabajos t2
            WHERE t2.id_estudiante = t.id_estudiante
              AND t2.id_configuracion = t.id_configuracion
        )',
    ];

    if ($filtro === 'pendientes') {
    $condiciones[] = "
        UPPER(TRIM(t.estado_aprobacion)) IN (
            'EN_REVISION',
            'CORREGIDO'
        )
    ";
}

    if ($mapaFiltros[$filtro] === 'OBSERVADO') {
        $condiciones[] = "
            UPPER(TRIM(t.estado_aprobacion)) IN ('OBSERVADO', 'RECHAZADO')
        ";
    }

    if ($mapaFiltros[$filtro] === 'REVISADO') {
        $condiciones[] = "
            UPPER(TRIM(t.estado_aprobacion)) IN ('APROBADO', 'REVISADO')
        ";
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    /*
    |--------------------------------------------------------------------------
    | Indicadores superiores
    |--------------------------------------------------------------------------
    */
    $stmtStats = $db->query("
        SELECT
            SUM(
                CASE
                    WHEN UPPER(TRIM(t.estado_aprobacion)) IN (
                'EN_REVISION',
                'CORREGIDO'
            )
            THEN 1 ELSE 0
                END
            ) AS pendientes,

            SUM(
                CASE
                    WHEN UPPER(TRIM(t.estado_aprobacion)) IN ('OBSERVADO', 'RECHAZADO')
                    THEN 1 ELSE 0
                END
            ) AS observados,

            SUM(
                CASE
                    WHEN UPPER(TRIM(t.estado_aprobacion)) IN ('APROBADO', 'REVISADO')
                    THEN 1 ELSE 0
                END
            ) AS revisados

        FROM trabajos t
        INNER JOIN programa_fase_config pfc
            ON pfc.id = t.id_configuracion
        INNER JOIN fases f
            ON f.id = pfc.id_fase

        WHERE t.id_configuracion IS NOT NULL
          AND f.numero_fase = 1
          AND UPPER(TRIM(COALESCE(t.estado_aprobacion, ''))) <> 'BORRADOR'
          AND t.id = (
                SELECT MAX(t2.id)
                FROM trabajos t2
                WHERE t2.id_estudiante = t.id_estudiante
                  AND t2.id_configuracion = t.id_configuracion
          )
    ");

    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];

    /*
    |--------------------------------------------------------------------------
    | Propuestas de Fase 1
    |--------------------------------------------------------------------------
    | El jurado se busca primero desde jurado_asignaciones.
    | Como respaldo, se consulta trabajo_docente para recuperar asignaciones
    | ya registradas en el flujo anterior.
    |--------------------------------------------------------------------------
    */
    $stmtItems = $db->prepare("
        SELECT
            t.id AS id_trabajo,
            t.id_estudiante,
            t.titulo_trabajo,
            t.fecha_presentacion,
            t.estado_aprobacion,
            t.comentario_revision,
            t.fecha_revision,
            t.ruta_archivo,

            CONCAT_WS(
                ' ',
                u.nombres,
                u.apellido_paterno,
                u.apellido_materno
            ) AS estudiante,

            u.usuario,
            u.ci,
            u.correo,
            u.profesion_postgrado,

            p.id AS id_programa,
            p.nombre_programa,
            p.tipo AS tipo_programa,

            pfc.id AS id_configuracion_fase_1,
            pfc.gestion AS gestion_configuracion,

            f.numero_fase,
            f.nombre_fase,

            ja.id_docente AS id_jurado_postulacion,

            CONCAT_WS(
                ' ',
                uj.nombres,
                uj.apellido_paterno,
                uj.apellido_materno
            ) AS jurado_postulacion,

            (
                SELECT td2.id_docente
                FROM trabajo_docente td2
                INNER JOIN trabajos t2
                    ON t2.id = td2.id_trabajo
                INNER JOIN programa_fase_config pfc2
                    ON pfc2.id = t2.id_configuracion
                INNER JOIN fases f2
                    ON f2.id = pfc2.id_fase
                WHERE t2.id_estudiante = t.id_estudiante
                  AND pfc2.id_programa = p.id
                  AND pfc2.gestion = pfc.gestion
                  AND f2.numero_fase = 2
                  AND UPPER(TRIM(COALESCE(td2.tipo_asignacion, ''))) IN (
                      'JURADO',
                      'EVALUADOR',
                      'REVISOR'
                  )
                ORDER BY td2.fecha_asignacion DESC, td2.id_docente DESC
                LIMIT 1
            ) AS id_jurado_trabajo,

            (
                SELECT CONCAT_WS(
                    ' ',
                    ujd.nombres,
                    ujd.apellido_paterno,
                    ujd.apellido_materno
                )
                FROM trabajo_docente td2
                INNER JOIN trabajos t2
                    ON t2.id = td2.id_trabajo
                INNER JOIN programa_fase_config pfc2
                    ON pfc2.id = t2.id_configuracion
                INNER JOIN fases f2
                    ON f2.id = pfc2.id_fase
                INNER JOIN usuarios ujd
                    ON ujd.id = td2.id_docente
                WHERE t2.id_estudiante = t.id_estudiante
                  AND pfc2.id_programa = p.id
                  AND pfc2.gestion = pfc.gestion
                  AND f2.numero_fase = 2
                  AND UPPER(TRIM(COALESCE(td2.tipo_asignacion, ''))) IN (
                      'JURADO',
                      'EVALUADOR',
                      'REVISOR'
                  )
                ORDER BY td2.fecha_asignacion DESC, td2.id_docente DESC
                LIMIT 1
            ) AS jurado_trabajo,

            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM fase_estudiante_config fec2
                    INNER JOIN programa_fase_config pfc2
                        ON pfc2.id = fec2.id_configuracion
                    INNER JOIN fases f2
                        ON f2.id = pfc2.id_fase
                    WHERE fec2.id_estudiante = t.id_estudiante
                      AND pfc2.id_programa = p.id
                      AND pfc2.gestion = pfc.gestion
                      AND f2.numero_fase = 2
                      AND fec2.estado = 'ACTIVO'
                )
                THEN 1

                WHEN EXISTS (
                    SELECT 1
                    FROM trabajos tf2
                    INNER JOIN programa_fase_config pfc2
                        ON pfc2.id = tf2.id_configuracion
                    INNER JOIN fases f2
                        ON f2.id = pfc2.id_fase
                    WHERE tf2.id_estudiante = t.id_estudiante
                      AND pfc2.id_programa = p.id
                      AND pfc2.gestion = pfc.gestion
                      AND f2.numero_fase = 2
                )
                THEN 1

                ELSE 0
            END AS fase_2_habilitada

        FROM trabajos t
        INNER JOIN usuarios u
            ON u.id = t.id_estudiante
        INNER JOIN programa_fase_config pfc
            ON pfc.id = t.id_configuracion
        INNER JOIN programa p
            ON p.id = pfc.id_programa
        INNER JOIN fases f
            ON f.id = pfc.id_fase

        LEFT JOIN inscripciones i
            ON i.id_estudiante = t.id_estudiante
            AND i.id_programa = p.id

        LEFT JOIN titulacion_postulaciones tp
            ON tp.id_inscripcion = i.id

        LEFT JOIN jurado_asignaciones ja
            ON ja.id = (
                SELECT ja2.id
                FROM jurado_asignaciones ja2
                WHERE ja2.id_postulacion = tp.id
                  AND UPPER(TRIM(COALESCE(ja2.estado, ''))) IN (
                      'ASIGNADO',
                      'ACTIVO'
                  )
                ORDER BY ja2.fecha_asignacion DESC, ja2.id DESC
                LIMIT 1
            )

        LEFT JOIN usuarios uj
            ON uj.id = ja.id_docente

        $where

        ORDER BY
            CASE
                WHEN UPPER(TRIM(t.estado_aprobacion)) IN (
    'EN_REVISION',
    'CORREGIDO'
) THEN 1
                WHEN UPPER(TRIM(t.estado_aprobacion)) IN ('OBSERVADO', 'RECHAZADO') THEN 2
                WHEN UPPER(TRIM(t.estado_aprobacion)) IN ('APROBADO', 'REVISADO') THEN 3
                ELSE 4
            END,
            t.fecha_presentacion DESC,
            t.id DESC
    ");

    $stmtItems->execute();
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['estado_aprobacion'] = estadoNormalizadoRevision(
            $item['estado_aprobacion'] ?? ''
        );

        $item['fase_2_habilitada'] = (int) (
            $item['fase_2_habilitada'] ?? 0
        );

        $item['id_jurado_asignado'] = $item['id_jurado_postulacion']
            ?: ($item['id_jurado_trabajo'] ?? null);

        $item['jurado_asignado'] = !empty($item['jurado_postulacion'])
            ? $item['jurado_postulacion']
            : ($item['jurado_trabajo'] ?? null);

        unset(
            $item['id_jurado_postulacion'],
            $item['jurado_postulacion'],
            $item['id_jurado_trabajo'],
            $item['jurado_trabajo']
        );
    }

    unset($item);

    responderRevision(true, 'Propuestas de Fase 1 cargadas correctamente.', [
        'stats' => [
            'pendientes' => (int) ($stats['pendientes'] ?? 0),
            'observados' => (int) ($stats['observados'] ?? 0),
            'revisados' => (int) ($stats['revisados'] ?? 0),
        ],
        'jurados' => $jurados,
        'data' => $items,
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    responderRevision(
        false,
        'No fue posible cargar las propuestas de Fase 1 para revisión administrativa.'
    );
}