const { createApp } = Vue;

createApp({
    data() {
        return {
            baseUrl: '/SIGET_ESAM',

            cargando: false,
            guardando: false,
            error: '',
            busqueda: '',
            filtroEstado: 'todos',

            stats: {
                pendientes: 0,
                en_revision: 0,
                observados: 0,
                corregidos: 0,
                revisados: 0,
            },

            items: [],

            modalAbierto: false,
            itemSeleccionado: null,

            form: {
                accion: '',
                comentario: '',
            },
        };
    },

    computed: {
        itemsFiltrados() {
            const termino = this.busqueda.trim().toLowerCase();

            if (!termino) {
                return this.items;
            }

            return this.items.filter((item) => {
                const contenido = [
                    item.estudiante,
                    item.usuario_estudiante,
                    item.nombre_programa,
                    item.tipo_programa,
                    item.gestion_externa,
                    item.titulo_trabajo,
                    item.nombre_fase,
                    item.estado_documento,
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                return contenido.includes(termino);
            });
        },
    },

    methods: {
        async cargarEvaluaciones() {
            this.cargando = true;
            this.error = '';

            try {
                const respuesta = await fetch(
                    `${this.baseUrl}/api/profesor/evaluaciones_listar.php?estado=${encodeURIComponent(this.filtroEstado)}`,
                    {
                        credentials: 'same-origin',
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message || 'No fue posible cargar los trabajos asignados.'
                    );
                }

                this.items = Array.isArray(datos.data) ? datos.data : [];

                this.stats = {
                    pendientes: Number(datos.stats?.pendientes || 0),
                    en_revision: Number(datos.stats?.en_revision || 0),
                    observados: Number(datos.stats?.observados || 0),
                    corregidos: Number(datos.stats?.corregidos || 0),
                    revisados: Number(datos.stats?.revisados || 0),
                };
            } catch (error) {
                this.error =
                    error.message ||
                    'Ocurrió un error al cargar los trabajos asignados.';

                this.items = [];
            } finally {
                this.cargando = false;
            }
        },

        abrirModal(item) {
            this.itemSeleccionado = item;

            const estado = String(item?.estado_documento || '').toUpperCase();

            this.form = {
                accion: ['APROBADO', 'REVISADO'].includes(estado)
                    ? 'APROBAR'
                    : ['RECHAZADO', 'OBSERVADO'].includes(estado)
                        ? 'RECHAZAR'
                        : '',
                comentario: item.comentario_revision ?? '',
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
                accion: '',
                comentario: '',
            };
        },

        async guardarEvaluacion() {
            if (!this.itemSeleccionado || this.guardando) {
                return;
            }

            if (!this.form.accion) {
                alert('Seleccione una decisión para el avance.');
                return;
            }

            if (
                this.form.accion === 'RECHAZAR' &&
                this.form.comentario.trim() === ''
            ) {
                alert(
                    'Debe escribir las observaciones antes de rechazar el avance.'
                );
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
                    'accion',
                    this.form.accion
                );

                formData.append(
                    'comentario',
                    this.form.comentario.trim()
                );

                const respuesta = await fetch(
                    `${this.baseUrl}/api/profesor/evaluaciones_guardar.php`,
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
                        'No fue posible guardar la revisión.'
                    );
                }

                alert(
                    datos.message ||
                    'La revisión del avance se guardó correctamente.'
                );

                this.cerrarModal();
                await this.cargarEvaluaciones();

            } catch (error) {
                alert(
                    error.message ||
                    'Ocurrió un error al guardar la revisión.'
                );
            } finally {
                this.guardando = false;
            }
        },

        puedeEvaluar(item) {
            const estado = String(item?.estado_documento || '').toUpperCase();

            return [
                'PENDIENTE',
                'EN_REVISION',
                'CORREGIDO',
            ].includes(estado);
        },

        esEvaluacionRegistrada(item) {
            const estado = String(item?.estado_documento || '').toUpperCase();

            return [
                'APROBADO',
                'RECHAZADO',
                'REVISADO',
                'OBSERVADO',
            ].includes(estado);
        },

        textoEstado(estado) {
            const etiquetas = {
                PENDIENTE: 'Pendiente',
                EN_REVISION: 'En revisión',
                OBSERVADO: 'Con observaciones',
                RECHAZADO: 'Con observaciones',
                CORREGIDO: 'Corregido',
                REVISADO: 'Avance validado',
                APROBADO: 'Avance validado',
            };

            return etiquetas[String(estado || '').toUpperCase()] || 'Pendiente';
        },

        claseEstado(estado) {
            const estadoNormalizado = String(estado || '').toUpperCase();

            return {
                'estado-borrador': estadoNormalizado === 'PENDIENTE',
                'estado-pendiente': estadoNormalizado === 'EN_REVISION',
                'estado-observado':
                    estadoNormalizado === 'OBSERVADO' ||
                    estadoNormalizado === 'RECHAZADO',
                'estado-aprobado':
                    estadoNormalizado === 'REVISADO' ||
                    estadoNormalizado === 'APROBADO',
                'estado-corregido': estadoNormalizado === 'CORREGIDO',
            };
        },

        rutaArchivo(ruta) {
            if (!ruta) {
                return '#';
            }

            return `${this.baseUrl}/${String(ruta).replace(/^\/+/, '')}`;
        },

        formatearFecha(fecha, incluirHora = false) {
            if (!fecha) {
                return 'No definida';
            }

            const fechaNormalizada = String(fecha).replace(' ', 'T');
            const objetoFecha = new Date(fechaNormalizada);

            if (Number.isNaN(objetoFecha.getTime())) {
                return fecha;
            }

            const opciones = {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            };

            if (incluirHora) {
                opciones.hour = '2-digit';
                opciones.minute = '2-digit';
                opciones.hour12 = false;
            }

            return objetoFecha.toLocaleString('es-BO', opciones);
        },
    },

    mounted() {
        this.cargarEvaluaciones();
    },
}).mount('#app-evaluaciones-jurado');