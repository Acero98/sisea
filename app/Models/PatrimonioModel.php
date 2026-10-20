<?php
declare(strict_types=1);

/** Consulta el catálogo patrimonial directamente, sin modificar ni copiar los DBF. */
final class PatrimonioModel
{
    public function catalogo(): array
    {
        $reader=new DbfReader(false);
        $unidades=[]; $lugares=[]; $estados=[];
        foreach($reader->rows(AVALON_PLANIF_DATA.'/unidad.dbf',['COD_UNIDAD','DES_UNIDAD']) as $r) $unidades[$r['COD_UNIDAD']]=$r['DES_UNIDAD'];
        foreach($reader->rows(AVALON_PATRIMONIO_DATA.'/cpaf0031.dbf',['COD_LGE','DES_LGE']) as $r) $lugares[$r['COD_LGE']]=$r['DES_LGE'];
        foreach($reader->rows(AVALON_PATRIMONIO_DATA.'/cpaf0030.dbf',['NUM_TAB','COD_TAB','DES_TAB']) as $r) if($r['NUM_TAB']==='3') $estados[$r['COD_TAB']]=$r['DES_TAB'];
        $fields=['COD_SUC','COD_ACT','ANT_ACT','DES_ACT','SER_ACT','MARCA','MODELO','UOR_ACT','LGE_ACT','EST_ACT','FAL_ACT'];
        $rows=[];
        foreach($reader->indexedRows(AVALON_PATRIMONIO_DATA.'/cpaf0050.dbf',$fields) as $item) {
            if($item['datos']===null) continue;
            $r=$item['datos'];
            $rows[]=[
                'codigo'=>$r['COD_ACT'],'anterior'=>$r['ANT_ACT'],'descripcion'=>$r['DES_ACT'],
                'serie'=>$r['SER_ACT'],'marca'=>$r['MARCA'],'modelo'=>$r['MODELO'],
                'unidad_codigo'=>$r['UOR_ACT'],'unidad'=>$this->label($r['UOR_ACT'],$unidades),
                'ubicacion_codigo'=>$r['LGE_ACT'],'ubicacion'=>$this->label($r['LGE_ACT'],$lugares),
                'estado_codigo'=>$r['EST_ACT'],'estado'=>$this->label($r['EST_ACT'],$estados),
                'sucursal'=>$r['COD_SUC'],'fecha'=>$this->fecha($r['FAL_ACT'])
            ];
        }
        return $rows;
    }

    private function label(string $code,array $catalog): string
    {
        return $code===''?'Sin registrar':$code.(isset($catalog[$code])?' · '.$catalog[$code]:'');
    }
    private function fecha(string $raw): string
    {
        $date=DateTimeImmutable::createFromFormat('!Ymd',$raw);
        return $date && $date->format('Ymd')===$raw ? $date->format('Y-m-d') : '';
    }
    public function opciones(array $rows): array
    {
        $options=['unidad'=>[],'ubicacion'=>[],'estado'=>[]];
        foreach($rows as $r) foreach($options as $field=>$_) $options[$field][$r[$field.'_codigo'] ?: '__vacio']=$r[$field];
        foreach($options as &$values) asort($values,SORT_NATURAL|SORT_FLAG_CASE);
        return $options;
    }
    public function listar(array $params, ?array $rows=null): array
    {
        $rows=$rows??$this->catalogo(); $total=count($rows);
        foreach(['desde','hasta'] as $key) {
            $v=(string)($params[$key]??'');
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$v);
            if($v!=='' && (!$date || $date->format('Y-m-d')!==$v)) throw new DomainException('Seleccione una fecha válida.');
        }
        if(!empty($params['desde']) && !empty($params['hasta']) && $params['desde']>$params['hasta']) throw new DomainException('La fecha desde no puede ser posterior a hasta.');
        $search=mb_strtolower(trim((string)($params['search']['value']??'')),'UTF-8');
        $rows=array_values(array_filter($rows,static function(array $r) use($params,$search): bool {
            foreach(['unidad','ubicacion','estado'] as $field) {
                $value=(string)($params[$field]??'');
                if($value!=='' && $r[$field.'_codigo']!==($value==='__vacio'?'':$value)) return false;
            }
            if(!empty($params['desde']) && ($r['fecha']==='' || $r['fecha']<$params['desde'])) return false;
            if(!empty($params['hasta']) && ($r['fecha']==='' || $r['fecha']>$params['hasta'])) return false;
            return $search==='' || mb_strpos(mb_strtolower(implode(' ',$r),'UTF-8'),$search)!==false;
        }));
        $filtered=count($rows);
        $columns=['codigo','descripcion','serie','unidad','ubicacion','estado','fecha'];
        $sort=$columns[(int)($params['order'][0]['column']??0)]??'codigo';
        $direction=($params['order'][0]['dir']??'asc')==='desc'?-1:1;
        usort($rows,static function($a,$b) use($sort,$direction) { return $direction*(strnatcasecmp($a[$sort],$b[$sort]) ?: strcmp($a['codigo'].$a['sucursal'],$b['codigo'].$b['sucursal'])); });
        $length=max(10,min(100,(int)($params['length']??25)));
        return ['draw'=>max(0,(int)($params['draw']??0)),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>array_slice($rows,max(0,(int)($params['start']??0)),$length)];
    }
}
