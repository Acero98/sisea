# Estados documentales de Avalon: verificación 2026-10-25

Inspección de solo lectura; no se modificaron datos de Avalon ni reglas de SISEA.

## Expediente y cotizaciones
Fuente: C:/Avalon/Logistica/logistica64.exe, código fuente incluido.
La consulta de cotizaciones une sum40025 y sum40022 por ZONA + LOCA + NRO_EXPE y selecciona sum40022.estado.
Equivalencia explícita: fcase(sum40022.estado,'P/C/A','Pendiente/Atendida/Anulada').
La creación de cotizaciones por proveedor inserta estado P en sum40025.
Al finalizar la generación de órdenes y las validaciones/actualizaciones presupuestales, el programa ejecuta UPDATE dbsumi!sum40022 SET estado = "C", numordens = nItem.
La anulación actualiza sum40022.estado a A y elimina las cotizaciones del expediente.

Caso 2000680, ZONA 01, LOCA 01: sum40022.estado=C; dos filas sum40025.estado=P. SISEA muestra P porque lee sum40025 y conserva una fila por expediente. Para representar el expediente debe consultar la cabecera sum40022 con la clave compuesta, no traducir directamente ese P.

## Órdenes
El código usa P/A/C/S = Pendiente/Anulada/Atendida/Parcial, para est_ordcmp en compras y estado en servicios.
La atención y la facturación/pago son campos diferentes. El código considera estfactu=C como orden pagada completamente.
Caso OC 2000295: est_ordcmp=P, estfactu=C, comppag=(01)E001-242, pagxfacs=13559.320000. No se deduce que el pago deba cambiar automáticamente el estado de atención.

## Facturas y pagos
Consulta sobre sum40030: iif(empty(estado),"Registrada",IIF(estado="A","Anulada","Pagada")). Por tanto P se muestra como Pagada en esa consulta.
La definición de Finanzas Mov_Cuenta_Corriente establece estado$"TPA" y describe T=total, P=Parcial, A=Anulado.

## Certificación: límite de la verificación
cabcertifica CODCER 2020000741 tiene estado G y PROCESO=Expediente Nro2000680.
Logística crea solicitudes en cabcertifica con estado S; esto está confirmado por su INSERT.
No se encontró la traducción ni transición a G en las definiciones DBC ni en el código legible disponible. Los ejecutables revisados de Planif actual y copias 2025/2026 no exponen estos identificadores como texto legible. No asignar a G un nombre supuesto (Generada, Girada, Aprobada, etc.). Se requiere observar la pantalla original de certificaciones o disponer de su fuente/manual para confirmar G y sus transiciones.

## Devengado
Registrado es una etiqueta de SISEA por existencia del asiento, no la traducción de un código de estado de Contabilidad.

## Aplicación en SISEA
Documentos vinculados ahora resuelve el estado del expediente desde sum40022 por ZONA + LOCA + NRO_EXPE. La lectura directa evita depender de campos ausentes en copias locales antiguas. Una cabecera ausente o ambigua no se interpreta como pendiente. Las órdenes muestran atención y facturación/pago por separado, y los códigos no confirmados conservan la leyenda por confirmar. La caché de seguimiento cambia a v30 para descartar resultados previos. Prueba: php tests/estados_documentos.php.
