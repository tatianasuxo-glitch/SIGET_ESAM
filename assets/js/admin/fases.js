document.addEventListener('DOMContentLoaded', function () {
    const contenedor = document.querySelector('#app-fases-admin');

    if (!contenedor || typeof Vue === 'undefined') {
        return;
    }

    Vue.createApp({
        data() {
            return {
                configuraciones: [],
                programas: [],
                fases: [],
                stats: {},
                cargando: true,
                error: '',
                busqueda: '',
                filtroPrograma: '',
                filtroEstado: '',
                filtroTipo: '',
                modalAbierto: false,
                modoFormulario: 'crear',
                mensajeModal: '',
                errorModal: '',

                form: {
                    id: '',
                    id_programa: '',
                    id_fase: '',
                    gestion: new Date().getFullYear().toString(),
                    tipo_trabajo: '',
                    fecha_inicio_entrega: '',
                    fecha_limite_entrega: '',
                    fecha_limite_revision: '',
                    fecha_devolucion_observaciones: '',
                    nota_minima: '71',
                    estado: 'ACTIVO'
                }
            };
        },

        computed: {
            configuracionesFiltradas() {
                const texto = this.busqueda.trim().toLowerCase();

                return this.configuraciones.filter((configuracion) => {
                    const textoConfig = [
                        configuracion.nombre_programa,
                        configuracion.tipo_programa,
                        configuracion.nombre_fase,
                        configuracion.numero_fase,
                        configuracion.gestion,
                        configuracion.tipo_trabajo,
                        configuracion.estado
                    ].join(' ').toLowerCase();

                    const coincideTexto =
                        texto === '' || textoConfig.includes(texto);

                    const coincidePrograma =
                        this.filtroPrograma === '' ||
                        String(configuracion.id_programa) === String(this.filtroPrograma);

                    const coincideEstado =
                        this.filtroEstado === '' ||
                        configuracion.estado === this.filtroEstado;

                    const coincideTipo =
                        this.filtroTipo === '' ||
                        String(configuracion.tipo_programa || '')
                            .toLowerCase()
                            .includes(this.filtroTipo.toLowerCase());

                    return (
                        coincideTexto &&
                        coincidePrograma &&
                        coincideEstado &&
                        coincideTipo
                    );
                });
            }
        },

        watch: {
            'form.id_fase'(idFase) {
                /*
                | Solo sugiere el tipo de trabajo cuando se está creando.
                | Al editar, conserva el valor que ya tenía la configuración.
                */
                if (this.modoFormulario !== 'crear') {
                    return;
                }

                const sugerencias = {
                    '1': 'Propuesta inicial',
                    '2': 'Desarrollo del Trabajo Final',
                    '3': 'Versión Final y Titulación'
                };

                this.form.tipo_trabajo = sugerencias[String(idFase)] || '';
            }
        },

        mounted() {
            this.cargarFases();
        },

        methods: {
            async cargarFases() {
                this.cargando = true;
                this.error = '';

                try {
                    const respuesta = await fetch('/SIGET_ESAM/api/admin/fases_listar.php');
                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(
                            resultado.message ||
                            resultado.error ||
                            'No se pudo cargar la gestión de fases.'
                        );
                    }

                    this.configuraciones = resultado.data || [];
                    this.programas = resultado.programas || [];
                    this.fases = resultado.fases || [];
                    this.stats = resultado.stats || {};

                } catch (error) {
                    this.error = 'Error al cargar fases: ' + error.message;

                } finally {
                    this.cargando = false;
                }
            },

            limpiarFiltros() {
                this.busqueda = '';
                this.filtroPrograma = '';
                this.filtroEstado = '';
                this.filtroTipo = '';
            },

            abrirCrear() {
                this.modoFormulario = 'crear';
                this.mensajeModal = '';
                this.errorModal = '';

                this.form = {
                    id: '',
                    id_programa: '',
                    id_fase: '',
                    gestion: new Date().getFullYear().toString(),
                    tipo_trabajo: '',
                    fecha_inicio_entrega: '',
                    fecha_limite_entrega: '',
                    fecha_limite_revision: '',
                    fecha_devolucion_observaciones: '',
                    nota_minima: '71',
                    estado: 'ACTIVO'
                };

                this.modalAbierto = true;
            },

            abrirEditar(configuracion) {
                this.modoFormulario = 'editar';
                this.mensajeModal = '';
                this.errorModal = '';

                this.form = {
                    id: configuracion.id,
                    id_programa: configuracion.id_programa,
                    id_fase: configuracion.id_fase,
                    gestion: configuracion.gestion || '',
                    tipo_trabajo: configuracion.tipo_trabajo || '',
                    fecha_inicio_entrega: this.toDatetimeLocal(
                        configuracion.fecha_inicio_entrega
                    ),
                    fecha_limite_entrega: this.toDatetimeLocal(
                        configuracion.fecha_limite_entrega
                    ),
                    fecha_limite_revision: this.toDatetimeLocal(
                        configuracion.fecha_limite_revision
                    ),
                    fecha_devolucion_observaciones: this.toDatetimeLocal(
                        configuracion.fecha_devolucion_observaciones
                    ),
                    nota_minima: configuracion.nota_minima || '71',
                    estado: configuracion.estado || 'ACTIVO'
                };

                this.modalAbierto = true;
            },

            cerrarModal() {
                this.modalAbierto = false;
            },

            async guardarConfiguracion() {
                this.mensajeModal = '';
                this.errorModal = '';

                try {
                    const datos = new FormData();

                    Object.keys(this.form).forEach((key) => {
                        datos.append(key, this.form[key] ?? '');
                    });

                    const respuesta = await fetch(
                        '/SIGET_ESAM/api/admin/fases_guardar.php',
                        {
                            method: 'POST',
                            body: datos
                        }
                    );

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(
                            resultado.message ||
                            resultado.error ||
                            'No se pudo guardar la configuración.'
                        );
                    }

                    this.mensajeModal =
                        resultado.message ||
                        'Configuración guardada correctamente.';

                    await this.cargarFases();

                    setTimeout(() => {
                        this.cerrarModal();
                    }, 700);

                } catch (error) {
                    this.errorModal = 'Error: ' + error.message;
                }
            },

            async cambiarEstado(configuracion) {
                const nuevoEstado =
                    configuracion.estado === 'ACTIVO'
                        ? 'INACTIVO'
                        : 'ACTIVO';

                if (!confirm(`¿Seguro que deseas cambiar el estado a ${nuevoEstado}?`)) {
                    return;
                }

                try {
                    const datos = new FormData();

                    datos.append('id', configuracion.id);
                    datos.append('estado', nuevoEstado);

                    const respuesta = await fetch(
                        '/SIGET_ESAM/api/admin/fases_estado.php',
                        {
                            method: 'POST',
                            body: datos
                        }
                    );

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(
                            resultado.message ||
                            resultado.error ||
                            'No se pudo actualizar el estado.'
                        );
                    }

                    await this.cargarFases();

                } catch (error) {
                    alert('Error: ' + error.message);
                }
            },

            formatoFecha(valor) {
                if (!valor) {
                    return 'No definido';
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

            toDatetimeLocal(valor) {
                if (!valor) {
                    return '';
                }

                return String(valor)
                    .replace(' ', 'T')
                    .slice(0, 16);
            }
        }
    }).mount('#app-fases-admin');
});