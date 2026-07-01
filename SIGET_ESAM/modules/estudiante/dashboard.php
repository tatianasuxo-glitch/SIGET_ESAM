<?php
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    die("Error: revisa config/database.php. La conexión debe llamarse \$conexion y usar PDO.");
}

$db = $conexion;

$usuarioId = $_SESSION["id"];
$programaId = intval($_GET["programa_id"] ?? 0);


if ($programaId <= 0) {

    $stmt = $db->prepare("
        SELECT
            up.id AS asignacion_id,
            up.usuario_id,
            up.programa_id,
            up.estado_academico,
            up.fecha_inscripcion,
            up.habilitacion,
            p.nombre_programa,
            p.tipo
        FROM usuario_programas up
        INNER JOIN programa p ON p.id = up.programa_id
        WHERE up.usuario_id = :usuario_id
        AND up.estado = 1
        AND p.estado = 1
        ORDER BY up.creado_el DESC
    ");

    $stmt->execute([
        ':usuario_id' => $usuarioId
    ]);

    $programa = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <section class="dashboard-estudiante">

        <div class="dashboard-title">
            <h1>Mis Programas</h1>
            <p>Selecciona el diplomado o maestría al que deseas ingresar.</p>
        </div>

        <?php if (empty($programa)): ?>

            <div class="student-summary-card">
                <h2>No tienes programas asignados</h2>
                <p>
                    Actualmente no se encontró ningún diplomado o maestría vinculado a tu usuario.
                </p>
            </div>

        <?php else: ?>

            <div class="programas-grid">

                <?php foreach ($programa as $p): ?>
                    <div class="programa-card">

                        <span class="programa-tag">
                            <?= htmlspecialchars($p["tipo"]) ?>
                        </span>

                        <h2><?= htmlspecialchars($p["nombre_programa"]) ?></h2>

                        <div class="programa-info">
                            <p>
                                <strong>Estado académico:</strong>
                                <?= htmlspecialchars($p["estado_academico"]) ?>
                            </p>

                            <p>
                                <strong>Fecha de inscripción:</strong>
                                <?= htmlspecialchars($p["fecha_inscripcion"] ?: "Sin registro") ?>
                            </p>

                            <p>
                                <strong>Habilitación:</strong>
                                <?= htmlspecialchars($p["habilitacion"]) ?>
                            </p>
                        </div>

                        <a href="index.php?page=estudiante/dashboard&programa_id=<?= $p["programa_id"] ?>" class="btn-ingresar-programa">
                            Ingresar
                        </a>

                    </div>
                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <?php
    return;
}

/*
    Si ya eligió un programa,
    se muestra el dashboard específico del programa seleccionado.
*/
$stmt = $db->prepare("
    SELECT
        u.id AS usuario_id,
        u.nombres,
        u.apellido_paterno,
        u.apellido_materno,
        u.profesion_postgrado,
        up.programa_id,
        up.estado_academico,
        up.fecha_inscripcion,
        up.habilitacion,
        up.observacion,
        p.nombre_programa,
        p.tipo
    FROM usuario_programas up
    INNER JOIN usuarios u ON u.id = up.usuario_id
    INNER JOIN programa p ON p.id = up.programa_id
    WHERE up.usuario_id = :usuario_id
    AND up.programa_id = :programa_id
    AND up.estado = 1
    AND p.estado = 1
    LIMIT 1
");

$stmt->execute([
    ':usuario_id' => $usuarioId,
    ':programa_id' => $programaId
]);

$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    ?>

    <section class="dashboard-estudiante">
        <div class="student-summary-card">
            <h2>Programa no encontrado</h2>
            <p>El programa solicitado no existe o no pertenece a tu usuario.</p>

            <a href="index.php?page=estudiante/dashboard" class="btn-ingresar-programa">
                Volver a mis programas
            </a>
        </div>
    </section>

    <?php
    return;
}

$nombreCompleto = trim(
    ($estudiante["nombres"] ?? '') . ' ' .
    ($estudiante["apellido_paterno"] ?? '') . ' ' .
    ($estudiante["apellido_materno"] ?? '')
);

$observacion = $estudiante["observacion"] ?: "El estudiante se encuentra en proceso académico o pendiente de validación.";
?>

<section class="dashboard-estudiante">

    <div class="dashboard-title">
        <h1>Dashboard del Estudiante</h1>
        <p>Resumen académico y carga documental del proceso de titulación.</p>
    </div>

    <a href="index.php?page=estudiante/dashboard" class="btn-volver-programas">
        ← Volver a mis programas
    </a>

    <div class="student-summary-card">

        <div class="student-main-info">
            <span>ESTUDIANTE</span>
            <h2><?= htmlspecialchars($nombreCompleto) ?></h2>

            <span>PROGRAMA</span>
            <h3><?= htmlspecialchars($estudiante["nombre_programa"]) ?></h3>
        </div>

        <div class="student-info-grid">

            <div class="info-box">
                <span>Tipo de programa</span>
                <strong><?= htmlspecialchars($estudiante["tipo"]) ?></strong>
            </div>

            <div class="info-box">
                <span>Estado académico</span>
                <strong><?= htmlspecialchars($estudiante["estado_academico"]) ?></strong>
            </div>

            <div class="info-box">
                <span>Fecha de inscripción</span>
                <strong><?= htmlspecialchars($estudiante["fecha_inscripcion"] ?: "Sin registro") ?></strong>
            </div>

            <div class="info-box">
                <span>Habilitación</span>
                <strong class="<?= $estudiante["habilitacion"] === "Habilitado para titulación" ? "text-success" : "text-warning" ?>">
                    <?= htmlspecialchars($estudiante["habilitacion"]) ?>
                </strong>
            </div>

        </div>

        <p class="student-note">
            <?= htmlspecialchars($observacion) ?>
        </p>

    </div>

    <div class="student-summary-card fase-card">
        <h2>No tiene documentos pendientes de carga</h2>
        <p>Esta fase fue aprobada. Puede continuar cuando la siguiente fase esté habilitada.</p>

        <a href="index.php?page=estudiante/Seguimiento" class="btn-ingresar-programa">
            Ver seguimiento
        </a>
    </div>

</section>