<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($conexion) || !$conexion instanceof PDO) {
        throw new Exception("No existe conexión PDO local.");
    }

    $db = $conexion;

    $idTrabajo = intval($_POST['id_trabajo'] ?? 0);
    $accion = $_POST['accion_revision'] ?? '';
    $comentario = trim($_POST['comentario_revision'] ?? '');
    $calificacion = $_POST['calificacion_final'] ?? null;

    if ($idTrabajo <= 0) {
        throw new Exception("Trabajo no válido.");
    }

    $mapaEstados = [
        'aprobar' => 'Aprobado',
        'observar' => 'Observado',
        'rechazar' => 'Rechazado'
    ];

    if (!isset($mapaEstados[$accion])) {
        throw new Exception("Acción de revisión no válida.");
    }

    $nuevoEstado = $mapaEstados[$accion];

    if ($calificacion === '') {
        $calificacion = null;
    }

    $stmt = $db->prepare("
        UPDATE trabajos
        SET
            estado_aprobacion = :estado_aprobacion,
            comentario_revision = :comentario_revision,
            calificacion_final = :calificacion_final,
            fecha_revision = NOW()
        WHERE id = :id_trabajo
    ");

    $stmt->execute([
        ':estado_aprobacion' => $nuevoEstado,
        ':comentario_revision' => $comentario,
        ':calificacion_final' => $calificacion,
        ':id_trabajo' => $idTrabajo
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Revisión actualizada correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar revisión.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}