CREATE TABLE IF NOT EXISTS sincronizacion_control (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  estado ENUM('LIBRE','EJECUTANDO','ERROR') NOT NULL DEFAULT 'LIBRE',
  usuario_id INT NULL,
  usuario_nombre VARCHAR(160) NULL,
  alcance VARCHAR(30) NULL,
  descripcion VARCHAR(220) NULL,
  iniciado_en DATETIME NULL,
  terminado_en DATETIME NULL,
  mensaje VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sincronizacion_control(id,estado) VALUES(1,'LIBRE');

