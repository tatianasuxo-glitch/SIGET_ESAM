const { createApp } = Vue;

createApp({
    data() {
        return {
            baseUrl: '/SIGET_ESAM',
            cargando: false,
            guardando: false,
            error: '',
            mensaje: '',
            tipoMensaje: 'success',
            filtroEstado: 'pendientes',
            busqueda: '',
            stats: {
                pendientes: 0,
                observados: 0,
                revisados: 0,
            },
            jurados: [],
            items: [],
            modalAbierto: false,
            accionModal: '',
            itemSeleccionado: null,
            form: {
                id_docente: '',
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
                    item.usuario,
                    item.ci,
                    item.nombre_programa,
                    item.tipo_programa,
                    item.titulo_trabajo,
                    item.nombre_fase,
                    item.numero_fase,
                    item.estado_aprobacion,
                    item.jurado_asignado,
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                return contenido.includes(termino);
            });
        },

        tituloModal() {
            return this.accionModal === 'validar'
                ? 'Validar propuesta y asignar jurado'
                : 'Registrar observaciones';
        },

        textoAccionModal() {
            return this.accionModal === 'validar'
                ? 'Validar y habilitar Fase 2'
                : 'Guardar observaciones';
        },
    },

    methods: {
        async cargarItems() {
            this.cargando = true;
            this.error = '';

            try {
                const respuesta = await fetch(
                    `${this.baseUrl}/api/admin/revision_listar.php?estado=${encodeURIComponent(this.filtroEstado)}`,
                    { credentials: 'same-origin' }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message || 'No fue posible cargar las propuestas.'
                    );
                }

                this.items = Array.isArray(datos.data) ? datos.data : [];
                this.jurados = Array.isArray(datos.jurados) ? datos.jurados : [];

                this.stats = {
                    pendientes: Number(datos.stats?.pendientes || 0),
                    observados: Number(datos.stats?.observados || 0),
                    revisados: Number(datos.stats?.revisados || 0),
                };
            } catch (error) {
                this.error =
                    error.message || 'Ocurrió un error al cargar las propuestas.';

                this.items = [];
                this.jurados = [];
            } finally {
                this.cargando = false;
            }
        },

        cambiarFiltro(filtro) {
            this.filtroEstado = filtro;
            this.busqueda = '';
            this.cargarItems();
        },

        abrirModal(item, accion) {
            if (!this.esRevisable(item)) {
                alert(
                    'Solo se pueden revisar propuestas en revisión o con versión corregida.'
                );
                return;
            }

            this.itemSeleccionado = item;
            this.accionModal = accion;

            this.form = {
                id_docente: '',
                comentario: '',
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
                id_docente: '',
                comentario: '',
            };
        },

        async guardarRevision() {
            if (!this.itemSeleccionado || this.guardando) {
                return;
            }

            if (this.accionModal === 'validar' && !this.form.id_docente) {
                alert(
                    'Selecciona el jurado responsable para las Fases 2 y 3.'
                );
                return;
            }

            if (
                this.accionModal === 'observar' &&
                this.form.comentario.trim().length < 5
            ) {
                alert('Registra observaciones de al menos 5 caracteres.');
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
                    this.accionModal === 'validar'
                        ? 'VALIDAR'
                        : 'OBSERVAR'
                );

                formData.append('id_docente', this.form.id_docente);
                formData.append(
                    'comentario',
                    this.form.comentario.trim()
                );

                const respuesta = await fetch(
                    `${this.baseUrl}/api/admin/revision_guardar.php`,
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message || 'No fue posible guardar la revisión.'
                    );
                }

                this.mensaje =
                    datos.message || 'La revisión se guardó correctamente.';

                this.tipoMensaje = 'success';

                this.cerrarModal();
                await this.cargarItems();
            } catch (error) {
                this.mensaje =
                    error.message ||
                    'Ocurrió un error al guardar la revisión.';

                this.tipoMensaje = 'error';
            } finally {
                this.guardando = false;
            }
        },

        esRevisable(item) {
            const estado = String(
                item?.estado_aprobacion || ''
            ).toUpperCase();

            return ['EN_REVISION', 'CORREGIDO'].includes(estado);
        },

        esValidada(item) {
            return ['REVISADO', 'APROBADO'].includes(
                String(item?.estado_aprobacion || '').toUpperCase()
            );
        },

        rutaArchivo(ruta) {
            if (!ruta) {
                return '#';
            }

            return `${this.baseUrl}/${String(ruta).replace(/^\/+/, '')}`;
        },

        textoEstado(estado) {
            const etiquetas = {
                BORRADOR: 'Pendiente',
                EN_REVISION: 'En revisión',
                OBSERVADO: 'Con observaciones',
                RECHAZADO: 'Con observaciones',
                CORREGIDO: 'Versión corregida',
                REVISADO: 'Validado',
                APROBADO: 'Validado',
            };

            return (
                etiquetas[String(estado || '').toUpperCase()] || 'Pendiente'
            );
        },

        claseEstado(estado) {
            const normalizado = String(estado || '').toLowerCase();

            return {
                'estado-borrador':
                    normalizado === 'borrador' ||
                    normalizado === 'pendiente',

                'estado-pendiente':
                    normalizado === 'en_revision',

                'estado-observado':
                    normalizado === 'observado' ||
                    normalizado === 'rechazado',

                'estado-corregido':
                    normalizado === 'corregido',

                'estado-aprobado':
                    normalizado === 'revisado' ||
                    normalizado === 'aprobado',
            };
        },
    },

    mounted() {
        this.cargarItems();
    },
}).mount('#app-revision-admin');