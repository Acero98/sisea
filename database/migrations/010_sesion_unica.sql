ALTER TABLE usuarios ADD COLUMN sesion_token VARCHAR(64) NULL;
ALTER TABLE usuarios ADD COLUMN sesion_iniciada_en DATETIME NULL;

