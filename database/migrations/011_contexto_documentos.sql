ALTER TABLE seguimiento_documentos ADD COLUMN proveedor VARCHAR(180) NULL AFTER fecha;
ALTER TABLE seguimiento_documentos ADD COLUMN orden_tipo VARCHAR(30) NULL AFTER proveedor;
ALTER TABLE seguimiento_documentos ADD COLUMN orden_numero VARCHAR(30) NULL AFTER orden_tipo;
CREATE INDEX idx_documento_contexto ON seguimiento_documentos(tipo,numero,nro_pedido);

