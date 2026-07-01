document.addEventListener('DOMContentLoaded', function () {
    const contenedor = document.querySelector('#app-reglamentos-index');

    if (!contenedor) {
        console.error('No se encontró #app-reglamentos-index');
        return;
    }

    if (typeof Vue === 'undefined') {
        console.error('Vue no está cargado. Revisa el script CDN de Vue.');
        return;
    }

    Vue.createApp({
        data() {
            return {
                resumen: window.REGLAMENTOS_RESUMEN || {
                    total: 0,
                    activos: 0,
                    inactivos: 0
                }
            };
        },

        methods: {
            ir(url) {
                window.location.href = url;
            }
        }
    }).mount('#app-reglamentos-index');
});