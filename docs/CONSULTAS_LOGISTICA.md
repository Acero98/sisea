# Consultas por cuenta, artículo y proveedor

Rutas: `?r=cuentas`, `?r=almacen`, `?r=proveedores`, dentro de Logística.

Se exige permiso de Órdenes y se aplica su alcance PROPIO, UNIDAD o GLOBAL. Los permisos ordenes_montos y ordenes_detalle controlan importes y enlaces. Los catálogos se pueden consultar; el historial se limita a pedidos autorizados. Los registros sin identidad o unidad válida no amplían el acceso.

Fuentes leídas directamente, sin escribir en Avalon:
- sum40003/04: catálogo de servicios/bienes, CTACTBLE como cuenta configurada, no como autorización presupuestal.
- tab_recu: nombres de cuentas. Se distingue historial CTA_CON del catálogo CTACTBLE; una coincidencia no determina autorización de uso.
- sum40013: nombre y código de proveedor. La identificación de la orden usa primero ese catálogo. Si no hay nombre, solo se utiliza un comprobante cuyo RUC coincide exactamente con el código y tiene un nombre inequívoco.
- sum40014/07: solicitante, unidad, fecha y detalle de pedido; CAN_SALI/CAN_INGRE son acumulados del registro, no stock disponible ni trazabilidad de lotes.
- sum40015/17 y sum40016/18: cabeceras y detalles de órdenes, proveedor y valor PRE_TOT del detalle.

Vinculación de compras por zona/local/pedido + línea SECPEDIDO + clave completa del recurso. El período de una orden puede diferir del pedido; se admite la referencia únicamente si identifica una sola cabecera. La cabecera de la orden sí se une por su período, zona, local y número. No se asigna un proveedor por coincidencia de descripción ni por saldo de almacén.

Paginación de 25 detalles y filtro por año del pedido. La búsqueda de catálogo acepta nombre/código. Los historiales pueden tardar varios segundos por lectura directa del archivo de 500 mil detalles. Para artículos/cuentas se conservan compras solo de la página solicitada. Los servicios no muestran acumulados físicos de almacén.

Límites visibles: directorio de proveedores con compras vinculadas a detalles autorizados; órdenes sin enlace inequívoco a pedido no se incluyen. No se presentan invitaciones a cotizar como compras adjudicadas. No se deduce entrega desde existencias por ausencia de orden. Facturas y pagos continúan disponibles en la consulta documental existente.

Verificación: `php tests/consultas_logistica.php`; con fuentes locales `php tests/consultas_logistica.php --avalon`. Se validó proveedor del ejemplo, pedido y compra entre meses distintos, filtros por artículo/cuenta y ausencia de importe/enlace sin permisos. Vista revisada en navegador con datos ficticios.
