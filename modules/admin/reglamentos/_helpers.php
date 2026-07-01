<?php
require_once __DIR__ . '/../../../config/database.php';

if (!isset($conexion) || !$conexion instanceof PDO) {
    die("Error: revisa config/database.php. La conexión debe llamarse \$conexion y usar PDO.");
}

$db = $conexion;

$usuarioId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : null;

$rootPath = dirname(__DIR__, 3);
$uploadDir = $rootPath . '/uploads/reglamentos/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('subirPdfReglamento')) {
    function subirPdfReglamento($campo, $uploadDir): array
    {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Debes seleccionar un archivo PDF.");
        }

        if ($_FILES[$campo]['size'] > 10 * 1024 * 1024) {
            throw new Exception("El PDF no debe superar los 10 MB.");
        }

        $archivoTmp = $_FILES[$campo]['tmp_name'];
        $archivoOriginal = $_FILES[$campo]['name'];
        $extension = strtolower(pathinfo($archivoOriginal, PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            throw new Exception("Solo se permiten archivos PDF.");
        }

        $mimeType = mime_content_type($archivoTmp);

        if ($mimeType !== 'application/pdf') {
            throw new Exception("El archivo seleccionado no es un PDF válido.");
        }

        $sizeBytes = $_FILES[$campo]['size'];
        $archivoServidor = 'reglamento_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.pdf';
        $destino = $uploadDir . $archivoServidor;

        if (!move_uploaded_file($archivoTmp, $destino)) {
            throw new Exception("No se pudo subir el archivo al servidor.");
        }

        return [
            'archivo_original' => $archivoOriginal,
            'archivo_servidor' => $archivoServidor,
            'ruta_archivo' => 'uploads/reglamentos/' . $archivoServidor,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes
        ];
    }
}

function obtenerReglamentos(PDO $db): array
{
    $stmt = $db->query("
        SELECT *
        FROM reglamentos
        ORDER BY created_at DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerReglamentoPorId(PDO $db, int $id): ?array
{
    $stmt = $db->prepare("
        SELECT *
        FROM reglamentos
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);

    $reglamento = $stmt->fetch(PDO::FETCH_ASSOC);

    return $reglamento ?: null;
}

function crearReglamento(PDO $db, ?int $usuarioId, string $uploadDir): string
{
    $tipoPrograma = trim($_POST['tipo_programa'] ?? '');
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');

    if ($tipoPrograma === '') {
        throw new Exception("Debes seleccionar el tipo de programa.");
    }

    if ($titulo === '') {
        throw new Exception("El título es obligatorio.");
    }

    $pdf = subirPdfReglamento('archivo_pdf', $uploadDir);

    $stmt = $db->prepare("
        INSERT INTO reglamentos
        (
            tipo_programa,
            titulo,
            descripcion,
            version,
            archivo_original,
            archivo_servidor,
            ruta_archivo,
            mime_type,
            size_bytes,
            estado,
            creado_por,
            actualizado_por,
            created_at,
            updated_at
        )
        VALUES
        (
            :tipo_programa,
            :titulo,
            :descripcion,
            :version,
            :archivo_original,
            :archivo_servidor,
            :ruta_archivo,
            :mime_type,
            :size_bytes,
            'ACTIVO',
            :creado_por,
            :actualizado_por,
            NOW(),
            NOW()
        )
    ");

    $stmt->execute([
        ':tipo_programa' => $tipoPrograma,
        ':titulo' => $titulo,
        ':descripcion' => $descripcion,
        ':version' => $version,
        ':archivo_original' => $pdf['archivo_original'],
        ':archivo_servidor' => $pdf['archivo_servidor'],
        ':ruta_archivo' => $pdf['ruta_archivo'],
        ':mime_type' => $pdf['mime_type'],
        ':size_bytes' => $pdf['size_bytes'],
        ':creado_por' => $usuarioId,
        ':actualizado_por' => $usuarioId
    ]);

    return "Reglamento subido correctamente.";
}

function actualizarDatosReglamento(PDO $db, ?int $usuarioId): string
{
    $id = intval($_POST['id'] ?? 0);
    $tipoPrograma = trim($_POST['tipo_programa'] ?? '');
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');

    if ($id <= 0) {
        throw new Exception("ID inválido.");
    }

    if ($titulo === '') {
        throw new Exception("El título es obligatorio.");
    }

    $stmt = $db->prepare("
        UPDATE reglamentos
        SET
            tipo_programa = :tipo_programa,
            titulo = :titulo,
            descripcion = :descripcion,
            version = :version,
            actualizado_por = :actualizado_por,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':tipo_programa' => $tipoPrograma,
        ':titulo' => $titulo,
        ':descripcion' => $descripcion,
        ':version' => $version,
        ':actualizado_por' => $usuarioId,
        ':id' => $id
    ]);

    return "Datos del reglamento actualizados correctamente.";
}

function reemplazarPdfReglamento(PDO $db, ?int $usuarioId, string $uploadDir): string
{
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception("ID inválido.");
    }

    $stmt = $db->prepare("SELECT archivo_servidor FROM reglamentos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$actual) {
        throw new Exception("No se encontró el reglamento.");
    }

    $pdf = subirPdfReglamento('nuevo_pdf', $uploadDir);

    $archivoAnterior = $uploadDir . $actual['archivo_servidor'];

    if (file_exists($archivoAnterior)) {
        unlink($archivoAnterior);
    }

    $stmt = $db->prepare("
        UPDATE reglamentos
        SET
            archivo_original = :archivo_original,
            archivo_servidor = :archivo_servidor,
            ruta_archivo = :ruta_archivo,
            mime_type = :mime_type,
            size_bytes = :size_bytes,
            actualizado_por = :actualizado_por,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':archivo_original' => $pdf['archivo_original'],
        ':archivo_servidor' => $pdf['archivo_servidor'],
        ':ruta_archivo' => $pdf['ruta_archivo'],
        ':mime_type' => $pdf['mime_type'],
        ':size_bytes' => $pdf['size_bytes'],
        ':actualizado_por' => $usuarioId,
        ':id' => $id
    ]);

    return "PDF actualizado correctamente.";
}

function cambiarEstadoReglamento(PDO $db, ?int $usuarioId): string
{
    $id = intval($_POST['id'] ?? 0);
    $estado = $_POST['estado'] ?? 'INACTIVO';

    if ($id <= 0) {
        throw new Exception("ID inválido.");
    }

    if (!in_array($estado, ['ACTIVO', 'INACTIVO'])) {
        $estado = 'INACTIVO';
    }

    $stmt = $db->prepare("
        UPDATE reglamentos
        SET estado = :estado,
            actualizado_por = :actualizado_por,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':estado' => $estado,
        ':actualizado_por' => $usuarioId,
        ':id' => $id
    ]);

    return "Estado actualizado correctamente.";
}