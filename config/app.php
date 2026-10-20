<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$settings = require __DIR__.'/settings.php';
define('APP_URL', rtrim($settings['app_url'] ?? '/sisea/public/', '/').'/');
define('AVALON_LOGISTICA_DATA', rtrim($settings['avalon_logistica'] ?? 'C:/Avalon/Logistica/data', '/\\'));
define('AVALON_PLANIF_DATA', rtrim($settings['avalon_planif'] ?? 'C:/Avalon/Planif/data', '/\\'));
define('AVALON_CONTAB_DATA', rtrim($settings['avalon_contab'] ?? 'C:/Avalon/contab/data', '/\\'));
define('AVALON_FINANZAS_DATA', rtrim($settings['avalon_finanzas'] ?? 'C:/Avalon/Finanzas/data', '/\\'));
define('AVALON_PATRIMONIO_DATA', rtrim($settings['avalon_patrimonio'] ?? 'C:/Avalon/Patrimonio/Data', '/\\'));
date_default_timezone_set('America/Lima');

spl_autoload_register(function ($class) {
    foreach (['Controllers', 'Models', 'Services'] as $folder) {
        $file = APP_ROOT . '/app/' . $folder . '/' . $class . '.php';
        if (is_file($file)) require_once $file;
    }
});
