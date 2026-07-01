<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($conexion) || !$conexion instanceof PDO) {
        throw new Exception("No existe conexión PDO local.");
    }

    $db = $conexion;

    $id = intval($_POST['id'] ?? 0);
    $estado = trim($_POST['estado_cuenta'] ?? '');

    if ($id <= 0) {
        throw new Exception("Usuario no válido.");
    }

    if (!in_array($estado, ['Activo', 'Inactivo'])) {
        throw new Exception("Estado no válido.");
    }

    $stmt = $db->prepare("
        UPDATE usuarios
        SET estado_cuenta = :estado_cuenta
        WHERE id = :id
    ");

    $stmt->execute([
        ':estado_cuenta' => $estado,
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