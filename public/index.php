<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/app.php';
require APP_ROOT . '/app/Helpers.php';
require APP_ROOT . '/config/database.php';
set_exception_handler(static function (Throwable $error): void {
    error_log('[SISEA] ' . get_class($error) . ': ' . $error->getMessage());
    if (!headers_sent()) http_response_code($error instanceof PDOException ? 503 : 500);
    $retry = htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? '/'), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Servicio temporalmente no disponible | SISEA</title><style>:root{--navy:#102f52;--blue:#0875d1;--muted:#62758a;--line:#dce6ef}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:#f4f8fb;color:var(--navy);font:16px/1.5 Segoe UI,Arial,sans-serif;display:grid;place-items:center;padding:24px}main{width:min(610px,100%);background:#fff;border:1px solid var(--line);border-radius:18px;padding:42px;box-shadow:0 20px 60px #12345616;text-align:center}.icon{display:grid;place-items:center;margin:0 auto 20px;width:68px;height:68px;border-radius:18px;background:#e9f4ff;color:var(--blue);font-size:33px;font-weight:700}h1{font-size:28px;line-height:1.2;margin:0 0 12px}p{margin:0 auto;color:var(--muted);max-width:470px}.notice{margin:24px 0;padding:13px 16px;border-radius:10px;background:#f2f7fb;color:#3c5872;font-size:14px}.retry{display:inline-flex;align-items:center;justify-content:center;min-height:45px;padding:0 20px;border:0;border-radius:9px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700}.brand{margin-top:28px;color:#7b8c9d;font-size:13px}</style></head><body><main><div class="icon">!</div><h1>Servicio temporalmente no disponible</h1><p>No se pudo establecer comunicación con la base de datos de SISEA. Inténtelo nuevamente en unos minutos.</p><div class="notice">Si el inconveniente continúa, comuníquese con el administrador del sistema.</div><a class="retry" href="' . $retry . '">Reintentar</a><div class="brand">SISEA · Consulta de solo lectura</div></main></body></html>';
    exit;
});
$sessionPath = APP_ROOT . '/storage/sessions';
if (!is_dir($sessionPath)) mkdir($sessionPath, 0775, true);
session_save_path($sessionPath);
session_name('avalon_seguimiento');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$route = $_GET['r'] ?? (usuario() ? 'seguimiento' : 'login');
$routes = ['login' => ['AuthController', 'login'], 'auth' => ['AuthController', 'auth'], 'logout' => ['AuthController', 'logout'], 'seguimiento' => ['SeguimientoController', 'index'], 'detalle_documento' => ['SeguimientoController', 'detalleDocumento'], 'ordenes' => ['OrdenesController', 'index'], 'ordenes_datos' => ['OrdenesController', 'datos'], 'notas' => ['NotasController', 'index'], 'exportar_notas' => ['NotasController', 'exportar'], 'presupuesto' => ['PresupuestoController', 'index'], 'presupuesto_ordenes' => ['PresupuestoController', 'ordenesComprometidas'], 'presupuesto_responsabilidades' => ['PresupuestoController', 'responsabilidades'], 'guardar_responsabilidades' => ['PresupuestoController', 'guardarResponsabilidades'], 'sincronizar_seguimiento' => ['NotasController', 'sincronizar'], 'estado_sincronizacion' => ['EstadoSincronizacionController', 'index'], 'usuarios' => ['UsuariosController', 'index'], 'sincronizar_usuarios' => ['UsuariosController', 'sincronizar'], 'guardar_usuario' => ['UsuariosController', 'guardar']];
if (!isset($routes[$route])) {
    http_response_code(404);
    exit('Ruta no encontrada');
}
[$controller, $method] = $routes[$route];
(new $controller())->$method();


