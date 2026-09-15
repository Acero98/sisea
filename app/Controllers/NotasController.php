<?php
declare(strict_types=1);
final class NotasController
{
    public function index():void
    {
        requiereLogin();$alcance=($_GET['vista']??'mis')==='unidad'?'unidad':'mis';$busqueda=trim((string)($_GET['q']??''));$pagina=max(1,(int)($_GET['pagina']??1));$porPagina=in_array((int)($_GET['por_pagina']??10),[10,25,50],true)?(int)($_GET['por_pagina']??10):10;$acceso=$this->acceso();$modelo=new NotasModel();$resultado=$modelo->listar($acceso,$alcance,$busqueda,$pagina,$porPagina);$notas=$resultado['items'];$ultimaSincronizacion=$modelo->ultimaSincronizacion();require APP_ROOT.'/app/Views/notas.php';
    }
    public function sincronizar():void
    {
        requiereLogin();validarCsrf();$acceso=$this->acceso();$tipo=($_POST['alcance']??'')==='unidad'?'unidad':'mis';$valor=$tipo==='unidad'?trim((string)($acceso['unidad_organica']??'')):trim((string)($acceso['avalon_codigo']??''));
        if($valor===''||($tipo==='unidad'&&$valor==='0000')){flash('error','No se puede determinar el alcance de actualización para este usuario.');header('Location: '.url('notas',['vista'=>$tipo]));exit;}
        $lock='avalon-seguimiento-sincronizacion';$stmt=db()->prepare('SELECT GET_LOCK(:lock_name,0)');$stmt->execute(['lock_name'=>$lock]);if((int)$stmt->fetchColumn()!==1){flash('error','Ya hay una actualización en proceso. Espere unos minutos e inténtelo nuevamente.');header('Location: '.url('notas',['vista'=>$tipo]));exit;}
        try{$cantidad=(new SeguimientoSnapshotService())->sincronizar(['tipo'=>$tipo,'valor'=>$valor]);flash('ok',$cantidad.' notas de '.($tipo==='mis'?'su usuario':'su unidad').' fueron actualizadas desde Avalon.');}catch(Throwable $e){flash('error','No se pudo actualizar el seguimiento: '.$e->getMessage());}finally{db()->prepare('SELECT RELEASE_LOCK(:lock_name)')->execute(['lock_name'=>$lock]);}header('Location: '.url('notas',['vista'=>$tipo]));exit;
    }
    private function acceso():array{$a=usuario()??[];if(empty($a['avalon_codigo'])&&!empty($a['id'])){$s=db()->prepare('SELECT avalon_codigo,unidad_organica,rol,alcance FROM usuarios WHERE id=:id');$s->execute(['id'=>$a['id']]);$a=array_merge($a,$s->fetch()?:[]);$_SESSION['usuario']=$a;}return$a;}
}

