<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderProgramasTitulacion(
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

function limpiarTextoPrograma($valor, int $limite = 100): string
{
    return mb_substr(trim((string) $valor), 0, $limite);
}

function obtenerFiltrosProgramas(): array
{
    $porPagina = (int) ($_GET['por_pagina'] ?? 12);

    if (!in_array($porPagina, [12, 24, 48], true)) {
        $porPagina = 12;
    }

    return [
        'sede' => (int) ($_GET['sede'] ?? 0),
        'tipo' => strtoupper(limpiarTextoPrograma($_GET['tipo'] ?? '', 50)),
        'gestion' => limpiarTextoPrograma($_GET['gestion'] ?? '', 20),
        'version' => limpiarTextoPrograma($_GET['version'] ?? '', 20),
        'buscar' => limpiarTextoPrograma($_GET['buscar'] ?? '', 100),
        'pagina' => max(1, (int) ($_GET['pagina'] ?? 1)),
        'por_pagina' => $porPagina,
    ];
}

function condicionesProgramas(array $filtros): array
{
    $condiciones = [];
    $parametros = [];

    if ($filtros['sede'] > 0) {
        $condiciones[] = 'p.id_sede_externa = :sede';
        $parametros[':sede'] = $filtros['sede'];
    }

    if ($filtros['tipo'] !== '') {
        $condiciones[] = "UPPER(TRIM(COALESCE(p.tipo, ''))) = :tipo";
        $parametros[':tipo'] = $filtros['tipo'];
    }

    if ($filtros['gestion'] !== '') {
        $condiciones[] = 'CAST(p.gestion_externa AS CHAR) = :gestion';
        $parametros[':gestion'] = $filtros['gestion'];
    }

    if ($filtros['version'] !== '') {
        $condiciones[] = 'CAST(p.version_programa_externa AS CHAR) = :version';
        $parametros[':version'] = $filtros['version'];
    }

    if ($filtros['buscar'] !== '') {
        $condiciones[] = "
            CONCAT_WS(
                ' ',
                p.nombre_programa,
                p.codigo_programa_externo,
                se.nombre_sede,
                se.ciudad,
                p.gestion_externa,
                p.version_programa_externa
            ) LIKE :buscar
        ";

        $parametros[':buscar'] = '%' . $filtros['buscar'] . '%';
    }

    return [$condiciones, $parametros];
}

function obtenerCatalogosProgramas(PDO $db): array
{
    $stmtSedes = $db->query("
        SELECT
            id_sede,
            nombre_sede,
            ciudad
        FROM siget_externa.ext_sedes
        WHERE COALESCE(estado, 1) = 1
        ORDER BY nombre_sede ASC
    ");

    $stmtTipos = $db->query("
        SELECT DISTINCT
            UPPER(TRIM(COALESCE(tipo, ''))) AS tipo_programa
        FROM programa
        WHERE TRIM(COALESCE(tipo, '')) <> ''
        ORDER BY tipo_programa ASC
    ");

    $stmtGestiones = $db->query("
        SELECT DISTINCT
            CAST(gestion_externa AS CHAR) AS gestion
        FROM programa
        WHERE gestion_externa IS NOT NULL
          AND CAST(gestion_externa AS CHAR) <> ''
        ORDER BY gestion DESC
    ");

    $stmtVersiones = $db->query("
        SELECT DISTINCT
            CAST(version_programa_externa AS CHAR) AS version
        FROM programa
        WHERE version_programa_externa IS NOT NULL
          AND CAST(version_programa_externa AS CHAR) <> ''
        ORDER BY version DESC
    ");

    return [
        'sedes' => $stmtSedes->fetchAll(PDO::FETCH_ASSOC),
        'tipos' => $stmtTipos->fetchAll(PDO::FETCH_ASSOC),
        'gestiones' => $stmtGestiones->fetchAll(PDO::FETCH_ASSOC),
        'versiones' => $stmtVersiones->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function accionPrincipalGrupo(array $grupo): array
{
    $configuracionCompleta =
        (int) $grupo['fase_1_configurada'] === 1 &&
        (int) $grupo['fase_2_configurada'] === 1 &&
        (int) $grupo['fase_3_configurada'] === 1;

    if (!$configuracionCompleta) {
        return [
            'codigo' => 'CONFIGURAR_FASES',
            'texto' => 'Configurar fases y fechas base',
            'cantidad' => 0,
        ];
    }

    if ((int) $grupo['fase_3_por_habilitar'] > 0) {
        return [
            'codigo' => 'HABILITAR_FASE_3',
            'texto' => 'Habilitar Fase 3',
            'cantidad' => (int) $grupo['fase_3_por_habilitar'],
        ];
    }

    if ((int) $grupo['fase_1_en_revision'] > 0) {
        return [
            'codigo' => 'REVISAR_FASE_1',
            'texto' => 'Revisar propuestas de Fase 1',
            'cantidad' => (int) $grupo['fase_1_en_revision'],
        ];
    }

    if ((int) $grupo['correcciones_pendientes'] > 0) {
        return [
            'codigo' => 'GESTIONAR_CORRECCIONES',
            'texto' => 'Gestionar correcciones y plazos',
            'cantidad' => (int) $grupo['correcciones_pendientes'],
        ];
    }

    if ((int) $grupo['fase_2_en_revision'] > 0) {
        return [
            'codigo' => 'SEGUIMIENTO_JURADO',
            'texto' => 'Monografías en revisión de jurado',
            'cantidad' => (int) $grupo['fase_2_en_revision'],
        ];
    }

    if ((int) $grupo['participantes_sin_proceso'] > 0) {
        return [
            'codigo' => 'INICIAR_PROCESOS',
            'texto' => 'Participantes sin proceso iniciado',
            'cantidad' => (int) $grupo['participantes_sin_proceso'],
        ];
    }

    return [
        'codigo' => 'VER_PARTICIPANTES',
        'texto' => 'Ver participantes del programa',
        'cantidad' => (int) $grupo['total_participantes'],
    ];
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderProgramasTitulacion(false, 'Tu sesión finalizó. Ingresa nuevamente.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderProgramasTitulacion(false, 'Acceso restringido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderProgramasTitulacion(false, 'No se pudo establecer la conexión local.');
}

try {
    $db = $conexion;
    $filtros = obtenerFiltrosProgramas();

    [$condiciones, $parametros] = condicionesProgramas($filtros);

    $where = $condiciones
        ? 'WHERE ' . implode(' AND ', $condiciones)
        : '';

    $stmtTotal = $db->prepare("
        SELECT COUNT(*)
        FROM programa p
        LEFT JOIN siget_externa.ext_sedes se
            ON se.id_sede = p.id_sede_externa
        $where
    ");

    foreach ($parametros as $clave => $valor) {
        $stmtTotal->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmtTotal->execute();

    $totalRegistros = (int) $stmtTotal->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalRegistros / $filtros['por_pagina']));

    if ($filtros['pagina'] > $totalPaginas) {
        $filtros['pagina'] = $totalPaginas;
    }

    $offset = ($filtros['pagina'] - 1) * $filtros['por_pagina'];

    $stmtProgramas = $db->prepare("
        SELECT
            p.id AS id_programa,
            p.id_programa_externo,
            p.codigo_programa_externo,
            p.nombre_programa,
            UPPER(TRIM(COALESCE(p.tipo, ''))) AS tipo_programa,
            p.gestion_externa,
            p.version_programa_externa,
            p.id_sede_externa,
            p.estado AS estado_programa,

            COALESCE(se.nombre_sede, 'Sin sede registrada') AS nombre_sede,
            COALESCE(se.ciudad, '') AS ciudad_sede,

            COALESCE(cfg.fase_1_configurada, 0) AS fase_1_configurada,
            COALESCE(cfg.fase_2_configurada, 0) AS fase_2_configurada,
            COALESCE(cfg.fase_3_configurada, 0) AS fase_3_configurada,

            COUNT(i.id) AS total_participantes,

            SUM(
                CASE
                    WHEN i.id IS NOT NULL
                     AND COALESCE(est.estado_f1, '') = ''
                     AND COALESCE(est.estado_f2, '') = ''
                     AND COALESCE(est.estado_f3, '') = ''
                     AND COALESCE(act.fase_1_activa, 0) = 0
                     AND COALESCE(act.fase_2_activa, 0) = 0
                     AND COALESCE(act.fase_3_activa, 0) = 0
                    THEN 1
                    ELSE 0
                END
            ) AS participantes_sin_proceso,

            SUM(
                CASE
                    WHEN UPPER(TRIM(COALESCE(est.estado_f1, ''))) = 'EN_REVISION'
                    THEN 1
                    ELSE 0
                END
            ) AS fase_1_en_revision,

            SUM(
                CASE
                    WHEN UPPER(TRIM(COALESCE(est.estado_f1, ''))) IN ('OBSERVADO', 'RECHAZADO')
                    THEN 1
                    ELSE 0
                END
            ) AS fase_1_observados,

            SUM(
                CASE
                    WHEN UPPER(TRIM(COALESCE(est.estado_f2, ''))) IN ('EN_REVISION', 'CORREGIDO')
                    THEN 1
                    ELSE 0
                END
            ) AS fase_2_en_revision,

            SUM(
                CASE
                    WHEN UPPER(TRIM(COALESCE(est.estado_f2, ''))) IN ('OBSERVADO', 'RECHAZADO')
                    THEN 1
                    ELSE 0
                END
            ) AS fase_2_observados,

            SUM(
                CASE
                    WHEN UPPER(TRIM(COALESCE(est.estado_f3, ''))) IN ('OBSERVADO', 'RECHAZADO')
                    THEN 1
                    ELSE 0
                END
            ) AS fase_3_observados,

            SUM(
                CASE
                    WHEN UPPER(TRIM(COALESCE(est.estado_f2, ''))) IN ('REVISADO', 'APROBADO')
                     AND COALESCE(est.estado_f3, '') = ''
                     AND COALESCE(act.fase_3_activa, 0) = 0
                    THEN 1
                    ELSE 0
                END
            ) AS fase_3_por_habilitar,

            SUM(
                CASE
                    WHEN COALESCE(act.fase_3_activa, 0) = 1
                      OR UPPER(TRIM(COALESCE(est.estado_f3, ''))) IN (
                          'BORRADOR',
                          'EN_REVISION',
                          'CORREGIDO',
                          'OBSERVADO',
                          'RECHAZADO'
                      )
                    THEN 1
                    ELSE 0
                END
            ) AS fase_3_en_proceso,

            SUM(
                CASE
                    WHEN UPPER(TRIM(COALESCE(est.estado_f3, ''))) IN ('REVISADO', 'APROBADO')
                    THEN 1
                    ELSE 0
                END
            ) AS procesos_finalizados,

            (
                SUM(
                    CASE
                        WHEN UPPER(TRIM(COALESCE(est.estado_f2, ''))) IN ('OBSERVADO', 'RECHAZADO')
                        THEN 1
                        ELSE 0
                    END
                )
                +
                SUM(
                    CASE
                        WHEN UPPER(TRIM(COALESCE(est.estado_f3, ''))) IN ('OBSERVADO', 'RECHAZADO')
                        THEN 1
                        ELSE 0
                    END
                )
            ) AS correcciones_pendientes

        FROM programa p

        LEFT JOIN siget_externa.ext_sedes se
            ON se.id_sede = p.id_sede_externa

        LEFT JOIN inscripciones i
            ON i.id_programa = p.id

        LEFT JOIN (
            SELECT
                pfc.id_programa,

                MAX(
                    CASE
                        WHEN f.numero_fase = 1
                         AND UPPER(TRIM(COALESCE(pfc.estado, ''))) = 'ACTIVO'
                        THEN 1
                        ELSE 0
                    END
                ) AS fase_1_configurada,

                MAX(
                    CASE
                        WHEN f.numero_fase = 2
                         AND UPPER(TRIM(COALESCE(pfc.estado, ''))) = 'ACTIVO'
                        THEN 1
                        ELSE 0
                    END
                ) AS fase_2_configurada,

                MAX(
                    CASE
                        WHEN f.numero_fase = 3
                         AND UPPER(TRIM(COALESCE(pfc.estado, ''))) = 'ACTIVO'
                        THEN 1
                        ELSE 0
                    END
                ) AS fase_3_configurada

            FROM programa_fase_config pfc
            INNER JOIN fases f
                ON f.id = pfc.id_fase
            GROUP BY pfc.id_programa
        ) cfg
            ON cfg.id_programa = p.id

        LEFT JOIN (
            SELECT
                t.id_estudiante,
                pfc.id_programa,

                MAX(
                    CASE
                        WHEN f.numero_fase = 1
                        THEN UPPER(TRIM(COALESCE(t.estado_aprobacion, '')))
                        ELSE NULL
                    END
                ) AS estado_f1,

                MAX(
                    CASE
                        WHEN f.numero_fase = 2
                        THEN UPPER(TRIM(COALESCE(t.estado_aprobacion, '')))
                        ELSE NULL
                    END
                ) AS estado_f2,

                MAX(
                    CASE
                        WHEN f.numero_fase = 3
                        THEN UPPER(TRIM(COALESCE(t.estado_aprobacion, '')))
                        ELSE NULL
                    END
                ) AS estado_f3

            FROM trabajos t

            INNER JOIN (
                SELECT
                    id_estudiante,
                    id_configuracion,
                    MAX(id) AS id_ultimo
                FROM trabajos
                GROUP BY
                    id_estudiante,
                    id_configuracion
            ) ultimo
                ON ultimo.id_ultimo = t.id

            INNER JOIN programa_fase_config pfc
                ON pfc.id = t.id_configuracion

            INNER JOIN fases f
                ON f.id = pfc.id_fase

            GROUP BY
                t.id_estudiante,
                pfc.id_programa
        ) est
            ON est.id_estudiante = i.id_estudiante
            AND est.id_programa = i.id_programa

        LEFT JOIN (
            SELECT
                fec.id_estudiante,
                pfc.id_programa,

                MAX(
                    CASE
                        WHEN f.numero_fase = 1
                         AND UPPER(TRIM(COALESCE(fec.estado, ''))) = 'ACTIVO'
                        THEN 1
                        ELSE 0
                    END
                ) AS fase_1_activa,

                MAX(
                    CASE
                        WHEN f.numero_fase = 2
                         AND UPPER(TRIM(COALESCE(fec.estado, ''))) = 'ACTIVO'
                        THEN 1
                        ELSE 0
                    END
                ) AS fase_2_activa,

                MAX(
                    CASE
                        WHEN f.numero_fase = 3
                         AND UPPER(TRIM(COALESCE(fec.estado, ''))) = 'ACTIVO'
                        THEN 1
                        ELSE 0
                    END
                ) AS fase_3_activa

            FROM fase_estudiante_config fec

            INNER JOIN programa_fase_config pfc
                ON pfc.id = fec.id_configuracion

            INNER JOIN fases f
                ON f.id = pfc.id_fase

            GROUP BY
                fec.id_estudiante,
                pfc.id_programa
        ) act
            ON act.id_estudiante = i.id_estudiante
            AND act.id_programa = i.id_programa

        $where

        GROUP BY
            p.id,
            p.id_programa_externo,
            p.codigo_programa_externo,
            p.nombre_programa,
            p.tipo,
            p.gestion_externa,
            p.version_programa_externa,
            p.id_sede_externa,
            p.estado,
            se.nombre_sede,
            se.ciudad,
            cfg.fase_1_configurada,
            cfg.fase_2_configurada,
            cfg.fase_3_configurada

        ORDER BY
            nombre_sede ASC,
            tipo_programa ASC,
            nombre_programa ASC,
            p.gestion_externa DESC,
            p.version_programa_externa DESC

        LIMIT :limite OFFSET :offset
    ");

    foreach ($parametros as $clave => $valor) {
        $stmtProgramas->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmtProgramas->bindValue(':limite', $filtros['por_pagina'], PDO::PARAM_INT);
    $stmtProgramas->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmtProgramas->execute();

    $programas = $stmtProgramas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($programas as &$programa) {
        $camposEnteros = [
            'fase_1_configurada',
            'fase_2_configurada',
            'fase_3_configurada',
            'total_participantes',
            'participantes_sin_proceso',
            'fase_1_en_revision',
            'fase_1_observados',
            'fase_2_en_revision',
            'fase_2_observados',
            'fase_3_observados',
            'fase_3_por_habilitar',
            'fase_3_en_proceso',
            'procesos_finalizados',
            'correcciones_pendientes',
        ];

        foreach ($camposEnteros as $campo) {
            $programa[$campo] = (int) ($programa[$campo] ?? 0);
        }

        $programa['accion_principal'] = accionPrincipalGrupo($programa);
    }

    unset($programa);

    responderProgramasTitulacion(
        true,
        'Programas de titulación cargados correctamente.',
        [
            'data' => $programas,
            'catalogos' => obtenerCatalogosProgramas($db),
            'paginacion' => [
                'pagina_actual' => $filtros['pagina'],
                'por_pagina' => $filtros['por_pagina'],
                'total_registros' => $totalRegistros,
                'total_paginas' => $totalPaginas,
            ],
            'filtros' => $filtros,
        ]
    );

} catch (Throwable $e) {
    http_response_code(500);

    responderProgramasTitulacion(
        false,
        'No fue posible cargar los programas de titulación.'
    );
}