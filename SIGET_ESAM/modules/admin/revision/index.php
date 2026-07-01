<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_revision.css?v=1">

<section class="revision-screen" id="app-revision-admin" v-cloak>
    <div class="revision-shell">

        <div class="revision-hero">
            <div class="hero-content">
                <span class="hero-label">Control académico</span>
                <h1>Revisión Administrativa</h1>
                <p>
                    Gestiona los trabajos recibidos, revisa archivos, registra observaciones
                    y actualiza el estado del proceso académico.
                </p>
            </div>

            <div class="hero-stats">
                <button type="button" class="stat-box" :class="{ activo: filtroEstado === 'pendientes' }" @click="cambiarFiltro('pendientes')">
                    <strong>{{ stats.pendientes || 0 }}</strong>
                    <span>Pendientes</span>
                </button>

                <button type="button" class="stat-box" :class="{ activo: filtroEstado === 'observados' }" @click="cambiarFiltro('observados')">
                    <strong>{{ stats.observados || 0 }}</strong>
                    <span>Observados</span>
                </button>

                <button type="button" class="stat-box" :class="{ activo: filtroEstado === 'aprobados' }" @click="cambiarFiltro('aprobados')">
                    <strong>{{ stats.aprobados || 0 }}</strong>
                    <span>Aprobados</span>
                </button>

                <button type="button" class="stat-box" :class="{ activo: filtroEstado === 'rechazados' }" @click="cambiarFiltro('rechazados')">
                    <strong>{{ stats.rechazados || 0 }}</strong>
                    <span>Rechazados</span>
                </button>
            </div>
        </div>

        <div class="revision-panel">
            <div class="panel-header">
                <div>
                    <h2>Listado de trabajos</h2>
                    <p>Filtra y revisa los documentos enviados por los participantes.</p>
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
                        placeholder="Buscar por participante, programa, fase o título..."
                    >
                </div>

                <select v-model="filtroEstado" @change="cargarItems">
                    <option value="pendientes">Pendientes</option>
                    <option value="observados">Observados</option>
                    <option value="aprobados">Aprobados</option>
                    <option value="rechazados">Rechazados</option>
                    <option value="todos">Todos</option>
                </select>
            </div>

            <div v-if="cargando" class="empty-state">
                Cargando revisión administrativa...
            </div>

            <div v-else-if="error" class="alert error">
                {{ error }}
            </div>

            <div v-else-if="itemsFiltrados.length === 0" class="empty-state">
                No existen registros para este filtro.
            </div>

            <div v-else class="tabla-wrap">
                <table class="revision-table">
                    <thead>
                        <tr>
                            <th>Participante</th>
                            <th>Programa</th>
                            <th>Trabajo / Fase</th>
                            <th>Estado</th>
                            <th>Archivo</th>
                            <th>Revisión</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in itemsFiltrados" :key="item.id_trabajo">
                            <td>
                                <div class="participante-cell">
                                    <strong>{{ item.estudiante || 'Sin nombre' }}</strong>
                                    <span>{{ item.profesion_postgrado || 'Sin profesión registrada' }}</span>
                                </div>
                            </td>

                            <td>
                                <div class="programa-cell">
                                    <strong>{{ item.nombre_programa || 'Sin programa' }}</strong>
                                    <span>{{ item.tipo_programa || 'No definido' }}</span>
                                </div>
                            </td>

                            <td>
                                <div class="fase-cell">
                                    <strong>{{ item.titulo_trabajo || 'Sin título' }}</strong>
                                    <span>
                                        Fase {{ item.numero_fase || '-' }} · {{ item.nombre_fase || 'Sin fase' }}
                                    </span>
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

                                <span v-else class="text-muted">
                                    No subido
                                </span>
                            </td>

                            <td>
                                <div class="revision-info">
                                    <strong>
                                        {{ item.calificacion_final ? item.calificacion_final + ' pts' : 'Sin nota' }}
                                    </strong>
                                    <span>
                                        {{ item.comentario_revision || 'Sin comentario' }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="revision-actions">
                                    <button type="button" class="btn-primary" @click="abrirModal(item, 'aprobar')">
                                        Aprobar
                                    </button>

                                    <button type="button" class="btn-warning" @click="abrirModal(item, 'observar')">
                                        Observar
                                    </button>

                                    <button type="button" class="btn-danger" @click="abrirModal(item, 'rechazar')">
                                        Rechazar
                                    </button>
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
                        <p>{{ itemSeleccionado?.estudiante }}</p>
                    </div>

                    <button type="button" class="modal-close" @click="cerrarModal">×</button>
                </div>

                <form @submit.prevent="guardarRevision">
                    <div class="form-group">
                        <label>Calificación</label>
                        <input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            v-model="form.calificacion"
                            placeholder="Ej: 85"
                        >
                    </div>

                    <div class="form-group">
                        <label>Comentario / observación</label>
                        <textarea
                            rows="5"
                            v-model="form.comentario"
                            placeholder="Escriba una observación para el participante..."
                        ></textarea>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" @click="cerrarModal">
                            Cancelar
                        </button>

                        <button type="submit" class="btn-primary">
                            Guardar revisión
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/revision.js?v=1"></script>