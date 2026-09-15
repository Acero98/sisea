ALTER TABLE usuarios
  MODIFY clave_hash VARCHAR(255) NULL,
  ADD COLUMN avalon_codigo VARCHAR(10) NULL UNIQUE AFTER id,
  ADD COLUMN perfil_avalon VARCHAR(10) NULL AFTER nombre,
  ADD COLUMN perfil_nombre VARCHAR(120) NULL AFTER perfil_avalon,
  ADD COLUMN zona VARCHAR(10) NULL AFTER perfil_nombre,
  ADD COLUMN localidad VARCHAR(10) NULL AFTER zona,
  ADD COLUMN unidad_organica VARCHAR(10) NULL AFTER localidad,
  ADD COLUMN unidad_nombre VARCHAR(180) NULL AFTER unidad_organica,
  ADD COLUMN almacen VARCHAR(10) NULL AFTER unidad_nombre,
  ADD COLUMN supervisor_avalon VARCHAR(10) NULL AFTER almacen,
  ADD COLUMN alcance ENUM('GLOBAL','UNIDAD') NOT NULL DEFAULT 'UNIDAD' AFTER rol,
  ADD COLUMN sincronizado_en DATETIME NULL AFTER activo;
UPDATE usuarios SET alcance='GLOBAL' WHERE rol='ADMIN';
