document.addEventListener('DOMContentLoaded', function () {
    const contenedor = document.querySelector('#app-revision-admin');

    if (!contenedor || typeof Vue === 'undefined') {
        return;
    }

    Vue.createApp({
        data() {
            return {
                items: [],
                stats: {
                    pendientes: 0,
                    observados: 0,
                    aprobados: 0,
                    rechazados: 0,
                    total: 0
                },
                filtroEstado: 'pendientes',
                busqueda: '',
                cargando: true,
                error: '',
                modalAbierto: false,
                itemSeleccionado: null,
                accionSeleccionada: '',
                form: {
                    comentario: '',
                    calificacion: ''
                }
            };
        },

        computed: {
            itemsFiltrados() {
                const texto = this.busqueda.trim().toLowerCase();

                if (!texto) {
                    return this.items;
                }

                return this.items.filter((item) => {
                    return String(item.estudiante || '').toLowerCase().includes(texto) ||
                        String(item.nombre_programa || '').toLowerCase().includes(texto) ||
                        String(item.titulo_trabajo || '').toLowerCase().includes(texto) ||
                        String(item.nombre_fase || '').toLowerCase().includes(texto) ||
                        String(item.estado_aprobacion || '').toLowerCase().includes(texto);
                });
            },

            tituloModal() {
                const mapa = {
                    aprobar: 'Aprobar trabajo',
                    observar: 'Observar trabajo',
                    rechazar: 'Rechazar trabajo'
                };

                return mapa[this.accionSeleccionada] || 'Registrar revisión';
            }
        },

        mounted() {
            this.cargarItems();
        },

        methods: {
            async cargarItems() {
                this.cargando = true;
                this.error = '';

                try {
                    const url = `/SIGET_ESAM/api/admin/revision_items.php?estado=${encodeURIComponent(this.filtroEstado)}`;
                    const respuesta = await fetch(url);
                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(resultado.message || resultado.error || 'No se pudo cargar la información.');
                    }

                    this.items = resultado.data || [];
                    this.stats = resultado.stats || this.stats;

                } catch (error) {
                    this.error = 'Error al cargar revisión: ' + error.message;

                } finally {
                    this.cargando = false;
                }
            },

            cambiarFiltro(estado) {
                this.filtroEstado = estado;
                this.cargarItems();
            },

            rutaArchivo(ruta) {
                if (!ruta) return '#';
                if (ruta.startsWith('http')) return ruta;

                return '/SIGET_ESAM/' + ruta.replace(/^\/+/, '');
            },

            textoEstado(estado) {
                if (!estado) return 'Sin estado';

                return String(estado)
                    .replaceAll('_', ' ')
                    .toLowerCase()
                    .replace(/\b\w/g, letra => letra.toUpperCase());
            },

            claseEstado(estado) {
                const valor = String(estado || '').toLowerCase();

                if (valor.includes('aprob')) return 'aprobado';
                if (valor.includes('observ')) return 'observado';
                if (valor.includes('rechaz') || valor.includes('reprob')) return 'rechazado';

                return 'pendiente';
            },

            abrirModal(item, accion) {
                this.itemSeleccionado = item;
                this.accionSeleccionada = accion;

                this.form.comentario = item.comentario_revision || '';
                this.form.calificacion = item.calificacion_final || '';

                this.modalAbierto = true;
            },

            cerrarModal() {
                this.modalAbierto = false;
                this.itemSeleccionado = null;
                this.accionSeleccionada = '';
                this.form.comentario = '';
                this.form.calificacion = '';
            },

            async guardarRevision() {
                try {
                    const datos = new FormData();
                    datos.append('id_trabajo', this.itemSeleccionado.id_trabajo);
                    datos.append('accion_revision', this.accionSeleccionada);
                    datos.append('comentario_revision', this.form.comentario);
                    datos.append('calificacion_final', this.form.calificacion);

                    const respuesta = await fetch('/SIGET_ESAM/api/admin/revision_actualizar.php', {
                        method: 'POST',
                        body: datos
                    });

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(resultado.message || resultado.error || 'No se pudo actualizar.');
                    }

                    this.cerrarModal();
                    await this.cargarItems();

                } catch (error) {
                    alert('Error: ' + error.message);
                }
            }
        }
    }).mount('#app-revision-admin');
});