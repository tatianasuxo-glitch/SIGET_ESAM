<?php
$nombre = $_SESSION["nombre"] ?? "Usuario";
$rol = $_SESSION["rol"] ?? "usuario";

$roles = [
    "estudiante" => "Estudiante",
    "docente" => "Docente",
    "administrador" => "Administrador"
];

$rolMostrar = $roles[$rol] ?? "Usuario";

$avatarIcon = [
    "estudiante" => "🎓",
    "docente" => "📘",
    "administrador" => "⚙️"
];

$icono = $avatarIcon[$rol] ?? "👤";
?>

<header class="esam-header">

    <div class="esam-header-left">
        <img src="assets/img/logo.png" alt="ESAM" class="esam-logo">

        <div class="esam-system">
            <h1>Sistema de Gestión Académica</h1>
            <span>Plataforma Documental de Posgrado</span>
        </div>
    </div>

    <div class="esam-header-right">

        <div class="esam-profile">
            <div class="esam-avatar">
                <?php echo $icono; ?>
            </div>

            <div class="esam-user-info">
                <strong><?php echo $nombre; ?></strong>
                <span><?php echo $rolMostrar; ?></span>
            </div>
        </div>

        <a href="logout.php" class="esam-logout">
            <span>Salir</span>
            <strong>↗</strong>
        </a>

    </div>
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/app.css">
    <link rel="stylesheet" href="/SIGET_ESAM/assets/css/componentes.css">

    <script src="https://unpkg.com/vue@3.5.13/dist/vue.global.prod.js"></script>
    <script src="/SIGET_ESAM/assets/js/siget-vue.js"></script>

</header>