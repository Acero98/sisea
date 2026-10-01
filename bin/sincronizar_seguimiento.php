<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/config/app.php';
require APP_ROOT.'/config/database.php';
$locked=false;
try {
    $control=new SincronizacionControlService(); $control->instalar();
    $locked=$control->iniciar(['nombre'=>'Terminal'],'GLOBAL','Sincronizacion desde terminal');
    if(!$locked) throw new RuntimeException('Ya existe una sincronizacion en curso.');
    echo date('c')." - Inicio. La primera carga puede tardar; no cierre esta terminal.\n";
    $n=(new SeguimientoSnapshotService())->sincronizar([],null,static function(string $mensaje): void { echo date('c').' - '.$mensaje.PHP_EOL; });
    $control->finalizar(true,"$n notas sincronizadas"); $locked=false;
    echo date('c')." - $n notas sincronizadas desde Avalon (solo lectura).\n";
} catch(Throwable $e) {
    if($locked) { try { $control->finalizar(false,$e->getMessage()); } catch(Throwable $ignored) {} }
    fwrite(STDERR,date('c').' - ERROR: '.$e->getMessage()."\n"); exit(1);
}
