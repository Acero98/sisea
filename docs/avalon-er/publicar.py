from pathlib import Path
import json, collections, csv, re, html
from reconstruir import OUT,writecsv

d=json.loads((OUT/'estructura.json').read_text(encoding='utf-8'))
tables=d['tables']; objects=d['dbc_objects']
lookup={(o['dbc'],o['id']):o for o in objects}
relations=[]
for o in objects:
    if o['type']!='Relation': continue
    p={x['id']:x['value'] for x in o['properties']}
    child=lookup.get((o['dbc'],o['parent']),{})
    relations.append(dict(module=o['module'],dbc=o['dbc'],parent_table=p.get(18,''),child_table=child.get('name',''),parent_tag=p.get(19,''),child_tag=p.get(13,''),ri=o['ri'],status='Declarada en DBC; cardinalidad y expresiones CDX pendientes'))
writecsv('relaciones_declaradas.csv',relations,['module','dbc','parent_table','child_table','parent_tag','child_tag','ri','status'])
writecsv('incidencias.csv',d['errors'],['path','error'])
groups=collections.defaultdict(list)
for t in tables: groups[(t['name'],t['signature'])].append(t['path'])
writecsv('estructuras_repetidas.csv',[dict(table=k[0],copies=len(v),paths=' | '.join(v)) for k,v in groups.items() if len(v)>1],['table','copies','paths'])

# Same-name fields are discovery leads, never foreign-key assertions.
candidates=[]
active=[t for t in tables if not t['backup'] and t['module'] not in ('Planif2025','Planif2026') and Path(t['path']).parent.name.lower()=='data']
for module in sorted({t['module'] for t in active}):
    fields=collections.defaultdict(list)
    for t in active:
        if t['module']!=module:continue
        for f in t['fields']:
            if re.match(r'^(COD|NRO_|ID[A-Z_]|CCTA|COMPROB|FCONTABLE)',f['name'].upper()): fields[(f['name'],f['type'],f['width'])].append(t['name'])
    for (field,typ,width),names in fields.items():
        if len(names)>1: candidates.append(dict(module=module,field=field,type=typ,width=width,tables=' | '.join(names),status='Candidato por nombre y tipo; sin validación de datos'))
writecsv('candidatos.csv',candidates,['module','field','type','width','tables','status'])

stats=collections.Counter(t['module'] for t in tables)
rc=collections.Counter(r['module'] for r in relations)
report=['# Reconstrucción de Avalon — 8 de octubre de 2026','',f'Se extrajeron {len(tables)} estructuras DBF y {sum(len(t["fields"]) for t in tables)} campos. Se leyeron 18 contenedores DBC y {len(relations)} relaciones declaradas. {len(d["errors"])} archivos no se pudieron interpretar; consultar incidencias.csv.','', '## Alcance y límites','', 'Lectura directa de cabeceras DBF y metadatos DBC/DCT, sin modificar Avalon ni exportar registros operativos. Los conteos de registros provienen de cabeceras y pueden incluir registros marcados como eliminados. La lectura no constituye una instantánea transaccional.','', 'Los diagramas representan dependencias declaradas mediante etiquetas de índice (TAG). No se asignan cardinalidades ni se equiparan etiquetas a columnas: falta extraer expresiones CDX y comprobar unicidad, claves compuestas, registros huérfanos y reglas activas. RIINFO se conserva literalmente, sin afirmar que las reglas se ejecuten.','', 'Planif2025 y Planif2026 se presentan separados; no se ha determinado cuál carpeta está activa. Nómina contiene una carpeta bk-pll-pnf identificada como copia por su ruta. Las firmas iguales indican estructura igual, no datos iguales.','', 'Las relaciones entre módulos y las relaciones implementadas únicamente en programas pueden faltar en el DBC. candidatos.csv contiene pistas por coincidencia de nombre y tipo, no relaciones confirmadas. El código de SISEA cubre parte del flujo documental; no representa todo el código original de Avalon.','', '## Inventario','', '| Carpeta | DBF legibles | Relaciones DBC |','|---|---:|---:|']
for m,n in sorted(stats.items()):report.append(f'| {m} | {n} | {rc[m]} |')
report+=['','## Archivos','', '- visor.html: visor local con diagramas por módulo y búsqueda de tablas y campos.','- tablas.csv y campos.csv: inventario y diccionario técnico; los significados de negocio siguen pendientes.','- relaciones_declaradas.csv: extremos, etiquetas, contenedor y RIINFO.','- objetos_dbc.csv: tablas, campos, índices, vistas y propiedades extraídas.','- estructuras_repetidas.csv: copias con igual nombre y estructura.','- candidatos.csv: pistas que requieren validación.','- incidencias.csv: archivos con cabecera incompleta.','- estructura.json: metadatos completos para análisis posterior.','','## Siguiente validación','', '1. Confirmar carpetas activas con los responsables de cada módulo.','2. Resolver expresiones de índices CDX y contrastar relaciones con ADBOBJECTS en una copia usando Visual FoxPro.','3. Analizar procedimientos, vistas, formularios y programas originales para conexiones entre módulos.','4. Medir unicidad y coincidencias de claves sobre una copia consistente.','5. Añadir definiciones de negocio y elaborar el modelo lógico objetivo de la reingeniería.','','Referencia del formato físico: [ayuda de Visual FoxPro](https://www.vfphelp.com/help/html/465e7a94-51b7-4e0c-98f9-432864fe5bcc.htm).']
(OUT/'INFORME.md').write_text('\n'.join(report),encoding='utf-8')
for module in stats:
    lines=['flowchart LR']
    nodes={}
    for r in relations:
        if r['module']!=module:continue
        for name in (r['parent_table'],r['child_table']):
            if name not in nodes:nodes[name]='T'+str(len(nodes));lines.append(f'  {nodes[name]}["{name}"]')
        lines.append(f'  {nodes[r["parent_table"]]} -->|"{r["parent_tag"]} → {r["child_tag"]}"| {nodes[r["child_table"]]}')
    (OUT/(module+'.mmd')).write_text('\n'.join(lines),encoding='utf-8')
profilepath=OUT/'vinculos_sisea_perfil.csv'
profiles=list(csv.DictReader(profilepath.open(encoding='utf-8-sig'))) if profilepath.exists() else []
if profiles:
    with (OUT/'INFORME.md').open('a',encoding='utf-8') as f:
        f.write('\n\n## Perfil de vínculos utilizados por SISEA\n\nSe compararon 16 vínculos de claves simples sobre registros no eliminados. Las coincidencias no certifican una clave foránea; las diferencias pueden deberse a históricos, claves compuestas, filtros o códigos con distinta representación. No se exportaron valores de registros.\n\n| Vínculo | Claves duplicadas en origen | Filas destino sin coincidencia |\n|---|---:|---:|\n')
        for r in profiles:
            f.write(f"| {r['parent_module']}.{r['parent_table']}.{r['parent_field']} → {r['child_module']}.{r['child_table']}.{r['child_field']} | {r.get('parent_duplicate_keys','')} | {r.get('unmatched_rows','')} |\n")
        f.write('\nConsultar vinculos_sisea_perfil.csv y flujo_sisea.mmd. Los vínculos sin coincidencias deben revisarse antes de incorporarlos al modelo lógico.\n')
payload=json.dumps(dict(tables=tables,relations=relations,errors=d['errors']),ensure_ascii=False).replace('</','<\\/')
page='''<!doctype html><html lang="es"><meta charset="utf-8"><title>Avalon · Reconstrucción E-R</title><style>body{font:15px system-ui;background:#f3f6fb;color:#17304a;margin:28px}h1{margin-bottom:5px}select,input{padding:10px;border:1px solid #b8c8d8;border-radius:7px;margin:8px}section{background:white;border-radius:12px;padding:18px;margin:16px 0}table{border-collapse:collapse;width:100%}td,th{padding:9px;text-align:left;border-bottom:1px solid #e0e7ef}summary{cursor:pointer}#graph{overflow:auto;max-height:650px}svg text{font:12px system-ui}small{color:#566b80}.note{background:#fff0cb;padding:12px;border-radius:8px}</style><h1>Avalon · Reconstrucción E-R</h1><p>Estructura física y relaciones declaradas · 8 de octubre de 2026</p><p class="note">Las flechas muestran vínculos DBC mediante etiquetas de índice. Cardinalidades, expresiones CDX y reglas de negocio pendientes de validación. Las copias y años se conservan separados.</p><select id="module"></select><input id="search" placeholder="Buscar tabla o campo"><section><h2>Relaciones declaradas</h2><p id="summary"></p><div id="graph"></div><table><thead><tr><th>Contenedor</th><th>Tabla referenciada / TAG</th><th>Tabla dependiente / TAG</th><th>RIINFO</th></tr></thead><tbody id="rels"></tbody></table></section><section><h2>Diccionario de tablas y campos</h2><div id="tables"></div></section><section><h2>Incidencias</h2><p id="errors"></p></section><script>const D=PAYLOAD;const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));const mods=[...new Set(D.tables.map(t=>t.module))].sort();module.innerHTML=mods.map(m=>`<option>${esc(m)}</option>`).join('');function draw(){const m=module.value,q=search.value.toLowerCase(),ts=D.tables.filter(t=>t.module===m&&(t.name.includes(q)||t.fields.some(f=>f.name.toLowerCase().includes(q)))),rs=D.relations.filter(r=>r.module===m);summary.textContent=`${ts.length} tablas visibles; ${rs.length} relaciones declaradas (pueden repetirse en copias).`;rels.innerHTML=rs.map(r=>`<tr><td>${esc(r.dbc)}</td><td>${esc(r.parent_table)} / ${esc(r.parent_tag)}</td><td>${esc(r.child_table)} / ${esc(r.child_tag)}</td><td>${esc(r.ri)}</td></tr>`).join('');tables.innerHTML=ts.map(t=>`<details><summary><b>${esc(t.name)}</b> · ${t.fields.length} campos · ${t.records.toLocaleString()} registros de cabecera${t.backup?' · copia':''}</summary><p><small>${esc(t.path)}<br>Contenedor en cabecera: ${esc(t.backlink||'Sin referencia')}</small></p><table><tr><th>Campo</th><th>Tipo FoxPro</th><th>Longitud</th><th>Decimales</th><th>Permite nulos</th></tr>${t.fields.map(f=>`<tr><td>${esc(f.name)}</td><td>${esc(f.type)}</td><td>${f.width}</td><td>${f.decimals}</td><td>${f.flags&2?'Sí':'No'}</td></tr>`).join('')}</table></details>`).join('');const ns=[...new Set(rs.flatMap(r=>[r.parent_table,r.child_table]))],w=1100,h=Math.max(250,Math.ceil(ns.length/4)*150),pos={};ns.forEach((n,i)=>pos[n]=[40+(i%4)*270,40+Math.floor(i/4)*150]);let svg=`<svg width="${w}" height="${h}" xmlns="http://www.w3.org/2000/svg"><defs><marker id="arrow" viewBox="0 0 10 10" refX="10" refY="5" markerWidth="6" markerHeight="6" orient="auto"><path d="M 0 0 L 10 5 L 0 10 z" fill="#637fa1"/></marker></defs>`;rs.forEach(r=>{let a=pos[r.parent_table],b=pos[r.child_table];svg+=`<path d="M${a[0]+100},${a[1]+45} Q${(a[0]+b[0])/2+160},${(a[1]+b[1])/2+90} ${b[0]+100},${b[1]}" fill="none" stroke="#637fa1" opacity=".55" marker-end="url(#arrow)"><title>${esc(r.parent_tag+' → '+r.child_tag+'; '+r.dbc)}</title></path>`});ns.forEach(n=>{let p=pos[n];svg+=`<rect x="${p[0]}" y="${p[1]}" width="210" height="45" rx="7" fill="#e7effa" stroke="#6483a6"/><text x="${p[0]+12}" y="${p[1]+28}">${esc(n)}</text>`});graph.innerHTML=ns.length?svg+'</svg>':'No hay relaciones DBC declaradas para esta carpeta.';errors.textContent=`${D.errors.length} archivos con incidencias. Consulte incidencias.csv para las rutas y causas.`;}module.onchange=draw;search.oninput=draw;draw();</script></html>'''
# Avoid the browser's legacy global `module` by explicitly binding elements.
if profiles:
    panel='<section><h2>Vínculos entre tablas usados por SISEA</h2><p>Comparación completa de claves simples en registros no eliminados. Las diferencias requieren revisar históricos, claves compuestas y reglas del negocio; no prueban corrupción.</p><table><tr><th>Origen → destino</th><th>Claves origen duplicadas</th><th>Filas coincidentes</th><th>Filas sin coincidencia</th></tr>'
    for r in profiles:
        panel+=f"<tr><td>{html.escape(r['parent_module']+'.'+r['parent_table']+'.'+r['parent_field']+' → '+r['child_module']+'.'+r['child_table']+'.'+r['child_field'])}</td><td>{r.get('parent_duplicate_keys','')}</td><td>{r.get('matched_rows','')}</td><td>{r.get('unmatched_rows','')}</td></tr>"
    page=page.replace('<script>',panel+'</table></section><script>')
page=page.replace('const D=PAYLOAD;', 'const module=document.getElementById("module"),search=document.getElementById("search"),summary=document.getElementById("summary"),rels=document.getElementById("rels"),tables=document.getElementById("tables"),graph=document.getElementById("graph"),errors=document.getElementById("errors");const D=PAYLOAD;')
(OUT/'visor.html').write_text(page.replace('PAYLOAD',payload),encoding='utf-8')
print(json.dumps(dict(tables=len(tables),fields=sum(len(t['fields']) for t in tables),relations=len(relations),modules=dict(stats),relation_modules=dict(rc),candidates=len(candidates),errors=len(d['errors']))))
