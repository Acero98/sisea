# Consulta de Patrimonio

Ruta del sistema: public/?r=patrimonio.

Consulta de solo lectura del catálogo cpaf0050.dbf de Avalon Patrimonio. Omite exclusivamente los registros marcados como eliminados físicamente en DBF; incluye los bienes dados de baja. No importa activos a MySQL ni modifica los archivos de origen.

Se consulta el estado EST_ACT contra la tabla 3 de cpaf0030.dbf, la ubicación LGE_ACT contra cpaf0031.dbf y la unidad UOR_ACT contra unidad.dbf de Planif. Si no existe una denominación, se conserva el código original. La fecha de alta corresponde a FAL_ACT. Los códigos no se convierten en números, para conservar ceros iniciales.

Filtros: unidad, ubicación, estado, fecha de alta desde/hasta. Búsqueda: código actual/anterior, descripción, serie, marca, modelo y denominaciones. Orden y paginación se aplican en servidor, con máximo 100 filas por respuesta. Con el catálogo actual de aproximadamente 6.000 registros se lee directamente en cada consulta; para un crecimiento considerable será necesario evaluar una caché e índices.

Configure avalon_patrimonio en config/local.php o AVALON_PATRIMONIO_DATA en el entorno. Predeterminado: C:/Avalon/Patrimonio/Data.

El permiso independiente permiso_patrimonio concede consulta de todo el catálogo. Se asigna inicialmente a administradores existentes; las demás cuentas requieren habilitación en Usuarios. No se hereda del permiso de Seguimiento. Las rutas de página y datos verifican el permiso en servidor. No se incluyen importes ni datos personales de responsables en esta primera vista.

La migración 017 agrega el permiso a instalaciones existentes y el esquema de nuevas instalaciones ya lo incluye. El sistema comprueba la columna durante la carga de permisos. Los traslados portátiles conservan el nuevo permiso.

Verificación: php tests/patrimonio_test.php. Comprueba lectura, filtros, fechas, búsqueda, paginación y autorización sin modificar los DBF.
