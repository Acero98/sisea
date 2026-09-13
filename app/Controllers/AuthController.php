<?php
declare(strict_types=1);
final class AuthController
{
    public function login(): void { require APP_ROOT . '/app/Views/login.php'; }
    public function auth(): void
    {
        try { validarCsrf(); $user = (new AuthModel())->login(trim((string)($_POST['usuario'] ?? '')), (string)($_POST['clave'] ?? '')); if (!$user) throw new DomainException('Usuario o contraseña no válidos.'); session_regenerate_id(true); $_SESSION['usuario'] = $user; header('Location: ' . url('seguimiento')); exit; }
        catch (DomainException $error) { flash('error', $error->getMessage()); header('Location: ' . url('login')); exit; }
    }
    public function logout(): void { $_SESSION = []; session_destroy(); header('Location: ' . url('login')); exit; }
}
