<?php

session_start();

require_once __DIR__ . "/../includes/functions.php";

if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? "") !== "administrador") {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php?page=admin/configuracion_fases/index");
    exit;
}

$idPrograma = $_POST["id_programa"] ?? "";
$idFase = $_POST["id_fase"] ?? "";
$gestion = trim($_POST["gestion"] ?? "");
$tipoTrabajo = trim($_POST["tipo_trabajo"] ?? "");
$fechaInicioEntrega = $_POST["fecha_inicio_entrega"] ?? "";
$fechaLimiteEntrega = $_POST["fecha_limite_entrega"] ?? "";
$fechaLimiteRevision = $_POST["fecha_limite_revision"] ?? "";
$fechaDevolucionObservaciones = $_POST["fecha_devolucion_observaciones"] ?? "";
$notaMinima = $_POST["nota_minima"] ?? 71;
$estado = $_POST["estado"] ?? "ACTIVO";
$requisitos = $_POST["requisitos"] ?? [];

if (
    empty($idPrograma) ||
    empty($idFase) ||
    empty($gestion) ||
    empty($tipoTrabajo) ||
    empty($fechaInicioEntrega) ||
    empty($fechaLimiteEntrega) ||
    empty($fechaLimiteRevision)
) {
    header("Location: ../index.php?page=admin/configuracion_fases/crear&error=datos_incompletos");
    exit;
}

/*
    Convertimos formato datetime-local:
    2026-06-01T08:00 → 2026-06-01 08:00:00
*/

function convertirFechaFormulario($fecha)
{
    if (empty($fecha)) {
        return null;
    }

    $fecha = str_replace("T", " ", $fecha);

    if (strlen($fecha) === 16) {
        $fecha .= ":00";
    }

    return $fecha;
}

$fechaInicioEntrega = convertirFechaFormulario($fechaInicioEntrega);
$fechaLimiteEntrega = convertirFechaFormulario($fechaLimiteEntrega);
$fechaLimiteRevision = convertirFechaFormulario($fechaLimiteRevision);
$fechaDevolucionObservaciones = convertirFechaFormulario($fechaDevolucionObservaciones);

/*
    Validamos duplicado activo.
*/

$existe = consultarUno("
    SELECT id
    FROM programa_fase_config
    WHERE id_programa = :id_programa
    AND id_fase = :id_fase
    AND gestion = :gestion
    AND estado = 'ACTIVO'
    LIMIT 1
", [
    ":id_programa" => $idPrograma,
    ":id_fase" => $idFase,
    ":gestion" => $gestion
]);

if ($existe && $estado === "ACTIVO") {
    header("Location: ../index.php?page=admin/configuracion_fases/crear&error=duplicado");
    exit;
}

/*
    Guardar configuración.
*/

ejecutarSQL("
    INSERT INTO programa_fase_config
    (
        id_programa,
        id_fase,
        gestion,
        tipo_trabajo,
        fecha_inicio_entrega,
        fecha_limite_entrega,
        fecha_limite_revision,
        fecha_devolucion_observaciones,
        nota_minima,
        estado,
        creado_por
    )
    VALUES
    (
        :id_programa,
        :id_fase,
        :gestion,
        :tipo_trabajo,
        :fecha_inicio_entrega,
        :fecha_limite_entrega,
        :fecha_limite_revision,
        :fecha_devolucion_observaciones,
        :nota_minima,
        :estado,
        :creado_por
    )
", [
    ":id_programa" => $idPrograma,
    ":id_fase" => $idFase,
    ":gestion" => $gestion,
    ":tipo_trabajo" => $tipoTrabajo,
    ":fecha_inicio_entrega" => $fechaInicioEntrega,
    ":fecha_limite_entrega" => $fechaLimiteEntrega,
    ":fecha_limite_revision" => $fechaLimiteRevision,
    ":fecha_devolucion_observaciones" => $fechaDevolucionObservaciones,
    ":nota_minima" => $notaMinima,
    ":estado" => $estado,
    ":creado_por" => $_SESSION["id"]
]);

$idConfiguracion = db()->lastInsertId();

/*
    Guardar requisitos.
*/

$orden = 1;

foreach ($requisitos as $requisito) {

    $nombre = trim($requisito["nombre"] ?? "");
    $descripcion = trim($requisito["descripcion"] ?? "");
    $obligatorio = isset($requisito["obligatorio"]) ? intval($requisito["obligatorio"]) : 1;

    if ($nombre === "") {
        continue;
    }

    ejecutarSQL("
        INSERT INTO fase_requisitos
        (
            id_configuracion,
            nombre_requisito,
            descripcion,
            obligatorio,
            orden,
            estado
        )
        VALUES
        (
            :id_configuracion,
            :nombre_requisito,
            :descripcion,
            :obligatorio,
            :orden,
            'ACTIVO'
        )
    ", [
        ":id_configuracion" => $idConfiguracion,
        ":nombre_requisito" => $nombre,
        ":descripcion" => $descripcion,
        ":obligatorio" => $obligatorio,
        ":orden" => $orden
    ]);

    $orden++;
}

header("Location: ../index.php?page=admin/configuracion_fases/index&success=configuracion_guardada");
exit;