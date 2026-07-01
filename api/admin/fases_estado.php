<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($conexion) || !$conexion instanceof PDO) {
        throw new Exception("No existe conexión PDO local.");
    }

    $db = $conexion;

    $id = intval($_POST['id'] ?? 0);
    $estado = trim($_POST['estado'] ?? '');

    if ($id <= 0) {
        throw new Exception("Configuración no válida.");
    }

    if (!in_array($estado, ['ACTIVO', 'INACTIVO'])) {
        throw new Exception("Estado no válido.");
    }

    $stmt = $db->prepare("
        UPDATE programa_fase_config
        SET estado = :estado,
            fecha_actualizacion = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':estado' => $estado,
        ':id' => $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar estado.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}