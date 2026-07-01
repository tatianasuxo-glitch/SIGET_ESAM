<?php
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

$rol = $_SESSION["rol"] ?? "";
$nombre = $_SESSION["nombre"] ?? "";

/* ==============================
   DEFINIR PÁGINA SEGÚN ROL
============================== */

$page = $_GET["page"] ?? "";

if ($page === "") {
    if ($rol === "docente") {
        $page = "profesor/dashboard";
    } elseif ($rol === "estudiante") {
        $page = "estudiante/dashboard";
    } elseif ($rol === "administrador") {
        $page = "admin/dashboard";
    }
}

/* Limpieza básica de ruta */
$page = trim($page, "/");
$page = str_replace(["..", "\\"], ["", "/"], $page);

/* Posibles rutas */
$rutasPosibles = [
    __DIR__ . "/modules/" . $page . ".php",
    __DIR__ . "/modules/" . $page . "/index.php"
];

$rutaEncontrada = null;

foreach ($rutasPosibles as $ruta) {
    if (file_exists($ruta)) {
        $rutaEncontrada = $ruta;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel ESAM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS BASE -->
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/style.css?v=1">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/app.css?v=1">

    <!-- HEADER Y SIDEBAR -->
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/header.css?v=1">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/sidebar_esam.css?v=10">

    <!-- MÓDULOS -->
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/dashboard_estudiante.css?v=1">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/seguimiento.css?v=1">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/revision.css?v=1">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_programas.css?v=1">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_reglamentos.css?v=3">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_revision.css?v=3">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_fases.css?v=3">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_usuarios.css?v=3">
</head>

<body>

<div class="layout-esam">

    <?php include __DIR__ . "/includes/sidebar.php"; ?>

    <main class="main-content" id="mainContent">

        <?php include __DIR__ . "/includes/header.php"; ?>

        <?php
        if ($rutaEncontrada) {
            include $rutaEncontrada;
        } else {
            echo "<section class='page-card'>";
            echo "<h1>Pantalla no encontrada</h1>";
            echo "<p>La página solicitada no existe.</p>";
            echo "<p><strong>Ruta buscada:</strong> modules/" . htmlspecialchars($page) . ".php o modules/" . htmlspecialchars($page) . "/index.php</p>";
            echo "</section>";
        }
        ?>

    </main>

</div>

</body>
</html>