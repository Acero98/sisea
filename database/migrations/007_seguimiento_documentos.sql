CREATE TABLE IF NOT EXISTS seguimiento_documentos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nro_pedido VARCHAR(20) NOT NULL,
  tipo VARCHAR(30) NOT NULL,
  numero VARCHAR(120) NOT NULL,
  estado VARCHAR(20) NULL,
  fecha DATE NULL,
  sincronizado_en DATETIME NOT NULL,
  UNIQUE KEY uq_documento_nota (nro_pedido, tipo, numero),
  INDEX idx_documento_busqueda (tipo, numero),
  INDEX idx_documento_numero (numero),
  INDEX idx_documento_pedido (nro_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
