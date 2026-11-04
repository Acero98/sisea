<?php
declare(strict_types=1);
require __DIR__.'/../config/app.php';require APP_ROOT.'/app/Helpers.php';
function verificarConsulta(bool $ok,string $mensaje): void { if(!$ok) throw new RuntimeException($mensaje); }
$pedido=['CODUNIORG'=>'5100','USRCODIGO'=>'00123'];
$a=['permiso_ordenes'=>1,'alcance_ordenes'=>'PROPIO','avalon_codigo'=>'123'];
verificarConsulta(ConsultasLogisticaModel::permitido($pedido,$a),'Propio normalizado');
verificarConsulta(!ConsultasLogisticaModel::permitido($pedido,array_merge($a,['avalon_codigo'=>'124'])),'No cruzar usuarios');
verificarConsulta(!ConsultasLogisticaModel::permitido($pedido,array_merge($a,['avalon_codigo'=>''])),'Identidad ausente');
verificarConsulta(ConsultasLogisticaModel::permitido($pedido,['permiso_ordenes'=>1,'alcance_ordenes'=>'UNIDAD','unidad_organica'=>'5100']),'Unidad');
verificarConsulta(!ConsultasLogisticaModel::permitido($pedido,['permiso_ordenes'=>1,'alcance_ordenes'=>'UNIDAD','unidad_organica'=>'0000']),'Unidad vacía');
verificarConsulta(!ConsultasLogisticaModel::permitido($pedido,['permiso_ordenes'=>0,'alcance_ordenes'=>'GLOBAL']),'Permiso de acceso');
verificarConsulta(ConsultasLogisticaModel::recurso(['TIPOFAMI'=>'S','GRUPO'=>'1','SUBGRUPO'=>'2','SEC_SERV'=>'3'])==='S|1|2|3','Clave de servicios');
if(in_array('--avalon',$argv,true)) {
 $m=new ConsultasLogisticaModel();
 $a=['permiso_ordenes'=>1,'alcance_ordenes'=>'GLOBAL','permiso_ordenes_montos'=>0,'permiso_ordenes_detalle'=>0];
 $r=$m->historial('proveedores','20544637747',$a,'2020',1);
 verificarConsulta($r['total']>0,'Compra real vinculada entre meses distintos');
 $f=$r['filas'][0];
 foreach($r['filas'] as $fila) foreach($fila['ordenes'] as $o) verificarConsulta(!isset($o['importe'])&&!isset($o['url']),'Ocultar monto y acceso a detalle');
 foreach(['almacen'=>$f['recurso']['codigo'],'cuentas'=>$f['detalle']['CTA_CON']] as $modo=>$codigo) {
  $h=$m->historial($modo,$codigo,$a,'2020',1);
  verificarConsulta($h['total']>0,'Historial por '.$modo);
  foreach($h['filas'] as $row) verificarConsulta(($modo==='almacen'?$row['recurso']['codigo']:$row['detalle']['CTA_CON'])===$codigo,'Filtro exacto '.$modo);
 }
 verificarConsulta(ProveedorService::resolver('20544637747')['nombre']!=='Nombre no disponible','Catálogo de proveedores');
}
echo "OK: alcance, identidad, recursos, vínculos y filtros.\n";
