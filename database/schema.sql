-- Estructura completa para instalaciones nuevas; sin datos ni nombre de base fijo.
CREATE TABLE `usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `avalon_codigo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `perfil_avalon` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `perfil_nombre` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zona` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localidad` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidad_organica` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidad_nombre` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `almacen` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supervisor_avalon` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol` enum('ADMIN','CONSULTA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CONSULTA',
  `perfil_portal` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USUARIO',
  `rol_acceso` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USUARIO',
  `alcance` enum('GLOBAL','UNIDAD','PROPIO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UNIDAD',
  `permiso_seguimiento` tinyint(1) NOT NULL DEFAULT '1',
  `alcance_seguimiento` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UNIDAD',
  `permiso_patrimonio` tinyint(1) NOT NULL DEFAULT '0',
  `permiso_presupuesto` tinyint(1) NOT NULL DEFAULT '0',
  `alcance_presupuesto` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UNIDAD',
  `permiso_responsabilidades` tinyint(1) NOT NULL DEFAULT '0',
  `permiso_usuarios` tinyint(1) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `sincronizado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sesion_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sesion_iniciada_en` datetime DEFAULT NULL,
  `permiso_ver_montos` tinyint(1) NOT NULL DEFAULT '1',
  `permiso_ver_detalle` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `avalon_codigo` (`avalon_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned NOT NULL,
  `termino` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_consultas_usuario_fecha` (`usuario_id`,`creado_en`),
  CONSTRAINT `fk_consultas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `presupuesto_responsabilidades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `unidad_responsable` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actividad` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidad_propietaria` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuenta` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clasificacion` enum('PROPIA_ADMINISTRADA','EXTERNA_ADMINISTRADA','PROPIA_NO_ADMINISTRADA','EXCLUIDA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_responsabilidad` (`unidad_responsable`,`actividad`,`unidad_propietaria`,`cuenta`),
  KEY `idx_responsable` (`unidad_responsable`,`clasificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `seguimiento_notas` (
  `nro_pedido` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidad_organica` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidad_nombre` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_codigo` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_nombre` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_logistica` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_registro` date DEFAULT NULL,
  `tipo_pedido` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prioridad` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sustentacion` text COLLATE utf8mb4_unicode_ci,
  `etapa` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `etapa_nivel` tinyint unsigned NOT NULL DEFAULT '1',
  `sincronizado_en` datetime NOT NULL,
  PRIMARY KEY (`nro_pedido`),
  KEY `idx_seg_unidad_fecha` (`unidad_organica`,`fecha_registro`),
  KEY `idx_seg_usuario_fecha` (`usuario_codigo`,`fecha_registro`),
  KEY `idx_seg_etapa` (`etapa_nivel`),
  KEY `idx_seg_estado` (`estado_logistica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `seguimiento_documentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nro_pedido` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `proveedor` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden_tipo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden_numero` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sincronizado_en` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documento_nota` (`nro_pedido`,`tipo`,`numero`),
  KEY `idx_documento_busqueda` (`tipo`,`numero`),
  KEY `idx_documento_numero` (`numero`),
  KEY `idx_documento_pedido` (`nro_pedido`),
  KEY `idx_documento_contexto` (`tipo`,`numero`,`nro_pedido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `seguimiento_historial` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sincronizacion_id` bigint unsigned DEFAULT NULL,
  `usuario_id` bigint unsigned DEFAULT NULL,
  `nro_pedido` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_documento` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_documento` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evento` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `campo` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_anterior` text COLLATE utf8mb4_unicode_ci,
  `valor_nuevo` text COLLATE utf8mb4_unicode_ci,
  `creado_en` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_historial_pedido_fecha` (`nro_pedido`,`creado_en`),
  KEY `idx_historial_sync` (`sincronizacion_id`),
  KEY `idx_historial_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `seguimiento_sincronizaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `iniciado_en` datetime NOT NULL,
  `terminado_en` datetime DEFAULT NULL,
  `notas_procesadas` int unsigned NOT NULL DEFAULT '0',
  `estado` enum('EJECUTANDO','COMPLETADO','ERROR') COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sync_fecha` (`iniciado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sincronizacion_control` (
  `id` tinyint unsigned NOT NULL,
  `estado` enum('LIBRE','EJECUTANDO','ERROR') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LIBRE',
  `usuario_id` int DEFAULT NULL,
  `usuario_nombre` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alcance` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(220) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iniciado_en` datetime DEFAULT NULL,
  `terminado_en` datetime DEFAULT NULL,
  `mensaje` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `avalon_cache_filas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `archivo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_fila` int unsigned NOT NULL,
  `contenido` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `huella` char(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actualizado_en` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cache_fila` (`archivo`,`numero_fila`),
  KEY `idx_cache_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `avalon_cache_indices` (
  `fila_id` bigint unsigned NOT NULL,
  `archivo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `campo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`fila_id`,`campo`),
  KEY `idx_cache_busqueda` (`archivo`,`campo`,`valor`),
  CONSTRAINT `fk_cache_fila` FOREIGN KEY (`fila_id`) REFERENCES `avalon_cache_filas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `avalon_cache_fuentes` (
  `archivo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamano` bigint unsigned NOT NULL,
  `modificado_en` bigint unsigned NOT NULL,
  `estructura` char(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sincronizado_en` datetime NOT NULL,
  PRIMARY KEY (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO sincronizacion_control (id, estado) VALUES (1, 'LIBRE');
