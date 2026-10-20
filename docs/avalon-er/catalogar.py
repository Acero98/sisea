"""Catálogo funcional: distingue evidencia de funciones pendientes."""
from pathlib import Path
import json,html,collections
from reconstruir import OUT,writecsv

d=json.loads((OUT/'estructura.json').read_text(encoding='utf-8'))
known={
 ('Logistica','sum10001'):('Menús y opciones de acceso','Estructura de campos'),
 ('Logistica','sum10002'):('Perfiles de usuarios','app/Models/UsuarioModel.php'),
 ('Logistica','sum10003'):('Usuarios, perfil, unidad, almacén y permisos','app/Models/UsuarioModel.php'),
 ('Logistica','sum40014'):('Cabecera de pedidos; glosa/sustentación en COMENTARIO','app/Models/SeguimientoModel.php; app/Services/SeguimientoSnapshotService.php'),
 ('Logistica','sum40007'):('Detalle de bienes o servicios solicitados en pedidos','app/Services/AvalonCacheService.php; app/Models/SeguimientoModel.php'),
 ('Logistica','sum40003'):('Catálogo de servicios','app/Services/AvalonCacheService.php'),
 ('Logistica','sum40004'):('Catálogo de materiales/bienes','app/Services/AvalonCacheService.php'),
 ('Logistica','sum40008'):('Movimientos de almacén asociados a pedidos','app/Services/SeguimientoSnapshotService.php'),
 ('Logistica','sum40025'):('Cabecera de expediente de cotización','app/Services/SeguimientoSnapshotService.php'),
 ('Logistica','sum40026'):('Ofertas por proveedor y expediente de cotización','Estructura: NRO_EXPE, CODPROV, VALOFER, PLAZENTR'),
 ('Logistica','sum40028'):('Detalle de cotización y adjudicación','app/Services/AvalonCacheService.php'),
 ('Logistica','sum40015'):('Cabecera de órdenes de compra','app/Services/SeguimientoSnapshotService.php'),
 ('Logistica','sum40016'):('Detalle de órdenes de compra','app/Models/SeguimientoModel.php'),
 ('Logistica','sum40017'):('Cabecera de órdenes de servicio','app/Services/SeguimientoSnapshotService.php'),
 ('Logistica','sum40018'):('Detalle de órdenes de servicio','app/Models/SeguimientoModel.php'),
 ('Logistica','sum40030'):('Cabecera de comprobantes relacionados con órdenes','app/Services/SeguimientoSnapshotService.php'),
 ('Logistica','sum40031'):('Detalle de comprobantes','app/Services/AvalonCacheService.php'),
 ('contab','cct0004'):('Catálogo de cuentas utilizado por SISEA','app/Services/AvalonCacheService.php'),
 ('contab','fcon0024'):('Registros contables utilizados en seguimiento de órdenes','app/Services/SeguimientoSnapshotService.php'),
 ('Finanzas','rfin0019'):('Registros de Finanzas utilizados en seguimiento de comprobantes/pagos','app/Services/SeguimientoSnapshotService.php'),
 ('Planif','cabcertifica'):('Cabecera de certificaciones presupuestales','app/Services/SeguimientoSnapshotService.php'),
 ('Planif','detcertifica'):('Detalle de certificaciones presupuestales','app/Services/AvalonCacheService.php'),
 ('Planif','unidad'):('Unidades orgánicas','app/Models/UsuarioModel.php'),
 ('Planif','mae_proy'):('Catálogo de proyectos','app/Services/AvalonCacheService.php'),
 ('Planif','tab_recu'):('Catálogo presupuestal de recursos utilizado por SISEA','app/Services/AvalonCacheService.php'),
}
refs=collections.defaultdict(list)
for o in d['dbc_objects']:
    if o['type']!='Table':continue
    p=next((p['value'] for p in o['properties'] if p['id']==1),None)
    if p:
        target=(Path(o['dbc']).parent/Path(p)).resolve()
        refs[str(target).casefold()].append(o['dbc'])
catalog=[]
for t in d['tables']:
    key=(t['module'],t['name']); desc,evidence=known.get(key,('Función de negocio pendiente de identificar','Cabecera DBF extraída'))
    status='Identificada en uso por SISEA' if evidence.startswith('app/') else ('Interpretación por estructura; validar con Avalon' if key in known else 'Pendiente')
    if t['module'] in ('Planif2025','Planif2026') and ('Planif',t['name']) in known:
        desc=known[('Planif',t['name'])][0];evidence='Nombre y estructura equivalentes a Planif';status='Interpretación por estructura; validar carpeta activa'
    fields={f['name'] for f in t['fields']}
    if {'CCODUSUARI','CNOMUSUARI','CPWDUSUARI','CPERFILUSR'}<=fields:
        desc='Usuarios de acceso y perfil asignado'
        if key not in known:evidence='Campos de identificación, contraseña y perfil';status='Interpretación por estructura; validar con Avalon'
    elif {'CODPERFIL','NOMPERFIL'}<=fields:
        desc='Catálogo de perfiles de acceso'
        if key not in known:evidence='Campos CODPERFIL y NOMPERFIL';status='Interpretación por estructura; validar con Avalon'
    elif {'CIDOPCION','CPERFILES','CCOMANDO'}<=fields:
        desc='Opciones de menú y perfiles asociados';evidence='Campos CIDOPCION, CPERFILES y CCOMANDO';status='Interpretación por estructura; validar con Avalon'
    containers=refs.get(str(Path(t['path']).resolve()).casefold(),[])
    catalog.append(dict(module=t['module'],container=' | '.join(containers),table=t['name']+'.dbf',path=t['path'],function=desc,status=status,evidence=evidence,records_header=t['records'],fields_count=len(t['fields']),backlink=t['backlink'],note='Copia por ruta' if t['backup'] else ('Histórico/año; actividad pendiente de confirmar' if t['module'] in ('Planif2025','Planif2026') else ''),field_names=', '.join(f['name'] for f in t['fields'])))
writecsv('catalogo_funcional.csv',catalog,['module','container','table','path','function','status','evidence','records_header','fields_count','backlink','note','field_names'])
guide='''# Catálogo funcional de Avalon

## Glosa de un pedido

La glosa/sustentación general que muestra SISEA se lee de `C:\\Avalon\\Logistica\\data\\sum40014.DBF`, campo `COMENTARIO`, vinculado al contenedor `dbsumi.DBC`. El pedido se busca por `NRO_PEDIDO` (carácter de 7 posiciones). `COMENTARIO` es tipo memo y su contenido se guarda en el archivo asociado `sum40014.FPT`; el ancho de 4 bytes del DBF es el puntero, no el límite del texto.

`sum40007.DBF` contiene los detalles del pedido. Descripciones de bienes o servicios y observaciones de órdenes son textos distintos; no deben confundirse con la glosa general.

Para corregirla, localizar el pedido en Avalon y usar su opción de modificación, verificando número, fecha, unidad y estado. No se ha comprobado el nombre exacto de la pantalla ni qué estados permiten editar. Si es necesario intervenir con Visual FoxPro, trabajar primero sobre una copia consistente del contenedor y tablas asociadas, identificar la clave completa y asegurar que se selecciona exactamente un registro. La revisión encontró números de pedido repetidos, por lo que NRO_PEDIDO por sí solo no es una condición suficiente para una actualización masiva. Evitar editar directamente el archivo binario DBF/FPT; usar el programa o herramientas que comprendan FoxPro.

SISEA almacena una copia en MySQL `seguimiento_notas.sustentacion` y en su caché. El origen de ese texto es FoxPro; una modificación local puede ser reemplazada durante la sincronización. Después de corregir el origen, actualizar la caché y el seguimiento mediante el flujo de sincronización existente.

## Cómo leer el catálogo

`catalogo_funcional.csv` y `catalogo_funcional.html` relacionan los 951 DBF legibles con el DBC que los registra y su función cuando existe evidencia. Una función pendiente se deja expresamente sin asignar. Contenedor vacío significa que no se resolvió una referencia entre los objetos Table del DBC y la ruta física; no certifica que la tabla sea libre. Se conserva también el backlink del DBF para investigar diferencias de rutas.

Los nombres de archivo no permiten documentar todas las funciones del negocio. Las descripciones comprobadas se basan en el código de SISEA; las interpretaciones por estructura requieren validación en Avalon. No se modificó ningún pedido.
'''
(OUT/'GUIA_TABLAS.md').write_text(guide,encoding='utf-8')
payload=json.dumps(catalog,ensure_ascii=False).replace('</','<\\/')
page='''<!doctype html><html lang="es"><meta charset="utf-8"><title>Avalon · Catálogo de tablas</title><style>body{font:15px system-ui;margin:25px;color:#18334d;background:#f4f7fa}input,select{padding:10px;margin:5px;border:1px solid #bccbd7;border-radius:6px}table{width:100%;border-collapse:collapse;background:white}th,td{text-align:left;padding:12px;border-bottom:1px solid #dce5ee;vertical-align:top}th{background:#dae7f3}small{display:block;color:#5f7180;max-width:400px;overflow-wrap:anywhere}aside{background:#fff0ce;padding:15px;border-radius:8px;margin:15px 0}details{max-width:430px}summary{cursor:pointer}</style><h1>Avalon · Catálogo de contenedores y tablas</h1><aside><b>Glosa general del pedido:</b> Logística → dbsumi.DBC → sum40014.DBF → COMENTARIO (memo en sum40014.FPT). Identificador de búsqueda: NRO_PEDIDO. Hay números repetidos: confirmar el registro y su contexto antes de modificar.</aside><p>Funciones con evidencia de SISEA o interpretación estructural. Las demás quedan pendientes. Un contenedor vacío significa referencia no resuelta.</p><select id="mod"><option value="">Todos los módulos</option></select><select id="state"><option value="">Todas las funciones</option><option value="identified">Con función identificada o interpretada</option><option value="pending">Pendientes</option></select><input id="q" placeholder="Buscar tabla, campo o función" size="40"><p id="count"></p><table><thead><tr><th>Módulo / contenedor</th><th>Tabla y campos</th><th>Función / evidencia</th></tr></thead><tbody id="body"></tbody></table><script>const data=PAYLOAD,mod=document.getElementById('mod'),state=document.getElementById('state'),q=document.getElementById('q'),body=document.getElementById('body'),count=document.getElementById('count');const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));mod.innerHTML+=[...new Set(data.map(t=>t.module))].sort().map(m=>`<option>${esc(m)}</option>`).join('');function draw(){const query=q.value.toLowerCase(),ts=data.filter(t=>(!mod.value||t.module===mod.value)&&(!state.value||(state.value==='pending'?t.status==='Pendiente':t.status!=='Pendiente'))&&[t.table,t.function,t.field_names,t.container].join(' ').toLowerCase().includes(query));count.textContent=ts.length+' tablas';body.innerHTML=ts.map(t=>`<tr><td><b>${esc(t.module)}</b><small>${esc(t.container||'Sin referencia resuelta')}<br>${esc(t.note)}</small></td><td><b>${esc(t.table)}</b><small>${esc(t.path)}</small><details><summary>${t.fields_count} campos</summary>${esc(t.field_names)}</details></td><td>${esc(t.function)}<small>${esc(t.status)}<br>Evidencia: ${esc(t.evidence)}</small></td></tr>`).join('')}mod.onchange=draw;state.onchange=draw;q.oninput=draw;draw();</script></html>'''
(OUT/'catalogo_funcional.html').write_text(page.replace('PAYLOAD',payload),encoding='utf-8')
print(json.dumps(dict(tables=len(catalog),with_container=sum(bool(t['container']) for t in catalog),with_function=sum(t['status']!='Pendiente' for t in catalog)),ensure_ascii=False))
