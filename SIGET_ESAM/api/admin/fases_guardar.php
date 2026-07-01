<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($conexion) || !$conexion instanceof PDO) {
        throw new Exception("No existe conexión PDO local.");
    }

    $db = $conexion;

    $id = intval($_POST['id'] ?? 0);
    $idPrograma = intval($_POST['id_programa'] ?? 0);
    $idFase = intval($_POST['id_fase'] ?? 0);
    $gestion = trim($_POST['gestion'] ?? '');
    $tipoTrabajo = trim($_POST['tipo_trabajo'] ?? '');
    $fechaInicio = trim($_POST['fecha_inicio_entrega'] ?? '');
    $fechaLimiteEntrega = trim($_POST['fecha_limite_entrega'] ?? '');
    $fechaLimiteRevision = trim($_POST['fecha_limite_revision'] ?? '');
    $fechaDevolucion = trim($_POST['fecha_devolucion_observaciones'] ?? '');
    $notaMinima = trim($_POST['nota_minima'] ?? '71');
    $estado = trim($_POST['estado'] ?? 'ACTIVO');

    if ($idPrograma <= 0) {
        throw new Exception("Debes seleccionar un programa.");
    }

    if ($idFase <= 0) {
        throw new Exception("Debes seleccionar una fase.");
    }

    if ($gestion === '') {
        throw new Exception("La gestión es obligatoria.");
    }

    if ($tipoTrabajo === '') {
        throw new Exception("El tipo de trabajo es obligatorio.");
    }

    if ($fechaInicio === '' || $fechaLimiteEntrega === '' || $fechaLimiteRevision === '') {
        throw new Exception("Debes completar las fechas principales.");
    }

    if (!in_array($estado, ['ACTIVO', 'INACTIVO'])) {
        $estado = 'ACTIVO';
    }

    if ($fechaDevolucion === '') {
        $fechaDevolucion = null;
    }

    if (!is_numeric($notaMinima)) {
        throw new Exception("La nota mínima debe ser numérica.");
    }

    if ((float)$notaMinima < 0 || (float)$notaMinima > 100) {
        throw new Exception("La nota mínima debe estar entre 0 y 100.");
    }

    if ($id <= 0) {
        $stmtExiste = $db->prepare("
            SELECT id
            FROM programa_fase_config
            WHERE id_programa = :id_programa
            AND id_fase = :id_fase
            AND gestion = :gestion
            LIMIT 1
        ");

        $stmtExiste->execute([
            ':id_programa' => $idPrograma,
            ':id_fase' => $idFase,
            ':gestion' => $gestion
        ]);

        if ($stmtExiste->fetch()) {
            throw new Exception("Ya existe una configuración para este programa, fase y gestión.");
        }

        $stmt = $db->prepare("
            INSERT INTO programa_fase_config
            (
                id_programa,
                id_fase,
                gestion,
                tipo_trabajo,
                fecha_inicio_entrega,
                fecha_limite_entrega,
                fecha_limite_revision,
                fecha_devolucion_observaciones,
                nota_minima,
                estado,
                creado_por,
                fecha_creacion,
                fecha_actualizacion
            )
            VALUES
            (
                :id_programa,
                :id_fase,
                :gestion,
                :tipo_trabajo,
                :fecha_inicio_entrega,
                :fecha_limite_entrega,
                :fecha_limite_revision,
                :fecha_devolucion_observaciones,
                :nota_minima,
                :estado,
                :creado_por,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':id_programa' => $idPrograma,
            ':id_fase' => $idFase,
            ':gestion' => $gestion,
            ':tipo_trabajo' => $tipoTrabajo,
            ':fecha_inicio_entrega' => $fechaInicio,
            ':fecha_limite_entrega' => $fechaLimiteEntrega,
            ':fecha_limite_revision' => $fechaLimiteRevision,
            ':fecha_devolucion_observaciones' => $fechaDevolucion,
            ':nota_minima' => $notaMinima,
            ':estado' => $estado,
            ':creado_por' => $_SESSION['id'] ?? null
        ]);

        $mensaje = "Configuración de fase creada correctamente.";

    } else {
        $stmt = $db->prepare("
            UPDATE programa_fase_config
            SET
                id_programa = :id_programa,
                id_fase = :id_fase,
                gestion = :gestion,
                tipo_trabajo = :tipo_trabajo,
                fecha_inicio_entrega = :fecha_inicio_entrega,
                fecha_limite_entrega = :fecha_limite_entrega,
                fecha_limite_revision = :fecha_limite_revision,
                fecha_devolucion_observaciones = :fecha_devolucion_observaciones,
                nota_minima = :nota_minima,
                estado = :estado,
                fecha_actualizacion = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':id_programa' => $idPrograma,
            ':id_fase' => $idFase,
            ':gestion' => $gestion,
            ':tipo_trabajo' => $tipoTrabajo,
            ':fecha_inicio_entrega' => $fechaInicio,
            ':fecha_limite_entrega' => $fechaLimiteEntrega,
            ':fecha_limite_revision' => $fechaLimiteRevision,
            ':fecha_devolucion_observaciones' => $fechaDevolucion,
            ':nota_minima' => $notaMinima,
            ':estado' => $estado,
            ':id' => $id
        ]);

        $mensaje = "Configuración de fase actualizada correctamente.";
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar configuración.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}