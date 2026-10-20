# Reconstrucción de Avalon — 8 de octubre de 2026

Se extrajeron 951 estructuras DBF y 15090 campos. Se leyeron 18 contenedores DBC y 138 relaciones declaradas. 32 archivos no se pudieron interpretar; consultar incidencias.csv.

## Alcance y límites

Lectura directa de cabeceras DBF y metadatos DBC/DCT, sin modificar Avalon ni exportar registros operativos. Los conteos de registros provienen de cabeceras y pueden incluir registros marcados como eliminados. La lectura no constituye una instantánea transaccional.

Los diagramas representan dependencias declaradas mediante etiquetas de índice (TAG). No se asignan cardinalidades ni se equiparan etiquetas a columnas: falta extraer expresiones CDX y comprobar unicidad, claves compuestas, registros huérfanos y reglas activas. RIINFO se conserva literalmente, sin afirmar que las reglas se ejecuten.

Planif2025 y Planif2026 se presentan separados; no se ha determinado cuál carpeta está activa. Nómina contiene una carpeta bk-pll-pnf identificada como copia por su ruta. Las firmas iguales indican estructura igual, no datos iguales.

Las relaciones entre módulos y las relaciones implementadas únicamente en programas pueden faltar en el DBC. candidatos.csv contiene pistas por coincidencia de nombre y tipo, no relaciones confirmadas. El código de SISEA cubre parte del flujo documental; no representa todo el código original de Avalon.

## Inventario

| Carpeta | DBF legibles | Relaciones DBC |
|---|---:|---:|
| Finanzas | 107 | 9 |
| Logistica | 99 | 23 |
| Nomina | 194 | 22 |
| Patrimonio | 61 | 12 |
| Planif | 124 | 21 |
| Planif2025 | 122 | 21 |
| Planif2026 | 123 | 21 |
| contab | 121 | 9 |

## Archivos

- visor.html: visor local con diagramas por módulo y búsqueda de tablas y campos.
- tablas.csv y campos.csv: inventario y diccionario técnico; los significados de negocio siguen pendientes.
- relaciones_declaradas.csv: extremos, etiquetas, contenedor y RIINFO.
- objetos_dbc.csv: tablas, campos, índices, vistas y propiedades extraídas.
- estructuras_repetidas.csv: copias con igual nombre y estructura.
- candidatos.csv: pistas que requieren validación.
- incidencias.csv: archivos con cabecera incompleta.
- estructura.json: metadatos completos para análisis posterior.

## Siguiente validación

1. Confirmar carpetas activas con los responsables de cada módulo.
2. Resolver expresiones de índices CDX y contrastar relaciones con ADBOBJECTS en una copia usando Visual FoxPro.
3. Analizar procedimientos, vistas, formularios y programas originales para conexiones entre módulos.
4. Medir unicidad y coincidencias de claves sobre una copia consistente.
5. Añadir definiciones de negocio y elaborar el modelo lógico objetivo de la reingeniería.

Referencia del formato físico: [ayuda de Visual FoxPro](https://www.vfphelp.com/help/html/465e7a94-51b7-4e0c-98f9-432864fe5bcc.htm).

## Perfil de vínculos utilizados por SISEA

Se compararon 16 vínculos de claves simples sobre registros no eliminados. Las coincidencias no certifican una clave foránea; las diferencias pueden deberse a históricos, claves compuestas, filtros o códigos con distinta representación. No se exportaron valores de registros.

| Vínculo | Claves duplicadas en origen | Filas destino sin coincidencia |
|---|---:|---:|
| Logistica.sum40014.NRO_PEDIDO → Logistica.sum40007.NRO_PEDIDO | 16 | 161 |
| Logistica.sum40014.NRO_PEDIDO → Logistica.sum40008.NRO_PEDIDO | 16 | 6220 |
| Logistica.sum40025.NRO_EXPE → Logistica.sum40026.NRO_EXPE | 4722 | 0 |
| Logistica.sum40025.NRO_EXPE → Logistica.sum40028.NRO_EXPE | 4722 | 92 |
| Logistica.sum40025.NRO_EXPE → Logistica.sum40015.NRO_EXPE | 4722 | 7 |
| Logistica.sum40025.NRO_EXPE → Logistica.sum40017.NRO_EXPE | 4722 | 21 |
| Logistica.sum40015.NRO_ORDCMP → Logistica.sum40016.NRO_ORDCMP | 0 | 0 |
| Logistica.sum40017.NRO_SERVIC → Logistica.sum40018.NRO_SERVIC | 0 | 0 |
| Logistica.sum40015.NRO_ORDCMP → Logistica.sum40030.NRO_ORDCMP | 0 | 92 |
| Logistica.sum40017.NRO_SERVIC → Logistica.sum40030.NRO_SERVIC | 0 | 173 |
| Logistica.sum40030.COMPROB → Logistica.sum40031.COMPROB | 0 | 0 |
| Logistica.sum40030.COMPROB → Finanzas.rfin0019.COMPROB | 0 | 2135 |
| Planif.cabcertifica.CODCER → Planif.detcertifica.CODCER | 0 | 0 |
| Planif.unidad.COD_UNIDAD → Logistica.sum40014.CODUNIORG | 40 | 1 |
| contab.cct0004.CCTA → Logistica.sum40007.CTA_CON | 0 | 340888 |
| Planif.mae_proy.COD_PROY → Logistica.sum40007.COD_PROY | 83 | 101779 |

Consultar vinculos_sisea_perfil.csv y flujo_sisea.mmd. Los vínculos sin coincidencias deben revisarse antes de incorporarlos al modelo lógico.
