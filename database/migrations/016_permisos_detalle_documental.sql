ALTER TABLE usuarios
  ADD COLUMN permiso_ver_montos TINYINT(1) NOT NULL DEFAULT 1 AFTER permiso_seguimiento,
  ADD COLUMN permiso_ver_detalle TINYINT(1) NOT NULL DEFAULT 1 AFTER permiso_ver_montos;
