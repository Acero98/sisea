"""Perfil agregado de claves simples; no exporta valores de registros."""
import json, collections
from reconstruir import OUT,rows,text,writecsv

d=json.loads((OUT/'estructura.json').read_text(encoding='utf-8'))
tables={(t['module'].lower(),t['name']):t for t in d['tables'] if not t['backup'] and __import__('pathlib').Path(t['path']).parent.name.lower()=='data'}
edges=[
 ('Logistica','sum40014','NRO_PEDIDO','Logistica','sum40007','NRO_PEDIDO'),
 ('Logistica','sum40014','NRO_PEDIDO','Logistica','sum40008','NRO_PEDIDO'),
 ('Logistica','sum40025','NRO_EXPE','Logistica','sum40026','NRO_EXPE'),
 ('Logistica','sum40025','NRO_EXPE','Logistica','sum40028','NRO_EXPE'),
 ('Logistica','sum40025','NRO_EXPE','Logistica','sum40015','NRO_EXPE'),
 ('Logistica','sum40025','NRO_EXPE','Logistica','sum40017','NRO_EXPE'),
 ('Logistica','sum40015','NRO_ORDCMP','Logistica','sum40016','NRO_ORDCMP'),
 ('Logistica','sum40017','NRO_SERVIC','Logistica','sum40018','NRO_SERVIC'),
 ('Logistica','sum40015','NRO_ORDCMP','Logistica','sum40030','NRO_ORDCMP'),
 ('Logistica','sum40017','NRO_SERVIC','Logistica','sum40030','NRO_SERVIC'),
 ('Logistica','sum40030','COMPROB','Logistica','sum40031','COMPROB'),
 ('Logistica','sum40030','COMPROB','Finanzas','rfin0019','COMPROB'),
 ('Planif','cabcertifica','CODCER','Planif','detcertifica','CODCER'),
 ('Planif','unidad','COD_UNIDAD','Logistica','sum40014','CODUNIORG'),
 ('contab','cct0004','CCTA','Logistica','sum40007','CTA_CON'),
 ('Planif','mae_proy','COD_PROY','Logistica','sum40007','COD_PROY'),
]
cache={}
def profile(mod,table,field):
    k=(mod.lower(),table,field)
    if k not in cache:
        s=tables[(mod.lower(),table)]
        fs={f['name']:f for f in s['fields']}
        if fs[field]['type'] not in ('C','N'):raise ValueError('Tipo no admitido para clave simple')
        c=collections.Counter();blank=0
        for r in rows(s):
            v=text(r[field]).strip()
            if v:c[v]+=1
            else:blank+=1
        cache[k]=(c,blank)
    return cache[k]
result=[];lines=['flowchart LR']
nodes={}
for pm,pt,pf,cm,ct,cf in edges:
    r=dict(parent_module=pm,parent_table=pt,parent_field=pf,child_module=cm,child_table=ct,child_field=cf,evidence='Hipótesis basada en campos utilizados por SISEA; comparación de valores simples, sin validar claves compuestas ni reglas de negocio')
    try:
        pc,pb=profile(pm,pt,pf);cc,cb=profile(cm,ct,cf)
        r.update(parent_rows=sum(pc.values()),parent_blank=pb,parent_distinct=len(pc),parent_duplicate_keys=sum(n>1 for n in pc.values()),child_rows=sum(cc.values()),child_blank=cb,matched_rows=sum(n for v,n in cc.items() if v in pc),unmatched_rows=sum(n for v,n in cc.items() if v not in pc),status='Perfil completo de registros no eliminados; cardinalidad no certificada')
    except Exception as e:r['status']='No validado: '+str(e)
    result.append(r)
    for m,t in ((pm,pt),(cm,ct)):
        if (m,t) not in nodes:
            nodes[(m,t)]='T'+str(len(nodes));lines.append(f'  {nodes[(m,t)]}["{m}.{t}"]')
    lines.append(f'  {nodes[(pm,pt)]} -.->|"{pf} / {cf}"| {nodes[(cm,ct)]}')
writecsv('vinculos_sisea_perfil.csv',result,['parent_module','parent_table','parent_field','child_module','child_table','child_field','parent_rows','parent_blank','parent_distinct','parent_duplicate_keys','child_rows','child_blank','matched_rows','unmatched_rows','evidence','status'])
(OUT/'flujo_sisea.mmd').write_text('\n'.join(lines),encoding='utf-8')
print(json.dumps(result,ensure_ascii=False))
