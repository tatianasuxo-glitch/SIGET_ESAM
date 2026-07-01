<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($conexion) || !$conexion instanceof PDO) {
        throw new Exception("No existe conexión PDO local.");
    }

    $db = $conexion;

    $id = intval($_POST['id'] ?? 0);
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidoPaterno = trim($_POST['apellido_paterno'] ?? '');
    $apellidoMaterno = trim($_POST['apellido_materno'] ?? '');
    $profesion = trim($_POST['profesion_postgrado'] ?? '');
    $estadoCuenta = trim($_POST['estado_cuenta'] ?? 'Activo');
    $idRol = intval($_POST['id_rol'] ?? 0);

    if ($usuario === '') {
        throw new Exception("El usuario es obligatorio.");
    }

    if ($nombres === '') {
        throw new Exception("Los nombres son obligatorios.");
    }

    if ($apellidoPaterno === '') {
        throw new Exception("El apellido paterno es obligatorio.");
    }

    if ($idRol <= 0) {
        throw new Exception("Debes seleccionar un rol.");
    }

    if (!in_array($estadoCuenta, ['Activo', 'Inactivo'])) {
        $estadoCuenta = 'Activo';
    }

    if ($id > 0) {
        $stmtExiste = $db->prepare("
            SELECT id 
            FROM usuarios 
            WHERE usuario = :usuario 
            AND id <> :id
            LIMIT 1
        ");

        $stmtExiste->execute([
            ':usuario' => $usuario,
            ':id' => $id
        ]);

        if ($stmtExiste->fetch()) {
            throw new Exception("Ya existe otro usuario con ese nombre de usuario.");
        }

        if ($contrasena !== '') {
            $stmt = $db->prepare("
                UPDATE usuarios
                SET
                    usuario = :usuario,
                    contrasena = :contrasena,
                    nombres = :nombres,
                    apellido_paterno = :apellido_paterno,
                    apellido_materno = :apellido_materno,
                    profesion_postgrado = :profesion_postgrado,
                    estado_cuenta = :estado_cuenta
                WHERE id = :id
            ");

            $stmt->execute([
                ':usuario' => $usuario,
                ':contrasena' => $contrasena,
                ':nombres' => $nombres,
                ':apellido_paterno' => $apellidoPaterno,
                ':apellido_materno' => $apellidoMaterno,
                ':profesion_postgrado' => $profesion,
                ':estado_cuenta' => $estadoCuenta,
                ':id' => $id
            ]);
        } else {
            $stmt = $db->prepare("
                UPDATE usuarios
                SET
                    usuario = :usuario,
                    nombres = :nombres,
                    apellido_paterno = :apellido_paterno,
                    apellido_materno = :apellido_materno,
                    profesion_postgrado = :profesion_postgrado,
                    estado_cuenta = :estado_cuenta
                WHERE id = :id
            ");

            $stmt->execute([
                ':usuario' => $usuario,
                ':nombres' => $nombres,
                ':apellido_paterno' => $apellidoPaterno,
                ':apellido_materno' => $apellidoMaterno,
                ':profesion_postgrado' => $profesion,
                ':estado_cuenta' => $estadoCuenta,
                ':id' => $id
            ]);
        }

        $db->prepare("DELETE FROM usuario_rol WHERE id_usuario = :id_usuario")
           ->execute([':id_usuario' => $id]);

        $stmtRol = $db->prepare("
            INSERT INTO usuario_rol (id_usuario, id_role)
            VALUES (:id_usuario, :id_role)
        ");

        $stmtRol->execute([
            ':id_usuario' => $id,
            ':id_role' => $idRol
        ]);

        $mensaje = "Usuario actualizado correctamente.";

    } else {
        if ($contrasena === '') {
            throw new Exception("La contraseña es obligatoria para crear usuario.");
        }

        $stmtExiste = $db->prepare("
            SELECT id 
            FROM usuarios 
            WHERE usuario = :usuario
            LIMIT 1
        ");

        $stmtExiste->execute([':usuario' => $usuario]);

        if ($stmtExiste->fetch()) {
            throw new Exception("Ya existe un usuario con ese nombre de usuario.");
        }

        $stmt = $db->prepare("
            INSERT INTO usuarios
            (
                usuario,
                contrasena,
                nombres,
                apellido_paterno,
                apellido_materno,
                profesion_postgrado,
                estado_cuenta
            )
            VALUES
            (
                :usuario,
                :contrasena,
                :nombres,
                :apellido_paterno,
                :apellido_materno,
                :profesion_postgrado,
                :estado_cuenta
            )
        ");

        $stmt->execute([
            ':usuario' => $usuario,
            ':contrasena' => $contrasena,
            ':nombres' => $nombres,
            ':apellido_paterno' => $apellidoPaterno,
            ':apellido_materno' => $apellidoMaterno,
            ':profesion_postgrado' => $profesion,
            ':estado_cuenta' => $estadoCuenta
        ]);

        $nuevoId = (int)$db->lastInsertId();

        $stmtRol = $db->prepare("
            INSERT INTO usuario_rol (id_usuario, id_role)
            VALUES (:id_usuario, :id_role)
        ");

        $stmtRol->execute([
            ':id_usuario' => $nuevoId,
            ':id_role' => $idRol
        ]);

        $mensaje = "Usuario creado correctamente.";
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar usuario.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}