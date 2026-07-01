-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-06-2026 a las 21:59:15
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `siget_externa`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ext_inscripciones`
--

CREATE TABLE `ext_inscripciones` (
  `id_inscripcion_externa` int(11) NOT NULL,
  `id_programa_externo` int(11) NOT NULL,
  `id_participante_externo` int(11) NOT NULL,
  `fecha_inscripcion` date NOT NULL,
  `estado_cartera` enum('EN_MORA','EXENTO_DE_DEUDA','RETRASADO','VIGENTE') DEFAULT 'VIGENTE',
  `estado_academico` enum('EN_DESARROLLO','CONCLUIDO','REPROBADO') DEFAULT 'EN_DESARROLLO',
  `estado_acceso` enum('HABILITADO','NO_HABILITADO') DEFAULT 'HABILITADO',
  `observacion_cartera` text DEFAULT NULL,
  `observacion_academica` text DEFAULT NULL,
  `motivo_bloqueo` text DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `creado_el` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_el` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ext_inscripciones`
--

INSERT INTO `ext_inscripciones` (`id_inscripcion_externa`, `id_programa_externo`, `id_participante_externo`, `fecha_inscripcion`, `estado_cartera`, `estado_academico`, `estado_acceso`, `observacion_cartera`, `observacion_academica`, `motivo_bloqueo`, `estado`, `creado_el`, `actualizado_el`) VALUES
(1, 1, 1, '2026-01-12', 'VIGENTE', 'CONCLUIDO', 'HABILITADO', NULL, 'Programa concluido satisfactoriamente.', NULL, 1, '2026-06-15 19:49:30', '2026-06-15 19:49:30'),
(2, 1, 2, '2026-01-15', 'EN_MORA', 'CONCLUIDO', 'NO_HABILITADO', 'Tiene cuotas pendientes.', 'Programa concluido, pendiente regularización de cartera.', 'Bloqueado por mora.', 1, '2026-06-15 19:49:30', '2026-06-15 19:49:30'),
(3, 2, 3, '2026-02-20', 'EXENTO_DE_DEUDA', 'CONCLUIDO', 'HABILITADO', 'Participante exento de pago.', 'Programa concluido.', NULL, 1, '2026-06-15 19:49:30', '2026-06-15 19:49:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ext_participantes`
--

CREATE TABLE `ext_participantes` (
  `id_participante_externo` int(11) NOT NULL,
  `codigo_participante` varchar(50) NOT NULL,
  `ci` varchar(30) DEFAULT NULL,
  `nombres` varchar(80) NOT NULL,
  `apellido_paterno` varchar(80) NOT NULL,
  `apellido_materno` varchar(80) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `celular` varchar(30) DEFAULT NULL,
  `profesion` varchar(150) DEFAULT NULL,
  `estado_registro` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `creado_el` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_el` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ext_participantes`
--

INSERT INTO `ext_participantes` (`id_participante_externo`, `codigo_participante`, `ci`, `nombres`, `apellido_paterno`, `apellido_materno`, `correo`, `celular`, `profesion`, `estado_registro`, `creado_el`, `actualizado_el`) VALUES
(1, 'PART-0001', '1234567', 'María', 'González', 'Pérez', 'maria.gonzalez@gmail.com', '70000001', 'Participante', 'ACTIVO', '2026-06-15 19:49:30', '2026-06-15 19:49:30'),
(2, 'PART-0002', '7654321', 'Carlos', 'Mendoza', 'Rojas', 'carlos.mendoza@gmail.com', '70000002', 'Participante', 'ACTIVO', '2026-06-15 19:49:30', '2026-06-15 19:49:30'),
(3, 'PART-0003', '5558889', 'Ana', 'Quispe', 'Mamani', 'ana.quispe@gmail.com', '70000003', 'Participante', 'ACTIVO', '2026-06-15 19:49:30', '2026-06-15 19:49:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ext_programas`
--

CREATE TABLE `ext_programas` (
  `id_programa_externo` int(11) NOT NULL,
  `codigo_programa` varchar(50) NOT NULL,
  `nombre_programa` varchar(200) NOT NULL,
  `tipo_programa` enum('DIPLOMADO','MAESTRIA','ESPECIALIDAD','CURSO') NOT NULL,
  `gestion` varchar(10) NOT NULL,
  `version_programa` varchar(20) DEFAULT '1',
  `id_sede` int(11) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado_programa` enum('EN_DESARROLLO','CONCLUIDO','REPROBADO','INACTIVO') DEFAULT 'EN_DESARROLLO',
  `estado` tinyint(1) DEFAULT 1,
  `creado_el` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_el` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ext_programas`
--

INSERT INTO `ext_programas` (`id_programa_externo`, `codigo_programa`, `nombre_programa`, `tipo_programa`, `gestion`, `version_programa`, `id_sede`, `fecha_inicio`, `fecha_fin`, `estado_programa`, `estado`, `creado_el`, `actualizado_el`) VALUES
(1, 'MAE-GP-2026-V1', 'Maestría en Gestión de Proyectos', 'MAESTRIA', '2026', '1', 1, '2026-01-10', '2026-06-10', 'CONCLUIDO', 1, '2026-06-15 19:49:30', '2026-06-15 19:49:30'),
(2, 'DIP-GE-2026-V1', 'Diplomado en Gestión Educativa', 'DIPLOMADO', '2026', '1', 1, '2026-02-15', '2026-05-30', 'CONCLUIDO', 1, '2026-06-15 19:49:30', '2026-06-15 19:49:30'),
(3, 'DIP-IA-2026-V1', 'Diplomado en Inteligencia Artificial Aplicada', 'DIPLOMADO', '2026', '1', 2, '2026-06-01', NULL, 'EN_DESARROLLO', 1, '2026-06-15 19:49:30', '2026-06-15 19:49:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ext_sedes`
--

CREATE TABLE `ext_sedes` (
  `id_sede` int(11) NOT NULL,
  `nombre_sede` varchar(100) NOT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `creado_el` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ext_sedes`
--

INSERT INTO `ext_sedes` (`id_sede`, `nombre_sede`, `ciudad`, `estado`, `creado_el`) VALUES
(1, 'ESAM La Paz Central', 'La Paz', 1, '2026-06-15 19:49:30'),
(2, 'ESAM El Alto', 'El Alto', 1, '2026-06-15 19:49:30'),
(3, 'ESAM Sede Norte', 'La Paz', 1, '2026-06-15 19:49:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ext_usuarios_acceso`
--

CREATE TABLE `ext_usuarios_acceso` (
  `id_usuario_acceso` int(11) NOT NULL,
  `id_participante_externo` int(11) DEFAULT NULL,
  `usuario` varchar(80) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `rol` enum('PARTICIPANTE','ADMINISTRADOR','DOCENTE','TUTOR') DEFAULT 'PARTICIPANTE',
  `estado_acceso` enum('HABILITADO','NO_HABILITADO') DEFAULT 'HABILITADO',
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_el` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_el` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ext_usuarios_acceso`
--

INSERT INTO `ext_usuarios_acceso` (`id_usuario_acceso`, `id_participante_externo`, `usuario`, `contrasena_hash`, `rol`, `estado_acceso`, `ultimo_acceso`, `creado_el`, `actualizado_el`) VALUES
(1, 1, 'maria.gonzalez', 'CAMBIAR_POR_HASH_REAL', 'PARTICIPANTE', 'HABILITADO', NULL, '2026-06-15 19:49:30', '2026-06-15 19:49:30'),
(2, 2, 'carlos.mendoza', 'CAMBIAR_POR_HASH_REAL', 'PARTICIPANTE', 'NO_HABILITADO', NULL, '2026-06-15 19:49:30', '2026-06-15 19:49:30'),
(3, 3, 'ana.quispe', 'CAMBIAR_POR_HASH_REAL', 'PARTICIPANTE', 'HABILITADO', NULL, '2026-06-15 19:49:30', '2026-06-15 19:49:30');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_participantes_programa`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_participantes_programa` (
`id_inscripcion_externa` int(11)
,`id_programa_externo` int(11)
,`codigo_programa` varchar(50)
,`nombre_programa` varchar(200)
,`tipo_programa` enum('DIPLOMADO','MAESTRIA','ESPECIALIDAD','CURSO')
,`gestion` varchar(10)
,`version_programa` varchar(20)
,`id_participante_externo` int(11)
,`codigo_participante` varchar(50)
,`ci` varchar(30)
,`nombres` varchar(80)
,`apellido_paterno` varchar(80)
,`apellido_materno` varchar(80)
,`nombre_completo` varchar(242)
,`correo` varchar(120)
,`celular` varchar(30)
,`profesion` varchar(150)
,`estado_cartera` enum('EN_MORA','EXENTO_DE_DEUDA','RETRASADO','VIGENTE')
,`estado_academico` enum('EN_DESARROLLO','CONCLUIDO','REPROBADO')
,`estado_acceso` enum('HABILITADO','NO_HABILITADO')
,`resultado_habilitacion` varchar(13)
,`observacion_cartera` text
,`observacion_academica` text
,`motivo_bloqueo` text
,`actualizado_el` timestamp
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_programas_concluidos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_programas_concluidos` (
`id_programa_externo` int(11)
,`codigo_programa` varchar(50)
,`nombre_programa` varchar(200)
,`tipo_programa` enum('DIPLOMADO','MAESTRIA','ESPECIALIDAD','CURSO')
,`gestion` varchar(10)
,`version_programa` varchar(20)
,`nombre_sede` varchar(100)
,`fecha_inicio` date
,`fecha_fin` date
,`estado_programa` enum('EN_DESARROLLO','CONCLUIDO','REPROBADO','INACTIVO')
,`total_participantes` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_participantes_programa`
--
DROP TABLE IF EXISTS `vw_participantes_programa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_participantes_programa`  AS SELECT `i`.`id_inscripcion_externa` AS `id_inscripcion_externa`, `p`.`id_programa_externo` AS `id_programa_externo`, `p`.`codigo_programa` AS `codigo_programa`, `p`.`nombre_programa` AS `nombre_programa`, `p`.`tipo_programa` AS `tipo_programa`, `p`.`gestion` AS `gestion`, `p`.`version_programa` AS `version_programa`, `par`.`id_participante_externo` AS `id_participante_externo`, `par`.`codigo_participante` AS `codigo_participante`, `par`.`ci` AS `ci`, `par`.`nombres` AS `nombres`, `par`.`apellido_paterno` AS `apellido_paterno`, `par`.`apellido_materno` AS `apellido_materno`, concat(`par`.`apellido_paterno`,' ',ifnull(`par`.`apellido_materno`,''),' ',`par`.`nombres`) AS `nombre_completo`, `par`.`correo` AS `correo`, `par`.`celular` AS `celular`, `par`.`profesion` AS `profesion`, `i`.`estado_cartera` AS `estado_cartera`, `i`.`estado_academico` AS `estado_academico`, `i`.`estado_acceso` AS `estado_acceso`, CASE WHEN `i`.`estado_cartera` in ('VIGENTE','EXENTO_DE_DEUDA') AND `i`.`estado_academico` = 'CONCLUIDO' AND `i`.`estado_acceso` = 'HABILITADO' THEN 'HABILITADO' ELSE 'NO_HABILITADO' END AS `resultado_habilitacion`, `i`.`observacion_cartera` AS `observacion_cartera`, `i`.`observacion_academica` AS `observacion_academica`, `i`.`motivo_bloqueo` AS `motivo_bloqueo`, `i`.`actualizado_el` AS `actualizado_el` FROM ((`ext_inscripciones` `i` join `ext_programas` `p` on(`p`.`id_programa_externo` = `i`.`id_programa_externo`)) join `ext_participantes` `par` on(`par`.`id_participante_externo` = `i`.`id_participante_externo`)) WHERE `i`.`estado` = 1 ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_programas_concluidos`
--
DROP TABLE IF EXISTS `vw_programas_concluidos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_programas_concluidos`  AS SELECT `p`.`id_programa_externo` AS `id_programa_externo`, `p`.`codigo_programa` AS `codigo_programa`, `p`.`nombre_programa` AS `nombre_programa`, `p`.`tipo_programa` AS `tipo_programa`, `p`.`gestion` AS `gestion`, `p`.`version_programa` AS `version_programa`, `s`.`nombre_sede` AS `nombre_sede`, `p`.`fecha_inicio` AS `fecha_inicio`, `p`.`fecha_fin` AS `fecha_fin`, `p`.`estado_programa` AS `estado_programa`, count(`i`.`id_inscripcion_externa`) AS `total_participantes` FROM ((`ext_programas` `p` left join `ext_sedes` `s` on(`s`.`id_sede` = `p`.`id_sede`)) left join `ext_inscripciones` `i` on(`i`.`id_programa_externo` = `p`.`id_programa_externo`)) WHERE `p`.`estado_programa` = 'CONCLUIDO' AND `p`.`estado` = 1 GROUP BY `p`.`id_programa_externo`, `p`.`codigo_programa`, `p`.`nombre_programa`, `p`.`tipo_programa`, `p`.`gestion`, `p`.`version_programa`, `s`.`nombre_sede`, `p`.`fecha_inicio`, `p`.`fecha_fin`, `p`.`estado_programa` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `ext_inscripciones`
--
ALTER TABLE `ext_inscripciones`
  ADD PRIMARY KEY (`id_inscripcion_externa`),
  ADD UNIQUE KEY `uk_programa_participante` (`id_programa_externo`,`id_participante_externo`),
  ADD KEY `fk_ext_inscripciones_participante` (`id_participante_externo`);

--
-- Indices de la tabla `ext_participantes`
--
ALTER TABLE `ext_participantes`
  ADD PRIMARY KEY (`id_participante_externo`),
  ADD UNIQUE KEY `codigo_participante` (`codigo_participante`);

--
-- Indices de la tabla `ext_programas`
--
ALTER TABLE `ext_programas`
  ADD PRIMARY KEY (`id_programa_externo`),
  ADD UNIQUE KEY `codigo_programa` (`codigo_programa`),
  ADD KEY `fk_ext_programas_sede` (`id_sede`);

--
-- Indices de la tabla `ext_sedes`
--
ALTER TABLE `ext_sedes`
  ADD PRIMARY KEY (`id_sede`);

--
-- Indices de la tabla `ext_usuarios_acceso`
--
ALTER TABLE `ext_usuarios_acceso`
  ADD PRIMARY KEY (`id_usuario_acceso`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `fk_ext_usuarios_participante` (`id_participante_externo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `ext_inscripciones`
--
ALTER TABLE `ext_inscripciones`
  MODIFY `id_inscripcion_externa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ext_participantes`
--
ALTER TABLE `ext_participantes`
  MODIFY `id_participante_externo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ext_programas`
--
ALTER TABLE `ext_programas`
  MODIFY `id_programa_externo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ext_sedes`
--
ALTER TABLE `ext_sedes`
  MODIFY `id_sede` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ext_usuarios_acceso`
--
ALTER TABLE `ext_usuarios_acceso`
  MODIFY `id_usuario_acceso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `ext_inscripciones`
--
ALTER TABLE `ext_inscripciones`
  ADD CONSTRAINT `fk_ext_inscripciones_participante` FOREIGN KEY (`id_participante_externo`) REFERENCES `ext_participantes` (`id_participante_externo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ext_inscripciones_programa` FOREIGN KEY (`id_programa_externo`) REFERENCES `ext_programas` (`id_programa_externo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ext_programas`
--
ALTER TABLE `ext_programas`
  ADD CONSTRAINT `fk_ext_programas_sede` FOREIGN KEY (`id_sede`) REFERENCES `ext_sedes` (`id_sede`) ON DELETE SET NULL;

--
-- Filtros para la tabla `ext_usuarios_acceso`
--
ALTER TABLE `ext_usuarios_acceso`
  ADD CONSTRAINT `fk_ext_usuarios_participante` FOREIGN KEY (`id_participante_externo`) REFERENCES `ext_participantes` (`id_participante_externo`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
