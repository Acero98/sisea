CREATE TABLE IF NOT EXISTS seguimiento_historial (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sincronizacion_id BIGINT UNSIGNED NULL,
  usuario_id BIGINT UNSIGNED NULL,
  nro_pedido VARCHAR(20) NOT NULL,
  tipo_documento VARCHAR(30) NOT NULL,
  numero_documento VARCHAR(120) NULL,
  evento VARCHAR(30) NOT NULL,
  campo VARCHAR(60) NULL,
  valor_anterior TEXT NULL,
  valor_nuevo TEXT NULL,
  creado_en DATETIME NOT NULL,
  INDEX idx_historial_pedido_fecha (nro_pedido, creado_en),
  INDEX idx_historial_sync (sincronizacion_id),
  INDEX idx_historial_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
