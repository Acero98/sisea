ALTER TABLE usuarios ADD COLUMN permiso_patrimonio TINYINT(1) NOT NULL DEFAULT 0;
UPDATE usuarios SET permiso_patrimonio=1 WHERE rol='ADMIN';
