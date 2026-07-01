<link rel="stylesheet" href="/SIGET_ESAM/assets/css/admin_usuarios.css?v=1">

<section class="usuarios-screen" id="app-usuarios-admin" v-cloak>
    <div class="usuarios-shell">

        <div class="usuarios-hero">
            <div class="hero-content">
                <span class="hero-label">Gestión de accesos</span>
                <h1>Administración de Usuarios</h1>
                <p>
                    Gestiona los usuarios del sistema, sus roles, estados de acceso
                    y datos principales de identificación.
                </p>
            </div>

            <div class="hero-stats">
                <button type="button" class="stat-box" @click="filtroEstado = ''; filtroRol = ''">
                    <strong>{{ stats.total || 0 }}</strong>
                    <span>Total</span>
                </button>

                <button type="button" class="stat-box" @click="filtroEstado = 'Activo'">
                    <strong>{{ stats.activos || 0 }}</strong>
                    <span>Activos</span>
                </button>

                <button type="button" class="stat-box" @click="filtroEstado = 'Inactivo'">
                    <strong>{{ stats.inactivos || 0 }}</strong>
                    <span>Inactivos</span>
                </button>

                <button type="button" class="stat-box" @click="filtroRol = 'administrador'">
                    <strong>{{ stats.administradores || 0 }}</strong>
                    <span>Admins</span>
                </button>
            </div>
        </div>

        <div class="usuarios-panel">
            <div class="panel-header">
                <div>
                    <h2>Listado de usuarios</h2>
                    <p>Busca, filtra, crea o actualiza usuarios del sistema.</p>
                </div>

                <button type="button" class="btn-primary" @click="abrirCrear">
                    + Crear usuario
                </button>
            </div>

            <div class="usuarios-toolbar">
                <div class="search-box">
                    <input
                        type="text"
                        v-model="busqueda"
                        placeholder="Buscar por nombre, usuario, profesión o rol..."
                    >
                </div>

                <select v-model="filtroRol">
                    <option value="">Todos los roles</option>
                    <option
                        v-for="rol in roles"
                        :key="rol.id"
                        :value="rol.nombre_rol"
                    >
                        {{ textoRol(rol.nombre_rol) }}
                    </option>
                </select>

                <select v-model="filtroEstado">
                    <option value="">Todos los estados</option>
                    <option value="Activo">Activos</option>
                    <option value="Inactivo">Inactivos</option>
                </select>
            </div>

            <div v-if="cargando" class="empty-state">
                Cargando usuarios...
            </div>

            <div v-else-if="error" class="alert error">
                {{ error }}
            </div>

            <div v-else-if="usuariosFiltrados.length === 0" class="empty-state">
                No existen usuarios con los filtros seleccionados.
            </div>

            <div v-else class="tabla-wrap">
                <table class="usuarios-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre completo</th>
                            <th>Rol</th>
                            <th>Profesión / Cargo</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="usuario in usuariosFiltrados" :key="usuario.id">
                            <td>
                                <div class="usuario-cell">
                                    <div class="avatar">
                                        {{ iniciales(usuario) }}
                                    </div>

                                    <div>
                                        <strong>{{ usuario.usuario }}</strong>
                                        <span>ID: {{ usuario.id }}</span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="nombre-cell">
                                    <strong>{{ nombreCompleto(usuario) }}</strong>
                                    <span>{{ usuario.apellido_paterno }} {{ usuario.apellido_materno || '' }}</span>
                                </div>
                            </td>

                            <td>
                                <span class="rol-badge">
                                    {{ textoRol(usuario.roles || 'Sin rol') }}
                                </span>
                            </td>

                            <td>
                                {{ usuario.profesion_postgrado || 'No registrado' }}
                            </td>

                            <td>
                                <span
                                    class="estado-badge"
                                    :class="usuario.estado_cuenta === 'Activo' ? 'activo' : 'inactivo'"
                                >
                                    {{ usuario.estado_cuenta }}
                                </span>
                            </td>

                            <td>
                                {{ usuario.creado_el || 'Sin fecha' }}
                            </td>

                            <td>
                                <div class="usuarios-actions">
                                    <button type="button" class="btn-primary" @click="abrirEditar(usuario)">
                                        Editar
                                    </button>

                                    <button
                                        type="button"
                                        :class="usuario.estado_cuenta === 'Activo' ? 'btn-danger' : 'btn-secondary'"
                                        @click="cambiarEstado(usuario)"
                                    >
                                        {{ usuario.estado_cuenta === 'Activo' ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modalAbierto" class="modal-overlay">
            <div class="modal-card">
                <div class="modal-header">
                    <div>
                        <h2>{{ modoFormulario === 'crear' ? 'Crear usuario' : 'Editar usuario' }}</h2>
                        <p>
                            Completa los datos principales y asigna el rol correspondiente.
                        </p>
                    </div>

                    <button type="button" class="modal-close" @click="cerrarModal">×</button>
                </div>

                <form @submit.prevent="guardarUsuario">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Usuario</label>
                            <input type="text" v-model="form.usuario" required>
                        </div>

                        <div class="form-group">
                            <label>Contraseña</label>
                            <input
                                type="password"
                                v-model="form.contrasena"
                                :placeholder="modoFormulario === 'editar' ? 'Dejar vacío para no cambiar' : 'Contraseña'"
                                :required="modoFormulario === 'crear'"
                            >
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombres</label>
                            <input type="text" v-model="form.nombres" required>
                        </div>

                        <div class="form-group">
                            <label>Apellido paterno</label>
                            <input type="text" v-model="form.apellido_paterno" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Apellido materno</label>
                            <input type="text" v-model="form.apellido_materno">
                        </div>

                        <div class="form-group">
                            <label>Profesión / Cargo</label>
                            <input type="text" v-model="form.profesion_postgrado">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Rol</label>
                            <select v-model="form.id_rol" required>
                                <option value="">Seleccionar rol</option>
                                <option
                                    v-for="rol in roles"
                                    :key="rol.id"
                                    :value="rol.id"
                                >
                                    {{ textoRol(rol.nombre_rol) }}
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Estado</label>
                            <select v-model="form.estado_cuenta" required>
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="mensajeModal" class="alert success">
                        {{ mensajeModal }}
                    </div>

                    <div v-if="errorModal" class="alert error">
                        {{ errorModal }}
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" @click="cerrarModal">
                            Cancelar
                        </button>

                        <button type="submit" class="btn-primary">
                            Guardar usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</section>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="/SIGET_ESAM/assets/js/admin/usuarios.js?v=1"></script>