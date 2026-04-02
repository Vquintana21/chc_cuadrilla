-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 02-04-2026 a las 18:16:38
-- Versión del servidor: 5.7.44-log
-- Versión de PHP: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `dpimeduc_calendario`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_p_cuadrilla`
--

CREATE TABLE `chc_p_cuadrilla` (
  `idcuadrilla` int(11) NOT NULL,
  `idsolicitud` int(11) NOT NULL,
  `idsubtipo` int(11) NOT NULL,
  `resumen_actividad` text,
  `insumos` text,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `rut_pec` varchar(15) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` datetime DEFAULT NULL,
  `fecha_envio` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_p_cuadrilla_capacitacion`
--

CREATE TABLE `chc_p_cuadrilla_capacitacion` (
  `id` int(11) NOT NULL,
  `idcuadrilla` int(11) NOT NULL,
  `orden` tinyint(1) NOT NULL,
  `modalidad` varchar(20) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `jornada` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_p_cuadrilla_debriefing`
--

CREATE TABLE `chc_p_cuadrilla_debriefing` (
  `id` int(11) NOT NULL,
  `idcuadrilla` int(11) NOT NULL,
  `implementacion_briefing` text,
  `implementacion_debriefing` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_p_cuadrilla_fecha`
--

CREATE TABLE `chc_p_cuadrilla_fecha` (
  `id` int(11) NOT NULL,
  `idcuadrilla` int(11) NOT NULL,
  `idplanclases` bigint(20) NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_termino` time DEFAULT NULL,
  `nro_pacientes` tinyint(3) DEFAULT NULL,
  `link_actividad` varchar(500) DEFAULT NULL,
  `ubicacion` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_p_cuadrilla_subtipo`
--

CREATE TABLE `chc_p_cuadrilla_subtipo` (
  `idsubtipo` int(11) NOT NULL,
  `idmodalidad` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `tiene_seccion3` tinyint(1) NOT NULL DEFAULT '1',
  `tiene_pacientes` tinyint(1) NOT NULL DEFAULT '0',
  `tiene_insumos` tinyint(1) NOT NULL DEFAULT '0',
  `tiene_debriefing` tinyint(1) NOT NULL DEFAULT '1',
  `tiene_link` tinyint(1) NOT NULL DEFAULT '0',
  `tiene_ubicacion` tinyint(1) NOT NULL DEFAULT '0',
  `observaciones` text,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `chc_p_cuadrilla`
--
ALTER TABLE `chc_p_cuadrilla`
  ADD PRIMARY KEY (`idcuadrilla`),
  ADD UNIQUE KEY `unique_p_cuadrilla_solicitud` (`idsolicitud`),
  ADD KEY `fk_p_cuadrilla_solicitud` (`idsolicitud`),
  ADD KEY `fk_p_cuadrilla_subtipo` (`idsubtipo`);

--
-- Indices de la tabla `chc_p_cuadrilla_capacitacion`
--
ALTER TABLE `chc_p_cuadrilla_capacitacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_p_cap_orden` (`idcuadrilla`,`orden`),
  ADD KEY `fk_p_ccap_cuadrilla` (`idcuadrilla`);

--
-- Indices de la tabla `chc_p_cuadrilla_debriefing`
--
ALTER TABLE `chc_p_cuadrilla_debriefing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_p_debriefing_cuadrilla` (`idcuadrilla`),
  ADD KEY `fk_p_cdb_cuadrilla` (`idcuadrilla`);

--
-- Indices de la tabla `chc_p_cuadrilla_fecha`
--
ALTER TABLE `chc_p_cuadrilla_fecha`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_p_fecha_planclase` (`idcuadrilla`,`idplanclases`),
  ADD KEY `fk_p_cf_cuadrilla` (`idcuadrilla`),
  ADD KEY `fk_p_cf_planclases` (`idplanclases`);

--
-- Indices de la tabla `chc_p_cuadrilla_subtipo`
--
ALTER TABLE `chc_p_cuadrilla_subtipo`
  ADD PRIMARY KEY (`idsubtipo`),
  ADD KEY `fk_p_subtipo_modalidad` (`idmodalidad`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `chc_p_cuadrilla`
--
ALTER TABLE `chc_p_cuadrilla`
  MODIFY `idcuadrilla` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_p_cuadrilla_capacitacion`
--
ALTER TABLE `chc_p_cuadrilla_capacitacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_p_cuadrilla_debriefing`
--
ALTER TABLE `chc_p_cuadrilla_debriefing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_p_cuadrilla_fecha`
--
ALTER TABLE `chc_p_cuadrilla_fecha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_p_cuadrilla_subtipo`
--
ALTER TABLE `chc_p_cuadrilla_subtipo`
  MODIFY `idsubtipo` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `chc_p_cuadrilla`
--
ALTER TABLE `chc_p_cuadrilla`
  ADD CONSTRAINT `fk_p_cuad_solicitud` FOREIGN KEY (`idsolicitud`) REFERENCES `chc_solicitud` (`idsolicitud`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_p_cuad_subtipo` FOREIGN KEY (`idsubtipo`) REFERENCES `chc_p_cuadrilla_subtipo` (`idsubtipo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `chc_p_cuadrilla_fecha`
--
ALTER TABLE `chc_p_cuadrilla_fecha`
  ADD CONSTRAINT `fk_p_cf_cuad` FOREIGN KEY (`idcuadrilla`) REFERENCES `chc_p_cuadrilla` (`idcuadrilla`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
