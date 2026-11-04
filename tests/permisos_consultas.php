<?php
declare(strict_types=1);
require __DIR__.'/../config/app.php';require APP_ROOT.'/app/Helpers.php';
function comprobarConsulta(bool $ok,string $texto): void {if(!$ok)throw new RuntimeException($texto);}
$viejo=['permiso_ordenes'=>1,'alcance_ordenes'=>'PROPIO','permiso_ordenes_montos'=>1];
$p=RolModel::permisos($viejo);
foreach(['cuentas','proveedores'] as $modulo){
 comprobarConsulta($p['permiso_'.$modulo]===1 && $p['alcance_'.$modulo]==='PROPIO' && $p['permiso_'.$modulo.'_montos']===1,'Conservar acceso previo');
 $guardado=RolModel::permisos($viejo,true);
 comprobarConsulta($guardado['permiso_'.$modulo]===0,'Checkbox desactivado');
 comprobarConsulta(RolModel::permisos($guardado)['permiso_'.$modulo]===0,'No reactivar al leer');
 $independiente=RolModel::permisos(['permiso_'.$modulo=>1,'alcance_'.$modulo=>'UNIDAD'],true);
 $a=array_merge($independiente,['unidad_organica'=>'5100']);
 comprobarConsulta(ConsultasLogisticaModel::permitido(['CODUNIORG'=>'5100'],$a,$modulo),'Acceso independiente de Órdenes');
 comprobarConsulta(!ConsultasLogisticaModel::permitido(['CODUNIORG'=>'5200'],$a,$modulo),'No cruzar unidades');
 comprobarConsulta(!ConsultasLogisticaModel::permitido(['CODUNIORG'=>'5100'],$guardado,$modulo),'Revocar consulta');
 $a=array_merge($a,['alcance_'.$modulo=>'PROPIO','avalon_codigo'=>'00123']);
 comprobarConsulta(ConsultasLogisticaModel::permitido(['USRCODIGO'=>'123'],$a,$modulo),'Propio');
 comprobarConsulta(!ConsultasLogisticaModel::permitido(['USRCODIGO'=>'124'],$a,$modulo),'Otro usuario');
}
echo "OK: permisos independientes, compatibilidad, revocación y alcances.\n";
