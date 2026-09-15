CREATE TABLE IF NOT EXISTS seguimiento_notas (
  nro_pedido VARCHAR(20) NOT NULL PRIMARY KEY,
  unidad_organica VARCHAR(10) NULL,
  unidad_nombre VARCHAR(180) NULL,
  usuario_codigo VARCHAR(12) NULL,
  usuario_nombre VARCHAR(160) NULL,
  estado_logistica CHAR(1) NULL,
  fecha_emision DATE NULL,
  fecha_registro DATE NULL,
  tipo_pedido CHAR(1) NULL,
  prioridad CHAR(1) NULL,
  etapa VARCHAR(60) NOT NULL,
  etapa_nivel TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sincronizado_en DATETIME NOT NULL,
  INDEX idx_seg_unidad_fecha (unidad_organica, fecha_registro),
  INDEX idx_seg_usuario_fecha (usuario_codigo, fecha_registro),
  INDEX idx_seg_etapa (etapa_nivel),
  INDEX idx_seg_estado (estado_logistica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seguimiento_sincronizaciones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  iniciado_en DATETIME NOT NULL,
  terminado_en DATETIME NULL,
  notas_procesadas INT UNSIGNED NOT NULL DEFAULT 0,
  estado ENUM('EJECUTANDO','COMPLETADO','ERROR') NOT NULL,
  mensaje VARCHAR(500) NULL,
  INDEX idx_sync_fecha (iniciado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
