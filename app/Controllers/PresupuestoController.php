<?php
declare(strict_types=1);
final class PresupuestoController
{
 private function unidad(): string { requiereLogin(); $user=usuario()??[]; $global=alcanceModulo('presupuesto',$user)==='GLOBAL'; $unidad=trim((string)($global?($_REQUEST['unidad']??($user['unidad_organica']??'')):($user['unidad_organica']??''))); if($unidad==='0000')$unidad=''; return preg_match('/^\d{4}$/',$unidad)?$unidad:''; }
 public function index():void
 {
  requiereLogin();
  if(!tienePermiso('presupuesto')){http_response_code(403);exit('No tiene acceso al módulo de Presupuesto.');}
  $puedeVerListadoOrdenes=tienePermiso('ordenes');
  $unidad=$this->unidad();$esAdmin=alcanceModulo('presupuesto')==='GLOBAL';
  $tipo=(string)($_GET['tipo']??'todos');
  $filtro=['actividad'=>trim((string)($_GET['actividad']??'')),'cuenta'=>trim((string)($_GET['cuenta']??'')),'tipo'=>in_array($tipo,['todos','operativo','inversion'],true)?$tipo:'todos'];
  $global=$esAdmin&&$unidad==='';
  $consultar=(($_GET['consultar']??'')==='1')&&($unidad!==''||($global&&($filtro['actividad']!==''||$filtro['cuenta']!=='')));
  $model=new PresupuestoModel();
  $opcionesFiltros=$model->opcionesFiltros($unidad,$esAdmin);
  $actividadNumerica=preg_replace('/\D/','',$filtro['actividad']);
  if(strlen($actividadNumerica)===4)$actividadNumerica='0101'.$actividadNumerica;
  if(strlen($actividadNumerica)===8)$filtro['actividad']=implode('.',str_split($actividadNumerica,2));
  $datos=$consultar?$model->consultar($unidad,$filtro,$global):null;
  require APP_ROOT.'/app/Views/presupuesto.php';
 }
 public function ordenesComprometidas():void
 {
  requiereLogin();if(!tienePermiso('presupuesto')){http_response_code(403);exit;}
  header('Content-Type: application/json; charset=utf-8');
  try{
   $unidad=$this->unidad();$esGlobal=alcanceModulo('presupuesto')==='GLOBAL'&&$unidad==='';
   $actividad=trim((string)($_GET['actividad']??''));$cuenta=trim((string)($_GET['cuenta']??''));$inversion=trim((string)($_GET['inversion']??''));$periodo=(string)($_GET['periodo']??'');
   if(!preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{2}$/',$actividad)||!preg_match('/^\d{1,20}$/',$cuenta)||!preg_match('/^[a-zA-Z0-9._-]{0,40}$/',$inversion)){http_response_code(400);echo json_encode(['error'=>'La cuenta presupuestal seleccionada no es válida.']);return;}
   $tipo=(string)($_GET['tipo']??'todos');$filtro=['actividad'=>$actividad,'cuenta'=>$cuenta,'tipo'=>in_array($tipo,['todos','operativo','inversion'],true)?$tipo:'todos'];
   $disponibles=(new PresupuestoModel())->consultar($unidad,$filtro,$esGlobal)['items'];$permitido=false;
   foreach($disponibles as$fila)if($fila['actividad_presupuestal']===$actividad&&$fila['cuenta']===$cuenta&&$fila['cod_inv']===$inversion){$permitido=true;break;}
   if(!$permitido){http_response_code(404);echo json_encode(['error'=>'La cuenta ya no está disponible para tu alcance.']);return;}
   echo json_encode((new PresupuestoModel())->ordenesComprometidas($actividad,$cuenta,$inversion,$periodo),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
  }catch(Throwable $error){error_log('[SISEA presupuesto] '.$error->getMessage());http_response_code(500);echo json_encode(['error'=>'No se pudieron consultar las órdenes comprometidas.']);}
 }
 public function responsabilidades():void{requiereLogin();if(!tienePermiso('responsabilidades')){http_response_code(403);exit('No tiene permiso para configurar responsabilidades presupuestales.');}$unidad=$this->unidad();$esAdmin=alcanceModulo('presupuesto')==='GLOBAL';$fuentes=$unidad!==''?(new PresupuestoModel())->configuracion($unidad):[];require APP_ROOT.'/app/Views/presupuesto_responsabilidades.php';}
 public function guardarResponsabilidades():void{requiereLogin();if(!tienePermiso('responsabilidades')){http_response_code(403);exit('Acceso no autorizado.');}validarCsrf();$unidad=$this->unidad();(new PresupuestoModel())->guardarConfiguracion($unidad,(array)($_POST['clasificacion']??[]));flash('ok','Responsabilidades presupuestales actualizadas.');header('Location: '.url('presupuesto_responsabilidades'));exit;}
}
