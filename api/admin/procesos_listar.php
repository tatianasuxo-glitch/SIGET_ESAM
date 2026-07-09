<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

function responderProcesos(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

function textoFiltro($valor, int $limite = 100): string
{
    $valor = trim((string) $valor);

    return substr($valor, 0, $limite);
}

function filtrosProcesos(): array
{
    $sede = (int) ($_GET['sede'] ?? 0);
    $programa = (int) ($_GET['programa'] ?? 0);
    $fase = (int) ($_GET['fase'] ?? 0);
    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
    $porPagina = (int) ($_GET['por_pagina'] ?? 25);

    if (!in_array($porPagina, [25, 50, 100], true)) {
        $porPagina = 25;
    }

    if (!in_array($fase, [0, 1, 2, 3], true)) {
        $fase = 0;
    }

    $tipo = strtoupper(textoFiltro($_GET['tipo'] ?? '', 40));
    $gestion = textoFiltro($_GET['gestion'] ?? '', 20);
    $version = textoFiltro($_GET['version'] ?? '', 20);
    $estado = strtoupper(textoFiltro($_GET['estado'] ?? '', 40));
    $buscar = textoFiltro($_GET['buscar'] ?? '', 100);

    $estadosPermitidos = [
        '',
        'SIN_CONFIGURACION',
        'HABILITADA',
        'BORRADOR',
        'EN_REVISION',
        'OBSERVADO',
        'CORREGIDO',
        'REVISADO',
    ];

    if (!in_array($estado, $estadosPermitidos, true)) {
        $estado = '';
    }

    return [
        'sede' => $sede,
        'programa' => $programa,
        'fase' => $fase,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
        'tipo' => $tipo,
        'gestion' => $gestion,
        'version' => $version,
        'estado' => $estado,
        'buscar' => $buscar,
    ];
}

function consultaBaseProcesos(): string
{
    return "
        SELECT
            base.*,

            CASE
                WHEN UPPER(TRIM(COALESCE(base.estado_actual_bruto, ''))) IN ('APROBADO', 'REVISADO')
                    THEN 'REVISADO'

                WHEN UPPER(TRIM(COALESCE(base.estado_actual_bruto, ''))) IN ('RECHAZADO', 'OBSERVADO')
                    THEN 'OBSERVADO'

                WHEN UPPER(TRIM(COALESCE(base.estado_actual_bruto, ''))) = 'CORREGIDO'
                    THEN 'CORREGIDO'

                WHEN UPPER(TRIM(COALESCE(base.estado_actual_bruto, ''))) = 'EN_REVISION'
                    THEN 'EN_REVISION'

                WHEN UPPER(TRIM(COALESCE(base.estado_actual_bruto, ''))) = 'BORRADOR'
                    THEN 'BORRADOR'

                WHEN UPPER(TRIM(COALESCE(base.estado_actual_bruto, ''))) = 'HABILITADA'
                    THEN 'HABILITADA'

                ELSE 'SIN_CONFIGURACION'
            END AS estado_proceso

        FROM (
            SELECT
                i.id AS id_inscripcion,
                i.id_inscripcion_externa,
                i.estado_cartera,
                i.estado_academico,
                i.estado_acceso,

                u.id AS id_estudiante,
                u.codigo_participante_externo,
                u.ci,
                u.usuario,
                u.correo,

                CONCAT_WS(
                    ' ',
                    u.nombres,
                    u.apellido_paterno,
                    u.apellido_materno
                ) AS estudiante,

                p.id AS id_programa,
                p.id_programa_externo,
                p.codigo_programa_externo,
                p.nombre_programa,
                UPPER(TRIM(COALESCE(p.tipo, ''))) AS tipo_programa,
                p.gestion_externa,
                p.version_programa_externa,
                p.id_sede_externa,

                CASE
                    WHEN se.nombre_sede IS NOT NULL
                        THEN se.nombre_sede

                    WHEN p.id_sede_externa IS NOT NULL
                        THEN CONCAT('Sede ', p.id_sede_externa)

                    ELSE 'Sin sede registrada'
                END AS nombre_sede,

                COALESCE(se.ciudad, '') AS ciudad_sede,

                tp.id AS id_postulacion,

                CONCAT_WS(
                    ' ',
                    uj.nombres,
                    uj.apellido_paterno,
                    uj.apellido_materno
                ) AS jurado_asignado,

                ja.id_docente AS id_jurado,

                COALESCE(rf.fase_1_activa, 0) AS fase_1_habilitada,
                COALESCE(rf.fase_2_activa, 0) AS fase_2_habilitada,
                COALESCE(rf.fase_3_activa, 0) AS fase_3_habilitada,

                rt.id_trabajo_f1,
                rt.id_trabajo_f2,
                rt.id_trabajo_f3,

                rt.estado_f1,
                rt.estado_f2,
                rt.estado_f3,

                CASE
                    WHEN rt.id_trabajo_f3 IS NOT NULL
                        OR COALESCE(rf.fase_3_activa, 0) = 1
                        THEN 3

                    WHEN rt.id_trabajo_f2 IS NOT NULL
                        OR COALESCE(rf.fase_2_activa, 0) = 1
                        THEN 2

                    WHEN rt.id_trabajo_f1 IS NOT NULL
                        OR COALESCE(rf.fase_1_activa, 0) = 1
                        THEN 1

                    ELSE 0
                END AS fase_actual,

                CASE
                    WHEN rt.id_trabajo_f3 IS NOT NULL
                        THEN rt.estado_f3

                    WHEN COALESCE(rf.fase_3_activa, 0) = 1
                        THEN 'HABILITADA'

                    WHEN rt.id_trabajo_f2 IS NOT NULL
                        THEN rt.estado_f2

                    WHEN COALESCE(rf.fase_2_activa, 0) = 1
                        THEN 'HABILITADA'

                    WHEN rt.id_trabajo_f1 IS NOT NULL
                        THEN rt.estado_f1

                    WHEN COALESCE(rf.fase_1_activa, 0) = 1
                        THEN 'HABILITADA'

                    ELSE 'SIN_CONFIGURACION'
                END AS estado_actual_bruto

            FROM inscripciones i

            INNER JOIN usuarios u
                ON u.id = i.id_estudiante

            INNER JOIN programa p
                ON p.id = i.id_programa

            LEFT JOIN siget_externa.ext_sedes se
                ON se.id_sede = p.id_sede_externa

            LEFT JOIN titulacion_postulaciones tp
                ON tp.id_inscripcion = i.id

            LEFT JOIN (
                SELECT
                    ja1.id_postulacion,
                    ja1.id_docente
                FROM jurado_asignaciones ja1
                INNER JOIN (
                    SELECT
                        id_postulacion,
                        MAX(id) AS id_ultimo
                    FROM jurado_asignaciones
                    WHERE UPPER(TRIM(COALESCE(estado, ''))) IN ('ASIGNADO', 'ACTIVO')
                    GROUP BY id_postulacion
                ) ja_ultimo
                    ON ja_ultimo.id_ultimo = ja1.id
            ) ja
                ON ja.id_postulacion = tp.id

            LEFT JOIN usuarios uj
                ON uj.id = ja.id_docente

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
            ) rf
                ON rf.id_estudiante = i.id_estudiante
                AND rf.id_programa = i.id_programa

            LEFT JOIN (
                SELECT
                    tu.id_estudiante,
                    pfc.id_programa,

                    MAX(
                        CASE
                            WHEN f.numero_fase = 1 THEN tu.id
                            ELSE NULL
                        END
                    ) AS id_trabajo_f1,

                    MAX(
                        CASE
                            WHEN f.numero_fase = 2 THEN tu.id
                            ELSE NULL
                        END
                    ) AS id_trabajo_f2,

                    MAX(
                        CASE
                            WHEN f.numero_fase = 3 THEN tu.id
                            ELSE NULL
                        END
                    ) AS id_trabajo_f3,

                    MAX(
                        CASE
                            WHEN f.numero_fase = 1 THEN tu.estado_aprobacion
                            ELSE NULL
                        END
                    ) AS estado_f1,

                    MAX(
                        CASE
                            WHEN f.numero_fase = 2 THEN tu.estado_aprobacion
                            ELSE NULL
                        END
                    ) AS estado_f2,

                    MAX(
                        CASE
                            WHEN f.numero_fase = 3 THEN tu.estado_aprobacion
                            ELSE NULL
                        END
                    ) AS estado_f3

                FROM trabajos tu

                INNER JOIN (
                    SELECT
                        id_estudiante,
                        id_configuracion,
                        MAX(id) AS id_ultimo
                    FROM trabajos
                    GROUP BY
                        id_estudiante,
                        id_configuracion
                ) ultimo_trabajo
                    ON ultimo_trabajo.id_ultimo = tu.id

                INNER JOIN programa_fase_config pfc
                    ON pfc.id = tu.id_configuracion

                INNER JOIN fases f
                    ON f.id = pfc.id_fase

                GROUP BY
                    tu.id_estudiante,
                    pfc.id_programa
            ) rt
                ON rt.id_estudiante = i.id_estudiante
                AND rt.id_programa = i.id_programa
        ) AS base
    ";
}

function condicionesProcesos(array $filtros): array
{
    $condiciones = [];
    $parametros = [];

    if ($filtros['sede'] > 0) {
        $condiciones[] = 'proceso.id_sede_externa = :sede';
        $parametros[':sede'] = $filtros['sede'];
    }

    if ($filtros['programa'] > 0) {
        $condiciones[] = 'proceso.id_programa = :programa';
        $parametros[':programa'] = $filtros['programa'];
    }

    if ($filtros['tipo'] !== '') {
        $condiciones[] = 'proceso.tipo_programa = :tipo';
        $parametros[':tipo'] = $filtros['tipo'];
    }

    if ($filtros['gestion'] !== '') {
        $condiciones[] = 'CAST(proceso.gestion_externa AS CHAR) = :gestion';
        $parametros[':gestion'] = $filtros['gestion'];
    }

    if ($filtros['version'] !== '') {
        $condiciones[] = 'CAST(proceso.version_programa_externa AS CHAR) = :version';
        $parametros[':version'] = $filtros['version'];
    }

    if ($filtros['fase'] > 0) {
        $condiciones[] = 'proceso.fase_actual = :fase';
        $parametros[':fase'] = $filtros['fase'];
    }

    if ($filtros['estado'] !== '') {
        $condiciones[] = 'proceso.estado_proceso = :estado';
        $parametros[':estado'] = $filtros['estado'];
    }

    if ($filtros['buscar'] !== '') {
        $condiciones[] = "
            CONCAT_WS(
                ' ',
                proceso.estudiante,
                proceso.ci,
                proceso.codigo_participante_externo,
                proceso.usuario,
                proceso.nombre_programa,
                proceso.codigo_programa_externo
            ) LIKE :buscar
        ";

        $parametros[':buscar'] = '%' . $filtros['buscar'] . '%';
    }

    return [$condiciones, $parametros];
}

function catalogosProcesos(PDO $db, array $filtros): array
{
    $sqlProgramas = "
        SELECT
            p.id,
            p.id_sede_externa,
            p.nombre_programa,
            p.codigo_programa_externo,
            UPPER(TRIM(COALESCE(p.tipo, ''))) AS tipo_programa,
            p.gestion_externa,
            p.version_programa_externa
        FROM programa p
        WHERE 1 = 1
    ";

    $params = [];

    if ($filtros['sede'] > 0) {
        $sqlProgramas .= " AND p.id_sede_externa = :sede";
        $params[':sede'] = $filtros['sede'];
    }

    if ($filtros['tipo'] !== '') {
        $sqlProgramas .= " AND UPPER(TRIM(COALESCE(p.tipo, ''))) = :tipo";
        $params[':tipo'] = $filtros['tipo'];
    }

    $sqlProgramas .= "
        ORDER BY
            p.nombre_programa ASC,
            p.gestion_externa DESC,
            p.version_programa_externa DESC
    ";

    $stmtProgramas = $db->prepare($sqlProgramas);

    foreach ($params as $clave => $valor) {
        $stmtProgramas->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmtProgramas->execute();

    $programas = $stmtProgramas->fetchAll(PDO::FETCH_ASSOC);

    $stmtSedes = $db->query("
        SELECT
            id_sede,
            nombre_sede,
            ciudad
        FROM siget_externa.ext_sedes
        WHERE COALESCE(estado, 1) = 1
        ORDER BY nombre_sede ASC
    ");

    $sedes = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

    $stmtTipos = $db->query("
        SELECT DISTINCT
            UPPER(TRIM(COALESCE(tipo, ''))) AS tipo_programa
        FROM programa
        WHERE TRIM(COALESCE(tipo, '')) <> ''
        ORDER BY tipo_programa ASC
    ");

    $tipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

    $gestiones = [];
    $versiones = [];

    foreach ($programas as $programa) {
        $gestion = (string) ($programa['gestion_externa'] ?? '');
        $version = (string) ($programa['version_programa_externa'] ?? '');

        if ($gestion !== '') {
            $gestiones[$gestion] = $gestion;
        }

        if ($version !== '') {
            $versiones[$version] = $version;
        }
    }

    krsort($gestiones, SORT_NATURAL);
    krsort($versiones, SORT_NATURAL);

    return [
        'sedes' => $sedes,
        'tipos' => $tipos,
        'programas' => $programas,
        'gestiones' => array_values($gestiones),
        'versiones' => array_values($versiones),
    ];
}
// NUEVA FUNCION PARA QUE TRABAJE CNO MAESTRIAS 
function nombresFasesProceso(string $tipoPrograma): array
{
    if (stripos($tipoPrograma, 'MAESTRIA') !== false) {

        return [

            0 => 'Sin fase habilitada',

            1 => 'Recepción de documentos',

            2 => 'Revisión administrativa',

            3 => 'Revisión docente',

            4 => 'Revisión metodológica',

            5 => 'Predefensa',

            6 => 'Defensa final'

        ];
    }

    return [

        0 => 'Sin fase habilitada',

        1 => 'Propuesta inicial',

        2 => 'Monografía',

        3 => 'Evaluación final'

    ];
}


// FIN DE LA FUNCION
function enriquecerProceso(array $proceso): array
{
    $fase = (int) ($proceso['fase_actual'] ?? 0);
    $estado = strtoupper(trim((string) ($proceso['estado_proceso'] ?? '')));

    $nombresFase = [
        0 => 'Sin fase habilitada',
        1 => 'Fase 1 — Propuesta inicial',
        2 => 'Fase 2 — Monografía',
        3 => 'Fase 3 — Evaluación final',
    ];

    $etiquetasEstado = [
        'SIN_CONFIGURACION' => 'Sin configuración',
        'HABILITADA' => 'Habilitada',
        'BORRADOR' => 'Borrador',
        'EN_REVISION' => 'En revisión',
        'OBSERVADO' => 'Con observaciones',
        'CORREGIDO' => 'Versión corregida',
        'REVISADO' => 'Validado',
    ];

    $responsable = 'Administración Académica';
    $siguienteAccion = 'Revisar estado del proceso';

    if ($fase === 0) {
        $siguienteAccion = 'Configurar fases para el programa';
    }

    if ($fase === 1) {
        if ($estado === 'EN_REVISION') {
            $siguienteAccion = 'Revisar propuesta de Fase 1';
        } elseif ($estado === 'OBSERVADO') {
            $responsable = 'Participante';
            $siguienteAccion = 'Esperar corrección de propuesta';
        } elseif ($estado === 'REVISADO') {
            if (empty($proceso['id_jurado'])) {
                $siguienteAccion = 'Asignar jurado y habilitar Fase 2';
            } elseif ((int) $proceso['fase_2_habilitada'] !== 1) {
                $siguienteAccion = 'Habilitar Fase 2';
            } else {
                $responsable = 'Participante';
                $siguienteAccion = 'Esperar entrega de monografía';
            }
        } elseif ($estado === 'HABILITADA' || $estado === 'BORRADOR') {
            $responsable = 'Participante';
            $siguienteAccion = 'Esperar propuesta inicial';
        }
    }

    if ($fase === 2) {
        if ($estado === 'HABILITADA' || $estado === 'BORRADOR') {
            $responsable = 'Participante';
            $siguienteAccion = 'Esperar entrega de monografía';
        } elseif ($estado === 'EN_REVISION' || $estado === 'CORREGIDO') {
            $responsable = !empty($proceso['jurado_asignado'])
                ? $proceso['jurado_asignado']
                : 'Jurado asignado';
            $siguienteAccion = 'Revisar monografía';
        } elseif ($estado === 'OBSERVADO') {
            $siguienteAccion = 'Gestionar corrección y plazo';
        } elseif ($estado === 'REVISADO') {
            $siguienteAccion = 'Habilitar Fase 3';
        }
    }

    if ($fase === 3) {
        if ($estado === 'HABILITADA' || $estado === 'BORRADOR') {
            $responsable = 'Participante';
            $siguienteAccion = 'Esperar versión final';
        } elseif ($estado === 'EN_REVISION' || $estado === 'CORREGIDO') {
            $responsable = !empty($proceso['jurado_asignado'])
                ? $proceso['jurado_asignado']
                : 'Jurado asignado';
            $siguienteAccion = 'Emitir valoración final';
        } elseif ($estado === 'OBSERVADO') {
            $siguienteAccion = 'Gestionar corrección y plazo';
        } elseif ($estado === 'REVISADO') {
            $siguienteAccion = 'Registrar cierre y código de empaste';
        }
    }

    $proceso['fase_nombre'] = $nombresFase[$fase] ?? 'Fase no identificada';
    $proceso['estado_etiqueta'] = $etiquetasEstado[$estado] ?? 'Sin estado';
    $proceso['responsable_actual'] = $responsable;
    $proceso['siguiente_accion'] = $siguienteAccion;

    if (empty($proceso['jurado_asignado'])) {
        $proceso['jurado_asignado'] = 'Pendiente de asignación';
    }

    return $proceso;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    responderProcesos(false, 'Tu sesión finalizó. Ingresa nuevamente.');
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    http_response_code(403);
    responderProcesos(false, 'Acceso restringido.');
}

if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    responderProcesos(false, 'No se pudo establecer la conexión local.');
}

try {
    $db = $conexion;
    $filtros = filtrosProcesos();
    $modo = strtolower(textoFiltro($_GET['modo'] ?? 'listar', 20));

    if ($modo === 'catalogos') {
        responderProcesos(
            true,
            'Catálogos cargados correctamente.',
            [
                'catalogos' => catalogosProcesos($db, $filtros),
            ]
        );
    }

    [$condiciones, $parametros] = condicionesProcesos($filtros);

    $sqlBase = consultaBaseProcesos();
    $where = $condiciones
        ? ' WHERE ' . implode(' AND ', $condiciones)
        : '';

    $stmtTotal = $db->prepare("
        SELECT COUNT(*)
        FROM (
            $sqlBase
        ) AS proceso
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
    $total = (int) $stmtTotal->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / $filtros['por_pagina']));

    if ($filtros['pagina'] > $totalPaginas) {
        $filtros['pagina'] = $totalPaginas;
    }

    $offset = ($filtros['pagina'] - 1) * $filtros['por_pagina'];

    $stmtProcesos = $db->prepare("
        SELECT
            proceso.*
        FROM (
            $sqlBase
        ) AS proceso
        $where
        ORDER BY
            CASE proceso.estado_proceso
                WHEN 'EN_REVISION' THEN 1
                WHEN 'OBSERVADO' THEN 2
                WHEN 'CORREGIDO' THEN 3
                WHEN 'REVISADO' THEN 4
                WHEN 'HABILITADA' THEN 5
                WHEN 'BORRADOR' THEN 6
                ELSE 7
            END,
            proceso.nombre_sede ASC,
            proceso.nombre_programa ASC,
            proceso.estudiante ASC
        LIMIT :limite OFFSET :offset
    ");

    foreach ($parametros as $clave => $valor) {
        $stmtProcesos->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmtProcesos->bindValue(
        ':limite',
        $filtros['por_pagina'],
        PDO::PARAM_INT
    );

    $stmtProcesos->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $stmtProcesos->execute();

    $procesos = $stmtProcesos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($procesos as &$proceso) {
        $proceso = enriquecerProceso($proceso);
    }

    unset($proceso);

    responderProcesos(
        true,
        'Procesos cargados correctamente.',
        [
            'data' => $procesos,
            'paginacion' => [
                'pagina_actual' => $filtros['pagina'],
                'por_pagina' => $filtros['por_pagina'],
                'total_registros' => $total,
                'total_paginas' => $totalPaginas,
            ],
            'filtros' => $filtros,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);

    responderProcesos(
        false,
        'No fue posible cargar la bandeja de procesos de titulación.'
    );
}