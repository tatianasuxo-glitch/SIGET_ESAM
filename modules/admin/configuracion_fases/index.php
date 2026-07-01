<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_fases.css?v=1">

<section class="fases-screen" id="app-fases-admin" v-cloak>
    <div class="fases-shell">

        <div class="fases-hero">
            <div class="hero-content">
                <span class="hero-label">Control de procesos</span>
                <h1>Gestión de Fases</h1>
                <p>
                    Configura las fases académicas por programa, gestión, tipo de trabajo,
                    fechas de entrega, revisión y nota mínima requerida.
                </p>
            </div>

            <div class="hero-stats">
                <button type="button" class="stat-box" @click="limpiarFiltros">
                    <strong>{{ stats.total || 0 }}</strong>
                    <span>Total</span>
                </button>

                <button type="button" class="stat-box" @click="filtroEstado = 'ACTIVO'">
                    <strong>{{ stats.activas || 0 }}</strong>
                    <span>Activas</span>
                </button>

                <button type="button" class="stat-box" @click="filtroEstado = 'INACTIVO'">
                    <strong>{{ stats.inactivas || 0 }}</strong>
                    <span>Inactivas</span>
                </button>

                <button type="button" class="stat-box" @click="filtroTipo = 'Diplomado'">
                    <strong>{{ stats.diplomados || 0 }}</strong>
                    <span>Diplomados</span>
                </button>

                <button type="button" class="stat-box" @click="filtroTipo = 'Maestría'">
                    <strong>{{ stats.maestrias || 0 }}</strong>
                    <span>Maestrías</span>
                </button>
            </div>
        </div>

        <div class="fases-panel">
            <div class="panel-header">
                <div>
                    <h2>Configuraciones de fases</h2>
                    <p>Administra las fechas, fases y parámetros por programa académico.</p>
                </div>

                <button type="button" class="btn-primary" @click="abrirCrear">
                    + Configurar fase
                </button>
            </div>

            <div class="fases-toolbar">
                <div class="search-box">
                    <input
                        type="text"
                        v-model="busqueda"
                        placeholder="Buscar por programa, fase, gestión o tipo de trabajo..."
                    >
                </div>

                <select v-model="filtroPrograma">
                    <option value="">Todos los programas</option>
                    <option
                        v-for="programa in programas"
                        :key="programa.id"
                        :value="programa.id"
                    >
                        {{ programa.nombre_programa }}
                    </option>
                </select>

                <select v-model="filtroEstado">
                    <option value="">Todos los estados</option>
                    <option value="ACTIVO">Activas</option>
                    <option value="INACTIVO">Inactivas</option>
                </select>
            </div>

            <div v-if="cargando" class="empty-state">
                Cargando gestión de fases...
            </div>

            <div v-else-if="error" class="alert error">
                {{ error }}
            </div>

            <div v-else-if="configuracionesFiltradas.length === 0" class="empty-state">
                No existen configuraciones con los filtros seleccionados.
            </div>

            <div v-else class="fases-grid">
                <article
                    v-for="config in configuracionesFiltradas"
                    :key="config.id"
                    class="fase-card"
                >
                    <div class="fase-card-top">
                        <span class="tipo-badge">
                            {{ config.tipo_programa || 'Programa' }}
                        </span>

                        <span
                            class="estado-badge"
                            :class="config.estado === 'ACTIVO' ? 'activo' : 'inactivo'"
                        >
                            {{ config.estado === 'ACTIVO' ? 'Activa' : 'Inactiva' }}
                        </span>
                    </div>

                    <h3>{{ config.nombre_programa }}</h3>

                    <div class="fase-title">
                        <strong>Fase {{ config.numero_fase }}</strong>
                        <span>{{ config.nombre_fase }}</span>
                    </div>

                    <div class="fase-meta">
                        <div>
                            <span>Gestión</span>
                            <strong>{{ config.gestion }}</strong>
                        </div>

                        <div>
                            <span>Trabajo</span>
                            <strong>{{ config.tipo_trabajo }}</strong>
                        </div>

                        <div>
                            <span>Nota mínima</span>
                            <strong>{{ config.nota_minima }}</strong>
                        </div>
                    </div>

                    <div class="fecha-list">
                        <div>
                            <span>Inicio entrega</span>
                            <strong>{{ formatoFecha(config.fecha_inicio_entrega) }}</strong>
                        </div>

                        <div>
                            <span>Límite entrega</span>
                            <strong>{{ formatoFecha(config.fecha_limite_entrega) }}</strong>
                        </div>

                        <div>
                            <span>Límite revisión</span>
                            <strong>{{ formatoFecha(config.fecha_limite_revision) }}</strong>
                        </div>

                        <div>
                            <span>Devolución obs.</span>
                            <strong>{{ config.fecha_devolucion_observaciones ? formatoFecha(config.fecha_devolucion_observaciones) : 'No definido' }}</strong>
                        </div>
                    </div>

                    <div class="fase-extra">
                        <div>
                            <strong>{{ config.total_requisitos || 0 }}</strong>
                            <span>Requisitos</span>
                        </div>

                        <div>
                            <strong>{{ config.total_estudiantes_configurados || 0 }}</strong>
                            <span>Personalizados</span>
                        </div>
                    </div>

                    <div class="fase-actions">
                        <button type="button" class="btn-primary" @click="abrirEditar(config)">
                            Editar
                        </button>

                        <button
                            type="button"
                            :class="config.estado === 'ACTIVO' ? 'btn-danger' : 'btn-secondary'"
                            @click="cambiarEstado(config)"
                        >
                            {{ config.estado === 'ACTIVO' ? 'Inactivar' : 'Activar' }}
                        </button>
                    </div>
                </article>
            </div>
        </div>

        <div v-if="modalAbierto" class="modal-overlay">
            <div class="modal-card">
                <div class="modal-header">
                    <div>
                        <h2>{{ modoFormulario === 'crear' ? 'Configurar fase' : 'Editar configuración' }}</h2>
                        <p>Completa los datos de control académico para esta fase.</p>
                    </div>

                    <button type="button" class="modal-close" @click="cerrarModal">×</button>
                </div>

                <form @submit.prevent="guardarConfiguracion">
                    <div class="form-group">
                        <label>Programa académico</label>
                        <select v-model="form.id_programa" required>
                            <option value="">Seleccionar programa</option>
                            <option
                                v-for="programa in programas"
                                :key="programa.id"
                                :value="programa.id"
                            >
                                {{ programa.nombre_programa }} - {{ programa.tipo }}
                            </option>
                        </select>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Fase</label>
                            <select v-model="form.id_fase" required>
                                <option value="">Seleccionar fase</option>
                                <option
                                    v-for="fase in fases"
                                    :key="fase.id"
                                    :value="fase.id"
                                >
                                    Fase {{ fase.numero_fase }} - {{ fase.nombre_fase }}
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Gestión</label>
                            <input type="text" v-model="form.gestion" placeholder="Ej: 2026" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo de trabajo</label>
                            <select v-model="form.tipo_trabajo" required>
                                <option value="">Seleccionar</option>
                                <option value="Monografía">Monografía</option>
                                <option value="Tesis">Tesis</option>
                                <option value="Proyecto de grado">Proyecto de grado</option>
                                <option value="Trabajo final">Trabajo final</option>
                                <option value="Artículo científico">Artículo científico</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Nota mínima</label>
                            <input type="number" min="0" max="100" step="0.01" v-model="form.nota_minima" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Inicio de entrega</label>
                            <input type="datetime-local" v-model="form.fecha_inicio_entrega" required>
                        </div>

                        <div class="form-group">
                            <label>Límite de entrega</label>
                            <input type="datetime-local" v-model="form.fecha_limite_entrega" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Límite de revisión</label>
                            <input type="datetime-local" v-model="form.fecha_limite_revision" required>
                        </div>

                        <div class="form-group">
                            <label>Devolución de observaciones</label>
                            <input type="datetime-local" v-model="form.fecha_devolucion_observaciones">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Estado</label>
                        <select v-model="form.estado" required>
                            <option value="ACTIVO">Activo</option>
                            <option value="INACTIVO">Inactivo</option>
                        </select>
                    </div>

                    <div v-if="mensajeModal" class="alert success">
                        {{ mensajeModal }}
                    </div>

                    <div v-if="errorModal" class="alert error">
                        {{ errorModal }}
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" @click="cerrarModal">
                            Cancelar
                        </button>

                        <button type="submit" class="btn-primary">
                            Guardar configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/fases.js?v=1"></script>