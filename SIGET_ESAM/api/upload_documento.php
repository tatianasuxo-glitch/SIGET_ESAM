<?php

session_start();

require_once __DIR__ . "/../includes/functions.php";

if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? "") !== "estudiante") {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php?page=estudiante/dashboard");
    exit;
}

$estudiante = obtenerEstudianteActual();

if (!$estudiante) {
    header("Location: ../index.php?page=estudiante/dashboard&error=estudiante_no_encontrado");
    exit;
}

if (!$estudiante["habilitado_titulacion"]) {
    header("Location: ../index.php?page=estudiante/dashboard&error=no_habilitado");
    exit;
}

$estudianteId = $_SESSION["id"];

$programaId = $_POST["programa_id"] ?? $estudiante["programa_id"] ?? "";
$faseId = $_POST["fase_id"] ?? $_POST["id_fase"] ?? "";

if (empty($faseId)) {
    $faseAdministrativa = obtenerFasePorNumero(1);
    $faseId = $faseAdministrativa["id"] ?? "";
}

if (empty($programaId) || empty($faseId) || !isset($_FILES["documentos"])) {
    header("Location: ../index.php?page=estudiante/dashboard&error=datos_incompletos");
    exit;
}

/* =========================================================
   VALIDAR PROGRAMA Y FASE CONFIGURADA
========================================================= */

$programa = obtenerProgramaPorId($programaId);

if (!$programa) {
    header("Location: ../index.php?page=estudiante/dashboard&error=programa_no_encontrado");
    exit;
}

$seguimiento = calcularSeguimientoFases($estudiante, $programa);

$itemFase = null;

foreach ($seguimiento as $item) {
    if ((string)($item["fase"]["id"] ?? "") === (string)$faseId) {
        $itemFase = $item;
        break;
    }
}

if (!$itemFase) {
    header("Location: ../index.php?page=estudiante/dashboard&error=fase_no_encontrada");
    exit;
}

$configuracion = $itemFase["configuracion"] ?? null;
$estadoVista = $itemFase["estado_vista"] ?? "";
$puedeSubir = $itemFase["puede_subir"] ?? false;

if (!$configuracion) {
    header("Location: ../index.php?page=estudiante/dashboard&error=fase_no_configurada");
    exit;
}

$estaDentroDeFecha = fechaActualDentroDeRango(
    $configuracion["fecha_inicio_entrega"],
    $configuracion["fecha_limite_entrega"]
);

if (!$estaDentroDeFecha) {
    header("Location: ../index.php?page=estudiante/dashboard&error=fuera_de_fecha");
    exit;
}

if (!$puedeSubir) {

    if ($estadoVista === "BLOQUEADO") {
        header("Location: ../index.php?page=estudiante/dashboard&error=fase_bloqueada");
        exit;
    }

    if ($estadoVista === "EN_REVISION") {
        header("Location: ../index.php?page=estudiante/dashboard&error=ya_existe_envio");
        exit;
    }

    if ($estadoVista === "APROBADO") {
        header("Location: ../index.php?page=estudiante/dashboard&error=fase_aprobada");
        exit;
    }

    header("Location: ../index.php?page=estudiante/dashboard&error=fase_no_habilitada");
    exit;
}

/* =========================================================
   VALIDAR SI YA EXISTE ENTREGA
========================================================= */

$trabajoExistente = obtenerTrabajoEstudianteFase($estudianteId, $faseId);

if ($trabajoExistente) {
    $estadoActual = normalizarEstadoRevision($trabajoExistente["estado_aprobacion"] ?? "");

    if ($estadoActual === "EN_REVISION" || $estadoActual === "APROBADO") {
        header("Location: ../index.php?page=estudiante/dashboard&error=ya_existe_envio");
        exit;
    }
}

/* =========================================================
   VALIDAR Y SUBIR ARCHIVOS
========================================================= */

$extensionesPermitidas = [
    "pdf",
    "doc",
    "docx",
    "ppt",
    "pptx",
    "xls",
    "xlsx",
    "jpg",
    "jpeg",
    "png",
    "zip"
];

$carpetaDestino = __DIR__ . "/../uploads/" . $estudianteId;

if (!is_dir($carpetaDestino)) {
    mkdir($carpetaDestino, 0777, true);
}

$archivosGuardados = [];

$nombres = $_FILES["documentos"]["name"];
$tmpNames = $_FILES["documentos"]["tmp_name"];
$errores = $_FILES["documentos"]["error"];

if (!is_array($nombres)) {
    $nombres = [$nombres];
    $tmpNames = [$tmpNames];
    $errores = [$errores];
}

$totalArchivos = count($nombres);

for ($i = 0; $i < $totalArchivos; $i++) {

    $nombreOriginal = $nombres[$i] ?? "";
    $tmpName = $tmpNames[$i] ?? "";
    $errorArchivo = $errores[$i] ?? UPLOAD_ERR_NO_FILE;

    if ($errorArchivo !== UPLOAD_ERR_OK) {
        continue;
    }

    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas)) {
        continue;
    }

    $nombreSeguro = preg_replace(
        "/[^a-zA-Z0-9_\.-]/",
        "_",
        $nombreOriginal
    );

    $nombreGuardado = time() . "_" . $i . "_" . $nombreSeguro;

    $rutaFisica = $carpetaDestino . "/" . $nombreGuardado;
    $rutaBD = "uploads/" . $estudianteId . "/" . $nombreGuardado;

    if (move_uploaded_file($tmpName, $rutaFisica)) {
        $archivosGuardados[] = [
            "nombre_original" => $nombreOriginal,
            "nombre_guardado" => $nombreGuardado,
            "ruta_fisica" => $rutaFisica,
            "ruta_bd" => $rutaBD,
            "tipo" => $extension
        ];
    }
}

if (count($archivosGuardados) === 0) {
    header("Location: ../index.php?page=estudiante/dashboard&error=sin_archivos_validos");
    exit;
}

/* =========================================================
   SI SUBE VARIOS ARCHIVOS, CREAR ZIP
========================================================= */

$rutaFinalBD = "";

if (count($archivosGuardados) === 1) {

    $rutaFinalBD = $archivosGuardados[0]["ruta_bd"];

} else {

    if (!class_exists("ZipArchive")) {
        header("Location: ../index.php?page=estudiante/dashboard&error=zip_no_disponible");
        exit;
    }

    $nombreZip = time() . "_documentacion_fase_" . $faseId . ".zip";
    $rutaZipFisica = $carpetaDestino . "/" . $nombreZip;
    $rutaZipBD = "uploads/" . $estudianteId . "/" . $nombreZip;

    $zip = new ZipArchive();

    if ($zip->open($rutaZipFisica, ZipArchive::CREATE) !== true) {
        header("Location: ../index.php?page=estudiante/dashboard&error=no_se_pudo_crear_zip");
        exit;
    }

    foreach ($archivosGuardados as $archivo) {
        $zip->addFile(
            $archivo["ruta_fisica"],
            $archivo["nombre_original"]
        );
    }

    $zip->close();

    $rutaFinalBD = $rutaZipBD;
}

/* =========================================================
   DEFINIR TÍTULO DEL TRABAJO
========================================================= */

$tituloTrabajo = trim($_POST["titulo_trabajo"] ?? "");

if ($tituloTrabajo === "") {
    $fase = obtenerFasePorId($faseId);
    $tituloTrabajo = $fase["nombre_fase"] ?? "Documento de titulación";
}

/* =========================================================
   GUARDAR EN SQL
   - Si existe trabajo Habilitado / Observado / Reprobado: actualiza.
   - Si no existe trabajo: crea nuevo.
========================================================= */

if ($trabajoExistente) {

    ejecutarSQL("
        UPDATE trabajos
        SET 
            titulo_trabajo = :titulo_trabajo,
            fecha_presentacion = NOW(),
            estado_aprobacion = 'En Revisión',
            calificacion_final = NULL,
            comentario_revision = NULL,
            fecha_revision = NULL,
            ruta_archivo = :ruta_archivo
        WHERE id = :id_trabajo
    ", [
        ":titulo_trabajo" => $tituloTrabajo,
        ":ruta_archivo" => $rutaFinalBD,
        ":id_trabajo" => $trabajoExistente["id"]
    ]);

} else {

    crearTrabajoEstudiante(
        $estudianteId,
        $faseId,
        $tituloTrabajo,
        $rutaFinalBD
    );

}

header("Location: ../index.php?page=estudiante/Seguimiento/index&success=upload");
exit;