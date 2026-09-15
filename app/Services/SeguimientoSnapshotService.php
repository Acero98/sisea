<?php
declare(strict_types=1);
/** Construye una copia local de solo consulta. Nunca escribe en C:\Avalon. */
final class SeguimientoSnapshotService
{
    private DbfReader $reader;
    public function __construct(){ $this->reader=new DbfReader(); }
    public function sincronizar(array $filtro = []): int
    {
        $this->instalar(); $pdo=db(); $pdo->exec("UPDATE seguimiento_sincronizaciones SET estado='ERROR',terminado_en=NOW(),mensaje='Sincronización interrumpida' WHERE estado='EJECUTANDO'");
        $sync=$pdo->prepare("INSERT INTO seguimiento_sincronizaciones(iniciado_en,estado) VALUES(NOW(),'EJECUTANDO')"); $sync->execute(); $syncId=(int)$pdo->lastInsertId();
        try {
            $unidades=[]; foreach($this->reader->rows(AVALON_PLANIF_DATA.'/unidad.dbf',['COD_UNIDAD','DES_UNIDAD']) as $u)$unidades[$u['COD_UNIDAD']]=$u['DES_UNIDAD'];
            $notas=[]; $expedientes=[]; $ordenes=[]; $comprobantes=[];
            foreach($this->reader->rows(AVALON_LOGISTICA_DATA.'/sum40014.DBF',['NRO_PEDIDO','CODUNIORG','USR_COD','USRCODIGO','USRNOMBRE','EST_PEDIDO','FEC_EMISIO','FEC_ULTACT','USRDATE','PRIORIDAD','TIPO_PED','DOCCERTI']) as $r){
                $n=$r['NRO_PEDIDO']; if($n==='')continue; if(!$this->incluye($r,$filtro))continue; $nivel=$r['EST_PEDIDO']==='A'?0:($r['PRIORIDAD']==='4'?2:($r['DOCCERTI']!==''?3:1)); $etapa=$r['EST_PEDIDO']==='A'?'Pedido anulado':($r['PRIORIDAD']==='4'?'Pendiente de retiro de almacén':($r['DOCCERTI']!==''?'Certificación':'Pedido'));
                $notas[$n]=['nro_pedido'=>$n,'unidad_organica'=>$r['CODUNIORG'],'unidad_nombre'=>$unidades[$r['CODUNIORG']]??null,'usuario_codigo'=>$r['USRCODIGO']?:$r['USR_COD'],'usuario_nombre'=>$r['USRNOMBRE'],'estado_logistica'=>$r['EST_PEDIDO'],'fecha_emision'=>$this->fecha($r['FEC_EMISIO']),'fecha_registro'=>$this->fecha($r['FEC_ULTACT']?:$r['USRDATE']),'tipo_pedido'=>$r['TIPO_PED'],'prioridad'=>$r['PRIORIDAD'],'etapa'=>$etapa,'etapa_nivel'=>$nivel];
            }
            foreach($this->reader->rows(AVALON_LOGISTICA_DATA.'/sum40025.DBF',['NRO_EXPE','REFERENCIA','ESTADO']) as $r){$n=$this->pedido($r['REFERENCIA']);if($n&&isset($notas[$n])&&!$this->anulado($r['ESTADO'])){$expedientes[$r['NRO_EXPE']]=$n;$this->etapa($notas,$n,'Cotización',2);}}
            $certDirectos=[]; foreach($notas as $n=>$nota)if(($nota['etapa_nivel']??0)>0&&$nota['etapa']==='Certificación')$certDirectos[$nota['nro_pedido']]=$n;
            foreach($this->reader->rows(AVALON_PLANIF_DATA.'/cabcertifica.DBF',['CODCER','PROCESO','ESTADO']) as $r){if($this->anulado($r['ESTADO']))continue; preg_match_all('/\\b\\d{7}\\b/', $r['PROCESO'], $matches); foreach(array_unique($matches[0]) as $e)if(isset($expedientes[$e]))$this->etapa($notas,$expedientes[$e],'Certificación',3);}
            foreach($this->reader->rows(AVALON_LOGISTICA_DATA.'/sum40015.DBF',['NRO_ORDCMP','NRO_EXPE','REFER','EST_ORDCMP']) as $r){if($this->anulado($r['EST_ORDCMP']))continue;$n=$expedientes[$r['NRO_EXPE']]??$this->pedido($r['REFER']);if($n&&isset($notas[$n])){$ordenes['OC'.$r['NRO_ORDCMP']]=$n;$this->etapa($notas,$n,'Orden de compra',4);}}
            foreach($this->reader->rows(AVALON_LOGISTICA_DATA.'/sum40017.DBF',['NRO_SERVIC','NRO_EXPE','REFER','ESTADO']) as $r){if($this->anulado($r['ESTADO']))continue;$n=$expedientes[$r['NRO_EXPE']]??$this->pedido($r['REFER']);if($n&&isset($notas[$n])){$ordenes['OS'.$r['NRO_SERVIC']]=$n;$this->etapa($notas,$n,'Orden de servicio',4);}}
            foreach($this->reader->rows(AVALON_LOGISTICA_DATA.'/sum40030.DBF',['COMPROB','NRO_SERVIC','NRO_ORDCMP','ESTADO']) as $r){if($this->anulado($r['ESTADO']))continue;$n=$ordenes['OC'.$r['NRO_ORDCMP']]??$ordenes['OS'.$r['NRO_SERVIC']]??null;if($n){$comprobantes[$r['COMPROB']]=$n;$this->etapa($notas,$n,'Factura o boleta',5);}}
            foreach($this->reader->rows('C:/Avalon/contab/data/FCON0024.DBF',['OCOS']) as $r){$o=strtoupper(preg_replace('/\s+/','',$r['OCOS']));if(isset($ordenes[$o]))$this->etapa($notas,$ordenes[$o],'Devengado',6);}
            foreach($this->reader->rows('C:/Avalon/Finanzas/data/rfin0019.DBF',['COMPROB','ESTADO']) as $r){if(isset($comprobantes[$r['COMPROB']])&&!$this->anulado($r['ESTADO']))$this->etapa($notas,$comprobantes[$r['COMPROB']],'Pago',7);}
            foreach($this->reader->rows(AVALON_LOGISTICA_DATA.'/sum40008.DBF',['NRO_PEDIDO','ESTMOV']) as $r){$n=$r['NRO_PEDIDO'];if(isset($notas[$n])&&$notas[$n]['prioridad']==='4'&&!$this->anulado($r['ESTMOV'])){$notas[$n]['etapa']='Retiro de almacén';$notas[$n]['etapa_nivel']=2;}}
            $sql='INSERT INTO seguimiento_notas(nro_pedido,unidad_organica,unidad_nombre,usuario_codigo,usuario_nombre,estado_logistica,fecha_emision,fecha_registro,tipo_pedido,prioridad,etapa,etapa_nivel,sincronizado_en) VALUES(:nro_pedido,:unidad_organica,:unidad_nombre,:usuario_codigo,:usuario_nombre,:estado_logistica,:fecha_emision,:fecha_registro,:tipo_pedido,:prioridad,:etapa,:etapa_nivel,NOW()) ON DUPLICATE KEY UPDATE unidad_organica=VALUES(unidad_organica),unidad_nombre=VALUES(unidad_nombre),usuario_codigo=VALUES(usuario_codigo),usuario_nombre=VALUES(usuario_nombre),estado_logistica=VALUES(estado_logistica),fecha_emision=VALUES(fecha_emision),fecha_registro=VALUES(fecha_registro),tipo_pedido=VALUES(tipo_pedido),prioridad=VALUES(prioridad),etapa=VALUES(etapa),etapa_nivel=VALUES(etapa_nivel),sincronizado_en=NOW()';
            $stmt=$pdo->prepare($sql);$pdo->beginTransaction();$i=0;foreach($notas as $nota){$stmt->execute($nota);if(++$i%1000===0){$pdo->commit();$pdo->beginTransaction();}}$pdo->commit();$pdo->prepare("UPDATE seguimiento_sincronizaciones SET terminado_en=NOW(),notas_procesadas=:n,estado='COMPLETADO',mensaje='Sincronización de solo lectura desde Avalon' WHERE id=:id")->execute(['n'=>count($notas),'id'=>$syncId]);return count($notas);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$pdo->prepare("UPDATE seguimiento_sincronizaciones SET terminado_en=NOW(),estado='ERROR',mensaje=:m WHERE id=:id")->execute(['m'=>mb_substr($e->getMessage(),0,500),'id'=>$syncId]);throw $e;}
    }
    private function instalar():void{foreach(explode(";",(string)file_get_contents(APP_ROOT.'/database/migrations/003_seguimiento_local.sql')) as $sql)if(trim($sql)!=='')db()->exec($sql);}
    private function incluye(array $fila, array $filtro): bool
    {
        if (!$filtro) return true;
        if (($filtro['tipo'] ?? '') === 'unidad') return trim($fila['CODUNIORG']) === trim((string)($filtro['valor'] ?? ''));
        if (($filtro['tipo'] ?? '') === 'mis') { $n = static fn(string $v): string => ltrim(trim($v), '0') ?: '0'; $codigo=$n((string)($filtro['valor'] ?? '')); return $n($fila['USR_COD']) === $codigo || $n($fila['USRCODIGO']) === $codigo; }
        return false;
    }    private function etapa(array &$n,string $pedido,string $etapa,int $nivel):void{if(isset($n[$pedido])&&$n[$pedido]['etapa_nivel']>0&&$nivel>=$n[$pedido]['etapa_nivel']){$n[$pedido]['etapa']=$etapa;$n[$pedido]['etapa_nivel']=$nivel;}}
    private function pedido(string $texto):?string{if(preg_match('/PEDIDO\s*(?:NRO|N°|Nº)?\s*:?\s*(\d{7,})/iu',$texto,$m))return$m[1];if(preg_match('/\b(\d{7,})\b/',$texto,$m))return$m[1];return null;}
    private function certificadoExpediente(string $proceso,string $e):bool{return preg_match('/EXPEDIENTE\s*(?:NRO|N°|Nº)?\s*:?\s*'.preg_quote($e,'/').'(?!\d)/iu',$proceso)===1;}
    private function anulado(string $e):bool{return strtoupper(trim($e))==='A';}
    private function fecha(string $v):?string{return preg_match('/^\d{8}$/',$v)?substr($v,0,4).'-'.substr($v,4,2).'-'.substr($v,6,2):null;}
}




