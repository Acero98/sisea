-- Planif define la visibilidad normal: unidad propietaria -> actividad -> cuenta.
-- Las cuentas de otras unidades solo se reactivarán como excepciones aprobadas.
UPDATE presupuesto_responsabilidades
SET clasificacion = 'EXCLUIDA'
WHERE unidad_responsable = '4140' AND clasificacion = 'EXTERNA_ADMINISTRADA';
