<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Sincronización de diplomados
|--------------------------------------------------------------------------
| BD externa: siget_externa (solo lectura)
| BD interna: proyecto_la_paz (programas, usuarios, roles e inscripciones)
|--------------------------------------------------------------------------
*/

function respuestaSincronizacion(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function textoNormalizado(string $texto): string
{
    $texto = trim($texto);

    if (function_exists('mb_strtolower')) {
        $texto = mb_strtolower($texto, 'UTF-8');
    } else {
        $texto = strtolower($texto);
    }

    return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
}

function estadoCuentaInterno(?string $estadoRegistro): string
{
    /*
    | La cuenta interna depende de que el participante esté activo.
    | estado_acceso se conserva en inscripciones como dato institucional,
    | pero no bloquea por sí solo la elegibilidad de fases.
    */
    return strtoupper(trim((string) $estadoRegistro)) === 'ACTIVO'
        ? 'Activo'
        : 'Inactivo';
}

if (($_SESSION['rol'] ?? '') !== 'administrador') {
    respuestaSincronizacion(403, [
        'success' => false,
        'message' => 'No autorizado. Debes iniciar sesión como administrador.'
    ]);
}

$confirmar = strtoupper(trim((string) ($_REQUEST['confirmar'] ?? '')));

if ($confirmar !== 'SI') {
    respuestaSincronizacion(400, [
        'success' => false,
        'message' => 'Sincronización no ejecutada. Agrega ?confirmar=SI a la URL.'
    ]);
}

try {
    $dbLocal = siget_local();
    $dbExterna = siget_externa();

    $estadisticas = [
        'programas_creados' => 0,
        'programas_actualizados' => 0,
        'usuarios_creados' => 0,
        'usuarios_actualizados' => 0,
        'roles_estudiante_asignados' => 0,
        'inscripciones_creadas' => 0,
        'inscripciones_actualizadas' => 0,
        'participantes_omitidos' => 0,
        'inscripciones_omitidas' => 0,
        'advertencias' => []
    ];

    /*
    |--------------------------------------------------------------------------
    | 1. Rol interno Estudiante
    |--------------------------------------------------------------------------
    */

    $stmtRol = $dbLocal->prepare("\n        SELECT id\n        FROM rol\n        WHERE LOWER(nombre_rol) = 'estudiante'\n          AND estado = 1\n        LIMIT 1\n    ");
    $stmtRol->execute();

    $rolEstudiante = $stmtRol->fetch(PDO::FETCH_ASSOC);

    if (!$rolEstudiante) {
        throw new Exception('No existe un rol interno activo llamado estudiante.');
    }

    $idRolEstudiante = (int) $rolEstudiante['id'];

    /*
    |--------------------------------------------------------------------------
    | 2. Lectura de diplomados externos activos
    |--------------------------------------------------------------------------
    */

    $stmtProgramasExternos = $dbExterna->query("\n        SELECT\n            id_programa_externo,\n            codigo_programa,\n            nombre_programa,\n            tipo_programa,\n            gestion,\n            version_programa,\n            id_sede,\n            fecha_inicio,\n            fecha_fin,\n            estado_programa,\n            estado\n        FROM ext_programas\n        WHERE tipo_programa = 'DIPLOMADO'\n          AND estado = 1\n        ORDER BY nombre_programa ASC, gestion ASC, version_programa ASC\n    ");

    $programasExternos = $stmtProgramasExternos->fetchAll(PDO::FETCH_ASSOC);

    $dbLocal->beginTransaction();

    $buscarProgramaPorExterno = $dbLocal->prepare("\n        SELECT id\n        FROM programa\n        WHERE id_programa_externo = :id_programa_externo\n        LIMIT 1\n    ");

    $buscarProgramaPorNombre = $dbLocal->prepare("\n        SELECT id\n        FROM programa\n        WHERE id_programa_externo IS NULL\n          AND LOWER(TRIM(nombre_programa)) = LOWER(TRIM(:nombre_programa))\n          AND LOWER(TRIM(tipo)) = 'diplomado'\n        LIMIT 1\n    ");

    $insertarPrograma = $dbLocal->prepare("\n        INSERT INTO programa\n        (\n            nombre_programa,\n            tipo,\n            estado,\n            id_programa_externo,\n            codigo_programa_externo,\n            gestion_externa,\n            version_programa_externa,\n            id_sede_externa,\n            fecha_inicio_externa,\n            fecha_fin_externa,\n            estado_programa_externo,\n            sincronizado_el\n        )\n        VALUES\n        (\n            :nombre_programa,\n            'Diplomado',\n            :estado,\n            :id_programa_externo,\n            :codigo_programa_externo,\n            :gestion_externa,\n            :version_programa_externa,\n            :id_sede_externa,\n            :fecha_inicio_externa,\n            :fecha_fin_externa,\n            :estado_programa_externo,\n            NOW()\n        )\n    ");

    $actualizarPrograma = $dbLocal->prepare("\n        UPDATE programa\n        SET\n            nombre_programa = :nombre_programa,\n            tipo = 'Diplomado',\n            estado = :estado,\n            codigo_programa_externo = :codigo_programa_externo,\n            gestion_externa = :gestion_externa,\n            version_programa_externa = :version_programa_externa,\n            id_sede_externa = :id_sede_externa,\n            fecha_inicio_externa = :fecha_inicio_externa,\n            fecha_fin_externa = :fecha_fin_externa,\n            estado_programa_externo = :estado_programa_externo,\n            sincronizado_el = NOW()\n        WHERE id = :id\n    ");

    $programasInternosPorExterno = [];

    foreach ($programasExternos as $programaExterno) {
        $idProgramaExterno = (int) $programaExterno['id_programa_externo'];

        $buscarProgramaPorExterno->execute([
            ':id_programa_externo' => $idProgramaExterno
        ]);

        $programaInterno = $buscarProgramaPorExterno->fetch(PDO::FETCH_ASSOC);

        if (!$programaInterno) {
            $buscarProgramaPorNombre->execute([
                ':nombre_programa' => $programaExterno['nombre_programa']
            ]);

            $programaInterno = $buscarProgramaPorNombre->fetch(PDO::FETCH_ASSOC);
        }

        $datosPrograma = [
            ':nombre_programa' => $programaExterno['nombre_programa'],
            ':estado' => (int) $programaExterno['estado'] === 1 ? 1 : 0,
            ':codigo_programa_externo' => $programaExterno['codigo_programa'],
            ':gestion_externa' => $programaExterno['gestion'],
            ':version_programa_externa' => $programaExterno['version_programa'],
            ':id_sede_externa' => $programaExterno['id_sede'],
            ':fecha_inicio_externa' => $programaExterno['fecha_inicio'],
            ':fecha_fin_externa' => $programaExterno['fecha_fin'],
            ':estado_programa_externo' => $programaExterno['estado_programa']
        ];

        if ($programaInterno) {
            $idProgramaInterno = (int) $programaInterno['id'];
            $datosPrograma[':id'] = $idProgramaInterno;

            /* Si se encontró por nombre, se vincula formalmente con el ID externo. */
            $dbLocal->prepare("\n                UPDATE programa\n                SET id_programa_externo = :id_programa_externo\n                WHERE id = :id\n                  AND id_programa_externo IS NULL\n            ")->execute([
                ':id_programa_externo' => $idProgramaExterno,
                ':id' => $idProgramaInterno
            ]);

            $actualizarPrograma->execute($datosPrograma);
            $estadisticas['programas_actualizados']++;
        } else {
            $datosPrograma[':id_programa_externo'] = $idProgramaExterno;
            $insertarPrograma->execute($datosPrograma);
            $idProgramaInterno = (int) $dbLocal->lastInsertId();
            $estadisticas['programas_creados']++;
        }

        $programasInternosPorExterno[$idProgramaExterno] = $idProgramaInterno;
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Participantes externos con acceso institucional
    |--------------------------------------------------------------------------
    | Se sincronizan únicamente participantes activos que pertenecen a un
    | diplomado activo. El usuario interno se identifica por ID externo.
    | El CI queda como verificación y dato institucional visible.
    |--------------------------------------------------------------------------
    */

    $stmtParticipantesExternos = $dbExterna->query("\n        SELECT DISTINCT\n            p.id_participante_externo,\n            p.codigo_participante,\n            p.ci,\n            p.nombres,\n            p.apellido_paterno,\n            p.apellido_materno,\n            p.correo,\n            p.celular,\n            p.profesion,\n            p.estado_registro,\n            a.usuario,\n            a.contrasena_hash,\n            a.rol,\n            a.estado_acceso\n        FROM ext_participantes p\n        INNER JOIN ext_inscripciones i\n            ON i.id_participante_externo = p.id_participante_externo\n           AND i.estado = 1\n        INNER JOIN ext_programas pr\n            ON pr.id_programa_externo = i.id_programa_externo\n           AND pr.tipo_programa = 'DIPLOMADO'\n           AND pr.estado = 1\n        LEFT JOIN ext_usuarios_acceso a\n            ON a.id_participante_externo = p.id_participante_externo\n        WHERE p.estado_registro = 'ACTIVO'\n        ORDER BY p.id_participante_externo ASC\n    ");

    $participantesExternos = $stmtParticipantesExternos->fetchAll(PDO::FETCH_ASSOC);

    $buscarUsuarioPorExterno = $dbLocal->prepare("\n        SELECT id, usuario\n        FROM usuarios\n        WHERE id_participante_externo = :id_participante_externo\n        LIMIT 1\n    ");

    $buscarUsuarioPorNombreUsuario = $dbLocal->prepare("\n        SELECT id, id_participante_externo\n        FROM usuarios\n        WHERE usuario = :usuario\n        LIMIT 1\n    ");

    $insertarUsuario = $dbLocal->prepare("\n        INSERT INTO usuarios\n        (\n            usuario,\n            contrasena,\n            nombres,\n            apellido_paterno,\n            apellido_materno,\n            profesion_postgrado,\n            estado_cuenta,\n            id_participante_externo,\n            codigo_participante_externo,\n            ci,\n            correo,\n            celular,\n            sincronizado_el\n        )\n        VALUES\n        (\n            :usuario,\n            :contrasena,\n            :nombres,\n            :apellido_paterno,\n            :apellido_materno,\n            :profesion_postgrado,\n            :estado_cuenta,\n            :id_participante_externo,\n            :codigo_participante_externo,\n            :ci,\n            :correo,\n            :celular,\n            NOW()\n        )\n    ");

    $actualizarUsuario = $dbLocal->prepare("\n        UPDATE usuarios\n        SET\n            usuario = :usuario,\n            contrasena = :contrasena,\n            nombres = :nombres,\n            apellido_paterno = :apellido_paterno,\n            apellido_materno = :apellido_materno,\n            profesion_postgrado = :profesion_postgrado,\n            estado_cuenta = :estado_cuenta,\n            codigo_participante_externo = :codigo_participante_externo,\n            ci = :ci,\n            correo = :correo,\n            celular = :celular,\n            sincronizado_el = NOW()\n        WHERE id = :id\n    ");

    $asignarRolEstudiante = $dbLocal->prepare("\n        INSERT IGNORE INTO usuario_rol (id_usuario, id_role)\n        VALUES (:id_usuario, :id_role)\n    ");

    $usuariosInternosPorExterno = [];

    foreach ($participantesExternos as $participanteExterno) {
        $idParticipanteExterno = (int) $participanteExterno['id_participante_externo'];
        $usuarioExterno = trim((string) ($participanteExterno['usuario'] ?? ''));
        $contrasenaExterna = (string) ($participanteExterno['contrasena_hash'] ?? '');
        $rolExterno = strtoupper(trim((string) ($participanteExterno['rol'] ?? '')));

        if ($usuarioExterno === '' || $contrasenaExterna === '' || $rolExterno !== 'PARTICIPANTE') {
            $estadisticas['participantes_omitidos']++;
            $estadisticas['advertencias'][] = [
                'tipo' => 'participante_sin_acceso',
                'id_participante_externo' => $idParticipanteExterno,
                'detalle' => 'No se sincronizó porque no cuenta con acceso PARTICIPANTE válido en la base externa.'
            ];
            continue;
        }

        $buscarUsuarioPorExterno->execute([
            ':id_participante_externo' => $idParticipanteExterno
        ]);

        $usuarioInterno = $buscarUsuarioPorExterno->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioInterno) {
            /* Evita sobrescribir cuentas internas existentes con el mismo usuario. */
            $buscarUsuarioPorNombreUsuario->execute([
                ':usuario' => $usuarioExterno
            ]);

            $usuarioConMismoNombre = $buscarUsuarioPorNombreUsuario->fetch(PDO::FETCH_ASSOC);

            if ($usuarioConMismoNombre) {
                $estadisticas['participantes_omitidos']++;
                $estadisticas['advertencias'][] = [
                    'tipo' => 'conflicto_usuario',
                    'id_participante_externo' => $idParticipanteExterno,
                    'usuario' => $usuarioExterno,
                    'detalle' => 'Existe una cuenta interna con el mismo usuario y no se modificó.'
                ];
                continue;
            }
        }

        $datosUsuario = [
            ':usuario' => $usuarioExterno,
            ':contrasena' => $contrasenaExterna,
            ':nombres' => $participanteExterno['nombres'],
            ':apellido_paterno' => $participanteExterno['apellido_paterno'],
            ':apellido_materno' => $participanteExterno['apellido_materno'],
            ':profesion_postgrado' => $participanteExterno['profesion'],
            ':estado_cuenta' => estadoCuentaInterno($participanteExterno['estado_registro']),
            ':codigo_participante_externo' => $participanteExterno['codigo_participante'],
            ':ci' => $participanteExterno['ci'],
            ':correo' => $participanteExterno['correo'],
            ':celular' => $participanteExterno['celular']
        ];

        if ($usuarioInterno) {
            $idUsuarioInterno = (int) $usuarioInterno['id'];
            $datosUsuario[':id'] = $idUsuarioInterno;
            $actualizarUsuario->execute($datosUsuario);
            $estadisticas['usuarios_actualizados']++;
        } else {
            $datosUsuario[':id_participante_externo'] = $idParticipanteExterno;
            $insertarUsuario->execute($datosUsuario);
            $idUsuarioInterno = (int) $dbLocal->lastInsertId();
            $estadisticas['usuarios_creados']++;
        }

        $asignarRolEstudiante->execute([
            ':id_usuario' => $idUsuarioInterno,
            ':id_role' => $idRolEstudiante
        ]);

        if ($asignarRolEstudiante->rowCount() > 0) {
            $estadisticas['roles_estudiante_asignados']++;
        }

        $usuariosInternosPorExterno[$idParticipanteExterno] = $idUsuarioInterno;
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Inscripciones externas de diplomados
    |--------------------------------------------------------------------------
    */

    $stmtInscripcionesExternas = $dbExterna->query("\n        SELECT\n            i.id_inscripcion_externa,\n            i.id_programa_externo,\n            i.id_participante_externo,\n            i.fecha_inscripcion,\n            i.estado_cartera,\n            i.estado_academico,\n            i.estado_acceso,\n            i.observacion_cartera,\n            i.observacion_academica,\n            i.motivo_bloqueo,\n            i.estado\n        FROM ext_inscripciones i\n        INNER JOIN ext_programas pr\n            ON pr.id_programa_externo = i.id_programa_externo\n           AND pr.tipo_programa = 'DIPLOMADO'\n           AND pr.estado = 1\n        WHERE i.estado = 1\n        ORDER BY i.id_inscripcion_externa ASC\n    ");

    $inscripcionesExternas = $stmtInscripcionesExternas->fetchAll(PDO::FETCH_ASSOC);

    $buscarInscripcionPorExterno = $dbLocal->prepare("\n        SELECT id\n        FROM inscripciones\n        WHERE id_inscripcion_externa = :id_inscripcion_externa\n        LIMIT 1\n    ");

    $insertarInscripcion = $dbLocal->prepare("\n        INSERT INTO inscripciones\n        (\n            id_estudiante,\n            id_programa,\n            fecha_inscripcion,\n            estado_academico,\n            id_inscripcion_externa,\n            estado_cartera,\n            estado_acceso,\n            observacion_cartera,\n            observacion_academica,\n            motivo_bloqueo,\n            sincronizado_el\n        )\n        VALUES\n        (\n            :id_estudiante,\n            :id_programa,\n            :fecha_inscripcion,\n            :estado_academico,\n            :id_inscripcion_externa,\n            :estado_cartera,\n            :estado_acceso,\n            :observacion_cartera,\n            :observacion_academica,\n            :motivo_bloqueo,\n            NOW()\n        )\n    ");

    $actualizarInscripcion = $dbLocal->prepare("\n        UPDATE inscripciones\n        SET\n            id_estudiante = :id_estudiante,\n            id_programa = :id_programa,\n            fecha_inscripcion = :fecha_inscripcion,\n            estado_academico = :estado_academico,\n            estado_cartera = :estado_cartera,\n            estado_acceso = :estado_acceso,\n            observacion_cartera = :observacion_cartera,\n            observacion_academica = :observacion_academica,\n            motivo_bloqueo = :motivo_bloqueo,\n            sincronizado_el = NOW()\n        WHERE id = :id\n    ");

    foreach ($inscripcionesExternas as $inscripcionExterna) {
        $idInscripcionExterna = (int) $inscripcionExterna['id_inscripcion_externa'];
        $idProgramaExterno = (int) $inscripcionExterna['id_programa_externo'];
        $idParticipanteExterno = (int) $inscripcionExterna['id_participante_externo'];

        if (!isset($programasInternosPorExterno[$idProgramaExterno])) {
            $estadisticas['inscripciones_omitidas']++;
            $estadisticas['advertencias'][] = [
                'tipo' => 'programa_no_sincronizado',
                'id_inscripcion_externa' => $idInscripcionExterna,
                'detalle' => 'No se encontró el programa interno vinculado.'
            ];
            continue;
        }

        if (!isset($usuariosInternosPorExterno[$idParticipanteExterno])) {
            $estadisticas['inscripciones_omitidas']++;
            $estadisticas['advertencias'][] = [
                'tipo' => 'participante_no_sincronizado',
                'id_inscripcion_externa' => $idInscripcionExterna,
                'detalle' => 'No se encontró un usuario interno sincronizado para el participante.'
            ];
            continue;
        }

        $datosInscripcion = [
            ':id_estudiante' => $usuariosInternosPorExterno[$idParticipanteExterno],
            ':id_programa' => $programasInternosPorExterno[$idProgramaExterno],
            ':fecha_inscripcion' => $inscripcionExterna['fecha_inscripcion'],
            ':estado_academico' => $inscripcionExterna['estado_academico'],
            ':estado_cartera' => $inscripcionExterna['estado_cartera'],
            ':estado_acceso' => $inscripcionExterna['estado_acceso'],
            ':observacion_cartera' => $inscripcionExterna['observacion_cartera'],
            ':observacion_academica' => $inscripcionExterna['observacion_academica'],
            ':motivo_bloqueo' => $inscripcionExterna['motivo_bloqueo']
        ];

        $buscarInscripcionPorExterno->execute([
            ':id_inscripcion_externa' => $idInscripcionExterna
        ]);

        $inscripcionInterna = $buscarInscripcionPorExterno->fetch(PDO::FETCH_ASSOC);

        if ($inscripcionInterna) {
            $datosInscripcion[':id'] = (int) $inscripcionInterna['id'];
            $actualizarInscripcion->execute($datosInscripcion);
            $estadisticas['inscripciones_actualizadas']++;
        } else {
            $datosInscripcion[':id_inscripcion_externa'] = $idInscripcionExterna;
            $insertarInscripcion->execute($datosInscripcion);
            $estadisticas['inscripciones_creadas']++;
        }
    }

    $dbLocal->commit();

    respuestaSincronizacion(200, [
        'success' => true,
        'message' => 'Sincronización de diplomados ejecutada correctamente.',
        'estadisticas' => $estadisticas
    ]);

} catch (Throwable $e) {
    if (isset($dbLocal) && $dbLocal instanceof PDO && $dbLocal->inTransaction()) {
        $dbLocal->rollBack();
    }

    respuestaSincronizacion(500, [
        'success' => false,
        'message' => 'No se pudo ejecutar la sincronización.',
        'error' => $e->getMessage()
    ]);
}
