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
        $version=$data['format']??'';
        if(!in_array($version,['sisea-config-v1','sisea-config-v2'],true) || array_keys($data['tables']??[])!==($version==='sisea-config-v2'?['roles_acceso','usuarios','presupuesto_responsabilidades']:['usuarios','presupuesto_responsabilidades'])) throw new RuntimeException('Formato incompatible.');
        $tables=$data['tables']; $admin=false;
        foreach($tables as $rows) { if(!is_array($rows)) throw new RuntimeException('Tabla invalida.'); foreach($rows as $row) if(!is_array($row)) throw new RuntimeException('Fila invalida.'); }
        $rolesImportados=[];foreach($tables['roles_acceso']??[]as$r)$rolesImportados[$r['id']]=$r;
        foreach($tables['usuarios'] as $row){
            $permiso=!empty($row['permiso_usuarios']);
            if($version==='sisea-config-v2'){
                $rol=$rolesImportados[$row['rol_id']??0]??null;
                if(!$rol)throw new RuntimeException('Una cuenta referencia un rol inexistente.');
                $permiso=!empty($rol['activo'])&&!empty((json_decode($rol['permisos_json'],true)??[])['permiso_usuarios']);
            }
            if(!empty($row['activo'])&&$permiso&&!empty($row['clave_hash']))$admin=true;
        }
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
                if($table==='usuarios'&&($version??'')==='sisea-config-v1')unset($row['rol_id']);
                if($table==='usuarios' && !array_key_exists('permiso_patrimonio',$row)) $row['permiso_patrimonio']=($row['rol']??'')==='ADMIN'?1:0;
                if($table==='usuarios' && !array_key_exists('permiso_ordenes',$row)) { $row['permiso_ordenes']=$row['permiso_seguimiento']??0; $row['alcance_ordenes']=($row['alcance_seguimiento']??'')==='GLOBAL'?'GLOBAL':'UNIDAD'; $row['permiso_ordenes_montos']=!empty($row['permiso_ordenes'])?($row['permiso_ver_montos']??1):0; $row['permiso_ordenes_detalle']=!empty($row['permiso_ordenes'])?($row['permiso_ver_detalle']??1):0; }
                if(!$row || array_diff(array_keys($row),$allowed)) throw new RuntimeException('Columnas incompatibles.');
                $pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
            }
        }
    } else {
        $pdo->prepare("INSERT INTO usuarios(usuario,nombre,clave_hash,rol,perfil_portal,rol_acceso,alcance,alcance_seguimiento,alcance_presupuesto,permiso_patrimonio,permiso_presupuesto,permiso_responsabilidades,permiso_usuarios,permiso_ordenes,alcance_ordenes,permiso_ordenes_montos,permiso_ordenes_detalle) VALUES('admin','Administrador',?,'ADMIN','ADMIN','ADMIN','GLOBAL','GLOBAL','GLOBAL',1,1,1,1,1,'GLOBAL',1,1)")->execute([password_hash($password,PASSWORD_DEFAULT)]);
    }
    $pdo->commit();
    require_once __DIR__.'/../config/app.php';
    require_once __DIR__.'/../config/database.php';
    RolModel::instalar();
    foreach(['cache','sessions','backups'] as $dir) { $path=__DIR__.'/../storage/'.$dir; if(!is_dir($path) && !mkdir($path,0770,true)) throw new RuntimeException('No se pudo crear '.$path); }
    echo "Instalacion completada en $name. Ejecute la sincronizacion inicial.\n";
    if($tables===null) echo "Usuario: admin\nClave inicial: $password\n";
} catch(Throwable $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'ERROR: '.$e->getMessage()."\nSi quedo estructura parcial, use otra base vacia. No se borran datos automaticamente.\n"); exit(1);
}
