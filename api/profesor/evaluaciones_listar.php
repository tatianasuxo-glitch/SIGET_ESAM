<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderEvaluaciones(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

function normalizarEstadoJurado(?string $estado): string
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
        'OBSERVADO', 'OBSERVADA', 'RECHAZADO', 'RECHAZADA' => 'OBSERVADO',
        'CORREGIDO', 'CORREGIDA' => 'CORREGIDO',
        'APROBADO', 'APROBADA', 'REVISADO', 'REVISADA' => 'REVISADO',
        default => $estado,
    };
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderEvaluaciones(false, 'Tu sesión finalizó. Ingresa nuevamente al sistema.');
}

$rol = strtolower(trim((string) ($_SESSION['rol'] ?? '')));

if (!in_array($rol, ['docente', 'tutor'], true)) {
    http_response_code(403);
    responderEvaluaciones(false, 'Acceso restringido para jurados.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderEvaluaciones(false, 'No se pudo establecer la conexión local.');
}

$idJurado = (int) $_SESSION['id'];
$filtro = strtolower(trim((string) ($_GET['estado'] ?? 'todos')));

$mapaFiltros = [
    'todos' => null,
    'pendientes' => 'PENDIENTE',
    'en_revision' => 'EN_REVISION',
    'observados' => 'OBSERVADO',
    'corregidos' => 'CORREGIDO',
    'revisados' => 'REVISADO',
];

if (!array_key_exists($filtro, $mapaFiltros)) {
    $filtro = 'todos';
}

try {
    $db = $conexion;

    $condiciones = [
        'f.numero_fase = 2',
        'ja.estado = "ASIGNADO"',
        'ja.id_docente = :id_jurado',
    ];

    $parametros = [
        ':id_jurado' => $idJurado,
    ];

    if ($mapaFiltros[$filtro] === 'PENDIENTE') {
        $condiciones[] = "UPPER(TRIM(COALESCE(t.estado_aprobacion, 'BORRADOR'))) IN ('BORRADOR', '')";
    }

    if ($mapaFiltros[$filtro] === 'EN_REVISION') {
        $condiciones[] = "UPPER(TRIM(t.estado_aprobacion)) = 'EN_REVISION'";
    }

    if ($mapaFiltros[$filtro] === 'OBSERVADO') {
        $condiciones[] = "UPPER(TRIM(t.estado_aprobacion)) IN ('OBSERVADO', 'RECHAZADO')";
    }

    if ($mapaFiltros[$filtro] === 'CORREGIDO') {
        $condiciones[] = "UPPER(TRIM(t.estado_aprobacion)) IN ('CORREGIDO', 'CORREGIDA')";
    }

    if ($mapaFiltros[$filtro] === 'REVISADO') {
        $condiciones[] = "UPPER(TRIM(t.estado_aprobacion)) IN ('APROBADO', 'REVISADO')";
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $stmt = $db->prepare("
        SELECT
            t.id AS id_trabajo,
            t.titulo_trabajo,
            t.id_estudiante,
            t.id_configuracion,
            t.fecha_presentacion,
            t.estado_aprobacion,
            t.calificacion_final,
            t.comentario_revision,
            t.fecha_revision,
            t.ruta_archivo,
            t.actualizado_el,

            CONCAT_WS(
                ' ',
                estudiante.nombres,
                estudiante.apellido_paterno,
                estudiante.apellido_materno
            ) AS estudiante,

            estudiante.usuario AS usuario_estudiante,
            estudiante.ci,
            estudiante.correo,

            p.id AS id_programa,
            p.nombre_programa,
            p.tipo AS tipo_programa,
            p.gestion_externa,

            pfc.gestion,
            pfc.tipo_trabajo,
            pfc.nota_minima,

            COALESCE(
                fec.fecha_inicio_entrega,
                pfc.fecha_inicio_entrega
            ) AS fecha_inicio_entrega,

            COALESCE(
                fec.fecha_limite_entrega,
                pfc.fecha_limite_entrega
            ) AS fecha_limite_entrega,

            COALESCE(
                fec.fecha_limite_revision,
                pfc.fecha_limite_revision
            ) AS fecha_limite_revision,

            COALESCE(
                fec.fecha_devolucion_observaciones,
                pfc.fecha_devolucion_observaciones
            ) AS fecha_devolucion_observaciones,

            f.numero_fase,
            f.nombre_fase,

            ja.id AS id_asignacion_jurado,
            ja.observacion AS observacion_asignacion,

            CONCAT_WS(
                ' ',
                jurado.nombres,
                jurado.apellido_paterno,
                jurado.apellido_materno
            ) AS jurado_asignado

        FROM trabajos t

        INNER JOIN programa_fase_config pfc
            ON pfc.id = t.id_configuracion

        INNER JOIN fases f
            ON f.id = pfc.id_fase

        INNER JOIN programa p
            ON p.id = pfc.id_programa

        INNER JOIN usuarios estudiante
            ON estudiante.id = t.id_estudiante

        INNER JOIN inscripciones i
            ON i.id_estudiante = t.id_estudiante
            AND i.id_programa = p.id

        INNER JOIN titulacion_postulaciones tp
            ON tp.id_inscripcion = i.id

        INNER JOIN jurado_asignaciones ja
            ON ja.id_postulacion = tp.id

        INNER JOIN usuarios jurado
            ON jurado.id = ja.id_docente

        LEFT JOIN fase_estudiante_config fec
            ON fec.id_estudiante = t.id_estudiante
            AND fec.id_configuracion = t.id_configuracion

        $where

        ORDER BY
            CASE
                WHEN UPPER(TRIM(t.estado_aprobacion)) = 'EN_REVISION' THEN 1
                WHEN UPPER(TRIM(t.estado_aprobacion)) IN ('CORREGIDO', 'CORREGIDA') THEN 2
                WHEN UPPER(TRIM(t.estado_aprobacion)) IN ('OBSERVADO', 'RECHAZADO') THEN 3
                WHEN UPPER(TRIM(t.estado_aprobacion)) IN ('APROBADO', 'REVISADO') THEN 4
                ELSE 5
            END,
            t.fecha_presentacion DESC,
            t.id DESC
    ");

    $stmt->execute($parametros);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'pendientes' => 0,
        'en_revision' => 0,
        'observados' => 0,
        'corregidos' => 0,
        'revisados' => 0,
    ];

    foreach ($items as &$item) {
        $item['estado_documento'] = normalizarEstadoJurado(
            $item['estado_aprobacion'] ?? ''
        );

        $item['calificacion_final'] = $item['calificacion_final'] !== null
            ? (float) $item['calificacion_final']
            : null;

        $item['nota_minima'] = $item['nota_minima'] !== null
            ? (float) $item['nota_minima']
            : null;

        $estado = $item['estado_documento'];

        if ($estado === 'PENDIENTE') {
            $stats['pendientes']++;
        } elseif ($estado === 'EN_REVISION') {
            $stats['en_revision']++;
        } elseif ($estado === 'OBSERVADO') {
            $stats['observados']++;
        } elseif ($estado === 'CORREGIDO') {
            $stats['corregidos']++;
        } elseif ($estado === 'REVISADO') {
            $stats['revisados']++;
        }
    }

    unset($item);

    responderEvaluaciones(true, 'Bandeja del jurado cargada correctamente.', [
        'stats' => $stats,
        'data' => $items,
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    responderEvaluaciones(
        false,
        'No fue posible cargar la bandeja de evaluación del jurado.'
    );
}