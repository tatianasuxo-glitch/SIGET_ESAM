const { createApp } = Vue;

createApp({
    data() {
        return {
            baseUrl: '/SIGET_ESAM',

            cargando: false,
            error: '',

            programas: [],

            catalogos: {
                sedes: [],
                tipos: [],
                gestiones: [],
                versiones: [],
            },

            filtros: {
                sede: '',
                tipo: '',
                gestion: '',
                version: '',
                buscar: '',
                pagina: 1,
                por_pagina: '12',
            },

            paginacion: {
                pagina_actual: 1,
                por_pagina: 12,
                total_registros: 0,
                total_paginas: 1,
            },
        };
    },

    computed: {
        totalParticipantes() {
            return this.programas.reduce((total, programa) => {
                return total + Number(programa.total_participantes || 0);
            }, 0);
        },

        totalFase3PorHabilitar() {
            return this.programas.reduce((total, programa) => {
                return total + Number(programa.fase_3_por_habilitar || 0);
            }, 0);
        },
    },

    methods: {
        construirParametros(pagina) {
            const parametros = new URLSearchParams();

            const valores = {
                sede: this.filtros.sede,
                tipo: this.filtros.tipo,
                gestion: this.filtros.gestion,
                version: this.filtros.version,
                buscar: this.filtros.buscar.trim(),
                pagina: pagina,
                por_pagina: this.filtros.por_pagina,
            };

            Object.keys(valores).forEach((clave) => {
                const valor = valores[clave];

                if (
                    valor !== '' &&
                    valor !== null &&
                    valor !== undefined
                ) {
                    parametros.append(clave, valor);
                }
            });

            return parametros.toString();
        },

        async cargarProgramas(pagina = 1) {
            this.cargando = true;
            this.error = '';

            try {
                this.filtros.pagina = pagina;

                const parametros = this.construirParametros(pagina);

                const respuesta = await fetch(
                    this.baseUrl +
                        '/api/admin/programas_titulacion_listar.php?' +
                        parametros,
                    {
                        credentials: 'same-origin',
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message ||
                        'No fue posible cargar los programas de titulación.'
                    );
                }

                this.programas = Array.isArray(datos.data)
                    ? datos.data
                    : [];

                this.catalogos = {
                    sedes: Array.isArray(datos.catalogos?.sedes)
                        ? datos.catalogos.sedes
                        : [],

                    tipos: Array.isArray(datos.catalogos?.tipos)
                        ? datos.catalogos.tipos
                        : [],

                    gestiones: Array.isArray(datos.catalogos?.gestiones)
                        ? datos.catalogos.gestiones
                        : [],

                    versiones: Array.isArray(datos.catalogos?.versiones)
                        ? datos.catalogos.versiones
                        : [],
                };

                this.paginacion = {
                    pagina_actual: Number(
                        datos.paginacion?.pagina_actual || 1
                    ),
                    por_pagina: Number(
                        datos.paginacion?.por_pagina || 12
                    ),
                    total_registros: Number(
                        datos.paginacion?.total_registros || 0
                    ),
                    total_paginas: Number(
                        datos.paginacion?.total_paginas || 1
                    ),
                };
            } catch (error) {
                this.error =
                    error.message ||
                    'Ocurrió un error al cargar los programas.';

                this.programas = [];
            } finally {
                this.cargando = false;
            }
        },

        async limpiarFiltros() {
            this.filtros = {
                sede: '',
                tipo: '',
                gestion: '',
                version: '',
                buscar: '',
                pagina: 1,
                por_pagina: '12',
            };

            await this.cargarProgramas(1);
        },

       abrirParticipantes(programa) {
    if (!programa || !programa.id_programa) {
        alert('No fue posible identificar el programa seleccionado.');
        return;
    }

    const parametros = new URLSearchParams({
        page: 'admin/procesos/index',
        programa: programa.id_programa,
    });

    if (programa.gestion_externa) {
        parametros.append('gestion', programa.gestion_externa);
    }

    if (programa.version_programa_externa) {
        parametros.append('version', programa.version_programa_externa);
    }

    window.location.href =
        this.baseUrl + '/index.php?' + parametros.toString();
},

        textoTipo(tipo) {
            const tipos = {
                DIPLOMADO: 'Diplomado',
                MAESTRIA: 'Maestría',
                'MAESTRÍA': 'Maestría',
            };

            const tipoNormalizado = String(tipo || '').toUpperCase();

            return tipos[tipoNormalizado] || tipo || 'Sin tipo';
        },

        textoConfiguracion(programa) {
            const fase1 = Number(programa.fase_1_configurada || 0);
            const fase2 = Number(programa.fase_2_configurada || 0);
            const fase3 = Number(programa.fase_3_configurada || 0);

            if (fase1 === 1 && fase2 === 1 && fase3 === 1) {
                return 'Fases configuradas';
            }

            return 'Fases pendientes';
        },

        claseConfiguracion(programa) {
            const fase1 = Number(programa.fase_1_configurada || 0);
            const fase2 = Number(programa.fase_2_configurada || 0);
            const fase3 = Number(programa.fase_3_configurada || 0);

            return {
                'configuracion-completa':
                    fase1 === 1 &&
                    fase2 === 1 &&
                    fase3 === 1,

                'configuracion-pendiente':
                    fase1 !== 1 ||
                    fase2 !== 1 ||
                    fase3 !== 1,
            };
        },
    },

    mounted() {
        this.cargarProgramas(1);
    },
}).mount('#app-programas-titulacion');