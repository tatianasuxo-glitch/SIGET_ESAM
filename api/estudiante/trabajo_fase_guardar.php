<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

function volverSeguimiento(int $programaId, string $tipo, string $mensaje): void
{
    $_SESSION['flash_seguimiento'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje,
    ];

    header(
        'Location: /SIGET_ESAM/index.php?page=estudiante/seguimiento&programa_id=' .
        $programaId
    );
    exit;
}

function estadoNormalizadoGuardar(?string $estado): string
{
    $estado = strtoupper(trim((string) $estado));

    $estado = str_replace(
        ['Á', 'É', 'Í', 'Ó', 'Ú', ' '],
        ['A', 'E', 'I', 'O', 'U', '_'],
        $estado
    );

    $equivalencias = [
        'BORRADOR' => 'BORRADOR',
        'EN_REVISION' => 'EN_REVISION',
        'CORREGIDO' => 'CORREGIDO',
        'CORREGIDA' => 'CORREGIDO',
        'OBSERVADO' => 'OBSERVADO',
        'OBSERVADA' => 'OBSERVADO',
        'RECHAZADO' => 'RECHAZADO',
        'RECHAZADA' => 'RECHAZADO',
        'REVISADO' => 'REVISADO',
        'REVISADA' => 'REVISADO',
        'APROBADO' => 'REVISADO',
        'APROBADA' => 'REVISADO',
        'REPROBADO' => 'RECHAZADO',
        'REPROBADA' => 'RECHAZADO',
    ];

    return $equivalencias[$estado] ?? 'BORRADOR';
}

function archivoErrorMensaje(int $codigo): string
{
    return match ($codigo) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE
            => 'El archivo supera el tamaño máximo permitido de 20 MB.',

        UPLOAD_ERR_PARTIAL
            => 'La carga del archivo quedó incompleta. Inténtalo nuevamente.',

        UPLOAD_ERR_NO_TMP_DIR
            => 'El servidor no tiene una carpeta temporal configurada para recibir archivos.',

        UPLOAD_ERR_CANT_WRITE
            => 'No fue posible guardar el archivo en el servidor.',

        UPLOAD_ERR_EXTENSION
            => 'Una extensión del servidor detuvo la carga del archivo.',

        default
            => 'No fue posible cargar el archivo.',
    };
}

if (!isset($_SESSION['id'])) {
    header('Location: /SIGET_ESAM/login.php');
    exit;
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'estudiante') {
    http_response_code(403);
    exit('Acceso restringido.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    exit('La conexión local no está disponible.');
}

$db = $conexion;

$usuarioId = (int) $_SESSION['id'];
$programaId = (int) ($_POST['programa_id'] ?? 0);
$idFaseSolicitada = (int) ($_POST['id_fase'] ?? 0);

$tituloTrabajo = trim((string) ($_POST['titulo_trabajo'] ?? ''));
$accion = strtoupper(trim((string) ($_POST['accion'] ?? '')));

if ($programaId <= 0 || $idFaseSolicitada <= 0) {
    volverSeguimiento(
        $programaId,
        'error',
        'No fue posible identificar el programa o la fase seleccionada.'
    );
}

if (!in_array($accion, ['GUARDAR_BORRADOR', 'ENVIAR_REVISION'], true)) {
    volverSeguimiento(
        $programaId,
        'error',
        'La acción solicitada no es válida.'
    );
}

if (mb_strlen($tituloTrabajo) < 10 || mb_strlen($tituloTrabajo) > 250) {
    volverSeguimiento(
        $programaId,
        'error',
        'El título debe tener entre 10 y 250 caracteres.'
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Validar inscripción del estudiante
    |--------------------------------------------------------------------------
    */
    $stmtInscripcion = $db->prepare("
        SELECT i.id
        FROM inscripciones i
        WHERE i.id_estudiante = :id_estudiante
          AND i.id_programa = :id_programa
        ORDER BY i.id DESC
        LIMIT 1
    ");

    $stmtInscripcion->execute([
        ':id_estudiante' => $usuarioId,
        ':id_programa' => $programaId,
    ]);

    if (!$stmtInscripcion->fetch(PDO::FETCH_ASSOC)) {
        volverSeguimiento(
            $programaId,
            'error',
            'El programa solicitado no está vinculado a tu cuenta.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Obtener fase individual habilitada
    |--------------------------------------------------------------------------
    */
    $stmtFase = $db->prepare("
        SELECT
            fec.id AS id_habilitacion,
            fec.estado AS estado_habilitacion,
            fec.fecha_inicio_entrega AS fecha_inicio_individual,
            fec.fecha_limite_entrega AS fecha_limite_individual,

            pfc.id AS id_configuracion,
            pfc.fecha_inicio_entrega,
            pfc.fecha_limite_entrega,
            pfc.estado AS estado_configuracion,

            f.id AS id_fase,
            f.numero_fase

        FROM fase_estudiante_config fec
        INNER JOIN programa_fase_config pfc
            ON pfc.id = fec.id_configuracion
        INNER JOIN fases f
            ON f.id = pfc.id_fase

        WHERE fec.id_estudiante = :id_estudiante
          AND pfc.id_programa = :id_programa
          AND f.id = :id_fase
          AND fec.estado = 'ACTIVO'
          AND pfc.estado = 'ACTIVO'

        LIMIT 1
    ");

    $stmtFase->execute([
        ':id_estudiante' => $usuarioId,
        ':id_programa' => $programaId,
        ':id_fase' => $idFaseSolicitada,
    ]);

    $fase = $stmtFase->fetch(PDO::FETCH_ASSOC);

    if (!$fase) {
        volverSeguimiento(
            $programaId,
            'error',
            'La fase no está habilitada para tu cuenta o ya no se encuentra activa.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buscar trabajo actual de la fase
    |--------------------------------------------------------------------------
    */
    $stmtTrabajo = $db->prepare("
        SELECT *
        FROM trabajos
        WHERE id_estudiante = :id_estudiante
          AND id_configuracion = :id_configuracion
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmtTrabajo->execute([
        ':id_estudiante' => $usuarioId,
        ':id_configuracion' => (int) $fase['id_configuracion'],
    ]);

    $trabajoAnterior = $stmtTrabajo->fetch(PDO::FETCH_ASSOC) ?: null;

    $esReentregaAutorizada = false;
    $controlReentrega = null;

    /*
    |--------------------------------------------------------------------------
    | Si fue rechazado, verificar autorización de Administración
    |--------------------------------------------------------------------------
    */
    if ($trabajoAnterior) {
        $estadoAnterior = estadoNormalizadoGuardar(
            $trabajoAnterior['estado_aprobacion'] ?? ''
        );

        if (in_array($estadoAnterior, ['RECHAZADO', 'OBSERVADO'], true)) {
            $stmtControl = $db->prepare("
                SELECT
                    id,
                    ciclo,
                    estado,
                    fecha_limite_correccion,
                    fecha_reentrega
                FROM control_reentregas
                WHERE id_trabajo = :id_trabajo
                ORDER BY ciclo DESC, id DESC
                LIMIT 1
            ");

            $stmtControl->execute([
                ':id_trabajo' => (int) $trabajoAnterior['id'],
            ]);

            $controlReentrega = $stmtControl->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$controlReentrega) {
                volverSeguimiento(
                    $programaId,
                    'error',
                    'Tu documento tiene observaciones. Espera la autorización de Administración para enviar una corrección.'
                );
            }

            $estadoControl = strtoupper(
                trim((string) ($controlReentrega['estado'] ?? ''))
            );

            if ($estadoControl !== 'AUTORIZADA') {
                volverSeguimiento(
                    $programaId,
                    'error',
                    'La corrección aún no fue autorizada por Administración.'
                );
            }

            $fechaLimiteCorreccion = $controlReentrega['fecha_limite_correccion'] ?? null;

            if (
                !$fechaLimiteCorreccion ||
                strtotime($fechaLimiteCorreccion) === false ||
                time() > strtotime($fechaLimiteCorreccion)
            ) {
                volverSeguimiento(
                    $programaId,
                    'error',
                    'El plazo autorizado para enviar la corrección ya finalizó. Comunícate con Administración.'
                );
            }

            if ($accion !== 'ENVIAR_REVISION') {
                volverSeguimiento(
                    $programaId,
                    'error',
                    'La versión corregida debe enviarse directamente a revisión.'
                );
            }

            $esReentregaAutorizada = true;
        }

        if (in_array(
            $estadoAnterior,
            ['EN_REVISION', 'CORREGIDO', 'REVISADO'],
            true
        )) {
            volverSeguimiento(
                $programaId,
                'error',
                'El documento no puede modificarse mientras está en revisión o después de haber sido revisado.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validar fechas generales de entrega
    |--------------------------------------------------------------------------
    */
    $fechaInicio = $fase['fecha_inicio_individual']
        ?: $fase['fecha_inicio_entrega'];

    $fechaLimite = $fase['fecha_limite_individual']
        ?: $fase['fecha_limite_entrega'];

    if ($accion === 'ENVIAR_REVISION' && !$esReentregaAutorizada) {
        $ahora = time();

        if (
            $fechaInicio &&
            strtotime($fechaInicio) !== false &&
            $ahora < strtotime($fechaInicio)
        ) {
            volverSeguimiento(
                $programaId,
                'error',
                'La entrega todavía no está habilitada según la fecha de inicio establecida.'
            );
        }

        if (
            $fechaLimite &&
            strtotime($fechaLimite) !== false &&
            $ahora > strtotime($fechaLimite)
        ) {
            volverSeguimiento(
                $programaId,
                'error',
                'La fecha límite de entrega ya finalizó. Consulta a Administración.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validar archivo
    |--------------------------------------------------------------------------
    */
    $rutaArchivo = $trabajoAnterior['ruta_archivo'] ?? null;

    $archivoNuevo = isset($_FILES['archivo']) &&
        (int) ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    $nombreOriginalArchivo = null;
    $mimeTypeArchivo = null;
    $tamanoArchivo = null;

    if ($archivoNuevo) {
        $errorArchivo = (int) ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errorArchivo !== UPLOAD_ERR_OK) {
            volverSeguimiento(
                $programaId,
                'error',
                archivoErrorMensaje($errorArchivo)
            );
        }

        $tamanoArchivo = (int) ($_FILES['archivo']['size'] ?? 0);

        if ($tamanoArchivo <= 0 || $tamanoArchivo > 20 * 1024 * 1024) {
            volverSeguimiento(
                $programaId,
                'error',
                'El archivo debe tener un tamaño mayor a 0 y menor o igual a 20 MB.'
            );
        }

        $nombreOriginalArchivo = (string) ($_FILES['archivo']['name'] ?? '');
        $mimeTypeArchivo = (string) ($_FILES['archivo']['type'] ?? '');

        $extension = strtolower(
            pathinfo($nombreOriginalArchivo, PATHINFO_EXTENSION)
        );

        $extensionesPermitidas = ['pdf', 'doc', 'docx'];

        if (!in_array($extension, $extensionesPermitidas, true)) {
            volverSeguimiento(
                $programaId,
                'error',
                'Solo se permiten archivos PDF, DOC o DOCX.'
            );
        }

        $carpetaDestino = dirname(__DIR__, 2) . '/uploads/trabajos';

        if (
            !is_dir($carpetaDestino) &&
            !mkdir($carpetaDestino, 0755, true) &&
            !is_dir($carpetaDestino)
        ) {
            volverSeguimiento(
                $programaId,
                'error',
                'No fue posible crear la carpeta para almacenar documentos.'
            );
        }

        $nombreSeguro = sprintf(
            'trabajo_%d_config_%d_%s_%s.%s',
            $usuarioId,
            (int) $fase['id_configuracion'],
            date('Ymd_His'),
            bin2hex(random_bytes(5)),
            $extension
        );

        $rutaFisica = $carpetaDestino . '/' . $nombreSeguro;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaFisica)) {
            volverSeguimiento(
                $programaId,
                'error',
                'No fue posible guardar el archivo cargado.'
            );
        }

        $rutaArchivo = 'uploads/trabajos/' . $nombreSeguro;
    }

    if ($accion === 'ENVIAR_REVISION' && empty($rutaArchivo)) {
        volverSeguimiento(
            $programaId,
            'error',
            'Para enviar a revisión debes adjuntar un documento.'
        );
    }

    if ($esReentregaAutorizada && !$archivoNuevo) {
        volverSeguimiento(
            $programaId,
            'error',
            'Debes adjuntar una nueva versión corregida del documento.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Estado nuevo
    |--------------------------------------------------------------------------
    */
    if ($accion === 'GUARDAR_BORRADOR') {
        $nuevoEstado = 'BORRADOR';
    } elseif ($esReentregaAutorizada) {
        $nuevoEstado = 'CORREGIDO';
    } else {
        $nuevoEstado = 'EN_REVISION';
    }

    $db->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Crear o actualizar trabajo principal
    |--------------------------------------------------------------------------
    */
    if ($trabajoAnterior) {
        $sqlActualizar = "
            UPDATE trabajos
            SET
                titulo_trabajo = :titulo_trabajo,
                ruta_archivo = :ruta_archivo,
                estado_aprobacion = :estado_aprobacion,
                actualizado_el = NOW()
        ";

        $parametros = [
            ':titulo_trabajo' => $tituloTrabajo,
            ':ruta_archivo' => $rutaArchivo,
            ':estado_aprobacion' => $nuevoEstado,
            ':id' => (int) $trabajoAnterior['id'],
        ];

        if ($accion === 'ENVIAR_REVISION') {
            $sqlActualizar .= ",
                fecha_presentacion = NOW(),
                comentario_revision = NULL,
                calificacion_final = NULL,
                fecha_revision = NULL
            ";
        }

        $sqlActualizar .= " WHERE id = :id";

        $stmtActualizar = $db->prepare($sqlActualizar);
        $stmtActualizar->execute($parametros);

        $idTrabajo = (int) $trabajoAnterior['id'];
    } else {
        $stmtCrear = $db->prepare("
            INSERT INTO trabajos (
                titulo_trabajo,
                id_estudiante,
                id_fase_actual,
                id_configuracion,
                fecha_presentacion,
                estado_aprobacion,
                calificacion_final,
                comentario_revision,
                fecha_revision,
                ruta_archivo,
                actualizado_el
            ) VALUES (
                :titulo_trabajo,
                :id_estudiante,
                :id_fase_actual,
                :id_configuracion,
                NOW(),
                :estado_aprobacion,
                NULL,
                NULL,
                NULL,
                :ruta_archivo,
                NOW()
            )
        ");

        $stmtCrear->execute([
            ':titulo_trabajo' => $tituloTrabajo,
            ':id_estudiante' => $usuarioId,
            ':id_fase_actual' => $idFaseSolicitada,
            ':id_configuracion' => (int) $fase['id_configuracion'],
            ':estado_aprobacion' => $nuevoEstado,
            ':ruta_archivo' => $rutaArchivo,
        ]);

        $idTrabajo = (int) $db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | Mantener versiones del documento
    |--------------------------------------------------------------------------
    */
    $stmtEntregaVigente = $db->prepare("
        SELECT *
        FROM trabajo_entregas
        WHERE id_trabajo = :id_trabajo
          AND es_vigente = 1
        ORDER BY numero_version DESC, id DESC
        LIMIT 1
    ");

    $stmtEntregaVigente->execute([
        ':id_trabajo' => $idTrabajo,
    ]);

    $entregaVigente = $stmtEntregaVigente->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($accion === 'ENVIAR_REVISION') {
        $crearNuevaVersion = $archivoNuevo || !$entregaVigente;

        if ($crearNuevaVersion) {
            if ($entregaVigente) {
                $stmtCerrarEntregaAnterior = $db->prepare("
                    UPDATE trabajo_entregas
                    SET
                        es_vigente = 0,
                        actualizado_el = NOW()
                    WHERE id = :id_entrega
                ");

                $stmtCerrarEntregaAnterior->execute([
                    ':id_entrega' => (int) $entregaVigente['id'],
                ]);
            }

            $stmtVersion = $db->prepare("
                SELECT COALESCE(MAX(numero_version), 0) + 1
                FROM trabajo_entregas
                WHERE id_trabajo = :id_trabajo
            ");

            $stmtVersion->execute([
                ':id_trabajo' => $idTrabajo,
            ]);

            $numeroVersion = (int) $stmtVersion->fetchColumn();

            $nombreServidor = $rutaArchivo
                ? basename($rutaArchivo)
                : null;

            $nombreOriginalGuardar = $nombreOriginalArchivo
                ?: ($rutaArchivo ? basename($rutaArchivo) : null);

            $stmtCrearEntrega = $db->prepare("
                INSERT INTO trabajo_entregas (
                    id_trabajo,
                    numero_version,
                    titulo_trabajo,
                    nombre_original,
                    archivo_servidor,
                    ruta_archivo,
                    mime_type,
                    tamano_bytes,
                    estado_entrega,
                    es_vigente,
                    guardado_por,
                    guardado_el,
                    enviado_el
                ) VALUES (
                    :id_trabajo,
                    :numero_version,
                    :titulo_trabajo,
                    :nombre_original,
                    :archivo_servidor,
                    :ruta_archivo,
                    :mime_type,
                    :tamano_bytes,
                    :estado_entrega,
                    1,
                    :guardado_por,
                    NOW(),
                    NOW()
                )
            ");

            $stmtCrearEntrega->execute([
                ':id_trabajo' => $idTrabajo,
                ':numero_version' => $numeroVersion,
                ':titulo_trabajo' => $tituloTrabajo,
                ':nombre_original' => $nombreOriginalGuardar,
                ':archivo_servidor' => $nombreServidor,
                ':ruta_archivo' => $rutaArchivo,
                ':mime_type' => $mimeTypeArchivo ?: null,
                ':tamano_bytes' => $tamanoArchivo ?: null,
                ':estado_entrega' => $nuevoEstado,
                ':guardado_por' => $usuarioId,
            ]);
        } else {
            $stmtActualizarEntrega = $db->prepare("
                UPDATE trabajo_entregas
                SET
                    titulo_trabajo = :titulo_trabajo,
                    estado_entrega = :estado_entrega,
                    enviado_el = NOW(),
                    actualizado_el = NOW()
                WHERE id = :id_entrega
            ");

            $stmtActualizarEntrega->execute([
                ':titulo_trabajo' => $tituloTrabajo,
                ':estado_entrega' => $nuevoEstado,
                ':id_entrega' => (int) $entregaVigente['id'],
            ]);
        }
    } else {
        if ($entregaVigente) {
            $stmtActualizarBorrador = $db->prepare("
                UPDATE trabajo_entregas
                SET
                    titulo_trabajo = :titulo_trabajo,
                    nombre_original = :nombre_original,
                    archivo_servidor = :archivo_servidor,
                    ruta_archivo = :ruta_archivo,
                    mime_type = :mime_type,
                    tamano_bytes = :tamano_bytes,
                    estado_entrega = 'BORRADOR',
                    actualizado_el = NOW()
                WHERE id = :id_entrega
            ");

            $stmtActualizarBorrador->execute([
                ':titulo_trabajo' => $tituloTrabajo,
                ':nombre_original' => $nombreOriginalArchivo ?: $entregaVigente['nombre_original'],
                ':archivo_servidor' => $rutaArchivo ? basename($rutaArchivo) : $entregaVigente['archivo_servidor'],
                ':ruta_archivo' => $rutaArchivo,
                ':mime_type' => $mimeTypeArchivo ?: $entregaVigente['mime_type'],
                ':tamano_bytes' => $tamanoArchivo ?: $entregaVigente['tamano_bytes'],
                ':id_entrega' => (int) $entregaVigente['id'],
            ]);
        } else {
            $stmtCrearBorrador = $db->prepare("
                INSERT INTO trabajo_entregas (
                    id_trabajo,
                    numero_version,
                    titulo_trabajo,
                    nombre_original,
                    archivo_servidor,
                    ruta_archivo,
                    mime_type,
                    tamano_bytes,
                    estado_entrega,
                    es_vigente,
                    guardado_por,
                    guardado_el
                ) VALUES (
                    :id_trabajo,
                    1,
                    :titulo_trabajo,
                    :nombre_original,
                    :archivo_servidor,
                    :ruta_archivo,
                    :mime_type,
                    :tamano_bytes,
                    'BORRADOR',
                    1,
                    :guardado_por,
                    NOW()
                )
            ");

            $stmtCrearBorrador->execute([
                ':id_trabajo' => $idTrabajo,
                ':titulo_trabajo' => $tituloTrabajo,
                ':nombre_original' => $nombreOriginalArchivo,
                ':archivo_servidor' => $rutaArchivo ? basename($rutaArchivo) : null,
                ':ruta_archivo' => $rutaArchivo,
                ':mime_type' => $mimeTypeArchivo ?: null,
                ':tamano_bytes' => $tamanoArchivo ?: null,
                ':guardado_por' => $usuarioId,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Reentrega autorizada
    |--------------------------------------------------------------------------
    */
    if ($esReentregaAutorizada && $controlReentrega) {
        $stmtActualizarControl = $db->prepare("
            UPDATE control_reentregas
            SET
                estado = 'REENTREGADA',
                fecha_reentrega = NOW(),
                actualizado_el = NOW()
            WHERE id = :id_control
              AND estado = 'AUTORIZADA'
        ");

        $stmtActualizarControl->execute([
            ':id_control' => (int) $controlReentrega['id'],
        ]);

        if ($stmtActualizarControl->rowCount() === 0) {
            throw new RuntimeException(
                'La autorización de corrección cambió o ya no está disponible.'
            );
        }

        $stmtActualizarFase = $db->prepare("
            UPDATE fase_estudiante_config
            SET
                estado = 'ACTIVO',
                observacion = 'Versión corregida enviada por el participante. Pendiente de nueva revisión del jurado.',
                fecha_actualizacion = NOW()
            WHERE id = :id_habilitacion
        ");

        $stmtActualizarFase->execute([
            ':id_habilitacion' => (int) $fase['id_habilitacion'],
        ]);
    }

    $db->commit();

    if ($esReentregaAutorizada) {
        $mensaje = 'Tu versión corregida fue enviada correctamente. El jurado realizará una nueva revisión.';
    } elseif ($accion === 'ENVIAR_REVISION') {
        $mensaje = 'Tu documento fue enviado correctamente a revisión.';
    } else {
        $mensaje = 'El borrador fue guardado correctamente.';
    }

    volverSeguimiento($programaId, 'success', $mensaje);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    volverSeguimiento(
        $programaId,
        'error',
        'Ocurrió un error al guardar el trabajo. Inténtalo nuevamente.'
    );
}