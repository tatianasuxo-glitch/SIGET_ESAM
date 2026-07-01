window.SIGET = {
    baseUrl: '/SIGET_ESAM',

    api(url) {
        return this.baseUrl + '/api/' + url;
    },

    async getJSON(url) {
        const respuesta = await fetch(url);

        if (!respuesta.ok) {
            throw new Error('Error HTTP ' + respuesta.status);
        }

        return await respuesta.json();
    },

    formatearEstado(valor) {
        if (!valor) return '';

        return String(valor)
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, letra => letra.toUpperCase());
    }
};

window.montarVue = function(selector, opciones) {
    const elemento = document.querySelector(selector);

    if (!elemento) {
        console.warn('No existe el contenedor Vue:', selector);
        return null;
    }

    if (typeof Vue === 'undefined') {
        console.error('Vue no está cargado. Revisa el script CDN de Vue.');
        return null;
    }

    return Vue.createApp(opciones).mount(selector);
};