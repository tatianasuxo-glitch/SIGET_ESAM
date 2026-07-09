<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

try {

    if (!isset($_GET['id_configuracion'])) {
        throw new Exception('No se recibió el id de la configuración.');
    }

    $idConfiguracion = (int)$_GET['id_configuracion'];

    $db = dbLocal();

    $sql = "
        SELECT
            id,
            id_configuracion,
            nombre_requisito,
            descripcion,
            obligatorio,
            orden,
            estado
        FROM fase_requisitos
        WHERE id_configuracion = :id
          AND estado = 'ACTIVO'
        ORDER BY orden ASC
    ";

    $stmt = $db->prepare($sql);

    $stmt->execute([
        ':id' => $idConfiguracion
    ]);

    $requisitos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'requisitos' => $requisitos
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}