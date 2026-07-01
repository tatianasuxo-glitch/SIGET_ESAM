<?php

require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json; charset=utf-8');

function tipoTrabajoSugeridoPorFase(int $numeroFase): string
{
    $tipos = [
        1 => 'Propuesta inicial',
        2 => 'Desarrollo del Trabajo Final',
        3 => 'Versión Final y Titulación'
    ];

    return $tipos[$numeroFase] ?? '';
}

function fechaFases(string $valor, string $campo, bool $obligatorio = true): ?DateTimeImmutable
{
    $valor = trim($valor);

    if ($valor === '') {
        if ($obligatorio) {
            throw new Exception("Debes completar: {$campo}.");
        }
        return null;
    }

    foreach (['Y-m-d\\TH:i', 'Y-m-d\\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $formato) {
        $fecha = DateTimeImmutable::createFromFormat($formato, $valor);
        $errores = DateTimeImmutable::getLastErrors();
        $sinErrores = $errores === false || ($errores['warning_count'] === 0 && $errores['error_count'] === 0);

        if ($fecha !== false && $sinErrores) {
            return $fecha;
        }
    }

    throw new Exception("La fecha de {$campo} no tiene un formato válido.");
}

try {
    $dbLocal = siget_local();
    $dbExterna = siget_externa();

    $id = (int) ($_POST['id'] ?? 0);
    $idPrograma = (int) ($_POST['id_programa'] ?? 0);
    $idFase = (int) ($_POST['id_fase'] ?? 0);
    $gestion = trim($_POST['gestion'] ?? '');
    $tipoTrabajo = trim($_POST['tipo_trabajo'] ?? '');
    $estado = trim($_POST['estado'] ?? 'ACTIVO');
    $notaMinima = trim($_POST['nota_minima'] ?? '71');

    if ($idPrograma <= 0) throw new Exception('Debes seleccionar un diplomado.');
    if ($idFase <= 0) throw new Exception('Debes seleccionar una fase.');
    if (!preg_match('/^\\d{4}$/', $gestion)) throw new Exception('La gestión debe tener cuatro dígitos. Ejemplo: 2026.');
    if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) throw new Exception('El estado seleccionado no es válido.');
    if (!is_numeric($notaMinima) || (float)$notaMinima < 0 || (float)$notaMinima > 100) {
        throw new Exception('La nota mínima debe estar entre 0 y 100.');
    }

    $inicio = fechaFases($_POST['fecha_inicio_entrega'] ?? '', 'Inicio de entrega');
    $limiteEntrega = fechaFases($_POST['fecha_limite_entrega'] ?? '', 'Límite de entrega');
    $limiteRevision = fechaFases($_POST['fecha_limite_revision'] ?? '', 'Límite de revisión');
    $devolucion = fechaFases($_POST['fecha_devolucion_observaciones'] ?? '', 'Devolución de observaciones', false);

    if ($inicio > $limiteEntrega) throw new Exception('El inicio de entrega no puede ser posterior al límite de entrega.');
    if ($limiteEntrega > $limiteRevision) throw new Exception('El límite de entrega no puede ser posterior al límite de revisión.');
    if ($devolucion !== null && $limiteRevision > $devolucion) {
        throw new Exception('La devolución de observaciones no puede ser anterior al límite de revisión.');
    }

    $stmtPrograma = $dbLocal->prepare("SELECT id, nombre_programa, tipo FROM programa WHERE id = :id AND estado = 1 LIMIT 1");
    $stmtPrograma->execute([':id' => $idPrograma]);
    $programa = $stmtPrograma->fetch(PDO::FETCH_ASSOC);

    if (!$programa) throw new Exception('El programa seleccionado no está disponible en la base interna.');
    if (stripos((string)$programa['tipo'], 'diplomado') === false) {
        throw new Exception('Este módulo solo permite configurar fases para diplomados.');
    }

    $stmtExterno = $dbExterna->prepare("
        SELECT id_programa_externo, codigo_programa, version_programa
        FROM ext_programas
        WHERE LOWER(TRIM(nombre_programa)) = LOWER(TRIM(:nombre))
          AND tipo_programa = 'DIPLOMADO'
          AND gestion = :gestion
          AND estado = 1
        LIMIT 1
    ");
    $stmtExterno->execute([':nombre' => $programa['nombre_programa'], ':gestion' => $gestion]);
    $programaExterno = $stmtExterno->fetch(PDO::FETCH_ASSOC);

    if (!$programaExterno) {
        throw new Exception('No se encontró un diplomado institucional activo con este programa y gestión.');
    }

    $stmtFase = $dbLocal->prepare("SELECT id, numero_fase FROM fases WHERE id = :id AND estado = 1 LIMIT 1");
    $stmtFase->execute([':id' => $idFase]);
    $fase = $stmtFase->fetch(PDO::FETCH_ASSOC);

    if (!$fase) throw new Exception('La fase seleccionada no está disponible.');
    if ($tipoTrabajo === '') $tipoTrabajo = tipoTrabajoSugeridoPorFase((int)$fase['numero_fase']);
    if ($tipoTrabajo === '') throw new Exception('Debes indicar el tipo de trabajo.');

    $stmtDuplicado = $dbLocal->prepare("
        SELECT id FROM programa_fase_config
        WHERE id_programa = :programa AND id_fase = :fase AND gestion = :gestion AND id <> :actual
        LIMIT 1
    ");
    $stmtDuplicado->execute([
        ':programa' => $idPrograma,
        ':fase' => $idFase,
        ':gestion' => $gestion,
        ':actual' => $id
    ]);

    if ($stmtDuplicado->fetch()) {
        throw new Exception('Ya existe una configuración para este diplomado, gestión y fase.');
    }

    $datos = [
        ':programa' => $idPrograma,
        ':fase' => $idFase,
        ':gestion' => $gestion,
        ':tipo' => $tipoTrabajo,
        ':inicio' => $inicio->format('Y-m-d H:i:s'),
        ':limite_entrega' => $limiteEntrega->format('Y-m-d H:i:s'),
        ':limite_revision' => $limiteRevision->format('Y-m-d H:i:s'),
        ':devolucion' => $devolucion?->format('Y-m-d H:i:s'),
        ':nota' => (float)$notaMinima,
        ':estado' => $estado
    ];

    $dbLocal->beginTransaction();

    if ($id <= 0) {
        $stmt = $dbLocal->prepare("
            INSERT INTO programa_fase_config (
                id_programa, id_fase, gestion, tipo_trabajo,
                fecha_inicio_entrega, fecha_limite_entrega, fecha_limite_revision,
                fecha_devolucion_observaciones, nota_minima, estado, creado_por,
                fecha_creacion, fecha_actualizacion
            ) VALUES (
                :programa, :fase, :gestion, :tipo,
                :inicio, :limite_entrega, :limite_revision,
                :devolucion, :nota, :estado, :creado_por,
                NOW(), NOW()
            )
        ");
        $datos[':creado_por'] = $_SESSION['id'] ?? null;
        $stmt->execute($datos);
        $mensaje = 'Configuración de fase creada correctamente.';
    } else {
        $stmtExiste = $dbLocal->prepare('SELECT id FROM programa_fase_config WHERE id = :id LIMIT 1');
        $stmtExiste->execute([':id' => $id]);
        if (!$stmtExiste->fetch()) throw new Exception('La configuración que intentas editar no existe.');

        $stmt = $dbLocal->prepare("
            UPDATE programa_fase_config SET
                id_programa = :programa,
                id_fase = :fase,
                gestion = :gestion,
                tipo_trabajo = :tipo,
                fecha_inicio_entrega = :inicio,
                fecha_limite_entrega = :limite_entrega,
                fecha_limite_revision = :limite_revision,
                fecha_devolucion_observaciones = :devolucion,
                nota_minima = :nota,
                estado = :estado,
                fecha_actualizacion = NOW()
            WHERE id = :id
        ");
        $datos[':id'] = $id;
        $stmt->execute($datos);
        $mensaje = 'Configuración de fase actualizada correctamente.';
    }

    $dbLocal->commit();

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'programa_externo' => [
            'id_programa_externo' => (int)$programaExterno['id_programa_externo'],
            'codigo_programa' => $programaExterno['codigo_programa'],
            'version_programa' => $programaExterno['version_programa']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (isset($dbLocal) && $dbLocal instanceof PDO && $dbLocal->inTransaction()) {
        $dbLocal->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar configuración.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
