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
    $partes = preg_split('/\s+/', trim((string) $nombre));

    $primera = $partes[0][0] ?? "U";
    $segunda = $partes[1][0] ?? "";

    return strtoupper($primera . $segunda);
}

/*
|--------------------------------------------------------------------------
| MENÚS ORGANIZADOS POR PROCESO ACADÉMICO
|--------------------------------------------------------------------------
| No se eliminan módulos existentes. Solo se reorganizan para que cada rol
| trabaje según su flujo real y no por herramientas técnicas separadas.
*/
$menus = [

    /*
    |--------------------------------------------------------------------------
    | ESTUDIANTE
    |--------------------------------------------------------------------------
    | El estudiante solo necesita saber:
    | 1. En qué fase está.
    | 2. Qué debe entregar.
    | 3. Qué observaciones tiene.
    |--------------------------------------------------------------------------
    */
    "estudiante" => [
        [
            "titulo" => "Mi titulación",
            "items" => [
                [
                    "icono" => "📊",
                    "texto" => "Inicio",
                    "ruta" => "estudiante/dashboard"
                ],
                [
                    "icono" => "🗂️",
                    "texto" => "Mi expediente de titulación",
                    "ruta" => "estudiante/expediente/index"
                ],
                [
                    "icono" => "📤",
                    "texto" => "Entregas y seguimiento",
                    "ruta" => "estudiante/seguimiento/index"
                ],
            ]
        ],
        [
            "titulo" => "Información",
            "items" => [
                [
                    "icono" => "📚",
                    "texto" => "Reglamentación",
                    "ruta" => "estudiante/reglamentacion"
                ],
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DOCENTE / JURADO
    |--------------------------------------------------------------------------
    | El jurado trabaja principalmente con evaluaciones asignadas.
    |--------------------------------------------------------------------------
    */
    "docente" => [
        [
            "titulo" => "Evaluación académica",
            "items" => [
                [
                    "icono" => "📊",
                    "texto" => "Dashboard",
                    "ruta" => "profesor/dashboard"
                ],
                [
                    "icono" => "📥",
                    "texto" => "Evaluaciones pendientes",
                    "ruta" => "profesor/evaluaciones/index"
                ],
                [
                    "icono" => "👨‍🎓",
                    "texto" => "Participantes asignados",
                    "ruta" => "profesor/estudiantes/index"
                ],
                [
                    "icono" => "✅",
                    "texto" => "Evaluaciones revisadas",
                    "ruta" => "profesor/revisadas/index"
                ],
            ]
        ],
        [
            "titulo" => "Consulta",
            "items" => [
                [
                    "icono" => "📈",
                    "texto" => "Reportes",
                    "ruta" => "profesor/reportes/index"
                ],
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRADOR
    |--------------------------------------------------------------------------
    | La bandeja "Procesos de Titulación" será el centro de trabajo diario.
    | Los demás módulos son bandejas operativas o configuraciones.
    |--------------------------------------------------------------------------
    */
    "administrador" => [
        [
            "titulo" => "Gestión académica",
            "items" => [
                [
                    "icono" => "📊",
                    "texto" => "Dashboard",
                    "ruta" => "admin/dashboard"
                ],
                [
                    "icono" => "🧭",
                    "texto" => "Procesos de titulación",
                    "ruta" => "admin/procesos/index"
                ],
                [
                    "icono" => "🗂️",
                    "texto" => "Revisión de propuestas · Fase 1",
                    "ruta" => "admin/revision/index"
                ],
                [
                    "icono" => "⏱️",
                    "texto" => "Correcciones y plazos",
                    "ruta" => "admin/reentregas/index"
                ],
                [
                    "icono" => "👨‍⚖️",
                    "texto" => "Jurados y asignaciones",
                    "ruta" => "admin/asignacion_jurados/index"
                ],
            ]
        ],

        [
            "titulo" => "Configuración",
            "items" => [
                [
                    "icono" => "🎓",
                    "texto" => "Programas y participantes",
                    "ruta" => "admin/programas_titulacion/index"
                ],
                [
                    "icono" => "📅",
                    "texto" => "Fases y fechas de entrega",
                    "ruta" => "admin/configuracion_fases/index"
                ],
                [
                    "icono" => "👥",
                    "texto" => "Usuarios",
                    "ruta" => "admin/configuracion_fases/participantes"
                ],
                [
                    "icono" => "📚",
                    "texto" => "Reglamentos",
                    "ruta" => "admin/reglamentos"
                ],
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

        <button
            type="button"
            class="brand-toggle"
            id="toggleSidebar"
            title="Contraer menú"
        >
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

<script src="/SIGET_ESAM/assets/js/sidebar_esam.js?v=4"></script>