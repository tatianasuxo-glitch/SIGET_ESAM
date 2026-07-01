<?php

session_start();

require_once "config/database.php";

$error = "";

function convertirRolSistema($nombreRol)
{
    $nombreRol = strtolower(trim($nombreRol));

    if ($nombreRol === "administrador") {
        return "administrador";
    }

    if ($nombreRol === "estudiante") {
        return "estudiante";
    }

    if ($nombreRol === "docente") {
        return "docente";
    }

    if ($nombreRol === "tutor") {
        return "tutor";
    }

    return "sin_rol";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $password = trim($_POST["password"] ?? $_POST["contrasena"] ?? "");

    if ($usuario === "" || $password === "") {

        $error = "Debe ingresar usuario y contraseña.";

    } else {

        $sql = "
            SELECT 
                u.id,
                u.usuario,
                u.contrasena,
                u.nombres,
                u.apellido_paterno,
                u.apellido_materno,
                u.profesion_postgrado,
                u.estado_cuenta,
                r.id AS id_rol,
                r.nombre_rol
            FROM usuarios u
            INNER JOIN usuario_rol ur ON ur.id_usuario = u.id
            INNER JOIN rol r ON r.id = ur.id_role
            WHERE u.usuario = :usuario
            LIMIT 1
        ";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ":usuario" => $usuario
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {

            $error = "Usuario o contraseña incorrectos.";

        } else {

            $estadoCuenta = strtolower(trim($user["estado_cuenta"] ?? ""));

            if ($estadoCuenta !== "activo") {

                $error = "La cuenta se encuentra inactiva.";

            } elseif ($user["contrasena"] !== $password) {

                $error = "Usuario o contraseña incorrectos.";

            } else {

                $rolSistema = convertirRolSistema($user["nombre_rol"] ?? "");

                if ($rolSistema === "sin_rol") {

                    $error = "Rol no reconocido en el sistema.";

                } else {

                    $nombreCompleto = trim(
                        ($user["nombres"] ?? "") . " " .
                        ($user["apellido_paterno"] ?? "") . " " .
                        ($user["apellido_materno"] ?? "")
                    );

                    $_SESSION["id"] = $user["id"];
                    $_SESSION["nombre"] = $nombreCompleto;
                    $_SESSION["usuario"] = $user["usuario"];
                    $_SESSION["profesion_postgrado"] = $user["profesion_postgrado"] ?? "";
                    $_SESSION["id_rol"] = $user["id_rol"];
                    $_SESSION["nombre_rol"] = $user["nombre_rol"];
                    $_SESSION["rol"] = $rolSistema;

                    header("Location: index.php");
                    exit;
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SIGET ESAM | Iniciar sesión</title>

    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/login_esam.css?v=2">

</head>

<body>

<section class="login-screen" id="app-login" v-cloak>

    <div class="login-background-shape shape-one"></div>
    <div class="login-background-shape shape-two"></div>

    <div class="login-container">

        <div class="login-info">
            <div class="info-badge">
                Portal académico del participante
            </div>

            <h1>Sistema Integral de Gestión y Seguimiento de Titulación Académica</h1>

            <p>
                Accede a tu espacio académico para subir tus documentos, revisar el estado de tus entregas,
                consultar observaciones y hacer seguimiento a cada fase de tu proceso de titulación.
            </p>

            <div class="info-cards">
                <div class="info-card">
                    <span>📁</span>
                    <strong>Sube tus documentos</strong>
                    <small>Carga tus archivos académicos de forma ordenada y segura.</small>
                </div>

                <div class="info-card">
                    <span>✅</span>
                    <strong>Consulta tu avance</strong>
                    <small>Revisa en qué fase estás y el estado de tus entregas.</small>
                </div>

                <div class="info-card">
                    <span>💬</span>
                    <strong>Revisa observaciones</strong>
                    <small>Visualiza comentarios, correcciones y resultados de revisión.</small>
                </div>
            </div>
        </div>

        <div class="login-card">

            <div class="login-logo-box">
                <img src="/SIGET_ESAM/assets/img/logo.png" alt="Logo ESAM">
            </div>

            <div class="login-title">
                <span>Bienvenido</span>
                <h2>Iniciar sesión</h2>
                <p>Ingresa tus credenciales para acceder al sistema.</p>
            </div>

            <?php if ($error): ?>
                <div class="login-alert error">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form" @submit="validarFormulario">

                <div class="form-group">
                    <label>Usuario</label>

                    <div class="input-wrapper">
                        <span>👤</span>

                        <input
                            type="text"
                            name="usuario"
                            v-model="usuario"
                            placeholder="Ingrese su usuario"
                            autocomplete="username"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label>Contraseña</label>

                    <div class="input-wrapper">
                        <span>🔒</span>

                        <input
                            :type="mostrarPassword ? 'text' : 'password'"
                            name="contrasena"
                            v-model="contrasena"
                            placeholder="Ingrese su contraseña"
                            autocomplete="current-password"
                            required
                        >

                        <button type="button" class="password-toggle" @click="mostrarPassword = !mostrarPassword">
                            {{ mostrarPassword ? 'Ocultar' : 'Ver' }}
                        </button>
                    </div>
                </div>

                <div v-if="errorVue" class="login-alert error">
                    {{ errorVue }}
                </div>

                <button type="submit" class="login-button">
                    <span>Ingresar al sistema</span>
                    <strong>→</strong>
                </button>

            </form>

            <div class="login-footer">
                <small>© <?= date("Y"); ?> ESAM - Todos los derechos reservados</small>
                <small>Sistema ESAM v1.0</small>
            </div>

        </div>

    </div>

</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/login_esam.js?v=2"></script>

</body>
</html>