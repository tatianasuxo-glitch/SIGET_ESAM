const { createApp } = Vue;

createApp({
    data() {
        return {
            baseUrl: '/SIGET_ESAM',

            cargando: false,
            cargandoCatalogos: false,
            error: '',
            mensaje: '',
            tipoMensaje: 'success',

            procesos: [],

            catalogos: {
                sedes: [],
                tipos: [],
                programas: [],
                gestiones: [],
                versiones: [],
            },

            filtros: {
                sede: new URLSearchParams(window.location.search).get('sede') || '',
                tipo: new URLSearchParams(window.location.search).get('tipo') || '',
                programa: new URLSearchParams(window.location.search).get('programa') || '',
                gestion: new URLSearchParams(window.location.search).get('gestion') || '',
                version: new URLSearchParams(window.location.search).get('version') || '',
                fase: new URLSearchParams(window.location.search).get('fase') || '',
                estado: new URLSearchParams(window.location.search).get('estado') || '',
                buscar: new URLSearchParams(window.location.search).get('buscar') || '',
                pagina: 1,
                por_pagina: '25',
            },

            paginacion: {
                pagina_actual: 1,
                por_pagina: 25,
                total_registros: 0,
                total_paginas: 1,
            },
        };
    },

    computed: {
        programasFiltrados() {
            return this.catalogos.programas.filter((programa) => {
                const coincideSede =
                    !this.filtros.sede ||
                    String(programa.id_sede_externa || '') === String(this.filtros.sede);

                const coincideTipo =
                    !this.filtros.tipo ||
                    String(programa.tipo_programa || '').toUpperCase() ===
                        String(this.filtros.tipo || '').toUpperCase();

                return coincideSede && coincideTipo;
            });
        },

        totalEnRevision() {
            return this.procesos.filter((proceso) => {
                return String(proceso.estado_proceso || '').toUpperCase() === 'EN_REVISION';
            }).length;
        },

        totalAccionesAdmin() {
            return this.procesos.filter((proceso) => {
                const accion = String(proceso.siguiente_accion || '').toLowerCase();

                return (
                    accion.includes('habilitar') ||
                    accion.includes('gestionar') ||
                    accion.includes('asignar') ||
                    accion.includes('registrar') ||
                    accion.includes('configurar')
                );
            }).length;
        },
    },

    methods: {
        construirParametros(extra = {}) {
            const parametros = new URLSearchParams();

            const valores = {
                ...this.filtros,
                ...extra,
            };

            Object.entries(valores).forEach(([clave, valor]) => {
                if (valor !== '' && valor !== null && valor !== undefined) {
                    parametros.append(clave, valor);
                }
            });

            return parametros.toString();
        },

        async cargarCatalogos() {
            this.cargandoCatalogos = true;

            try {
                const parametros = new URLSearchParams({
                    modo: 'catalogos',
                });

                if (this.filtros.sede) {
                    parametros.append('sede', this.filtros.sede);
                }

                if (this.filtros.tipo) {
                    parametros.append('tipo', this.filtros.tipo);
                }

                const respuesta = await fetch(
                    `${this.baseUrl}/api/admin/procesos_listar.php?${parametros.toString()}`,
                    {
                        credentials: 'same-origin',
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message || 'No fue posible cargar los filtros.'
                    );
                }

                this.catalogos = {
                    sedes: Array.isArray(datos.catalogos?.sedes)
                        ? datos.catalogos.sedes
                        : [],

                    tipos: Array.isArray(datos.catalogos?.tipos)
                        ? datos.catalogos.tipos
                        : [],

                    programas: Array.isArray(datos.catalogos?.programas)
                        ? datos.catalogos.programas
                        : [],

                    gestiones: Array.isArray(datos.catalogos?.gestiones)
                        ? datos.catalogos.gestiones
                        : [],

                    versiones: Array.isArray(datos.catalogos?.versiones)
                        ? datos.catalogos.versiones
                        : [],
                };

                const programaExiste = this.catalogos.programas.some((programa) => {
                    return String(programa.id) === String(this.filtros.programa);
                });

                if (this.filtros.programa && !programaExiste) {
                    this.filtros.programa = '';
                }
            } catch (error) {
                this.error =
                    error.message || 'No fue posible cargar los catálogos.';
            } finally {
                this.cargandoCatalogos = false;
            }
        },

        async cargarProcesos(pagina = 1) {
            this.cargando = true;
            this.error = '';
            this.mensaje = '';

            this.filtros.pagina = pagina;

            try {
                const parametros = this.construirParametros({
                    pagina,
                });

                const respuesta = await fetch(
                    `${this.baseUrl}/api/admin/procesos_listar.php?${parametros}`,
                    {
                        credentials: 'same-origin',
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message || 'No fue posible cargar los procesos.'
                    );
                }

                this.procesos = Array.isArray(datos.data) ? datos.data : [];

                this.paginacion = {
                    pagina_actual: Number(datos.paginacion?.pagina_actual || 1),
                    por_pagina: Number(datos.paginacion?.por_pagina || 25),
                    total_registros: Number(datos.paginacion?.total_registros || 0),
                    total_paginas: Number(datos.paginacion?.total_paginas || 1),
                };
            } catch (error) {
                this.error =
                    error.message ||
                    'Ocurrió un error al cargar los procesos de titulación.';

                this.procesos = [];
            } finally {
                this.cargando = false;
            }
        },

        async cambiarSede() {
            this.filtros.programa = '';
            this.filtros.gestion = '';
            this.filtros.version = '';

            await this.cargarCatalogos();
            await this.cargarProcesos(1);
        },

        async cambiarTipo() {
            this.filtros.programa = '';
            this.filtros.gestion = '';
            this.filtros.version = '';

            await this.cargarCatalogos();
            await this.cargarProcesos(1);
        },

        async limpiarFiltros() {
            this.filtros = {
                sede: '',
                tipo: '',
                programa: '',
                gestion: '',
                version: '',
                fase: '',
                estado: '',
                buscar: '',
                pagina: 1,
                por_pagina: '25',
            };

            this.mensaje = '';
            this.error = '';

            await this.cargarCatalogos();
            await this.cargarProcesos(1);
        },

        abrirExpediente(proceso) {
            if (!proceso || !proceso.id_inscripcion) {
                alert('No fue posible identificar la inscripción del participante.');
                return;
            }

            window.location.href =
                this.baseUrl +
                '/index.php?page=admin/expediente/index&id_inscripcion=' +
                encodeURIComponent(proceso.id_inscripcion);
        },

        textoTipo(tipo) {
            const tipos = {
                DIPLOMADO: 'Diplomado',
                MAESTRIA: 'Maestría',
                'MAESTRÍA': 'Maestría',
            };

            return tipos[String(tipo || '').toUpperCase()] || tipo || 'Sin tipo';
        },

        claseEstado(estado) {
            const normalizado = String(estado || '').toUpperCase();

            return {
                'estado-borrador':
                    normalizado === 'SIN_CONFIGURACION' ||
                    normalizado === 'BORRADOR',

                'estado-pendiente':
                    normalizado === 'HABILITADA' ||
                    normalizado === 'EN_REVISION',

                'estado-observado':
                    normalizado === 'OBSERVADO',

                'estado-corregido':
                    normalizado === 'CORREGIDO',

                'estado-aprobado':
                    normalizado === 'REVISADO',
            };
        },
    },

    async mounted() {
        await this.cargarCatalogos();
        await this.cargarProcesos(1);
    },
}).mount('#app-procesos-admin');