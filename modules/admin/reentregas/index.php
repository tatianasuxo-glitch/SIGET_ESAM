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
    <section class="revision-page">
        <div class="revision-empty">
            <h2>Acceso restringido</h2>
            <p>Esta sección está disponible únicamente para Administración.</p>
        </div>
    </section>
    <?php
    return;
}
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_revision.css?v=1">

<section class="revision-screen" id="app-reentregas">

    <div class="revision-shell">

        <div class="revision-hero">
            <div class="hero-content">
                <span class="hero-label">Control académico</span>

                <h1>Control de Correcciones</h1>

                <p>
                    Autoriza nuevas entregas, define plazos de corrección,
                    cierra solicitudes vencidas o reabre casos excepcionales.
                </p>
            </div>

            <div class="hero-stats">
                <div class="stat-box">
                    <strong>{{ stats.pendientes_autorizacion || 0 }}</strong>
                    <span>Por autorizar</span>
                </div>

                <div class="stat-box">
                    <strong>{{ stats.autorizadas || 0 }}</strong>
                    <span>Autorizadas</span>
                </div>

                <div class="stat-box">
                    <strong>{{ stats.reentregadas || 0 }}</strong>
                    <span>Reentregadas</span>
                </div>

                <div class="stat-box">
                    <strong>{{ stats.cerradas || 0 }}</strong>
                    <span>Cerradas</span>
                </div>
            </div>
        </div>

        <div class="revision-panel">

            <div class="panel-header">
                <div>
                    <h2>Solicitudes de corrección</h2>
                    <p>
                        Las solicitudes aparecen cuando el jurado rechaza un documento
                        y registra observaciones.
                    </p>
                </div>

                <button
                    type="button"
                    class="btn-secondary"
                    @click="cargarReentregas"
                >
                    Actualizar
                </button>
            </div>

            <div class="revision-toolbar">
                <div class="search-box">
                    <input
                        type="text"
                        v-model="busqueda"
                        placeholder="Buscar participante, programa, jurado o tema..."
                    >
                </div>

                <select v-model="filtroEstado">
                    <option value="todos">Todos los estados</option>
                    <option value="PENDIENTE_AUTORIZACION">Pendiente de autorización</option>
                    <option value="AUTORIZADA">Autorizada</option>
                    <option value="REENTREGADA">Reentregada</option>
                    <option value="CERRADA">Cerrada</option>
                </select>
            </div>

            <div v-if="cargando" class="empty-state">
                Cargando solicitudes de corrección...
            </div>

            <div v-else-if="error" class="alert error">
                {{ error }}
            </div>

            <div v-else-if="itemsFiltrados.length === 0" class="empty-state">
                No existen solicitudes para el filtro seleccionado.
            </div>

            <div v-else class="tabla-wrap">
                <table class="revision-table">

                    <thead>
                        <tr>
                            <th>Participante</th>
                            <th>Programa / Fase</th>
                            <th>Jurado</th>
                            <th>Observaciones</th>
                            <th>Estado</th>
                            <th>Plazo de corrección</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="item in itemsFiltrados"
                            :key="item.id_control"
                        >
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
                                        Fase {{ item.numero_fase }} · {{ item.nombre_fase }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <strong>
                                    {{ item.jurado_responsable || 'Sin jurado registrado' }}
                                </strong>
                            </td>

                            <td>
                                <div class="revision-info">
                                    <strong>{{ item.titulo_trabajo }}</strong>
                                    <span>{{ item.motivo || 'Sin observaciones registradas' }}</span>
                                </div>
                            </td>

                            <td>
                                <span
                                    class="estado-badge"
                                    :class="claseEstado(item.estado)"
                                >
                                    {{ textoEstado(item.estado) }}
                                </span>
                            </td>

                            <td>
                                <span v-if="item.fecha_limite_correccion">
                                    {{ formatearFecha(item.fecha_limite_correccion, true) }}
                                </span>

                                <span v-else class="text-muted">
                                    Pendiente de definición
                                </span>
                            </td>

                            <td>
                                <div class="revision-actions">

                                    <button
                                        v-if="item.estado === 'PENDIENTE_AUTORIZACION'"
                                        type="button"
                                        class="btn-primary"
                                        @click="abrirModal(item, 'AUTORIZAR')"
                                    >
                                        Autorizar nueva entrega
                                    </button>

                                    <button
                                        v-if="item.estado === 'AUTORIZADA'"
                                        type="button"
                                        class="btn-primary"
                                        @click="abrirModal(item, 'AUTORIZAR')"
                                    >
                                        Modificar plazo
                                    </button>

                                    <button
                                        v-if="item.estado === 'CERRADA'"
                                        type="button"
                                        class="btn-primary"
                                        @click="abrirModal(item, 'REABRIR')"
                                    >
                                        Reabrir excepcionalmente
                                    </button>

                                    <button
                                        v-if="['PENDIENTE_AUTORIZACION', 'AUTORIZADA'].includes(item.estado)"
                                        type="button"
                                        class="btn-danger"
                                        @click="abrirModal(item, 'CERRAR')"
                                    >
                                        Cerrar corrección
                                    </button>

                                    <span
                                        v-if="item.estado === 'REENTREGADA'"
                                        class="text-muted"
                                    >
                                        El estudiante ya reenvi&oacute; su documento.
                                    </span>

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
                        <strong>Observaciones del jurado:</strong><br>
                        {{ itemSeleccionado.motivo }}
                    </p>
                </div>

                <form @submit.prevent="guardarGestion">

                    <div
                        v-if="['AUTORIZAR', 'REABRIR'].includes(accionModal)"
                        class="form-group"
                    >
                        <label>Nueva fecha límite de corrección</label>

                        <input
                            type="datetime-local"
                            v-model="form.fecha_limite_correccion"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>
                            {{
                                accionModal === 'CERRAR'
                                    ? 'Motivo de cierre'
                                    : 'Mensaje para el estudiante'
                            }}
                        </label>

                        <textarea
                            rows="5"
                            v-model="form.observacion"
                            :placeholder="
                                accionModal === 'CERRAR'
                                    ? 'Explique por qué se cierra la corrección...'
                                    : 'Ej.: Se autoriza la nueva entrega hasta la fecha indicada.'
                            "
                        ></textarea>
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
                            {{ guardando ? 'Guardando...' : textoBotonModal }}
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>

</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/reentregas.js?v=1"></script>