<?php
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: ../../../login.php");
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

if (!isset($conexion) || !$conexion instanceof PDO) {
    die("Error de conexión.");
}

$db = $conexion;

$usuarioId = $_SESSION["id"];
$reglamentoId = intval($_GET["id"] ?? 0);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($reglamentoId <= 0) {
    die("Reglamento no válido.");
}

$stmt = $db->prepare("
    SELECT id, ruta_archivo
    FROM reglamentos
    WHERE id = :id
    AND estado = 'ACTIVO'
    LIMIT 1
");

$stmt->execute([
    ':id' => $reglamentoId
]);

$reglamento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reglamento) {
    die("El reglamento no existe o no está disponible.");
}

/*
    Registrar vista del estudiante.
    Si por algún motivo falla, igual dejamos abrir el PDF.
*/
try {
    $stmt = $db->prepare("
        INSERT INTO reglamentos_vistas
        (
            reglamento_id,
            usuario_id,
            visto_at,
            ip
        )
        VALUES
        (
            :reglamento_id,
            :usuario_id,
            NOW(),
            :ip
        )
    ");

    $stmt->execute([
        ':reglamento_id' => $reglamentoId,
        ':usuario_id' => $usuarioId,
        ':ip' => $ip
    ]);
} catch (PDOException $e) {
    // No detenemos la visualización del PDF si falla el registro de vista.
}

$rutaArchivo = $reglamento['ruta_archivo'];

header("Location: ../../../" . $rutaArchivo);
exit;