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

                return this.configuraciones.filter((c) => {
                    const textoConfig = [
                        c.nombre_programa,
                        c.tipo_programa,
                        c.nombre_fase,
                        c.numero_fase,
                        c.gestion,
                        c.tipo_trabajo,
                        c.estado
                    ].join(' ').toLowerCase();

                    const coincideTexto = texto === '' || textoConfig.includes(texto);

                    const coincidePrograma =
                        this.filtroPrograma === '' ||
                        String(c.id_programa) === String(this.filtroPrograma);

                    const coincideEstado =
                        this.filtroEstado === '' ||
                        c.estado === this.filtroEstado;

                    const coincideTipo =
                        this.filtroTipo === '' ||
                        String(c.tipo_programa || '').toLowerCase().includes(this.filtroTipo.toLowerCase());

                    return coincideTexto && coincidePrograma && coincideEstado && coincideTipo;
                });
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
                        throw new Error(resultado.message || resultado.error || 'No se pudo cargar gestión de fases.');
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

            abrirEditar(config) {
                this.modoFormulario = 'editar';
                this.mensajeModal = '';
                this.errorModal = '';

                this.form = {
                    id: config.id,
                    id_programa: config.id_programa,
                    id_fase: config.id_fase,
                    gestion: config.gestion || '',
                    tipo_trabajo: config.tipo_trabajo || '',
                    fecha_inicio_entrega: this.toDatetimeLocal(config.fecha_inicio_entrega),
                    fecha_limite_entrega: this.toDatetimeLocal(config.fecha_limite_entrega),
                    fecha_limite_revision: this.toDatetimeLocal(config.fecha_limite_revision),
                    fecha_devolucion_observaciones: this.toDatetimeLocal(config.fecha_devolucion_observaciones),
                    nota_minima: config.nota_minima || '71',
                    estado: config.estado || 'ACTIVO'
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

                    const respuesta = await fetch('/SIGET_ESAM/api/admin/fases_guardar.php', {
                        method: 'POST',
                        body: datos
                    });

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(resultado.message || resultado.error || 'No se pudo guardar configuración.');
                    }

                    this.mensajeModal = resultado.message || 'Configuración guardada correctamente.';

                    await this.cargarFases();

                    setTimeout(() => {
                        this.cerrarModal();
                    }, 700);

                } catch (error) {
                    this.errorModal = 'Error: ' + error.message;
                }
            },

            async cambiarEstado(config) {
                const nuevoEstado = config.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';

                if (!confirm(`¿Seguro que deseas cambiar el estado a ${nuevoEstado}?`)) {
                    return;
                }

                try {
                    const datos = new FormData();
                    datos.append('id', config.id);
                    datos.append('estado', nuevoEstado);

                    const respuesta = await fetch('/SIGET_ESAM/api/admin/fases_estado.php', {
                        method: 'POST',
                        body: datos
                    });

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(resultado.message || resultado.error || 'No se pudo actualizar estado.');
                    }

                    await this.cargarFases();

                } catch (error) {
                    alert('Error: ' + error.message);
                }
            },

            formatoFecha(valor) {
                if (!valor) return 'No definido';

                const fecha = new Date(valor.replace(' ', 'T'));

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
                if (!valor) return '';

                return String(valor).replace(' ', 'T').slice(0, 16);
            }
        }
    }).mount('#app-fases-admin');
});