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

$idInscripcion = (int) ($_GET['id_inscripcion'] ?? 0);

if ($idInscripcion <= 0) {
    ?>
    <section class="page-card">
        <h1>Expediente no identificado</h1>
        <p>No se recibió una inscripción válida para abrir el expediente.</p>

        <a
            href="/SIGET_ESAM/index.php?page=admin/procesos/index"
            class="btn-view"
        >
            Volver a Procesos de titulación
        </a>
    </section>
    <?php
    return;
}
?>

<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_expediente.css?v=1">

<section
    class="expediente-screen"
    id="app-expediente-admin"
    data-id-inscripcion="<?= $idInscripcion ?>"
    v-cloak
>
    <div class="expediente-shell">

        <div class="expediente-topbar">
            <a
                href="/SIGET_ESAM/index.php?page=admin/procesos/index"
                class="btn-secondary"
            >
                ← Volver a procesos
            </a>

            <span class="expediente-codigo">
                Expediente de titulación
            </span>
        </div>

        <div v-if="cargando" class="empty-state">
            Cargando expediente del participante...
        </div>

        <div v-else-if="error" class="alert error">
            {{ error }}
        </div>

        <template v-else-if="contexto">

            <section class="expediente-hero">

                <div class="hero-principal">
                    <span class="hero-etiqueta">
                        {{ textoTipo(contexto.tipo_programa) }}
                    </span>

                    <h1>{{ contexto.estudiante }}</h1>

                    <p>
                        {{ contexto.nombre_programa }}
                        · Gestión {{ contexto.gestion_externa || 'Sin gestión' }}
                        · Versión {{ contexto.version_programa_externa || '-' }}
                    </p>

                    <div class="hero-datos">
                        <span>
                            <strong>CI:</strong>
                            {{ contexto.ci || 'Sin registro' }}
                        </span>

                        <span>
                            <strong>Código:</strong>
                            {{ contexto.codigo_participante_externo || contexto.usuario }}
                        </span>

                        <span>
                            <strong>Sede:</strong>
                            {{ contexto.nombre_sede }}
                        </span>
                    </div>
                </div>

                <div class="hero-estado">
                    <span class="estado-label">Siguiente acción</span>

                    <strong>{{ siguienteAccion.texto }}</strong>

                    <span>
                        Responsable: {{ siguienteAccion.responsable }}
                    </span>
                </div>

            </section>

            <section class="resumen-expediente">

                <div class="resumen-card">
                    <span>Programa</span>
                    <strong>{{ contexto.nombre_programa }}</strong>
                    <small>{{ textoTipo(contexto.tipo_programa) }}</small>
                </div>

                <div class="resumen-card">
                    <span>Estado académico</span>
                    <strong>{{ contexto.estado_academico || 'Sin registro' }}</strong>
                    <small>{{ contexto.estado_acceso || 'Sin acceso definido' }}</small>
                </div>

                <div class="resumen-card">
                    <span>Jurado Fases 2 y 3</span>
                    <strong>{{ jurado?.nombre_completo || 'Pendiente de asignación' }}</strong>
                    <small>
                        {{ jurado?.rol_jurado || 'Sin jurado registrado' }}
                    </small>
                </div>

                <div class="resumen-card">
                    <span>Proceso</span>
                    <strong>{{ contexto.estado_proceso || 'En curso' }}</strong>
                    <small>
                        {{ contexto.estado_jurado || 'Sin estado de jurado' }}
                    </small>
                </div>

            </section>

            <section class="accion-principal">
                <div>
                    <span>Acción pendiente</span>
                    <h2>{{ siguienteAccion.texto }}</h2>
                    <p>
                        Esta acción corresponde a la Fase
                        {{ siguienteAccion.fase || '-' }} y está a cargo de
                        <strong>{{ siguienteAccion.responsable }}</strong>.
                    </p>
                </div>

                <span class="accion-fase">
                    Fase {{ siguienteAccion.fase || '-' }}
                </span>
            </section>

            <section class="fases-seccion">

                <div class="seccion-encabezado">
                    <div>
                        <span>Proceso académico</span>
                        <h2>Fases del expediente</h2>
                        <p>
                            Consulta documentos, entregas, observaciones y la acción
                            pendiente de cada fase desde una sola pantalla.
                        </p>
                    </div>
                </div>

                <div class="fases-lista">

                    <article
                        v-for="fase in fases"
                        :key="fase.id_configuracion"
                        class="fase-card"
                        :class="'fase-' + fase.numero_fase"
                    >
                        <div class="fase-cabecera">

                            <div class="fase-numero">
                                {{ fase.numero_fase }}
                            </div>

                            <div class="fase-titulo">
                                <span>Fase {{ fase.numero_fase }}</span>
                                <h3>{{ nombreVisualFase(fase) }}</h3>
                                <p>{{ fase.tipo_trabajo || 'Trabajo académico' }}</p>
                            </div>

                            <span
                                class="estado-badge"
                                :class="claseEstado(fase.estado)"
                            >
                                {{ fase.estado_etiqueta }}
                            </span>

                        </div>

                        <div class="fase-contenido">

                            <div class="fase-detalle">
                                <span>Responsable actual</span>
                                <strong>{{ fase.accion?.responsable || 'Administración Académica' }}</strong>
                            </div>

                            <div class="fase-detalle">
                                <span>Acción actual</span>
                                <strong>{{ fase.accion?.texto || 'Sin acción pendiente' }}</strong>
                            </div>

                            <div class="fase-detalle">
                                <span>Plazo de entrega</span>
                                <strong>
                                    {{ formatearFecha(fase.fecha_limite_entrega) }}
                                </strong>
                            </div>

                            <div class="fase-detalle">
                                <span>Plazo de revisión</span>
                                <strong>
                                    {{ formatearFecha(fase.fecha_limite_revision) }}
                                </strong>
                            </div>

                        </div>

                        <div v-if="fase.trabajo" class="fase-trabajo">

                            <div class="documento-info">
                                <span>Documento registrado</span>
                                <strong>{{ fase.trabajo.titulo_trabajo || 'Sin título registrado' }}</strong>

                                <small>
                                    Presentado:
                                    {{ formatearFecha(fase.trabajo.fecha_presentacion) }}
                                </small>
                            </div>

                            <a
                                v-if="rutaDocumento(fase)"
                                :href="rutaDocumento(fase)"
                                target="_blank"
                                class="btn-view"
                            >
                                Ver documento
                            </a>

                        </div>

                        <div v-if="fase.ultima_revision" class="revision-actual">
                            <span>Última revisión</span>

                            <strong>
                                {{ fase.ultima_revision.revisor || 'Revisor registrado' }}
                                · {{ textoDecision(fase.ultima_revision.decision) }}
                            </strong>

                            <p>
                                {{ fase.ultima_revision.comentario || 'Sin comentario registrado.' }}
                            </p>
                        </div>

                        <div v-if="fase.control_reentrega" class="control-correccion">
                            <span>Control de corrección</span>

                            <strong>
                                Ciclo {{ fase.control_reentrega.ciclo }}
                                · {{ textoControlReentrega(fase.control_reentrega.estado) }}
                            </strong>

                            <p v-if="fase.control_reentrega.motivo">
                                {{ fase.control_reentrega.motivo }}
                            </p>
                        </div>

                        <div
                            v-if="fase.observacion_habilitacion"
                            class="observacion-fase"
                        >
                            <strong>Observación administrativa:</strong>
                            {{ fase.observacion_habilitacion }}
                        </div>

                    </article>

                </div>

            </section>

        </template>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/expediente.js?v=1"></script>