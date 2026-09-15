<?php
declare(strict_types=1);
final class UsuariosController
{
    private function admin(): void { requiereLogin(); if ((usuario()['rol'] ?? '') !== 'ADMIN') { http_response_code(403); exit('Acceso no autorizado.'); } }
    public function index(): void { $this->admin(); $usuarios=(new UsuarioModel())->listar(); require APP_ROOT . '/app/Views/usuarios.php'; }
    public function sincronizar(): void { $this->admin(); validarCsrf(); $cantidad=(new UsuarioModel())->sincronizar(); flash('ok', $cantidad . ' usuarios de Avalon sincronizados. Configure una contraseña local para habilitar el acceso.'); header('Location: '.url('usuarios')); exit; }
    public function guardar(): void { $this->admin(); validarCsrf(); (new UsuarioModel())->guardarAcceso((int)$_POST['id'],(string)$_POST['clave'],(string)$_POST['alcance'],isset($_POST['activo'])); flash('ok','Acceso actualizado.'); header('Location: '.url('usuarios')); exit; }
}
