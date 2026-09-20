<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/app.php';
require APP_ROOT . '/app/Helpers.php';
require APP_ROOT . '/config/database.php';
$sessionPath = APP_ROOT . '/storage/sessions';
if (!is_dir($sessionPath)) mkdir($sessionPath, 0775, true);
session_save_path($sessionPath);
session_name('avalon_seguimiento');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$route = $_GET['r'] ?? (usuario() ? 'seguimiento' : 'login');
$routes = ['login' => ['AuthController', 'login'], 'auth' => ['AuthController', 'auth'], 'logout' => ['AuthController', 'logout'], 'seguimiento' => ['SeguimientoController', 'index'], 'notas' => ['NotasController', 'index'], 'exportar_notas' => ['NotasController', 'exportar'], 'presupuesto' => ['PresupuestoController', 'index'], 'presupuesto_responsabilidades' => ['PresupuestoController', 'responsabilidades'], 'guardar_responsabilidades' => ['PresupuestoController', 'guardarResponsabilidades'], 'sincronizar_seguimiento' => ['NotasController', 'sincronizar'], 'estado_sincronizacion' => ['EstadoSincronizacionController', 'index'], 'usuarios' => ['UsuariosController', 'index'], 'sincronizar_usuarios' => ['UsuariosController', 'sincronizar'], 'guardar_usuario' => ['UsuariosController', 'guardar']];
if (!isset($routes[$route])) {
    http_response_code(404);
    exit('Ruta no encontrada');
}
[$controller, $method] = $routes[$route];
(new $controller())->$method();


