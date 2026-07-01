document.addEventListener('DOMContentLoaded', function () {
    montarVue('#app-programas', {
        data() {
            return {
                programas: [],
                cargando: true,
                error: null
            };
        },

        mounted() {
            this.cargarProgramas();
        },

        methods: {
            async cargarProgramas() {
                try {
                    const resultado = await SIGET.getJSON(
                        SIGET.api('admin/programas_concluidos.php')
                    );

                    if (!resultado.success) {
                        throw new Error(resultado.message || 'No se pudo cargar la información.');
                    }

                    this.programas = resultado.data;

                } catch (error) {
                    this.error = 'Error al cargar programas: ' + error.message;

                } finally {
                    this.cargando = false;
                }
            }
        }
    });
});