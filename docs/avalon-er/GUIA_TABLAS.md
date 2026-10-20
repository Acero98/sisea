# Catálogo funcional de Avalon

## Glosa de un pedido

La glosa/sustentación general que muestra SISEA se lee de `C:\Avalon\Logistica\data\sum40014.DBF`, campo `COMENTARIO`, vinculado al contenedor `dbsumi.DBC`. El pedido se busca por `NRO_PEDIDO` (carácter de 7 posiciones). `COMENTARIO` es tipo memo y su contenido se guarda en el archivo asociado `sum40014.FPT`; el ancho de 4 bytes del DBF es el puntero, no el límite del texto.

`sum40007.DBF` contiene los detalles del pedido. Descripciones de bienes o servicios y observaciones de órdenes son textos distintos; no deben confundirse con la glosa general.

Para corregirla, localizar el pedido en Avalon y usar su opción de modificación, verificando número, fecha, unidad y estado. No se ha comprobado el nombre exacto de la pantalla ni qué estados permiten editar. Si es necesario intervenir con Visual FoxPro, trabajar primero sobre una copia consistente del contenedor y tablas asociadas, identificar la clave completa y asegurar que se selecciona exactamente un registro. La revisión encontró números de pedido repetidos, por lo que NRO_PEDIDO por sí solo no es una condición suficiente para una actualización masiva. Evitar editar directamente el archivo binario DBF/FPT; usar el programa o herramientas que comprendan FoxPro.

SISEA almacena una copia en MySQL `seguimiento_notas.sustentacion` y en su caché. El origen de ese texto es FoxPro; una modificación local puede ser reemplazada durante la sincronización. Después de corregir el origen, actualizar la caché y el seguimiento mediante el flujo de sincronización existente.

## Cómo leer el catálogo

`catalogo_funcional.csv` y `catalogo_funcional.html` relacionan los 951 DBF legibles con el DBC que los registra y su función cuando existe evidencia. Una función pendiente se deja expresamente sin asignar. Contenedor vacío significa que no se resolvió una referencia entre los objetos Table del DBC y la ruta física; no certifica que la tabla sea libre. Se conserva también el backlink del DBF para investigar diferencias de rutas.

Los nombres de archivo no permiten documentar todas las funciones del negocio. Las descripciones comprobadas se basan en el código de SISEA; las interpretaciones por estructura requieren validación en Avalon. No se modificó ningún pedido.
