<?php
require_once __DIR__ . "/../../../includes/functions.php";

if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? "") !== "administrador") {
    header("Location: login.php");
    exit;
}

$programas = consultarTodos("
    SELECT id, nombre_programa, tipo
    FROM programa
    WHERE estado = 1
    ORDER BY nombre_programa ASC
");

$fases = consultarTodos("
    SELECT id, nombre_fase, numero_fase
    FROM fases
    WHERE estado = 1
    ORDER BY numero_fase ASC
");

$error = $_GET["error"] ?? "";
?>

<section class="revision-page">

    <a href="index.php?page=admin/configuracion_fases/index" class="back-link">
        ← Volver a Gestión de Fases
    </a>

    <div class="revision-header">
        <h1>Nueva Configuración de Fase</h1>
        <p>Registre fechas, tipo de trabajo, nota mínima y requisitos para habilitar entregas por programa.</p>
    </div>

    <?php if ($error): ?>
        <div class="revision-empty">
            <h2>No se pudo guardar la configuración</h2>

            <?php if ($error === "datos_incompletos"): ?>
                <p>Debe completar todos los campos obligatorios.</p>
            <?php elseif ($error === "duplicado"): ?>
                <p>Ya existe una configuración activa para ese programa, gestión y fase.</p>
            <?php else: ?>
                <p>Ocurrió un error al procesar la solicitud.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="revision-card">

        <form action="api/guardar_configuracion_fase.php" method="POST" class="revision-form">

            <div class="form-row">

                <div>
                    <label>Programa</label>

                    <select name="id_programa" required>
                        <option value="">Seleccione programa</option>

                        <?php foreach ($programas as $programa): ?>
                            <option value="<?php echo htmlspecialchars($programa["id"]); ?>">
                                <?php echo htmlspecialchars($programa["nombre_programa"]); ?>
                                -
                                <?php echo htmlspecialchars($programa["tipo"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Fase</label>

                    <select name="id_fase" required>
                        <option value="">Seleccione fase</option>

                        <?php foreach ($fases as $fase): ?>
                            <option value="<?php echo htmlspecialchars($fase["id"]); ?>">
                                <?php echo htmlspecialchars($fase["nombre_fase"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="form-row">

                <div>
                    <label>Gestión</label>

                    <input 
                        type="text" 
                        name="gestion" 
                        value="2026" 
                        placeholder="Ej: 2026"
                        required
                    >
                </div>

                <div>
                    <label>Tipo de trabajo</label>

                    <select name="tipo_trabajo" required>
                        <option value="">Seleccione</option>
                        <option value="Tesis">Tesis</option>
                        <option value="Monografía">Monografía</option>
                        <option value="Proyecto de grado">Proyecto de grado</option>
                    </select>
                </div>

            </div>

            <div class="form-row">

                <div>
                    <label>Fecha y hora inicio de entrega</label>

                    <input 
                        type="datetime-local" 
                        name="fecha_inicio_entrega" 
                        required
                    >
                </div>

                <div>
                    <label>Fecha y hora límite de entrega</label>

                    <input 
                        type="datetime-local" 
                        name="fecha_limite_entrega" 
                        required
                    >
                </div>

            </div>

            <div class="form-row">

                <div>
                    <label>Fecha límite de revisión</label>

                    <input 
                        type="datetime-local" 
                        name="fecha_limite_revision" 
                        required
                    >
                </div>

                <div>
                    <label>Fecha devolución de observaciones</label>

                    <input 
                        type="datetime-local" 
                        name="fecha_devolucion_observaciones"
                    >
                </div>

            </div>

            <div class="form-row">

                <div>
                    <label>Nota mínima</label>

                    <input 
                        type="number" 
                        name="nota_minima" 
                        min="0" 
                        max="100" 
                        step="0.01" 
                        value="71"
                        required
                    >
                </div>

                <div>
                    <label>Estado</label>

                    <select name="estado" required>
                        <option value="ACTIVO">Activo</option>
                        <option value="INACTIVO">Inactivo</option>
                    </select>
                </div>

            </div>

            <div class="revision-files">

                <h3>Requisitos de la fase</h3>

                <p>
                    Registre los documentos que el estudiante deberá subir para esta fase. Puede dejar vacíos los campos que no necesite.
                </p>

                <?php for ($i = 1; $i <= 6; $i++): ?>

                    <div class="requirement-admin-box">

                        <h4>Requisito <?php echo $i; ?></h4>

                        <div class="form-row">

                            <div>
                                <label>Nombre del requisito</label>

                                <input 
                                    type="text" 
                                    name="requisitos[<?php echo $i; ?>][nombre]" 
                                    placeholder="Ej: Carta de aceptación de tutor"
                                >
                            </div>

                            <div>
                                <label>Obligatorio</label>

                                <select name="requisitos[<?php echo $i; ?>][obligatorio]">
                                    <option value="1">Sí</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                        </div>

                        <label>Descripción</label>

                        <textarea 
                            name="requisitos[<?php echo $i; ?>][descripcion]" 
                            placeholder="Describa brevemente qué debe presentar el estudiante."
                        ></textarea>

                    </div>

                <?php endfor; ?>

            </div>

            <button type="submit">
                Guardar configuración
            </button>

        </form>

    </div>

</section>