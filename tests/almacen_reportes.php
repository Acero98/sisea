<?php
declare(strict_types=1);
require __DIR__.'/../config/app.php';
function verificarAlmacen(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
$c=new DateTimeImmutable('2026-11-03');
foreach(['20260803'=>'reciente','20260802'=>'3','20260503'=>'3','20260502'=>'6','20251103'=>'6','20251102'=>'12','20241102'=>'24','20231102'=>'36','20201102'=>'72'] as $f=>$tramo) verificarAlmacen(AlmacenReportesModel::tramo(AlmacenReportesModel::fecha((string)$f),$c)===$tramo,'Límite '.$f);
verificarAlmacen(AlmacenReportesModel::limite(new DateTimeImmutable('2024-05-31'),3)->format('Y-m-d')==='2024-02-29','Mes y año bisiesto');
verificarAlmacen(AlmacenReportesModel::fecha('20260230')===null,'Fecha inexistente');
verificarAlmacen(AlmacenReportesModel::tramo(null,$c)==='desconocido','Sin fecha');
verificarAlmacen(AlmacenReportesModel::tramo(new DateTimeImmutable('2027-01-01'),$c)==='desconocido','Fecha futura');
verificarAlmacen(AlmacenReportesModel::celdaCsv('=SUM(A1)')==="'=SUM(A1)",'Fórmula CSV');
verificarAlmacen(RolModel::permisos(['permiso_usuarios'=>1])['permiso_almacen_reportes']===1,'Compatibilidad administrador');
verificarAlmacen(RolModel::permisos(['permiso_usuarios'=>1],true)['permiso_almacen_reportes']===0,'Desactivar permiso explícitamente');
verificarAlmacen(RolModel::permisos(['permiso_ordenes'=>1])['permiso_almacen_reportes']===0,'Sin ampliación de acceso');
if(in_array('--avalon',$argv,true)){
 $m=new AlmacenReportesModel();$datos=$m->cargar(AlmacenReportesModel::filtros(['reporte'=>'inactividad','minimo'=>'todos']));
 verificarAlmacen(array_sum($datos['tramos'])===$datos['total'],'Tramos sin doble conteo');
 verificarAlmacen(count($datos['filas'])===$datos['total'],'Todos los artículos');
 echo json_encode(['articulos'=>$datos['total'],'altas_mes'=>$datos['altas'],'con_ingresos'=>$datos['ingresados'],'tramos'=>$datos['tramos']],JSON_UNESCAPED_UNICODE).PHP_EOL;
}
echo "OK: límites, fechas, permisos, CSV y conteos.\n";
