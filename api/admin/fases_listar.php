<?php

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Funciones auxiliares
|--------------------------------------------------------------------------
*/

function normalizarTextoFases(string $texto): string
{
    $texto = trim($texto);

    if (function_exists('mb_strtolower')) {
        $texto = mb_strtolower($texto, 'UTF-8');
    } else {
        $texto = strtolower($texto);
    }

    return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
}

/*function nombreFaseDiplomado(int $numeroFase, string $nombreOriginal): string
{
    switch ($numeroFase) {
        case 1:
            return 'Registro y Presentación de Propuesta';

        case 2:
            return 'Desarrollo del Trabajo Final';

        case 3:
            return 'Evaluación Final y Titulación';

        default:
            return $nombreOriginal;
    }
}*/
    /*
    |--------------------------------------------------------------------------
    | PROGRAMAS
    |--------------------------------------------------------------------------
    | FUNCION PARA EMPEZAR A TRABAJAR CON TODOS LOS PROGRAMAS DIP Y MAST
    |--------------------------------------------------------------------------
    */

    /*function obtenerNombreFase(array $fase): string
{
    return trim($fase['nombre_fase']);
}*/




try {
    /*
    |--------------------------------------------------------------------------
    | Conexiones
    |--------------------------------------------------------------------------
    | BD externa: solo lectura de información institucional.
    | BD interna: fases y configuraciones propias de SIGET.
    |--------------------------------------------------------------------------
    */

    $dbLocal = siget_local();
    $dbExterna = siget_externa();

    /*
    |--------------------------------------------------------------------------
    | 1. PROGRAMAS EXTERNOS: SOLO DIPLOMADOS ACTIVOS
    |--------------------------------------------------------------------------
    */

    $stmtExternos = $dbExterna->prepare("
        SELECT
            id_programa_externo,
            codigo_programa,
            nombre_programa,
            tipo_programa,
            gestion,
            version_programa,
            id_sede,
            fecha_inicio,
            fecha_fin,
            estado_programa,
            estado
        FROM ext_programas
        WHERE  estado = 1
        ORDER BY nombre_programa ASC, gestion DESC, version_programa ASC
    ");

    // impide funcionar con las maestrias
   /* $stmtExternos->execute([
        'tipo_programa' => 'DIPLOMADO'
    ]);*/
    
    $stmtExternos->execute();

    $programasExternos = $stmtExternos->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | 2. PROGRAMAS INTERNOS ACTIVOS
    |--------------------------------------------------------------------------
    | programa_fase_config necesita id_programa interno.
    | Por ahora el vínculo será:
    | nombre_programa externo = nombre_programa interno.
    |--------------------------------------------------------------------------
    */

    $stmtInternos = $dbLocal->query("
        SELECT
            id,
            nombre_programa,
            tipo,
            estado
        FROM programa
        WHERE estado = 1
        ORDER BY nombre_programa ASC
    ");

    $programasInternos = $stmtInternos->fetchAll(PDO::FETCH_ASSOC);

    $internosPorNombre = [];

    foreach ($programasInternos as $programaInterno) {

 

        //if ($tipoInterno !== 'diplomado') {
          //  continue;
       // }

       $clave = normalizarTextoFases($programaInterno['nombre_programa']);

        $internosPorNombre[$clave] = $programaInterno;
    }

    /*
    |--------------------------------------------------------------------------
    | 3. VINCULAR DIPLOMADOS EXTERNOS CON PROGRAMAS INTERNOS
    |--------------------------------------------------------------------------
    | Solo se mostrarán los diplomados que tengan equivalencia interna.
    | Así evitamos guardar una fase con un ID incorrecto.
    |--------------------------------------------------------------------------
    */

    $programas = [];
    $programasSinVinculo = [];

    foreach ($programasExternos as $programaExterno) {
        $clave = normalizarTextoFases($programaExterno['nombre_programa']);

        if (!isset($internosPorNombre[$clave])) {
            $programasSinVinculo[] = [
                'id_programa_externo' => (int) $programaExterno['id_programa_externo'],
                'codigo_programa' => $programaExterno['codigo_programa'],
                'nombre_programa' => $programaExterno['nombre_programa'],
                'gestion' => $programaExterno['gestion'],
                'version_programa' => $programaExterno['version_programa']
            ];

            continue;
        }

        $programaInterno = $internosPorNombre[$clave];

        $programas[] = [
            /*
            | id se mantiene como ID INTERNO porque este valor será
            | guardado posteriormente en programa_fase_config.id_programa.
            */
            'id' => (int) $programaInterno['id'],
            'id_programa_interno' => (int) $programaInterno['id'],
            'id_programa_externo' => (int) $programaExterno['id_programa_externo'],

            'codigo_programa' => $programaExterno['codigo_programa'],
            'nombre_programa' => $programaExterno['nombre_programa'],
            'tipo_programa' => $programaExterno['tipo_programa'],
            'tipo' => $programaInterno['tipo'],

            'gestion' => $programaExterno['gestion'],
            'version_programa' => $programaExterno['version_programa'],
            'id_sede' => $programaExterno['id_sede'],
            'fecha_inicio' => $programaExterno['fecha_inicio'],
            'fecha_fin' => $programaExterno['fecha_fin'],
            'estado_programa' => $programaExterno['estado_programa']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 4. FASES INTERNAS
    |--------------------------------------------------------------------------
    */

    $stmtFases = $dbLocal->query("
         SELECT
        id,
        nombre_fase,
        tipo_programa,
        numero_fase,
        descripcion,
        estado,
        calificacion_requerido
    FROM fases
    WHERE estado = 1
    ORDER BY
         tipo_programa ASC,
         numero_fase ASC
    ");

    $fases = $stmtFases->fetchAll(PDO::FETCH_ASSOC);

 /*   foreach ($fases as &$fase) {
        $fase['nombre_fase_bd'] = $fase['nombre_fase'];
        $fase['nombre_fase'] = nombreFaseDiplomado(
            (int) $fase['numero_fase'],
            $fase['nombre_fase']
        );
    }

    unset($fase);*/
// modificado para manejar todos los programas
    foreach ($fases as &$fase) {
    $fase['nombre_fase_bd'] = $fase['nombre_fase'];
    }
    unset($fase);

    /*
    |--------------------------------------------------------------------------
    | 5. CONFIGURACIONES YA GUARDADAS EN LA BD INTERNA
    |--------------------------------------------------------------------------
    */

    $sqlConfiguraciones = "
        SELECT
            c.id,
            c.id_programa,
            c.id_fase,
            c.gestion,
            c.tipo_trabajo,
            c.fecha_inicio_entrega,
            c.fecha_limite_entrega,
            c.fecha_limite_revision,
            c.fecha_devolucion_observaciones,
            c.nota_minima,
            c.estado,
            c.fecha_creacion,
            c.fecha_actualizacion,

            p.nombre_programa,
            p.tipo AS tipo_programa,

            f.nombre_fase,
            f.tipo_programa,
            f.numero_fase,

            COUNT(DISTINCT r.id) AS total_requisitos,
            COUNT(DISTINCT ec.id) AS total_estudiantes_configurados

        FROM programa_fase_config c
        INNER JOIN programa p
            ON p.id = c.id_programa
        INNER JOIN fases f
            ON f.id = c.id_fase
        LEFT JOIN fase_requisitos r
            ON r.id_configuracion = c.id
            AND r.estado = 'ACTIVO'
        LEFT JOIN fase_estudiante_config ec
            ON ec.id_configuracion = c.id
            AND ec.estado = 'ACTIVO'

        GROUP BY
            c.id,
            c.id_programa,
            c.id_fase,
            c.gestion,
            c.tipo_trabajo,
            c.fecha_inicio_entrega,
            c.fecha_limite_entrega,
            c.fecha_limite_revision,
            c.fecha_devolucion_observaciones,
            c.nota_minima,
            c.estado,
            c.fecha_creacion,
            c.fecha_actualizacion,
            p.nombre_programa,
          p.tipo,
            f.nombre_fase,
            f.tipo_programa,
            f.numero_fase

        ORDER BY c.fecha_creacion DESC
    ";

    $stmtConfiguraciones = $dbLocal->prepare($sqlConfiguraciones);
    $stmtConfiguraciones->execute();

    $configuraciones = $stmtConfiguraciones->fetchAll(PDO::FETCH_ASSOC);

    /*foreach ($configuraciones as &$configuracion) {
        $configuracion['nombre_fase_bd'] = $configuracion['nombre_fase'];
        $configuracion['nombre_fase'] = nombreFaseDiplomado(
            (int) $configuracion['numero_fase'],
            $configuracion['nombre_fase']
        );
    }

    unset($configuracion);*/

    foreach ($configuraciones as &$configuracion) {
    $configuracion['nombre_fase_bd'] = $configuracion['nombre_fase'];
    }
    unset($configuracion);

    /*
    |--------------------------------------------------------------------------
    | 6. ESTADÍSTICAS
    |--------------------------------------------------------------------------
    */

    $stats = [
        'total' => count($configuraciones),
        'activas' => 0,
        'inactivas' => 0,
        'diplomados' => 0,
        'maestrias' => 0,
        'programas_diplomado_disponibles' => count($programas),
        'programas_sin_vinculo_interno' => count($programasSinVinculo)
    ];

    $diplomadosConfigurados = [];
    $maestriasConfiguradas = [];

    foreach ($configuraciones as $configuracion) {
        if (($configuracion['estado'] ?? '') === 'ACTIVO') {
            $stats['activas']++;
        } else {
            $stats['inactivas']++;
        }

        $tipoPrograma = normalizarTextoFases($configuracion['tipo_programa'] ?? '');

        if (str_contains($tipoPrograma, 'diplomado')) {
            $diplomadosConfigurados[$configuracion['id_programa']] = true;
        }

        if (str_contains($tipoPrograma, 'maestr')) {
            $maestriasConfiguradas[$configuracion['id_programa']] = true;
        }
    }

    $stats['diplomados'] = count($diplomadosConfigurados);
    $stats['maestrias'] = count($maestriasConfiguradas);

    /*
    |--------------------------------------------------------------------------
    | RESPUESTA PARA VUE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'message' => 'Gestión de fases cargada correctamente.',
        'stats' => $stats,
        'programas' => $programas,
        'programas_sin_vinculo' => $programasSinVinculo,
        'fases' => $fases,
        'data' => $configuraciones
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar gestión de fases.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}