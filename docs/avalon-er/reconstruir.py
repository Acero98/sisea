from pathlib import Path
import struct, json, csv, hashlib, collections

ROOT=Path('C:/Avalon')
OUT=Path(__file__).resolve().parent

def schema(p):
    with p.open('rb') as f:
        h=f.read(32)
        if len(h)!=32: raise ValueError('Cabecera incompleta')
        count,start,size=struct.unpack_from('<IHH',h,4)
        if start<33 or size<1: raise ValueError('Cabecera inválida')
        fields=[]; offset=1
        while f.tell()<start:
            b=f.read(1)
            if b==b'\r': break
            d=b+f.read(31)
            if len(d)!=32: raise ValueError('Descriptor incompleto')
            name=d[:11].split(b'\0')[0].decode('cp1252')
            fields.append(dict(name=name,type=chr(d[11]),width=d[16],decimals=d[17],flags=d[18],offset=offset))
            offset+=d[16]
        backlink=f.read(max(0,start-f.tell())).split(b'\0')[0].decode('cp1252',errors='replace')
    return dict(path=str(p),module=p.relative_to(ROOT).parts[0],name=p.stem.lower(),records=count,start=start,record_size=size,fields=fields,backlink=backlink,bytes=p.stat().st_size,backup='bk-pll-pnf' in str(p).lower())

def rows(s):
    with open(s['path'],'rb') as f:
        f.seek(s['start'])
        for i in range(s['records']):
            r=f.read(s['record_size'])
            if len(r)!=s['record_size']: raise ValueError('Registros incompletos')
            if r[:1]!=b'*': yield {d['name']:r[d['offset']:d['offset']+d['width']] for d in s['fields']}

def memo(p,ptr):
    if not ptr: return b''
    with p.open('rb') as f:
        h=f.read(8); block=struct.unpack_from('>H',h,6)[0]
        if not block: raise ValueError('Bloque memo inválido')
        f.seek(ptr*block); h=f.read(8)
        if len(h)!=8: raise ValueError('Memo incompleto')
        n=struct.unpack_from('>I',h,4)[0]
        if n>32*1024*1024: raise ValueError('Memo demasiado grande')
        return f.read(n)

def text(b): return b.rstrip(b' \0').decode('cp1252',errors='replace')

def properties(b):
    result=[]; pos=0
    while pos+7<=len(b):
        size=struct.unpack_from('<I',b,pos)[0]
        if size<7 or pos+size>len(b): break
        result.append(dict(id=b[pos+6],value=text(b[pos+7:pos+size]),hex=b[pos:pos+size].hex()))
        pos+=size
    return result

def writecsv(name,items,keys):
    with (OUT/name).open('w',encoding='utf-8-sig',newline='') as f:
        w=csv.DictWriter(f,fieldnames=keys,extrasaction='ignore');w.writeheader();w.writerows(items)

def main():
    tables=[]; errors=[]; objects=[]
    for p in sorted(ROOT.rglob('*')):
        if p.suffix.lower() not in ('.dbf','.dbc'): continue
        try:
            s=schema(p)
            if p.suffix.lower()=='.dbf':
                s['signature']=hashlib.sha256(json.dumps([(d['name'],d['type'],d['width'],d['decimals'],d['flags']) for d in s['fields']]).encode()).hexdigest()
                tables.append(s);continue
            siblings={x.suffix.lower():x for x in p.parent.iterdir() if x.stem.lower()==p.stem.lower()}
            for r in rows(s):
                o=dict(dbc=str(p),module=s['module'],id=struct.unpack('<i',r['OBJECTID'])[0],parent=struct.unpack('<i',r['PARENTID'])[0],type=text(r['OBJECTTYPE']),name=text(r['OBJECTNAME']),ri=text(r.get('RIINFO',b'')))
                ptr=struct.unpack('<I',r['PROPERTY'])[0]
                b=memo(siblings['.dct'],ptr) if ptr else b''
                o['properties']=properties(b);o['property_hex']=b.hex()
                if o['type']=='Connection':
                    o['properties']=[dict(id=x['id'],value='[Contenido de conexión omitido]') for x in o['properties']]
                    o['property_hex']='[Omitido]'
                objects.append(o)
        except Exception as e: errors.append(dict(path=str(p),error=str(e)))
    (OUT/'estructura.json').write_text(json.dumps(dict(tables=tables,dbc_objects=objects,errors=errors),ensure_ascii=False,indent=2),encoding='utf-8')
    writecsv('tablas.csv',tables,['module','name','path','records','bytes','backlink','backup','signature'])
    fields=[dict(module=t['module'],table=t['name'],path=t['path'],nullable=bool(d['flags']&2),system=bool(d['flags']&1),**d) for t in tables for d in t['fields']]
    writecsv('campos.csv',fields,['module','table','path','name','type','width','decimals','nullable','system','offset','flags'])
    writecsv('objetos_dbc.csv',[{**o,'properties':json.dumps(o['properties'],ensure_ascii=False)} for o in objects],['module','dbc','id','parent','type','name','ri','properties'])
    print(json.dumps(dict(tables=len(tables),fields=len(fields),objects=len(objects),types=dict(collections.Counter(o['type'] for o in objects)),errors=errors),ensure_ascii=False))

if __name__=='__main__': main()
