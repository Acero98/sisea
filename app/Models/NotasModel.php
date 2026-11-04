<?php
declare(strict_types=1);
final class NotasModel
{
    public function listar(array $acceso,string $alcance,string $busqueda,int $pagina,int $porPagina,string $desde='',string $hasta='',string $filtroUnidad='',string $filtroUsuario='',string $orden='fecha_registro',string $direccion='DESC'):array
    {
        if(alcanceModulo('seguimiento',$acceso)==='PROPIO')$alcance='mis';
        $alcance=$alcance==='mis'?'mis':'unidad';$unidad=trim((string)($acceso['unidad_organica']??''));$codigo=trim((string)($acceso['avalon_codigo']??''));
        if(($alcance==='mis'&&$codigo==='')||($alcance==='unidad'&&(alcanceModulo('seguimiento',$acceso)!=='GLOBAL')&&($unidad===''||$unidad==='0000')))return['items'=>[],'total'=>0,'pagina'=>1,'por_pagina'=>$porPagina,'paginas'=>1];
        $where=[];$params=[];
        $this->filtrarFechas($where, $params, $desde, $hasta);
        if ($filtroUnidad !== '') { $where[]='unidad_organica=:filtro_unidad'; $params['filtro_unidad']=$filtroUnidad; }
        if ($filtroUsuario !== '') { $where[]='usuario_codigo=:filtro_usuario'; $params['filtro_usuario']=$filtroUsuario; }
        if($alcance==='mis'){$where[]="TRIM(LEADING '0' FROM usuario_codigo)=:codigo";$params['codigo']=ltrim($codigo,'0')?:'0';}
        elseif(alcanceModulo('seguimiento',$acceso)!=='GLOBAL'){$where[]='unidad_organica=:unidad';$params['unidad']=$unidad;}
        if(trim($busqueda)!==''){$where[]='(nro_pedido LIKE :q OR unidad_nombre LIKE :q OR unidad_organica LIKE :q OR usuario_nombre LIKE :q OR sustentacion LIKE :q OR etapa LIKE :q OR estado_logistica LIKE :q)';$params['q']='%'.trim($busqueda).'%';}
        $filtro=$where?' WHERE '.implode(' AND ',$where):'';$pdo=db();$total=(int)$this->valor($pdo,'SELECT COUNT(*) FROM seguimiento_notas'.$filtro,$params);$paginas=max(1,(int)ceil($total/$porPagina));$pagina=max(1,min($pagina,$paginas));
        if (!in_array($orden,['nro_pedido','fecha_emision','unidad_nombre','sustentacion','tipo_pedido','prioridad','etapa','usuario_nombre','fecha_registro'],true)) $orden='fecha_registro';
        $direccion=strtoupper($direccion)==='ASC'?'ASC':'DESC';
        $stmt=$pdo->prepare('SELECT * FROM seguimiento_notas'.$filtro.' ORDER BY '.$orden.' '.$direccion.',nro_pedido DESC LIMIT :lim OFFSET :off');foreach($params as $k=>$v)$stmt->bindValue(':'.$k,$v);$stmt->bindValue(':lim',$porPagina,PDO::PARAM_INT);$stmt->bindValue(':off',($pagina-1)*$porPagina,PDO::PARAM_INT);$stmt->execute();$items=$stmt->fetchAll();foreach($items as &$r){$r['NRO_PEDIDO']=$r['nro_pedido'];$r['FEC_EMISIO']=str_replace('-','',$r['fecha_emision']??'');$r['UNIDAD_LABEL']=trim(($r['unidad_organica']?:'—').' · '.($r['unidad_nombre']?:'Unidad no identificada'));$r['TIPO_LABEL']=$this->tipo($r['tipo_pedido']);$r['PRIORIDAD_LABEL']=$this->prioridad($r['prioridad']);$r['ESTADO_LABEL']=$this->estado($r['estado_logistica']);$r['ULTIMA_RUTA']=$r['etapa'];$r['USRNOMBRE']=$r['usuario_nombre'];$r['USRCODIGO']=$r['usuario_codigo'];}unset($r);return compact('items','total','pagina','porPagina','paginas')+['por_pagina'=>$porPagina];
    }
    public function ultimaSincronizacion():?array{return db()->query("SELECT terminado_en,notas_procesadas,estado,mensaje FROM seguimiento_sincronizaciones WHERE estado='COMPLETADO' ORDER BY id DESC LIMIT 1")->fetch()?:null;}
    public function exportar(array $acceso,string $alcance,string $busqueda,string $desde='',string $hasta='',string $filtroUnidad='',string $filtroUsuario=''):array
    {
        if(alcanceModulo('seguimiento',$acceso)==='PROPIO')$alcance='mis';
        $alcance=$alcance==='mis'?'mis':'unidad';$unidad=trim((string)($acceso['unidad_organica']??''));$codigo=trim((string)($acceso['avalon_codigo']??''));
        if(($alcance==='mis'&&$codigo==='')||($alcance==='unidad'&&(alcanceModulo('seguimiento',$acceso)!=='GLOBAL')&&($unidad===''||$unidad==='0000')))return[];
        $where=[];$params=[];
        $this->filtrarFechas($where, $params, $desde, $hasta);
        if ($filtroUnidad !== '') { $where[]='unidad_organica=:filtro_unidad'; $params['filtro_unidad']=$filtroUnidad; }
        if ($filtroUsuario !== '') { $where[]='usuario_codigo=:filtro_usuario'; $params['filtro_usuario']=$filtroUsuario; }
        if($alcance==='mis'){$where[]="TRIM(LEADING '0' FROM usuario_codigo)=:codigo";$params['codigo']=ltrim($codigo,'0')?:'0';}
        elseif(alcanceModulo('seguimiento',$acceso)!=='GLOBAL'){$where[]='unidad_organica=:unidad';$params['unidad']=$unidad;}
        if(trim($busqueda)!==''){$where[]='(nro_pedido LIKE :q OR unidad_nombre LIKE :q OR unidad_organica LIKE :q OR usuario_nombre LIKE :q OR sustentacion LIKE :q OR etapa LIKE :q OR estado_logistica LIKE :q)';$params['q']='%'.trim($busqueda).'%';}
        $stmt=db()->prepare('SELECT nro_pedido,fecha_emision,unidad_organica,unidad_nombre,sustentacion,tipo_pedido,prioridad,etapa,estado_logistica,usuario_codigo,usuario_nombre FROM seguimiento_notas'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY fecha_registro DESC,nro_pedido DESC');$stmt->execute($params);return$stmt->fetchAll();
    }
    public function opcionesFiltros(array $acceso,string $alcance):array
    {
        $where=[];$params=[];
        $scope=alcanceModulo('seguimiento',$acceso);
        if($scope==='PROPIO'||$alcance==='mis') {
            $codigo=trim((string)($acceso['avalon_codigo']??''));
            $where[]=$codigo===''?'1=0':"TRIM(LEADING '0' FROM usuario_codigo)=:codigo";
            if($codigo!=='')$params['codigo']=ltrim($codigo,'0')?:'0';
        } elseif($scope!=='GLOBAL') {
            $unidad=trim((string)($acceso['unidad_organica']??''));
            $where[]=($unidad===''||$unidad==='0000')?'1=0':'unidad_organica=:unidad';
            if($unidad!==''&&$unidad!=='0000')$params['unidad']=$unidad;
        }
        $stmt=db()->prepare('SELECT DISTINCT unidad_organica,unidad_nombre,usuario_codigo,usuario_nombre FROM seguimiento_notas'.($where?' WHERE '.implode(' AND ',$where):''));
        $stmt->execute($params);$unidades=[];$usuarios=[];
        foreach($stmt as $fila){
            if(trim($fila['unidad_organica']??'')!=='')$unidades[$fila['unidad_organica']]=$fila['unidad_organica'].' · '.($fila['unidad_nombre']?:'Unidad no identificada');
            if(trim($fila['usuario_codigo']??'')!=='')$usuarios[$fila['usuario_codigo']]=$fila['usuario_codigo'].' · '.($fila['usuario_nombre']?:'Nombre no registrado');
        }
        asort($unidades,SORT_NATURAL|SORT_FLAG_CASE);asort($usuarios,SORT_NATURAL|SORT_FLAG_CASE);
        return compact('unidades','usuarios');
    }
    private function filtrarFechas(array &$where, array &$params, string $desde, string $hasta): void
    {
        if ($desde !== '') { $where[] = 'fecha_emision >= :desde'; $params['desde'] = $desde; }
        if ($hasta !== '') { $where[] = 'fecha_emision <= :hasta'; $params['hasta'] = $hasta; }
    }
    private function valor(PDO $pdo,string $sql,array $p):string{$s=$pdo->prepare($sql);$s->execute($p);return(string)$s->fetchColumn();}private function estado(string $v):string{return['P'=>'Pendiente','T'=>'Atendida','A'=>'Anulada'][$v]??'Sin estado identificado';}private function tipo(string $v):string{return['E'=>'Existencia','S'=>'Servicios','A'=>'Activos'][$v]??'No identificado';}private function prioridad(string $v):string{return['1'=>'Normal','2'=>'Urgente','3'=>'Emergencia','4'=>'Retiro de almacén'][$v]??'No identificada';}
}

