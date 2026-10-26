<?php
declare(strict_types=1);

final class RolModel
{
    public const CAMPOS = ['permiso_seguimiento','alcance_seguimiento','permiso_ver_montos','permiso_ver_detalle','permiso_ordenes','alcance_ordenes','permiso_ordenes_montos','permiso_ordenes_detalle','permiso_patrimonio','permiso_presupuesto','alcance_presupuesto','permiso_responsabilidades','permiso_usuarios'];

    public static function modulos(): array
    {
        return [
            'Logística'=>[
                ['campo'=>'permiso_seguimiento','nombre'=>'Mis notas y seguimiento','icono'=>'bi-journal-text','alcance'=>'alcance_seguimiento','extras'=>['permiso_ver_montos'=>'Ver montos','permiso_ver_detalle'=>'Abrir detalle']],
                ['campo'=>'permiso_ordenes','nombre'=>'Órdenes de compra y servicio','icono'=>'bi-receipt-cutoff','alcance'=>'alcance_ordenes','extras'=>['permiso_ordenes_montos'=>'Ver montos','permiso_ordenes_detalle'=>'Abrir detalle']],
            ],
            'Patrimonio'=>[['campo'=>'permiso_patrimonio','nombre'=>'Consulta de activos','icono'=>'bi-box-seam']],
            'Presupuesto'=>[
                ['campo'=>'permiso_presupuesto','nombre'=>'Consulta presupuestal','icono'=>'bi-wallet2','alcance'=>'alcance_presupuesto'],
                ['campo'=>'permiso_responsabilidades','nombre'=>'Configurar responsabilidades','icono'=>'bi-sliders'],
            ],
            'Seguridad'=>[['campo'=>'permiso_usuarios','nombre'=>'Administrar usuarios y roles','icono'=>'bi-shield-lock']],
        ];
    }

    public static function permisos(array $entrada, bool $validar = false): array
    {
        $out=[];
        foreach(self::CAMPOS as $campo){
            if(strpos($campo,'alcance_')===0){
                $valor=(string)($entrada[$campo]??'UNIDAD');
                if(!in_array($valor,['PROPIO','UNIDAD','GLOBAL'],true)){
                    if($validar)throw new DomainException('Seleccione un alcance válido.');
                    $valor='UNIDAD';
                }
                $out[$campo]=$valor;
            }else $out[$campo]=!empty($entrada[$campo])?1:0;
        }
        if($validar){
            foreach([['permiso_seguimiento','permiso_ver_montos','permiso_ver_detalle'],['permiso_ordenes','permiso_ordenes_montos','permiso_ordenes_detalle']] as [$modulo,$montos,$detalle]){
                if(!$out[$modulo])$out[$montos]=$out[$detalle]=0;
            }
            if($out['alcance_presupuesto']==='PROPIO')$out['alcance_presupuesto']='UNIDAD';
        }
        return $out;
    }

    public static function instalar(): void
    {
        static $listo=false;
        if($listo)return;
        UsuarioModel::instalarPermisosDocumentales();
        $pdo=db();
        try{$pdo->query('SELECT id FROM roles_acceso LIMIT 0');}
        catch(PDOException $e){
            if($e->getCode()!=='42S02')throw $e;
            $pdo->exec("CREATE TABLE IF NOT EXISTS roles_acceso (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL UNIQUE,
            descripcion VARCHAR(1000) NOT NULL DEFAULT '',
            permisos_json TEXT NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        if(!$pdo->query("SHOW COLUMNS FROM usuarios LIKE 'rol_id'")->fetch()){
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN rol_id INT UNSIGNED DEFAULT NULL, ADD INDEX idx_usuario_rol (rol_id)');
        }
        // Only unassigned accounts are migrated. Each distinct existing access
        // configuration becomes a shared role; no account gains permissions.
        $pendientes=$pdo->query('SELECT * FROM usuarios WHERE rol_id IS NULL')->fetchAll();
        if($pendientes){
            $pdo->beginTransaction();
            try{
                $pendientes=$pdo->query('SELECT * FROM usuarios WHERE rol_id IS NULL FOR UPDATE')->fetchAll();
                $existentes=$pdo->query('SELECT id,nombre,permisos_json FROM roles_acceso WHERE activo=1')->fetchAll();
                $nombres=['USUARIO'=>'Usuario','LOGISTICA'=>'Logística','PRESUPUESTO'=>'Presupuesto','CONTABILIDAD'=>'Contabilidad','FINANZAS'=>'Finanzas','ADMINISTRADOR'=>'Administrador','ADMIN'=>'Administrador'];
                foreach($pendientes as $u){
                    $p=self::permisos($u);$json=json_encode($p,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                    $base=$nombres[$u['rol_acceso']??'']??'Acceso existente';$rolId=null;
                    foreach($existentes as $r){
                        if(($r['nombre']===$base||strpos($r['nombre'],$base.' · ')===0)&&self::permisos(json_decode($r['permisos_json'],true)??[])===$p){$rolId=(int)$r['id'];break;}
                    }
                    if($rolId===null){
                        $nombre=$base;$n=2;$usados=array_column($pdo->query('SELECT nombre FROM roles_acceso')->fetchAll(),'nombre');
                        while(in_array($nombre,$usados,true))$nombre=$base.' · Acceso '.$n++;
                        $q=$pdo->prepare('INSERT INTO roles_acceso(nombre,descripcion,permisos_json) VALUES(?,?,?)');
                        $q->execute([$nombre,'Configuración migrada conservando los accesos existentes.',$json]);$rolId=(int)$pdo->lastInsertId();
                        $existentes[]=['id'=>$rolId,'nombre'=>$nombre,'permisos_json'=>$json];
                    }
                    $pdo->prepare('UPDATE usuarios SET rol_id=? WHERE id=? AND rol_id IS NULL')->execute([$rolId,$u['id']]);
                }
                $pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        }
        $listo=true;
    }

    public function listar(): array
    {
        self::instalar();
        $rows=db()->query('SELECT r.*, (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id=r.id) AS usuarios FROM roles_acceso r ORDER BY r.nombre')->fetchAll();
        foreach($rows as &$r)$r['permisos']=self::permisos(json_decode($r['permisos_json'],true)??[]);
        return $rows;
    }

    public static function aplicar(array $usuario, ?array $rol = null): array
    {
        self::instalar();
        if($rol===null){$q=db()->prepare('SELECT id,nombre,activo,permisos_json FROM roles_acceso WHERE id=?');$q->execute([$usuario['rol_id']??0]);$rol=$q->fetch()?:[];}
        $activo=$rol&&(bool)$rol['activo'];
        $p=$activo?self::permisos(json_decode($rol['permisos_json'],true)??[]):self::permisos([]);
        $usuario=array_merge($usuario,$p,['rol_nombre'=>$rol['nombre']??'Sin rol asignado','rol_activo'=>(bool)$activo]);
        // Permission checks always use explicit fields; rol is retained solely
        // for old consumers that distinguish administrative access.
        $usuario['rol']=$p['permiso_usuarios']?'ADMIN':'CONSULTA';
        $usuario['alcance']=$p['alcance_seguimiento'];
        return $usuario;
    }

    public function guardar(int $id,array $datos): void
    {
        self::instalar();
        $nombre=trim((string)($datos['nombre']??''));$descripcion=trim((string)($datos['descripcion']??''));
        if($nombre===''||mb_strlen($nombre)>120||mb_strlen($descripcion)>1000)throw new DomainException('Indique un nombre de hasta 120 caracteres y una descripción de hasta 1000.');
        $p=self::permisos($datos,true);$activo=!empty($datos['activo'])?1:0;$pdo=db();$pdo->beginTransaction();
        try{
            self::bloquearAdministracion();
            $dup=$pdo->prepare('SELECT id FROM roles_acceso WHERE nombre=? AND id<>?');$dup->execute([$nombre,$id]);
            if($dup->fetch())throw new DomainException('Ya existe un rol con ese nombre.');
            $json=json_encode($p,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if($id>0){
                $q=$pdo->prepare('SELECT id FROM roles_acceso WHERE id=?');$q->execute([$id]);if(!$q->fetch())throw new DomainException('El rol ya no existe.');
                $pdo->prepare('UPDATE roles_acceso SET nombre=?,descripcion=?,permisos_json=?,activo=? WHERE id=?')->execute([$nombre,$descripcion,$json,$activo,$id]);
            }else $pdo->prepare('INSERT INTO roles_acceso(nombre,descripcion,permisos_json,activo) VALUES(?,?,?,?)')->execute([$nombre,$descripcion,$json,$activo]);
            self::asegurarAdministrador();$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function estado(int $id,bool $activo): void
    {
        self::instalar();$pdo=db();$pdo->beginTransaction();
        try{
            self::bloquearAdministracion();
            $q=$pdo->prepare('SELECT id FROM roles_acceso WHERE id=?');$q->execute([$id]);if(!$q->fetch())throw new DomainException('El rol ya no existe.');
            $pdo->prepare('UPDATE roles_acceso SET activo=? WHERE id=?')->execute([$activo?1:0,$id]);
            self::asegurarAdministrador();$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function bloquearAdministracion(): void
    {
        db()->query('SELECT id FROM roles_acceso ORDER BY id FOR UPDATE')->fetchAll();
        db()->query('SELECT id FROM usuarios ORDER BY id FOR UPDATE')->fetchAll();
    }

    public static function asegurarAdministrador(): void
    {
        $rows=db()->query("SELECT r.permisos_json FROM usuarios u INNER JOIN roles_acceso r ON r.id=u.rol_id WHERE u.activo=1 AND r.activo=1 AND u.clave_hash IS NOT NULL AND u.clave_hash<>''")->fetchAll();
        foreach($rows as $r)if(!empty((json_decode($r['permisos_json'],true)??[])['permiso_usuarios']))return;
        throw new DomainException('Debe permanecer al menos una cuenta activa con contraseña y permiso para administrar usuarios y roles.');
    }
}
