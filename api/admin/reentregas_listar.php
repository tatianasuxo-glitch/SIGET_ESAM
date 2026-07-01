<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderReentregas(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderReentregas(false, 'Tu sesión finalizó. Ingresa nuevamente al sistema.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderReentregas(false, 'Acceso restringido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderReentregas(false, 'No se pudo establecer la conexión local.');
}

try {
    $db = $conexion;

    $stmt = $db->query("
        SELECT
            cr.id AS id_control,
            cr.id_trabajo,
            cr.id_fase_estudiante_config,
            cr.ciclo,
            cr.estado,
            cr.fecha_autorizacion,
            cr.fecha_limite_correccion,
            cr.fecha_reentrega,
            cr.motivo,
            cr.observacion_cierre,
            cr.creado_el,
            cr.actualizado_el,

            t.titulo_trabajo,
            t.ruta_archivo,
            t.estado_aprobacion,
            t.fecha_presentacion,

            CONCAT_WS(
                ' ',
                estudiante.nombres,
                estudiante.apellido_paterno,
                estudiante.apellido_materno
            ) AS estudiante,

            estudiante.usuario AS usuario_estudiante,
            estudiante.correo,

            p.nombre_programa,
            p.tipo AS tipo_programa,
            p.gestion_externa,

            f.numero_fase,
            f.nombre_fase,

            COALESCE(
                fec.fecha_limite_entrega,
                pfc.fecha_limite_entrega
            ) AS fecha_limite_original,

            CONCAT_WS(
                ' ',
                jurado.nombres,
                jurado.apellido_paterno,
                jurado.apellido_materno
            ) AS jurado_responsable

        FROM control_reentregas cr

        INNER JOIN trabajos t
            ON t.id = cr.id_trabajo

        INNER JOIN fase_estudiante_config fec
            ON fec.id = cr.id_fase_estudiante_config

        INNER JOIN programa_fase_config pfc
            ON pfc.id = fec.id_configuracion

        INNER JOIN fases f
            ON f.id = pfc.id_fase

        INNER JOIN programa p
            ON p.id = pfc.id_programa

        INNER JOIN usuarios estudiante
            ON estudiante.id = t.id_estudiante

        LEFT JOIN inscripciones i
            ON i.id_estudiante = estudiante.id
            AND i.id_programa = p.id

        LEFT JOIN titulacion_postulaciones tp
            ON tp.id_inscripcion = i.id

        LEFT JOIN jurado_asignaciones ja
            ON ja.id = (
                SELECT ja2.id
                FROM jurado_asignaciones ja2
                WHERE ja2.id_postulacion = tp.id
                  AND ja2.estado = 'ASIGNADO'
                ORDER BY ja2.fecha_asignacion DESC, ja2.id DESC
                LIMIT 1
            )

        LEFT JOIN usuarios jurado
            ON jurado.id = ja.id_docente

        ORDER BY
            CASE cr.estado
                WHEN 'PENDIENTE_AUTORIZACION' THEN 1
                WHEN 'AUTORIZADA' THEN 2
                WHEN 'REENTREGADA' THEN 3
                WHEN 'CERRADA' THEN 4
                ELSE 5
            END,
            cr.actualizado_el DESC,
            cr.id DESC
    ");

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'pendientes_autorizacion' => 0,
        'autorizadas' => 0,
        'reentregadas' => 0,
        'cerradas' => 0,
    ];

    foreach ($items as &$item) {
        $estado = strtoupper((string) ($item['estado'] ?? ''));

        if ($estado === 'PENDIENTE_AUTORIZACION') {
            $stats['pendientes_autorizacion']++;
        }

        if ($estado === 'AUTORIZADA') {
            $stats['autorizadas']++;
        }

        if ($estado === 'REENTREGADA') {
            $stats['reentregadas']++;
        }

        if ($estado === 'CERRADA') {
            $stats['cerradas']++;
        }
    }

    unset($item);

    responderReentregas(true, 'Correcciones cargadas correctamente.', [
        'stats' => $stats,
        'data' => $items,
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    responderReentregas(
        false,
        'No fue posible cargar las solicitudes de corrección.'
    );
}