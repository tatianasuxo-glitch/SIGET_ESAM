<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($conexion) || !$conexion instanceof PDO) {
        throw new Exception("No existe conexión PDO local.");
    }

    $db = $conexion;

    $sql = "
        SELECT
            u.id,
            u.usuario,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno,
            u.profesion_postgrado,
            u.estado_cuenta,
            u.creado_el,
            u.actualizado_el,
            GROUP_CONCAT(r.nombre_rol SEPARATOR ', ') AS roles,
            MIN(r.id) AS id_rol_principal
        FROM usuarios u
        LEFT JOIN usuario_rol ur 
            ON ur.id_usuario = u.id
        LEFT JOIN rol r 
            ON r.id = ur.id_role
        GROUP BY 
            u.id,
            u.usuario,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno,
            u.profesion_postgrado,
            u.estado_cuenta,
            u.creado_el,
            u.actualizado_el
        ORDER BY u.creado_el DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtRoles = $db->query("
        SELECT id, nombre_rol
        FROM rol
        WHERE estado = 1
        ORDER BY nombre_rol ASC
    ");

    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'total' => count($usuarios),
        'activos' => 0,
        'inactivos' => 0,
        'administradores' => 0,
        'estudiantes' => 0,
        'docentes' => 0
    ];

    foreach ($usuarios as $u) {
        $estado = strtolower($u['estado_cuenta'] ?? '');
        $rolesTexto = strtolower($u['roles'] ?? '');

        if ($estado === 'activo') {
            $stats['activos']++;
        } else {
            $stats['inactivos']++;
        }

        if (str_contains($rolesTexto, 'administrador')) {
            $stats['administradores']++;
        }

        if (str_contains($rolesTexto, 'estudiante')) {
            $stats['estudiantes']++;
        }

        if (str_contains($rolesTexto, 'docente')) {
            $stats['docentes']++;
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'roles' => $roles,
        'data' => $usuarios
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar usuarios.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}