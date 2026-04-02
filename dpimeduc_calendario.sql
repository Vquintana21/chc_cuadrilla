-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 02-04-2026 a las 16:09:03
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
-- Estructura de tabla para la tabla `chc_actividades_externas`
--

CREATE TABLE `chc_actividades_externas` (
  `id` int(11) NOT NULL,
  `dia` datetime NOT NULL,
  `inicio` time NOT NULL,
  `termino` time NOT NULL,
  `comentarios` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_cuadrilla_doc`
--

CREATE TABLE `chc_cuadrilla_doc` (
  `idcuadrilla` int(11) NOT NULL,
  `idsolicitud` int(11) NOT NULL,
  `ruta_pdf` varchar(255) COLLATE utf8_spanish_ci NOT NULL,
  `nombre_archivo` varchar(255) COLLATE utf8_spanish_ci NOT NULL,
  `comentario` text COLLATE utf8_spanish_ci,
  `rut_usuario` varchar(12) COLLATE utf8_spanish_ci NOT NULL,
  `fecha_subida` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `idestadocuadrilla` int(11) NOT NULL DEFAULT '1',
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_espacio_requerido`
--

CREATE TABLE `chc_espacio_requerido` (
  `idespacio` int(11) NOT NULL,
  `espacio_requerido` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_estado_agenda`
--

CREATE TABLE `chc_estado_agenda` (
  `idestadoagenda` int(11) NOT NULL,
  `estado_agenda` varchar(100) NOT NULL,
  `observacion` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_estado_cuadrilla`
--

CREATE TABLE `chc_estado_cuadrilla` (
  `idestadocuadrilla` int(11) NOT NULL,
  `estado_cuadrilla` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_log_gestor`
--

CREATE TABLE `chc_log_gestor` (
  `id` int(11) NOT NULL,
  `rut_usuario` varchar(20) NOT NULL,
  `idsolicitud` int(11) NOT NULL,
  `estado` int(11) NOT NULL DEFAULT '1',
  `accion` varchar(100) NOT NULL,
  `detalles` text,
  `fecha` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_modalidad`
--

CREATE TABLE `chc_modalidad` (
  `idmodalidad` int(11) NOT NULL,
  `modalidad` varchar(100) NOT NULL,
  `detalle` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_periodosys`
--

CREATE TABLE `chc_periodosys` (
  `id` int(11) NOT NULL,
  `periodo` varchar(10) NOT NULL,
  `inicio` datetime NOT NULL,
  `fin` datetime NOT NULL,
  `fin_revision` datetime NOT NULL,
  `activo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_solicitud`
--

CREATE TABLE `chc_solicitud` (
  `idsolicitud` int(11) NOT NULL,
  `idcurso` int(11) NOT NULL,
  `periodo` varchar(11) NOT NULL,
  `carrera` varchar(2) NOT NULL,
  `codigocurso` varchar(15) NOT NULL,
  `seccion` int(3) NOT NULL,
  `nombrecurso` varchar(100) NOT NULL,
  `rutpec` varchar(15) NOT NULL,
  `nombrepec` varchar(255) DEFAULT NULL,
  `correopec` varchar(50) NOT NULL,
  `uso_fantoma` int(11) NOT NULL,
  `fantoma_capacitado` int(11) NOT NULL,
  `fantoma_fecha_capacitacion` date DEFAULT NULL,
  `fantoma_hora_capacitacion` time DEFAULT NULL,
  `npacientes` varchar(10) NOT NULL,
  `nestudiantesxsesion` int(11) NOT NULL,
  `nboxes` varchar(10) NOT NULL,
  `espacio_requerido_otros` text NOT NULL,
  `uso_debriefing` int(11) NOT NULL,
  `observaciones` varchar(2000) NOT NULL,
  `comentarios` text,
  `idestadoagenda` int(11) NOT NULL,
  `idestadocuadrilla` int(11) NOT NULL,
  `fecha_registro` datetime NOT NULL,
  `comentario_agenda` varchar(2000) NOT NULL,
  `registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='tabla donde se guardarán las respuestas de la agtenda CHC';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_solicitud_actividad`
--

CREATE TABLE `chc_solicitud_actividad` (
  `id` int(11) NOT NULL,
  `idsolicitud` int(11) NOT NULL,
  `idplanclases` bigint(20) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_solicitud_espacio`
--

CREATE TABLE `chc_solicitud_espacio` (
  `id` int(11) NOT NULL,
  `idsolicitud` int(11) NOT NULL,
  `idespacio` int(11) NOT NULL,
  `otro` text,
  `registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_solicitud_modalidad`
--

CREATE TABLE `chc_solicitud_modalidad` (
  `id` int(11) NOT NULL,
  `idsolicitud` int(11) NOT NULL,
  `idmodalidad` int(11) NOT NULL,
  `registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chc_usuario`
--

CREATE TABLE `chc_usuario` (
  `id` int(11) NOT NULL,
  `rut` varchar(20) NOT NULL,
  `nombre` varchar(2000) NOT NULL,
  `correo` varchar(200) NOT NULL,
  `admin` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pcl_TipoSesion`
--

CREATE TABLE `pcl_TipoSesion` (
  `id` int(11) NOT NULL,
  `tipo_sesion` varchar(200) NOT NULL,
  `Sub_tipo_sesion` varchar(200) NOT NULL,
  `tipo_activo` int(11) NOT NULL,
  `subtipo_activo` int(11) NOT NULL,
  `pedir_sala` int(11) NOT NULL,
  `docentes` int(11) NOT NULL,
  `actividades_eximir` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `pcl_TipoSesion`
--

INSERT INTO `pcl_TipoSesion` (`id`, `tipo_sesion`, `Sub_tipo_sesion`, `tipo_activo`, `subtipo_activo`, `pedir_sala`, `docentes`, `actividades_eximir`) VALUES
(1, 'Actividad Grupal', 'Aula invertida', 1, 1, 1, 1, 0),
(2, 'Actividad Grupal', 'Creación Audiovisual', 1, 1, 1, 1, 0),
(3, 'Actividad Grupal', 'Debate', 1, 1, 1, 1, 0),
(4, 'Actividad Grupal', 'Estudio de casos', 1, 1, 1, 1, 0),
(5, 'Actividad Grupal', 'Retroalimentación', 1, 1, 1, 1, 0),
(6, 'Actividad Grupal', 'Role playing', 1, 1, 1, 1, 0),
(7, 'Actividad Grupal', 'Seminario', 1, 1, 1, 1, 0),
(8, 'Actividad Grupal', 'Taller', 1, 1, 1, 1, 0),
(9, 'Actividad Grupal', 'Tutoría', 1, 1, 1, 1, 0),
(10, 'Autoaprendizaje', 'Autoaprendizaje', 0, 0, 0, 0, 1),
(11, 'Bloque Protegido', '', 0, 0, 0, 0, 1),
(12, 'Clase', 'Clase teórica o expositiva', 1, 0, 1, 1, 0),
(13, 'Evaluación', 'Coevaluación o evaluación entre pares', 1, 1, 1, 1, 0),
(14, 'Evaluación', 'Control', 1, 1, 1, 1, 0),
(15, 'Evaluación', 'Evaluación de desempeño clínico', 1, 1, 1, 1, 0),
(16, 'Evaluación', 'Evaluación recuperativa', 1, 1, 1, 1, 0),
(17, 'Evaluación', 'Presentación individual o grupal', 1, 1, 1, 1, 0),
(18, 'Evaluación', 'Prueba oral (interrogación)', 1, 1, 1, 1, 0),
(19, 'Evaluación', 'Prueba práctica (demostración o similar)', 1, 1, 1, 1, 0),
(20, 'Evaluación', 'Prueba teórica o certamen', 1, 1, 1, 1, 0),
(21, 'Evaluación', 'Trabajo escrito (informe, revisión bibliográfica, ensayo o similar)', 1, 1, 1, 1, 0),
(22, 'Examen', 'Examen de Primera Práctico', 1, 1, 1, 1, 0),
(23, 'Examen', 'Examen de Primera Teórico', 1, 1, 1, 1, 0),
(24, 'Examen', 'Examen de Primera Teórico-Práctico', 1, 1, 1, 1, 0),
(25, 'Examen', 'Examen de Segunda Práctico', 1, 1, 1, 1, 0),
(26, 'Examen', 'Examen de Segunda Teórico', 1, 1, 1, 1, 0),
(27, 'Examen', 'Examen de Segunda Teórico-Práctico', 1, 1, 1, 1, 0),
(28, 'Examen', 'Presentación individual o grupal', 1, 1, 1, 1, 0),
(29, 'Feriado', 'Feriado', 0, 0, 0, 0, 1),
(30, 'Práctica Clínica', 'Práctica Clínica', 1, 0, 0, 1, 1),
(31, 'Sin actividad', 'Sin actividad', 1, 0, 0, 0, 1),
(32, 'Trabajo Autonomo', '', 1, 0, 0, 0, 1),
(33, 'Trabajo Práctico', 'Centro de Simulación', 1, 1, 1, 1, 0),
(34, 'Trabajo Práctico', 'Instalaciones Deportivas', 1, 1, 1, 1, 0),
(35, 'Trabajo Práctico', 'Laboratorios', 1, 1, 1, 1, 0),
(36, 'Vacaciones', 'Vacaciones', 0, 0, 0, 0, 1),
(37, 'Visita a terreno', 'Visita a terreno', 1, 0, 0, 1, 1),
(38, 'CHC', 'Simulación con pacientes simulados', 1, 1, 1, 1, 1),
(39, 'CHC', 'Simulación de alta fidelidad (Fantoma HAL)', 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planclases`
--

CREATE TABLE `planclases` (
  `idplanclases` bigint(20) NOT NULL,
  `cursos_idcursos` int(11) NOT NULL DEFAULT '0',
  `pcl_Periodo` int(11) DEFAULT NULL,
  `pcl_tituloActividad` varchar(200) DEFAULT '',
  `pcl_tipoActividad` int(11) DEFAULT '0',
  `pcl_Modalidad` varchar(45) DEFAULT NULL,
  `pcl_Presencialidad` varchar(1) DEFAULT NULL,
  `pcl_ActividadConEvaluacion` varchar(1) DEFAULT '',
  `pcl_Observaciones` text,
  `pcl_Fecha` datetime DEFAULT NULL,
  `pcl_Lugar` int(11) DEFAULT '0',
  `pcl_tipoLugar` int(11) DEFAULT '0',
  `pcl_nGrupos` int(11) DEFAULT '0',
  `pcl_alumnos` int(11) DEFAULT NULL,
  `pcl_alumnosGrupo` int(11) DEFAULT '0',
  `pcl_docentesGrupo` int(11) DEFAULT '0',
  `pcl_Inicio` time DEFAULT NULL,
  `pcl_Termino` time DEFAULT NULL,
  `unidad_idunidad` int(11) DEFAULT '0',
  `pcl_enGrupos` varchar(1) DEFAULT '',
  `pcl_Estilo` int(11) DEFAULT '0',
  `pcl_Con` int(11) DEFAULT '0',
  `pcl_fechamodifica` datetime DEFAULT CURRENT_TIMESTAMP,
  `pcl_usermodifica` varchar(100) DEFAULT '',
  `pcl_aluexclusivos` int(11) DEFAULT '0',
  `pcl_campus` varchar(50) DEFAULT '0',
  `pcl_nSalas` int(11) DEFAULT '0',
  `pcl_Seccion` int(11) DEFAULT '0',
  `pcl_condicion` varchar(20) DEFAULT '',
  `pcl_HorasPresenciales` time DEFAULT NULL,
  `pcl_HorasNoPresenciales` time DEFAULT '00:00:00',
  `pcl_TipoSesion` varchar(100) DEFAULT NULL,
  `pcl_SubTipoSesion` varchar(200) DEFAULT NULL,
  `pcl_LugarDesc` varchar(100) DEFAULT NULL,
  `pcl_Aula` varchar(100) DEFAULT NULL,
  `pcl_movilidadReducida` varchar(1) NOT NULL,
  `pcl_CapacidadReducida` varchar(500) DEFAULT NULL,
  `pcl_FechaCreacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `pcl_Semana` int(11) DEFAULT NULL,
  `pcl_AlumnosSala` int(11) DEFAULT NULL,
  `pcl_Justificacion` tinytext,
  `pcl_Guardado` varchar(5) DEFAULT NULL,
  `pcl_Cercania` varchar(2) DEFAULT NULL,
  `pcl_SalasCercanas` varchar(500) DEFAULT NULL,
  `pcl_AulaDescripcion` varchar(500) DEFAULT NULL,
  `pcl_AsiCodigo` varchar(20) DEFAULT NULL,
  `pcl_AsiNombre` varchar(200) DEFAULT NULL,
  `pcl_OrigCampus` varchar(20) DEFAULT NULL,
  `pcl_OrigLugarDesc` varchar(500) DEFAULT NULL,
  `pcl_OrigAula` varchar(100) DEFAULT NULL,
  `pcl_OrignSalas` int(11) DEFAULT NULL,
  `Sala` varchar(50) DEFAULT NULL,
  `Bloque` varchar(50) DEFAULT NULL,
  `dia` varchar(50) DEFAULT NULL,
  `pcl_SinActividadJustificacion` varchar(200) DEFAULT NULL,
  `pcl_BloqueExtendido` int(1) DEFAULT '0',
  `pcl_BloqueExtendidoActivado` int(1) DEFAULT '0',
  `pcl_MantenerSala` int(1) DEFAULT '0',
  `pcl_DeseaSala` int(1) DEFAULT NULL,
  `AsigAuto` int(1) DEFAULT '1',
  `pcl_Publico` int(1) NOT NULL,
  `pcl_Link` text,
  `pcl_fechacreacionsolicitud` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `chc_actividades_externas`
--
ALTER TABLE `chc_actividades_externas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `chc_cuadrilla_doc`
--
ALTER TABLE `chc_cuadrilla_doc`
  ADD PRIMARY KEY (`idcuadrilla`),
  ADD KEY `idx_idsolicitud` (`idsolicitud`),
  ADD KEY `idx_estado` (`idestadocuadrilla`);

--
-- Indices de la tabla `chc_espacio_requerido`
--
ALTER TABLE `chc_espacio_requerido`
  ADD PRIMARY KEY (`idespacio`);

--
-- Indices de la tabla `chc_estado_agenda`
--
ALTER TABLE `chc_estado_agenda`
  ADD PRIMARY KEY (`idestadoagenda`);

--
-- Indices de la tabla `chc_estado_cuadrilla`
--
ALTER TABLE `chc_estado_cuadrilla`
  ADD PRIMARY KEY (`idestadocuadrilla`);

--
-- Indices de la tabla `chc_log_gestor`
--
ALTER TABLE `chc_log_gestor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rut_usuario` (`rut_usuario`),
  ADD KEY `fecha` (`fecha`);

--
-- Indices de la tabla `chc_modalidad`
--
ALTER TABLE `chc_modalidad`
  ADD PRIMARY KEY (`idmodalidad`);

--
-- Indices de la tabla `chc_periodosys`
--
ALTER TABLE `chc_periodosys`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `chc_solicitud`
--
ALTER TABLE `chc_solicitud`
  ADD PRIMARY KEY (`idsolicitud`),
  ADD KEY `fk_solicitud_estado_agenda` (`idestadoagenda`),
  ADD KEY `fk_solicitud_estado_cuadrilla` (`idestadocuadrilla`);

--
-- Indices de la tabla `chc_solicitud_actividad`
--
ALTER TABLE `chc_solicitud_actividad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_solicitud_actividad` (`idsolicitud`,`idplanclases`),
  ADD KEY `idx_idsolicitud` (`idsolicitud`),
  ADD KEY `idx_idplanclases` (`idplanclases`);

--
-- Indices de la tabla `chc_solicitud_espacio`
--
ALTER TABLE `chc_solicitud_espacio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_solicitud_espacio` (`idsolicitud`,`idespacio`),
  ADD KEY `fk_sol_esp_espacio` (`idespacio`);

--
-- Indices de la tabla `chc_solicitud_modalidad`
--
ALTER TABLE `chc_solicitud_modalidad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_solicitud_modalidad` (`idsolicitud`,`idmodalidad`),
  ADD KEY `fk_sol_mod_modalidad` (`idmodalidad`);

--
-- Indices de la tabla `chc_usuario`
--
ALTER TABLE `chc_usuario`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pcl_TipoSesion`
--
ALTER TABLE `pcl_TipoSesion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo_sesion` (`tipo_sesion`);

--
-- Indices de la tabla `planclases`
--
ALTER TABLE `planclases`
  ADD PRIMARY KEY (`idplanclases`),
  ADD KEY `cursos_idcursos` (`cursos_idcursos`),
  ADD KEY `id-curso-periodo` (`idplanclases`,`cursos_idcursos`,`pcl_Periodo`,`pcl_AsiCodigo`,`pcl_Presencialidad`) USING BTREE,
  ADD KEY `idx_pcl_tipo_sesion` (`pcl_TipoSesion`),
  ADD KEY `idx_pcl_dashboard` (`pcl_Periodo`,`pcl_DeseaSala`,`pcl_TipoSesion`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `chc_actividades_externas`
--
ALTER TABLE `chc_actividades_externas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_cuadrilla_doc`
--
ALTER TABLE `chc_cuadrilla_doc`
  MODIFY `idcuadrilla` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_espacio_requerido`
--
ALTER TABLE `chc_espacio_requerido`
  MODIFY `idespacio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_estado_agenda`
--
ALTER TABLE `chc_estado_agenda`
  MODIFY `idestadoagenda` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_estado_cuadrilla`
--
ALTER TABLE `chc_estado_cuadrilla`
  MODIFY `idestadocuadrilla` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_log_gestor`
--
ALTER TABLE `chc_log_gestor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_modalidad`
--
ALTER TABLE `chc_modalidad`
  MODIFY `idmodalidad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_periodosys`
--
ALTER TABLE `chc_periodosys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_solicitud`
--
ALTER TABLE `chc_solicitud`
  MODIFY `idsolicitud` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_solicitud_actividad`
--
ALTER TABLE `chc_solicitud_actividad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_solicitud_espacio`
--
ALTER TABLE `chc_solicitud_espacio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_solicitud_modalidad`
--
ALTER TABLE `chc_solicitud_modalidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chc_usuario`
--
ALTER TABLE `chc_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pcl_TipoSesion`
--
ALTER TABLE `pcl_TipoSesion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT de la tabla `planclases`
--
ALTER TABLE `planclases`
  MODIFY `idplanclases` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `chc_cuadrilla_doc`
--
ALTER TABLE `chc_cuadrilla_doc`
  ADD CONSTRAINT `fk_cuadrilla_estado` FOREIGN KEY (`idestadocuadrilla`) REFERENCES `chc_estado_cuadrilla` (`idestadocuadrilla`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cuadrilla_solicitud` FOREIGN KEY (`idsolicitud`) REFERENCES `chc_solicitud` (`idsolicitud`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `chc_solicitud`
--
ALTER TABLE `chc_solicitud`
  ADD CONSTRAINT `fk_solicitud_estado_agenda` FOREIGN KEY (`idestadoagenda`) REFERENCES `chc_estado_agenda` (`idestadoagenda`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_estado_cuadrilla` FOREIGN KEY (`idestadocuadrilla`) REFERENCES `chc_estado_cuadrilla` (`idestadocuadrilla`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `chc_solicitud_actividad`
--
ALTER TABLE `chc_solicitud_actividad`
  ADD CONSTRAINT `fk_sol_act_solicitud` FOREIGN KEY (`idsolicitud`) REFERENCES `chc_solicitud` (`idsolicitud`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `chc_solicitud_espacio`
--
ALTER TABLE `chc_solicitud_espacio`
  ADD CONSTRAINT `fk_sol_esp_espacio` FOREIGN KEY (`idespacio`) REFERENCES `chc_espacio_requerido` (`idespacio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sol_esp_solicitud` FOREIGN KEY (`idsolicitud`) REFERENCES `chc_solicitud` (`idsolicitud`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `chc_solicitud_modalidad`
--
ALTER TABLE `chc_solicitud_modalidad`
  ADD CONSTRAINT `fk_sol_mod_modalidad` FOREIGN KEY (`idmodalidad`) REFERENCES `chc_modalidad` (`idmodalidad`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sol_mod_solicitud` FOREIGN KEY (`idsolicitud`) REFERENCES `chc_solicitud` (`idsolicitud`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
