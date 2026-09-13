<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_URL', '/avalon/public/');
define('AVALON_LOGISTICA_DATA', getenv('AVALON_LOGISTICA_DATA') ?: 'C:/Avalon/Logistica/data');
define('AVALON_PLANIF_DATA', getenv('AVALON_PLANIF_DATA') ?: 'C:/Avalon/Planif/data');
date_default_timezone_set('America/Lima');

spl_autoload_register(function ($class) {
    foreach (['Controllers', 'Models', 'Services'] as $folder) {
        $file = APP_ROOT . '/app/' . $folder . '/' . $class . '.php';
        if (is_file($file)) require_once $file;
    }
});
