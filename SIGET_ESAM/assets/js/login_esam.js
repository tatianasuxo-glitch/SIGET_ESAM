document.addEventListener("DOMContentLoaded", function () {
    const contenedor = document.querySelector("#app-login");

    if (!contenedor || typeof Vue === "undefined") {
        return;
    }

    Vue.createApp({
        data() {
            return {
                usuario: "",
                contrasena: "",
                mostrarPassword: false,
                errorVue: ""
            };
        },

        methods: {
            validarFormulario(evento) {
                this.errorVue = "";

                if (!this.usuario.trim()) {
                    this.errorVue = "Debes ingresar tu usuario.";
                    evento.preventDefault();
                    return;
                }

                if (!this.contrasena.trim()) {
                    this.errorVue = "Debes ingresar tu contraseña.";
                    evento.preventDefault();
                    return;
                }
            }
        }
    }).mount("#app-login");
});