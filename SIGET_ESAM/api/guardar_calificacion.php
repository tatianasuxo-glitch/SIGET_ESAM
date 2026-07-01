<?php

session_start();

require_once __DIR__ . "/../includes/functions.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit;
}

$entregaId = $_POST["entrega_id"] ?? "";
$estudianteId = $_POST["estudiante_id"] ?? "";
$faseId = $_POST["fase_id"] ?? "";
$evaluadorTipo = $_POST["evaluador_tipo"] ?? "";
$nota = isset($_POST["nota"]) ? floatval($_POST["nota"]) : null;
$estadoSeleccionado = $_POST["estado"] ?? "";
$comentario = trim($_POST["comentario"] ?? "");
$returnTo = $_POST["return_to"] ?? "";

if (
    empty($entregaId) ||
    empty($estudianteId) ||
    empty($faseId) ||
    empty($evaluadorTipo) ||
    $nota === null ||
    empty($estadoSeleccionado) ||
    empty($comentario)
) {
    header("Location: ../index.php?error=datos_incompletos");
    exit;
}

$rolSesion = $_SESSION["rol"] ?? "";

if (!in_array($rolSesion, ["administrador", "docente"])) {
    header("Location: ../index.php?error=sin_permiso");
    exit;
}

$trabajo = obtenerTrabajoPorId($entregaId);

if (!$trabajo) {
    header("Location: ../index.php?error=trabajo_no_encontrado");
    exit;
}

if ($estadoSeleccionado === "APROBADO" && $nota < 71) {
    $estadoFinal = "OBSERVADO";
} else {
    $estadoFinal = $estadoSeleccionado;
}

switch ($estadoFinal) {
    case "APROBADO":
        $estadoBD = "Aprobado";
        break;

    case "OBSERVADO":
        $estadoBD = "Observado";
        break;

    case "REPROBADO":
        $estadoBD = "Reprobado";
        break;

    default:
        $estadoBD = "En Revisión";
        break;
}

ejecutarSQL("
    UPDATE trabajos
    SET 
        estado_aprobacion = :estado,
        calificacion_final = :nota,
        comentario_revision = :comentario,
        fecha_revision = NOW()
    WHERE id = :id_trabajo
", [
    ":estado" => $estadoBD,
    ":nota" => $nota,
    ":comentario" => $comentario,
    ":id_trabajo" => $entregaId
]);

if (!empty($returnTo)) {

    $returnTo = preg_replace("/[^a-zA-Z0-9_\/-]/", "", $returnTo);

    header("Location: ../index.php?page=" . $returnTo . "&success=calificacion_guardada");
    exit;
}

if ($rolSesion === "administrador") {
    header("Location: ../index.php?page=admin/revisados/index&success=revision_guardada");
    exit;
}

if ($rolSesion === "docente") {
    header("Location: ../index.php?page=profesor/revisadas/index&success=evaluacion_guardada");
    exit;
}

header("Location: ../index.php");
exit;