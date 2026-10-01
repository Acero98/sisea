<?php
declare(strict_types=1);

final class PresupuestoModel
{
    private const MESES = ['ENE'=>'Enero','FEB'=>'Febrero','MAR'=>'Marzo','ABR'=>'Abril','MAY'=>'Mayo','JUN'=>'Junio','JUL'=>'Julio','AGO'=>'Agosto','SET'=>'Setiembre','OCT'=>'Octubre','NOV'=>'Noviembre','DIC'=>'Diciembre'];

    public function consultar(string $unidad, array $filtro = [], bool $global = false): array
    {
        $cache = APP_ROOT . '/storage/cache/presupuesto-v5-' . sha1($unidad . '|' . (int)$global . '|' . json_encode($filtro)) . '.json';
        if (is_file($cache) && filemtime($cache) > time() - 300) {
            $saved = json_decode((string) file_get_contents($cache), true);
            if (is_array($saved)) return $saved;
        }
        $fuentes = $this->fuentes($unidad, $this->catalogo($unidad), false, $global);
        $actividad = trim((string)($filtro['actividad'] ?? ''));
        $actividadNumerica = preg_replace('/\D/', '', $actividad);
        if (strlen($actividadNumerica) === 8 && substr($actividadNumerica, 0, 4) === '0101') $actividad = substr($actividadNumerica, -4);
        $cuenta = trim((string)($filtro['cuenta'] ?? ''));
        $tipo = (string)($filtro['tipo'] ?? 'operativo');
        $fuentes = array_values(array_filter($fuentes, static function (array $fila) use ($actividad, $cuenta, $tipo): bool {
            if ($actividad !== '' && $fila['actividad_presupuestal'] !== $actividad && $fila['actividad'] !== $actividad) return false;
            if ($cuenta !== '' && strpos($fila['cuenta'], $cuenta) === false) return false;
            if ($tipo === 'inversion') return $fila['actividad'] === '0408';
            if ($tipo === 'operativo') return $fila['actividad'] !== '0408';
            return true;
        }));
        $resultado = $this->leerImportes($fuentes);
        $resultado['unidad'] = $unidad;
        $resultado['actualizado_en'] = date('d/m/Y H:i');
        @file_put_contents($cache, json_encode($resultado, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $resultado;
    }

    /** Órdenes logísticas asociadas a los movimientos de compromiso de una cuenta. */
    public function ordenesComprometidas(string $actividad, string $cuenta, string $inversion, string $periodo): array
    {
        $meses = ['ENE'=>1,'FEB'=>2,'MAR'=>3,'ABR'=>4,'MAY'=>5,'JUN'=>6,'JUL'=>7,'AGO'=>8,'SET'=>9,'OCT'=>10,'NOV'=>11,'DIC'=>12];
        if ($periodo !== 'total' && !isset($meses[$periodo])) throw new InvalidArgumentException('El período seleccionado no es válido.');
        $anio = (int)date('Y'); $movimientos = [];
        foreach ((new DbfReader(false))->rows(AVALON_PLANIF_DATA . '/con_apru.dbf', ['COD_DOC','COD_NUM','COD_CCP','COD_CC','COD_INV','NUM_MES','VALOR','FECHA']) as $movimiento) {
            $fecha = preg_replace('/\D/', '', (string)$movimiento['FECHA']); $mes = (int)$movimiento['NUM_MES'];
            if ((int)substr($fecha, 0, 4) !== $anio || trim($movimiento['COD_DOC']) === 'EX'
                || trim($movimiento['COD_CCP']) !== $actividad || trim($movimiento['COD_CC']) !== $cuenta
                || trim($movimiento['COD_INV']) !== $inversion || $mes < 1 || $mes > 12
                || ($periodo !== 'total' && $meses[$periodo] !== $mes)) continue;
            $movimientos[] = $movimiento;
        }

        $ordenesPorExpediente = []; $ordenesPorReferencia = []; $reader = new DbfReader(false);
        foreach ([
            ['archivo'=>'sum40015.DBF','tipo'=>'Compra','campo'=>'NRO_ORDCMP','estado'=>'EST_ORDCMP','total'=>'TOTALOC'],
            ['archivo'=>'sum40017.DBF','tipo'=>'Servicio','campo'=>'NRO_SERVIC','estado'=>'ESTADO','total'=>'TOTALOS'],
        ] as $def) {
            foreach ($reader->rows(AVALON_LOGISTICA_DATA . '/' . $def['archivo'], [$def['campo'],'NRO_EXPE','FEC_EMISIO',$def['estado'],$def['total'],'VAL_TOTORD','MONEDA']) as $fila) {
                $expediente = trim($fila['NRO_EXPE']); $numero = trim($fila[$def['campo']]);
                if ($numero === '') continue;
                $total = $fila[$def['total']];
                if (!is_numeric($total) || (float)$total === 0.0) $total = $fila['VAL_TOTORD'];
                $fecha=preg_replace('/\D/','',trim($fila['FEC_EMISIO']));
                if(strlen($fecha)===8)$fecha=substr($fecha,6,2).'/'.substr($fecha,4,2).'/'.substr($fecha,0,4);
                $estado=trim($fila[$def['estado']]);
                $orden = ['numero'=>$numero,'tipo'=>$def['tipo'],'fecha'=>$fecha,'estado'=>$estado===''?'Sin estado':(strtoupper($estado)==='A'?'Anulada':'Avalon: '.$estado),'total'=>is_numeric($total)?(float)$total:null,'moneda'=>trim($fila['MONEDA']),'expediente'=>$expediente];
                $claveOrden = $def['tipo'].'|'.$numero;
                if ($expediente !== '') $ordenesPorExpediente[$expediente][$claveOrden] = $orden;
                $referencias = [$numero, ($def['tipo']==='Compra'?'OC':'OS').$numero];
                foreach ($referencias as $referencia) $ordenesPorReferencia[$this->normalizarReferencia($referencia)][$claveOrden] = $orden;
            }
        }
        $ordenes=[]; $compromisoTotal=0.0; $compromisoVinculado=0.0;
        foreach($movimientos as $movimiento){
            $monto=is_numeric($movimiento['VALOR'])?(float)$movimiento['VALOR']:0.0; $compromisoTotal+=$monto;
            $referencia=trim($movimiento['COD_NUM']); $tipoDocumento=strtoupper(trim($movimiento['COD_DOC']));
            if ($tipoDocumento === 'EX') continue;
            $coincidencias=$ordenesPorReferencia[$this->normalizarReferencia($referencia)]??[];
            if (!$coincidencias && $tipoDocumento === 'SU') $coincidencias=$ordenesPorExpediente[$referencia]??[];
            if (!$coincidencias) continue;
            $compromisoVinculado+=$monto;
            $distribucionExacta=count($coincidencias)===1;
            foreach($coincidencias as $clave=>$orden){if(!isset($ordenes[$clave]))$ordenes[$clave]=$orden+['compromiso'=>$distribucionExacta?0.0:null];if($ordenes[$clave]['compromiso']!==null)$ordenes[$clave]['compromiso']+=$monto;}
        }
        usort($ordenes,static fn(array $a,array $b):int=>[$a['fecha'],$a['numero']]<=>[$b['fecha'],$b['numero']]);
        $etiquetasMes=['ENE'=>'enero','FEB'=>'febrero','MAR'=>'marzo','ABR'=>'abril','MAY'=>'mayo','JUN'=>'junio','JUL'=>'julio','AGO'=>'agosto','SET'=>'setiembre','OCT'=>'octubre','NOV'=>'noviembre','DIC'=>'diciembre'];
        return ['anio'=>$anio,'periodo'=>$periodo==='total'?'anual':'de '.$etiquetasMes[$periodo],'total'=>$compromisoTotal,'vinculado'=>$compromisoVinculado,'sin_orden'=>$compromisoTotal-$compromisoVinculado,'ordenes'=>array_values($ordenes)];
    }

    private function normalizarReferencia(string $referencia): string
    {
        return strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', trim($referencia)));
    }

    public function configuracion(string $unidad): array
    {
        $fuentes = array_values(array_filter($this->fuentes($unidad, $this->catalogo($unidad), true), static fn(array $fila): bool => $fila['origen'] === 'EXTERNA'));
        usort($fuentes, static fn(array $a, array $b): int => [$a['origen'], $a['actividad'], $a['cuenta']] <=> [$b['origen'], $b['actividad'], $b['cuenta']]);
        return $fuentes;
    }

    public function guardarConfiguracion(string $unidad, array $clasificaciones): void
    {
        $permitidas = ['PROPIA_ADMINISTRADA', 'PROPIA_NO_ADMINISTRADA', 'EXTERNA_ADMINISTRADA', 'EXCLUIDA'];
        $porOrigen = ['PROPIA'=>['PROPIA_ADMINISTRADA','PROPIA_NO_ADMINISTRADA'], 'EXTERNA'=>['EXTERNA_ADMINISTRADA','EXCLUIDA']];
        $sql = 'INSERT INTO presupuesto_responsabilidades (unidad_responsable, actividad, unidad_propietaria, cuenta, clasificacion) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE clasificacion=VALUES(clasificacion)';
        $stmt = db()->prepare($sql);
        foreach ($this->configuracion($unidad) as $fila) {
            $clave = $this->clave($fila['actividad'], $fila['unidad_propietaria'], $fila['cuenta']);
            $clase = $clasificaciones[$clave] ?? $fila['clasificacion'];
            if (!in_array($clase, $permitidas, true) || !in_array($clase, $porOrigen[$fila['origen']], true)) continue;
            $stmt->execute([$unidad, $fila['actividad'], $fila['unidad_propietaria'], $fila['cuenta'], $clase]);
        }
        foreach (glob(APP_ROOT . '/storage/cache/presupuesto-v5-*.json') ?: [] as $file) @unlink($file);
    }

    public function clave(string $actividad, string $unidad, string $cuenta): string { return $actividad . '|' . $unidad . '|' . $cuenta; }

    private function catalogo(string $unidad): array
    {
        $q = db()->prepare('SELECT actividad, unidad_propietaria, cuenta, clasificacion FROM presupuesto_responsabilidades WHERE unidad_responsable = ?');
        $q->execute([$unidad]); $out = [];
        foreach ($q->fetchAll() as $fila) $out[$this->clave($fila['actividad'], $fila['unidad_propietaria'], $fila['cuenta'])] = $fila['clasificacion'];
        return $out;
    }

    private function fuentes(string $unidad, array $catalogo, bool $incluirExcluidas = false, bool $global = false): array
    {
        $reader = new DbfReader(false); $out = []; $unidades=[];
        foreach($reader->rows(AVALON_PLANIF_DATA.'/unidad.dbf',['COD_UNIDAD','DES_UNIDAD']) as $u)$unidades[trim($u['COD_UNIDAD'])]=trim($u['DES_UNIDAD']);
        foreach ($reader->rows(AVALON_PLANIF_DATA . '/mae_proy.dbf', ['COD_PROY','DESC_PROY','CTA_CTBLE','COD_INV','COD_UNIDAD']) as $fila) {
            $actividad = trim($fila['COD_PROY']); $propietaria = trim($fila['COD_UNIDAD']); $cuenta = trim($fila['CTA_CTBLE']);
            $clave = $this->clave($actividad, $propietaria, $cuenta); $esPropia = !$global && $propietaria === $unidad;
            if (!$global && !$esPropia && !isset($catalogo[$clave])) continue;
            $clase = $catalogo[$clave] ?? 'PROPIA_NO_ADMINISTRADA'; if ($clase === 'EXCLUIDA' && !$incluirExcluidas && !$global) continue;
            $out[$clave] = ['actividad'=>$actividad, 'actividad_nombre'=>trim($fila['DESC_PROY']), 'actividad_presupuestal'=>'01.01.'.substr($actividad,0,2).'.'.substr($actividad,2,2), 'unidad_propietaria'=>$propietaria, 'unidad_nombre'=>$unidades[$propietaria]??'', 'cuenta'=>$cuenta, 'cod_inv'=>trim($fila['COD_INV']), 'clasificacion'=>$global?'PROPIA_NO_ADMINISTRADA':$clase, 'origen'=>$esPropia?'PROPIA':'EXTERNA'];
        }
        return array_values($out);
    }

    private function leerImportes(array $fuentes): array
    {
        $porRecurso=[]; foreach($fuentes as $i=>$fuente)$porRecurso[$fuente['actividad_presupuestal'].'|'.$fuente['cuenta']][]=$i;
        $compromisos=[]; $anio=date('Y');
        foreach((new DbfReader(false))->rows(AVALON_PLANIF_DATA.'/con_apru.dbf',['COD_DOC','COD_CCP','COD_CC','COD_INV','NUM_MES','VALOR','FECHA']) as $movimiento){
            $fecha=preg_replace('/\D/','',(string)$movimiento['FECHA']); if(substr($fecha,0,4)!==$anio||trim($movimiento['COD_DOC'])==='EX')continue;
            $mes=(int)$movimiento['NUM_MES']; if($mes<1||$mes>12)continue;
            $clave=trim($movimiento['COD_CCP']).'|'.trim($movimiento['COD_CC']).'|'.trim($movimiento['COD_INV']);
            $compromisos[$clave][$mes]=($compromisos[$clave][$mes]??0)+(float)$movimiento['VALOR'];
        }
        $items=[];$grupos=[];
        foreach(['PROPIA_ADMINISTRADA'=>'Propio administrado por Informática','EXTERNA_ADMINISTRADA'=>'Presupuesto de Gerencias administrado por Informática','PROPIA_NO_ADMINISTRADA'=>'Presupuesto propio no administrado por Informática'] as $id=>$nombre)$grupos[$id]=['nombre'=>$nombre,'items'=>[],'total'=>$this->totalVacio()];
        $mensual=[];$campos=['COD_PRE','ORIGEN','COD_CCP1','DESCRIP','PIA','PRO_TOT'];foreach(array_keys(self::MESES)as$mes){$campos[]='EJE_'.$mes;$campos[]='DEV_'.$mes;}
        foreach((new DbfReader(false))->rows(AVALON_PLANIF_DATA.'/tab_recu.dbf',$campos)as$fila){
            if(trim($fila['ORIGEN'])!=='O')continue;$indices=$porRecurso[trim($fila['COD_PRE']).'|'.trim($fila['COD_CCP1'])]??[];
            foreach($indices as$i){$item=$fuentes[$i];$ejecucion=$devengado=0.0;$porMes=[];
                foreach(self::MESES as$mes=>$nombre){$numero=array_search($mes,array_keys(self::MESES),true)+1;$ejec=(float)($compromisos[$item['actividad_presupuestal'].'|'.$item['cuenta'].'|'.$item['cod_inv']][$numero]??0);$dev=(float)$fila['DEV_'.$mes];$porMes[$mes]=$ejec;$ejecucion+=$ejec;$devengado+=$dev;if($item['origen']==='PROPIA'||$item['clasificacion']==='EXTERNA_ADMINISTRADA'){$mensual[$mes]['mes']=$nombre;$mensual[$mes]['ejecucion']=($mensual[$mes]['ejecucion']??0)+$ejec;$mensual[$mes]['devengado']=($mensual[$mes]['devengado']??0)+$dev;}}
                $item+=['descripcion'=>trim($fila['DESCRIP']),'pia'=>(float)$fila['PIA'],'programado'=>(float)$fila['PRO_TOT'],'ejecucion'=>$ejecucion,'devengado'=>$devengado,'mensual'=>$porMes,'saldo'=>(float)$fila['PRO_TOT']-$ejecucion];
                $grupo=&$grupos[$item['clasificacion']];$grupo['items'][]=$item;$this->sumar($grupo['total'],$item);unset($grupo);$items[]=$item;
            }
        }
        foreach($grupos as&$grupo)usort($grupo['items'],static fn(array$a,array$b):int=>[$a['actividad'],$a['cuenta']]<=>[$b['actividad'],$b['cuenta']]);unset($grupo);ksort($mensual);
        return ['grupos'=>$grupos,'items'=>$items,'mensual'=>array_values($mensual)];
    }
    private function totalVacio(): array { return ['pia'=>0.0,'programado'=>0.0,'ejecucion'=>0.0,'devengado'=>0.0,'saldo'=>0.0]; }
    private function sumar(array &$total,array $item):void{foreach(array_keys($total)as$campo)$total[$campo]+=(float)$item[$campo];}
}

