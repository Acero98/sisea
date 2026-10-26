<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/app.php';
$hash=password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT);
$permissions=RolModel::permisos(['permiso_usuarios'=>1]);
$users=[['id'=>1,'usuario'=>'admin_traslado','nombre'=>'Cuenta de prueba','rol'=>'CONSULTA','rol_acceso'=>'ADMINISTRADOR','activo'=>1,'clave_hash'=>$hash,'permiso_usuarios'=>1]];
$files=[];
try{
    foreach(['sisea-config-v1','sisea-config-v2']as$version){
        $tables=['usuarios'=>$users,'presupuesto_responsabilidades'=>[]];
        if($version==='sisea-config-v2'){
            $tables['usuarios'][0]['rol_id']=25;
            $tables['usuarios'][0]['permiso_usuarios']=0; // Only inherited grants are authoritative.
            $tables=['roles_acceso'=>[['id'=>25,'nombre'=>'Administrador de prueba','permisos_json'=>json_encode($permissions),'activo'=>1]]]+$tables;
        }
        $file=tempnam(sys_get_temp_dir(),'sisea-roles-');$files[]=$file;
        file_put_contents($file,gzencode(json_encode(['format'=>$version,'tables'=>$tables])));
        $cmd=escapeshellarg(PHP_BINARY).' -d zend.exception_ignore_args=1 '.escapeshellarg(__DIR__.'/installation_smoke.php').' '.escapeshellarg($file);
        passthru($cmd,$status);if($status!==0)throw new RuntimeException('Falló el traslado '.$version);
        echo 'OK traslado '.$version.PHP_EOL;
    }
}finally{foreach($files as$file)if(is_file($file))unlink($file);}
