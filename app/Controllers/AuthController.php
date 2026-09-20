<?php
declare(strict_types=1);
final class AuthController
{
    public function login(): void { require APP_ROOT . '/app/Views/login.php'; }
    public function auth(): void
    {
        try { validarCsrf(); $model=new AuthModel(); $user = $model->login(trim((string)($_POST['usuario'] ?? '')), (string)($_POST['clave'] ?? '')); if (!$user) throw new DomainException('Usuario o contraseña no válidos.'); session_regenerate_id(true); $token=bin2hex(random_bytes(32)); $model->registrarSesion((int)$user['id'],$token); $user['sesion_token']=$token; $_SESSION['usuario'] = $user; header('Location: ' . url('seguimiento')); exit; }
        catch (DomainException $error) { flash('error', $error->getMessage()); header('Location: ' . url('login')); exit; }
    }
    public function logout(): void { $user=usuario(); if ($user && !empty($user['sesion_token'])) (new AuthModel())->cerrarSesion((int)$user['id'],(string)$user['sesion_token']); $_SESSION = []; session_destroy(); header('Location: ' . url('login')); exit; }
}
