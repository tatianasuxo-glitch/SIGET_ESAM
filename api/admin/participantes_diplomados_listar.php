<?php

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function textoNormalizado(?string $valor): string
{
    return strtoupper(trim((string) $valor));
}

try {
    $dbLocal = siget_local();
    $dbExterna = siget_externa();

    /*
    |--------------------------------------------------------------------------
    | Participantes sincronizados de diplomados
    |--------------------------------------------------------------------------
    */

    $stmtParticipantes = $dbLocal->query("
        SELECT
            i.id AS id_inscripcion_interna,
            i.id_estudiante,
            i.id_programa,
            i.id_inscripcion_externa,
            i.estado_academico AS estado_academico_interno,
            i.estado_cartera AS estado_cartera_interno,
            i.estado_acceso AS estado_acceso_interno,
            i.observacion_cartera,
            i.observacion_academica,
            i.motivo_bloqueo,

            u.usuario,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno,
            u.ci,
            u.correo,
            u.celular,
            u.profesion_postgrado,
            u.id_participante_externo,
            u.codigo_participante_externo,
            u.estado_cuenta,

            p.nombre_programa,
            p.tipo AS tipo_programa,
            p.gestion_externa,
            p.version_programa_externa,
            p.id_programa_externo,
            p.estado_programa_externo

        FROM inscripciones i
        INNER JOIN usuarios u
            ON u.id = i.id_estudiante
        INNER JOIN programa p
            ON p.id = i.id_programa
        WHERE LOWER(p.tipo) LIKE '%diplomado%'
          AND i.id_inscripcion_externa IS NOT NULL
        ORDER BY
            p.nombre_programa ASC,
            u.apellido_paterno ASC,
            u.nombres ASC
    ");

    $participantesInternos = $stmtParticipantes->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Configuraciones activas de Fase 1
    |--------------------------------------------------------------------------
    */

    $stmtConfiguraciones = $dbLocal->query("
        SELECT
            c.id AS id_configuracion,
            c.id_programa,
            c.gestion,
            c.tipo_trabajo,
            c.fecha_inicio_entrega,
            c.fecha_limite_entrega,
            c.fecha_limite_revision,
            c.fecha_devolucion_observaciones,
            c.nota_minima,
            c.estado,
            f.numero_fase,
            f.nombre_fase
        FROM programa_fase_config c
        INNER JOIN fases f
            ON f.id = c.id_fase
        WHERE f.numero_fase = 1
          AND f.estado = 1
          AND c.estado = 'ACTIVO'
    ");

    $configuracionesFaseUno = $stmtConfiguraciones->fetchAll(PDO::FETCH_ASSOC);

    $configuracionPorProgramaGestion = [];

    foreach ($configuracionesFaseUno as $configuracion) {
        $clave = $configuracion['id_programa'] . '|' . $configuracion['gestion'];
        $configuracionPorProgramaGestion[$clave] = $configuracion;
    }

    /*
    |--------------------------------------------------------------------------
    | Habilitaciones ya registradas para Fase 1
    |--------------------------------------------------------------------------
    */

    $stmtHabilitaciones = $dbLocal->query("
        SELECT
            id,
            id_configuracion,
            id_estudiante,
            fecha_inicio_entrega,
            fecha_limite_entrega,
            fecha_limite_revision,
            fecha_devolucion_observaciones,
            estado,
            observacion,
            fecha_creacion
        FROM fase_estudiante_config
        ORDER BY id DESC
    ");

    $habilitaciones = $stmtHabilitaciones->fetchAll(PDO::FETCH_ASSOC);

    $habilitacionPorEstudianteConfig = [];

    foreach ($habilitaciones as $habilitacion) {
        $clave = $habilitacion['id_estudiante'] . '|' . $habilitacion['id_configuracion'];

        if (!isset($habilitacionPorEstudianteConfig[$clave])) {
            $habilitacionPorEstudianteConfig[$clave] = $habilitacion;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Datos institucionales actuales desde la BD externa
    |--------------------------------------------------------------------------
    */

    $stmtExternos = $dbExterna->query("
        SELECT
            ei.id_inscripcion_externa,
            ei.id_programa_externo,
            ei.id_participante_externo,
            ei.estado_cartera,
            ei.estado_academico,
            ei.estado_acceso,
            ei.observacion_cartera,
            ei.observacion_academica,
            ei.motivo_bloqueo,
            ei.estado AS estado_inscripcion_externa,

            ep.codigo_participante,
            ep.ci,
            ep.nombres,
            ep.apellido_paterno,
            ep.apellido_materno,
            ep.correo,
            ep.celular,
            ep.profesion,
            ep.estado_registro

        FROM ext_inscripciones ei
        INNER JOIN ext_participantes ep
            ON ep.id_participante_externo = ei.id_participante_externo
    ");

    $inscripcionesExternas = $stmtExternos->fetchAll(PDO::FETCH_ASSOC);

    $externaPorInscripcion = [];

    foreach ($inscripcionesExternas as $inscripcionExterna) {
        $externaPorInscripcion[
            (int) $inscripcionExterna['id_inscripcion_externa']
        ] = $inscripcionExterna;
    }

    /*
    |--------------------------------------------------------------------------
    | Consolidación y elegibilidad
    |--------------------------------------------------------------------------
    */

    $resultado = [];

    $stats = [
        'total' => 0,
        'habilitables' => 0,
        'ya_habilitados' => 0,
        'no_habilitables' => 0,
        'sin_configuracion_fase_1' => 0
    ];

    foreach ($participantesInternos as $participante) {
        $stats['total']++;

        $idInscripcionExterna = (int) $participante['id_inscripcion_externa'];

        $externa = $externaPorInscripcion[$idInscripcionExterna] ?? null;

        $estadoAcademico = textoNormalizado(
            $externa['estado_academico']
            ?? $participante['estado_academico_interno']
        );

        $estadoCartera = textoNormalizado(
            $externa['estado_cartera']
            ?? $participante['estado_cartera_interno']
        );

        $estadoAcceso = textoNormalizado(
            $externa['estado_acceso']
            ?? $participante['estado_acceso_interno']
        );

        $inscripcionExternaActiva = $externa !== null
            && (int) ($externa['estado_inscripcion_externa'] ?? 0) === 1;

        $gestion = (string) ($participante['gestion_externa'] ?? '');

        $claveConfiguracion = $participante['id_programa'] . '|' . $gestion;

        $configuracionFaseUno = $configuracionPorProgramaGestion[$claveConfiguracion] ?? null;

        $motivos = [];

        if ($externa === null) {
            $motivos[] = 'No se encontró la inscripción en la base externa.';
        }

        if (!$inscripcionExternaActiva) {
            $motivos[] = 'La inscripción externa no está activa.';
        }

        if ($estadoAcademico !== 'CONCLUIDO') {
            $motivos[] = 'Estado académico: ' . ($estadoAcademico ?: 'NO DEFINIDO') . '.';
        }

        if (!in_array($estadoCartera, ['VIGENTE', 'EXENTO_DE_DEUDA'], true)) {
            $motivos[] = 'Estado de cartera: ' . ($estadoCartera ?: 'NO DEFINIDO') . '.';
        }

        if (!$configuracionFaseUno) {
            $motivos[] = 'No existe una configuración activa de Fase 1 para este diplomado y gestión.';
            $stats['sin_configuracion_fase_1']++;
        }

        $esHabilitable = empty($motivos);

        $habilitacion = null;

        if ($configuracionFaseUno) {
            $claveHabilitacion = $participante['id_estudiante']
                . '|'
                . $configuracionFaseUno['id_configuracion'];

            $habilitacion = $habilitacionPorEstudianteConfig[$claveHabilitacion] ?? null;
        }

        $yaHabilitado = $habilitacion !== null
            && textoNormalizado($habilitacion['estado'] ?? '') === 'ACTIVO';

        if ($yaHabilitado) {
            $stats['ya_habilitados']++;
        } elseif ($esHabilitable) {
            $stats['habilitables']++;
        } else {
            $stats['no_habilitables']++;
        }

        $resultado[] = [
            'id_estudiante' => (int) $participante['id_estudiante'],
            'id_inscripcion_interna' => (int) $participante['id_inscripcion_interna'],
            'id_inscripcion_externa' => $idInscripcionExterna,

            'nombre_completo' => trim(
                ($participante['nombres'] ?? '') . ' ' .
                ($participante['apellido_paterno'] ?? '') . ' ' .
                ($participante['apellido_materno'] ?? '')
            ),

            'usuario' => $participante['usuario'],
            'ci' => $participante['ci'],
            'correo' => $participante['correo'],
            'celular' => $participante['celular'],
            'profesion' => $participante['profesion_postgrado'],
            'codigo_participante' => $participante['codigo_participante_externo'],

            'id_programa' => (int) $participante['id_programa'],
            'id_programa_externo' => (int) $participante['id_programa_externo'],
            'nombre_programa' => $participante['nombre_programa'],
            'gestion' => $gestion,
            'version_programa' => $participante['version_programa_externa'],
            'estado_programa' => $participante['estado_programa_externo'],

            'estado_academico' => $estadoAcademico,
            'estado_cartera' => $estadoCartera,
            'estado_acceso' => $estadoAcceso,

            'observacion_cartera' => $externa['observacion_cartera']
                ?? $participante['observacion_cartera'],

            'observacion_academica' => $externa['observacion_academica']
                ?? $participante['observacion_academica'],

            'motivo_bloqueo' => $externa['motivo_bloqueo']
                ?? $participante['motivo_bloqueo'],

            'es_habilitable' => $esHabilitable,
            'ya_habilitado' => $yaHabilitado,
            'puede_habilitar' => $esHabilitable && !$yaHabilitado,
            'motivos_no_habilitacion' => $motivos,

            'fase_1' => $configuracionFaseUno ? [
                'id_configuracion' => (int) $configuracionFaseUno['id_configuracion'],
                'nombre_fase' => 'Registro y Presentación de Propuesta',
                'tipo_trabajo' => $configuracionFaseUno['tipo_trabajo'],
                'fecha_inicio_entrega' => $configuracionFaseUno['fecha_inicio_entrega'],
                'fecha_limite_entrega' => $configuracionFaseUno['fecha_limite_entrega'],
                'fecha_limite_revision' => $configuracionFaseUno['fecha_limite_revision'],
                'fecha_devolucion_observaciones' => $configuracionFaseUno['fecha_devolucion_observaciones'],
                'nota_minima' => $configuracionFaseUno['nota_minima']
            ] : null,

            'habilitacion_fase_1' => $habilitacion
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Participantes de diplomados cargados correctamente.',
        'stats' => $stats,
        'data' => $resultado
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No se pudieron cargar los participantes de diplomados.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}