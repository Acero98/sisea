<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/app.php';require APP_ROOT.'/config/database.php';
try{$n=(new SeguimientoSnapshotService())->sincronizar();echo date('c')." - {$n} notas sincronizadas desde Avalon (solo lectura).".PHP_EOL;}catch(Throwable $e){fwrite(STDERR,date('c')." - ERROR: ".$e->getMessage().PHP_EOL);exit(1);}
