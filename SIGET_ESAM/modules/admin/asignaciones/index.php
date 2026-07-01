<?php
require_once __DIR__ . "/../../../includes/functions.php";

if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? "") !== "administrador") {
    header("Location: login.php");
    exit;
}

$trabajoOrigenId = $_GET["trabajo_id"] ?? "";

if ($trabajoOrigenId === "") {
    echo "
        <section class='revision-page'>
            <div class='revision-empty'>
                <h2>Trabajo no encontrado</h2>
                <p>No se recibió el identificador del trabajo aprobado.</p>
                <a href='index.php?page=admin/revisados/index' class='back-link'>← Volver a revisados</a>
            </div>
        </section>
    ";
    return;
}

$trabajoOrigen = obtenerTrabajoPorId($trabajoOrigenId);

if (!$trabajoOrigen) {
    echo "
        <section class='revision-page'>
            <div class='revision-empty'>
                <h2>Trabajo no encontrado</h2>
                <p>No se encontró el trabajo en la base de datos.</p>
                <a href='index.php?page=admin/revisados/index' class='back-link'>← Volver a revisados</a>
            </div>
        </section>
    ";
    return;
}

$estadoOrigen = normalizarEstadoRevision($trabajoOrigen["estado_aprobacion"] ?? "");

if ($estadoOrigen !== "APROBADO") {
    echo "
        <section class='revision-page'>
            <div class='revision-empty'>
                <h2>No se puede asignar responsable</h2>
                <p>Solo se puede asignar tutor, docente o tribunal cuando la fase anterior está aprobada.</p>
                <a href='index.php?page=admin/revisados/detalle&entrega_id=" . htmlspecialchars($trabajoOrigenId) . "' class='back-link'>← Volver al detalle</a>
            </div>
        </section>
    ";
    return;
}

$idEstudiante = $trabajoOrigen["id_estudiante"];
$numeroFaseActual = intval($trabajoOrigen["numero_fase"] ?? 1);
$numeroFaseSiguiente = $numeroFaseActual + 1;

$faseSiguiente = obtenerFasePorNumero($numeroFaseSiguiente);

if (!$faseSiguiente) {
    echo "
        <section class='revision-page'>
            <div class='revision-empty'>
                <h2>No existe una fase siguiente</h2>
                <p>El trabajo ya se encuentra en la última fase registrada.</p>
                <a href='index.php?page=admin/revisados/detalle&entrega_id=" . htmlspecialchars($trabajoOrigenId) . "' class='back-link'>← Volver al detalle</a>
            </div>
        </section>
    ";
    return;
}

$programa = obtenerProgramaEstudiante($idEstudiante);
$docentes = obtenerDocentes();
$tutores = obtenerTutores();

$trabajoSiguiente = obtenerTrabajoEstudianteFase($idEstudiante, $faseSiguiente["id"]);

$responsablesActuales = [];

if ($trabajoSiguiente) {
    $responsablesActuales = obtenerDocentesTrabajo($trabajoSiguiente["id"]);
}

$nombreEstudiante = nombreCompletoUsuario($trabajoOrigen);
$tituloPrograma = $programa["nombre_programa"] ?? "Programa no registrado";
$tipoPrograma = $programa["tipo"] ?? "Sin tipo";
?>

<section class="revision-page">

    <a href="index.php?page=admin/revisados/detalle&entrega_id=<?php echo htmlspecialchars($trabajoOrigenId); ?>" class="back-link">
        ← Volver al detalle
    </a>

    <div class="revision-header">
        <h1>Asignación de Responsable</h1>
        <p>Configure el tutor, docente calificador o tribunal para la siguiente fase del proceso.</p>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="revision-empty success">
            <h2>Asignación guardada correctamente</h2>
            <p>El responsable fue asignado para la siguiente fase.</p>
        </div>
    <?php endif; ?>

    <div class="revision-card">

        <div class="revision-card-top">
            <div>
                <span class="revision-label">Participante</span>
                <h2><?php echo htmlspecialchars($nombreEstudiante); ?></h2>
                <p>
                    <?php echo htmlspecialchars($tituloPrograma); ?> |
                    <?php echo htmlspecialchars($tipoPrograma); ?>
                </p>
            </div>

            <div class="revision-status">
                Fase <?php echo htmlspecialchars($numeroFaseSiguiente); ?>
            </div>
        </div>

        <div class="revision-grid">

            <div>
                <span>Fase aprobada</span>
                <strong><?php echo htmlspecialchars($trabajoOrigen["nombre_fase"]); ?></strong>
            </div>

            <div>
                <span>Siguiente fase</span>
                <strong><?php echo htmlspecialchars($faseSiguiente["nombre_fase"]); ?></strong>
            </div>

            <div>
                <span>Estado actual</span>
                <strong>
                    <?php echo $trabajoSiguiente ? htmlspecialchars($trabajoSiguiente["estado_aprobacion"]) : "Pendiente de habilitación"; ?>
                </strong>
            </div>

        </div>

        <?php if (!empty($responsablesActuales)): ?>

            <div class="revision-files">
                <h3>Responsables actualmente asignados</h3>

                <ul>
                    <?php foreach ($responsablesActuales as $responsable): ?>
                        <li>
                            <span>
                                <?php echo htmlspecialchars(nombreCompletoUsuario($responsable)); ?>
                            </span>

                            <span>
                                <?php echo htmlspecialchars($responsable["tipo_asignacion"]); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php endif; ?>

        <form action="api/guardar_asignacion.php" method="POST" class="revision-form">

            <input type="hidden" name="trabajo_origen_id" value="<?php echo htmlspecialchars($trabajoOrigenId); ?>">
            <input type="hidden" name="id_estudiante" value="<?php echo htmlspecialchars($idEstudiante); ?>">
            <input type="hidden" name="id_fase_siguiente" value="<?php echo htmlspecialchars($faseSiguiente["id"]); ?>">
            <input type="hidden" name="numero_fase_siguiente" value="<?php echo htmlspecialchars($numeroFaseSiguiente); ?>">

            <div class="form-row">

                <div>
                    <label>Tipo de asignación</label>

                    <select name="tipo_asignacion" required>
                        <option value="">Seleccione</option>
                        <option value="Tutor">Tutor</option>
                        <option value="Docente calificador">Docente calificador</option>
                        <option value="Tribunal">Tribunal</option>
                    </select>
                </div>

                <div>
                    <label>Título del trabajo para la siguiente fase</label>

                    <input 
                        type="text" 
                        name="titulo_trabajo"
                        value="<?php echo htmlspecialchars($faseSiguiente["nombre_fase"]); ?>"
                        required
                    >
                </div>

            </div>

            <label>Seleccione responsable(s)</label>

            <select name="responsables[]" multiple required style="min-height: 180px;">
                <optgroup label="Docentes">
                    <?php foreach ($docentes as $docente): ?>
                        <option value="<?php echo htmlspecialchars($docente["id"]); ?>">
                            <?php echo htmlspecialchars(nombreCompletoUsuario($docente)); ?> - Docente
                        </option>
                    <?php endforeach; ?>
                </optgroup>

                <optgroup label="Tutores">
                    <?php foreach ($tutores as $tutor): ?>
                        <option value="<?php echo htmlspecialchars($tutor["id"]); ?>">
                            <?php echo htmlspecialchars(nombreCompletoUsuario($tutor)); ?> - Tutor
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>

            <small>
                Para seleccionar más de un responsable, mantenga presionada la tecla CTRL y haga clic sobre los nombres.
            </small>

            <button type="submit">
                Guardar asignación
            </button>

        </form>

    </div>

</section>