<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../config/database.php';
$options=getopt('', ['help','output:']);
if(isset($options['help'])) { echo "php bin/exportar_configuracion.php [--output=ruta.json.gz]\n"; exit; }
try {
    $path=$options['output']??__DIR__.'/../storage/backups/config-'.date('Ymd-His').'.json.gz';
    if(!is_dir(dirname($path))) mkdir(dirname($path),0770,true);
    $pdo=db(); $pdo->beginTransaction(); $tables=[];
    foreach(['usuarios','presupuesto_responsabilidades'] as $table) {
        $tables[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll();
        foreach($tables[$table] as &$row) unset($row['sesion_token'],$row['sesion_iniciada_en']);
        unset($row);
    }
    $pdo->commit();
    $bytes=gzencode(json_encode(['format'=>'sisea-config-v1','tables'=>$tables],JSON_THROW_ON_ERROR),9);
    $h=fopen($path,'xb'); if(!$h) throw new RuntimeException('No se crea ni sobrescribe el archivo.');
    try { if(fwrite($h,$bytes)!==strlen($bytes)) throw new RuntimeException('Escritura incompleta.'); } finally { fclose($h); }
    echo "Creado: $path\nContiene cuentas y hashes de claves. Transportar en privado. No incluye historial ni sesiones.\n";
} catch(Throwable $e) { if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
