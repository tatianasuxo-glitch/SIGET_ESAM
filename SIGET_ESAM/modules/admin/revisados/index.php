<?php
require_once __DIR__ . "/../../../includes/functions.php";

if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? "") !== "administrador") {
    header("Location: login.php");
    exit;
}

$adminId = $_SESSION["id"];

$items = obtenerItemsRevisionPorResponsable("administrador", $adminId, "revisados");

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
        <h1>Revisados</h1>
        <p>Historial de documentos administrativos aprobados, observados o reprobados.</p>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="revision-empty success">
            <h2>Revisión guardada correctamente</h2>
            <p>El resultado fue registrado en el sistema.</p>
        </div>
    <?php endif; ?>

    <form method="GET" class="revision-filters">

        <input type="hidden" name="page" value="admin/revisados/index">

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

                <option value="APROBADO" <?php echo $filtros["estado"] === "APROBADO" ? "selected" : ""; ?>>
                    Aprobado
                </option>

                <option value="OBSERVADO" <?php echo $filtros["estado"] === "OBSERVADO" ? "selected" : ""; ?>>
                    Observado
                </option>

                <option value="REPROBADO" <?php echo $filtros["estado"] === "REPROBADO" ? "selected" : ""; ?>>
                    Reprobado
                </option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit">Filtrar</button>
            <a href="index.php?page=admin/revisados/index">Limpiar</a>
        </div>

    </form>

    <?php if (empty($itemsFiltrados)): ?>

        <div class="revision-empty">
            <h2>No existen revisiones registradas.</h2>
            <p>Aquí aparecerán los documentos administrativos aprobados, observados o reprobados.</p>
        </div>

    <?php else: ?>

        <div class="review-table-list">

            <?php foreach ($itemsFiltrados as $item): ?>

                <?php
                    $entrega = $item["entrega"] ?? [];
                    $estudiante = $item["estudiante"] ?? [];
                    $programa = $item["programa"] ?? [];
                    $fase = $item["fase"] ?? [];
                    $calificacion = $item["calificacion"] ?? null;

                    $estado = obtenerEstadoRevisionItem($item);

                    $nombreEstudiante = $estudiante["nombre"] ?? "Participante no encontrado";
                    $tituloPrograma = $programa["titulo"] ?? "Programa no registrado";
                    $tipoPrograma = $programa["tipo"] ?? "Sin tipo";
                    $gestionPrograma = $programa["gestion"] ?? "2026";
                    $nombreFase = $fase["nombre"] ?? "Fase no registrada";
                    $fechaEnvio = $entrega["fecha_envio"] ?? "Sin fecha";
                    $entregaId = $entrega["id"] ?? "";

                    $nota = $calificacion["nota"] ?? "Sin nota";
                    $fechaCalificacion = $calificacion["fecha_calificacion"] ?? "Sin fecha";
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
                        <span>Nota</span>
                        <strong>
                            <?php echo htmlspecialchars($nota); ?>
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
                            href="index.php?page=admin/revisados/detalle&entrega_id=<?php echo htmlspecialchars($entregaId); ?>"
                        >
                            Ver detalle
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