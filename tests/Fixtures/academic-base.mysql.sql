-- Schema only, from the normalized multicareer backup. No production rows.
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `alertas_operativas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `huella` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severidad` enum('informativa','advertencia','critica') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('abierta','atendida','resuelta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierta',
  `titulo` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `datos` json DEFAULT NULL,
  `atendida_por` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detectada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atendida_en` datetime DEFAULT NULL,
  `resuelta_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alerta_huella` (`huella`),
  KEY `idx_alerta_estado_severidad` (`estado`,`severidad`,`actualizada_en`),
  KEY `fk_alerta_atendida_actor` (`atendida_por`),
  CONSTRAINT `fk_alerta_atendida_actor` FOREIGN KEY (`atendida_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `asignaturas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `clave` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asignaturas_carrera_clave` (`carrera_id`,`clave`),
  KEY `idx_asignaturas_carrera_activo` (`carrera_id`,`activo`),
  CONSTRAINT `fk_asignaturas_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auditoria_carreras` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned DEFAULT NULL,
  `actor_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_http` smallint unsigned NOT NULL,
  `direccion_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agente_usuario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detalle` json DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_carrera_fecha` (`carrera_id`,`creado_en`),
  KEY `idx_auditoria_actor_fecha` (`actor_id`,`creado_en`),
  KEY `idx_auditoria_estado_fecha` (`estado_http`,`creado_en`),
  CONSTRAINT `fk_auditoria_actor` FOREIGN KEY (`actor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_auditoria_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `autorizaciones_excepcionales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proyecto_id` bigint unsigned NOT NULL,
  `usuario_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('revision_propuesta','presentacion_semestre','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vigente_hasta` datetime DEFAULT NULL,
  `autorizada_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_autorizaciones_proyecto_tipo` (`proyecto_id`,`tipo`,`activa`),
  KEY `fk_autorizaciones_usuario` (`usuario_id`),
  KEY `fk_autorizaciones_autor` (`autorizada_por`),
  CONSTRAINT `fk_autorizaciones_autor` FOREIGN KEY (`autorizada_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_autorizaciones_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_autorizaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bitacora_usuarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `administrador_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accion` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle` json DEFAULT NULL,
  `motivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accion_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bitacora_usuario_fecha` (`usuario_id`,`accion_en`),
  KEY `fk_bitacora_administrador` (`administrador_id`),
  CONSTRAINT `fk_bitacora_administrador` FOREIGN KEY (`administrador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carrera_modulos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `modulo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `habilitado` tinyint(1) NOT NULL DEFAULT '1',
  `configuracion` json DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_carrera_modulo` (`carrera_id`,`modulo`),
  CONSTRAINT `fk_carrera_modulos_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carreras` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_corto` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_primario` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1B396A',
  `color_secundario` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#2D5A96',
  `color_acento` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lema` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_ruta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portada_ruta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizada_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_carreras_clave` (`clave`),
  UNIQUE KEY `uq_carreras_slug` (`slug`),
  KEY `idx_carreras_activas` (`activa`,`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `configuraciones_carrera` (
  `carrera_id` smallint unsigned NOT NULL,
  `clave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` json DEFAULT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `creada_en` datetime DEFAULT NULL,
  `actualizada_en` datetime DEFAULT NULL,
  PRIMARY KEY (`carrera_id`,`clave`),
  CONSTRAINT `fk_configuraciones_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `configuraciones_sistema` (
  `clave` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` json DEFAULT NULL,
  `tipo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `creada_en` datetime DEFAULT NULL,
  `actualizada_en` datetime DEFAULT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `criterios_rubrica` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rubrica_id` bigint unsigned NOT NULL,
  `proyecto_id` bigint unsigned DEFAULT NULL,
  `proyecto_scope` bigint unsigned GENERATED ALWAYS AS (ifnull(`proyecto_id`,0)) STORED,
  `clave` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pregunta` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `puntaje_maximo` decimal(5,2) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_criterio_contexto_clave` (`rubrica_id`,`proyecto_scope`,`clave`),
  KEY `idx_criterios_proyecto` (`proyecto_id`),
  CONSTRAINT `chk_criterios_puntaje_maximo` CHECK (((`puntaje_maximo` is null) or (`puntaje_maximo` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `curso_docentes` (
  `curso_id` bigint unsigned NOT NULL,
  `docente_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `labor` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'docente',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `asignado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`curso_id`,`docente_id`),
  KEY `idx_curso_docentes_docente` (`docente_id`,`activo`),
  CONSTRAINT `fk_curso_docentes_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_curso_docentes_usuario` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cursos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `grupo_id` bigint unsigned NOT NULL,
  `asignatura_id` bigint unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_curso_grupo_asignatura` (`grupo_id`,`asignatura_id`),
  KEY `fk_cursos_asignatura` (`asignatura_id`),
  KEY `idx_cursos_carrera_activo` (`carrera_id`,`activo`),
  CONSTRAINT `fk_cursos_asignatura` FOREIGN KEY (`asignatura_id`) REFERENCES `asignaturas` (`id`),
  CONSTRAINT `fk_cursos_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`),
  CONSTRAINT `fk_cursos_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_academicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dictamenes_docentes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `evaluacion_id` bigint unsigned NOT NULL,
  `docente_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `intentos_realizados` tinyint unsigned NOT NULL DEFAULT '0',
  `comentario_general` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `enviado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dictamen_evaluacion_docente` (`evaluacion_id`,`docente_id`),
  KEY `fk_dictamenes_docente` (`docente_id`),
  CONSTRAINT `fk_dictamenes_docente` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dictamenes_evaluacion` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documento_etiquetas` (
  `documento_id` bigint unsigned NOT NULL,
  `etiqueta_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`documento_id`,`etiqueta_id`),
  KEY `fk_documento_etiquetas_etiqueta` (`etiqueta_id`),
  CONSTRAINT `fk_documento_etiquetas_documento` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documento_etiquetas_etiqueta` FOREIGN KEY (`etiqueta_id`) REFERENCES `etiquetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documento_versiones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `documento_id` bigint unsigned NOT NULL,
  `numero_version` int unsigned NOT NULL,
  `nombre_archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_archivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `extension` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamano_bytes` bigint unsigned DEFAULT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `subido_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documento_version` (`documento_id`,`numero_version`),
  KEY `fk_versiones_usuario` (`subido_por`),
  CONSTRAINT `fk_versiones_documento` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_versiones_usuario` FOREIGN KEY (`subido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned DEFAULT NULL,
  `proyecto_id` bigint unsigned DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `categoria` enum('repositorio','propuesta','entregable','evaluacion','tesis','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'repositorio',
  `visibilidad` enum('privado','publico') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'privado',
  `estado` enum('borrador','publicado','archivado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `autor_usuario_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autor_nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subido_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publicado_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publicado_en` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_documentos_proyecto_categoria` (`proyecto_id`,`categoria`,`activo`),
  KEY `idx_documentos_publicacion` (`visibilidad`,`estado`,`publicado_en`),
  KEY `fk_documentos_autor_usuario` (`autor_usuario_id`),
  KEY `fk_documentos_subido_por` (`subido_por`),
  KEY `fk_documentos_publicado_por` (`publicado_por`),
  KEY `idx_documentos_carrera_visibilidad_estado` (`carrera_id`,`visibilidad`,`estado`),
  CONSTRAINT `fk_documentos_autor_usuario` FOREIGN KEY (`autor_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documentos_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`),
  CONSTRAINT `fk_documentos_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documentos_publicado_por` FOREIGN KEY (`publicado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documentos_subido_por` FOREIGN KEY (`subido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ejecuciones_integridad` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ejecutado_por` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen` enum('manual','programado','consola') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `saludable` tinyint(1) NOT NULL,
  `incidencias` int unsigned NOT NULL DEFAULT '0',
  `verificaciones_correctas` smallint unsigned NOT NULL DEFAULT '0',
  `verificaciones_totales` smallint unsigned NOT NULL DEFAULT '0',
  `reporte` json NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_integridad_fecha` (`creado_en`),
  KEY `idx_integridad_saludable_fecha` (`saludable`,`creado_en`),
  KEY `fk_integridad_actor` (`ejecutado_por`),
  CONSTRAINT `fk_integridad_actor` FOREIGN KEY (`ejecutado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `empresas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `giro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto_nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto_cargo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_empresas_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entregables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `curso_id` bigint unsigned NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipo_documento` enum('reporte','video','presentacion','codigo','documento','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'documento',
  `fecha_limite` datetime DEFAULT NULL,
  `estado` enum('borrador','publicado','cerrado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entregables_curso_estado` (`curso_id`,`estado`,`activo`),
  KEY `idx_entregables_carrera_estado` (`carrera_id`,`estado`,`activo`),
  CONSTRAINT `fk_entregables_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`),
  CONSTRAINT `fk_entregables_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entregas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entregable_id` bigint unsigned NOT NULL,
  `proyecto_id` bigint unsigned NOT NULL,
  `documento_id` bigint unsigned NOT NULL,
  `enviado_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entregado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `calificacion` decimal(5,2) DEFAULT NULL,
  `comentarios_docente` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entrega_proyecto` (`entregable_id`,`proyecto_id`),
  KEY `fk_entregas_proyecto` (`proyecto_id`),
  KEY `fk_entregas_documento` (`documento_id`),
  KEY `fk_entregas_usuario` (`enviado_por`),
  CONSTRAINT `fk_entregas_documento` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`),
  CONSTRAINT `fk_entregas_entregable` FOREIGN KEY (`entregable_id`) REFERENCES `entregables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_entregas_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_entregas_usuario` FOREIGN KEY (`enviado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `etiquetas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned DEFAULT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1B396A',
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etiquetas_carrera_nombre` (`carrera_id`,`nombre`),
  KEY `idx_etiquetas_carrera_activa` (`carrera_id`,`activa`),
  CONSTRAINT `fk_etiquetas_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evaluaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `sala_id` bigint unsigned NOT NULL,
  `proyecto_id` bigint unsigned NOT NULL,
  `orden_presentacion` smallint unsigned NOT NULL,
  `fecha_exposicion` datetime DEFAULT NULL,
  `estado` enum('programada','en_evaluacion','finalizada','archivada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'programada',
  `resultado` enum('pendiente','viable','no_viable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `apto_titulacion` tinyint(1) DEFAULT NULL,
  `retroalimentacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `retroalimentado_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retroalimentado_en` datetime DEFAULT NULL,
  `finalizada_en` datetime DEFAULT NULL,
  `archivada_en` datetime DEFAULT NULL,
  `archivada_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creada_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizada_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evaluacion_sala_proyecto` (`sala_id`,`proyecto_id`),
  UNIQUE KEY `uq_evaluacion_sala_orden` (`sala_id`,`orden_presentacion`),
  KEY `idx_evaluaciones_proyecto` (`proyecto_id`,`estado`),
  KEY `fk_evaluaciones_retroalimentador` (`retroalimentado_por`),
  KEY `fk_evaluaciones_archivador` (`archivada_por`),
  KEY `fk_evaluaciones_creador` (`creada_por`),
  KEY `idx_evaluaciones_carrera_estado` (`carrera_id`,`estado`),
  CONSTRAINT `fk_evaluaciones_archivador` FOREIGN KEY (`archivada_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evaluaciones_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`),
  CONSTRAINT `fk_evaluaciones_creador` FOREIGN KEY (`creada_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evaluaciones_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluaciones_retroalimentador` FOREIGN KEY (`retroalimentado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evaluaciones_sala` FOREIGN KEY (`sala_id`) REFERENCES `salas_evaluacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `grupo_estudiantes` (
  `grupo_id` bigint unsigned NOT NULL,
  `estudiante_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `inscrito_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`grupo_id`,`estudiante_id`),
  KEY `idx_grupo_estudiantes_usuario` (`estudiante_id`,`activo`),
  CONSTRAINT `fk_grupo_estudiantes_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_academicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grupo_estudiantes_usuario` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `grupos_academicos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `periodo_id` bigint unsigned NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `semestre` tinyint unsigned DEFAULT NULL,
  `clave_grupo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_proyectos_desde` datetime DEFAULT NULL,
  `registro_proyectos_hasta` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grupo_carrera_periodo_semestre_clave` (`carrera_id`,`periodo_id`,`semestre`,`clave_grupo`),
  KEY `idx_grupos_periodo_activo` (`periodo_id`,`activo`),
  KEY `idx_grupos_carrera_periodo_activo` (`carrera_id`,`periodo_id`,`activo`),
  CONSTRAINT `fk_grupos_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`),
  CONSTRAINT `fk_grupos_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos_academicos` (`id`),
  CONSTRAINT `chk_grupos_ventana` CHECK (((`registro_proyectos_hasta` is null) or (`registro_proyectos_desde` is null) or (`registro_proyectos_hasta` >= `registro_proyectos_desde`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `indicadores_carrera` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `modulo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'indicadores',
  `clave` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `unidad` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'porcentaje',
  `valor_actual` decimal(12,2) DEFAULT NULL,
  `valor_meta` decimal(12,2) DEFAULT NULL,
  `color` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icono` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_indicador_carrera_clave` (`carrera_id`,`clave`),
  KEY `idx_indicador_carrera_modulo` (`carrera_id`,`modulo`,`activo`),
  CONSTRAINT `fk_indicador_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mediciones_continuidad` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `medido_por` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen` enum('manual','programado','consola') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `indice_preparacion` tinyint unsigned NOT NULL,
  `controles_correctos` tinyint unsigned NOT NULL,
  `controles_totales` tinyint unsigned NOT NULL,
  `incidencias_integridad` int unsigned NOT NULL DEFAULT '0',
  `respaldos_disponibles` int unsigned NOT NULL DEFAULT '0',
  `respaldos_verificados` int unsigned NOT NULL DEFAULT '0',
  `alertas_activas` int unsigned NOT NULL DEFAULT '0',
  `alertas_criticas` int unsigned NOT NULL DEFAULT '0',
  `instantanea` json NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_medicion_fecha` (`creado_en`),
  KEY `idx_medicion_indice_fecha` (`indice_preparacion`,`creado_en`),
  KEY `fk_medicion_actor` (`medido_por`),
  CONSTRAINT `fk_medicion_actor` FOREIGN KEY (`medido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notificaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned DEFAULT NULL,
  `usuario_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leida_en` datetime DEFAULT NULL,
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notificaciones_usuario` (`usuario_id`,`leida_en`,`creada_en`),
  KEY `fk_notificaciones_actor` (`actor_id`),
  KEY `idx_notificaciones_carrera_usuario` (`carrera_id`,`usuario_id`,`leida_en`),
  CONSTRAINT `fk_notificaciones_actor` FOREIGN KEY (`actor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notificaciones_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `perfiles` (
  `id` tinyint unsigned NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perfiles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `periodos_academicos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_periodos_nombre` (`nombre`),
  CONSTRAINT `chk_periodos_fechas` CHECK ((`fecha_fin` >= `fecha_inicio`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `politica_continuidad` (
  `id` tinyint unsigned NOT NULL,
  `objetivo_preparacion` tinyint unsigned NOT NULL DEFAULT '100',
  `umbral_critico` tinyint unsigned NOT NULL DEFAULT '75',
  `antiguedad_maxima_respaldo_horas` smallint unsigned NOT NULL DEFAULT '26',
  `retencion_respaldos_dias` smallint unsigned NOT NULL DEFAULT '30',
  `actualizado_por` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_politica_continuidad_actor` (`actualizado_por`),
  CONSTRAINT `fk_politica_continuidad_actor` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proyecto_integrantes` (
  `proyecto_id` bigint unsigned NOT NULL,
  `usuario_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('lider','integrante','asesor','primario','secundario','revisor_1','revisor_2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'integrante',
  `agregado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`proyecto_id`,`usuario_id`),
  KEY `idx_proyecto_integrantes_usuario` (`usuario_id`),
  CONSTRAINT `fk_proyecto_integrantes_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_proyecto_integrantes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proyectos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `grupo_id` bigint unsigned NOT NULL,
  `empresa_id` bigint unsigned DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipo` enum('propuesta','desarrollo','tesis','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'propuesta',
  `estado` enum('borrador','pendiente','en_revision','requiere_cambios','aprobado','rechazado','finalizado','archivado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `creado_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revisado_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comentario_revision` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `revisado_en` datetime DEFAULT NULL,
  `revision_permitida_hasta` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_proyectos_grupo_estado` (`grupo_id`,`estado`,`activo`),
  KEY `fk_proyectos_empresa` (`empresa_id`),
  KEY `fk_proyectos_creador` (`creado_por`),
  KEY `fk_proyectos_revisor` (`revisado_por`),
  KEY `idx_proyectos_carrera_estado_activo` (`carrera_id`,`estado`,`activo`),
  CONSTRAINT `fk_proyectos_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`),
  CONSTRAINT `fk_proyectos_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_proyectos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_proyectos_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_academicos` (`id`),
  CONSTRAINT `fk_proyectos_revisor` FOREIGN KEY (`revisado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `registros_modulo_carrera` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `modulo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clave` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `estado` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `responsable_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `datos` json DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registro_modulo_clave` (`carrera_id`,`modulo`,`clave`),
  KEY `idx_registro_modulo_estado` (`carrera_id`,`modulo`,`estado`,`activo`),
  KEY `idx_registro_modulo_responsable` (`responsable_id`),
  CONSTRAINT `fk_registro_modulo_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_registro_modulo_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `respaldos_base_datos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creado_por` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen` enum('manual','programado','consola') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `estado` enum('completado','fallido') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_archivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta_privada` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamano_bytes` bigint unsigned DEFAULT NULL,
  `checksum_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mensaje_error` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_respaldos_estado_fecha` (`estado`,`creado_en`),
  KEY `fk_respaldos_actor` (`creado_por`),
  KEY `idx_respaldos_eliminado_fecha` (`eliminado_en`,`creado_en`),
  KEY `fk_respaldos_eliminado_actor` (`eliminado_por`),
  CONSTRAINT `fk_respaldos_actor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_respaldos_eliminado_actor` FOREIGN KEY (`eliminado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `respuestas_evaluacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dictamen_id` bigint unsigned NOT NULL,
  `criterio_id` bigint unsigned NOT NULL,
  `valor` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `puntaje` decimal(5,2) DEFAULT NULL,
  `comentario` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_respuesta_dictamen_criterio` (`dictamen_id`,`criterio_id`),
  KEY `idx_respuestas_criterio` (`criterio_id`),
  CONSTRAINT `fk_respuestas_criterio` FOREIGN KEY (`criterio_id`) REFERENCES `criterios_rubrica` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_respuestas_dictamen` FOREIGN KEY (`dictamen_id`) REFERENCES `dictamenes_docentes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_respuestas_contenido` CHECK (((`valor` is not null) or (`puntaje` is not null) or (`comentario` is not null))),
  CONSTRAINT `chk_respuestas_puntaje` CHECK (((`puntaje` is null) or (`puntaje` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rubricas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `etapa` enum('propuesta','avance','titulacion') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `semestre` tinyint unsigned DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rubrica_carrera_etapa_semestre` (`carrera_id`,`etapa`,`semestre`),
  KEY `idx_rubricas_carrera_etapa_activa` (`carrera_id`,`etapa`,`activa`),
  CONSTRAINT `fk_rubricas_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sala_docentes` (
  `sala_id` bigint unsigned NOT NULL,
  `docente_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('evaluador','presidente','secretario','vocal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'evaluador',
  PRIMARY KEY (`sala_id`,`docente_id`),
  KEY `idx_sala_docentes_docente` (`docente_id`),
  CONSTRAINT `fk_sala_docentes_sala` FOREIGN KEY (`sala_id`) REFERENCES `salas_evaluacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sala_docentes_usuario` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `salas_evaluacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` smallint unsigned NOT NULL,
  `rubrica_id` bigint unsigned NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `salon` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inicia_en` datetime DEFAULT NULL,
  `termina_en` datetime DEFAULT NULL,
  `minutos_evaluacion_docente` smallint unsigned NOT NULL DEFAULT '15',
  `minutos_presentacion_proyecto` smallint unsigned NOT NULL DEFAULT '20',
  `max_intentos` tinyint unsigned NOT NULL DEFAULT '1',
  `estado` enum('programada','en_curso','finalizada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'programada',
  `creado_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_salas_rubrica_estado` (`rubrica_id`,`estado`),
  KEY `fk_salas_creador` (`creado_por`),
  KEY `idx_salas_carrera_estado` (`carrera_id`,`estado`),
  CONSTRAINT `fk_salas_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`),
  CONSTRAINT `fk_salas_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_salas_rubrica` FOREIGN KEY (`rubrica_id`) REFERENCES `rubricas` (`id`),
  CONSTRAINT `chk_salas_fechas` CHECK (((`termina_en` is null) or (`inicia_en` is null) or (`termina_en` >= `inicia_en`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_carrera` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrera_id` smallint unsigned NOT NULL,
  `perfil_id` tinyint unsigned NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `asignado_por` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_carrera` (`usuario_id`,`carrera_id`),
  KEY `idx_usuario_carrera_contexto` (`usuario_id`,`activo`,`es_principal`),
  KEY `idx_carrera_perfil_activo` (`carrera_id`,`perfil_id`,`activo`),
  KEY `fk_usuario_carrera_perfil` (`perfil_id`),
  KEY `fk_usuario_carrera_asignador` (`asignado_por`),
  CONSTRAINT `fk_usuario_carrera_asignador` FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_usuario_carrera_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`),
  CONSTRAINT `fk_usuario_carrera_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfiles` (`id`),
  CONSTRAINT `fk_usuario_carrera_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `perfil_id` tinyint unsigned NOT NULL,
  `contrasena` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apellido_materno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `curp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `foto_ruta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `especialidad` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `perfil_completado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_correo` (`correo`),
  KEY `idx_usuarios_perfil_activo` (`perfil_id`,`activo`),
  CONSTRAINT `fk_usuarios_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfiles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `verificaciones_restauracion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `respaldo_id` bigint unsigned NOT NULL,
  `verificado_por` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('correcto','fallido') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tablas_encontradas` int unsigned NOT NULL DEFAULT '0',
  `filas_verificadas` bigint unsigned NOT NULL DEFAULT '0',
  `mensaje_error` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_verificacion_respaldo_fecha` (`respaldo_id`,`creado_en`),
  KEY `fk_verificacion_actor` (`verificado_por`),
  CONSTRAINT `fk_verificacion_actor` FOREIGN KEY (`verificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_verificacion_respaldo` FOREIGN KEY (`respaldo_id`) REFERENCES `respaldos_base_datos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
