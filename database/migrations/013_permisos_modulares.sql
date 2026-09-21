ALTER TABLE usuarios
  ADD COLUMN perfil_portal VARCHAR(30) NOT NULL DEFAULT 'USUARIO' AFTER rol,
  ADD COLUMN permiso_seguimiento TINYINT(1) NOT NULL DEFAULT 1 AFTER alcance,
  ADD COLUMN alcance_seguimiento VARCHAR(15) NOT NULL DEFAULT 'UNIDAD' AFTER permiso_seguimiento,
  ADD COLUMN permiso_presupuesto TINYINT(1) NOT NULL DEFAULT 0 AFTER alcance_seguimiento,
  ADD COLUMN alcance_presupuesto VARCHAR(15) NOT NULL DEFAULT 'UNIDAD' AFTER permiso_presupuesto,
  ADD COLUMN permiso_responsabilidades TINYINT(1) NOT NULL DEFAULT 0 AFTER alcance_presupuesto,
  ADD COLUMN permiso_usuarios TINYINT(1) NOT NULL DEFAULT 0 AFTER permiso_responsabilidades;

UPDATE usuarios SET
 perfil_portal=IF(rol='ADMIN','ADMINISTRADOR','USUARIO'),
 permiso_seguimiento=1,
 alcance_seguimiento=alcance,
 permiso_presupuesto=IF(rol='ADMIN',1,0),
 alcance_presupuesto=IF(rol='ADMIN','GLOBAL','UNIDAD'),
 permiso_responsabilidades=IF(rol='ADMIN',1,0),
 permiso_usuarios=IF(rol='ADMIN',1,0);
