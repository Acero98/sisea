# Almacén: reportes y exportación

Sección Almacén independiente, con acceso a reportes y al catálogo de artículos/solicitudes existente.

## Reportes
- Altas: artículos de sum40004 cuya FECCREA corresponde al mes seleccionado. No son ingresos físicos.
- Ingresos: artículos distintos con movimientos tipo 1 en sum40008 durante el mes; sumatoria de cantidad por artículo/unidad y número de líneas, no de documentos únicos.
- Inactividad: último movimiento válido encontrado; se utiliza FECULTMOV del catálogo si es más reciente. Si no hay ninguno, se toma FECCREA, indicando claramente su origen. No implica stock positivo ni confirma ausencia de actividad en archivos no disponibles.

Clave de artículo: TIPOFAMI + GRUPO + SUBGRUPO + SEC_BIEN. No incluye servicios. Excluye registros DBF eliminados y movimientos ESTMOV=1 (anulados). El ejecutable de Logística contiene las expresiones tipo_mov=1 → Ingreso y estmov=1 → Anulado. Fechas inválidas y futuras se excluyen; códigos duplicados/fechas futuras de catálogo quedan no evaluables.

Corte: hoy, en la zona horaria de la aplicación. Se informa la última fecha de movimiento disponible para distinguir archivos antiguos de actividad actual. Tramos excluyentes: hasta 3 meses; >3 a 6; >6 a 12; >12 a 24; >24 a 36; >36 a 72; >72; sin fecha. Meses calendario con ajuste al último día del mes. Más de es estricto: exactamente tres meses no supera tres meses.

Filtro mínimo acumulativo y tramo específico; el segundo tiene precedencia visible. Indicadores globales del catálogo; tabla y exportación con búsqueda por nombre/código. DataTables con búsqueda y paginación inicial de 10 artículos (opciones 10, 25, 50 y 100). La tabla recibe todos los resultados del filtro para buscar más allá de la página visible. La exportación corresponde a los filtros del formulario. CSV UTF-8 BOM separado por punto y coma, contiene todos los resultados filtrados, fechas y criterios, escapando fórmulas de hoja de cálculo. No suma unidades heterogéneas.

## Acceso
permiso_almacen_reportes en el JSON del rol, sin nuevas columnas de usuarios. Permiso global de catálogo y movimientos, incluye exportación. Roles existentes con permiso_usuarios reciben acceso inicial si el campo no existe; al guardar un rol se respeta el checkbox explícito, incluida su desactivación. Los otros roles no reciben el permiso automáticamente. Control de acceso tanto en pantalla como en exportación. El historial de artículos mantiene los permisos de Órdenes.

## Verificación
php tests/almacen_reportes.php --avalon: fechas, límites/bisiesto, CSV, permisos y conteos reales.
php -d zend.exception_ignore_args=1 tests/roles_acceso.php: tablas temporales, herencia y último administrador.
Vista revisada en navegador con datos ficticios.
