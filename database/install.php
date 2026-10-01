<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options=getopt('', ['help','portable:']);
if(isset($options['help'])) { echo "php database/install.php [--portable=archivo.json.gz]\nSolo bases vacias.\n"; exit; }
try {
    $tables=null;
    if(isset($options['portable'])) {
        $h=gzopen($options['portable'],'rb'); if(!$h) throw new RuntimeException('No se puede leer el traslado.');
        $json='';
        while(!gzeof($h)) { $part=gzread($h,65536); if($part===false) throw new RuntimeException('Error de lectura.'); $json.=$part; if(strlen($json)>67108864) throw new RuntimeException('Traslado demasiado grande.'); }
        gzclose($h);
        $data=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        if(($data['format']??'')!=='sisea-config-v1' || array_keys($data['tables']??[])!==['usuarios','presupuesto_responsabilidades']) throw new RuntimeException('Formato incompatible.');
        $tables=$data['tables']; $admin=false;
        foreach($tables as $rows) { if(!is_array($rows)) throw new RuntimeException('Tabla invalida.'); foreach($rows as $row) if(!is_array($row)) throw new RuntimeException('Fila invalida.'); }
        foreach($tables['usuarios'] as $row) if(!empty($row['activo']) && !empty($row['permiso_usuarios']) && !empty($row['clave_hash'])) $admin=true;
        if(!$admin) throw new RuntimeException('Falta un administrador activo con clave.');
    }
    $c=require __DIR__.'/../config/settings.php'; $name=$c['database'];
    if(!preg_match('/\A[A-Za-z0-9_]+\z/',$name)) throw new RuntimeException('Nombre de base invalido.');
    $password=getenv('SISEA_ADMIN_PASSWORD') ?: bin2hex(random_bytes(12));
    if(!$tables && strlen($password)<12) throw new RuntimeException('Clave minima: 12 caracteres.');
    $pdo=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4',$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$name`");
    if($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('Base no vacia: no se modifico. Use una base nueva.');
    $pdo->exec(file_get_contents(__DIR__.'/schema.sql'));
    $pdo->beginTransaction();
    if($tables!==null) {
        foreach($tables as $table=>$rows) {
            $allowed=$pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            foreach($rows as $row) {
                unset($row['sesion_token'],$row['sesion_iniciada_en']);
                if(!$row || array_diff(array_keys($row),$allowed)) throw new RuntimeException('Columnas incompatibles.');
                $pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
            }
        }
    } else {
        $pdo->prepare("INSERT INTO usuarios(usuario,nombre,clave_hash,rol,perfil_portal,rol_acceso,alcance,alcance_seguimiento,alcance_presupuesto,permiso_presupuesto,permiso_responsabilidades,permiso_usuarios) VALUES('admin','Administrador',?,'ADMIN','ADMIN','ADMIN','GLOBAL','GLOBAL','GLOBAL',1,1,1)")->execute([password_hash($password,PASSWORD_DEFAULT)]);
    }
    $pdo->commit();
    foreach(['cache','sessions','backups'] as $dir) { $path=__DIR__.'/../storage/'.$dir; if(!is_dir($path) && !mkdir($path,0770,true)) throw new RuntimeException('No se pudo crear '.$path); }
    echo "Instalacion completada en $name. Ejecute la sincronizacion inicial.\n";
    if($tables===null) echo "Usuario: admin\nClave inicial: $password\n";
} catch(Throwable $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'ERROR: '.$e->getMessage()."\nSi quedo estructura parcial, use otra base vacia. No se borran datos automaticamente.\n"); exit(1);
}
