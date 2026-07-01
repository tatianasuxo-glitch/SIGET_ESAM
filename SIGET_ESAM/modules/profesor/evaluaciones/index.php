<?php
require_once __DIR__ . "/../../../includes/functions.php";

if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? "") !== "docente") {
    header("Location: login.php");
    exit;
}

$docenteId = $_SESSION["id"];

/*
    Obtiene trabajos asignados al docente desde SQL.
    Fuente:
    trabajos
    trabajo_docente
    usuarios
    inscripciones
    programa
    fases
*/
$items = obtenerItemsRevisionPorResponsable("docente", $docenteId, "pendientes");

$filtros = [
    "q" => $_GET["q"] ?? "",
    "programa_id" => $_GET["programa_id"] ?? "",
    "tipo" => $_GET["tipo"] ?? "",
    "gestion" => $_GET["gestion"] ?? "",
    "estado" => $_GET["estado"] ?? "",
    "fase_id" => $_GET["fase_id"] ?? ""
];

$programasFiltro = obtenerProgramasDesdeItems($items);
$fasesFiltro = obtenerFasesDesdeItems($items);
$gestionesFiltro = obtenerGestionesDesdeItems($items);

$itemsFiltrados = aplicarFiltrosRevision($items, $filtros);
?>

<section class="revision-page">

    <div class="revision-header">
        <h1>Bandeja de Evaluación Docente</h1>
        <p>Revise los trabajos académicos asignados a su perfil docente.</p>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="revision-empty success">
            <h2>Evaluación guardada correctamente</h2>
            <p>La calificación fue registrada y el trabajo pasó al listado de evaluaciones revisadas.</p>
        </div>
    <?php endif; ?>

    <form method="GET" class="revision-filters">

        <input type="hidden" name="page" value="profesor/evaluaciones/index">

        <div>
            <label>Buscar participante o programa</label>
            <input 
                type="text" 
                name="q" 
                placeholder="Ej: María, Gestión de Proyectos"
                value="<?php echo htmlspecialchars($filtros["q"]); ?>"
            >
        </div>

        <div>
            <label>Programa</label>
            <select name="programa_id">
                <option value="">Todos</option>

                <?php foreach ($programasFiltro as $programa): ?>
                    <option 
                        value="<?php echo htmlspecialchars($programa["id"]); ?>"
                        <?php echo ((string)$filtros["programa_id"] === (string)$programa["id"]) ? "selected" : ""; ?>
                    >
                        <?php echo htmlspecialchars($programa["titulo"]); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Tipo</label>
            <select name="tipo">
                <option value="">Todos</option>

                <option value="Maestría" <?php echo $filtros["tipo"] === "Maestría" ? "selected" : ""; ?>>
                    Maestría
                </option>

                <option value="Diplomado" <?php echo $filtros["tipo"] === "Diplomado" ? "selected" : ""; ?>>
                    Diplomado
                </option>
            </select>
        </div>

        <div>
            <label>Gestión</label>
            <select name="gestion">
                <option value="">Todas</option>

                <?php foreach ($gestionesFiltro as $gestion): ?>
                    <option 
                        value="<?php echo htmlspecialchars($gestion); ?>"
                        <?php echo $filtros["gestion"] === $gestion ? "selected" : ""; ?>
                    >
                        <?php echo htmlspecialchars($gestion); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Fase</label>
            <select name="fase_id">
                <option value="">Todas</option>

                <?php foreach ($fasesFiltro as $fase): ?>
                    <option 
                        value="<?php echo htmlspecialchars($fase["id"]); ?>"
                        <?php echo ((string)$filtros["fase_id"] === (string)$fase["id"]) ? "selected" : ""; ?>
                    >
                        <?php echo htmlspecialchars($fase["nombre"]); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>

                <option value="EN_REVISION" <?php echo $filtros["estado"] === "EN_REVISION" ? "selected" : ""; ?>>
                    En revisión
                </option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit">Filtrar</button>
            <a href="index.php?page=profesor/evaluaciones/index">Limpiar</a>
        </div>

    </form>

    <?php if (empty($itemsFiltrados)): ?>

        <div class="revision-empty">
            <h2>No existen evaluaciones pendientes.</h2>
            <p>
                Actualmente no tiene trabajos académicos asignados o no existen entregas pendientes de revisión.
            </p>
        </div>

    <?php else: ?>

        <div class="review-table-list">

            <?php foreach ($itemsFiltrados as $item): ?>

                <?php
                    $entrega = $item["entrega"] ?? [];
                    $estudiante = $item["estudiante"] ?? [];
                    $programa = $item["programa"] ?? [];
                    $fase = $item["fase"] ?? [];
                    $estado = obtenerEstadoRevisionItem($item);

                    $nombreEstudiante = $estudiante["nombre"] ?? "Participante no encontrado";
                    $tituloPrograma = $programa["titulo"] ?? "Programa no registrado";
                    $tipoPrograma = $programa["tipo"] ?? "Sin tipo";
                    $gestionPrograma = $programa["gestion"] ?? "2026";
                    $nombreFase = $fase["nombre"] ?? "Fase no registrada";
                    $fechaEnvio = $entrega["fecha_envio"] ?? "Sin fecha";
                    $entregaId = $entrega["id"] ?? "";
                ?>

                <div class="review-row">

                    <div class="review-main">
                        <span class="revision-label">Participante</span>

                        <h2>
                            <?php echo htmlspecialchars($nombreEstudiante); ?>
                        </h2>

                        <p>
                            <?php echo htmlspecialchars($tituloPrograma); ?> |
                            <?php echo htmlspecialchars($tipoPrograma); ?> |
                            Gestión <?php echo htmlspecialchars($gestionPrograma); ?>
                        </p>
                    </div>

                    <div class="review-meta">
                        <span>Fase</span>
                        <strong>
                            <?php echo htmlspecialchars($nombreFase); ?>
                        </strong>
                    </div>

                    <div class="review-meta">
                        <span>Fecha de envío</span>
                        <strong>
                            <?php echo htmlspecialchars($fechaEnvio); ?>
                        </strong>
                    </div>

                    <div class="revision-status">
                        <?php echo htmlspecialchars($estado); ?>
                    </div>

                    <?php if ($entregaId !== ""): ?>
                        <a 
                            class="review-action" 
                            href="index.php?page=profesor/evaluaciones/detalle&entrega_id=<?php echo htmlspecialchars($entregaId); ?>"
                        >
                            Ver / Calificar
                        </a>
                    <?php else: ?>
                        <span class="review-action disabled">
                            Sin entrega
                        </span>
                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>