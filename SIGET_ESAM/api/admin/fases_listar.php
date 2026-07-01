<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($conexion) || !$conexion instanceof PDO) {
        throw new Exception("No existe conexión PDO local.");
    }

    $db = $conexion;

    $stmtProgramas = $db->query("
        SELECT 
            id,
            nombre_programa,
            tipo,
            estado
        FROM programa
        WHERE estado = 1
        ORDER BY nombre_programa ASC
    ");

    $programas = $stmtProgramas->fetchAll(PDO::FETCH_ASSOC);

    $stmtFases = $db->query("
        SELECT 
            id,
            nombre_fase,
            numero_fase,
            descripcion,
            estado,
            calificacion_requerido
        FROM fases
        WHERE estado = 1
        ORDER BY numero_fase ASC
    ");

    $fases = $stmtFases->fetchAll(PDO::FETCH_ASSOC);

    $sql = "
        SELECT 
            c.id,
            c.id_programa,
            c.id_fase,
            c.gestion,
            c.tipo_trabajo,
            c.fecha_inicio_entrega,
            c.fecha_limite_entrega,
            c.fecha_limite_revision,
            c.fecha_devolucion_observaciones,
            c.nota_minima,
            c.estado,
            c.fecha_creacion,
            c.fecha_actualizacion,

            p.nombre_programa,
            p.tipo AS tipo_programa,

            f.nombre_fase,
            f.numero_fase,

            COUNT(DISTINCT r.id) AS total_requisitos,
            COUNT(DISTINCT ec.id) AS total_estudiantes_configurados

        FROM programa_fase_config c
        INNER JOIN programa p 
            ON p.id = c.id_programa
        INNER JOIN fases f 
            ON f.id = c.id_fase
        LEFT JOIN fase_requisitos r 
            ON r.id_configuracion = c.id
            AND r.estado = 'ACTIVO'
        LEFT JOIN fase_estudiante_config ec 
            ON ec.id_configuracion = c.id
            AND ec.estado = 'ACTIVO'
        GROUP BY 
            c.id,
            c.id_programa,
            c.id_fase,
            c.gestion,
            c.tipo_trabajo,
            c.fecha_inicio_entrega,
            c.fecha_limite_entrega,
            c.fecha_limite_revision,
            c.fecha_devolucion_observaciones,
            c.nota_minima,
            c.estado,
            c.fecha_creacion,
            c.fecha_actualizacion,
            p.nombre_programa,
            p.tipo,
            f.nombre_fase,
            f.numero_fase
        ORDER BY 
            c.fecha_creacion DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    $configuraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'total' => count($configuraciones),
        'activas' => 0,
        'inactivas' => 0,
        'diplomados' => 0,
        'maestrias' => 0
    ];

    foreach ($configuraciones as $c) {
        if ($c['estado'] === 'ACTIVO') {
            $stats['activas']++;
        } else {
            $stats['inactivas']++;
        }

        $tipo = strtolower($c['tipo_programa'] ?? '');

        if (str_contains($tipo, 'diplomado')) {
            $stats['diplomados']++;
        }

        if (str_contains($tipo, 'maestr')) {
            $stats['maestrias']++;
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'programas' => $programas,
        'fases' => $fases,
        'data' => $configuraciones
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar gestión de fases.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}