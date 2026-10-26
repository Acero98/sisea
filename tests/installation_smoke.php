<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
$root=dirname(__DIR__);
$c=require $root.'/config/settings.php';
$server=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4',$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='sisea_install_test_'.bin2hex(random_bytes(5));
$backup=$argv[1]??'';
function checkInstall(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); echo 'OK '.$message.PHP_EOL; }
try {
    putenv('SISEA_DB_NAME='.$name);
    putenv('SISEA_ADMIN_PASSWORD=InstallTest-'.bin2hex(random_bytes(8)));
    $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/database/install.php');
    if($backup!=='') $command.=' --portable='.escapeshellarg($backup);
    exec($command.' 2>&1',$output,$status);
    checkInstall($status===0,'instalacion en base aislada');
    $server->exec('USE `'.$name.'`');
    checkInstall(count($server->query('SHOW TABLES')->fetchAll())===12,'estructura completa de 12 tablas');
    $admins=$server->query('SELECT r.permisos_json FROM usuarios u INNER JOIN roles_acceso r ON r.id=u.rol_id WHERE u.activo=1 AND r.activo=1')->fetchAll(PDO::FETCH_COLUMN);
    checkInstall(count(array_filter($admins,static fn($p)=>!empty((json_decode($p,true)??[])['permiso_usuarios'])))>0,'administracion heredada disponible');
    checkInstall((int)$server->query('SELECT COUNT(*) FROM usuarios WHERE sesion_token IS NOT NULL')->fetchColumn()===0,'sin sesiones trasladadas');
    if($backup!=='') {
        $data=json_decode(gzdecode(file_get_contents($backup)),true);
        foreach($data['tables'] as $table=>$rows) {
            checkInstall((int)$server->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===count($rows),'conteo '.$table);
        }
        $stored=$server->query('SELECT id,clave_hash FROM usuarios')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach($data['tables']['usuarios'] as $row) checkInstall(array_key_exists($row['id'],$stored) && $stored[$row['id']]===$row['clave_hash'],'hash conservado usuario '.$row['id']);
    } else {
        checkInstall(password_verify(getenv('SISEA_ADMIN_PASSWORD'),$server->query("SELECT clave_hash FROM usuarios WHERE usuario='admin'")->fetchColumn()),'clave inicial valida');
    }
    $output=[]; exec($command.' 2>&1',$output,$status);
    checkInstall($status!==0,'reinstalacion rechazada sin sobrescribir');
} finally {
    if(preg_match('/^sisea_install_test_[a-f0-9]{10}$/',$name)) $server->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    putenv('SISEA_DB_NAME'); putenv('SISEA_ADMIN_PASSWORD');
}
