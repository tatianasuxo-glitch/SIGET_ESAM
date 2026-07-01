<?php
require_once __DIR__ . "/../../../includes/functions.php";

if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? "") !== "docente") {
    header("Location: login.php");
    exit;
}

$docenteId = $_SESSION["id"];
$entregaId = $_GET["entrega_id"] ?? "";

if ($entregaId === "") {
    echo "
        <section class='revision-page'>
            <div class='revision-empty'>
                <h2>Entrega no encontrada</h2>
                <p>No se recibió el identificador de la entrega.</p>
                <a href='index.php?page=profesor/evaluaciones/index' class='back-link'>← Volver a evaluaciones</a>
            </div>
        </section>
    ";
    return;
}

$item = obtenerItemRevisionPorEntrega($entregaId, "docente", $docenteId);

if (!$item) {
    echo "
        <section class='revision-page'>
            <div class='revision-empty'>
                <h2>Entrega no encontrada</h2>
                <p>No se encontró una entrega asignada a este docente.</p>
                <a href='index.php?page=profesor/evaluaciones/index' class='back-link'>← Volver a evaluaciones</a>
            </div>
        </section>
    ";
    return;
}

$entrega = $item["entrega"] ?? [];
$estudiante = $item["estudiante"] ?? [];
$programa = $item["programa"] ?? [];
$fase = $item["fase"] ?? [];
$calificacion = $item["calificacion"] ?? null;

$paginaActual = $_GET["page"] ?? "profesor/evaluaciones/detalle";

$volver = strpos($paginaActual, "revisadas") !== false
    ? "profesor/revisadas/index"
    : "profesor/evaluaciones/index";

$estadoActual = obtenerEstadoRevisionItem($item);

$nombreEstudiante = $estudiante["nombre"] ?? "Participante no encontrado";
$tituloPrograma = $programa["titulo"] ?? "Programa no registrado";
$tipoPrograma = $programa["tipo"] ?? "Sin tipo";
$gestionPrograma = $programa["gestion"] ?? "2026";

$nombreFase = $fase["nombre"] ?? "Fase no registrada";
$fechaEnvio = $entrega["fecha_envio"] ?? "Sin fecha";
$notaMinima = $fase["nota_minima"] ?? 71;

$archivos = $entrega["archivos"] ?? [];
?>

<section class="revision-page">

    <a href="index.php?page=<?php echo htmlspecialchars($volver); ?>" class="back-link">
        ← Volver
    </a>

    <div class="revision-header">
        <h1>Detalle de Evaluación Docente</h1>
        <p>Revise el documento académico asignado y registre la evaluación correspondiente.</p>
    </div>

    <div class="revision-card">

        <div class="revision-card-top">

            <div>
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

            <div class="revision-status">
                <?php echo htmlspecialchars($estadoActual); ?>
            </div>

        </div>

        <div class="revision-grid">

            <div>
                <span>Fase</span>
                <strong>
                    <?php echo htmlspecialchars($nombreFase); ?>
                </strong>
            </div>

            <div>
                <span>Fecha de envío</span>
                <strong>
                    <?php echo htmlspecialchars($fechaEnvio); ?>
                </strong>
            </div>

            <div>
                <span>Nota mínima</span>
                <strong>
                    <?php echo htmlspecialchars($notaMinima); ?>
                </strong>
            </div>

        </div>

        <div class="revision-files">

            <h3>Documentos enviados</h3>

            <?php if (!empty($archivos) && is_array($archivos)): ?>

                <ul>
                    <?php foreach ($archivos as $archivo): ?>

                        <?php
                            $rutaArchivo = $archivo["ruta"] ?? "";
                            $nombreArchivo = $archivo["nombre_original"] ?? basename($rutaArchivo);
                            $tipoArchivo = strtoupper($archivo["tipo"] ?? pathinfo($rutaArchivo, PATHINFO_EXTENSION));
                        ?>

                        <li>
                            <?php if ($rutaArchivo !== ""): ?>
                                <a href="<?php echo htmlspecialchars($rutaArchivo); ?>" target="_blank">
                                    <?php echo htmlspecialchars($nombreArchivo); ?>
                                </a>
                            <?php else: ?>
                                <span>
                                    <?php echo htmlspecialchars($nombreArchivo); ?>
                                </span>
                            <?php endif; ?>

                            <span>
                                <?php echo htmlspecialchars($tipoArchivo); ?>
                            </span>
                        </li>

                    <?php endforeach; ?>
                </ul>

            <?php else: ?>

                <p>No hay archivos registrados para esta entrega.</p>

            <?php endif; ?>

        </div>

        <form action="api/guardar_calificacion.php" method="POST" class="revision-form">

            <input 
                type="hidden" 
                name="entrega_id" 
                value="<?php echo htmlspecialchars($entrega["id"] ?? ""); ?>"
            >

            <input 
                type="hidden" 
                name="estudiante_id" 
                value="<?php echo htmlspecialchars($entrega["estudiante_id"] ?? ""); ?>"
            >

            <input 
                type="hidden" 
                name="fase_id" 
                value="<?php echo htmlspecialchars($fase["id"] ?? ""); ?>"
            >

            <input 
                type="hidden" 
                name="evaluador_tipo" 
                value="docente"
            >

            <input 
                type="hidden" 
                name="return_to" 
                value="<?php echo htmlspecialchars($volver); ?>"
            >

            <div class="form-row">

                <div>
                    <label>Nota</label>

                    <input 
                        type="number" 
                        name="nota" 
                        min="0" 
                        max="100" 
                        step="0.01"
                        required 
                        value="<?php echo htmlspecialchars($calificacion["nota"] ?? ""); ?>"
                    >
                </div>

                <div>
                    <label>Resultado</label>

                    <select name="estado" required>
                        <option value="">Seleccione</option>

                        <option 
                            value="APROBADO"
                            <?php echo ($calificacion && ($calificacion["estado"] ?? "") === "APROBADO") ? "selected" : ""; ?>
                        >
                            Aprobar
                        </option>

                        <option 
                            value="OBSERVADO"
                            <?php echo ($calificacion && ($calificacion["estado"] ?? "") === "OBSERVADO") ? "selected" : ""; ?>
                        >
                            Observar
                        </option>

                        <option 
                            value="REPROBADO"
                            <?php echo ($calificacion && ($calificacion["estado"] ?? "") === "REPROBADO") ? "selected" : ""; ?>
                        >
                            Reprobar
                        </option>
                    </select>
                </div>

            </div>

            <label>Comentario para el participante</label>

            <textarea name="comentario" required><?php echo htmlspecialchars($calificacion["comentario"] ?? ""); ?></textarea>

            <button type="submit">
                Guardar evaluación docente
            </button>

        </form>

    </div>

</section>