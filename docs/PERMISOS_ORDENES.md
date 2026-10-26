# Permisos de Órdenes

Órdenes tiene acceso independiente de Mis notas y Seguimiento. El administrador lo configura dentro de Seguridad → Roles; las cuentas heredan el acceso del rol asignado. Puede elegir:

- **Solo este usuario:** órdenes vinculadas a pedidos cuyo código de usuario coincide con el código Avalon de la cuenta. Se normalizan los ceros iniciales. Sin código, no se muestran registros.
- **Su unidad orgánica:** órdenes vinculadas a pedidos de la unidad asignada. Sin unidad válida, no se muestran registros.
- **Todas las unidades:** consulta global de las órdenes vinculadas a pedidos en la copia local.

El alcance se determina por el pedido vinculado, no por el usuario que emitió la orden. Una orden consolidada puede tener varios pedidos; el listado solo expone las asociaciones dentro del alcance permitido. Abrir la orden muestra su documento completo, por lo que este permiso se puede desactivar por separado.

Los permisos de ver montos y abrir detalle también son independientes para Órdenes. El servidor comprueba el módulo, el alcance y el vínculo pedido–orden; cambiar los filtros o el enlace no concede acceso adicional.

## Actualización

Los campos se crean automáticamente al iniciar sesión o refrescar una sesión existente. También se puede ejecutar `php bin/actualizar_permisos_ordenes.php`. No ejecutar además la migración SQL si los campos ya existen.

Al incorporarlos, las cuentas existentes conservan el acceso que antes heredaban de Seguimiento: el alcance previo de Órdenes era global si Seguimiento era global y por unidad en los demás casos. A partir de entonces se administran por separado. Los datos FoxPro no se modifican.

Las instalaciones nuevas incluyen los campos en `database/schema.sql`. El traslado de configuraciones antiguas conserva la equivalencia de acceso al importarlas.

## Comprobación

`php tests/permisos_ordenes.php` comprueba alcances, montos, detalle, guardado y refresco de sesión con tablas temporales aisladas. No modifica cuentas ni documentos reales.
