<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/app.php';
require APP_ROOT.'/config/database.php';
UsuarioModel::instalarPermisosDocumentales();
$tieneRol=db()->query("SHOW COLUMNS FROM usuarios LIKE 'rol_id'")->fetch();
$antes=db()->query('SELECT id,'.implode(',',RolModel::CAMPOS).' FROM usuarios'.($tieneRol?' WHERE rol_id IS NULL':''))->fetchAll();
RolModel::instalar();
foreach($antes as$cuenta){
    $q=db()->prepare('SELECT * FROM usuarios WHERE id=?');$q->execute([$cuenta['id']]);$actual=$q->fetch();
    if(RolModel::permisos($cuenta)!==RolModel::permisos(RolModel::aplicar($actual)))throw new RuntimeException('La verificación de conservación de acceso no coincide.');
}
echo 'Roles centralizados instalados. '.count($antes)." cuentas migradas con acceso equivalente verificado.\n";
