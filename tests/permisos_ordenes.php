<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/app.php';
require APP_ROOT.'/config/database.php';
require APP_ROOT.'/app/Helpers.php';

function checkAccess(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException($label);
    echo 'OK '.$label.PHP_EOL;
}
// All fixtures and writes are isolated in temporary tables on this connection.
$pdo=db();
foreach(['usuarios','seguimiento_notas','seguimiento_documentos'] as $table){
    $ddl=$pdo->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];
    $pdo->exec(preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$ddl));
}
UsuarioModel::instalarPermisosDocumentales();
$pdo->exec("INSERT INTO seguimiento_notas(nro_pedido,unidad_organica,usuario_codigo,etapa,sincronizado_en) VALUES ('T000001','4140','00014','Pedido',NOW()),('T000002','4140','00015','Pedido',NOW()),('T000003','4110','00016','Pedido',NOW())");
$pdo->exec("INSERT INTO seguimiento_documentos(nro_pedido,tipo,numero,estado,fecha,sincronizado_en) VALUES ('T000001','orden_compra','TEST1','','2026-01-01',NOW()),('T000002','orden_servicio','TEST2','','2026-01-02',NOW()),('T000003','orden_compra','TEST3','','2026-01-03',NOW())");
$model=new OrdenesModel();
$mapProperty=new ReflectionProperty(OrdenesModel::class,'proveedoresOrden');$mapProperty->setAccessible(true);
$mapProperty->setValue($model,['orden_compra|TEST1'=>'20544637747','orden_servicio|TEST2'=>'TEST-PROV-2','orden_compra|TEST3'=>'TEST-PROV-3']);
$user=['rol'=>'CONSULTA','permiso_ordenes'=>1,'alcance_ordenes'=>'PROPIO','unidad_organica'=>'4140','avalon_codigo'=>'14','permiso_ordenes_montos'=>0,'permiso_ordenes_detalle'=>0,'permiso_seguimiento'=>0,'alcance_seguimiento'=>'GLOBAL'];
$own=$model->listar($user,[]);
checkAccess(count($own['data'])===1&&$own['data'][0]['numero']==='TEST1','usuario propio, código normalizado y seguimiento independiente');
checkAccess(!isset($own['data'][0]['importe'])&&!isset($own['data'][0]['detalle_url']),'montos y detalle ausentes cuando no están permitidos');
checkAccess($model->listar(array_merge($user,['alcance_ordenes'=>'UNIDAD']),[])['recordsTotal']===2,'alcance por unidad');
checkAccess($model->listar(array_merge($user,['alcance_ordenes'=>'GLOBAL','unidad_organica'=>'']),[])['recordsTotal']===3,'alcance global sin unidad');
checkAccess($model->listar(array_merge($user,['permiso_ordenes'=>0,'permiso_seguimiento'=>1]),[])['recordsTotal']===0,'seguimiento no concede órdenes');
checkAccess($model->listar(array_merge($user,['avalon_codigo'=>'']),[])['recordsTotal']===0,'propio sin código deniega registros');
checkAccess($model->listar(array_merge($user,['alcance_ordenes'=>'UNIDAD','unidad_organica'=>'0000']),[])['recordsTotal']===0,'unidad no asignada deniega registros');
checkAccess($model->listar($user,['ordenes'=>['orden_compra|TEST3']])['recordsTotal']===0,'filtro directo de órdenes no evade alcance propio');
checkAccess($model->listar($user,['unidad'=>'4110'])['recordsTotal']===0,'unidad seleccionada no evade alcance');
checkAccess($model->listar($user,['proveedor'=>'TEST-PROV-3'])['recordsTotal']===0,'proveedor no evade alcance');
checkAccess($model->listar($user,['proveedor'=>'20544637747','unidad'=>'4140'])['recordsTotal']===1,'filtros combinados de unidad y proveedor');
checkAccess($model->listar($user,['search'=>['value'=>'20544637747']])['recordsFiltered']===1,'búsqueda general por RUC');
checkAccess($own['data'][0]['proveedor_ruc']==='20544637747' && $own['data'][0]['proveedor_nombre']!=='Nombre no disponible','nombre y RUC sin permiso de montos');
$opciones=$model->opcionesFiltros($user);
checkAccess(count($opciones['unidades'])===1 && isset($opciones['unidades']['4140']) && count($opciones['proveedores'])===1,'opciones limitadas al alcance propio');
$withDetail=$model->listar(array_merge($user,['permiso_ordenes_detalle'=>1]),[]);
checkAccess(strpos($withDetail['data'][0]['detalle_url'],'origen=ordenes')!==false,'enlace de detalle utiliza autorización de órdenes');
foreach([['T000002','TEST2'],['T000003','TEST3'],['T000001','TEST3']] as [$pedido,$numero]){
    try{(new SeguimientoModel())->detalleDocumento($pedido,'orden_compra',$numero,array_merge($user,['alcance'=>'PROPIO']),false);throw new LogicException('Detalle fuera de alcance admitido');}
    catch(RuntimeException $e){checkAccess(true,'detalle denegado para '.$pedido.' / '.$numero);}
}
$seguimiento=new SeguimientoModel();$propio=array_merge($user,['alcance'=>'PROPIO','alcance_seguimiento'=>'PROPIO']);
checkAccess(count($seguimiento->buscarCoincidencias('TEST1','orden_compra',$propio))===1,'seguimiento encuentra documentos propios');
checkAccess(count($seguimiento->buscarCoincidencias('TEST2','orden_servicio',$propio))===0,'seguimiento no revela coincidencias de otro usuario de la unidad');
$reflection=new ReflectionMethod(SeguimientoModel::class,'puedeVerPedido');$reflection->setAccessible(true);
checkAccess($reflection->invoke($seguimiento,['USRCODIGO'=>'00014','CODUNIORG'=>'4140'],$propio),'pedido propio normaliza código Avalon');
checkAccess(!$reflection->invoke($seguimiento,['USRCODIGO'=>'00015','CODUNIORG'=>'4140'],$propio),'pedido ajeno se deniega aunque sea de la misma unidad');
$pdo->exec("UPDATE seguimiento_notas SET tipo_pedido='S',prioridad='1',estado_logistica='P'");
$notas=(new NotasModel())->listar($propio,'unidad','',1,10);
checkAccess($notas['total']===1,'Mis notas no permite ampliar un rol propio a unidad');
checkAccess(count((new NotasModel())->exportar($propio,'unidad',''))===1,'exportación respeta el alcance propio del rol');
$modeloNotas=new NotasModel();
$globalNotas=array_merge($propio,['alcance_seguimiento'=>'GLOBAL']);
checkAccess($modeloNotas->listar($globalNotas,'unidad','',1,10,'','','4140','00015')['total']===1,'notas combina unidad y registrado por');
checkAccess($modeloNotas->listar($propio,'unidad','',1,10,'','','4110','00016')['total']===0,'filtros de notas no amplían alcance propio');
checkAccess(count($modeloNotas->exportar($globalNotas,'unidad','','','','4140','00015'))===1,'exportación de notas respeta ambos filtros');
$opcionesNotas=$modeloNotas->opcionesFiltros($propio,'unidad');
checkAccess(count($opcionesNotas['usuarios'])===1&&isset($opcionesNotas['usuarios']['00014']),'selector de registrados limitado al alcance propio');
foreach(array_merge(array_values($opcionesNotas['usuarios']),array_values($opcionesNotas['unidades'])) as $etiqueta){
    checkAccess(e($etiqueta)!==''&&preg_match('//u',$etiqueta)===1,'etiqueta de filtro válida y visible en HTML');
}
checkAccess($modeloNotas->listar($globalNotas,'unidad','',1,1,'','','','','nro_pedido','ASC')['items'][0]['NRO_PEDIDO']==='T000001','ordenamiento ascendente de notas antes de paginar');
checkAccess($modeloNotas->listar($globalNotas,'unidad','',2,1,'','','','','nro_pedido','ASC')['items'][0]['NRO_PEDIDO']==='T000002','segunda página de notas conserva ordenamiento');
echo "Pruebas completadas; no se modificaron cuentas ni registros reales.\n";
