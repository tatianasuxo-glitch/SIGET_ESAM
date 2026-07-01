<?php

$rol = $_SESSION["rol"] ?? "";
$paginaActual = $_GET["page"] ?? "";

$nombreUsuario = $_SESSION["nombres"]
    ?? $_SESSION["nombre"]
    ?? $_SESSION["usuario"]
    ?? "Usuario";

function sidebarActivo($paginaActual, $rutaBase)
{
    return str_starts_with($paginaActual, $rutaBase) ? "active" : "";
}

function inicialesSidebar($nombre)
{
    $partes = preg_split('/\s+/', trim((string)$nombre));

    $primera = $partes[0][0] ?? "U";
    $segunda = $partes[1][0] ?? "";

    return strtoupper($primera . $segunda);
}

$menus = [
    "estudiante" => [
        [
            "titulo" => "Mi proceso",
            "items" => [
                ["icono" => "📊", "texto" => "Dashboard", "ruta" => "estudiante/dashboard"],
                ["icono" => "📁", "texto" => "Seguimiento", "ruta" => "estudiante/Seguimiento/index"],
                ["icono" => "📚", "texto" => "Reglamentación", "ruta" => "estudiante/reglamentacion"],
            ]
        ],
    ],

    "docente" => [
        [
            "titulo" => "Docencia",
            "items" => [
                ["icono" => "📊", "texto" => "Dashboard", "ruta" => "profesor/dashboard"],
                ["icono" => "👨‍🎓", "texto" => "Participantes", "ruta" => "profesor/estudiantes/index"],
                ["icono" => "📥", "texto" => "Bandeja de Evaluación", "ruta" => "profesor/evaluaciones/index"],
                ["icono" => "✅", "texto" => "Evaluaciones Revisadas", "ruta" => "profesor/revisadas/index"],
                ["icono" => "📈", "texto" => "Reportes", "ruta" => "profesor/reportes/index"],
            ]
        ],
    ],

    "administrador" => [
        [
            "titulo" => "Principal",
            "items" => [
                ["icono" => "📊", "texto" => "Dashboard", "ruta" => "admin/dashboard"],
                ["icono" => "🎓", "texto" => "Programas Académicos", "ruta" => "admin/programas"],
                ["icono" => "🗂️", "texto" => "Revisión Administrativa", "ruta" => "admin/revision/index"],
                ["icono" => "✅", "texto" => "Gestión de Fases", "ruta" => "admin/configuracion_fases/index"],
            ]
        ],
        [
            "titulo" => "Administración",
            "items" => [
                ["icono" => "👥", "texto" => "Usuarios", "ruta" => "admin/usuarios/index"],
                ["icono" => "📚", "texto" => "Reglamentos", "ruta" => "admin/reglamentos"],
            ]
        ],
    ],
];

$menuSeleccionado = $menus[$rol] ?? [];
?>


<button type="button" class="sidebar-mobile-button" id="sidebarMobileButton">
    ☰
</button>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar-esam esam-sidebar" id="sidebar">

    <div class="sidebar-brand">
        <div class="brand-left">
            <div class="brand-logo">
                ES
            </div>

            <div class="brand-text">
                <strong>SIGET</strong>
                <span>Gestión Académica</span>
            </div>
        </div>

        <button type="button" class="brand-toggle" id="toggleSidebar" title="Contraer menú">
            ⇔
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?= htmlspecialchars(inicialesSidebar($nombreUsuario), ENT_QUOTES, "UTF-8") ?>
        </div>

        <div class="user-info">
            <strong><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, "UTF-8") ?></strong>
            <span><?= htmlspecialchars(ucfirst($rol), ENT_QUOTES, "UTF-8") ?></span>
        </div>
    </div>

    <nav class="sidebar-menu sidebar-nav">

        <?php foreach ($menuSeleccionado as $grupo): ?>

            <div class="sidebar-section-title">
                <?= htmlspecialchars($grupo["titulo"], ENT_QUOTES, "UTF-8") ?>
            </div>

            <?php foreach ($grupo["items"] as $item): ?>

                <a
                    href="/SIGET_ESAM/index.php?page=<?= htmlspecialchars($item["ruta"], ENT_QUOTES, "UTF-8") ?>"
                    class="sidebar-link <?= sidebarActivo($paginaActual, $item["ruta"]) ?>"
                    title="<?= htmlspecialchars($item["texto"], ENT_QUOTES, "UTF-8") ?>"
                >
                    <span class="link-icon">
                        <?= $item["icono"] ?>
                    </span>

                    <span class="link-text">
                        <?= htmlspecialchars($item["texto"], ENT_QUOTES, "UTF-8") ?>
                    </span>
                </a>

            <?php endforeach; ?>

        <?php endforeach; ?>

    </nav>

    <div class="sidebar-footer">
        <a href="/SIGET_ESAM/logout.php" class="sidebar-link logout-link">
            <span class="link-icon">⏻</span>
            <span class="link-text">Cerrar sesión</span>
        </a>

        <small class="system-version">
            Sistema ESAM v1.0
        </small>
    </div>

</aside>

<script src="/SIGET_ESAM/assets/js/sidebar_esam.js?v=3"></script>