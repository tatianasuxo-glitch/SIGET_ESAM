document.addEventListener('DOMContentLoaded', function () {
    const contenedor = document.querySelector('#app-usuarios-admin');

    if (!contenedor || typeof Vue === 'undefined') {
        return;
    }

    Vue.createApp({
        data() {
            return {
                usuarios: [],
                roles: [],
                stats: {},
                cargando: true,
                error: '',
                busqueda: '',
                filtroRol: '',
                filtroEstado: '',
                modalAbierto: false,
                modoFormulario: 'crear',
                mensajeModal: '',
                errorModal: '',
                form: {
                    id: '',
                    usuario: '',
                    contrasena: '',
                    nombres: '',
                    apellido_paterno: '',
                    apellido_materno: '',
                    profesion_postgrado: '',
                    estado_cuenta: 'Activo',
                    id_rol: ''
                }
            };
        },

        computed: {
            usuariosFiltrados() {
                const texto = this.busqueda.trim().toLowerCase();
                const rolFiltro = this.filtroRol.trim().toLowerCase();

                return this.usuarios.filter((u) => {
                    const textoUsuario = [
                        u.usuario,
                        u.nombres,
                        u.apellido_paterno,
                        u.apellido_materno,
                        u.profesion_postgrado,
                        u.roles
                    ].join(' ').toLowerCase();

                    const coincideTexto = texto === '' || textoUsuario.includes(texto);

                    const coincideRol =
                        rolFiltro === '' ||
                        String(u.roles || '').toLowerCase().includes(rolFiltro);

                    const coincideEstado =
                        this.filtroEstado === '' ||
                        u.estado_cuenta === this.filtroEstado;

                    return coincideTexto && coincideRol && coincideEstado;
                });
            }
        },

        mounted() {
            this.cargarUsuarios();
        },

        methods: {
            async cargarUsuarios() {
                this.cargando = true;
                this.error = '';

                try {
                    const respuesta = await fetch('/SIGET_ESAM/api/admin/usuarios_listar.php');
                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(resultado.message || resultado.error || 'No se pudo cargar usuarios.');
                    }

                    this.usuarios = resultado.data || [];
                    this.roles = resultado.roles || [];
                    this.stats = resultado.stats || {};

                } catch (error) {
                    this.error = 'Error al cargar usuarios: ' + error.message;

                } finally {
                    this.cargando = false;
                }
            },

            abrirCrear() {
                this.modoFormulario = 'crear';
                this.mensajeModal = '';
                this.errorModal = '';

                this.form = {
                    id: '',
                    usuario: '',
                    contrasena: '',
                    nombres: '',
                    apellido_paterno: '',
                    apellido_materno: '',
                    profesion_postgrado: '',
                    estado_cuenta: 'Activo',
                    id_rol: ''
                };

                this.modalAbierto = true;
            },

            abrirEditar(usuario) {
                this.modoFormulario = 'editar';
                this.mensajeModal = '';
                this.errorModal = '';

                this.form = {
                    id: usuario.id,
                    usuario: usuario.usuario || '',
                    contrasena: '',
                    nombres: usuario.nombres || '',
                    apellido_paterno: usuario.apellido_paterno || '',
                    apellido_materno: usuario.apellido_materno || '',
                    profesion_postgrado: usuario.profesion_postgrado || '',
                    estado_cuenta: usuario.estado_cuenta || 'Activo',
                    id_rol: usuario.id_rol_principal || ''
                };

                this.modalAbierto = true;
            },

            cerrarModal() {
                this.modalAbierto = false;
            },

            async guardarUsuario() {
                this.mensajeModal = '';
                this.errorModal = '';

                try {
                    const datos = new FormData();

                    Object.keys(this.form).forEach((key) => {
                        datos.append(key, this.form[key] ?? '');
                    });

                    const respuesta = await fetch('/SIGET_ESAM/api/admin/usuarios_guardar.php', {
                        method: 'POST',
                        body: datos
                    });

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(resultado.message || resultado.error || 'No se pudo guardar usuario.');
                    }

                    this.mensajeModal = resultado.message || 'Usuario guardado correctamente.';

                    await this.cargarUsuarios();

                    setTimeout(() => {
                        this.cerrarModal();
                    }, 700);

                } catch (error) {
                    this.errorModal = 'Error: ' + error.message;
                }
            },

            async cambiarEstado(usuario) {
                const nuevoEstado = usuario.estado_cuenta === 'Activo' ? 'Inactivo' : 'Activo';

                if (!confirm(`¿Seguro que deseas cambiar el estado a ${nuevoEstado}?`)) {
                    return;
                }

                try {
                    const datos = new FormData();
                    datos.append('id', usuario.id);
                    datos.append('estado_cuenta', nuevoEstado);

                    const respuesta = await fetch('/SIGET_ESAM/api/admin/usuarios_estado.php', {
                        method: 'POST',
                        body: datos
                    });

                    const resultado = await respuesta.json();

                    if (!respuesta.ok || !resultado.success) {
                        throw new Error(resultado.message || resultado.error || 'No se pudo actualizar estado.');
                    }

                    await this.cargarUsuarios();

                } catch (error) {
                    alert('Error: ' + error.message);
                }
            },

            nombreCompleto(usuario) {
                return [
                    usuario.nombres,
                    usuario.apellido_paterno,
                    usuario.apellido_materno
                ].filter(Boolean).join(' ');
            },

            iniciales(usuario) {
                const nombres = String(usuario.nombres || '').trim();
                const apellido = String(usuario.apellido_paterno || '').trim();

                return ((nombres[0] || '') + (apellido[0] || '')).toUpperCase() || 'U';
            },

            textoRol(rol) {
                if (!rol) return 'Sin rol';

                return String(rol)
                    .replaceAll('_', ' ')
                    .toLowerCase()
                    .replace(/\b\w/g, letra => letra.toUpperCase());
            }
        }
    }).mount('#app-usuarios-admin');
});