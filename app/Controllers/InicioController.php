<?php
declare(strict_types=1);
final class InicioController
{
    public function index():void{
        requiereLogin();$ruta=rutaInicio();
        if($ruta!=='sin_acceso'){header('Location: '.url($ruta));exit;}
        $pageTitle='Sin módulos asignados';$pageDescription='Tu cuenta no tiene módulos habilitados en su rol.';$activeNav='';
        require APP_ROOT.'/app/Views/partials/header.php';
        echo '<section class="card empty"><h2>Solicita la revisión de tu acceso</h2><p>Contacta al administrador para que revise el rol asignado a tu cuenta.</p></section>';
        require APP_ROOT.'/app/Views/partials/footer.php';
    }
}
