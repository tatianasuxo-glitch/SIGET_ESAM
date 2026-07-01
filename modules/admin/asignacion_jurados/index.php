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
            <p>Esta sección está disponible únicamente para administración.</p>
        </div>
    </section>
    <?php
    return;
}
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_revision.css?v=1">

<section class="revision-screen" id="app-asignacion-jurados" v-cloak>

    <div class="revision-shell">

        <div class="revision-hero">
            <div class="hero-content">
                <span class="hero-label">Gestión académica</span>

                <h1>Asignación de Jurados</h1>

                <p>
                    Asigna un jurado a participantes cuya Fase 1 fue revisada.
                    Al confirmar la asignación, se habilitará formalmente la Fase 2.
                </p>
            </div>

            <div class="hero-stats">
                <div class="stat-box">
                    <strong>{{ stats.pendientes || 0 }}</strong>
                    <span>Por asignar</span>
                </div>

                <div class="stat-box">
                    <strong>{{ stats.asignados || 0 }}</strong>
                    <span>Con jurado</span>
                </div>

                <div class="stat-box">
                    <strong>{{ stats.fase_2_habilitada || 0 }}</strong>
                    <span>Fase 2 activa</span>
                </div>
            </div>
        </div>

        <div class="revision-panel">

            <div class="panel-header">
                <div>
                    <h2>Fase 1 revisada</h2>
                    <p>
                        Solo aparecen participantes cuya propuesta inicial fue revisada
                        por Administración Académica.
                    </p>
                </div>

                <button type="button" class="btn-secondary" @click="cargarParticipantes">
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
            </div>

            <div v-if="cargando" class="empty-state">
                Cargando participantes...
            </div>

            <div v-else-if="error" class="alert error">
                {{ error }}
            </div>

            <div v-else-if="participantesFiltrados.length === 0" class="empty-state">
                No existen participantes con Fase 1 revisada para asignar.
            </div>

            <div v-else class="tabla-wrap">
                <table class="revision-table">

                    <thead>
                        <tr>
                            <th>Participante</th>
                            <th>Programa</th>
                            <th>Propuesta revisada</th>
                            <th>Comentario de Administración</th>
                            <th>Jurado actual</th>
                            <th>Fase 2</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in participantesFiltrados" :key="item.id_trabajo">

                            <td>
                                <div class="participante-cell">
                                    <strong>{{ item.estudiante }}</strong>
                                    <span>{{ item.usuario }}</span>
                                </div>
                            </td>

                            <td>
                                <div class="programa-cell">
                                    <strong>{{ item.nombre_programa }}</strong>
                                    <span>
                                        Gestión {{ item.gestion_externa || 'Sin gestión' }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="fase-cell">
                                    <strong>{{ item.titulo_trabajo }}</strong>
                                    <span>
                                        Nota: {{ item.calificacion_final || 'Sin nota' }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <span>
                                    {{ item.comentario_revision || 'Sin comentarios registrados' }}
                                </span>
                            </td>

                            <td>
                                <strong v-if="item.jurado_asignado">
                                    {{ item.jurado_asignado }}
                                </strong>

                                <span v-else class="text-muted">
                                    Pendiente de asignación
                                </span>
                            </td>

                            <td>
                                <span
                                    class="estado-badge"
                                    :class="item.fase_2_habilitada ? 'estado-aprobado' : 'estado-borrador'"
                                >
                                    {{ item.fase_2_habilitada ? 'Habilitada' : 'Pendiente' }}
                                </span>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="btn-primary"
                                    @click="abrirModal(item)"
                                >
                                    {{ item.jurado_asignado ? 'Reasignar jurado' : 'Asignar jurado' }}
                                </button>
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
                        <h2>Asignar jurado y habilitar Fase 2</h2>
                        <p>{{ itemSeleccionado?.estudiante }}</p>
                    </div>

                    <button type="button" class="modal-close" @click="cerrarModal">
                        ×
                    </button>
                </div>

                <form @submit.prevent="guardarAsignacion">

                    <div class="form-group">
                        <label>Jurado responsable</label>

                        <select v-model="form.id_docente" required>
                            <option value="">Seleccione un jurado</option>

                            <option
                                v-for="jurado in jurados"
                                :key="jurado.id"
                                :value="jurado.id"
                            >
                                {{ jurado.nombre_completo }} — {{ jurado.nombre_rol }}
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Observación de asignación</label>

                        <textarea
                            rows="4"
                            v-model="form.observacion"
                            placeholder="Ej.: Jurado responsable de revisar la monografía de la Fase 2."
                        ></textarea>
                    </div>

                    <div class="student-summary-card">
                        <strong>Acción al confirmar:</strong>
                        <p>
                            Se registrará el jurado asignado y se habilitará la Fase 2
                            para el participante.
                        </p>
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
                            {{ guardando ? 'Guardando...' : 'Confirmar asignación' }}
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/asignacion_jurados.js?v=1"></script>