<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/app.php';
require APP_ROOT.'/config/database.php';
require APP_ROOT.'/app/Helpers.php';
function verifyRole(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'OK '.$label.PHP_EOL;}
$pdo=db();
$ddl=$pdo->query('SHOW CREATE TABLE usuarios')->fetch(PDO::FETCH_NUM)[1];
$pdo->exec(preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$ddl));
$pdo->exec("CREATE TEMPORARY TABLE roles_acceso(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,nombre VARCHAR(120) UNIQUE,descripcion VARCHAR(1000) NOT NULL DEFAULT '',permisos_json TEXT NOT NULL,activo TINYINT NOT NULL DEFAULT 1,creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$pdo->exec("INSERT INTO usuarios(id,usuario,nombre,rol,rol_acceso,clave_hash,permiso_usuarios,alcance_seguimiento,permiso_ordenes,alcance_ordenes) VALUES
 (900001,'admin_test','Administrador prueba','ADMIN','ADMINISTRADOR','hash_de_prueba',1,'GLOBAL',1,'GLOBAL'),
 (900002,'usuario_a','Usuario A','CONSULTA','USUARIO',NULL,0,'PROPIO',1,'PROPIO'),
 (900003,'usuario_b','Usuario B','CONSULTA','USUARIO',NULL,0,'UNIDAD',1,'UNIDAD'),
 (900004,'usuario_c','Usuario C','CONSULTA','USUARIO',NULL,0,'PROPIO',1,'PROPIO')");
$antes=[];foreach($pdo->query('SELECT * FROM usuarios')->fetchAll()as$u)$antes[$u['id']]=RolModel::permisos($u);
RolModel::instalar();
$users=$pdo->query('SELECT * FROM usuarios ORDER BY id')->fetchAll();
foreach($users as$u)verifyRole(RolModel::permisos(RolModel::aplicar($u))===$antes[$u['id']],'migración conserva acceso '.$u['id']);
verifyRole($users[1]['rol_id']===$users[3]['rol_id']&&$users[1]['rol_id']!==$users[2]['rol_id'],'comparte roles iguales y separa configuraciones personalizadas');
$role=(new RolModel())->listar();verifyRole(count($role)===3,'tres configuraciones existentes sin roles redundantes');
$sharedId=(int)$users[1]['rol_id'];$adminId=(int)$users[0]['rol_id'];
$permissions=$antes[900002];$permissions['alcance_ordenes']='GLOBAL';$permissions['permiso_ordenes_montos']=1;
$model=new RolModel();$model->guardar($sharedId,array_merge($permissions,['nombre'=>'Consulta logística','descripcion'=>'Acceso compartido','activo'=>1]));
foreach([900002,900004]as$id){$session=(new AuthModel())->datosSesion($id);verifyRole($session['alcance_ordenes']==='GLOBAL'&&(int)$session['permiso_ordenes_montos']===1,'cambio de rol se hereda al refrescar sesión '.$id);}
(new UsuarioModel())->asignarRol(900003,$sharedId,true,'');
verifyRole((new AuthModel())->datosSesion(900003)['alcance_ordenes']==='GLOBAL','asignar rol aplica sus permisos');
$model->estado($sharedId,false);$session=(new AuthModel())->datosSesion(900002);
verifyRole(!$session['rol_activo']&&!tienePermiso('ordenes',$session)&&!tienePermiso('seguimiento',$session),'rol inactivo revoca todos los módulos');
try{(new UsuarioModel())->asignarRol(900003,$sharedId,true,'');throw new LogicException('Rol inactivo admitido');}catch(DomainException $e){verifyRole(true,'no se asignan roles inactivos');}
try{$model->estado($adminId,false);throw new LogicException('Se eliminó el último administrador');}catch(DomainException $e){verifyRole(true,'protección del último administrador al desactivar rol');}
try{(new UsuarioModel())->asignarRol(900001,$sharedId,false,'');throw new LogicException('Se desactivó el último administrador');}catch(DomainException $e){verifyRole(true,'protección al desactivar o reasignar cuenta administrativa');}
$admin=$antes[900001];$admin['permiso_usuarios']=0;
try{$model->guardar($adminId,array_merge($admin,['nombre'=>'Administrador','activo'=>1]));throw new LogicException('Se retiró la última administración');}catch(DomainException $e){verifyRole(true,'protección al retirar permiso de Seguridad');}
$model->estado($sharedId,true);
try{$model->guardar(0,['nombre'=>'Inválido','alcance_ordenes'=>'OTRO','activo'=>1]);throw new LogicException('Alcance inválido admitido');}catch(DomainException $e){verifyRole(true,'validación de alcances');}
$model->guardar(0,['nombre'=>'Sin acceso','activo'=>1]);$newId=(int)$pdo->query("SELECT id FROM roles_acceso WHERE nombre='Sin acceso'")->fetchColumn();
(new UsuarioModel())->asignarRol(900002,$newId,true,'');$session=(new AuthModel())->datosSesion(900002);
verifyRole(!tienePermiso('usuarios',$session)&&!tienePermiso('ordenes',$session),'rol nuevo no concede permisos implícitos');
verifyRole((bool)(new AuthModel())->datosSesion(900001)['permiso_usuarios'],'administrador preservado después de rechazos');
verifyRole(count((new UsuarioModel())->listar())===4,'listado de usuarios resuelve permisos y nombres de roles');
echo "Pruebas completadas con tablas temporales; sin cambios en cuentas reales.\n";
