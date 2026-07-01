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
                <a href='index.php?page=profesor/revisadas/index' class='back-link'>← Volver a revisadas</a>
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
                <p>No se encontró una evaluación revisada asignada a este docente.</p>
                <a href='index.php?page=profesor/revisadas/index' class='back-link'>← Volver a revisadas</a>
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

$estadoActual = obtenerEstadoRevisionItem($item);

$nombreEstudiante = $estudiante["nombre"] ?? "Participante no encontrado";
$tituloPrograma = $programa["titulo"] ?? "Programa no registrado";
$tipoPrograma = $programa["tipo"] ?? "Sin tipo";
$gestionPrograma = $programa["gestion"] ?? "2026";

$nombreFase = $fase["nombre"] ?? "Fase no registrada";
$fechaEnvio = $entrega["fecha_envio"] ?? "Sin fecha";
$notaMinima = $fase["nota_minima"] ?? 71;

$nota = $calificacion["nota"] ?? "Sin nota";
$comentario = $calificacion["comentario"] ?? "Sin comentario registrado";
$fechaCalificacion = $calificacion["fecha_calificacion"] ?? "Sin fecha registrada";

$archivos = $entrega["archivos"] ?? [];
?>

<section class="revision-page">

    <a href="index.php?page=profesor/revisadas/index" class="back-link">
        ← Volver a evaluaciones revisadas
    </a>

    <div class="revision-header">
        <h1>Detalle de Evaluación Revisada</h1>
        <p>Consulta del documento académico evaluado por el docente.</p>
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

            <div>
                <span>Nota obtenida</span>
                <strong>
                    <?php echo htmlspecialchars($nota); ?>
                </strong>
            </div>

            <div>
                <span>Fecha de calificación</span>
                <strong>
                    <?php echo htmlspecialchars($fechaCalificacion); ?>
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

        <div class="revision-files">

            <h3>Comentario de evaluación</h3>

            <div class="review-comment-box">
                <?php echo nl2br(htmlspecialchars($comentario)); ?>
            </div>

        </div>

    </div>

</section>