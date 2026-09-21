<?php
declare(strict_types=1);
final class UsuariosController
{
    private function admin(): void { requiereLogin(); if (!tienePermiso('usuarios')) { http_response_code(403); exit('Acceso no autorizado.'); } }
    public function index(): void { $this->admin(); $usuarios=(new UsuarioModel())->listar(); require APP_ROOT . '/app/Views/usuarios.php'; }
    public function sincronizar(): void {
        $this->admin(); validarCsrf();
        liberarSesion();
        $control = new SincronizacionControlService();
        if (!$control->iniciar(usuario() ?? [], 'usuarios', 'Usuarios y perfiles')) {
            reanudarSesion();
            flash('error', 'Ya hay una actualización en proceso. Los botones se habilitarán cuando finalice.');
            header('Location: '.url('usuarios')); exit;
        }
        try {
            $cantidad=(new UsuarioModel())->sincronizar();
            $mensaje=$cantidad . ' usuarios de Avalon sincronizados.';
            $control->finalizar(true,$mensaje);
            reanudarSesion();
            flash('ok', $mensaje . ' Configure una contraseña local para habilitar el acceso.');
        } catch (Throwable $e) {
            $control->finalizar(false,$e->getMessage());
            reanudarSesion();
            flash('error','No se pudo sincronizar usuarios: '.$e->getMessage());
        }
        header('Location: '.url('usuarios')); exit;
    }
    public function guardar(): void { $this->admin(); validarCsrf(); (new UsuarioModel())->guardarAcceso((int)$_POST['id'],(string)($_POST['clave']??''),$_POST); flash('ok','Perfil, permisos y alcances actualizados.'); header('Location: '.url('usuarios')); exit; }
}
