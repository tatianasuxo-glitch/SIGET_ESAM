<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($conexion) || !$conexion instanceof PDO) {
        throw new Exception("No existe conexión PDO local.");
    }

    $db = $conexion;

    $estado = $_GET['estado'] ?? 'pendientes';

    $filtros = [
        'pendientes' => "t.estado_aprobacion IN ('En Revisión', 'Pendiente', 'PENDIENTE', 'EN_REVISION', 'En revision')",
        'observados' => "t.estado_aprobacion IN ('Observado', 'OBSERVADO', 'Con Observaciones', 'CON_OBSERVACIONES')",
        'aprobados' => "t.estado_aprobacion IN ('Aprobado', 'APROBADO')",
        'rechazados' => "t.estado_aprobacion IN ('Rechazado', 'RECHAZADO', 'Reprobado', 'REPROBADO')",
        'todos' => "1 = 1"
    ];

    $whereEstado = $filtros[$estado] ?? $filtros['pendientes'];

    $stmtStats = $db->query("
        SELECT
            SUM(CASE WHEN estado_aprobacion IN ('En Revisión', 'Pendiente', 'PENDIENTE', 'EN_REVISION', 'En revision') THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado_aprobacion IN ('Observado', 'OBSERVADO', 'Con Observaciones', 'CON_OBSERVACIONES') THEN 1 ELSE 0 END) AS observados,
            SUM(CASE WHEN estado_aprobacion IN ('Aprobado', 'APROBADO') THEN 1 ELSE 0 END) AS aprobados,
            SUM(CASE WHEN estado_aprobacion IN ('Rechazado', 'RECHAZADO', 'Reprobado', 'REPROBADO') THEN 1 ELSE 0 END) AS rechazados,
            COUNT(*) AS total
        FROM trabajos
    ");

    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    $sql = "
        SELECT
            t.id AS id_trabajo,
            t.titulo_trabajo,
            t.estado_aprobacion,
            t.calificacion_final,
            t.comentario_revision,
            t.fecha_presentacion,
            t.fecha_revision,
            t.ruta_archivo,

            f.numero_fase,
            f.nombre_fase,

            u.id AS id_estudiante,
            CONCAT(
                u.apellido_paterno, ' ',
                IFNULL(u.apellido_materno, ''), ' ',
                u.nombres
            ) AS estudiante,
            u.usuario,
            u.profesion_postgrado,

            p.nombre_programa,
            p.tipo AS tipo_programa,
            i.estado_academico

        FROM trabajos t
        INNER JOIN usuarios u
            ON u.id = t.id_estudiante

        LEFT JOIN fases f
            ON f.id = t.id_fase_actual

        LEFT JOIN inscripciones i
            ON i.id_estudiante = u.id

        LEFT JOIN programa p
            ON p.id = i.id_programa

        WHERE {$whereEstado}

        GROUP BY t.id
        ORDER BY t.fecha_presentacion DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar revisión administrativa.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}