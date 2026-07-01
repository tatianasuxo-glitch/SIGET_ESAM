<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['id'])) {
    header('Location: /SIGET_ESAM/login.php');
    exit;
}

if (($_SESSION['rol'] ?? '') !== 'estudiante') {
    ?>
    <section class="dashboard-estudiante">
        <div class="student-summary-card">
            <h2>Acceso restringido</h2>
            <p>Esta sección está disponible únicamente para participantes estudiantes.</p>
        </div>
    </section>
    <?php
    return;
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    die('Error: revisa config/database.php. La conexión debe llamarse $conexion y usar PDO.');
}

$db = $conexion;

$usuarioId = (int) $_SESSION['id'];
$programaId = (int) ($_GET['programa_id'] ?? 0);

function eDashboard($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function fechaDashboard(?string $fecha, bool $incluirHora = false): string
{
    if (!$fecha) {
        return 'No definida';
    }

    $marcaTiempo = strtotime($fecha);

    if ($marcaTiempo === false) {
        return $fecha;
    }

    return $incluirHora
        ? date('d/m/Y, h:i a', $marcaTiempo)
        : date('d/m/Y', $marcaTiempo);
}

function estadoFaseEstudiante(array $fase): string
{
    $estado = strtoupper(trim((string) ($fase['estado_habilitacion'] ?? '')));

    if ($estado === 'ACTIVO') {
        return 'Habilitada';
    }

    return 'Pendiente de habilitación';
}

/*
|--------------------------------------------------------------------------
| Vista 1: Programas sincronizados del estudiante
|--------------------------------------------------------------------------
*/

if ($programaId <= 0) {
    $stmtProgramas = $db->prepare("
        SELECT
            i.id AS id_inscripcion,
            i.id_programa,
            i.fecha_inscripcion,
            i.estado_academico,
            i.estado_cartera,
            i.estado_acceso,
            i.observacion_cartera,
            i.observacion_academica,
            i.motivo_bloqueo,

            p.nombre_programa,
            p.tipo,
            p.gestion_externa,
            p.version_programa_externa,
            p.estado_programa_externo,

            (
                SELECT COUNT(*)
                FROM fase_estudiante_config fec
                INNER JOIN programa_fase_config pfc
                    ON pfc.id = fec.id_configuracion
                INNER JOIN fases f
                    ON f.id = pfc.id_fase
                WHERE fec.id_estudiante = i.id_estudiante
                  AND pfc.id_programa = i.id_programa
                  AND pfc.gestion = p.gestion_externa
                  AND f.numero_fase = 1
                  AND fec.estado = 'ACTIVO'
            ) AS fase_1_habilitada

        FROM inscripciones i
        INNER JOIN programa p
            ON p.id = i.id_programa
        WHERE i.id_estudiante = :id_estudiante
        ORDER BY
            i.fecha_inscripcion DESC,
            p.nombre_programa ASC
    ");

    $stmtProgramas->execute([
        ':id_estudiante' => $usuarioId
    ]);

    $programas = $stmtProgramas->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <section class="dashboard-estudiante">

        <div class="dashboard-title">
            <h1>Mis Programas</h1>
            <p>Selecciona el diplomado en el que deseas revisar tu proceso de titulación.</p>
        </div>

        <?php if (empty($programas)): ?>

            <div class="student-summary-card">
                <h2>No tienes programas sincronizados</h2>
                <p>
                    Actualmente no existe una inscripción institucional vinculada a tu cuenta.
                </p>
            </div>

        <?php else: ?>

            <div class="programas-grid">

                <?php foreach ($programas as $programa): ?>

                    <?php
                    $faseUnoHabilitada = (int) ($programa['fase_1_habilitada'] ?? 0) > 0;

                    $estadoProceso = $faseUnoHabilitada
                        ? 'Fase 1 habilitada'
                        : 'Pendiente de habilitación administrativa';
                    ?>

                    <div class="programa-card">

                        <span class="programa-tag">
                            <?= eDashboard($programa['tipo']) ?>
                        </span>

                        <h2><?= eDashboard($programa['nombre_programa']) ?></h2>

                        <div class="programa-info">

                            <p>
                                <strong>Gestión:</strong>
                                <?= eDashboard($programa['gestion_externa'] ?: 'No definida') ?>
                            </p>

                            <p>
                                <strong>Versión:</strong>
                                <?= eDashboard($programa['version_programa_externa'] ?: 'No definida') ?>
                            </p>

                            <p>
                                <strong>Estado académico:</strong>
                                <?= eDashboard($programa['estado_academico'] ?: 'No definido') ?>
                            </p>

                            <p>
                                <strong>Estado de cartera:</strong>
                                <?= eDashboard($programa['estado_cartera'] ?: 'No definido') ?>
                            </p>

                            <p>
                                <strong>Proceso:</strong>
                                <?= eDashboard($estadoProceso) ?>
                            </p>

                        </div>

                        <a
                            href="index.php?page=estudiante/dashboard&programa_id=<?= (int) $programa['id_programa'] ?>"
                            class="btn-ingresar-programa"
                        >
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
|--------------------------------------------------------------------------
| Vista 2: Dashboard de un programa seleccionado
|--------------------------------------------------------------------------
*/

$stmtEstudiante = $db->prepare("
    SELECT
        u.id AS usuario_id,
        u.usuario,
        u.nombres,
        u.apellido_paterno,
        u.apellido_materno,
        u.profesion_postgrado,
        u.ci,
        u.correo,
        u.celular,

        i.id AS id_inscripcion,
        i.id_programa,
        i.fecha_inscripcion,
        i.estado_academico,
        i.estado_cartera,
        i.estado_acceso,
        i.observacion_cartera,
        i.observacion_academica,
        i.motivo_bloqueo,

        p.nombre_programa,
        p.tipo,
        p.gestion_externa,
        p.version_programa_externa,
        p.estado_programa_externo

    FROM inscripciones i
    INNER JOIN usuarios u
        ON u.id = i.id_estudiante
    INNER JOIN programa p
        ON p.id = i.id_programa
    WHERE i.id_estudiante = :id_estudiante
      AND i.id_programa = :id_programa
    ORDER BY i.id DESC
    LIMIT 1
");

$stmtEstudiante->execute([
    ':id_estudiante' => $usuarioId,
    ':id_programa' => $programaId
]);

$estudiante = $stmtEstudiante->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    ?>

    <section class="dashboard-estudiante">
        <div class="student-summary-card">
            <h2>Programa no encontrado</h2>
            <p>El programa solicitado no existe o no está vinculado a tu usuario.</p>

            <a href="index.php?page=estudiante/dashboard" class="btn-ingresar-programa">
                Volver a mis programas
            </a>
        </div>
    </section>

    <?php
    return;
}

$stmtFases = $db->prepare("
    SELECT
        c.id AS id_configuracion,
        c.id_fase,
        c.gestion,
        c.tipo_trabajo,
        c.fecha_inicio_entrega,
        c.fecha_limite_entrega,
        c.fecha_limite_revision,
        c.fecha_devolucion_observaciones,
        c.nota_minima,
        c.estado AS estado_configuracion,

        f.numero_fase,
        f.nombre_fase,
        f.descripcion,

        fec.id AS id_habilitacion,
        fec.estado AS estado_habilitacion,
        fec.fecha_inicio_entrega AS fecha_inicio_individual,
        fec.fecha_limite_entrega AS fecha_limite_individual,
        fec.fecha_limite_revision AS fecha_revision_individual,
        fec.fecha_devolucion_observaciones AS fecha_devolucion_individual,
        fec.observacion AS observacion_habilitacion

    FROM programa_fase_config c
    INNER JOIN fases f
        ON f.id = c.id_fase
    LEFT JOIN fase_estudiante_config fec
        ON fec.id_configuracion = c.id
        AND fec.id_estudiante = :id_estudiante
        AND fec.estado = 'ACTIVO'

    WHERE c.id_programa = :id_programa
      AND c.gestion = :gestion
      AND c.estado = 'ACTIVO'
      AND f.estado = 1

    ORDER BY f.numero_fase ASC
");

$stmtFases->execute([
    ':id_estudiante' => $usuarioId,
    ':id_programa' => $programaId,
    ':gestion' => $estudiante['gestion_externa']
]);

$fases = $stmtFases->fetchAll(PDO::FETCH_ASSOC);

$nombreCompleto = trim(
    ($estudiante['nombres'] ?? '') . ' ' .
    ($estudiante['apellido_paterno'] ?? '') . ' ' .
    ($estudiante['apellido_materno'] ?? '')
);

$faseActual = null;

foreach ($fases as $fase) {
    if (strtoupper((string) ($fase['estado_habilitacion'] ?? '')) === 'ACTIVO') {
        $faseActual = $fase;
        break;
    }
}

$observaciones = array_filter([
    $estudiante['observacion_cartera'] ?? '',
    $estudiante['observacion_academica'] ?? '',
    $estudiante['motivo_bloqueo'] ?? ''
]);
?>

<section class="dashboard-estudiante">

    <div class="dashboard-title">
        <h1>Dashboard del Estudiante</h1>
        <p>Consulta el estado de tus fases y el avance de tu proceso de titulación.</p>
    </div>

    <a href="index.php?page=estudiante/dashboard" class="btn-volver-programas">
        ← Volver a mis programas
    </a>

    <div class="student-summary-card">

        <div class="student-main-info">
            <span>ESTUDIANTE</span>
            <h2><?= eDashboard($nombreCompleto) ?></h2>

            <span>PROGRAMA</span>
            <h3><?= eDashboard($estudiante['nombre_programa']) ?></h3>
        </div>

        <div class="student-info-grid">

            <div class="info-box">
                <span>Gestión</span>
                <strong><?= eDashboard($estudiante['gestion_externa'] ?: 'No definida') ?></strong>
            </div>

            <div class="info-box">
                <span>Versión</span>
                <strong><?= eDashboard($estudiante['version_programa_externa'] ?: 'No definida') ?></strong>
            </div>

            <div class="info-box">
                <span>Estado académico</span>
                <strong><?= eDashboard($estudiante['estado_academico'] ?: 'No definido') ?></strong>
            </div>

            <div class="info-box">
                <span>Estado de cartera</span>
                <strong><?= eDashboard($estudiante['estado_cartera'] ?: 'No definido') ?></strong>
            </div>

            <div class="info-box">
                <span>Acceso institucional</span>
                <strong><?= eDashboard($estudiante['estado_acceso'] ?: 'No definido') ?></strong>
            </div>

            <div class="info-box">
                <span>Fecha de inscripción</span>
                <strong><?= eDashboard(fechaDashboard($estudiante['fecha_inscripcion'])) ?></strong>
            </div>

        </div>

        <?php if (!empty($observaciones)): ?>
            <p class="student-note">
                <?= eDashboard(implode(' | ', $observaciones)) ?>
            </p>
        <?php endif; ?>

    </div>

    <div class="student-summary-card fase-card">

        <h2>Mis fases del proceso</h2>

        <?php if (empty($fases)): ?>

            <p>
                Todavía no existen fases activas configuradas para este diplomado y gestión.
            </p>

        <?php else: ?>

            <div class="programas-grid">

                <?php foreach ($fases as $fase): ?>

                    <?php
                    $habilitada = strtoupper((string) ($fase['estado_habilitacion'] ?? '')) === 'ACTIVO';

                    $fechaInicio = $habilitada && $fase['fecha_inicio_individual']
                        ? $fase['fecha_inicio_individual']
                        : $fase['fecha_inicio_entrega'];

                    $fechaLimite = $habilitada && $fase['fecha_limite_individual']
                        ? $fase['fecha_limite_individual']
                        : $fase['fecha_limite_entrega'];
                    ?>

                    <div class="programa-card">

                        <span class="programa-tag">
                            Fase <?= (int) $fase['numero_fase'] ?>
                        </span>

                        <h2><?= eDashboard($fase['nombre_fase']) ?></h2>

                        <div class="programa-info">

                            <p>
                                <strong>Estado:</strong>
                                <?= eDashboard(estadoFaseEstudiante($fase)) ?>
                            </p>

                            <p>
                                <strong>Trabajo:</strong>
                                <?= eDashboard($fase['tipo_trabajo']) ?>
                            </p>

                            <p>
                                <strong>Inicio:</strong>
                                <?= eDashboard(fechaDashboard($fechaInicio, true)) ?>
                            </p>

                            <p>
                                <strong>Límite:</strong>
                                <?= eDashboard(fechaDashboard($fechaLimite, true)) ?>
                            </p>

                            <p>
                                <strong>Nota mínima:</strong>
                                <?= eDashboard($fase['nota_minima']) ?>
                            </p>

                        </div>

                        <?php if ($habilitada): ?>

                            <p>
                                Esta fase se encuentra habilitada para ti.
                            </p>

                        <?php else: ?>

                            <p>
                                Esta fase todavía no fue habilitada. Debes concluir y aprobar la fase anterior.
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

    <?php if ($faseActual): ?>

        <div class="student-summary-card fase-card">

            <h2>Fase actualmente habilitada</h2>

            <p>
                <strong>
                    Fase <?= (int) $faseActual['numero_fase'] ?>:
                    <?= eDashboard($faseActual['nombre_fase']) ?>
                </strong>
            </p>

            <p>
                Tipo de trabajo:
                <strong><?= eDashboard($faseActual['tipo_trabajo']) ?></strong>
            </p>

            <p>
                En el siguiente paso se habilitará el registro del tema y la carga documental
                correspondiente a esta fase.
            </p>

        </div>

    <?php endif; ?>

</section>