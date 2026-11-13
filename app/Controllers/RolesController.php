<?php
declare(strict_types=1);
final class RolesController
{
    private function acceso():void { requiereLogin();if(!tienePermiso('usuarios')){http_response_code(403);exit('No tiene acceso a la administración de roles.');} }
    public function index():void { $this->acceso();$roles=(new RolModel())->listar();require APP_ROOT.'/app/Views/roles.php'; }
    public function guardar():void {
        $this->acceso();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}validarCsrf();
        try{(new RolModel())->guardar((int)($_POST['id']??0),$_POST);flash('ok','Rol y permisos actualizados. Los cambios se aplican a todas las cuentas asignadas.');}
        catch(DomainException $e){flash('error',$e->getMessage());}
        header('Location: '.url('roles'));exit;
    }
    public function estado():void {
        $this->acceso();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}validarCsrf();
        try{(new RolModel())->estado((int)($_POST['id']??0),($_POST['activo']??'')==='1');flash('ok','Estado del rol actualizado.');}
        catch(DomainException $e){flash('error',$e->getMessage());}
        header('Location: '.url('roles'));exit;
    }
}
