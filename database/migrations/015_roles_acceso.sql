ALTER TABLE usuarios
  ADD COLUMN rol_acceso VARCHAR(30) NOT NULL DEFAULT 'USUARIO' AFTER perfil_portal;

UPDATE usuarios
SET rol_acceso = CASE
  WHEN perfil_portal = 'ADMINISTRADOR' THEN 'ADMINISTRADOR'
  WHEN permiso_responsabilidades = 1 AND alcance_presupuesto = 'GLOBAL' THEN 'PRESUPUESTO'
  WHEN permiso_seguimiento = 1 AND alcance_seguimiento = 'GLOBAL' THEN 'LOGISTICA'
  ELSE 'USUARIO'
END;
