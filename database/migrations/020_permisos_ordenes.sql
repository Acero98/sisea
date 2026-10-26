-- Separar Órdenes conservando el acceso efectivo anterior.
ALTER TABLE usuarios
 ADD COLUMN permiso_ordenes TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN alcance_ordenes VARCHAR(15) NOT NULL DEFAULT 'UNIDAD',
 ADD COLUMN permiso_ordenes_montos TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN permiso_ordenes_detalle TINYINT(1) NOT NULL DEFAULT 0;
UPDATE usuarios SET permiso_ordenes=permiso_seguimiento,
 alcance_ordenes=IF(alcance_seguimiento='GLOBAL','GLOBAL','UNIDAD'),
 permiso_ordenes_montos=IF(permiso_seguimiento=1,permiso_ver_montos,0),
 permiso_ordenes_detalle=IF(permiso_seguimiento=1,permiso_ver_detalle,0);
