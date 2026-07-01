<?php
require_once __DIR__ . '/_helpers.php';

$mensaje = '';
$error = '';

$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    die("Reglamento no válido.");
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'actualizar_datos') {
            $mensaje = actualizarDatosReglamento($db, $usuarioId);
        }

        if ($accion === 'reemplazar') {
            $mensaje = reemplazarPdfReglamento($db, $usuarioId, $uploadDir);
        }

        if ($accion === 'estado') {
            $mensaje = cambiarEstadoReglamento($db, $usuarioId);
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$reglamento = obtenerReglamentoPorId($db, $id);

if (!$reglamento) {
    die("No se encontró el reglamento.");
}
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_reglamentos.css?v=1">

<script>
    window.REGLAMENTO_ACTUAL = <?= json_encode($reglamento, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<section class="reglamentos-screen reglamentos-admin-vue" id="app-reglamentos-index" v-cloak>
    <div class="reglamentos-shell">
    <div class="panel-header">
        <div>
            <h1>Editar reglamento</h1>
            <p>Modifica los datos, reemplaza el PDF o cambia su visibilidad.</p>
        </div>

        <div class="header-actions">
            <a href="/SIGET_ESAM/index.php?page=admin/reglamentos/ver" class="btn-secondary">
                ← Ver reglamentos
            </a>

            <a href="/SIGET_ESAM/<?= h($reglamento['ruta_archivo']) ?>" target="_blank" class="btn-view">
                Ver PDF
            </a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert success"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="edit-layout">
        <div class="edit-side">
            <span
                class="estado-badge <?= $reglamento['estado'] === 'ACTIVO' ? 'activo' : 'inactivo' ?>"
            >
                <?= $reglamento['estado'] === 'ACTIVO' ? 'Visible' : 'Oculto' ?>
            </span>

            <h2><?= h($reglamento['titulo']) ?></h2>

            <p><?= h($reglamento['descripcion'] ?: 'Sin descripción registrada.') ?></p>

            <small>
                Archivo: <?= h($reglamento['archivo_original']) ?><br>
                Tamaño: <?= number_format($reglamento['size_bytes'] / 1024, 2) ?> KB<br>
                Versión: <?= h($reglamento['version']) ?>
            </small>
        </div>

        <div class="edit-main">
            <div class="reglamentos-tabs">
                <button
                    type="button"
                    :class="{ activo: pestana === 'datos' }"
                    @click="pestana = 'datos'"
                >
                    Datos
                </button>

                <button
                    type="button"
                    :class="{ activo: pestana === 'pdf' }"
                    @click="pestana = 'pdf'"
                >
                    PDF
                </button>

                <button
                    type="button"
                    :class="{ activo: pestana === 'estado' }"
                    @click="pestana = 'estado'"
                >
                    Estado
                </button>
            </div>

            <form v-if="pestana === 'datos'" method="POST" class="form-reglamento">
                <input type="hidden" name="accion" value="actualizar_datos">
                <input type="hidden" name="id" value="<?= (int)$reglamento['id'] ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo_programa" required>
                            <option value="GENERAL" <?= $reglamento['tipo_programa'] === 'GENERAL' ? 'selected' : '' ?>>General</option>
                            <option value="DIPLOMADO" <?= $reglamento['tipo_programa'] === 'DIPLOMADO' ? 'selected' : '' ?>>Diplomado</option>
                            <option value="MAESTRIA" <?= $reglamento['tipo_programa'] === 'MAESTRIA' ? 'selected' : '' ?>>Maestría</option>
                            <option value="ESPECIALIDAD" <?= $reglamento['tipo_programa'] === 'ESPECIALIDAD' ? 'selected' : '' ?>>Especialidad</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Versión</label>
                        <input type="text" name="version" value="<?= h($reglamento['version']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Título</label>
                    <input type="text" name="titulo" value="<?= h($reglamento['titulo']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="4"><?= h($reglamento['descripcion']) ?></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    Guardar cambios
                </button>
            </form>

            <form v-if="pestana === 'pdf'" method="POST" enctype="multipart/form-data" class="form-reglamento">
                <input type="hidden" name="accion" value="reemplazar">
                <input type="hidden" name="id" value="<?= (int)$reglamento['id'] ?>">

                <div class="form-group">
                    <label>Nuevo PDF</label>
                    <input type="file" name="nuevo_pdf" accept="application/pdf" @change="capturarArchivo" required>
                    <small>{{ nombreArchivo || 'El archivo anterior será reemplazado.' }}</small>
                </div>

                <button type="submit" class="btn-primary">
                    Actualizar PDF
                </button>
            </form>

            <form v-if="pestana === 'estado'" method="POST" class="form-reglamento">
                <input type="hidden" name="accion" value="estado">
                <input type="hidden" name="id" value="<?= (int)$reglamento['id'] ?>">
                <input
                    type="hidden"
                    name="estado"
                    value="<?= $reglamento['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' ?>"
                >

                <p>
                    Estado actual:
                    <strong><?= $reglamento['estado'] === 'ACTIVO' ? 'Visible para estudiantes' : 'Oculto para estudiantes' ?></strong>
                </p>

                <button
                    type="submit"
                    class="<?= $reglamento['estado'] === 'ACTIVO' ? 'btn-danger' : 'btn-primary' ?>"
                >
                    <?= $reglamento['estado'] === 'ACTIVO' ? 'Ocultar reglamento' : 'Mostrar reglamento' ?>
                </button>
            </form>
        </div>
      </div>
    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/reglamentos_form.js"></script>