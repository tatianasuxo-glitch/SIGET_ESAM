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

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_procesos.css?v=1">

<section class="procesos-screen" id="app-procesos-admin" v-cloak>

    <div class="procesos-shell">

        <div class="procesos-hero">
            <div>
                <span class="procesos-etiqueta">Gestión académica</span>
                <h1>Procesos de Titulación</h1>
                <p>
                    Consulta y gestiona el avance de cada participante desde un solo lugar:
                    sede, programa, gestión, fase actual, jurado y siguiente acción.
                </p>
            </div>

            <div class="procesos-resumen">
                <div class="resumen-card">
                    <strong>{{ paginacion.total_registros || 0 }}</strong>
                    <span>Procesos encontrados</span>
                </div>

                <div class="resumen-card">
                    <strong>{{ totalEnRevision }}</strong>
                    <span>En revisión</span>
                </div>

                <div class="resumen-card">
                    <strong>{{ totalAccionesAdmin }}</strong>
                    <span>Acciones administrativas</span>
                </div>
            </div>
        </div>

        <div v-if="mensaje" class="alert" :class="tipoMensaje">
            {{ mensaje }}
        </div>

        <div class="procesos-panel">

            <div class="panel-header">
                <div>
                    <h2>Bandeja general de procesos</h2>
                    <p>
                        Utiliza los filtros para ubicar rápidamente un participante,
                        incluso cuando existan muchos programas, gestiones y sedes.
                    </p>
                </div>

                <button type="button" class="btn-secondary" @click="limpiarFiltros">
                    Limpiar filtros
                </button>
            </div>

            <div class="filtros-grid">

                <div class="form-group">
                    <label>Sede</label>
                    <select v-model="filtros.sede" @change="cambiarSede">
                        <option value="">Todas las sedes</option>
                        <option
                            v-for="sede in catalogos.sedes"
                            :key="sede.id_sede"
                            :value="String(sede.id_sede)"
                        >
                            {{ sede.nombre_sede }}{{ sede.ciudad ? ' — ' + sede.ciudad : '' }}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de programa</label>
                    <select v-model="filtros.tipo" @change="cambiarTipo">
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
                    <label>Programa</label>
                    <select v-model="filtros.programa" @change="cargarProcesos(1)">
                        <option value="">Todos los programas</option>
                        <option
                            v-for="programa in programasFiltrados"
                            :key="programa.id"
                            :value="String(programa.id)"
                        >
                            {{ programa.nombre_programa }}
                            · {{ programa.gestion_externa || 'Sin gestión' }}
                            · V{{ programa.version_programa_externa || '-' }}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Gestión</label>
                    <select v-model="filtros.gestion" @change="cargarProcesos(1)">
                        <option value="">Todas las gestiones</option>
                        <option
                            v-for="gestion in catalogos.gestiones"
                            :key="gestion"
                            :value="gestion"
                        >
                            {{ gestion }}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Versión</label>
                    <select v-model="filtros.version" @change="cargarProcesos(1)">
                        <option value="">Todas las versiones</option>
                        <option
                            v-for="version in catalogos.versiones"
                            :key="version"
                            :value="version"
                        >
                            Versión {{ version }}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fase actual</label>
                    <select v-model="filtros.fase" @change="cargarProcesos(1)">
                        <option value="">Todas las fases</option>
                        <option value="0">Sin fase habilitada</option>
                        <option value="1">Fase 1 — Propuesta inicial</option>
                        <option value="2">Fase 2 — Monografía</option>
                        <option value="3">Fase 3 — Evaluación final</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Estado</label>
                    <select v-model="filtros.estado" @change="cargarProcesos(1)">
                        <option value="">Todos los estados</option>
                        <option value="SIN_CONFIGURACION">Sin configuración</option>
                        <option value="HABILITADA">Habilitada</option>
                        <option value="BORRADOR">Borrador</option>
                        <option value="EN_REVISION">En revisión</option>
                        <option value="OBSERVADO">Con observaciones</option>
                        <option value="CORREGIDO">Versión corregida</option>
                        <option value="REVISADO">Validado</option>
                    </select>
                </div>

                <div class="form-group buscador-procesos">
                    <label>Buscar participante</label>
                    <input
                        type="search"
                        v-model="filtros.buscar"
                        @keyup.enter="cargarProcesos(1)"
                        placeholder="Nombre, CI, usuario o código..."
                    >
                </div>

            </div>

            <div class="acciones-filtros">
                <button
                    type="button"
                    class="btn-primary"
                    @click="cargarProcesos(1)"
                    :disabled="cargando"
                >
                    {{ cargando ? 'Buscando...' : 'Buscar procesos' }}
                </button>

                <select
                    v-model="filtros.por_pagina"
                    @change="cargarProcesos(1)"
                    class="select-cantidad"
                >
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                    <option value="100">100 por página</option>
                </select>
            </div>

            <div v-if="cargando" class="empty-state">
                Cargando procesos de titulación...
            </div>

            <div v-else-if="error" class="alert error">
                {{ error }}
            </div>

            <div v-else-if="procesos.length === 0" class="empty-state">
                No existen procesos que coincidan con los filtros seleccionados.
            </div>

            <div v-else class="tabla-wrap">
                <table class="procesos-table">
                    <thead>
                        <tr>
                            <th>Sede / Programa</th>
                            <th>Participante</th>
                            <th>Gestión</th>
                            <th>Fase actual</th>
                            <th>Estado</th>
                            <th>Jurado</th>
                            <th>Responsable actual</th>
                            <th>Siguiente acción</th>
                            <th>Expediente</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="proceso in procesos" :key="proceso.id_inscripcion">

                            <td>
                                <div class="celda-principal">
                                    <strong>{{ proceso.nombre_sede }}</strong>
                                    <span>{{ proceso.nombre_programa }}</span>
                                    <small>{{ textoTipo(proceso.tipo_programa) }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="celda-principal">
                                    <strong>{{ proceso.estudiante }}</strong>
                                    <span>{{ proceso.ci || proceso.codigo_participante_externo || proceso.usuario }}</span>
                                </div>
                            </td>

                            <td>
                                <div class="celda-simple">
                                    <strong>{{ proceso.gestion_externa || 'Sin gestión' }}</strong>
                                    <span>Versión {{ proceso.version_programa_externa || '-' }}</span>
                                </div>
                            </td>

                            <td>
                                <div class="celda-simple">
                                    <strong>{{ proceso.fase_nombre }}</strong>
                                </div>
                            </td>

                            <td>
                                <span
                                    class="estado-badge"
                                    :class="claseEstado(proceso.estado_proceso)"
                                >
                                    {{ proceso.estado_etiqueta }}
                                </span>
                            </td>

                            <td>
                                <span>{{ proceso.jurado_asignado }}</span>
                            </td>

                            <td>
                                <span>{{ proceso.responsable_actual }}</span>
                            </td>

                            <td>
                                <div class="siguiente-accion">
                                    {{ proceso.siguiente_accion }}
                                </div>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="btn-view"
                                    @click="abrirExpediente(proceso)"
                                >
                                    Abrir
                                </button>
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="paginacion.total_registros > 0" class="paginacion">

                <span>
                    Mostrando página {{ paginacion.pagina_actual }}
                    de {{ paginacion.total_paginas }}
                    · {{ paginacion.total_registros }} procesos
                </span>

                <div class="paginacion-botones">
                    <button
                        type="button"
                        class="btn-secondary"
                        :disabled="paginacion.pagina_actual <= 1 || cargando"
                        @click="cargarProcesos(paginacion.pagina_actual - 1)"
                    >
                        ← Anterior
                    </button>

                    <button
                        type="button"
                        class="btn-secondary"
                        :disabled="paginacion.pagina_actual >= paginacion.total_paginas || cargando"
                        @click="cargarProcesos(paginacion.pagina_actual + 1)"
                    >
                        Siguiente →
                    </button>
                </div>

            </div>

        </div>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/procesos.js?v=1"></script>