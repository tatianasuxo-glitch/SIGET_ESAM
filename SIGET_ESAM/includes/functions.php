<?php

require_once __DIR__ . "/../config/database.php";



function db()
{
    global $conexion;
    return $conexion;
}

function consultarUno($sql, $parametros = [])
{
    try {
        $db = siget_local();

        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("Error en consultarUno(): " . $e->getMessage());
    }
}

function consultarTodos($sql, $parametros = [])
{
    try {
        $db = siget_local();

        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("Error en consultarTodos(): " . $e->getMessage());
    }
}
 
function ejecutarSQL($sql, $params = [])
{
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}


function nombreCompletoUsuario($usuario)
{
    if (!$usuario) {
        return "";
    }

    return trim(
        ($usuario["nombres"] ?? "") . " " .
        ($usuario["apellido_paterno"] ?? "") . " " .
        ($usuario["apellido_materno"] ?? "")
    );
}

function normalizarRol($rol)
{
    return strtolower(trim($rol ?? ""));
}

function normalizarEstadoRevision($estado)
{
    $estado = strtolower(trim($estado ?? ""));

    if ($estado === "aprobado" || $estado === "aprobada") {
        return "APROBADO";
    }

    if ($estado === "observado" || $estado === "observada") {
        return "OBSERVADO";
    }

    if (
        $estado === "reprobado" ||
        $estado === "reprobada" ||
        $estado === "rechazado" ||
        $estado === "rechazada"
    ) {
        return "REPROBADO";
    }

    if ($estado === "habilitado" || $estado === "habilitada") {
        return "HABILITADO";
    }

    return "EN_REVISION";
}

function descripcionEstadoAcademico($estado)
{
    $estado = strtoupper(trim($estado ?? ""));

    if ($estado === "CONCLUIDO_APROBADO") {
        return "El estudiante culminó todos sus módulos satisfactoriamente y pasa al proceso de titulación.";
    }

    if ($estado === "EN_DESARROLLO_NIVELACION") {
        return "El estudiante aún tiene módulos o actividades pendientes y no puede pasar al proceso de titulación.";
    }

    if ($estado === "REPROBADO") {
        return "El estudiante no cumple las condiciones académicas para pasar al proceso de titulación.";
    }

    return "Estado académico pendiente de validación.";
}

function obtenerExtensionArchivo($ruta)
{
    if (!$ruta) {
        return "";
    }

    return strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
}

function obtenerNombreArchivo($ruta)
{
    if (!$ruta) {
        return "";
    }

    return basename($ruta);
}


function obtenerUsuarioPorId($idUsuario)
{
    return consultarUno("
        SELECT 
            u.*,
            r.id AS id_rol,
            r.nombre_rol
        FROM usuarios u
        INNER JOIN usuario_rol ur ON ur.id_usuario = u.id
        INNER JOIN rol r ON r.id = ur.id_role
        WHERE u.id = :id
        LIMIT 1
    ", [
        ":id" => $idUsuario
    ]);
}

function obtenerUsuarioActual()
{
    if (!isset($_SESSION["id"])) {
        return null;
    }

    return obtenerUsuarioPorId($_SESSION["id"]);
}

function obtenerUsuariosPorRol($nombreRol)
{
    return consultarTodos("
        SELECT 
            u.*,
            r.nombre_rol
        FROM usuarios u
        INNER JOIN usuario_rol ur ON ur.id_usuario = u.id
        INNER JOIN rol r ON r.id = ur.id_role
        WHERE LOWER(r.nombre_rol) = LOWER(:rol)
        ORDER BY u.nombres ASC, u.apellido_paterno ASC
    ", [
        ":rol" => $nombreRol
    ]);
}

function obtenerAdministradores()
{
    return obtenerUsuariosPorRol("administrador");
}

function obtenerDocentes()
{
    return obtenerUsuariosPorRol("docente");
}

function obtenerTutores()
{
    return obtenerUsuariosPorRol("tutor");
}



function obtenerEstudianteActual()
{
    if (!isset($_SESSION["id"])) {
        return null;
    }

    $idEstudiante = $_SESSION["id"];

    $data = consultarUno("
        SELECT 
            u.id AS id_estudiante,
            u.usuario,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno,
            u.profesion_postgrado,
            u.estado_cuenta,

            i.id AS id_inscripcion,
            i.id_programa,
            i.estado_academico,
            i.fecha_inscripcion,

            p.nombre_programa,
            p.tipo,
            p.estado AS estado_programa
        FROM usuarios u
        INNER JOIN inscripciones i ON i.id_estudiante = u.id
        INNER JOIN programa p ON p.id = i.id_programa
        WHERE u.id = :id_estudiante
        LIMIT 1
    ", [
        ":id_estudiante" => $idEstudiante
    ]);

    if (!$data) {
        return null;
    }

    $estadoAcademico = $data["estado_academico"] ?? "";
    $habilitado = strtoupper($estadoAcademico) === "CONCLUIDO_APROBADO";

    return [
        "id" => $data["id_estudiante"],
        "id_estudiante" => $data["id_estudiante"],
        "nombre" => nombreCompletoUsuario($data),
        "usuario" => $data["usuario"],
        "rol" => "estudiante",

        "programa_id" => $data["id_programa"],
        "id_programa" => $data["id_programa"],
        "titulo_programa" => $data["nombre_programa"],
        "tipo_programa" => $data["tipo"],

        "estado_academico" => $estadoAcademico,
        "descripcion_estado" => descripcionEstadoAcademico($estadoAcademico),
        "habilitado_titulacion" => $habilitado,
        "fecha_inscripcion" => $data["fecha_inscripcion"]
    ];
}

function obtenerProgramaPorId($programaId)
{
    $programa = consultarUno("
        SELECT *
        FROM programa
        WHERE id = :id
        LIMIT 1
    ", [
        ":id" => $programaId
    ]);

    if (!$programa) {
        return null;
    }

    $fases = obtenerFasesActivas();

    return [
        "id" => $programa["id"],
        "id_programa" => $programa["id"],
        "titulo" => $programa["nombre_programa"],
        "nombre_programa" => $programa["nombre_programa"],
        "tipo" => $programa["tipo"],
        "estado" => $programa["estado"],
        "fases" => $fases
    ];
}

function obtenerProgramaEstudiante($idEstudiante)
{
    return consultarUno("
        SELECT 
            p.*,
            i.estado_academico,
            i.fecha_inscripcion
        FROM inscripciones i
        INNER JOIN programa p ON p.id = i.id_programa
        WHERE i.id_estudiante = :id_estudiante
        LIMIT 1
    ", [
        ":id_estudiante" => $idEstudiante
    ]);
}


function obtenerFasesActivas()
{
    $fases = consultarTodos("
        SELECT *
        FROM fases
        WHERE estado = 1
        ORDER BY numero_fase ASC
    ");

    $resultado = [];

    foreach ($fases as $fase) {

        $numeroFase = intval($fase["numero_fase"]);

        $fechaLimiteEntrega = "Pendiente de asignación";
        $fechaLimiteRevision = "Pendiente de asignación";
        $fechaInicioEntrega = "Pendiente de asignación";

        if ($numeroFase === 1) {
            $fechaInicioEntrega = "Disponible";
            $fechaLimiteEntrega = "Según coordinación";
            $fechaLimiteRevision = "Según administración";
        }

        $resultado[] = [
            "id" => $fase["id"],
            "numero" => $fase["numero_fase"],
            "nombre" => $fase["nombre_fase"],
            "descripcion" => $fase["descripcion"],
            "nota_minima" => $fase["calificacion_requerido"] ?? 71,

            "fecha_inicio_entrega" => $fechaInicioEntrega,
            "fecha_limite_entrega" => $fechaLimiteEntrega,
            "fecha_limite_revision" => $fechaLimiteRevision
        ];
    }

    return $resultado;
}

function obtenerFasePorId($idFase)
{
    return consultarUno("
        SELECT *
        FROM fases
        WHERE id = :id
        LIMIT 1
    ", [
        ":id" => $idFase
    ]);
}

function obtenerFasePorNumero($numeroFase)
{
    return consultarUno("
        SELECT *
        FROM fases
        WHERE numero_fase = :numero
        LIMIT 1
    ", [
        ":numero" => $numeroFase
    ]);
}


function obtenerTrabajosEstudiante($idEstudiante)
{
    return consultarTodos("
        SELECT 
            t.*,
            f.nombre_fase,
            f.numero_fase,
            f.descripcion AS descripcion_fase,
            f.calificacion_requerido
        FROM trabajos t
        INNER JOIN fases f ON f.id = t.id_fase_actual
        WHERE t.id_estudiante = :id_estudiante
        ORDER BY f.numero_fase ASC, t.fecha_presentacion DESC
    ", [
        ":id_estudiante" => $idEstudiante
    ]);
}

function obtenerTrabajoEstudianteFase($idEstudiante, $idFase)
{
    return consultarUno("
        SELECT 
            t.*,
            f.nombre_fase,
            f.numero_fase,
            f.descripcion AS descripcion_fase,
            f.calificacion_requerido
        FROM trabajos t
        INNER JOIN fases f ON f.id = t.id_fase_actual
        WHERE t.id_estudiante = :id_estudiante
        AND t.id_fase_actual = :id_fase
        ORDER BY t.fecha_presentacion DESC
        LIMIT 1
    ", [
        ":id_estudiante" => $idEstudiante,
        ":id_fase" => $idFase
    ]);
}

function obtenerTrabajoPorId($idTrabajo)
{
    return consultarUno("
        SELECT 
            t.*,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno,
            f.nombre_fase,
            f.numero_fase,
            f.descripcion AS descripcion_fase,
            f.calificacion_requerido
        FROM trabajos t
        INNER JOIN usuarios u ON u.id = t.id_estudiante
        INNER JOIN fases f ON f.id = t.id_fase_actual
        WHERE t.id = :id
        LIMIT 1
    ", [
        ":id" => $idTrabajo
    ]);
}

function crearTrabajoEstudiante($idEstudiante, $idFase, $tituloTrabajo, $rutaArchivo)
{
    $sql = "
        INSERT INTO trabajos 
        (
            titulo_trabajo,
            id_estudiante,
            id_fase_actual,
            fecha_presentacion,
            estado_aprobacion,
            calificacion_final,
            ruta_archivo
        )
        VALUES
        (
            :titulo_trabajo,
            :id_estudiante,
            :id_fase_actual,
            NOW(),
            'En Revisión',
            NULL,
            :ruta_archivo
        )
    ";

    ejecutarSQL($sql, [
        ":titulo_trabajo" => $tituloTrabajo,
        ":id_estudiante" => $idEstudiante,
        ":id_fase_actual" => $idFase,
        ":ruta_archivo" => $rutaArchivo
    ]);

    return db()->lastInsertId();
}

function actualizarTrabajoRevision($idTrabajo, $estado, $nota = null, $comentario = "")
{
    return ejecutarSQL("
        UPDATE trabajos
        SET 
            estado_aprobacion = :estado,
            calificacion_final = :nota,
            comentario_revision = :comentario,
            fecha_revision = NOW()
        WHERE id = :id
    ", [
        ":estado" => $estado,
        ":nota" => $nota,
        ":comentario" => $comentario,
        ":id" => $idTrabajo
    ]);
}


function obtenerDocentesTrabajo($idTrabajo)
{
    return consultarTodos("
        SELECT 
            td.*,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno,
            u.usuario,
            r.nombre_rol
        FROM trabajo_docente td
        INNER JOIN usuarios u ON u.id = td.id_docente
        INNER JOIN usuario_rol ur ON ur.id_usuario = u.id
        INNER JOIN rol r ON r.id = ur.id_role
        WHERE td.id_trabajo = :id_trabajo
        ORDER BY td.fecha_asignacion ASC
    ", [
        ":id_trabajo" => $idTrabajo
    ]);
}

function asignarDocenteTrabajo($idTrabajo, $idDocente, $tipoAsignacion)
{
    return ejecutarSQL("
        INSERT INTO trabajo_docente
        (
            id_trabajo,
            id_docente,
            tipo_asignacion
        )
        VALUES
        (
            :id_trabajo,
            :id_docente,
            :tipo_asignacion
        )
    ", [
        ":id_trabajo" => $idTrabajo,
        ":id_docente" => $idDocente,
        ":tipo_asignacion" => $tipoAsignacion
    ]);
}

function obtenerResponsableFase($fase, $trabajo = null)
{
    $numeroFase = intval($fase["numero"] ?? $fase["numero_fase"] ?? 0);

    if ($numeroFase === 1) {
        $admin = consultarUno("
            SELECT u.*
            FROM usuarios u
            INNER JOIN usuario_rol ur ON ur.id_usuario = u.id
            INNER JOIN rol r ON r.id = ur.id_role
            WHERE LOWER(r.nombre_rol) = 'administrador'
            LIMIT 1
        ");

        return $admin ? nombreCompletoUsuario($admin) : "Administrador asignado";
    }

    if ($trabajo && isset($trabajo["id"])) {
        $docentes = obtenerDocentesTrabajo($trabajo["id"]);

        if (!empty($docentes)) {
            $nombres = [];

            foreach ($docentes as $docente) {
                $nombres[] = nombreCompletoUsuario($docente) . " (" . $docente["tipo_asignacion"] . ")";
            }

            return implode(", ", $nombres);
        }
    }

    return "Pendiente de asignación";
}


function trabajoAprobado($trabajo, $notaMinima = 71)
{
    if (!$trabajo) {
        return false;
    }

    $estado = normalizarEstadoRevision($trabajo["estado_aprobacion"] ?? "");
    $nota = $trabajo["calificacion_final"] ?? null;

    return $estado === "APROBADO" && $nota !== null && floatval($nota) >= floatval($notaMinima);
}

function calcularSeguimientoFases($estudiante, $programa = null)
{
    $fases = obtenerFasesActivas();
    $resultado = [];
    $faseAnteriorAprobada = true;

    foreach ($fases as $fase) {

        $configuracion = obtenerConfiguracionFaseParaEstudiante($estudiante, $fase["id"]);
        $requisitos = [];

        if ($configuracion) {
            $requisitos = obtenerRequisitosConfiguracion($configuracion["id"]);

            $fase["fecha_inicio_entrega"] = formatearFechaProceso($configuracion["fecha_inicio_entrega"]);
            $fase["fecha_limite_entrega"] = formatearFechaProceso($configuracion["fecha_limite_entrega"]);
            $fase["fecha_limite_revision"] = formatearFechaProceso($configuracion["fecha_limite_revision"]);
            $fase["fecha_devolucion_observaciones"] = formatearFechaProceso($configuracion["fecha_devolucion_observaciones"] ?? "");
            $fase["nota_minima"] = $configuracion["nota_minima"] ?? $fase["nota_minima"];
            $fase["tipo_trabajo"] = $configuracion["tipo_trabajo"];
            $fase["gestion"] = $configuracion["gestion"];
            $fase["configuracion_id"] = $configuracion["id"];
            $fase["tiene_configuracion"] = true;
        } else {
            $fase["fecha_inicio_entrega"] = "No configurada";
            $fase["fecha_limite_entrega"] = "No configurada";
            $fase["fecha_limite_revision"] = "No configurada";
            $fase["fecha_devolucion_observaciones"] = "No configurada";
            $fase["tipo_trabajo"] = "";
            $fase["gestion"] = obtenerGestionActivaEstudiante($estudiante);
            $fase["configuracion_id"] = null;
            $fase["tiene_configuracion"] = false;
        }

        $trabajo = obtenerTrabajoEstudianteFase($estudiante["id"], $fase["id"]);

        $estadoVista = "BLOQUEADO";
        $puedeSubir = false;
        $mensajeAccion = "";

        $faseConfigurada = $configuracion !== null;

        $dentroDeFecha = false;

        if ($configuracion) {
            $dentroDeFecha = fechaActualDentroDeRango(
                $configuracion["fecha_inicio_entrega"],
                $configuracion["fecha_limite_entrega"]
            );
        }

        if (!($estudiante["habilitado_titulacion"] ?? false)) {

            $estadoVista = "NO_HABILITADO";
            $mensajeAccion = "El estudiante aún no está habilitado para el proceso de titulación.";

        } elseif (!$faseConfigurada) {

            $estadoVista = "NO_CONFIGURADO";
            $mensajeAccion = "Administración aún no configuró las fechas y requisitos de esta fase.";

        } elseif (!$faseAnteriorAprobada) {

            $estadoVista = "BLOQUEADO";
            $mensajeAccion = "Debe aprobar la fase anterior para continuar.";

        } elseif (!$trabajo) {

            if ($dentroDeFecha) {
                $estadoVista = "HABILITADO";
                $puedeSubir = true;
                $mensajeAccion = "Puede subir el documento correspondiente desde el Dashboard.";
            } else {
                $estadoVista = "FUERA_DE_FECHA";
                $mensajeAccion = "La fase está configurada, pero actualmente está fuera del periodo de entrega.";
            }

        } else {

            $estadoTrabajo = normalizarEstadoRevision($trabajo["estado_aprobacion"] ?? "");

            if ($estadoTrabajo === "APROBADO") {

                $estadoVista = "APROBADO";
                $mensajeAccion = "Esta fase fue aprobada. Puede continuar cuando la siguiente fase esté habilitada.";

            } elseif ($estadoTrabajo === "OBSERVADO") {

                $estadoVista = "OBSERVADO";

                if ($dentroDeFecha) {
                    $puedeSubir = true;
                    $mensajeAccion = "La entrega fue observada. Puede subir una corrección dentro del periodo habilitado.";
                } else {
                    $mensajeAccion = "La entrega fue observada, pero el periodo de corrección no está habilitado.";
                }

            } elseif ($estadoTrabajo === "REPROBADO") {

                $estadoVista = "REPROBADO";
                $mensajeAccion = "La fase no fue aprobada. Debe comunicarse con coordinación académica.";

            } elseif ($estadoTrabajo === "HABILITADO") {

                if ($dentroDeFecha) {
                    $estadoVista = "HABILITADO";
                    $puedeSubir = true;
                    $mensajeAccion = "La fase está habilitada. Puede subir su documento desde el Dashboard.";
                } else {
                    $estadoVista = "FUERA_DE_FECHA";
                    $mensajeAccion = "La fase fue habilitada, pero está fuera del periodo de entrega configurado.";
                }

            } else {

                $estadoVista = "EN_REVISION";
                $mensajeAccion = "Su documento fue enviado correctamente. Debe esperar la revisión del responsable.";
            }
        }

        $calificacion = null;

        if ($trabajo && $trabajo["calificacion_final"] !== null) {
            $calificacion = [
                "nota" => $trabajo["calificacion_final"],
                "estado" => normalizarEstadoRevision($trabajo["estado_aprobacion"]),
                "comentario" => $trabajo["comentario_revision"] ?? "",
                "fecha_calificacion" => $trabajo["fecha_revision"] ?? ""
            ];
        }

        $entrega = null;

        if ($trabajo && !empty($trabajo["ruta_archivo"])) {
            $entrega = [
                "id" => $trabajo["id"],
                "estudiante_id" => $trabajo["id_estudiante"],
                "fase_id" => $trabajo["id_fase_actual"],
                "fecha_envio" => $trabajo["fecha_presentacion"],
                "estado_entrega" => "ENTREGADO",
                "estado_revision" => normalizarEstadoRevision($trabajo["estado_aprobacion"]),
                "archivos" => [
                    [
                        "nombre_original" => obtenerNombreArchivo($trabajo["ruta_archivo"]),
                        "nombre_guardado" => obtenerNombreArchivo($trabajo["ruta_archivo"]),
                        "ruta" => $trabajo["ruta_archivo"],
                        "tipo" => obtenerExtensionArchivo($trabajo["ruta_archivo"])
                    ]
                ]
            ];
        } elseif ($trabajo) {
            $entrega = [
                "id" => $trabajo["id"],
                "estudiante_id" => $trabajo["id_estudiante"],
                "fase_id" => $trabajo["id_fase_actual"],
                "fecha_envio" => $trabajo["fecha_presentacion"],
                "estado_entrega" => "PENDIENTE",
                "estado_revision" => normalizarEstadoRevision($trabajo["estado_aprobacion"]),
                "archivos" => []
            ];
        }

        $resultado[] = [
            "fase" => $fase,
            "configuracion" => $configuracion,
            "requisitos" => $requisitos,
            "responsable" => obtenerResponsableFase($fase, $trabajo),
            "trabajo" => $trabajo,
            "entrega" => $entrega,
            "calificacion" => $calificacion,
            "estado_vista" => $estadoVista,
            "puede_subir" => $puedeSubir,
            "mensaje_accion" => $mensajeAccion
        ];

        $faseAnteriorAprobada = trabajoAprobado($trabajo, $fase["nota_minima"] ?? 71);
    }

    return $resultado;
}
/* =========================================================
   COMPATIBILIDAD CON PANTALLAS ANTERIORES
========================================================= */

function obtenerEntregasEstudiante($estudianteId)
{
    $trabajos = obtenerTrabajosEstudiante($estudianteId);
    $programa = obtenerProgramaEstudiante($estudianteId);

    $entregas = [];

    foreach ($trabajos as $trabajo) {
        $entregas[] = [
            "id" => $trabajo["id"],
            "estudiante_id" => $trabajo["id_estudiante"],
            "programa_id" => $programa["id"] ?? null,
            "fase_id" => $trabajo["id_fase_actual"],
            "fecha_envio" => $trabajo["fecha_presentacion"],
            "estado_entrega" => $trabajo["ruta_archivo"] ? "ENTREGADO" : "PENDIENTE",
            "estado_revision" => normalizarEstadoRevision($trabajo["estado_aprobacion"]),
            "archivos" => $trabajo["ruta_archivo"] ? [
                [
                    "nombre_original" => obtenerNombreArchivo($trabajo["ruta_archivo"]),
                    "nombre_guardado" => obtenerNombreArchivo($trabajo["ruta_archivo"]),
                    "ruta" => $trabajo["ruta_archivo"],
                    "tipo" => obtenerExtensionArchivo($trabajo["ruta_archivo"])
                ]
            ] : []
        ];
    }

    return $entregas;
}

function obtenerCalificacionesEstudiante($estudianteId)
{
    $trabajos = obtenerTrabajosEstudiante($estudianteId);
    $programa = obtenerProgramaEstudiante($estudianteId);

    $calificaciones = [];

    foreach ($trabajos as $trabajo) {
        if ($trabajo["calificacion_final"] !== null) {
            $calificaciones[] = [
                "id" => "CAL-" . $trabajo["id"],
                "entrega_id" => $trabajo["id"],
                "estudiante_id" => $trabajo["id_estudiante"],
                "programa_id" => $programa["id"] ?? null,
                "fase_id" => $trabajo["id_fase_actual"],
                "evaluador_id" => null,
                "evaluador_tipo" => "",
                "nota" => $trabajo["calificacion_final"],
                "estado" => normalizarEstadoRevision($trabajo["estado_aprobacion"]),
                "comentario" => $trabajo["comentario_revision"] ?? "",
                "fecha_calificacion" => $trabajo["fecha_revision"] ?? ""
            ];
        }
    }

    return $calificaciones;
}

function buscarEntregaPorFase($entregas, $faseId)
{
    foreach ($entregas as $entrega) {
        if (($entrega["fase_id"] ?? "") == $faseId) {
            return $entrega;
        }
    }

    return null;
}

function buscarCalificacionPorFase($calificaciones, $faseId)
{
    foreach ($calificaciones as $calificacion) {
        if (($calificacion["fase_id"] ?? "") == $faseId) {
            return $calificacion;
        }
    }

    return null;
}

function faseAprobada($calificacion, $notaMinima)
{
    if (!$calificacion) {
        return false;
    }

    return (
        ($calificacion["estado"] ?? "") === "APROBADO" &&
        isset($calificacion["nota"]) &&
        floatval($calificacion["nota"]) >= floatval($notaMinima)
    );
}

function obtenerEstadoRevisionItem($item)
{
    if (isset($item["trabajo"])) {
        return normalizarEstadoRevision($item["trabajo"]["estado_aprobacion"] ?? "");
    }

    if (isset($item["entrega"]["estado_revision"])) {
        return normalizarEstadoRevision($item["entrega"]["estado_revision"]);
    }

    return "PENDIENTE";
}

/* =========================================================
   BANDEJAS DE REVISIÓN ADMIN / DOCENTE
========================================================= */

function obtenerItemsRevisionAdministrador($modo = "pendientes")
{
    $condicion = "LOWER(t.estado_aprobacion) = 'en revisión'";

    if ($modo === "revisados") {
        $condicion = "LOWER(t.estado_aprobacion) <> 'en revisión'";
    }

    $trabajos = consultarTodos("
        SELECT 
            t.*,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno,
            f.nombre_fase,
            f.numero_fase,
            f.descripcion AS descripcion_fase,
            f.calificacion_requerido,
            p.id AS id_programa,
            p.nombre_programa,
            p.tipo
        FROM trabajos t
        INNER JOIN usuarios u ON u.id = t.id_estudiante
        INNER JOIN fases f ON f.id = t.id_fase_actual
        INNER JOIN inscripciones i ON i.id_estudiante = u.id
        INNER JOIN programa p ON p.id = i.id_programa
        WHERE f.numero_fase = 1
        AND $condicion
        ORDER BY t.fecha_presentacion DESC
    ");

    return convertirTrabajosAItemsRevision($trabajos);
}

function obtenerItemsRevisionDocente($idDocente, $modo = "pendientes")
{
    $condicion = "LOWER(t.estado_aprobacion) = 'en revisión'";

    if ($modo === "revisados") {
        $condicion = "LOWER(t.estado_aprobacion) <> 'en revisión'";
    }

    $trabajos = consultarTodos("
        SELECT 
            t.*,
            u.nombres,
            u.apellido_paterno,
            u.apellido_materno,
            f.nombre_fase,
            f.numero_fase,
            f.descripcion AS descripcion_fase,
            f.calificacion_requerido,
            p.id AS id_programa,
            p.nombre_programa,
            p.tipo
        FROM trabajos t
        INNER JOIN trabajo_docente td ON td.id_trabajo = t.id
        INNER JOIN usuarios u ON u.id = t.id_estudiante
        INNER JOIN fases f ON f.id = t.id_fase_actual
        INNER JOIN inscripciones i ON i.id_estudiante = u.id
        INNER JOIN programa p ON p.id = i.id_programa
        WHERE td.id_docente = :id_docente
        AND $condicion
        ORDER BY t.fecha_presentacion DESC
    ", [
        ":id_docente" => $idDocente
    ]);

    return convertirTrabajosAItemsRevision($trabajos);
}

function convertirTrabajosAItemsRevision($trabajos)
{
    $items = [];

    foreach ($trabajos as $trabajo) {

        $fase = [
            "id" => $trabajo["id_fase_actual"],
            "numero" => $trabajo["numero_fase"],
            "nombre" => $trabajo["nombre_fase"],
            "descripcion" => $trabajo["descripcion_fase"],
            "nota_minima" => $trabajo["calificacion_requerido"] ?? 71
        ];

        $programa = [
            "id" => $trabajo["id_programa"],
            "titulo" => $trabajo["nombre_programa"],
            "tipo" => $trabajo["tipo"],
            "gestion" => "2026"
        ];

        $estudiante = [
            "id" => $trabajo["id_estudiante"],
            "nombre" => nombreCompletoUsuario($trabajo)
        ];

        $entrega = [
            "id" => $trabajo["id"],
            "estudiante_id" => $trabajo["id_estudiante"],
            "programa_id" => $trabajo["id_programa"],
            "fase_id" => $trabajo["id_fase_actual"],
            "fecha_envio" => $trabajo["fecha_presentacion"],
            "estado_entrega" => $trabajo["ruta_archivo"] ? "ENTREGADO" : "PENDIENTE",
            "estado_revision" => normalizarEstadoRevision($trabajo["estado_aprobacion"]),
            "archivos" => $trabajo["ruta_archivo"] ? [
                [
                    "nombre_original" => obtenerNombreArchivo($trabajo["ruta_archivo"]),
                    "nombre_guardado" => obtenerNombreArchivo($trabajo["ruta_archivo"]),
                    "ruta" => $trabajo["ruta_archivo"],
                    "tipo" => obtenerExtensionArchivo($trabajo["ruta_archivo"])
                ]
            ] : []
        ];

        $calificacion = null;

        if ($trabajo["calificacion_final"] !== null) {
            $calificacion = [
                "nota" => $trabajo["calificacion_final"],
                "estado" => normalizarEstadoRevision($trabajo["estado_aprobacion"]),
                "comentario" => $trabajo["comentario_revision"] ?? "",
                "fecha_calificacion" => $trabajo["fecha_revision"] ?? ""
            ];
        }

        $items[] = [
            "trabajo" => $trabajo,
            "entrega" => $entrega,
            "estudiante" => $estudiante,
            "programa" => $programa,
            "fase" => $fase,
            "calificacion" => $calificacion
        ];
    }

    return $items;
}

function obtenerItemsRevisionPorResponsable($responsableTipo, $responsableId, $modo = "pendientes")
{
    if ($responsableTipo === "administrador") {
        return obtenerItemsRevisionAdministrador($modo);
    }

    if ($responsableTipo === "docente") {
        return obtenerItemsRevisionDocente($responsableId, $modo);
    }

    return [];
}

function obtenerItemRevisionPorEntrega($entregaId, $responsableTipo, $responsableId)
{
    $items = array_merge(
        obtenerItemsRevisionPorResponsable($responsableTipo, $responsableId, "pendientes"),
        obtenerItemsRevisionPorResponsable($responsableTipo, $responsableId, "revisados")
    );

    foreach ($items as $item) {
        if (($item["entrega"]["id"] ?? "") == $entregaId) {
            return $item;
        }
    }

    return null;
}

/* =========================================================
   FILTROS
========================================================= */

function obtenerProgramasDesdeItems($items)
{
    $programas = [];

    foreach ($items as $item) {
        $programa = $item["programa"] ?? null;

        if ($programa && isset($programa["id"])) {
            $programas[$programa["id"]] = $programa;
        }
    }

    return array_values($programas);
}

function obtenerFasesDesdeItems($items)
{
    $fases = [];

    foreach ($items as $item) {
        $fase = $item["fase"] ?? null;

        if ($fase && isset($fase["id"])) {
            $fases[$fase["id"]] = $fase;
        }
    }

    return array_values($fases);
}

function obtenerGestionesDesdeItems($items)
{
    return ["2026"];
}

function aplicarFiltrosRevision($items, $filtros)
{
    return array_values(array_filter($items, function ($item) use ($filtros) {

        $q = strtolower(trim($filtros["q"] ?? ""));
        $programaId = $filtros["programa_id"] ?? "";
        $tipo = $filtros["tipo"] ?? "";
        $estado = $filtros["estado"] ?? "";
        $faseId = $filtros["fase_id"] ?? "";

        $nombreEstudiante = strtolower($item["estudiante"]["nombre"] ?? "");
        $tituloPrograma = strtolower($item["programa"]["titulo"] ?? "");
        $tipoPrograma = $item["programa"]["tipo"] ?? "";
        $estadoActual = obtenerEstadoRevisionItem($item);

        if ($q !== "") {
            if (
                strpos($nombreEstudiante, $q) === false &&
                strpos($tituloPrograma, $q) === false
            ) {
                return false;
            }
        }

        if ($programaId !== "" && ($item["programa"]["id"] ?? "") != $programaId) {
            return false;
        }

        if ($tipo !== "" && $tipoPrograma !== $tipo) {
            return false;
        }

        if ($estado !== "" && $estadoActual !== $estado) {
            return false;
        }

        if ($faseId !== "" && ($item["fase"]["id"] ?? "") != $faseId) {
            return false;
        }

        return true;
    }));
}


/* =========================================================
   CONFIGURACIÓN DE FASES POR PROGRAMA
========================================================= */

function obtenerGestionActivaEstudiante($estudiante)
{
    /*
        Por ahora usamos 2026 porque la tabla inscripciones actual
        no tiene columna gestion. Luego podemos agregarla si lo necesitas.
    */
    return "2026";
}

function obtenerConfiguracionFasePrograma($idPrograma, $idFase, $gestion = "2026")
{
    return consultarUno("
        SELECT 
            c.*,
            p.nombre_programa,
            p.tipo,
            f.nombre_fase,
            f.numero_fase,
            f.descripcion AS descripcion_fase
        FROM programa_fase_config c
        INNER JOIN programa p ON p.id = c.id_programa
        INNER JOIN fases f ON f.id = c.id_fase
        WHERE c.id_programa = :id_programa
        AND c.id_fase = :id_fase
        AND c.gestion = :gestion
        AND c.estado = 'ACTIVO'
        LIMIT 1
    ", [
        ":id_programa" => $idPrograma,
        ":id_fase" => $idFase,
        ":gestion" => $gestion
    ]);
}

function obtenerConfiguracionEspecialEstudiante($idConfiguracion, $idEstudiante)
{
    return consultarUno("
        SELECT *
        FROM fase_estudiante_config
        WHERE id_configuracion = :id_configuracion
        AND id_estudiante = :id_estudiante
        AND estado = 'ACTIVO'
        LIMIT 1
    ", [
        ":id_configuracion" => $idConfiguracion,
        ":id_estudiante" => $idEstudiante
    ]);
}

function obtenerConfiguracionFaseParaEstudiante($estudiante, $idFase)
{
    $idPrograma = $estudiante["programa_id"] ?? $estudiante["id_programa"] ?? null;
    $idEstudiante = $estudiante["id"] ?? $estudiante["id_estudiante"] ?? null;
    $gestion = obtenerGestionActivaEstudiante($estudiante);

    if (!$idPrograma || !$idEstudiante) {
        return null;
    }

    $config = obtenerConfiguracionFasePrograma($idPrograma, $idFase, $gestion);

    if (!$config) {
        return null;
    }

    $especial = obtenerConfiguracionEspecialEstudiante($config["id"], $idEstudiante);

    /*
        Si hay fechas especiales para el estudiante, reemplazan a las fechas generales.
    */
    if ($especial) {
        if (!empty($especial["fecha_inicio_entrega"])) {
            $config["fecha_inicio_entrega"] = $especial["fecha_inicio_entrega"];
        }

        if (!empty($especial["fecha_limite_entrega"])) {
            $config["fecha_limite_entrega"] = $especial["fecha_limite_entrega"];
        }

        if (!empty($especial["fecha_limite_revision"])) {
            $config["fecha_limite_revision"] = $especial["fecha_limite_revision"];
        }

        if (!empty($especial["fecha_devolucion_observaciones"])) {
            $config["fecha_devolucion_observaciones"] = $especial["fecha_devolucion_observaciones"];
        }

        $config["observacion_especial"] = $especial["observacion"] ?? "";
        $config["tiene_configuracion_especial"] = true;
    } else {
        $config["observacion_especial"] = "";
        $config["tiene_configuracion_especial"] = false;
    }

    return $config;
}

function obtenerRequisitosConfiguracion($idConfiguracion)
{
    return consultarTodos("
        SELECT *
        FROM fase_requisitos
        WHERE id_configuracion = :id_configuracion
        AND estado = 'ACTIVO'
        ORDER BY orden ASC, id ASC
    ", [
        ":id_configuracion" => $idConfiguracion
    ]);
}

function fechaActualDentroDeRango($fechaInicio, $fechaLimite)
{
    if (empty($fechaInicio) || empty($fechaLimite)) {
        return false;
    }

    $ahora = time();
    $inicio = strtotime($fechaInicio);
    $limite = strtotime($fechaLimite);

    if (!$inicio || !$limite) {
        return false;
    }

    return $ahora >= $inicio && $ahora <= $limite;
}

function formatearFechaProceso($fecha)
{
    if (empty($fecha)) {
        return "No configurada";
    }

    $timestamp = strtotime($fecha);

    if (!$timestamp) {
        return $fecha;
    }

    return date("Y-m-d H:i", $timestamp);
}