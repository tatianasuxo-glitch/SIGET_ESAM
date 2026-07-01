-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 08-06-2026 a las 22:22:03
-- Versión del servidor: 10.4.24-MariaDB
-- Versión de PHP: 8.0.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `proyecto_la_paz`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fases`
--

CREATE TABLE `fases` (
  `id` int(11) NOT NULL,
  `nombre_fase` varchar(50) NOT NULL,
  `numero_fase` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `calificacion_requerido` decimal(5,2) DEFAULT NULL,
  `creado_el` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_el` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `fases`
--

INSERT INTO `fases` (`id`, `nombre_fase`, `numero_fase`, `descripcion`, `estado`, `calificacion_requerido`, `creado_el`, `actualizado_el`) VALUES
(1, 'Fase 1 - Revisión administrativa', 1, 'Validación inicial de la documentación administrativa del estudiante.', 1, '71.00', '2026-06-02 14:59:30', '2026-06-02 14:59:30'),
(2, 'Fase 2 - Revisión docente calificadora', 2, 'Evaluación académica del documento por docente calificador o tribunal asignado.', 1, '71.00', '2026-06-02 14:59:30', '2026-06-02 14:59:30'),
(3, 'Fase 3 - Revisión final', 3, 'Revisión final para cierre del proceso de titulación.', 1, '71.00', '2026-06-02 14:59:30', '2026-06-02 14:59:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fase_estudiante_config`
--

CREATE TABLE `fase_estudiante_config` (
  `id` int(11) NOT NULL,
  `id_configuracion` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `fecha_inicio_entrega` datetime DEFAULT NULL,
  `fecha_limite_entrega` datetime DEFAULT NULL,
  `fecha_limite_revision` datetime DEFAULT NULL,
  `fecha_devolucion_observaciones` datetime DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'ACTIVO',
  `observacion` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fase_requisitos`
--

CREATE TABLE `fase_requisitos` (
  `id` int(11) NOT NULL,
  `id_configuracion` int(11) NOT NULL,
  `nombre_requisito` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `obligatorio` tinyint(1) DEFAULT 1,
  `orden` int(11) DEFAULT 1,
  `estado` varchar(20) DEFAULT 'ACTIVO',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `fase_requisitos`
--

INSERT INTO `fase_requisitos` (`id`, `id_configuracion`, `nombre_requisito`, `descripcion`, `obligatorio`, `orden`, `estado`, `fecha_creacion`) VALUES
(1, 1, 'Título del trabajo', 'Documento o formulario donde se registre el título tentativo de la tesis o monografía.', 1, 1, 'ACTIVO', '2026-06-08 14:44:12'),
(2, 1, 'Carta de aceptación de tutor', 'Carta firmada por el tutor aceptando acompañar el proceso académico del participante.', 1, 2, 'ACTIVO', '2026-06-08 14:44:12'),
(3, 1, 'Documento de identidad', 'Fotocopia o imagen del documento de identidad vigente.', 1, 3, 'ACTIVO', '2026-06-08 14:44:12'),
(4, 1, 'Solicitud de inicio de proceso de titulación', 'Formulario o carta dirigida a coordinación solicitando el inicio del proceso.', 1, 4, 'ACTIVO', '2026-06-08 14:44:12'),
(5, 2, 'Documento académico principal', 'Primera versión de la tesis o monografía para revisión del docente calificador.', 1, 1, 'ACTIVO', '2026-06-08 14:44:37'),
(6, 2, 'Anexos académicos', 'Anexos, instrumentos, evidencias o documentos complementarios del trabajo.', 0, 2, 'ACTIVO', '2026-06-08 14:44:37'),
(7, 3, 'Versión final del trabajo', 'Documento final corregido de tesis o monografía.', 1, 1, 'ACTIVO', '2026-06-08 14:44:52'),
(8, 3, 'Constancia de correcciones', 'Documento o evidencia de correcciones realizadas según observaciones previas.', 0, 2, 'ACTIVO', '2026-06-08 14:44:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones`
--

CREATE TABLE `inscripciones` (
  `id` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_programa` int(11) NOT NULL,
  `fecha_inscripcion` date NOT NULL,
  `estado_academico` varchar(35) DEFAULT 'Cursando'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `inscripciones`
--

INSERT INTO `inscripciones` (`id`, `id_estudiante`, `id_programa`, `fecha_inscripcion`, `estado_academico`) VALUES
(1, 2, 1, '2026-05-20', 'CONCLUIDO_APROBADO'),
(2, 3, 2, '2026-05-22', 'CONCLUIDO_APROBADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `programa`
--

CREATE TABLE `programa` (
  `id` int(11) NOT NULL,
  `nombre_programa` varchar(150) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `creado_el` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `programa`
--

INSERT INTO `programa` (`id`, `nombre_programa`, `tipo`, `estado`, `creado_el`) VALUES
(1, 'Maestría en Gestión de Proyectos', 'Maestría', 1, '2026-06-02 14:59:18'),
(2, 'Diplomado en Gestión Educativa', 'Diplomado', 1, '2026-06-02 14:59:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `programa_fase_config`
--

CREATE TABLE `programa_fase_config` (
  `id` int(11) NOT NULL,
  `id_programa` int(11) NOT NULL,
  `id_fase` int(11) NOT NULL,
  `gestion` varchar(10) NOT NULL,
  `tipo_trabajo` varchar(50) NOT NULL,
  `fecha_inicio_entrega` datetime NOT NULL,
  `fecha_limite_entrega` datetime NOT NULL,
  `fecha_limite_revision` datetime NOT NULL,
  `fecha_devolucion_observaciones` datetime DEFAULT NULL,
  `nota_minima` decimal(5,2) DEFAULT 71.00,
  `estado` varchar(20) DEFAULT 'ACTIVO',
  `creado_por` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `programa_fase_config`
--

INSERT INTO `programa_fase_config` (`id`, `id_programa`, `id_fase`, `gestion`, `tipo_trabajo`, `fecha_inicio_entrega`, `fecha_limite_entrega`, `fecha_limite_revision`, `fecha_devolucion_observaciones`, `nota_minima`, `estado`, `creado_por`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 1, '2026', 'Monografía', '2026-06-01 08:00:00', '2026-06-10 23:59:00', '2026-06-15 23:59:00', '2026-06-17 23:59:00', '71.00', 'ACTIVO', 1, '2026-06-08 14:43:54', '2026-06-08 14:43:54'),
(2, 1, 2, '2026', 'Monografía', '2026-06-18 08:00:00', '2026-06-30 23:59:00', '2026-07-07 23:59:00', '2026-07-09 23:59:00', '71.00', 'ACTIVO', 1, '2026-06-08 14:43:54', '2026-06-08 14:43:54'),
(3, 1, 3, '2026', 'Monografía', '2026-07-10 08:00:00', '2026-07-20 23:59:00', '2026-07-28 23:59:00', '2026-07-30 23:59:00', '71.00', 'ACTIVO', 1, '2026-06-08 14:43:54', '2026-06-08 14:43:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `id` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id`, `nombre_rol`, `estado`) VALUES
(1, 'administrador', 1),
(2, 'estudiante', 1),
(3, 'docente', 1),
(4, 'tutor', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajos`
--

CREATE TABLE `trabajos` (
  `id` int(11) NOT NULL,
  `titulo_trabajo` varchar(255) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_fase_actual` int(11) NOT NULL,
  `fecha_presentacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado_aprobacion` varchar(30) DEFAULT 'En Revisión',
  `calificacion_final` decimal(5,2) DEFAULT NULL,
  `comentario_revision` text DEFAULT NULL,
  `fecha_revision` datetime DEFAULT NULL,
  `ruta_archivo` varchar(255) DEFAULT NULL,
  `actualizado_el` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `trabajos`
--

INSERT INTO `trabajos` (`id`, `titulo_trabajo`, `id_estudiante`, `id_fase_actual`, `fecha_presentacion`, `estado_aprobacion`, `calificacion_final`, `comentario_revision`, `fecha_revision`, `ruta_archivo`, `actualizado_el`) VALUES
(1, 'Fase 1 - Revisión administrativa', 2, 1, '2026-06-03 14:10:10', 'Aprobado', '80.00', 'FALTA EL CURRICUMLUM DEL DOCENTE TUTIR', '2026-06-03 10:23:58', 'uploads/2/1780495809_documentacion_administrativa.zip', '2026-06-03 14:23:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajo_docente`
--

CREATE TABLE `trabajo_docente` (
  `id_trabajo` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `tipo_asignacion` varchar(50) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(35) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `nombres` varchar(50) NOT NULL,
  `apellido_paterno` varchar(50) NOT NULL,
  `apellido_materno` varchar(50) DEFAULT NULL,
  `profesion_postgrado` varchar(150) DEFAULT NULL,
  `estado_cuenta` varchar(25) DEFAULT 'Activo',
  `creado_el` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_el` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `contrasena`, `nombres`, `apellido_paterno`, `apellido_materno`, `profesion_postgrado`, `estado_cuenta`, `creado_el`, `actualizado_el`) VALUES
(1, 'admin', '1234', 'Lic.', 'Varinia', '', 'Administración Académica', 'Activo', '2026-06-02 14:58:28', '2026-06-02 14:58:28'),
(2, 'estudiante', '1234', 'María', 'González', 'Pérez', 'Participante', 'Activo', '2026-06-02 14:58:28', '2026-06-02 14:58:28'),
(3, 'carlos', '1234', 'Carlos', 'Mendoza', 'Rojas', 'Participante', 'Activo', '2026-06-02 14:58:28', '2026-06-02 14:58:28'),
(4, 'docente', '1234', 'Carlos', 'Mendoza', '', 'Docente Calificador', 'Activo', '2026-06-02 14:58:28', '2026-06-02 14:58:28'),
(5, 'docente2', '1234', 'Ana', 'Rodríguez', '', 'Docente Calificadora', 'Activo', '2026-06-02 14:58:28', '2026-06-02 14:58:28'),
(6, 'tutor1', '1234', 'Roberto', 'Salinas', '', 'Tutor Académico', 'Activo', '2026-06-02 14:58:28', '2026-06-02 14:58:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_rol`
--

CREATE TABLE `usuario_rol` (
  `id_usuario` int(11) NOT NULL,
  `id_role` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `usuario_rol`
--

INSERT INTO `usuario_rol` (`id_usuario`, `id_role`) VALUES
(1, 1),
(2, 2),
(3, 2),
(4, 3),
(5, 3),
(6, 4);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `fases`
--
ALTER TABLE `fases`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `fase_estudiante_config`
--
ALTER TABLE `fase_estudiante_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_configuracion` (`id_configuracion`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indices de la tabla `fase_requisitos`
--
ALTER TABLE `fase_requisitos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_configuracion` (`id_configuracion`);

--
-- Indices de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `id_programa` (`id_programa`);

--
-- Indices de la tabla `programa`
--
ALTER TABLE `programa`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `programa_fase_config`
--
ALTER TABLE `programa_fase_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_programa` (`id_programa`),
  ADD KEY `id_fase` (`id_fase`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `trabajos`
--
ALTER TABLE `trabajos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `id_fase_actual` (`id_fase_actual`);

--
-- Indices de la tabla `trabajo_docente`
--
ALTER TABLE `trabajo_docente`
  ADD PRIMARY KEY (`id_trabajo`,`id_docente`,`tipo_asignacion`),
  ADD KEY `id_docente` (`id_docente`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Indices de la tabla `usuario_rol`
--
ALTER TABLE `usuario_rol`
  ADD PRIMARY KEY (`id_usuario`,`id_role`),
  ADD KEY `id_role` (`id_role`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `fases`
--
ALTER TABLE `fases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `fase_estudiante_config`
--
ALTER TABLE `fase_estudiante_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fase_requisitos`
--
ALTER TABLE `fase_requisitos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `programa`
--
ALTER TABLE `programa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `programa_fase_config`
--
ALTER TABLE `programa_fase_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `trabajos`
--
ALTER TABLE `trabajos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `fase_estudiante_config`
--
ALTER TABLE `fase_estudiante_config`
  ADD CONSTRAINT `fase_estudiante_config_ibfk_1` FOREIGN KEY (`id_configuracion`) REFERENCES `programa_fase_config` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fase_estudiante_config_ibfk_2` FOREIGN KEY (`id_estudiante`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fase_estudiante_config_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `fase_requisitos`
--
ALTER TABLE `fase_requisitos`
  ADD CONSTRAINT `fase_requisitos_ibfk_1` FOREIGN KEY (`id_configuracion`) REFERENCES `programa_fase_config` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `inscripciones_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscripciones_ibfk_2` FOREIGN KEY (`id_programa`) REFERENCES `programa` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `programa_fase_config`
--
ALTER TABLE `programa_fase_config`
  ADD CONSTRAINT `programa_fase_config_ibfk_1` FOREIGN KEY (`id_programa`) REFERENCES `programa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `programa_fase_config_ibfk_2` FOREIGN KEY (`id_fase`) REFERENCES `fases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `programa_fase_config_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `trabajos`
--
ALTER TABLE `trabajos`
  ADD CONSTRAINT `trabajos_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trabajos_ibfk_2` FOREIGN KEY (`id_fase_actual`) REFERENCES `fases` (`id`);

--
-- Filtros para la tabla `trabajo_docente`
--
ALTER TABLE `trabajo_docente`
  ADD CONSTRAINT `trabajo_docente_ibfk_1` FOREIGN KEY (`id_trabajo`) REFERENCES `trabajos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trabajo_docente_ibfk_2` FOREIGN KEY (`id_docente`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario_rol`
--
ALTER TABLE `usuario_rol`
  ADD CONSTRAINT `usuario_rol_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_rol_ibfk_2` FOREIGN KEY (`id_role`) REFERENCES `rol` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
