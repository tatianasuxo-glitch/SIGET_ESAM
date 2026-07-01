<?php
require_once __DIR__ . "/../../../includes/functions.php";

if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? "") !== "estudiante") {
    header("Location: login.php");
    exit;
}

$estudiante = obtenerEstudianteActual();

if (!$estudiante) {
    echo "
        <section class='seguimiento-page'>
            <div class='revision-empty'>
                <h2>No se encontró información del estudiante</h2>
                <p>Verifique que el usuario tenga una inscripción activa.</p>
            </div>
        </section>
    ";
    return;
}

$programa = obtenerProgramaPorId($estudiante["programa_id"]);

if (!$programa) {
    echo "
        <section class='seguimiento-page'>
            <div class='revision-empty'>
                <h2>No se encontró el programa académico</h2>
                <p>Verifique que el estudiante tenga un programa asignado.</p>
            </div>
        </section>
    ";
    return;
}

$seguimiento = calcularSeguimientoFases($estudiante, $programa);

$totalFases = count($seguimiento);
$fasesAprobadas = 0;

foreach ($seguimiento as $item) {
    if (($item["estado_vista"] ?? "") === "APROBADO") {
        $fasesAprobadas++;
    }
}

$porcentaje = $totalFases > 0 ? round(($fasesAprobadas / $totalFases) * 100) : 0;

$faseSeleccionadaId = $_GET["fase"] ?? "";
$itemSeleccionado = null;

if ($faseSeleccionadaId !== "") {
    foreach ($seguimiento as $item) {
        if ((string)$item["fase"]["id"] === (string)$faseSeleccionadaId) {
            $itemSeleccionado = $item;
            break;
        }
    }
}

if (!$itemSeleccionado) {
    foreach ($seguimiento as $item) {
        if (in_array($item["estado_vista"], ["HABILITADO", "EN_REVISION", "OBSERVADO", "REPROBADO"])) {
            $itemSeleccionado = $item;
            break;
        }
    }
}

if (!$itemSeleccionado && !empty($seguimiento)) {
    $itemSeleccionado = $seguimiento[0];
}

function segIconoEstado($estado)
{
    switch ($estado) {
        case "APROBADO":
            return "✅";
        case "EN_REVISION":
            return "⏳";
        case "OBSERVADO":
            return "⚠️";
        case "REPROBADO":
            return "❌";
        case "HABILITADO":
            return "📤";
        case "BLOQUEADO":
            return "🔒";
        default:
            return "🔒";
    }
}

function segTextoEstado($estado)
{
    switch ($estado) {
        case "APROBADO":
            return "Fase aprobada";
        case "EN_REVISION":
            return "En revisión";
        case "OBSERVADO":
            return "Con observaciones";
        case "REPROBADO":
            return "Fase no aprobada";
        case "HABILITADO":
            return "Puede continuar";
        case "BLOQUEADO":
            return "Fase bloqueada";
        case "NO_HABILITADO":
            return "No habilitado";
        default:
            return "Pendiente";
    }
}

function segClaseEstado($estado)
{
    switch ($estado) {
        case "APROBADO":
            return "approved";
        case "EN_REVISION":
            return "review";
        case "OBSERVADO":
            return "observed";
        case "REPROBADO":
            return "failed";
        case "HABILITADO":
            return "enabled";
        case "BLOQUEADO":
            return "blocked";
        default:
            return "blocked";
    }
}

function segMensajeGeneral($estado)
{
    switch ($estado) {
        case "APROBADO":
            return "La fase fue aprobada correctamente. Puede continuar con la siguiente fase cuando esté habilitada.";
        case "EN_REVISION":
            return "Su documento fue enviado correctamente. Debe esperar la revisión del responsable.";
        case "OBSERVADO":
            return "Su documento tiene observaciones. Revise los comentarios y vuelva a cargar la documentación corregida.";
        case "REPROBADO":
            return "La fase no fue aprobada. Debe comunicarse con coordinación académica.";
        case "HABILITADO":
            return "Debe subir el documento correspondiente desde el Dashboard.";
        case "BLOQUEADO":
            return "Debe aprobar la fase anterior para continuar.";
        default:
            return "El proceso se encuentra pendiente.";
    }
}

function segComentarioTrabajo($trabajo)
{
    if (!$trabajo) {
        return "";
    }

    $posiblesCampos = [
        "comentario",
        "comentario_revision",
        "observacion",
        "observaciones"
    ];

    foreach ($posiblesCampos as $campo) {
        if (!empty($trabajo[$campo])) {
            return $trabajo[$campo];
        }
    }

    return "";
}

$faseActual = $itemSeleccionado["fase"] ?? null;
$trabajoActual = $itemSeleccionado["trabajo"] ?? null;
$entregaActual = $itemSeleccionado["entrega"] ?? null;
$calificacionActual = $itemSeleccionado["calificacion"] ?? null;
$estadoActual = $itemSeleccionado["estado_vista"] ?? "BLOQUEADO";
$responsableActual = $itemSeleccionado["responsable"] ?? "Pendiente de asignación";

$comentarioActual = segComentarioTrabajo($trabajoActual);

if (!$comentarioActual && $calificacionActual && !empty($calificacionActual["comentario"])) {
    $comentarioActual = $calificacionActual["comentario"];
}
?>

<section class="seguimiento-page">

    <div class="seguimiento-header">
        <h1>Seguimiento del Proceso de Titulación</h1>
        <p><?php echo htmlspecialchars($estudiante["titulo_programa"]); ?></p>
    </div>

    <div class="process-summary">

        <div class="summary-main">
            <span>Estado general del proceso</span>

            <h2>
                <?php echo htmlspecialchars($faseActual["nombre"] ?? "Proceso de titulación"); ?>
            </h2>

            <p>
                <?php echo htmlspecialchars(segMensajeGeneral($estadoActual)); ?>
            </p>
        </div>

        <div class="summary-status <?php echo htmlspecialchars(segClaseEstado($estadoActual)); ?>">
            <div class="summary-icon">
                <?php echo segIconoEstado($estadoActual); ?>
            </div>

            <strong>
                <?php echo htmlspecialchars(segTextoEstado($estadoActual)); ?>
            </strong>
        </div>

        <div class="summary-progress">
            <div class="progress-title">
                <span>Avance del proceso</span>
                <strong><?php echo htmlspecialchars($porcentaje); ?>%</strong>
            </div>

            <div class="progress-bar">
                <div style="width: <?php echo htmlspecialchars($porcentaje); ?>%;"></div>
            </div>

            <p>
                <?php echo htmlspecialchars($fasesAprobadas); ?> de 
                <?php echo htmlspecialchars($totalFases); ?> fases aprobadas
            </p>
        </div>

    </div>

    <div class="phase-tabs">

        <?php foreach ($seguimiento as $item): ?>

            <?php
                $fase = $item["fase"];
                $estado = $item["estado_vista"];
                $activo = ((string)$fase["id"] === (string)($faseActual["id"] ?? ""));
            ?>

            <a 
                href="index.php?page=estudiante/Seguimiento/index&fase=<?php echo htmlspecialchars($fase["id"]); ?>"
                class="phase-tab <?php echo htmlspecialchars(segClaseEstado($estado)); ?> <?php echo $activo ? "active" : ""; ?>"
            >
                <div class="phase-tab-icon">
                    <?php echo segIconoEstado($estado); ?>
                </div>

                <div>
                    <strong>
                        Fase <?php echo htmlspecialchars($fase["numero"]); ?>
                    </strong>

                    <span>
                        <?php echo htmlspecialchars(segTextoEstado($estado)); ?>
                    </span>
                </div>
            </a>

        <?php endforeach; ?>

    </div>

    <?php if ($faseActual): ?>

        <div class="phase-detail-card <?php echo htmlspecialchars(segClaseEstado($estadoActual)); ?>">

            <div class="phase-detail-top">

                <div class="phase-title-area">
                    <div class="phase-number">
                        <?php echo htmlspecialchars($faseActual["numero"]); ?>
                    </div>

                    <div>
                        <h2>
                            <?php echo htmlspecialchars($faseActual["nombre"]); ?>
                        </h2>

                        <p>
                            <?php echo htmlspecialchars($faseActual["descripcion"]); ?>
                        </p>
                    </div>
                </div>

                <div class="phase-status-badge <?php echo htmlspecialchars(segClaseEstado($estadoActual)); ?>">
                    <?php echo segIconoEstado($estadoActual); ?>
                    <?php echo htmlspecialchars(segTextoEstado($estadoActual)); ?>
                </div>

            </div>

            <div class="phase-info-grid">

                <div>
                    <span>Responsable</span>
                    <strong><?php echo htmlspecialchars($responsableActual); ?></strong>
                </div>

                <div>
                    <span>Fecha límite de entrega</span>
                    <strong><?php echo htmlspecialchars($faseActual["fecha_limite_entrega"] ?? "Pendiente de asignación"); ?></strong>
                </div>

                <div>
                    <span>Fecha límite de revisión</span>
                    <strong><?php echo htmlspecialchars($faseActual["fecha_limite_revision"] ?? "Pendiente de asignación"); ?></strong>
                </div>

                <div>
                    <span>Nota mínima</span>
                    <strong><?php echo htmlspecialchars($faseActual["nota_minima"] ?? 71); ?></strong>
                </div>

            </div>

            <div class="next-action-box <?php echo htmlspecialchars(segClaseEstado($estadoActual)); ?>">

                <strong>Próxima acción:</strong>

                <?php if ($estadoActual === "HABILITADO"): ?>

                    <span>Debe subir el documento correspondiente desde el Dashboard.</span>

                    <a href="index.php?page=estudiante/dashboard">
                        Ir al Dashboard para subir documento
                    </a>

                <?php elseif ($estadoActual === "EN_REVISION"): ?>

                    <span>Debe esperar la revisión del responsable asignado.</span>

                <?php elseif ($estadoActual === "APROBADO"): ?>

                    <span>Esta fase fue aprobada. Puede continuar con la siguiente fase cuando esté habilitada.</span>

                <?php elseif ($estadoActual === "OBSERVADO"): ?>

                    <span>Revise las observaciones y vuelva a cargar el documento corregido desde el Dashboard.</span>

                    <a href="index.php?page=estudiante/dashboard">
                        Subir corrección
                    </a>

                <?php elseif ($estadoActual === "REPROBADO"): ?>

                    <span>Debe comunicarse con coordinación académica para recibir orientación.</span>

                <?php else: ?>

                    <span>Debe aprobar la fase anterior para continuar.</span>

                <?php endif; ?>

            </div>

            <div class="documents-box">

                <div class="documents-header">
                    <h3>Documentos enviados</h3>

                    <span>
                        <?php echo count($entregaActual["archivos"] ?? []); ?> archivo(s)
                    </span>
                </div>

                <?php if ($entregaActual && !empty($entregaActual["archivos"])): ?>

                    <div class="documents-list">

                        <?php foreach ($entregaActual["archivos"] as $archivo): ?>

                            <?php
                                $rutaArchivo = $archivo["ruta"] ?? "";
                                $nombreArchivo = $archivo["nombre_original"] ?? basename($rutaArchivo);
                                $tipoArchivo = strtoupper($archivo["tipo"] ?? pathinfo($rutaArchivo, PATHINFO_EXTENSION));
                            ?>

                            <div class="document-item">

                                <div>
                                    <strong>
                                        <?php echo htmlspecialchars($nombreArchivo); ?>
                                    </strong>

                                    <span>
                                        Archivo cargado para esta fase
                                    </span>
                                </div>

                                <div class="document-actions">

                                    <span class="document-type">
                                        <?php echo htmlspecialchars($tipoArchivo); ?>
                                    </span>

                                    <?php if ($rutaArchivo !== ""): ?>
                                        <a 
                                            href="<?php echo htmlspecialchars($rutaArchivo); ?>" 
                                            target="_blank" 
                                            class="document-eye"
                                            title="Ver documento"
                                        >
                                            👁️
                                        </a>
                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <p class="document-date">
                        Fecha de envío: <?php echo htmlspecialchars($entregaActual["fecha_envio"] ?? "Sin fecha"); ?>
                    </p>

                <?php else: ?>

                    <p class="empty-documents">
                        Aún no hay documentos enviados en esta fase.
                    </p>

                <?php endif; ?>

            </div>

            <?php if ($calificacionActual || $comentarioActual): ?>

                <div class="review-result-box">

                    <h3>Resultado de revisión</h3>

                    <?php if ($calificacionActual): ?>

                        <div class="review-result-grid">

                            <div>
                                <span>Nota</span>
                                <strong>
                                    <?php echo htmlspecialchars($calificacionActual["nota"] ?? "Sin nota"); ?>
                                </strong>
                            </div>

                            <div>
                                <span>Estado</span>
                                <strong>
                                    <?php echo htmlspecialchars($calificacionActual["estado"] ?? $estadoActual); ?>
                                </strong>
                            </div>

                            <div>
                                <span>Fecha de calificación</span>
                                <strong>
                                    <?php echo htmlspecialchars($calificacionActual["fecha_calificacion"] ?? "Sin fecha"); ?>
                                </strong>
                            </div>

                        </div>

                    <?php endif; ?>

                    <div class="review-comments">

                        <strong>Comentarios del responsable:</strong>

                        <?php if ($comentarioActual): ?>
                            <p><?php echo nl2br(htmlspecialchars($comentarioActual)); ?></p>
                        <?php else: ?>
                            <p>No se registraron comentarios adicionales.</p>
                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</section>