const { createApp } = Vue;

const contenedorExpediente = document.getElementById('app-expediente-admin');

createApp({
    data() {
        return {
            baseUrl: '/SIGET_ESAM',

            idInscripcion: Number(
                contenedorExpediente &&
                contenedorExpediente.dataset &&
                contenedorExpediente.dataset.idInscripcion
                    ? contenedorExpediente.dataset.idInscripcion
                    : 0
            ),

            cargando: true,
            error: '',

            contexto: null,
            jurado: null,
            fases: [],
            siguienteAccion: {
                fase: null,
                codigo: 'SIN_ACCION',
                texto: 'Cargando acción pendiente...',
                responsable: 'Administración Académica',
            },
        };
    },

    methods: {
        async cargarExpediente() {
            if (!this.idInscripcion) {
                this.error = 'No se recibió una inscripción válida.';
                this.cargando = false;
                return;
            }

            this.cargando = true;
            this.error = '';

            try {
                const respuesta = await fetch(
                    this.baseUrl +
                        '/api/admin/expediente_detalle.php?id_inscripcion=' +
                        encodeURIComponent(this.idInscripcion),
                    {
                        credentials: 'same-origin',
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.success) {
                    throw new Error(
                        datos.message || 'No fue posible cargar el expediente.'
                    );
                }

                this.contexto = datos.contexto || null;
                this.jurado = datos.jurado || null;
                this.fases = Array.isArray(datos.fases) ? datos.fases : [];

                this.siguienteAccion = datos.siguiente_accion || {
                    fase: null,
                    codigo: 'SIN_ACCION',
                    texto: 'No existen acciones pendientes.',
                    responsable: 'Administración Académica',
                };
            } catch (error) {
                this.error =
                    error.message ||
                    'Ocurrió un error al cargar el expediente de titulación.';
            } finally {
                this.cargando = false;
            }
        },

        textoTipo(tipo) {
            const tipos = {
                DIPLOMADO: 'Diplomado',
                MAESTRIA: 'Maestría',
                'MAESTRÍA': 'Maestría',
            };

            const tipoNormalizado = String(tipo || '').toUpperCase();

            return tipos[tipoNormalizado] || tipo || 'Programa';
        },

        nombreVisualFase(fase) {
            const nombres = {
                1: 'Propuesta inicial',
                2: 'Monografía',
                3: 'Evaluación final',
            };

            const numeroFase = Number(fase && fase.numero_fase);

            return (
                nombres[numeroFase] ||
                (fase ? fase.nombre_fase : '') ||
                'Fase académica'
            );
        },

        formatearFecha(fecha) {
            if (!fecha) {
                return 'No definido';
            }

            const fechaNormalizada = String(fecha).replace(' ', 'T');
            const objetoFecha = new Date(fechaNormalizada);

            if (Number.isNaN(objetoFecha.getTime())) {
                return String(fecha);
            }

            return new Intl.DateTimeFormat('es-BO', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(objetoFecha);
        },

        rutaDocumento(fase) {
            if (!fase) {
                return '';
            }

            const ruta =
                (fase.entrega && fase.entrega.ruta_archivo) ||
                (fase.trabajo && fase.trabajo.ruta_archivo) ||
                '';

            if (!ruta) {
                return '';
            }

            return (
                this.baseUrl +
                '/' +
                String(ruta).replace(/^\/+/, '')
            );
        },

        textoDecision(decision) {
            const decisiones = {
                APROBAR: 'Validado',
                APROBADO: 'Validado',
                VALIDAR: 'Validado',
                VALIDADO: 'Validado',
                REVISADO: 'Validado',
                RECHAZAR: 'Con observaciones',
                RECHAZADO: 'Con observaciones',
                OBSERVADO: 'Con observaciones',
                CORREGIDO: 'Versión corregida',
            };

            const decisionNormalizada = String(decision || '').toUpperCase();

            return (
                decisiones[decisionNormalizada] ||
                decision ||
                'Revisión registrada'
            );
        },

        textoControlReentrega(estado) {
            const estados = {
                PENDIENTE_AUTORIZACION: 'Pendiente de autorización',
                AUTORIZADA: 'Corrección autorizada',
                REENTREGADA: 'Versión corregida reenviada',
                FINALIZADA: 'Corrección finalizada',
                CERRADA: 'Corrección cerrada',
            };

            const estadoNormalizado = String(estado || '').toUpperCase();

            return estados[estadoNormalizado] || estado || 'Sin estado';
        },

        claseEstado(estado) {
            const normalizado = String(estado || '').toUpperCase();

            return {
                'estado-borrador':
                    normalizado === 'PENDIENTE' ||
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

    mounted() {
        this.cargarExpediente();
    },
}).mount('#app-expediente-admin');