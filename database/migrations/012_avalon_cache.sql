CREATE TABLE IF NOT EXISTS avalon_cache_filas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  archivo VARCHAR(80) NOT NULL,
  numero_fila INT UNSIGNED NOT NULL,
  contenido LONGTEXT NOT NULL,
  huella CHAR(40) NOT NULL,
  actualizado_en DATETIME NOT NULL,
  UNIQUE KEY uq_cache_fila (archivo,numero_fila),
  INDEX idx_cache_archivo (archivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avalon_cache_indices (
  fila_id BIGINT UNSIGNED NOT NULL,
  archivo VARCHAR(80) NOT NULL,
  campo VARCHAR(30) NOT NULL,
  valor VARCHAR(255) NOT NULL,
  PRIMARY KEY (fila_id,campo),
  INDEX idx_cache_busqueda (archivo,campo,valor),
  CONSTRAINT fk_cache_fila FOREIGN KEY (fila_id) REFERENCES avalon_cache_filas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avalon_cache_fuentes (
  archivo VARCHAR(80) NOT NULL PRIMARY KEY,
  ruta VARCHAR(255) NOT NULL,
  tamano BIGINT UNSIGNED NOT NULL,
  modificado_en BIGINT UNSIGNED NOT NULL,
  estructura CHAR(40) NULL,
  sincronizado_en DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
