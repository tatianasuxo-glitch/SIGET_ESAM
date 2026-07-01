const { createApp } = Vue;

createApp({
    data() {
        return {
            baseUrl: '/SIGET_ESAM',

            cargando: false,
            guardando: false,
            error: '',
            busqueda: '',

            stats: {
                pendientes: 0,
                asignados: 0,
                fase_2_habilitada: 0,
            },

            participantes: [],
            jurados: [],

            modalAbierto: false,
            itemSeleccionado: null,

            form: {
                id_docente: '',
                observacion: '',
            },
        };
    },

    computed: {
        participantesFiltrados() {
            const termino = this.busqueda.trim().toLowerCase();

            if (!termino) {
                return this.participantes;
            }

            return this.participantes.filter((item) => {
                const contenido = [
                    item.estudiante,
                    item.usuario,
                    item.nombre_programa,
                    item.gestion_externa,
                    item.titulo_trabajo,
                    item.jurado_asignado,
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                return contenido.includes(termino);
            });
        },
    },

    methods: {
        async cargarParticipantes() {
            this.cargando = true;
            this.error = '';

            try {
                const respuesta = await fetch(
                    `${this.baseUrl}/api/admin/asignacion_jurados_listar.php`,
                    {
                        credentials: 'same-origin',
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message || 'No fue posible cargar las asignaciones.'
                    );
                }

                this.participantes = Array.isArray(datos.data)
                    ? datos.data
                    : [];

                this.jurados = Array.isArray(datos.jurados)
                    ? datos.jurados
                    : [];

                this.stats = {
                    pendientes: Number(datos.stats?.pendientes || 0),
                    asignados: Number(datos.stats?.asignados || 0),
                    fase_2_habilitada: Number(
                        datos.stats?.fase_2_habilitada || 0
                    ),
                };
            } catch (error) {
                this.error =
                    error.message ||
                    'Ocurrió un error al cargar los participantes.';
            } finally {
                this.cargando = false;
            }
        },

        abrirModal(item) {
            this.itemSeleccionado = item;

            this.form = {
                id_docente: item.id_jurado_actual
                    ? String(item.id_jurado_actual)
                    : '',
                observacion: item.observacion_jurado || '',
            };

            this.modalAbierto = true;
        },

        cerrarModal() {
            if (this.guardando) {
                return;
            }

            this.modalAbierto = false;
            this.itemSeleccionado = null;

            this.form = {
                id_docente: '',
                observacion: '',
            };
        },

        async guardarAsignacion() {
            if (!this.itemSeleccionado) {
                return;
            }

            if (!this.form.id_docente) {
                alert('Seleccione un jurado responsable.');
                return;
            }

            this.guardando = true;

            try {
                const formData = new FormData();

                formData.append(
                    'id_trabajo',
                    this.itemSeleccionado.id_trabajo
                );

                formData.append(
                    'id_docente',
                    this.form.id_docente
                );

                formData.append(
                    'observacion',
                    this.form.observacion
                );

                const respuesta = await fetch(
                    `${this.baseUrl}/api/admin/asignacion_jurados_guardar.php`,
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message ||
                        'No fue posible guardar la asignación.'
                    );
                }

                alert(
                    datos.message ||
                    'Jurado asignado y Fase 2 habilitada correctamente.'
                );

                this.cerrarModal();
                await this.cargarParticipantes();

            } catch (error) {
                alert(
                    error.message ||
                    'Ocurrió un error al guardar la asignación.'
                );
            } finally {
                this.guardando = false;
            }
        },
    },

    mounted() {
        this.cargarParticipantes();
    },
}).mount('#app-asignacion-jurados');