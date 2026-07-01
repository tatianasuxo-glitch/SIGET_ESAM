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
    <section class="dashboard-estudiante">
        <div class="student-summary-card">
            <h2>Acceso restringido</h2>
            <p>Esta sección está disponible únicamente para Administración Académica.</p>
        </div>
    </section>
    <?php
    return;
}
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_revision.css?v=3">

<section class="revision-screen" id="app-revision-admin" v-cloak>
    <div class="revision-shell">

        <div class="revision-hero">
            <div class="hero-content">
                <span class="hero-label">Gestión académica</span>
                <h1>Revisión de Fase 1</h1>
                <p>
                    Revisa la propuesta inicial. Al validarla, asigna el jurado responsable
                    para las Fases 2 y 3, y habilita la Fase 2 para el participante.
                </p>
            </div>

            <div class="hero-stats">
                <button
                    type="button"
                    class="stat-box"
                   
                    @click="cambiarFiltro('pendientes')"
                >
                    <strong>{{ stats.pendientes || 0 }}</strong>
                    <span>Pendientes</span>
                </button>

                <button
                    type="button"
                    class="stat-box"
                   
                    @click="cambiarFiltro('observados')"
                >
                    <strong>{{ stats.observados || 0 }}</strong>
                    <span>Con observaciones</span>
                </button>

                <button
                    type="button"
                    class="stat-box"
                    
                    @click="cambiarFiltro('revisados')"
                >
                    <strong>{{ stats.revisados || 0 }}</strong>
                    <span>Validados</span>
                </button>
            </div>
        </div>

        <div v-if="mensaje" class="alert" :class="tipoMensaje === 'error' ? 'error' : 'success'">
            {{ mensaje }}
        </div>

        <div class="revision-panel">
            <div class="panel-header">
                <div>
                    <h2>Propuestas iniciales</h2>
                    <p>
                        La Fase 1 no registra nota. La validación confirma la propuesta,
                        el jurado y la habilitación de la Fase 2.
                    </p>
                </div>

                <button type="button" class="btn-secondary" @click="cambiarFiltro('todos')">
                    Ver todos
                </button>
            </div>

            <div class="revision-toolbar">
                <div class="search-box">
                    <input
                        type="text"
                        v-model="busqueda"
                        placeholder="Buscar por participante, programa, propuesta o fase..."
                    >
                </div>

                <select v-model="filtroEstado" @change="cargarItems">
                    <option value="pendientes">Pendientes</option>
                    <option value="observados">Con observaciones</option>
                    <option value="revisados">Validados</option>
                    <option value="todos">Todos</option>
                </select>
            </div>

            <div v-if="cargando" class="empty-state">
                Cargando revisión de Fase 1...
            </div>

            <div v-else-if="error" class="alert error">
                {{ error }}
            </div>

            <div v-else-if="itemsFiltrados.length === 0" class="empty-state">
                No existen propuestas para este filtro.
            </div>

            <div v-else class="tabla-wrap">
                <table class="revision-table">
                    <thead>
                        <tr>
                            <th>Participante</th>
                            <th>Programa</th>
                            <th>Propuesta / Fase</th>
                            <th>Estado</th>
                            <th>Archivo</th>
                            <th>Resultado administrativo</th>
                            <th>Continuidad del proceso</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in itemsFiltrados" :key="item.id_trabajo">
                            <td>
                                <div class="participante-cell">
                                    <strong>{{ item.estudiante || 'Sin nombre' }}</strong>
                                    <span>{{ item.profesion_postgrado || item.usuario || 'Sin dato registrado' }}</span>
                                </div>
                            </td>

                            <td>
                                <div class="programa-cell">
                                    <strong>{{ item.nombre_programa || 'Sin programa' }}</strong>
                                    <span>{{ item.tipo_programa || 'No definido' }} · Gestión {{ item.gestion_configuracion || '-' }}</span>
                                </div>
                            </td>

                            <td>
                                <div class="fase-cell">
                                    <strong>{{ item.titulo_trabajo || 'Sin título' }}</strong>
                                    <span>Fase {{ item.numero_fase || '1' }} · {{ item.nombre_fase || 'Propuesta inicial' }}</span>
                                </div>
                            </td>

                            <td>
                                <span class="estado-badge" :class="claseEstado(item.estado_aprobacion)">
                                    {{ textoEstado(item.estado_aprobacion) }}
                                </span>
                            </td>

                            <td>
                                <a
                                    v-if="item.ruta_archivo"
                                    :href="rutaArchivo(item.ruta_archivo)"
                                    target="_blank"
                                    class="btn-view"
                                >
                                    Ver archivo
                                </a>

                                <span v-else class="text-muted">No subido</span>
                            </td>

                            <td>
                                <div class="revision-info">
                                    <strong>Sin valoración en esta fase</strong>
                                    <span>{{ item.comentario_revision || 'Sin comentario registrado' }}</span>
                                </div>
                            </td>

                            <td>
                                <div v-if="esRevisable(item)" class="revision-actions">
                                    <button type="button" class="btn-primary" @click="abrirModal(item, 'validar')">
                                        Validar propuesta
                                    </button>

                                    <button type="button" class="btn-warning" @click="abrirModal(item, 'observar')">
                                        Observar
                                    </button>
                                </div>

                                <div v-else-if="esValidada(item)" class="revision-info">
                                    <strong>Fase 2 {{ item.fase_2_habilitada ? 'habilitada' : 'pendiente' }}</strong>
                                    <span>
                                        Jurado para Fases 2 y 3:
                                        {{ item.jurado_asignado || 'Pendiente de asignación' }}
                                    </span>
                                </div>

                                <div v-else class="revision-info">
                                    <strong>Esperando corrección del participante</strong>
                                    <span>La Fase 1 permanece activa hasta que reenvíe la propuesta.</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modalAbierto" class="modal-overlay">
            <div class="modal-card">
                <div class="modal-header">
                    <div>
                        <h2>{{ tituloModal }}</h2>
                        <p>{{ itemSeleccionado?.estudiante }} · {{ itemSeleccionado?.titulo_trabajo }}</p>
                    </div>

                    <button type="button" class="modal-close" @click="cerrarModal">×</button>
                </div>

                <form @submit.prevent="guardarRevision">

                    <div v-if="accionModal === 'validar'" class="form-group">
                        <label>Jurado responsable para Fases 2 y 3</label>
                        <select v-model="form.id_docente" required>
                            <option value="">Seleccione un jurado</option>
                            <option
                                v-for="jurado in jurados"
                                :key="jurado.id"
                                :value="String(jurado.id)"
                            >
                                {{ jurado.nombre_completo }} — {{ jurado.nombre_rol }}
                            </option>
                        </select>

                        <small>
                            El mismo jurado revisará la monografía de Fase 2 y el documento final de Fase 3.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>
                            {{ accionModal === 'validar' ? 'Comentario de validación (opcional)' : 'Observaciones para el participante (obligatorio)' }}
                        </label>

                        <textarea
                            rows="5"
                            v-model="form.comentario"
                            :placeholder="accionModal === 'validar'
                                ? 'Ej.: Propuesta validada. Continúe con el desarrollo de la monografía.'
                                : 'Escriba las correcciones que debe realizar el participante...'"
                            :required="accionModal === 'observar'"
                        ></textarea>
                    </div>

                    <div v-if="accionModal === 'validar'" class="student-summary-card">
                        <strong>Acción al confirmar</strong>
                        <p>
                            La propuesta quedará validada, el jurado seleccionado quedará asignado
                            para Fases 2 y 3, y se habilitará la Fase 2.
                        </p>
                    </div>

                    <div v-else class="student-summary-card">
                        <strong>Acción al confirmar</strong>
                        <p>
                            La propuesta quedará con observaciones. El participante podrá corregirla
                            y volver a enviarla para revisión administrativa.
                        </p>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" @click="cerrarModal" :disabled="guardando">
                            Cancelar
                        </button>

                        <button type="submit" class="btn-primary" :disabled="guardando">
                            {{ guardando ? 'Guardando...' : textoAccionModal }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/revision.js?v=3"></script>
