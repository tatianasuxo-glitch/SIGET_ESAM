<?php
require_once __DIR__ . '/_helpers.php';

$reglamentos = obtenerReglamentos($db);

$total = count($reglamentos);
$activos = count(array_filter($reglamentos, function ($r) {
    return $r['estado'] === 'ACTIVO';
}));
$inactivos = $total - $activos;
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_reglamentos.css?v=1">

<script>
    window.REGLAMENTOS_RESUMEN = {
        total: <?= (int)$total ?>,
        activos: <?= (int)$activos ?>,
        inactivos: <?= (int)$inactivos ?>
    };
</script>

<section class="reglamentos-screen reglamentos-admin-vue" id="app-reglamentos-index" v-cloak>
    <div class="reglamentos-shell">

        <div class="reglamentos-hero">
            <div class="hero-content">
                <span class="hero-label">Gestión documental</span>

                <h1>Administración de Reglamentos</h1>

                <p>
                    Gestiona los documentos normativos visibles para los estudiantes,
                    de forma rápida, clara y ordenada.
                </p>
            </div>

            <div class="hero-stats">
                <div class="stat-box">
                    <strong>{{ resumen.total }}</strong>
                    <span>Total</span>
                </div>

                <div class="stat-box">
                    <strong>{{ resumen.activos }}</strong>
                    <span>Visibles</span>
                </div>

                <div class="stat-box">
                    <strong>{{ resumen.inactivos }}</strong>
                    <span>Ocultos</span>
                </div>
            </div>
        </div>

        <div class="reglamento-home-grid">
            <article class="reglamento-option-card" @click="ir('/SIGET_ESAM/index.php?page=admin/reglamentos/agregar')">
                <div class="option-icon option-blue">➕</div>

                <div class="option-body">
                    <h2>Agregar reglamento</h2>
                    <p>
                        Sube un nuevo reglamento en formato PDF y registra sus datos principales.
                    </p>
                </div>

                <div class="option-footer">
                    <button type="button" class="btn-primary">Ingresar</button>
                </div>
            </article>

            <article class="reglamento-option-card" @click="ir('/SIGET_ESAM/index.php?page=admin/reglamentos/ver')">
                <div class="option-icon option-gold">📄</div>

                <div class="option-body">
                    <h2>Ver reglamentos</h2>
                    <p>
                        Visualiza, busca, filtra y edita los reglamentos registrados.
                    </p>
                </div>

                <div class="option-footer">
                    <button type="button" class="btn-primary">Ingresar</button>
                </div>
            </article>
        </div>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/reglamentos_index.js"></script>