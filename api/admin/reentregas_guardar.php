<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderReentregaGuardar(
    bool $success,
    string $message = '',
    array $extra = []
): void {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

function fechaValidaReentrega(?string $fecha): ?string
{
    $fecha = trim((string) $fecha);

    if ($fecha === '') {
        return null;
    }

    $fecha = str_replace('T', ' ', $fecha);

    $marcaTiempo = strtotime($fecha);

    if ($marcaTiempo === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $marcaTiempo);
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderReentregaGuardar(false, 'Tu sesión finalizó. Ingresa nuevamente al sistema.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderReentregaGuardar(false, 'Acceso restringido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderReentregaGuardar(false, 'No se pudo establecer la conexión local.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responderReentregaGuardar(false, 'Método no permitido.');
}

$idAdministrador = (int) $_SESSION['id'];
$idControl = (int) ($_POST['id_control'] ?? 0);
$accion = strtoupper(trim((string) ($_POST['accion'] ?? '')));
$fechaLimite = fechaValidaReentrega($_POST['fecha_limite_correccion'] ?? '');
$observacion = trim((string) ($_POST['observacion'] ?? ''));

$accionesValidas = [
    'AUTORIZAR',
    'CERRAR',
    'REABRIR',
];

if ($idControl <= 0 || !in_array($accion, $accionesValidas, true)) {
    http_response_code(422);
    responderReentregaGuardar(false, 'Los datos enviados no son válidos.');
}

if (in_array($accion, ['AUTORIZAR', 'REABRIR'], true)) {
    if (!$fechaLimite) {
        http_response_code(422);
        responderReentregaGuardar(
            false,
            'Debes definir una fecha límite para la nueva entrega.'
        );
    }

    if (strtotime($fechaLimite) <= time()) {
        http_response_code(422);
        responderReentregaGuardar(
            false,
            'La nueva fecha límite debe ser posterior a la fecha y hora actual.'
        );
    }
}

if ($accion === 'CERRAR' && $observacion === '') {
    http_response_code(422);
    responderReentregaGuardar(
        false,
        'Debes indicar el motivo de cierre de la corrección.'
    );
}

try {
    $db = $conexion;
    $db->beginTransaction();

    $stmtControl = $db->prepare("
        SELECT
            cr.id,
            cr.id_trabajo,
            cr.id_fase_estudiante_config,
            cr.ciclo,
            cr.estado,
            cr.motivo,

            t.id_estudiante,
            t.titulo_trabajo,
            t.estado_aprobacion,

            fec.id AS id_fase_estudiante,
            fec.id_configuracion,

            pfc.id_programa,
            pfc.gestion,

            f.numero_fase,

            CONCAT_WS(
                ' ',
                u.nombres,
                u.apellido_paterno,
                u.apellido_materno
            ) AS estudiante

        FROM control_reentregas cr
        INNER JOIN trabajos t
            ON t.id = cr.id_trabajo
        INNER JOIN fase_estudiante_config fec
            ON fec.id = cr.id_fase_estudiante_config
        INNER JOIN programa_fase_config pfc
            ON pfc.id = fec.id_configuracion
        INNER JOIN fases f
            ON f.id = pfc.id_fase
        INNER JOIN usuarios u
            ON u.id = t.id_estudiante
        WHERE cr.id = :id_control
        LIMIT 1
        FOR UPDATE
    ");

    $stmtControl->execute([
        ':id_control' => $idControl,
    ]);

    $control = $stmtControl->fetch(PDO::FETCH_ASSOC);

    if (!$control) {
        throw new RuntimeException('No se encontró el control de reentrega solicitado.');
    }

    $estadoActual = strtoupper(trim((string) $control['estado']));

    if ($accion === 'AUTORIZAR' && !in_array(
        $estadoActual,
        ['PENDIENTE_AUTORIZACION', 'AUTORIZADA'],
        true
    )) {
        throw new RuntimeException(
            'Esta corrección no se encuentra disponible para autorización.'
        );
    }

    if ($accion === 'REABRIR' && $estadoActual !== 'CERRADA') {
        throw new RuntimeException(
            'Solo se pueden reabrir correcciones que hayan sido cerradas.'
        );
    }

    if ($accion === 'CERRAR' && $estadoActual === 'REENTREGADA') {
        throw new RuntimeException(
            'No se puede cerrar una corrección que ya fue reenviada por el estudiante.'
        );
    }

    if ($accion === 'AUTORIZAR' || $accion === 'REABRIR') {
        $mensajeAutorizacion = $accion === 'REABRIR'
            ? 'Corrección reabierta excepcionalmente por Administración.'
            : 'Nueva entrega autorizada por Administración.';

        if ($observacion !== '') {
            $mensajeAutorizacion .= ' ' . $observacion;
        }

        $stmtActualizarControl = $db->prepare("
            UPDATE control_reentregas
            SET
                estado = 'AUTORIZADA',
                fecha_autorizacion = NOW(),
                fecha_limite_correccion = :fecha_limite,
                fecha_reentrega = NULL,
                observacion_cierre = NULL,
                autorizado_por = :id_administrador,
                cerrado_por = NULL
            WHERE id = :id_control
        ");

        $stmtActualizarControl->execute([
            ':fecha_limite' => $fechaLimite,
            ':id_administrador' => $idAdministrador,
            ':id_control' => $idControl,
        ]);

        $stmtActualizarFase = $db->prepare("
            UPDATE fase_estudiante_config
            SET
                estado = 'ACTIVO',
                fecha_inicio_entrega = NOW(),
                fecha_limite_entrega = :fecha_limite,
                observacion = :observacion
            WHERE id = :id_fase_estudiante
        ");

        $stmtActualizarFase->execute([
            ':fecha_limite' => $fechaLimite,
            ':observacion' => $mensajeAutorizacion,
            ':id_fase_estudiante' => $control['id_fase_estudiante'],
        ]);

        $db->commit();

        responderReentregaGuardar(
            true,
            'Nueva entrega autorizada correctamente.',
            [
                'estado' => 'AUTORIZADA',
                'fecha_limite_correccion' => $fechaLimite,
                'estudiante' => $control['estudiante'],
            ]
        );
    }

    if ($accion === 'CERRAR') {
        $stmtCerrarControl = $db->prepare("
            UPDATE control_reentregas
            SET
                estado = 'CERRADA',
                observacion_cierre = :observacion_cierre,
                cerrado_por = :id_administrador
            WHERE id = :id_control
        ");

        $stmtCerrarControl->execute([
            ':observacion_cierre' => $observacion,
            ':id_administrador' => $idAdministrador,
            ':id_control' => $idControl,
        ]);

        $stmtActualizarFase = $db->prepare("
            UPDATE fase_estudiante_config
            SET
                observacion = :observacion
            WHERE id = :id_fase_estudiante
        ");

        $stmtActualizarFase->execute([
            ':observacion' => 'Corrección cerrada por Administración. ' . $observacion,
            ':id_fase_estudiante' => $control['id_fase_estudiante'],
        ]);

        $db->commit();

        responderReentregaGuardar(
            true,
            'La corrección fue cerrada correctamente.',
            [
                'estado' => 'CERRADA',
                'estudiante' => $control['estudiante'],
            ]
        );
    }

    throw new RuntimeException('No se pudo completar la acción solicitada.');

} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);

    responderReentregaGuardar(
        false,
        $e->getMessage() ?: 'No fue posible gestionar la reentrega.'
    );
}