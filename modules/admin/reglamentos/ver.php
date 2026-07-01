<?php
require_once __DIR__ . '/_helpers.php';

$mensaje = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'estado') {
            $mensaje = cambiarEstadoReglamento($db, $usuarioId);
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$reglamentos = obtenerReglamentos($db);
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_reglamentos.css?v=1">

<script>
    window.REGLAMENTOS_LISTA = <?= json_encode($reglamentos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<section class="page-card reglamentos-admin-vue" id="app-reglamentos-ver" v-cloak>
    <div class="panel-header">
        <div>
            <h1>Reglamentos registrados</h1>
            <p>Visualiza, busca, filtra y administra los reglamentos cargados.</p>
        </div>

        <div class="header-actions">
            <a href="/SIGET_ESAM/index.php?page=admin/reglamentos" class="btn-secondary">
                ← Volver
            </a>

            <a href="/SIGET_ESAM/index.php?page=admin/reglamentos/agregar" class="btn-primary">
                + Agregar reglamento
            </a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert success"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="reglamentos-toolbar">
        <div class="search-box">
            <input
                type="text"
                v-model="busqueda"
                placeholder="Buscar por título, descripción o archivo..."
            >
        </div>

        <select v-model="filtroTipo">
            <option value="">Todos los tipos</option>
            <option value="GENERAL">General</option>
            <option value="DIPLOMADO">Diplomado</option>
            <option value="MAESTRIA">Maestría</option>
            <option value="ESPECIALIDAD">Especialidad</option>
        </select>

        <select v-model="filtroEstado">
            <option value="">Todos los estados</option>
            <option value="ACTIVO">Visibles</option>
            <option value="INACTIVO">Ocultos</option>
        </select>
    </div>

    <div v-if="reglamentosFiltrados.length === 0" class="empty-state">
        No se encontraron reglamentos con los filtros seleccionados.
    </div>

    <div v-else class="reglamentos-grid">
        <article
            v-for="reglamento in reglamentosFiltrados"
            :key="reglamento.id"
            class="reglamento-card-vue"
        >
            <div class="reglamento-card-top">
                <span class="tipo-badge">{{ textoTipo(reglamento.tipo_programa) }}</span>

                <span
                    class="estado-badge"
                    :class="reglamento.estado === 'ACTIVO' ? 'activo' : 'inactivo'"
                >
                    {{ reglamento.estado === 'ACTIVO' ? 'Visible' : 'Oculto' }}
                </span>
            </div>

            <h3>{{ reglamento.titulo }}</h3>

            <p class="descripcion">
                {{ reglamento.descripcion || 'Sin descripción registrada.' }}
            </p>

            <div class="reglamento-meta">
                <div>
                    <span>Versión</span>
                    <strong>{{ reglamento.version || '1.0' }}</strong>
                </div>

                <div>
                    <span>Tamaño</span>
                    <strong>{{ formatoKB(reglamento.size_bytes) }}</strong>
                </div>

                <div>
                    <span>Archivo</span>
                    <strong>{{ reglamento.archivo_original }}</strong>
                </div>
            </div>

            <div class="reglamento-actions">
                <a
                    class="btn-view"
                    :href="rutaArchivo(reglamento.ruta_archivo)"
                    target="_blank"
                >
                    Ver PDF
                </a>

                <a
                    class="btn-primary"
                    :href="'/SIGET_ESAM/index.php?page=admin/reglamentos/editar&id=' + reglamento.id"
                >
                    Editar
                </a>

                <form method="POST" class="inline-form">
                    <input type="hidden" name="accion" value="estado">
                    <input type="hidden" name="id" :value="reglamento.id">
                    <input
                        type="hidden"
                        name="estado"
                        :value="reglamento.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'"
                    >

                    <button
                        type="submit"
                        :class="reglamento.estado === 'ACTIVO' ? 'btn-danger' : 'btn-secondary'"
                    >
                        {{ reglamento.estado === 'ACTIVO' ? 'Ocultar' : 'Mostrar' }}
                    </button>
                </form>
            </div>
        </article>
    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/reglamentos_ver.js"></script>