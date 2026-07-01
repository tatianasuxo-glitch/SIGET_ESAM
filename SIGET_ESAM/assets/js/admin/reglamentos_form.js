(function () {
    const agregar = document.querySelector('#app-reglamento-form');
    const editar = document.querySelector('#app-reglamento-editar');

    if (typeof Vue === 'undefined') {
        return;
    }

    if (agregar) {
        Vue.createApp({
            data() {
                return {
                    tipo: '',
                    titulo: '',
                    descripcion: '',
                    version: '1.0',
                    nombreArchivo: ''
                };
            },

            methods: {
                capturarArchivo(evento) {
                    const archivo = evento.target.files[0];
                    this.nombreArchivo = archivo ? archivo.name : '';
                }
            }
        }).mount('#app-reglamento-form');
    }

    if (editar) {
        Vue.createApp({
            data() {
                return {
                    pestana: 'datos',
                    nombreArchivo: ''
                };
            },

            methods: {
                capturarArchivo(evento) {
                    const archivo = evento.target.files[0];
                    this.nombreArchivo = archivo ? archivo.name : '';
                }
            }
        }).mount('#app-reglamento-editar');
    }
})();