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
                pendientes_autorizacion: 0,
                autorizadas: 0,
                reentregadas: 0,
                cerradas: 0,
            },

            items: [],

            modalAbierto: false,
            accionModal: '',
            itemSeleccionado: null,

            form: {
                fecha_limite_correccion: '',
                observacion: '',
            },
        };
    },

    computed: {
        itemsFiltrados() {
            const termino = this.busqueda.trim().toLowerCase();
            const estadoFiltro = String(this.filtroEstado || 'todos').toUpperCase();

            return this.items.filter((item) => {
                const estadoCoincide =
                    estadoFiltro === 'TODOS' ||
                    String(item.estado || '').toUpperCase() === estadoFiltro;

                if (!estadoCoincide) {
                    return false;
                }

                if (!termino) {
                    return true;
                }

                const contenido = [
                    item.estudiante,
                    item.usuario_estudiante,
                    item.nombre_programa,
                    item.tipo_programa,
                    item.gestion_externa,
                    item.titulo_trabajo,
                    item.nombre_fase,
                    item.jurado_responsable,
                    item.motivo,
                    item.estado,
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                return contenido.includes(termino);
            });
        },

        tituloModal() {
            const titulos = {
                AUTORIZAR: 'Autorizar nueva entrega',
                CERRAR: 'Cerrar corrección',
                REABRIR: 'Reabrir corrección excepcionalmente',
            };

            return titulos[this.accionModal] || 'Gestión de corrección';
        },

        textoBotonModal() {
            const textos = {
                AUTORIZAR: 'Autorizar entrega',
                CERRAR: 'Cerrar corrección',
                REABRIR: 'Reabrir corrección',
            };

            return textos[this.accionModal] || 'Guardar';
        },
    },

    methods: {
        async cargarReentregas() {
            this.cargando = true;
            this.error = '';

            try {
                const respuesta = await fetch(
                    `${this.baseUrl}/api/admin/reentregas_listar.php`,
                    {
                        credentials: 'same-origin',
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message ||
                        'No fue posible cargar las solicitudes de corrección.'
                    );
                }

                this.items = Array.isArray(datos.data) ? datos.data : [];

                this.stats = {
                    pendientes_autorizacion: Number(
                        datos.stats?.pendientes_autorizacion || 0
                    ),
                    autorizadas: Number(datos.stats?.autorizadas || 0),
                    reentregadas: Number(datos.stats?.reentregadas || 0),
                    cerradas: Number(datos.stats?.cerradas || 0),
                };
            } catch (error) {
                this.error =
                    error.message ||
                    'Ocurrió un error al cargar las solicitudes de corrección.';

                this.items = [];
            } finally {
                this.cargando = false;
            }
        },

        abrirModal(item, accion) {
            this.itemSeleccionado = item;
            this.accionModal = accion;

            this.form = {
                fecha_limite_correccion:
                    accion === 'AUTORIZAR' && item.fecha_limite_correccion
                        ? this.fechaParaInput(item.fecha_limite_correccion)
                        : '',
                observacion:
                    accion === 'AUTORIZAR'
                        ? ''
                        : accion === 'CERRAR'
                            ? ''
                            : '',
            };

            this.modalAbierto = true;
        },

        cerrarModal() {
            if (this.guardando) {
                return;
            }

            this.modalAbierto = false;
            this.accionModal = '';
            this.itemSeleccionado = null;

            this.form = {
                fecha_limite_correccion: '',
                observacion: '',
            };
        },

        async guardarGestion() {
            if (!this.itemSeleccionado || this.guardando) {
                return;
            }

            if (
                ['AUTORIZAR', 'REABRIR'].includes(this.accionModal) &&
                !this.form.fecha_limite_correccion
            ) {
                alert('Define la nueva fecha límite para la corrección.');
                return;
            }

            if (
                this.accionModal === 'CERRAR' &&
                this.form.observacion.trim() === ''
            ) {
                alert('Debes indicar el motivo de cierre.');
                return;
            }

            this.guardando = true;

            try {
                const formData = new FormData();

                formData.append(
                    'id_control',
                    this.itemSeleccionado.id_control
                );

                formData.append('accion', this.accionModal);

                formData.append(
                    'fecha_limite_correccion',
                    this.form.fecha_limite_correccion
                );

                formData.append(
                    'observacion',
                    this.form.observacion.trim()
                );

                const respuesta = await fetch(
                    `${this.baseUrl}/api/admin/reentregas_guardar.php`,
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
                        'No fue posible guardar la gestión de corrección.'
                    );
                }

                alert(
                    datos.message ||
                    'La gestión de corrección fue registrada correctamente.'
                );

                this.cerrarModal();
                await this.cargarReentregas();
            } catch (error) {
                alert(
                    error.message ||
                    'Ocurrió un error al gestionar la corrección.'
                );
            } finally {
                this.guardando = false;
            }
        },

        textoEstado(estado) {
            const etiquetas = {
                PENDIENTE_AUTORIZACION: 'Pendiente de autorización',
                AUTORIZADA: 'Nueva entrega autorizada',
                REENTREGADA: 'Documento corregido enviado',
                CERRADA: 'Corrección cerrada',
            };

            return etiquetas[String(estado || '').toUpperCase()] || 'Sin estado';
        },

        claseEstado(estado) {
            const normalizado = String(estado || '').toUpperCase();

            return {
                'estado-pendiente':
                    normalizado === 'PENDIENTE_AUTORIZACION',

                'estado-aprobado':
                    normalizado === 'AUTORIZADA' ||
                    normalizado === 'REENTREGADA',

                'estado-rechazado':
                    normalizado === 'CERRADA',
            };
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

        fechaParaInput(fecha) {
            if (!fecha) {
                return '';
            }

            return String(fecha)
                .replace(' ', 'T')
                .slice(0, 16);
        },
    },

    mounted() {
        this.cargarReentregas();
    },
}).mount('#app-reentregas');