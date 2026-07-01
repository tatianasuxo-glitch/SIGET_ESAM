<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['id'])) {
    header('Location: /SIGET_ESAM/login.php');
    exit;
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'estudiante') {
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
$baseUrl = '/SIGET_ESAM';

function eSeguimientoCompleto($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function fechaSeguimientoCompleto(?string $fecha, bool $hora = false): string
{
    if (!$fecha) {
        return 'No definida';
    }

    $marca = strtotime($fecha);

    if ($marca === false) {
        return $fecha;
    }

    return $hora ? date('d/m/Y H:i', $marca) : date('d/m/Y', $marca);
}

function normalizarEstadoSeguimientoCompleto(?string $estado): string
{
    $estado = strtoupper(trim((string) $estado));
    $estado = str_replace(
        ['Á', 'É', 'Í', 'Ó', 'Ú', ' '],
        ['A', 'E', 'I', 'O', 'U', '_'],
        $estado
    );

    return match ($estado) {
        'EN_REVISION', 'EN_REVISION_' => 'EN_REVISION',
        'OBSERVADO', 'OBSERVADA', 'RECHAZADO', 'RECHAZADA' => 'OBSERVADO',
        'CORREGIDO', 'CORREGIDA' => 'CORREGIDO',
        'APROBADO', 'APROBADA', 'REVISADO', 'REVISADA' => 'REVISADO',
        'BORRADOR' => 'BORRADOR',
        default => $estado ?: 'PENDIENTE',
    };
}

function estadoVistaSeguimientoCompleto(?array $fase, ?array $trabajo): string
{
    $numeroFase = (int) ($fase['numero_fase'] ?? 0);

    if ($trabajo) {
        $estadoTrabajo = normalizarEstadoSeguimientoCompleto($trabajo['estado_aprobacion'] ?? '');

        if ($numeroFase === 3 && $estadoTrabajo === 'REVISADO') {
            return 'APROBADO_FINAL';
        }

        return match ($estadoTrabajo) {
            'BORRADOR' => 'BORRADOR',
            'EN_REVISION' => 'EN_REVISION',
            'OBSERVADO' => 'OBSERVADO',
            'CORREGIDO' => 'CORREGIDO',
            'REVISADO' => 'REVISADO',
        };
    }

    $estadoHabilitacion = strtoupper(trim((string) ($fase['estado_habilitacion'] ?? '')));

    if ($estadoHabilitacion === 'APROBADO') {
        return $numeroFase === 3 ? 'APROBADO_FINAL' : 'REVISADO';
    }

    return 'PENDIENTE';
}

function textoEstadoSeguimientoCompleto(string $estado): string
{
    return match ($estado) {
        'PENDIENTE' => 'Pendiente',
        'BORRADOR' => 'Borrador guardado',
        'EN_REVISION' => 'En revisión',
        'OBSERVADO' => 'Con observaciones',
        'CORREGIDO' => 'Versión corregida',
        'REVISADO' => 'Revisado',
        'APROBADO_FINAL' => 'Aprobado final',
        default => 'Pendiente',
    };
}

function mensajeEstadoSeguimientoCompleto(string $estado): string
{
    return match ($estado) {
        'PENDIENTE' => 'Aún no existe una entrega pendiente de revisión para esta fase.',

        'BORRADOR' => 'Tienes un documento guardado como borrador. Para enviarlo al jurado, utiliza el botón Enviar a revisión.',

        'EN_REVISION' => 'Tu documento fue enviado correctamente y se encuentra en proceso de revisión. No necesitas realizar ninguna acción por el momento.',

        'OBSERVADO' => 'Tu documento recibió observaciones. Revisa el resultado vigente y espera que Administración autorice una nueva entrega.',

        'CORREGIDO' => 'La versión corregida fue enviada correctamente y está pendiente de una nueva revisión.',

        'REVISADO' => 'La revisión de esta fase fue concluida correctamente.',

        'APROBADO_FINAL' => 'La revisión final fue aprobada. Administración gestionará el acta de evaluación y el código de empaste cuando corresponda.',

        default => 'Revisa la información de esta fase.',
    };
}

function nombreFaseVisibleSeguimientoCompleto(string $tipoPrograma, int $numeroFase, string $nombreOriginal): string
{
    if (strtoupper(trim($tipoPrograma)) !== 'DIPLOMADO') {
        return $nombreOriginal;
    }

    return match ($numeroFase) {
        1 => 'Registro y Presentación de Propuesta',
        2 => 'Desarrollo del Trabajo Final',
        3 => 'Evaluación Final y Titulación',
        default => $nombreOriginal,
    };
}

function puedeRegistrarPrimeraEntrega(?array $fase, ?array $trabajo): bool
{
    $estadoHabilitacion = strtoupper(trim((string) ($fase['estado_habilitacion'] ?? '')));

    if ($estadoHabilitacion !== 'ACTIVO') {
        return false;
    }

    if ($trabajo === null) {
        return true;
    }

    return normalizarEstadoSeguimientoCompleto($trabajo['estado_aprobacion'] ?? '') === 'BORRADOR';
}

/* --------------------------------------------------------------------------
   Programas sincronizados del estudiante
-------------------------------------------------------------------------- */
$stmtProgramas = $db->prepare("
    SELECT
        i.id_programa,
        i.fecha_inscripcion,
        i.estado_academico,
        i.estado_cartera,
        i.estado_acceso,
        p.nombre_programa,
        p.tipo,
        p.gestion_externa,
        p.version_programa_externa
    FROM inscripciones i
    INNER JOIN programa p ON p.id = i.id_programa
    WHERE i.id_estudiante = :id_estudiante
    ORDER BY i.fecha_inscripcion DESC, p.nombre_programa ASC
");

$stmtProgramas->execute([
    ':id_estudiante' => $usuarioId,
]);

$programas = $stmtProgramas->fetchAll(PDO::FETCH_ASSOC);

if ($programaId <= 0 && count($programas) === 1) {
    $programaId = (int) $programas[0]['id_programa'];
}

if (empty($programas) || $programaId <= 0) {
    ?>
    <section class="dashboard-estudiante">
        <div class="student-summary-card">
            <h2>No tienes programas sincronizados</h2>
            <p>No se encontró una inscripción institucional vinculada a tu cuenta.</p>
        </div>
    </section>
    <?php
    return;
}

if (count($programas) > 1 && !isset($_GET['programa_id'])) {
    ?>
    <section class="dashboard-estudiante">
        <div class="dashboard-title">
            <h1>Seguimiento de titulación</h1>
            <p>Selecciona el programa cuyo proceso deseas consultar.</p>
        </div>

        <div class="programas-grid">
            <?php foreach ($programas as $programa): ?>
                <div class="programa-card">
                    <span class="programa-tag"><?= eSeguimientoCompleto($programa['tipo']) ?></span>
                    <h2><?= eSeguimientoCompleto($programa['nombre_programa']) ?></h2>
                    <p><strong>Gestión:</strong> <?= eSeguimientoCompleto($programa['gestion_externa'] ?: 'No definida') ?></p>
                    <a class="btn-ingresar-programa" href="index.php?page=estudiante/seguimiento&programa_id=<?= (int) $programa['id_programa'] ?>">
                        Ver seguimiento
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return;
}

/* --------------------------------------------------------------------------
   Inscripción y programa seleccionado
-------------------------------------------------------------------------- */
$stmtInscripcion = $db->prepare("
    SELECT
        i.id AS id_inscripcion,
        i.id_programa,
        i.fecha_inscripcion,
        i.estado_academico,
        i.estado_cartera,
        i.estado_acceso,
        p.nombre_programa,
        p.tipo,
        p.gestion_externa,
        p.version_programa_externa
    FROM inscripciones i
    INNER JOIN programa p ON p.id = i.id_programa
    WHERE i.id_estudiante = :id_estudiante
      AND i.id_programa = :id_programa
    ORDER BY i.id DESC
    LIMIT 1
");

$stmtInscripcion->execute([
    ':id_estudiante' => $usuarioId,
    ':id_programa' => $programaId,
]);

$inscripcion = $stmtInscripcion->fetch(PDO::FETCH_ASSOC);

if (!$inscripcion) {
    ?>
    <section class="dashboard-estudiante">
        <div class="student-summary-card">
            <h2>Programa no encontrado</h2>
            <p>El programa solicitado no está vinculado a tu cuenta.</p>
        </div>
    </section>
    <?php
    return;
}

/* --------------------------------------------------------------------------
   Jurados asignados al participante
-------------------------------------------------------------------------- */
$stmtJurados = $db->prepare("
    SELECT
        ja.id AS id_jurado_asignacion,
        ja.rol_jurado,
        ja.observacion,
        ja.fecha_asignacion,
        CONCAT_WS(' ', ju.nombres, ju.apellido_paterno, ju.apellido_materno) AS nombre_jurado
    FROM titulacion_postulaciones tp
    INNER JOIN jurado_asignaciones ja
        ON ja.id_postulacion = tp.id
       AND ja.estado = 'ASIGNADO'
    INNER JOIN usuarios ju
        ON ju.id = ja.id_docente
    WHERE tp.id_inscripcion = :id_inscripcion
    ORDER BY ja.fecha_asignacion ASC, ja.id ASC
");

$stmtJurados->execute([
    ':id_inscripcion' => $inscripcion['id_inscripcion'],
]);

$jurados = $stmtJurados->fetchAll(PDO::FETCH_ASSOC);
$nombreJurados = implode(' | ', array_filter(array_column($jurados, 'nombre_jurado')));

/* --------------------------------------------------------------------------
   Todas las fases configuradas y su último trabajo
-------------------------------------------------------------------------- */
$stmtFases = $db->prepare("
    SELECT
        pfc.id AS id_configuracion,
        pfc.tipo_trabajo,
        pfc.fecha_inicio_entrega,
        pfc.fecha_limite_entrega,
        pfc.fecha_limite_revision,
        pfc.fecha_devolucion_observaciones,
        pfc.nota_minima,
        pfc.gestion,

        f.id AS id_fase,
        f.numero_fase,
        f.nombre_fase,
        f.descripcion,

        fec.id AS id_habilitacion,
        fec.estado AS estado_habilitacion,
        fec.fecha_inicio_entrega AS fecha_inicio_individual,
        fec.fecha_limite_entrega AS fecha_limite_individual,
        fec.fecha_limite_revision AS fecha_revision_individual,
        fec.fecha_devolucion_observaciones AS fecha_devolucion_individual,
        fec.observacion AS observacion_habilitacion,

        t.id AS id_trabajo,
        t.titulo_trabajo,
        t.fecha_presentacion,
        t.estado_aprobacion,
        t.calificacion_final,
        t.comentario_revision,
        t.fecha_revision,
        t.ruta_archivo,
        t.actualizado_el

    FROM programa_fase_config pfc
    INNER JOIN fases f
        ON f.id = pfc.id_fase
    LEFT JOIN fase_estudiante_config fec
        ON fec.id_configuracion = pfc.id
       AND fec.id_estudiante = :id_estudiante
    LEFT JOIN trabajos t
        ON t.id = (
            SELECT t2.id
            FROM trabajos t2
            WHERE t2.id_estudiante = :id_estudiante_trabajo
              AND t2.id_configuracion = pfc.id
            ORDER BY t2.id DESC
            LIMIT 1
        )
    WHERE pfc.id_programa = :id_programa
      AND pfc.gestion = :gestion
      AND pfc.estado = 'ACTIVO'
      AND f.estado = 1
    ORDER BY f.numero_fase ASC
");

$stmtFases->execute([
    ':id_estudiante' => $usuarioId,
    ':id_estudiante_trabajo' => $usuarioId,
    ':id_programa' => $programaId,
    ':gestion' => $inscripcion['gestion_externa'],
]);

$fases = $stmtFases->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Resultado vigente de revisión
|--------------------------------------------------------------------------
| El historial completo se conserva en trabajo_revisiones para auditoría,
| pero el estudiante ve únicamente el último resultado vigente.
*/
$stmtRevisiones = $db->prepare("
    SELECT
        tr.decision,
        tr.calificacion,
        tr.comentario,
        tr.origen,
        tr.fecha_revision,
        CONCAT_WS(' ', ur.nombres, ur.apellido_paterno, ur.apellido_materno) AS revisor
    FROM trabajo_revisiones tr
    INNER JOIN trabajo_entregas te
        ON te.id = tr.id_entrega
    LEFT JOIN usuarios ur
        ON ur.id = tr.id_revisor
    WHERE te.id_trabajo = :id_trabajo
    ORDER BY tr.fecha_revision DESC, tr.id DESC
    LIMIT 1
");

/*
|--------------------------------------------------------------------------
| Último control de reentrega
|--------------------------------------------------------------------------
| Se consulta únicamente cuando el jurado dejó observaciones. Administración
| define si existe nueva entrega autorizada, cerrada o pendiente de autorización.
*/
$stmtControlReentrega = $db->prepare("
    SELECT
        id,
        ciclo,
        estado,
        fecha_autorizacion,
        fecha_limite_correccion,
        fecha_reentrega,
        motivo,
        observacion_cierre
    FROM control_reentregas
    WHERE id_trabajo = :id_trabajo
    ORDER BY ciclo DESC, id DESC
    LIMIT 1
");

$indiceTituloAprobado = '';
$fasesProceso = [];

foreach ($fases as $fase) {
    $trabajo = !empty($fase['id_trabajo']) ? [
        'id' => $fase['id_trabajo'],
        'titulo_trabajo' => $fase['titulo_trabajo'],
        'fecha_presentacion' => $fase['fecha_presentacion'],
        'estado_aprobacion' => $fase['estado_aprobacion'],
        'calificacion_final' => $fase['calificacion_final'],
        'comentario_revision' => $fase['comentario_revision'],
        'fecha_revision' => $fase['fecha_revision'],
        'ruta_archivo' => $fase['ruta_archivo'],
        'actualizado_el' => $fase['actualizado_el'],
    ] : null;

    $estado = estadoVistaSeguimientoCompleto($fase, $trabajo);
    $numeroFase = (int) $fase['numero_fase'];

    /*
    |--------------------------------------------------------------------------
    | Responsable por fase
    |--------------------------------------------------------------------------
    | Fase 1: Administración Académica.
    | Fases 2 y 3: el mismo jurado asignado después de revisar Fase 1.
    */
    if ($numeroFase === 1) {
        $responsable = 'Administración Académica';
    } else {
        $responsable = $nombreJurados !== ''
            ? $nombreJurados
            : 'Jurado pendiente de asignación';
    }

    $revisiones = [];

    if ($trabajo) {
        $stmtRevisiones->execute([
            ':id_trabajo' => $trabajo['id'],
        ]);

        $revisionVigente = $stmtRevisiones->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($revisionVigente !== null) {
            $revisiones[] = $revisionVigente;
        } elseif (!empty($trabajo['comentario_revision'])) {
            $revisiones[] = [
                'decision' => $estado,
                'calificacion' => null,
                'comentario' => $trabajo['comentario_revision'],
                'origen' => $numeroFase === 1 ? 'ADMINISTRACION' : 'JURADO',
                'fecha_revision' => $trabajo['fecha_revision'],
                'revisor' => $responsable,
            ];
        }
    }

    $controlReentrega = null;

    if ($trabajo !== null) {
        $stmtControlReentrega->execute([
            ':id_trabajo' => $trabajo['id'],
        ]);

        $controlReentrega = $stmtControlReentrega->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($indiceTituloAprobado === '' && $trabajo && !empty($trabajo['titulo_trabajo']) && $estado === 'REVISADO') {
        $indiceTituloAprobado = trim((string) $trabajo['titulo_trabajo']);
    }

    $fase['trabajo'] = $trabajo;
    $fase['estado_vista'] = $estado;
    $fase['responsable'] = $responsable;
    $fase['revisiones'] = $revisiones;
    $fase['control_reentrega'] = $controlReentrega;
    $fase['nombre_visible'] = nombreFaseVisibleSeguimientoCompleto(
        (string) $inscripcion['tipo'],
        $numeroFase,
        (string) $fase['nombre_fase']
    );
    $fase['titulo_sugerido'] = $trabajo['titulo_trabajo'] ?? $indiceTituloAprobado;

    $fasesProceso[] = $fase;
}

$faseActual = null;
foreach ($fasesProceso as $faseProceso) {
    if (strtoupper(trim((string) ($faseProceso['estado_habilitacion'] ?? ''))) === 'ACTIVO') {
        $faseActual = $faseProceso;
        break;
    }
}

if ($faseActual === null) {
    foreach ($fasesProceso as $faseProceso) {
        if (in_array($faseProceso['estado_vista'], ['EN_REVISION', 'OBSERVADO', 'CORREGIDO'], true)) {
            $faseActual = $faseProceso;
            break;
        }
    }
}

if ($faseActual === null && !empty($fasesProceso)) {
    $faseActual = end($fasesProceso);
}

$fasesRevisadas = count(array_filter(
    $fasesProceso,
    static fn(array $faseProceso): bool => in_array(
        $faseProceso['estado_vista'],
        ['REVISADO', 'APROBADO_FINAL'],
        true
    )
));

$totalFases = count($fasesProceso);
$porcentaje = $totalFases > 0 ? (int) round(($fasesRevisadas / $totalFases) * 100) : 0;

$flash = $_SESSION['flash_seguimiento'] ?? null;
unset($_SESSION['flash_seguimiento']);
?>

<section class="dashboard-estudiante">

    <div class="dashboard-title">
        <h1>Seguimiento de titulación</h1>
        <p>
            <?= eSeguimientoCompleto($inscripcion['nombre_programa']) ?>
            · Gestión <?= eSeguimientoCompleto($inscripcion['gestion_externa'] ?: 'No definida') ?>
        </p>
    </div>

    <?php if ($flash): ?>
        <div class="student-summary-card">
            <strong><?= eSeguimientoCompleto($flash['tipo'] === 'success' ? 'Proceso realizado correctamente' : 'No se pudo completar la operación') ?></strong>
            <p><?= eSeguimientoCompleto($flash['mensaje']) ?></p>
        </div>
    <?php endif; ?>

    <div class="student-summary-card">
        <div class="student-main-info">
            <span>FASE ACTUAL</span>
            <h2><?= eSeguimientoCompleto($faseActual['nombre_visible'] ?? 'Pendiente de habilitación') ?></h2>
            <span>ESTADO</span>
            <h3><?= eSeguimientoCompleto(textoEstadoSeguimientoCompleto($faseActual['estado_vista'] ?? 'PENDIENTE')) ?></h3>
        </div>

        <div class="student-info-grid">
            <div class="info-box">
                <span>Responsable actual</span>
                <strong><?= eSeguimientoCompleto($faseActual['responsable'] ?? 'Pendiente de asignación') ?></strong>
            </div>

            <div class="info-box">
                <span>Avance del proceso</span>
                <strong><?= $porcentaje ?>% · <?= $fasesRevisadas ?>/<?= $totalFases ?> revisadas</strong>
            </div>

            <div class="info-box">
                <span>Estado académico</span>
                <strong><?= eSeguimientoCompleto($inscripcion['estado_academico'] ?: 'No definido') ?></strong>
            </div>

            <div class="info-box">
                <span>Jurado asignado para Fases 2 y 3</span>
                <strong><?= eSeguimientoCompleto($nombreJurados ?: 'Pendiente de asignación') ?></strong>
            </div>
        </div>
    </div>

    <div class="student-summary-card fase-card">
        <h2>Estado de mis fases</h2>

        <div class="programas-grid">
            <?php foreach ($fasesProceso as $faseProceso): ?>
                <div class="programa-card">
                    <span class="programa-tag">FASE <?= (int) $faseProceso['numero_fase'] ?></span>
                    <h2><?= eSeguimientoCompleto($faseProceso['nombre_visible']) ?></h2>
                    <p><strong>Estado:</strong> <?= eSeguimientoCompleto(textoEstadoSeguimientoCompleto($faseProceso['estado_vista'])) ?></p>
                    <p><strong>Responsable:</strong> <?= eSeguimientoCompleto($faseProceso['responsable']) ?></p>
                    <p><strong>Entrega:</strong> <?= eSeguimientoCompleto(fechaSeguimientoCompleto($faseProceso['fecha_limite_individual'] ?: $faseProceso['fecha_limite_entrega'], true)) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php foreach ($fasesProceso as $faseProceso): ?>
        <?php
        $trabajo = $faseProceso['trabajo'];
        $estadoVista = $faseProceso['estado_vista'];
        $fechaInicio = $faseProceso['fecha_inicio_individual'] ?: $faseProceso['fecha_inicio_entrega'];
        $fechaLimite = $faseProceso['fecha_limite_individual'] ?: $faseProceso['fecha_limite_entrega'];
        $fechaRevision = $faseProceso['fecha_revision_individual'] ?: $faseProceso['fecha_limite_revision'];
        $fechaDevolucion = $faseProceso['fecha_devolucion_individual'] ?: $faseProceso['fecha_devolucion_observaciones'];
        $faseActiva = strtoupper(trim((string) ($faseProceso['estado_habilitacion'] ?? ''))) === 'ACTIVO';

        $controlReentrega = $faseProceso['control_reentrega'] ?? null;
        $estadoControl = strtoupper(trim((string) ($controlReentrega['estado'] ?? '')));
        $fechaLimiteCorreccion = $controlReentrega['fecha_limite_correccion'] ?? null;

        $correccionAutorizada =
            $estadoVista === 'OBSERVADO'
            && $estadoControl === 'AUTORIZADA'
            && !empty($fechaLimiteCorreccion)
            && strtotime($fechaLimiteCorreccion) >= time();

        $correccionVencida =
            $estadoVista === 'OBSERVADO'
            && $estadoControl === 'AUTORIZADA'
            && !empty($fechaLimiteCorreccion)
            && strtotime($fechaLimiteCorreccion) < time();

        $correccionCerrada =
            $estadoVista === 'OBSERVADO'
            && $estadoControl === 'CERRADA';

        $puedeRegistrar =
            puedeRegistrarPrimeraEntrega($faseProceso, $trabajo)
            || $correccionAutorizada;

        $documentoObligatorio =
            !$trabajo
            || empty($trabajo['ruta_archivo'])
            || $correccionAutorizada;

        $mensajeEstado = mensajeEstadoSeguimientoCompleto($estadoVista);

        if ($estadoVista === 'OBSERVADO' && $correccionAutorizada) {
            $mensajeEstado = 'Administración autorizó una nueva entrega. Corrige el documento y envía una nueva versión dentro del plazo habilitado.';
        } elseif ($estadoVista === 'OBSERVADO' && $correccionVencida) {
            $mensajeEstado = 'El plazo autorizado para corregir este documento venció. Comunícate con Administración Académica.';
        } elseif ($estadoVista === 'OBSERVADO' && $correccionCerrada) {
            $mensajeEstado = 'La corrección fue cerrada por Administración.';
        } elseif ($estadoVista === 'OBSERVADO' && $estadoControl === 'PENDIENTE_AUTORIZACION') {
            $mensajeEstado = 'Tu documento recibió observaciones. Administración revisará el caso antes de habilitar una nueva entrega.';
        }
        ?>

        <div class="student-summary-card fase-card">
            <span>FASE <?= (int) $faseProceso['numero_fase'] ?></span>
            <h2><?= eSeguimientoCompleto($faseProceso['nombre_visible']) ?></h2>

            <div class="student-info-grid">
                <div class="info-box">
                    <span>Estado del documento</span>
                    <strong><?= eSeguimientoCompleto(textoEstadoSeguimientoCompleto($estadoVista)) ?></strong>
                </div>

                <div class="info-box">
                    <span>Persona encargada</span>
                    <strong><?= eSeguimientoCompleto($faseProceso['responsable']) ?></strong>
                </div>

                <div class="info-box">
                    <span>Inicio de entrega</span>
                    <strong><?= eSeguimientoCompleto(fechaSeguimientoCompleto($fechaInicio, true)) ?></strong>
                </div>

                <div class="info-box">
                    <span>Límite de entrega</span>
                    <strong><?= eSeguimientoCompleto(fechaSeguimientoCompleto($fechaLimite, true)) ?></strong>
                </div>

                <div class="info-box">
                    <span>Límite de revisión</span>
                    <strong><?= eSeguimientoCompleto(fechaSeguimientoCompleto($fechaRevision, true)) ?></strong>
                </div>

                <div class="info-box">
                    <span>Devolución de observaciones</span>
                    <strong><?= eSeguimientoCompleto(fechaSeguimientoCompleto($fechaDevolucion, true)) ?></strong>
                </div>
            </div>

            <p><?= eSeguimientoCompleto($mensajeEstado) ?></p>

            <?php if (!empty($faseProceso['observacion_habilitacion'])): ?>
                <p class="student-note">
                    <strong>Indicaciones de Administración:</strong><br>
                    <?= nl2br(eSeguimientoCompleto($faseProceso['observacion_habilitacion'])) ?>
                </p>
            <?php endif; ?>

            <?php if ($trabajo): ?>
                <div class="student-summary-card">
                    <h3>Documento enviado</h3>
                    <p><strong>Tema:</strong> <?= eSeguimientoCompleto($trabajo['titulo_trabajo']) ?></p>
                    <p><strong>Fecha de envío:</strong> <?= eSeguimientoCompleto(fechaSeguimientoCompleto($trabajo['fecha_presentacion'], true)) ?></p>

                    <?php if (!empty($trabajo['ruta_archivo'])): ?>
                        <p>
                            <a href="<?= eSeguimientoCompleto($baseUrl . '/' . ltrim($trabajo['ruta_archivo'], '/')) ?>" target="_blank" rel="noopener" class="btn-ingresar-programa">
                                Ver documento enviado
                            </a>
                        </p>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

            <div class="student-summary-card">
                <h3>Resultado vigente de la revisión</h3>

                <?php $revisionVigente = $faseProceso['revisiones'][0] ?? null; ?>

                <?php if ($revisionVigente): ?>
                    <div class="student-note">
                        <p>
                            <strong>Revisado por:</strong>
                            <?= eSeguimientoCompleto($revisionVigente['revisor'] ?: $faseProceso['responsable']) ?>
                        </p>

                        <p>
                            <strong>Fecha de revisión:</strong>
                            <?= eSeguimientoCompleto(fechaSeguimientoCompleto($revisionVigente['fecha_revision'], true)) ?>
                        </p>

                        <p>
                            <strong>Resultado:</strong>
                            <?= eSeguimientoCompleto(textoEstadoSeguimientoCompleto($estadoVista)) ?>
                        </p>

                        <?php if (trim((string) ($revisionVigente['comentario'] ?? '')) !== ''): ?>
                            <p>
                                <strong><?= $estadoVista === 'OBSERVADO' ? 'Observaciones:' : 'Comentario:' ?></strong><br>
                                <?= nl2br(eSeguimientoCompleto($revisionVigente['comentario'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p>Aún no existe un resultado de revisión registrado para esta fase.</p>
                <?php endif; ?>

                <small>
                    Se muestra únicamente el resultado vigente de la revisión.
                </small>
            </div>

            <?php if ((int) $faseProceso['numero_fase'] === 3 && $estadoVista === 'APROBADO_FINAL'): ?>
                <div class="student-summary-card">
                    <h3>Resultado final del proceso</h3>
                    <p>
                        La valoración formal se emitirá únicamente en el acta de evaluación.
                        Administración registrará o comunicará el código de empaste cuando corresponda.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($estadoVista === 'OBSERVADO' && $correccionAutorizada): ?>
                <div class="student-summary-card">
                    <h3>Nueva entrega autorizada</h3>
                    <p>
                        Administración autorizó una versión corregida de tu documento.
                        Debes enviarla hasta el:
                        <strong><?= eSeguimientoCompleto(fechaSeguimientoCompleto($fechaLimiteCorreccion, true)) ?></strong>.
                    </p>
                </div>

            <?php elseif ($estadoVista === 'OBSERVADO' && $correccionVencida): ?>
                <div class="student-summary-card">
                    <h3>Plazo de corrección vencido</h3>
                    <p>
                        El plazo autorizado para enviar la corrección finalizó.
                        Comunícate con Administración Académica.
                    </p>
                </div>

            <?php elseif ($estadoVista === 'OBSERVADO' && $correccionCerrada): ?>
                <div class="student-summary-card">
                    <h3>Corrección cerrada</h3>
                    <p>
                        <?= eSeguimientoCompleto(
                            $controlReentrega['observacion_cierre']
                            ?: 'La corrección fue cerrada por Administración.'
                        ) ?>
                    </p>
                </div>

            <?php elseif ($estadoVista === 'OBSERVADO'): ?>
                <div class="student-summary-card">
                    <h3>Corrección pendiente de autorización</h3>
                    <p>
                        Administración revisará las observaciones y definirá si corresponde
                        habilitar una nueva entrega.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($puedeRegistrar): ?>
                <div class="student-summary-card">
                    <h3>
                        <?php if ($correccionAutorizada): ?>
                            Subir versión corregida
                        <?php elseif ((int) $faseProceso['numero_fase'] === 1): ?>
                            <?= $trabajo ? 'Actualizar propuesta inicial' : 'Registrar propuesta inicial' ?>
                        <?php elseif ((int) $faseProceso['numero_fase'] === 2): ?>
                            <?= $trabajo ? 'Actualizar monografía' : 'Cargar monografía' ?>
                        <?php else: ?>
                            <?= $trabajo ? 'Actualizar documento final' : 'Cargar documento final' ?>
                        <?php endif; ?>
                    </h3>

                    <form method="POST" action="<?= eSeguimientoCompleto($baseUrl) ?>/api/estudiante/trabajo_fase_guardar.php" enctype="multipart/form-data">
                        <input type="hidden" name="programa_id" value="<?= (int) $programaId ?>">
                        <input type="hidden" name="id_fase" value="<?= (int) $faseProceso['id_fase'] ?>">

                        <?php if ((int) $faseProceso['numero_fase'] === 1): ?>
                            <div class="form-group">
                                <label for="titulo_trabajo_<?= (int) $faseProceso['id_fase'] ?>">Tema o título del trabajo</label>
                                <input id="titulo_trabajo_<?= (int) $faseProceso['id_fase'] ?>" name="titulo_trabajo" type="text" maxlength="250" required value="<?= eSeguimientoCompleto($faseProceso['titulo_sugerido']) ?>" placeholder="Ej.: Propuesta de mejora para...">
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="titulo_trabajo" value="<?= eSeguimientoCompleto($faseProceso['titulo_sugerido']) ?>">
                            <p><strong>Tema aprobado:</strong> <?= eSeguimientoCompleto($faseProceso['titulo_sugerido'] ?: 'Pendiente de registro en Fase 1') ?></p>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="archivo_<?= (int) $faseProceso['id_fase'] ?>">
                                <?= (int) $faseProceso['numero_fase'] === 2 ? 'Monografía' : 'Documento de la fase' ?>
                            </label>
                            <input id="archivo_<?= (int) $faseProceso['id_fase'] ?>" name="archivo" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" <?= $documentoObligatorio ? 'required' : '' ?>>
                        </div>

                        <div class="form-group">
                            <?php if ($correccionAutorizada): ?>
                                <button type="submit" name="accion" value="ENVIAR_REVISION" class="btn-ingresar-programa">
                                    Enviar versión corregida
                                </button>
                           <?php elseif ((int) $faseProceso['numero_fase'] === 1): ?>
    <button type="submit" name="accion" value="GUARDAR_BORRADOR" class="btn-ingresar-programa">
        Guardar como borrador
    </button>

    <button type="submit" name="accion" value="ENVIAR_REVISION" class="btn-ingresar-programa">
        Enviar propuesta a revisión
    </button>

<?php elseif ((int) $faseProceso['numero_fase'] === 2): ?>
    <button type="submit" name="accion" value="ENVIAR_REVISION" class="btn-ingresar-programa">
        Enviar monografía a revisión
    </button>

<?php else: ?>
    <button type="submit" name="accion" value="ENVIAR_REVISION" class="btn-ingresar-programa">
        Enviar documento final a revisión
    </button>
<?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php elseif (!$faseActiva && $estadoVista === 'PENDIENTE'): ?>
                <div class="student-summary-card">
                    <p>Esta fase aún no fue habilitada por Administración.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</section>
