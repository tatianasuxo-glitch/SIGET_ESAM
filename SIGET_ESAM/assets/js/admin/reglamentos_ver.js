(function () {
    const contenedor = document.querySelector('#app-reglamentos-ver');

    if (!contenedor || typeof Vue === 'undefined') {
        return;
    }

    Vue.createApp({
        data() {
            return {
                reglamentos: window.REGLAMENTOS_LISTA || [],
                busqueda: '',
                filtroTipo: '',
                filtroEstado: ''
            };
        },

        computed: {
            reglamentosFiltrados() {
                const texto = this.busqueda.trim().toLowerCase();

                return this.reglamentos.filter((r) => {
                    const coincideTexto =
                        texto === '' ||
                        String(r.titulo || '').toLowerCase().includes(texto) ||
                        String(r.descripcion || '').toLowerCase().includes(texto) ||
                        String(r.archivo_original || '').toLowerCase().includes(texto);

                    const coincideTipo =
                        this.filtroTipo === '' ||
                        r.tipo_programa === this.filtroTipo;

                    const coincideEstado =
                        this.filtroEstado === '' ||
                        r.estado === this.filtroEstado;

                    return coincideTexto && coincideTipo && coincideEstado;
                });
            }
        },

        methods: {
            rutaArchivo(ruta) {
                if (!ruta) return '#';
                if (ruta.startsWith('http')) return ruta;

                return '/SIGET_ESAM/' + ruta.replace(/^\/+/, '');
            },

            formatoKB(bytes) {
                const numero = Number(bytes || 0);

                if (numero <= 0) {
                    return '0 KB';
                }

                return (numero / 1024).toFixed(2) + ' KB';
            },

            textoTipo(tipo) {
                const mapa = {
                    GENERAL: 'General',
                    DIPLOMADO: 'Diplomado',
                    MAESTRIA: 'Maestría',
                    ESPECIALIDAD: 'Especialidad'
                };

                return mapa[tipo] || tipo || 'Sin tipo';
            }
        }
    }).mount('#app-reglamentos-ver');
})();