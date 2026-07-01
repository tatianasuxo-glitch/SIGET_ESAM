<link
    rel="stylesheet"
    href="/SIGET_ESAM/assets/css/admin_participantes_diplomados.css?v=1"
>

<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rolActual = strtolower(trim((string) ($_SESSION['rol'] ?? '')));

if ($rolActual !== 'administrador') {
    echo '<h2>Acceso restringido</h2>';
    echo '<p>Solo el administrador puede habilitar participantes.</p>';
    return;
}

?>

<section id="app-participantes-diplomados">

    <h1>Participantes de Diplomados</h1>

    <p>
        Filtra participantes por estado académico y cartera. Selecciona solamente a
        quienes cumplen los requisitos y habilita la Fase 1 de forma masiva.
    </p>

    <p v-if="cargando">Cargando participantes...</p>

    <p v-if="error">{{ error }}</p>

    <div v-if="!cargando && !error">

        <h3>Resumen</h3>

        <p>
            Total: <strong>{{ stats.total || 0 }}</strong> |
            Habilitables: <strong>{{ stats.habilitables || 0 }}</strong> |
            Ya habilitados: <strong>{{ stats.ya_habilitados || 0 }}</strong> |
            No habilitables: <strong>{{ stats.no_habilitables || 0 }}</strong>
        </p>

        <hr>

        <label>
            Buscar:
            <input
                type="text"
                v-model="busqueda"
                placeholder="Nombre, CI, usuario o programa"
            >
        </label>

        <label>
            Estado académico:
            <select v-model="filtroAcademico">
                <option value="">Todos</option>
                <option value="CONCLUIDO">CONCLUIDO</option>
                <option value="EN_DESARROLLO">EN_DESARROLLO</option>
                <option value="REPROBADO">REPROBADO</option>
            </select>
        </label>

        <label>
            Estado de cartera:
            <select v-model="filtroCartera">
                <option value="">Todos</option>
                <option value="VIGENTE">VIGENTE</option>
                <option value="EXENTO_DE_DEUDA">EXENTO_DE_DEUDA</option>
                <option value="EN_MORA">EN_MORA</option>
                <option value="RETRASADO">RETRASADO</option>
            </select>
        </label>

        <label>
            Situación:
            <select v-model="filtroEstado">
                <option value="">Todos</option>
                <option value="habilitable">Puede habilitarse</option>
                <option value="habilitado">Fase 1 habilitada</option>
                <option value="no_habilitable">No habilitable</option>
            </select>
        </label>

        <button type="button" @click="filtrarHabilitables">
            Ver solo habilitables
        </button>

        <button type="button" @click="limpiarFiltros">
            Limpiar filtros
        </button>

        <hr>

        <p>
            Habilitables con los filtros actuales:
            <strong>{{ participantesElegiblesFiltrados.length }}</strong>
        </p>

        <button
            type="button"
            @click="seleccionarHabilitablesFiltrados"
            :disabled="participantesElegiblesFiltrados.length === 0"
        >
            Seleccionar habilitables filtrados
        </button>

        <button
            type="button"
            @click="limpiarSeleccion"
            :disabled="seleccionados.length === 0"
        >
            Limpiar selección
        </button>

        <button
            type="button"
            @click="habilitarSeleccionados"
            :disabled="seleccionados.length === 0 || procesandoMasivo"
        >
            {{
                procesandoMasivo
                    ? 'Habilitando seleccionados...'
                    : 'Habilitar Fase 1 seleccionados (' + seleccionados.length + ')'
            }}
        </button>

        <br><br>

        <p v-if="participantesFiltrados.length === 0">
            No existen participantes con los filtros seleccionados.
        </p>

        <table
            v-else
            border="1"
            cellpadding="8"
            cellspacing="0"
            width="100%"
        >
            <thead>
                <tr>
                    <th>
                        <input
                            type="checkbox"
                            :checked="todosElegiblesFiltradosSeleccionados"
                            :disabled="participantesElegiblesFiltrados.length === 0"
                            @change="alternarTodosElegiblesFiltrados($event)"
                        >
                    </th>
                    <th>Participante</th>
                    <th>CI</th>
                    <th>Diplomado</th>
                    <th>Gestión</th>
                    <th>Académico</th>
                    <th>Cartera</th>
                    <th>Acceso</th>
                    <th>Fase 1</th>
                    <th>Estado</th>
                    <th>Acción individual</th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="participante in participantesFiltrados"
                    :key="participante.id_inscripcion_interna"
                >
                    <td>
                        <input
                            type="checkbox"
                            :checked="estaSeleccionado(participante)"
                            :disabled="!participante.puede_habilitar"
                            @change="alternarSeleccion(participante, $event)"
                        >
                    </td>

                    <td>
                        <strong>{{ participante.nombre_completo }}</strong>
                        <br>
                        <small>Usuario: {{ participante.usuario }}</small>
                        <br>
                        <small>{{ participante.correo || 'Sin correo' }}</small>
                    </td>

                    <td>{{ participante.ci || 'Sin CI' }}</td>

                    <td>
                        {{ participante.nombre_programa }}
                        <br>
                        <small>Versión: {{ participante.version_programa || 'No definida' }}</small>
                    </td>

                    <td>{{ participante.gestion }}</td>
                    <td>{{ participante.estado_academico }}</td>
                    <td>{{ participante.estado_cartera }}</td>
                    <td>{{ participante.estado_acceso }}</td>

                    <td>
                        <template v-if="participante.fase_1">
                            {{ participante.fase_1.nombre_fase }}
                            <br>
                            <small>
                                Inicio:
                                {{ formatoFecha(participante.fase_1.fecha_inicio_entrega) }}
                            </small>
                            <br>
                            <small>
                                Límite:
                                {{ formatoFecha(participante.fase_1.fecha_limite_entrega) }}
                            </small>
                        </template>

                        <span v-else>Sin configuración activa</span>
                    </td>

                    <td>
                        <strong>{{ textoEstado(participante) }}</strong>

                        <ul
                            v-if="
                                !participante.es_habilitable &&
                                participante.motivos_no_habilitacion &&
                                participante.motivos_no_habilitacion.length
                            "
                        >
                            <li
                                v-for="motivo in participante.motivos_no_habilitacion"
                                :key="motivo"
                            >
                                {{ motivo }}
                            </li>
                        </ul>
                    </td>

                    <td>
                        <button
                            v-if="participante.puede_habilitar"
                            type="button"
                            :disabled="procesandoIndividual === participante.id_inscripcion_interna"
                            @click="habilitarUno(participante)"
                        >
                            {{
                                procesandoIndividual === participante.id_inscripcion_interna
                                    ? 'Habilitando...'
                                    : 'Habilitar Fase 1'
                            }}
                        </button>

                        <span v-else-if="participante.ya_habilitado">
                            Fase 1 ya habilitada
                        </span>

                        <span v-else>No habilitable</span>
                    </td>
                </tr>
            </tbody>
        </table>

    </div>

</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const contenedor = document.querySelector('#app-participantes-diplomados');

    if (!contenedor || typeof Vue === 'undefined') {
        return;
    }

    const BASE_URL = '/SIGET_ESAM';

    Vue.createApp({
        data() {
            return {
                participantes: [],
                stats: {},
                cargando: true,
                error: '',
                busqueda: '',
                filtroAcademico: '',
                filtroCartera: '',
                filtroEstado: '',
                seleccionados: [],
                procesandoIndividual: null,
                procesandoMasivo: false
            };
        },

        computed: {
            participantesFiltrados() {
                const texto = this.busqueda.trim().toLowerCase();

                return this.participantes.filter((participante) => {
                    const contenido = [
                        participante.nombre_completo,
                        participante.usuario,
                        participante.ci,
                        participante.correo,
                        participante.nombre_programa,
                        participante.gestion,
                        participante.estado_academico,
                        participante.estado_cartera,
                        participante.estado_acceso
                    ].join(' ').toLowerCase();

                    const coincideBusqueda =
                        texto === '' || contenido.includes(texto);

                    const coincideAcademico =
                        this.filtroAcademico === '' ||
                        participante.estado_academico === this.filtroAcademico;

                    const coincideCartera =
                        this.filtroCartera === '' ||
                        participante.estado_cartera === this.filtroCartera;

                    let coincideEstado = true;

                    if (this.filtroEstado === 'habilitable') {
                        coincideEstado = participante.puede_habilitar === true;
                    }

                    if (this.filtroEstado === 'habilitado') {
                        coincideEstado = participante.ya_habilitado === true;
                    }

                    if (this.filtroEstado === 'no_habilitable') {
                        coincideEstado =
                            participante.es_habilitable === false &&
                            participante.ya_habilitado === false;
                    }

                    return (
                        coincideBusqueda &&
                        coincideAcademico &&
                        coincideCartera &&
                        coincideEstado
                    );
                });
            },

            participantesElegiblesFiltrados() {
                return this.participantesFiltrados.filter((participante) => {
                    return participante.puede_habilitar === true;
                });
            },

            todosElegiblesFiltradosSeleccionados() {
                const idsElegibles = this.participantesElegiblesFiltrados.map((participante) => {
                    return String(participante.id_inscripcion_interna);
                });

                return idsElegibles.length > 0 && idsElegibles.every((id) => {
                    return this.seleccionados.includes(id);
                });
            }
        },

        mounted() {
            this.cargarParticipantes();
        },

        methods: {
            async cargarParticipantes() {
                this.cargando = true;
                this.error = '';

                try {
                    const respuesta = await fetch(
                        BASE_URL + '/api/admin/participantes_diplomados_listar.php',
                        {
                            credentials: 'same-origin'
                        }
                    );

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(
                            resultado.message ||
                            resultado.error ||
                            'No se pudieron cargar los participantes.'
                        );
                    }

                    this.participantes = resultado.data || [];
                    this.stats = resultado.stats || {};

                    const idsValidos = new Set(
                        this.participantes
                            .filter((participante) => participante.puede_habilitar)
                            .map((participante) => String(participante.id_inscripcion_interna))
                    );

                    this.seleccionados = this.seleccionados.filter((id) => {
                        return idsValidos.has(String(id));
                    });

                } catch (error) {
                    this.error = 'Error al cargar participantes: ' + error.message;

                } finally {
                    this.cargando = false;
                }
            },

            limpiarFiltros() {
                this.busqueda = '';
                this.filtroAcademico = '';
                this.filtroCartera = '';
                this.filtroEstado = '';
            },

            filtrarHabilitables() {
                this.filtroAcademico = 'CONCLUIDO';
                this.filtroCartera = '';
                this.filtroEstado = 'habilitable';
            },

            limpiarSeleccion() {
                this.seleccionados = [];
            },

            estaSeleccionado(participante) {
                return this.seleccionados.includes(
                    String(participante.id_inscripcion_interna)
                );
            },

            alternarSeleccion(participante, evento) {
                if (!participante.puede_habilitar) {
                    return;
                }

                const id = String(participante.id_inscripcion_interna);

                if (evento.target.checked) {
                    if (!this.seleccionados.includes(id)) {
                        this.seleccionados.push(id);
                    }
                } else {
                    this.seleccionados = this.seleccionados.filter((item) => {
                        return item !== id;
                    });
                }
            },

            seleccionarHabilitablesFiltrados() {
                const ids = this.participantesElegiblesFiltrados.map((participante) => {
                    return String(participante.id_inscripcion_interna);
                });

                this.seleccionados = Array.from(
                    new Set([...this.seleccionados, ...ids])
                );
            },

            alternarTodosElegiblesFiltrados(evento) {
                const ids = this.participantesElegiblesFiltrados.map((participante) => {
                    return String(participante.id_inscripcion_interna);
                });

                if (evento.target.checked) {
                    this.seleccionados = Array.from(
                        new Set([...this.seleccionados, ...ids])
                    );
                } else {
                    this.seleccionados = this.seleccionados.filter((id) => {
                        return !ids.includes(id);
                    });
                }
            },

            textoEstado(participante) {
                if (participante.ya_habilitado) {
                    return 'Fase 1 habilitada';
                }

                if (participante.puede_habilitar) {
                    return 'Puede habilitarse';
                }

                return 'No habilitable';
            },

            formatoFecha(valor) {
                if (!valor) {
                    return 'No definida';
                }

                const fecha = new Date(String(valor).replace(' ', 'T'));

                if (isNaN(fecha.getTime())) {
                    return valor;
                }

                return fecha.toLocaleString('es-BO', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            },

            async habilitarUno(participante) {
                if (!participante.puede_habilitar || !participante.fase_1) {
                    return;
                }

                const confirmar = confirm(
                    '¿Deseas habilitar la Fase 1 para ' +
                    participante.nombre_completo +
                    '?'
                );

                if (!confirmar) {
                    return;
                }

                this.procesandoIndividual = participante.id_inscripcion_interna;

                try {
                    const datos = new FormData();

                    datos.append('id_estudiante', participante.id_estudiante);
                    datos.append(
                        'id_configuracion',
                        participante.fase_1.id_configuracion
                    );

                    datos.append(
                        'observacion',
                        'Fase 1 habilitada por Administración.'
                    );

                    const respuesta = await fetch(
                        BASE_URL + '/api/admin/participantes_diplomados_habilitar.php',
                        {
                            method: 'POST',
                            body: datos,
                            credentials: 'same-origin'
                        }
                    );

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(
                            resultado.message ||
                            resultado.error ||
                            'No se pudo habilitar la Fase 1.'
                        );
                    }

                    alert(resultado.message);

                    await this.cargarParticipantes();

                } catch (error) {
                    alert('Error: ' + error.message);

                } finally {
                    this.procesandoIndividual = null;
                }
            },

            async habilitarSeleccionados() {
                if (this.seleccionados.length === 0) {
                    return;
                }

                const confirmar = confirm(
                    '¿Deseas habilitar la Fase 1 para ' +
                    this.seleccionados.length +
                    ' participante(s) seleccionados?'
                );

                if (!confirmar) {
                    return;
                }

                this.procesandoMasivo = true;

                try {
                    const datos = new FormData();

                    this.seleccionados.forEach((idInscripcionInterna) => {
                        datos.append(
                            'id_inscripciones_internas[]',
                            idInscripcionInterna
                        );
                    });

                    datos.append(
                        'observacion',
                        'Fase 1 habilitada masivamente por Administración.'
                    );

                    const respuesta = await fetch(
                        BASE_URL + '/api/admin/participantes_diplomados_habilitar_masivo.php',
                        {
                            method: 'POST',
                            body: datos,
                            credentials: 'same-origin'
                        }
                    );

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(
                            resultado.message ||
                            resultado.error ||
                            'No se pudo realizar la habilitación masiva.'
                        );
                    }

                    const resumen = resultado.resumen || {};

                    alert(
                        resultado.message +
                        '\n\nHabilitados: ' + (resumen.habilitados || 0) +
                        '\nReactivados: ' + (resumen.reactivados || 0) +
                        '\nYa habilitados: ' + (resumen.ya_habilitados || 0) +
                        '\nOmitidos: ' + (resumen.omitidos || 0)
                    );

                    this.seleccionados = [];

                    await this.cargarParticipantes();

                } catch (error) {
                    alert('Error: ' + error.message);

                } finally {
                    this.procesandoMasivo = false;
                }
            }
        }
    }).mount('#app-participantes-diplomados');
});
</script>
