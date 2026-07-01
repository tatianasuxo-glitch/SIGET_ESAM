<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $dbExt = siget_externa();

    $sql = "
        SELECT 
            p.id_programa_externo,
            p.codigo_programa,
            p.nombre_programa,
            p.tipo_programa,
            p.gestion,
            p.version_programa,
            COALESCE(s.nombre_sede, 'Sin sede') AS nombre_sede,
            COUNT(i.id_inscripcion_externa) AS total_participantes,
            SUM(
                CASE 
                    WHEN i.estado_cartera IN ('VIGENTE', 'EXENTO_DE_DEUDA')
                    AND i.estado_academico = 'CONCLUIDO'
                    AND i.estado_acceso = 'HABILITADO'
                    THEN 1 ELSE 0
                END
            ) AS total_habilitados
        FROM ext_programas p
        LEFT JOIN ext_sedes s 
            ON s.id_sede = p.id_sede
        LEFT JOIN ext_inscripciones i 
            ON i.id_programa_externo = p.id_programa_externo
            AND i.estado = 1
        WHERE p.estado_programa = 'CONCLUIDO'
        AND p.estado = 1
        GROUP BY 
            p.id_programa_externo,
            p.codigo_programa,
            p.nombre_programa,
            p.tipo_programa,
            p.gestion,
            p.version_programa,
            s.nombre_sede
        ORDER BY p.nombre_programa ASC
    ";

    $stmt = $dbExt->prepare($sql);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'data' => $stmt->fetchAll()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar programas concluidos.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}