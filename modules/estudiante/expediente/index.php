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
    http_response_code(403);
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
    die('Error: la conexión local no está disponible.');
}

$db = $conexion;
$usuarioId = (int) $_SESSION['id'];
$programaId = (int) ($_GET['programa_id'] ?? $_POST['programa_id'] ?? 0);
$baseUrl = '/SIGET_ESAM';

function eExpediente($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function fechaExpediente(?string $fecha, bool $hora = false): string
{
    if (!$fecha) {
        return 'Sin registro';
    }

    $marca = strtotime($fecha);

    if ($marca === false) {
        return (string) $fecha;
    }

    return $hora ? date('d/m/Y H:i', $marca) : date('d/m/Y', $marca);
}

function etiquetaExpediente(?string $estado): string
{
    $estado = strtoupper(trim((string) $estado));

    $etiquetas = [
        'PENDIENTE' => 'Pendiente',
        'PENDIENTE_DOCUMENTOS' => 'Pendiente de documentos',
        'DOCUMENTOS_EN_REVISION' => 'Documentos en revisión',
        'APROBADO' => 'Aprobado',
        'OBSERVADO' => 'Observado',
        'ASIGNADO' => 'Asignado',
        'PENDIENTE_JURADO' => 'Pendiente de jurado',
        'PENDIENTE_INSCRIPCION' => 'Pendiente de aprobación',
        'HABILITADO_FASE_1' => 'Fase 1 habilitada',
    ];

    return $etiquetas[$estado] ?? str_replace('_', ' ', ucfirst(strtolower($estado)));
}

function claseEstadoExpediente(?string $estado): string
{
    $estado = strtoupper(trim((string) $estado));

    return match ($estado) {
        'APROBADO', 'ASIGNADO', 'HABILITADO_FASE_1' => 'text-success',
        'OBSERVADO' => 'text-warning',
        default => 'text-muted',
    };
}

function volverExpediente(int $programaId, string $tipo, string $mensaje): void
{
    $_SESSION['flash_expediente'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje,
    ];

    header('Location: /SIGET_ESAM/index.php?page=estudiante/expediente/index&programa_id=' . $programaId);
    exit;
}

function mensajeArchivoExpediente(int $codigo): string
{
    return match ($codigo) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido de 20 MB.',
        UPLOAD_ERR_PARTIAL => 'La carga del archivo quedó incompleta. Inténtalo nuevamente.',
        UPLOAD_ERR_NO_FILE => 'Selecciona un archivo antes de continuar.',
        UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal configurada para recibir archivos.',
        UPLOAD_ERR_CANT_WRITE => 'No fue posible guardar el archivo en el servidor.',
        UPLOAD_ERR_EXTENSION => 'Una extensión del servidor detuvo la carga del archivo.',
        default => 'No fue posible cargar el archivo.',
    };
}

/* --------------------------------------------------------------------------
   Programas de la cuenta
-------------------------------------------------------------------------- */
$stmtProgramas = $db->prepare("
    SELECT
        i.id AS id_inscripcion,
        i.id_programa,
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
    ORDER BY i.id DESC
");
$stmtProgramas->execute([':id_estudiante' => $usuarioId]);
$programas = $stmtProgramas->fetchAll(PDO::FETCH_ASSOC);

if ($programaId <= 0 && count($programas) === 1) {
    $programaId = (int) $programas[0]['id_programa'];
}

if (empty($programas)) {
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

if ($programaId <= 0) {
    ?>
    <section class="dashboard-estudiante">
        <div class="dashboard-title">
            <h1>Expediente de titulación</h1>
            <p>Selecciona el programa cuyo expediente deseas completar.</p>
        </div>

        <div class="programas-grid">
            <?php foreach ($programas as $programa): ?>
                <div class="programa-card">
                    <span class="programa-tag"><?= eExpediente($programa['tipo']) ?></span>
                    <h2><?= eExpediente($programa['nombre_programa']) ?></h2>
                    <p><strong>Gestión:</strong> <?= eExpediente($programa['gestion_externa'] ?: 'No definida') ?></p>
                    <a class="btn-ingresar-programa" href="index.php?page=estudiante/expediente/index&programa_id=<?= (int) $programa['id_programa'] ?>">
                        Completar expediente
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return;
}

/* --------------------------------------------------------------------------
   Inscripción y postulación del programa seleccionado
-------------------------------------------------------------------------- */
$stmtInscripcion = $db->prepare("
    SELECT
        i.id AS id_inscripcion,
        i.id_programa,
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
            <a class="btn-ingresar-programa" href="index.php?page=estudiante/expediente/index">Volver</a>
        </div>
    </section>
    <?php
    return;
}

$stmtPostulacion = $db->prepare("
    SELECT *
    FROM titulacion_postulaciones
    WHERE id_inscripcion = :id_inscripcion
    LIMIT 1
");
$stmtPostulacion->execute([':id_inscripcion' => $inscripcion['id_inscripcion']]);
$postulacion = $stmtPostulacion->fetch(PDO::FETCH_ASSOC);

if (!$postulacion) {
    $stmtCrearPostulacion = $db->prepare("
        INSERT INTO titulacion_postulaciones
        (id_inscripcion, estado_documental, estado_inscripcion, estado_jurado, estado_proceso, creado_por)
        VALUES (:id_inscripcion, 'PENDIENTE', 'PENDIENTE', 'PENDIENTE', 'PENDIENTE_DOCUMENTOS', :creado_por)
    ");
    $stmtCrearPostulacion->execute([
        ':id_inscripcion' => $inscripcion['id_inscripcion'],
        ':creado_por' => $usuarioId,
    ]);

    $stmtPostulacion->execute([':id_inscripcion' => $inscripcion['id_inscripcion']]);
    $postulacion = $stmtPostulacion->fetch(PDO::FETCH_ASSOC);
}

/* --------------------------------------------------------------------------
   Guarda un documento del expediente
-------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'SUBIR_DOCUMENTO') {
    $idRequisito = (int) ($_POST['id_requisito'] ?? 0);

    if ($idRequisito <= 0) {
        volverExpediente($programaId, 'error', 'No fue posible identificar el requisito seleccionado.');
    }

    if (strtoupper((string) $postulacion['estado_documental']) === 'APROBADO') {
        volverExpediente($programaId, 'error', 'Tu expediente documental ya fue aprobado. Comunícate con administración para solicitar una actualización.');
    }

    $stmtRequisito = $db->prepare("
        SELECT id, nombre_requisito
        FROM titulacion_requisitos
        WHERE id = :id_requisito
          AND id_programa = :id_programa
          AND estado = 'ACTIVO'
          AND (gestion = :gestion OR gestion IS NULL)
        LIMIT 1
    ");
    $stmtRequisito->execute([
        ':id_requisito' => $idRequisito,
        ':id_programa' => $programaId,
        ':gestion' => $inscripcion['gestion_externa'],
    ]);
    $requisito = $stmtRequisito->fetch(PDO::FETCH_ASSOC);

    if (!$requisito) {
        volverExpediente($programaId, 'error', 'El requisito seleccionado no pertenece a tu programa o no está activo.');
    }

    $archivo = $_FILES['archivo'] ?? null;
    $codigoError = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($codigoError !== UPLOAD_ERR_OK) {
        volverExpediente($programaId, 'error', mensajeArchivoExpediente($codigoError));
    }

    $tamano = (int) ($archivo['size'] ?? 0);
    if ($tamano <= 0 || $tamano > 20 * 1024 * 1024) {
        volverExpediente($programaId, 'error', 'El archivo debe tener un tamaño mayor a 0 y menor o igual a 20 MB.');
    }

    $nombreOriginal = (string) ($archivo['name'] ?? '');
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    $extensionesPermitidas = ['pdf', 'doc', 'docx'];

    if (!in_array($extension, $extensionesPermitidas, true)) {
        volverExpediente($programaId, 'error', 'Solo se permiten archivos PDF, DOC o DOCX.');
    }

    $carpetaDestino = dirname(__DIR__, 3) . '/uploads/expedientes';
    if (!is_dir($carpetaDestino) && !mkdir($carpetaDestino, 0755, true) && !is_dir($carpetaDestino)) {
        volverExpediente($programaId, 'error', 'No fue posible crear la carpeta para almacenar los documentos.');
    }

    $nombreSeguro = sprintf(
        'expediente_%d_requisito_%d_%s_%s.%s',
        $usuarioId,
        $idRequisito,
        date('Ymd_His'),
        bin2hex(random_bytes(5)),
        $extension
    );

    $rutaFisica = $carpetaDestino . '/' . $nombreSeguro;
    if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
        volverExpediente($programaId, 'error', 'No fue posible guardar el archivo cargado.');
    }

    try {
        $db->beginTransaction();

        $stmtVersion = $db->prepare("
            SELECT COALESCE(MAX(numero_version), 0) + 1
            FROM titulacion_documentos
            WHERE id_postulacion = :id_postulacion
              AND id_requisito = :id_requisito
        ");
        $stmtVersion->execute([
            ':id_postulacion' => $postulacion['id'],
            ':id_requisito' => $idRequisito,
        ]);
        $numeroVersion = (int) $stmtVersion->fetchColumn();

        $stmtDesactivar = $db->prepare("
            UPDATE titulacion_documentos
            SET es_vigente = 0
            WHERE id_postulacion = :id_postulacion
              AND id_requisito = :id_requisito
              AND es_vigente = 1
        ");
        $stmtDesactivar->execute([
            ':id_postulacion' => $postulacion['id'],
            ':id_requisito' => $idRequisito,
        ]);

        $stmtInsertar = $db->prepare("
            INSERT INTO titulacion_documentos
            (
                id_postulacion, id_requisito, numero_version,
                nombre_original, archivo_servidor, ruta_archivo,
                mime_type, tamano_bytes, estado_validacion,
                es_vigente, cargado_por, cargado_el
            )
            VALUES
            (
                :id_postulacion, :id_requisito, :numero_version,
                :nombre_original, :archivo_servidor, :ruta_archivo,
                :mime_type, :tamano_bytes, 'PENDIENTE',
                1, :cargado_por, NOW()
            )
        ");
        $stmtInsertar->execute([
            ':id_postulacion' => $postulacion['id'],
            ':id_requisito' => $idRequisito,
            ':numero_version' => $numeroVersion,
            ':nombre_original' => $nombreOriginal,
            ':archivo_servidor' => $nombreSeguro,
            ':ruta_archivo' => 'uploads/expedientes/' . $nombreSeguro,
            ':mime_type' => (string) ($archivo['type'] ?? null),
            ':tamano_bytes' => $tamano,
            ':cargado_por' => $usuarioId,
        ]);

        $stmtPendientes = $db->prepare("
            SELECT COUNT(*)
            FROM titulacion_requisitos tr
            WHERE tr.id_programa = :id_programa
              AND tr.estado = 'ACTIVO'
              AND tr.obligatorio = 1
              AND (tr.gestion = :gestion OR tr.gestion IS NULL)
              AND NOT EXISTS (
                  SELECT 1
                  FROM titulacion_documentos td
                  WHERE td.id_postulacion = :id_postulacion
                    AND td.id_requisito = tr.id
                    AND td.es_vigente = 1
              )
        ");
        $stmtPendientes->execute([
            ':id_programa' => $programaId,
            ':gestion' => $inscripcion['gestion_externa'],
            ':id_postulacion' => $postulacion['id'],
        ]);
        $faltantes = (int) $stmtPendientes->fetchColumn();

        $nuevoEstadoProceso = $faltantes === 0
            ? 'DOCUMENTOS_EN_REVISION'
            : 'PENDIENTE_DOCUMENTOS';

        $stmtActualizarPostulacion = $db->prepare("
            UPDATE titulacion_postulaciones
            SET
                estado_documental = 'PENDIENTE',
                estado_inscripcion = 'PENDIENTE',
                estado_jurado = 'PENDIENTE',
                estado_proceso = :estado_proceso,
                observacion_documental = NULL,
                observacion_inscripcion = NULL,
                validado_por = NULL,
                validado_el = NULL,
                aprobado_por = NULL,
                aprobado_el = NULL
            WHERE id = :id_postulacion
        ");
        $stmtActualizarPostulacion->execute([
            ':estado_proceso' => $nuevoEstadoProceso,
            ':id_postulacion' => $postulacion['id'],
        ]);

        $db->commit();
        volverExpediente($programaId, 'success', 'Documento cargado correctamente. El expediente será revisado por administración.');

    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if (is_file($rutaFisica)) {
            @unlink($rutaFisica);
        }

        volverExpediente($programaId, 'error', 'No fue posible registrar el documento.');
    }
}

/* --------------------------------------------------------------------------
   Requisitos y documentos vigentes
-------------------------------------------------------------------------- */
$stmtRequisitos = $db->prepare("
    SELECT
        tr.id AS id_requisito,
        tr.nombre_requisito,
        tr.descripcion,
        tr.obligatorio,
        tr.orden,

        td.id AS id_documento,
        td.numero_version,
        td.nombre_original,
        td.ruta_archivo,
        td.estado_validacion,
        td.observacion,
        td.cargado_el,
        td.validado_el

    FROM titulacion_requisitos tr
    LEFT JOIN titulacion_documentos td ON td.id = (
        SELECT td2.id
        FROM titulacion_documentos td2
        WHERE td2.id_postulacion = :id_postulacion
          AND td2.id_requisito = tr.id
          AND td2.es_vigente = 1
        ORDER BY td2.numero_version DESC, td2.id DESC
        LIMIT 1
    )

    WHERE tr.id_programa = :id_programa
      AND tr.estado = 'ACTIVO'
      AND (tr.gestion = :gestion OR tr.gestion IS NULL)

    ORDER BY tr.orden ASC, tr.id ASC
");
$stmtRequisitos->execute([
    ':id_postulacion' => $postulacion['id'],
    ':id_programa' => $programaId,
    ':gestion' => $inscripcion['gestion_externa'],
]);
$requisitos = $stmtRequisitos->fetchAll(PDO::FETCH_ASSOC);

$totalObligatorios = 0;
$obligatoriosCargados = 0;
$documentosAprobados = 0;
$documentosObservados = 0;

foreach ($requisitos as $requisito) {
    if ((int) $requisito['obligatorio'] === 1) {
        $totalObligatorios++;
        if (!empty($requisito['id_documento'])) {
            $obligatoriosCargados++;
        }
    }

    $estadoDocumento = strtoupper((string) ($requisito['estado_validacion'] ?? ''));
    if ($estadoDocumento === 'APROBADO') {
        $documentosAprobados++;
    }
    if ($estadoDocumento === 'OBSERVADO') {
        $documentosObservados++;
    }
}

$flash = $_SESSION['flash_expediente'] ?? null;
unset($_SESSION['flash_expediente']);
?>

<section class="dashboard-estudiante">

    <div class="dashboard-title">
        <h1>Expediente de titulación</h1>
        <p>
            <?= eExpediente($inscripcion['nombre_programa']) ?>
            · Gestión <?= eExpediente($inscripcion['gestion_externa'] ?: 'No definida') ?>
        </p>
    </div>

    <?php if ($flash): ?>
        <div class="student-summary-card">
            <strong><?= $flash['tipo'] === 'success' ? 'Proceso realizado correctamente' : 'No fue posible completar la operación' ?></strong>
            <p><?= eExpediente($flash['mensaje']) ?></p>
        </div>
    <?php endif; ?>

    <div class="student-summary-card">
        <h2>Estado de tu proceso</h2>

        <div class="student-info-grid">
            <div class="info-box">
                <span>Documentación</span>
                <strong class="<?= eExpediente(claseEstadoExpediente($postulacion['estado_documental'])) ?>">
                    <?= eExpediente(etiquetaExpediente($postulacion['estado_documental'])) ?>
                </strong>
            </div>

            <div class="info-box">
                <span>Inscripción a titulación</span>
                <strong class="<?= eExpediente(claseEstadoExpediente($postulacion['estado_inscripcion'])) ?>">
                    <?= eExpediente(etiquetaExpediente($postulacion['estado_inscripcion'])) ?>
                </strong>
            </div>

            <div class="info-box">
                <span>Jurado</span>
                <strong class="<?= eExpediente(claseEstadoExpediente($postulacion['estado_jurado'])) ?>">
                    <?= eExpediente(etiquetaExpediente($postulacion['estado_jurado'])) ?>
                </strong>
            </div>

            <div class="info-box">
                <span>Fase actual</span>
                <strong><?= eExpediente(etiquetaExpediente($postulacion['estado_proceso'])) ?></strong>
            </div>
        </div>

        <?php if (!empty($postulacion['observacion_documental'])): ?>
            <p class="student-note">
                <strong>Observación documental:</strong><br>
                <?= nl2br(eExpediente($postulacion['observacion_documental'])) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($postulacion['observacion_inscripcion'])): ?>
            <p class="student-note">
                <strong>Observación de inscripción:</strong><br>
                <?= nl2br(eExpediente($postulacion['observacion_inscripcion'])) ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="student-summary-card fase-card">
        <h2>Documentos requeridos</h2>
        <p>
            Has cargado <?= (int) $obligatoriosCargados ?> de <?= (int) $totalObligatorios ?> documentos obligatorios.
            Administración validará cada archivo y te comunicará cualquier observación en esta pantalla.
        </p>

        <?php if (empty($requisitos)): ?>
            <p>No existen requisitos configurados para este programa y gestión.</p>
        <?php else: ?>
            <div class="programas-grid">
                <?php foreach ($requisitos as $requisito): ?>
                    <?php
                    $tieneDocumento = !empty($requisito['id_documento']);
                    $estadoDocumento = $tieneDocumento
                        ? strtoupper((string) $requisito['estado_validacion'])
                        : 'PENDIENTE';
                    ?>
                    <div class="programa-card">
                        <span class="programa-tag">
                            <?= (int) $requisito['obligatorio'] === 1 ? 'Obligatorio' : 'Opcional' ?>
                        </span>

                        <h2><?= eExpediente($requisito['nombre_requisito']) ?></h2>

                        <?php if (!empty($requisito['descripcion'])): ?>
                            <p><?= eExpediente($requisito['descripcion']) ?></p>
                        <?php endif; ?>

                        <p>
                            <strong>Estado:</strong>
                            <span class="<?= eExpediente(claseEstadoExpediente($estadoDocumento)) ?>">
                                <?= eExpediente(etiquetaExpediente($estadoDocumento)) ?>
                            </span>
                        </p>

                        <?php if ($tieneDocumento): ?>
                            <p>
                                <strong>Archivo:</strong>
                                <a href="<?= eExpediente($baseUrl . '/' . ltrim((string) $requisito['ruta_archivo'], '/')) ?>" target="_blank" rel="noopener">
                                    <?= eExpediente($requisito['nombre_original']) ?>
                                </a>
                            </p>

                            <p><strong>Versión:</strong> <?= (int) $requisito['numero_version'] ?></p>
                            <p><strong>Cargado:</strong> <?= eExpediente(fechaExpediente($requisito['cargado_el'], true)) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($requisito['observacion'])): ?>
                            <p class="student-note">
                                <strong>Observación:</strong><br>
                                <?= nl2br(eExpediente($requisito['observacion'])) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (strtoupper((string) $postulacion['estado_documental']) !== 'APROBADO'): ?>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="accion" value="SUBIR_DOCUMENTO">
                                <input type="hidden" name="programa_id" value="<?= (int) $programaId ?>">
                                <input type="hidden" name="id_requisito" value="<?= (int) $requisito['id_requisito'] ?>">

                                <div class="form-group">
                                    <label>Seleccionar archivo</label>
                                    <input
                                        type="file"
                                        name="archivo"
                                        accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                        required
                                    >
                                </div>

                                <button type="submit" class="btn-ingresar-programa">
                                    <?= $tieneDocumento ? 'Reemplazar documento' : 'Cargar documento' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="student-summary-card fase-card">
        <h2>Próximo paso</h2>

        <?php if ($documentosObservados > 0): ?>
            <p>Tienes <?= (int) $documentosObservados ?> documento(s) observado(s). Corrige y reemplaza únicamente los archivos observados.</p>
        <?php elseif ($totalObligatorios > $obligatoriosCargados): ?>
            <p>Carga los documentos obligatorios pendientes. Cuando estén completos, el expediente pasará a revisión administrativa.</p>
        <?php elseif (strtoupper((string) $postulacion['estado_documental']) !== 'APROBADO'): ?>
            <p>Todos los documentos obligatorios fueron cargados. Administración realizará la validación documental.</p>
        <?php elseif (strtoupper((string) $postulacion['estado_inscripcion']) !== 'APROBADO'): ?>
            <p>Tu documentación fue aprobada. Administración debe aprobar tu inscripción a titulación.</p>
        <?php elseif (strtoupper((string) $postulacion['estado_jurado']) !== 'ASIGNADO'): ?>
            <p>Tu inscripción fue aprobada. Administración debe asignar a los jurados responsables de tu proceso.</p>
        <?php else: ?>
            <p>Tu expediente está completo y el jurado fue asignado. Administración habilitará la Fase 1 conforme al cronograma establecido.</p>
        <?php endif; ?>
    </div>

</section>
