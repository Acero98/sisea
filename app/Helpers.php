<?php
declare(strict_types=1);

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $route = '', array $params = []): string { return APP_URL . '?' . http_build_query(array_merge(['r' => $route], $params)); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function validarCsrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) throw new DomainException('La sesión del formulario venció. Actualice la página e inténtelo nuevamente.'); }
function usuario(): ?array { return $_SESSION['usuario'] ?? null; }
function rutaInicio(?array $cuenta = null): string {
    foreach(['seguimiento'=>'seguimiento','ordenes'=>'ordenes','patrimonio'=>'patrimonio','presupuesto'=>'presupuesto','presupuesto_responsabilidades'=>'responsabilidades','usuarios'=>'usuarios'] as$ruta=>$permiso)if(tienePermiso($permiso,$cuenta))return $ruta;
    return 'sin_acceso';
}
function tienePermiso(string $modulo, ?array $cuenta = null): bool {
    $cuenta = $cuenta ?? usuario() ?? [];
    $campo = 'permiso_' . $modulo;
    return array_key_exists($campo, $cuenta) ? (bool)$cuenta[$campo] : (($cuenta['rol'] ?? '') === 'ADMIN');
}
function alcanceModulo(string $modulo, ?array $cuenta = null): string {
    $cuenta = $cuenta ?? usuario() ?? [];
    return (string)($cuenta['alcance_' . $modulo] ?? $cuenta['alcance'] ?? 'UNIDAD');
}
function requiereLogin(): void {
    $actual=usuario();
    if (!$actual) { header('Location: ' . url('login')); exit; }
    $token=(string)($actual['sesion_token'] ?? '');
    if ($token === '') {
        $token=bin2hex(random_bytes(32));
        (new AuthModel())->registrarSesion((int)$actual['id'],$token);
        $datos=(new AuthModel())->datosSesion((int)$actual['id']);
        $_SESSION['usuario']=array_merge($datos??$actual,['sesion_token'=>$token]);
        return;
    }
    if (!(new AuthModel())->sesionVigente((int)$actual['id'],$token)) {
        setcookie('sisea_motivo_salida','sesion_reemplazada',time()+120,APP_URL,'',false,true);
        $_SESSION=[]; session_destroy();
        header('Location: '.url('login',['motivo'=>'sesion_reemplazada'])); exit;
    }
    $datos=(new AuthModel())->datosSesion((int)$actual['id']);
    if($datos)$_SESSION['usuario']=array_merge($datos,['sesion_token'=>$token]);
}
function flash(string $type, string $message): void { $_SESSION['flash'] = compact('type', 'message'); }
function takeFlash(): ?array { $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }
function liberarSesion(): void { if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); }
function reanudarSesion(): void { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); }
