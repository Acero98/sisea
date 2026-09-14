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
$routes = ['login' => ['AuthController', 'login'], 'auth' => ['AuthController', 'auth'], 'logout' => ['AuthController', 'logout'], 'seguimiento' => ['SeguimientoController', 'index']];
if (!isset($routes[$route])) {
    http_response_code(404);
    exit('Ruta no encontrada');
}
[$controller, $method] = $routes[$route];
(new $controller())->$method();
