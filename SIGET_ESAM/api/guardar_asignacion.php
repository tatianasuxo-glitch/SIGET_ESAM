<?php

session_start();

require_once __DIR__ . "/../includes/functions.php";

if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? "") !== "administrador") {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit;
}

$trabajoOrigenId = $_POST["trabajo_origen_id"] ?? "";
$idEstudiante = $_POST["id_estudiante"] ?? "";
$idFaseSiguiente = $_POST["id_fase_siguiente"] ?? "";
$numeroFaseSiguiente = $_POST["numero_fase_siguiente"] ?? "";
$tipoAsignacion = $_POST["tipo_asignacion"] ?? "";
$tituloTrabajo = trim($_POST["titulo_trabajo"] ?? "");
$responsables = $_POST["responsables"] ?? [];

if (
    empty($trabajoOrigenId) ||
    empty($idEstudiante) ||
    empty($idFaseSiguiente) ||
    empty($tipoAsignacion) ||
    empty($tituloTrabajo) ||
    empty($responsables)
) {
    header("Location: ../index.php?page=admin/asignaciones/index&trabajo_id=" . urlencode($trabajoOrigenId) . "&error=datos_incompletos");
    exit;
}

$trabajoOrigen = obtenerTrabajoPorId($trabajoOrigenId);

if (!$trabajoOrigen) {
    header("Location: ../index.php?page=admin/revisados/index&error=trabajo_no_encontrado");
    exit;
}

$estadoOrigen = normalizarEstadoRevision($trabajoOrigen["estado_aprobacion"] ?? "");

if ($estadoOrigen !== "APROBADO") {
    header("Location: ../index.php?page=admin/revisados/detalle&entrega_id=" . urlencode($trabajoOrigenId) . "&error=no_aprobado");
    exit;
}

/*
    Buscamos si ya existe un trabajo habilitado para la siguiente fase.
    Si no existe, lo creamos como Habilitado.
    Esto permitirá que el estudiante vea la siguiente fase disponible para subir documento.
*/

$trabajoSiguiente = obtenerTrabajoEstudianteFase($idEstudiante, $idFaseSiguiente);

if (!$trabajoSiguiente) {

    ejecutarSQL("
        INSERT INTO trabajos
        (
            titulo_trabajo,
            id_estudiante,
            id_fase_actual,
            fecha_presentacion,
            estado_aprobacion,
            calificacion_final,
            comentario_revision,
            fecha_revision,
            ruta_archivo
        )
        VALUES
        (
            :titulo_trabajo,
            :id_estudiante,
            :id_fase_actual,
            NOW(),
            'Habilitado',
            NULL,
            NULL,
            NULL,
            ''
        )
    ", [
        ":titulo_trabajo" => $tituloTrabajo,
        ":id_estudiante" => $idEstudiante,
        ":id_fase_actual" => $idFaseSiguiente
    ]);

    $idTrabajoSiguiente = db()->lastInsertId();

} else {

    $idTrabajoSiguiente = $trabajoSiguiente["id"];

    ejecutarSQL("
        UPDATE trabajos
        SET titulo_trabajo = :titulo_trabajo
        WHERE id = :id_trabajo
    ", [
        ":titulo_trabajo" => $tituloTrabajo,
        ":id_trabajo" => $idTrabajoSiguiente
    ]);
}

/*
    Reemplazamos responsables anteriores de ese trabajo.
*/

ejecutarSQL("
    DELETE FROM trabajo_docente
    WHERE id_trabajo = :id_trabajo
", [
    ":id_trabajo" => $idTrabajoSiguiente
]);

$contador = 1;

foreach ($responsables as $idResponsable) {

    $idResponsable = intval($idResponsable);

    if ($idResponsable <= 0) {
        continue;
    }

    if ($tipoAsignacion === "Tribunal") {
        $funcion = "Tribunal " . $contador;
    } else {
        $funcion = $tipoAsignacion;
    }

    ejecutarSQL("
        INSERT INTO trabajo_docente
        (
            id_trabajo,
            id_docente,
            tipo_asignacion,
            fecha_asignacion
        )
        VALUES
        (
            :id_trabajo,
            :id_docente,
            :tipo_asignacion,
            NOW()
        )
    ", [
        ":id_trabajo" => $idTrabajoSiguiente,
        ":id_docente" => $idResponsable,
        ":tipo_asignacion" => $funcion
    ]);

    $contador++;
}

header("Location: ../index.php?page=admin/asignaciones/index&trabajo_id=" . urlencode($trabajoOrigenId) . "&success=asignacion_guardada");
exit;