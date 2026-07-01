<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rol = strtolower(trim((string) ($_SESSION['rol'] ?? '')));

if (!isset($_SESSION['id'])) {
    header('Location: /SIGET_ESAM/login.php');
    exit;
}

if (!in_array($rol, ['docente', 'tutor'], true)) {
    ?>
    <section class="revision-page">
        <div class="revision-empty">
            <h2>Acceso restringido</h2>
            <p>Esta sección está disponible únicamente para docentes o jurados asignados.</p>
        </div>
    </section>
    <?php
    return;
}
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_revision.css?v=1">

<section class="revision-screen" id="app-evaluaciones-jurado" v-cloak>

    <div class="revision-shell">

        <div class="revision-hero">
            <div class="hero-content">
                <span class="hero-label">Jurado académico</span>

                <h1>Bandeja de Evaluación</h1>

                <p>
                    Revisa los avances de Fase 2 asignados a tu perfil,
                    registra observaciones o valida el avance del participante.
                </p>
            </div>

            <div class="hero-stats">
                <div class="stat-box">
                    <strong>{{ stats.en_revision || 0 }}</strong>
                    <span>En revisión</span>
                </div>

                <div class="stat-box">
                    <strong>{{ stats.observados || 0 }}</strong>
                    <span>Con observaciones</span>
                </div>

                <div class="stat-box">
                    <strong>{{ stats.revisados || 0 }}</strong>
                    <span>Avances validados</span>
                </div>
            </div>
        </div>

        <div class="revision-panel">

            <div class="panel-header">
                <div>
                    <h2>Trabajos asignados</h2>
                    <p>
                        Solo se muestran las entregas vinculadas a tu asignación como jurado.
                    </p>
                </div>

                <button type="button" class="btn-secondary" @click="cargarEvaluaciones">
                    Actualizar
                </button>
            </div>

            <div class="revision-toolbar">
                <div class="search-box">
                    <input
                        type="text"
                        v-model="busqueda"
                        placeholder="Buscar participante, programa o tema..."
                    >
                </div>

                <select v-model="filtroEstado" @change="cargarEvaluaciones">
                    <option value="todos">Todos</option>
                    <option value="en_revision">En revisión</option>
                    <option value="observados">Con observaciones</option>
                    <option value="revisados">Avances validados</option>
                </select>
            </div>

            <div v-if="cargando" class="empty-state">
                Cargando trabajos asignados...
            </div>

            <div v-else-if="error" class="alert error">
                {{ error }}
            </div>

            <div v-else-if="itemsFiltrados.length === 0" class="empty-state">
                No existen trabajos asignados para este filtro.
            </div>

            <div v-else class="tabla-wrap">
                <table class="revision-table">
                    <thead>
                        <tr>
                            <th>Participante</th>
                            <th>Programa</th>
                            <th>Trabajo</th>
                            <th>Fechas</th>
                            <th>Estado</th>
                            <th>Archivo</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in itemsFiltrados" :key="item.id_trabajo">

                            <td>
                                <div class="participante-cell">
                                    <strong>{{ item.estudiante }}</strong>
                                    <span>{{ item.usuario_estudiante }}</span>
                                </div>
                            </td>

                            <td>
                                <div class="programa-cell">
                                    <strong>{{ item.nombre_programa }}</strong>
                                    <span>
                                        Gestión {{ item.gestion_externa || 'No definida' }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="fase-cell">
                                    <strong>{{ item.titulo_trabajo }}</strong>
                                    <span>
                                        Fase {{ item.numero_fase }} · {{ item.nombre_fase }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="revision-info">
                                    <strong>Envío:</strong>
                                    <span>{{ formatearFecha(item.fecha_presentacion, true) }}</span>

                                    <strong>Respuesta hasta:</strong>
                                    <span>{{ formatearFecha(item.fecha_limite_revision, true) }}</span>
                                </div>
                            </td>

                            <td>
                                <span
                                    class="estado-badge"
                                    :class="claseEstado(item.estado_documento)"
                                >
                                    {{ textoEstado(item.estado_documento) }}
                                </span>
                            </td>

                            <td>
                                <a
                                    v-if="item.ruta_archivo"
                                    :href="rutaArchivo(item.ruta_archivo)"
                                    target="_blank"
                                    class="btn-view"
                                >
                                    Ver documento
                                </a>

                                <span v-else class="text-muted">
                                    Sin archivo
                                </span>
                            </td>

                            <td>
                                <button
                                    v-if="puedeEvaluar(item)"
                                    type="button"
                                    class="btn-primary"
                                    @click="abrirModal(item)"
                                >
                                    {{ esEvaluacionRegistrada(item) ? 'Editar revisión' : 'Revisar avance' }}
                                </button>

                                <span v-else class="text-muted">
                                    Revisión registrada
                                </span>
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
                        <h2>Revisión de avance — Fase 2</h2>
                        <p>{{ itemSeleccionado?.estudiante }}</p>
                    </div>

                    <button
                        type="button"
                        class="modal-close"
                        @click="cerrarModal"
                    >
                        ×
                    </button>
                </div>

                <div v-if="itemSeleccionado" class="student-summary-card">
                    <p>
                        <strong>Trabajo:</strong>
                        {{ itemSeleccionado.titulo_trabajo }}
                    </p>

                    <p>
                        <strong>Documento:</strong>
                        El participante presentó una versión para revisión.
                    </p>

                    <p>
                        La valoración o nota se registrará únicamente en la Fase 3,
                        mediante el acta de evaluación final.
                    </p>
                </div>

                <form @submit.prevent="guardarEvaluacion">

                    <div class="form-group">
                        <label>Decisión del jurado</label>

                        <select v-model="form.accion" required>
                            <option value="">Seleccione una decisión</option>
                            <option value="APROBAR">
                                Validar avance de Fase 2
                            </option>
                            <option value="RECHAZAR">
                                Rechazar y enviar observaciones
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Comentarios u observaciones</label>

                        <textarea
                            rows="6"
                            v-model="form.comentario"
                            placeholder="Escriba las observaciones, recomendaciones o comentario sobre el avance..."
                        ></textarea>

                        <small>
                            Las observaciones son obligatorias cuando se rechaza el documento.
                            La valoración se emitirá únicamente en la Fase 3 mediante
                            el acta de evaluación.
                        </small>
                    </div>

                    <div class="modal-actions">
                        <button
                            type="button"
                            class="btn-secondary"
                            @click="cerrarModal"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            class="btn-primary"
                            :disabled="guardando"
                        >
                            {{ guardando ? 'Guardando...' : 'Guardar revisión' }}
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>

</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/profesor/evaluaciones.js?v=3"></script>