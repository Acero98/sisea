<?php
declare(strict_types=1);
final class NotasModel
{
    public function listar(array $acceso,string $alcance,string $busqueda,int $pagina,int $porPagina):array
    {
        $alcance=$alcance==='mis'?'mis':'unidad';$unidad=trim((string)($acceso['unidad_organica']??''));$codigo=trim((string)($acceso['avalon_codigo']??''));
        if(($alcance==='mis'&&$codigo==='')||($alcance==='unidad'&&(($acceso['rol']??'')!=='ADMIN')&&($unidad===''||$unidad==='0000')))return['items'=>[],'total'=>0,'pagina'=>1,'por_pagina'=>$porPagina,'paginas'=>1];
        $where=[];$params=[];
        if($alcance==='mis'){$where[]="TRIM(LEADING '0' FROM usuario_codigo)=:codigo";$params['codigo']=ltrim($codigo,'0')?:'0';}
        elseif(($acceso['rol']??'')!=='ADMIN'||($unidad!==''&&$unidad!=='0000')){$where[]='unidad_organica=:unidad';$params['unidad']=$unidad;}
        if(trim($busqueda)!==''){$where[]='(nro_pedido LIKE :q OR unidad_nombre LIKE :q OR unidad_organica LIKE :q OR usuario_nombre LIKE :q OR etapa LIKE :q OR estado_logistica LIKE :q)';$params['q']='%'.trim($busqueda).'%';}
        $filtro=$where?' WHERE '.implode(' AND ',$where):'';$pdo=db();$total=(int)$this->valor($pdo,'SELECT COUNT(*) FROM seguimiento_notas'.$filtro,$params);$paginas=max(1,(int)ceil($total/$porPagina));$pagina=max(1,min($pagina,$paginas));
        $stmt=$pdo->prepare('SELECT * FROM seguimiento_notas'.$filtro.' ORDER BY fecha_registro DESC,nro_pedido DESC LIMIT :lim OFFSET :off');foreach($params as $k=>$v)$stmt->bindValue(':'.$k,$v);$stmt->bindValue(':lim',$porPagina,PDO::PARAM_INT);$stmt->bindValue(':off',($pagina-1)*$porPagina,PDO::PARAM_INT);$stmt->execute();$items=$stmt->fetchAll();foreach($items as &$r){$r['NRO_PEDIDO']=$r['nro_pedido'];$r['FEC_EMISIO']=str_replace('-','',$r['fecha_emision']??'');$r['UNIDAD_LABEL']=trim(($r['unidad_organica']?:'—').' · '.($r['unidad_nombre']?:'Unidad no identificada'));$r['TIPO_LABEL']=$this->tipo($r['tipo_pedido']);$r['PRIORIDAD_LABEL']=$this->prioridad($r['prioridad']);$r['ESTADO_LABEL']=$this->estado($r['estado_logistica']);$r['ULTIMA_RUTA']=$r['etapa'];$r['USRNOMBRE']=$r['usuario_nombre'];$r['USRCODIGO']=$r['usuario_codigo'];}unset($r);return compact('items','total','pagina','porPagina','paginas')+['por_pagina'=>$porPagina];
    }
    public function ultimaSincronizacion():?array{return db()->query("SELECT terminado_en,notas_procesadas,estado,mensaje FROM seguimiento_sincronizaciones WHERE estado='COMPLETADO' ORDER BY id DESC LIMIT 1")->fetch()?:null;}
    private function valor(PDO $pdo,string $sql,array $p):string{$s=$pdo->prepare($sql);$s->execute($p);return(string)$s->fetchColumn();}private function estado(string $v):string{return['P'=>'Pendiente','T'=>'Atendida','A'=>'Anulada'][$v]??'Sin estado identificado';}private function tipo(string $v):string{return['E'=>'Existencia','S'=>'Servicios','A'=>'Activos'][$v]??'No identificado';}private function prioridad(string $v):string{return['1'=>'Normal','2'=>'Urgente','3'=>'Emergencia','4'=>'Retiro de almacén'][$v]??'No identificada';}
}

