<?php
require_once __DIR__ . '/_helpers.php';

$mensaje = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'crear') {
            $mensaje = crearReglamento($db, $usuarioId, $uploadDir);
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_reglamentos.css?v=1">

<section class="reglamentos-screen reglamentos-admin-vue" id="app-reglamento-form" v-cloak>
    <div class="reglamentos-shell">

        <div class="page-topbar">
            <div>
                <h1>Añadir reglamento</h1>
                <p>Registra un nuevo documento PDF para consulta estudiantil.</p>
            </div>

            <div class="header-actions">
                <a href="/SIGET_ESAM/index.php?page=admin/reglamentos" class="btn-secondary">← Volver</a>
                <a href="/SIGET_ESAM/index.php?page=admin/reglamentos/ver" class="btn-primary">Ver reglamentos</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert success"><?= h($mensaje) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="form-layout">
            <div class="form-info-card">
                <span class="hero-label dark">Nuevo documento</span>
                <h2>Sube un reglamento de forma rápida</h2>
                <p>
                    Desde esta sección podrás cargar el archivo PDF, registrar su título,
                    versión, tipo de programa y descripción.
                </p>

                <div class="mini-steps">
                    <div class="mini-step">
                        <strong>1</strong>
                        <span>Completa los datos del reglamento</span>
                    </div>
                    <div class="mini-step">
                        <strong>2</strong>
                        <span>Selecciona el archivo PDF</span>
                    </div>
                    <div class="mini-step">
                        <strong>3</strong>
                        <span>Guarda y publícalo para consulta</span>
                    </div>
                </div>
            </div>

            <div class="form-main-card">
                <form method="POST" enctype="multipart/form-data" class="form-reglamento">
                    <input type="hidden" name="accion" value="crear">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo de programa</label>
                            <select name="tipo_programa" v-model="tipo" required>
                                <option value="">Seleccionar</option>
                                <option value="GENERAL">General</option>
                                <option value="DIPLOMADO">Diplomado</option>
                                <option value="MAESTRIA">Maestría</option>
                                <option value="ESPECIALIDAD">Especialidad</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Versión</label>
                            <input type="text" name="version" v-model="version" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Título del documento</label>
                        <input
                            type="text"
                            name="titulo"
                            v-model="titulo"
                            placeholder="Ej: Reglamento del Participante"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea
                            name="descripcion"
                            v-model="descripcion"
                            rows="4"
                            placeholder="Descripción breve del reglamento"
                        ></textarea>
                    </div>

                    <div class="form-group">
                        <label>Archivo PDF</label>
                        <input type="file" name="archivo_pdf" accept="application/pdf" @change="capturarArchivo" required>
                        <small>{{ nombreArchivo || 'Solo se permiten archivos PDF de hasta 10 MB.' }}</small>
                    </div>

                    <div class="preview-card">
                        <span>Vista previa</span>
                        <strong>{{ titulo || 'Título del reglamento' }}</strong>
                        <p>{{ descripcion || 'Descripción breve del documento.' }}</p>
                        <small>{{ tipo || 'Tipo de programa' }} · Versión {{ version }}</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            Subir reglamento
                        </button>

                        <a href="/SIGET_ESAM/index.php?page=admin/reglamentos/ver" class="btn-secondary">
                            Ver reglamentos
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/reglamentos_form.js"></script>