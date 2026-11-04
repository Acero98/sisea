<?php
declare(strict_types=1);
final class AlmacenReportesModel
{
    public const TRAMOS=['reciente'=>'Hasta 3 meses','3'=>'Más de 3 a 6 meses','6'=>'Más de 6 a 12 meses','12'=>'Más de 1 a 2 años','24'=>'Más de 2 a 3 años','36'=>'Más de 3 a 6 años','72'=>'Más de 6 años','desconocido'=>'Sin fecha para evaluar'];
    public static function fecha(string $valor): ?DateTimeImmutable
    {
        $valor=trim($valor);$d=DateTimeImmutable::createFromFormat('!Ymd',$valor);
        return $d && $d->format('Ymd')===$valor ? $d : null;
    }
    public static function limite(DateTimeImmutable $corte,int $meses): DateTimeImmutable
    {
        $base=$corte->modify('first day of this month')->modify('-'.$meses.' months');
        return $base->setDate((int)$base->format('Y'),(int)$base->format('m'),min((int)$corte->format('d'),(int)$base->format('t')));
    }
    public static function tramo(?DateTimeImmutable $base,DateTimeImmutable $corte): string
    {
        if (!$base || $base>$corte) return 'desconocido';
        foreach ([72,36,24,12,6,3] as $m) if ($base<self::limite($corte,$m)) return (string)$m;
        return 'reciente';
    }
    public static function filtros(array $in): array
    {
        $reporte=in_array($in['reporte']??'', ['altas','inactividad','ingresos'],true)?$in['reporte']:'altas';
        $mes=(string)($in['mes']??date('Y-m'));if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$mes))$mes=date('Y-m');
        $min=(string)($in['minimo']??'3');if(!in_array($min,['todos','3','6','12','24','36','72'],true))$min='3';
        $tramo=(string)($in['tramo']??'');if(!array_key_exists($tramo,self::TRAMOS))$tramo='';
        return ['reporte'=>$reporte,'mes'=>$mes,'minimo'=>$min,'tramo'=>$tramo,'q'=>mb_substr(trim((string)($in['q']??'')),0,150)];
    }
    public function cargar(array $f): array
    {
        $reader=new DbfReader(false);$corte=new DateTimeImmutable('today');$items=[];$duplicados=[];
        foreach ($reader->indexedRows(AVALON_LOGISTICA_DATA.'/sum40004.dbf',['TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','DESC_MAT','UNIMED','FECCREA','FECULTMOV']) as $entry) {
            $r=$entry['datos'];if(!$r)continue;$key=ConsultasLogisticaModel::recurso($r);
            if(isset($items[$key])){$duplicados[$key]=true;continue;}
            $alta=self::fecha($r['FECCREA']);$ultimo=self::fecha($r['FECULTMOV']);
            $items[$key]=['codigo'=>$key,'nombre'=>$r['DESC_MAT'],'unidad'=>$r['UNIMED'],'alta'=>$alta,'catalogo_mov'=>$ultimo,'ultimo'=>null,'ingresos'=>0,'cantidad_ingreso'=>0.0];
        }
        $anulados=0;$fechasInvalidas=0;$ultimoDisponible=null;
        foreach ($reader->indexedRows(AVALON_LOGISTICA_DATA.'/sum40008.DBF',['TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','FECHA','TIPO_MOV','ESTMOV','CANTIDAD']) as $entry) {
            $r=$entry['datos'];if(!$r)continue;
            if(trim($r['ESTMOV'])==='1'){$anulados++;continue;}
            $key=ConsultasLogisticaModel::recurso($r);if(!isset($items[$key]))continue;
            $fecha=self::fecha($r['FECHA']);if(!$fecha || $fecha>$corte){$fechasInvalidas++;continue;}
            if(!$ultimoDisponible || $fecha>$ultimoDisponible)$ultimoDisponible=$fecha;
            if(!$items[$key]['ultimo'] || $fecha>$items[$key]['ultimo'])$items[$key]['ultimo']=$fecha;
            if(trim($r['TIPO_MOV'])==='1' && $fecha->format('Y-m')===$f['mes']) {
                $items[$key]['ingresos']++;
                if(is_numeric($r['CANTIDAD']))$items[$key]['cantidad_ingreso']+=(float)$r['CANTIDAD'];
            }
        }
        $resumen=array_fill_keys(array_keys(self::TRAMOS),0);$altas=0;$ingresados=0;$resultado=[];
        foreach($items as $key=>$r) {
            $ult=$r['ultimo'];$origen=$ult?'Movimiento registrado':'';
            if($r['catalogo_mov'] && $r['catalogo_mov']<=$corte && (!$ult || $r['catalogo_mov']>$ult)){$ult=$r['catalogo_mov'];$origen='Fecha de último movimiento del catálogo';}
            $base=$ult??$r['alta'];
            if(!$ult)$origen=$r['alta']?'Sin movimiento encontrado; desde el alta':'Sin fecha para evaluar';
            if(isset($duplicados[$key]) || ($r['alta'] && $r['alta']>$corte) || ($r['catalogo_mov'] && $r['catalogo_mov']>$corte)){$base=null;$origen='Datos de fecha o código inconsistentes';}
            $tramo=self::tramo($base,$corte);$resumen[$tramo]++;
            $esAlta=$r['alta'] && $r['alta']->format('Y-m')===$f['mes'] && $r['alta']<=$corte;
            if($esAlta)$altas++;if($r['ingresos']>0)$ingresados++;
            $r['ultimo']=$ult;$r['base']=$base;$r['origen']=$origen;$r['tramo']=$tramo;
            $r['dias']=$base && $base<=$corte?(int)$base->diff($corte)->days:null;
            if($f['q']!=='' && mb_stripos($key.' '.$r['nombre'],$f['q'])===false)continue;
            if($f['reporte']==='altas' && !$esAlta)continue;
            if($f['reporte']==='ingresos' && !$r['ingresos'])continue;
            if($f['reporte']==='inactividad'){
                if($f['tramo']!=='' && $tramo!==$f['tramo'])continue;
                if($f['tramo']==='' && $f['minimo']!=='todos' && (!$base || $base>=self::limite($corte,(int)$f['minimo'])))continue;
            }
            $resultado[]=$r;
        }
        usort($resultado,static function($a,$b)use($f){
            if($f['reporte']==='inactividad')return ($b['dias']??-1)<=>($a['dias']??-1) ?: strcmp($a['codigo'],$b['codigo']);
            return strcmp($a['nombre'],$b['nombre']) ?: strcmp($a['codigo'],$b['codigo']);
        });
        return ['filas'=>$resultado,'total'=>count($items),'altas'=>$altas,'ingresados'=>$ingresados,'tramos'=>$resumen,'corte'=>$corte,'anulados'=>$anulados,'fechas_invalidas'=>$fechasInvalidas,'ultimo_disponible'=>$ultimoDisponible];
    }
    public static function celdaCsv($valor): string
    {
        $s=(string)$valor;
        return preg_match('/^[\s]*[=+@-]/u',$s) || preg_match('/^[\t\r\n]/',$s)?"'".$s:$s;
    }
}
