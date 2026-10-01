<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../config/app.php';
require APP_ROOT.'/config/database.php';
$errors=0;
foreach(['pdo_mysql','mbstring','iconv','json','zlib'] as $extension) {
    $ok=extension_loaded($extension); echo ($ok?'OK ':'FALTA ').$extension.PHP_EOL; if(!$ok) $errors++;
}
foreach([AVALON_LOGISTICA_DATA,AVALON_PLANIF_DATA,AVALON_CONTAB_DATA,AVALON_FINANZAS_DATA] as $path) {
    $ok=is_dir($path)&&is_readable($path); echo ($ok?'OK ':'FALTA ').$path.PHP_EOL; if(!$ok) $errors++;
}
foreach(['cache','sessions'] as $dir) {
    $path=APP_ROOT.'/storage/'.$dir; $ok=is_dir($path)&&is_writable($path);
    echo ($ok?'OK ':'FALTA ').'escritura '.$path.PHP_EOL; if(!$ok) $errors++;
}
try { db()->query('SELECT 1 FROM usuarios LIMIT 1'); echo "OK base instalada\n"; }
catch(Throwable $e) { echo "FALTA conexion o estructura de base. Revise config/local.php e instalacion.\n"; $errors++; }
exit($errors?1:0);
