<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderAsignacion(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderAsignacion(false, 'Tu sesión finalizó. Ingresa nuevamente al sistema.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderAsignacion(false, 'Acceso restringido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderAsignacion(false, 'No se pudo establecer la conexión local.');
}

try {
    $db = $conexion;

    /*
    |--------------------------------------------------------------------------
    | Jurados disponibles: docentes y tutores activos
    |--------------------------------------------------------------------------
    */
    $stmtJurados = $db->query("
        SELECT
            u.id,
            u.usuario,
            CONCAT_WS(
                ' ',
                u.nombres,
                u.apellido_paterno,
                u.apellido_materno
            ) AS nombre_completo,
            GROUP_CONCAT(
                DISTINCT r.nombre_rol
                ORDER BY r.nombre_rol
                SEPARATOR ' / '
            ) AS nombre_rol
        FROM usuarios u
        INNER JOIN usuario_rol ur
            ON ur.id_usuario = u.id
        INNER JOIN rol r
            ON r.id = ur.id_role
        WHERE ur.id_role IN (3, 4)
          AND COALESCE(u.estado_cuenta, 'ACTIVO') <> 'INACTIVO'
        GROUP BY
            u.id,
            u.usuario,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno
        ORDER BY nombre_completo ASC
    ");

    $jurados = $stmtJurados->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Solo Fase 1 revisada por Administración.
    |--------------------------------------------------------------------------
    */
    $stmtParticipantes = $db->prepare("
        SELECT
            t.id AS id_trabajo,
            t.id_estudiante,
            t.titulo_trabajo,
            t.calificacion_final,
            t.comentario_revision,
            t.fecha_presentacion,
            t.fecha_revision,
            t.ruta_archivo,

            CONCAT_WS(
                ' ',
                u.nombres,
                u.apellido_paterno,
                u.apellido_materno
            ) AS estudiante,

            u.usuario,
            u.correo,

            p.id AS id_programa,
            p.nombre_programa,
            p.tipo AS tipo_programa,
            p.gestion_externa,

            pfc.id AS id_configuracion_fase_1,
            pfc.gestion AS gestion_configuracion,

            i.id AS id_inscripcion,
            tp.id AS id_postulacion,

            ja.id AS id_jurado_actual,
            ja.observacion AS observacion_jurado,

            CONCAT_WS(
                ' ',
                uj.nombres,
                uj.apellido_paterno,
                uj.apellido_materno
            ) AS jurado_asignado,

            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM fase_estudiante_config fec2
                    INNER JOIN programa_fase_config pfc2
                        ON pfc2.id = fec2.id_configuracion
                    INNER JOIN fases f2
                        ON f2.id = pfc2.id_fase
                    WHERE fec2.id_estudiante = t.id_estudiante
                      AND pfc2.id_programa = p.id
                      AND pfc2.gestion = pfc.gestion
                      AND f2.numero_fase = 2
                      AND fec2.estado = 'ACTIVO'
                )
                THEN 1
                ELSE 0
            END AS fase_2_habilitada

        FROM trabajos t
        INNER JOIN programa_fase_config pfc
            ON pfc.id = t.id_configuracion
        INNER JOIN fases f
            ON f.id = pfc.id_fase
        INNER JOIN usuarios u
            ON u.id = t.id_estudiante
        INNER JOIN programa p
            ON p.id = pfc.id_programa
        INNER JOIN inscripciones i
            ON i.id_estudiante = t.id_estudiante
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
        LEFT JOIN usuarios uj
            ON uj.id = ja.id_docente

        WHERE f.numero_fase = 1
          AND UPPER(TRIM(t.estado_aprobacion)) IN ('APROBADO', 'REVISADO')
          AND t.id = (
                SELECT MAX(t2.id)
                FROM trabajos t2
                WHERE t2.id_estudiante = t.id_estudiante
                  AND t2.id_configuracion = t.id_configuracion
          )

        ORDER BY
            CASE
                WHEN ja.id IS NULL THEN 1
                ELSE 2
            END,
            estudiante ASC
    ");

    $stmtParticipantes->execute();

    $participantes = $stmtParticipantes->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'pendientes' => 0,
        'asignados' => 0,
        'fase_2_habilitada' => 0,
    ];

    foreach ($participantes as &$participante) {
        $participante['fase_2_habilitada'] = (int) $participante['fase_2_habilitada'];

        if (empty($participante['id_jurado_actual'])) {
            $stats['pendientes']++;
        } else {
            $stats['asignados']++;
        }

        if ($participante['fase_2_habilitada'] === 1) {
            $stats['fase_2_habilitada']++;
        }
    }

    unset($participante);

    responderAsignacion(true, 'Participantes y jurados cargados correctamente.', [
        'stats' => $stats,
        'jurados' => $jurados,
        'data' => $participantes,
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    responderAsignacion(
        false,
        'No fue posible cargar la información para asignación de jurados.'
    );
}