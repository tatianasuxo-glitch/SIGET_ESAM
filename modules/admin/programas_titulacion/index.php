<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id'])) {
    header('Location: /SIGET_ESAM/login.php');
    exit;
}

if (strtolower((string) ($_SESSION['rol'] ?? '')) !== 'administrador') {
    ?>
    <section class="page-card">
        <h1>Acceso restringido</h1>
        <p>Esta sección está disponible únicamente para Administración Académica.</p>
    </section>
    <?php
    return;
}
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_programas_titulacion.css?v=1">

<section class="programas-titulacion-screen" id="app-programas-titulacion" v-cloak>

    <div class="programas-titulacion-shell">

        <section class="programas-titulacion-hero">
            <div>
                <span class="hero-etiqueta">Gestión académica</span>

                <h1>Programas de Titulación</h1>

                <p>
                    Ingresa por sede, tipo de programa, gestión y versión.
                    Desde cada grupo podrás revisar participantes, aplicar acciones
                    masivas y abrir expedientes individuales.
                </p>
            </div>

            <div class="hero-resumen">
                <div class="hero-card">
                    <strong>{{ paginacion.total_registros || 0 }}</strong>
                    <span>Programas encontrados</span>
                </div>

                <div class="hero-card">
                    <strong>{{ totalParticipantes }}</strong>
                    <span>Participantes visibles</span>
                </div>

                <div class="hero-card">
                    <strong>{{ totalFase3PorHabilitar }}</strong>
                    <span>Listos para Fase 3</span>
                </div>
            </div>
        </section>

        <section class="programas-panel">

            <div class="panel-header">
                <div>
                    <h2>Grupos académicos</h2>
                    <p>
                        Selecciona un programa para gestionar a sus participantes
                        como grupo, sin perder el detalle individual.
                    </p>
                </div>

                <button
                    type="button"
                    class="btn-secondary"
                    @click="limpiarFiltros"
                >
                    Limpiar filtros
                </button>
            </div>

            <div class="filtros-programas">

                <div class="form-group">
                    <label>Sede</label>

                    <select v-model="filtros.sede" @change="cargarProgramas(1)">
                        <option value="">Todas las sedes</option>

                        <option
                            v-for="sede in catalogos.sedes"
                            :key="sede.id_sede"
                            :value="String(sede.id_sede)"
                        >
                            {{ sede.nombre_sede }}
                            {{ sede.ciudad ? ' — ' + sede.ciudad : '' }}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de programa</label>

                    <select v-model="filtros.tipo" @change="cargarProgramas(1)">
                        <option value="">Todos los tipos</option>

                        <option
                            v-for="tipo in catalogos.tipos"
                            :key="tipo.tipo_programa"
                            :value="tipo.tipo_programa"
                        >
                            {{ textoTipo(tipo.tipo_programa) }}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Gestión</label>

                    <select v-model="filtros.gestion" @change="cargarProgramas(1)">
                        <option value="">Todas las gestiones</option>

                        <option
                            v-for="gestion in catalogos.gestiones"
                            :key="gestion.gestion"
                            :value="gestion.gestion"
                        >
                            {{ gestion.gestion }}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Versión</label>

                    <select v-model="filtros.version" @change="cargarProgramas(1)">
                        <option value="">Todas las versiones</option>

                        <option
                            v-for="version in catalogos.versiones"
                            :key="version.version"
                            :value="version.version"
                        >
                            Versión {{ version.version }}
                        </option>
                    </select>
                </div>

                <div class="form-group buscador-programas">
                    <label>Buscar programa</label>

                    <input
                        type="search"
                        v-model="filtros.buscar"
                        @keyup.enter="cargarProgramas(1)"
                        placeholder="Programa, código, sede o ciudad..."
                    >
                </div>

            </div>

            <div class="acciones-filtros">
                <button
                    type="button"
                    class="btn-primary"
                    @click="cargarProgramas(1)"
                    :disabled="cargando"
                >
                    {{ cargando ? 'Buscando...' : 'Buscar programas' }}
                </button>

                <select
                    v-model="filtros.por_pagina"
                    class="select-cantidad"
                    @change="cargarProgramas(1)"
                >
                    <option value="12">12 por página</option>
                    <option value="24">24 por página</option>
                    <option value="48">48 por página</option>
                </select>
            </div>

            <div v-if="cargando" class="empty-state">
                Cargando programas de titulación...
            </div>

            <div v-else-if="error" class="alert error">
                {{ error }}
            </div>

            <div v-else-if="programas.length === 0" class="empty-state">
                No existen programas que coincidan con los filtros seleccionados.
            </div>

            <div v-else class="programas-grid">

                <article
                    v-for="programa in programas"
                    :key="programa.id_programa"
                    class="programa-card"
                >
                    <div class="programa-card-header">
                        <div>
                            <span class="sede-label">
                                {{ programa.nombre_sede }}
                            </span>

                            <h3>{{ programa.nombre_programa }}</h3>

                            <p>
                                {{ textoTipo(programa.tipo_programa) }}
                                · Gestión {{ programa.gestion_externa || 'Sin gestión' }}
                                · Versión {{ programa.version_programa_externa || '-' }}
                            </p>
                        </div>

                        <span
                            class="configuracion-badge"
                            :class="claseConfiguracion(programa)"
                        >
                            {{ textoConfiguracion(programa) }}
                        </span>
                    </div>

                    <div class="programa-participantes">
                        <strong>{{ programa.total_participantes }}</strong>
                        <span>Participantes inscritos</span>
                    </div>

                    <div class="programa-metricas">

                        <div>
                            <strong>{{ programa.participantes_sin_proceso }}</strong>
                            <span>Sin iniciar</span>
                        </div>

                        <div>
                            <strong>{{ programa.fase_1_en_revision }}</strong>
                            <span>Fase 1</span>
                        </div>

                        <div>
                            <strong>{{ programa.fase_2_en_revision }}</strong>
                            <span>Fase 2</span>
                        </div>

                        <div>
                            <strong>{{ programa.fase_3_por_habilitar }}</strong>
                            <span>Fase 3</span>
                        </div>

                    </div>

                    <div class="programa-accion">
                        <span>Acción prioritaria</span>

                        <strong>
                            {{ programa.accion_principal.texto }}
                        </strong>

                        <small v-if="programa.accion_principal.cantidad > 0">
                            {{ programa.accion_principal.cantidad }}
                            participante(s) relacionado(s)
                        </small>
                    </div>

                    <button
                        type="button"
                        class="btn-view"
                        @click="abrirParticipantes(programa)"
                    >
                        Ver participantes →
                    </button>

                </article>

            </div>

            <div v-if="paginacion.total_registros > 0" class="paginacion">

                <span>
                    Página {{ paginacion.pagina_actual }}
                    de {{ paginacion.total_paginas }}
                    · {{ paginacion.total_registros }} programa(s)
                </span>

                <div class="paginacion-botones">
                    <button
                        type="button"
                        class="btn-secondary"
                        :disabled="paginacion.pagina_actual <= 1 || cargando"
                        @click="cargarProgramas(paginacion.pagina_actual - 1)"
                    >
                        ← Anterior
                    </button>

                    <button
                        type="button"
                        class="btn-secondary"
                        :disabled="paginacion.pagina_actual >= paginacion.total_paginas || cargando"
                        @click="cargarProgramas(paginacion.pagina_actual + 1)"
                    >
                        Siguiente →
                    </button>
                </div>

            </div>

        </section>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/programas_titulacion.js?v=99"></script>