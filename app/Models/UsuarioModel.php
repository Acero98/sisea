<?php

declare(strict_types=1);
final class UsuarioModel
{
    public function sincronizar(): int
    {
        $reader = new DbfReader();
        $perfiles = [];
        foreach ($reader->rows(AVALON_LOGISTICA_DATA . '/sum10002.dbf', ['CODPERFIL', 'NOMPERFIL']) as $perfil) $perfiles[$perfil['CODPERFIL']] = $perfil['NOMPERFIL'];
        $unidades = [];
        foreach ($reader->rows(AVALON_PLANIF_DATA . '/unidad.dbf', ['COD_UNIDAD', 'DES_UNIDAD']) as $unidad) $unidades[$unidad['COD_UNIDAD']] = $unidad['DES_UNIDAD'];
        $usuarios = $reader->rows(AVALON_LOGISTICA_DATA . '/sum10003.dbf', ['CCODUSUARI', 'CNOMUSUARI', 'CPERFILUSR', 'ZONA', 'LOCA', 'CODUNIORG', 'NUMALM', 'USUARIOSUP', 'ACTIVO']);
        $sql = 'INSERT INTO usuarios (avalon_codigo,usuario,nombre,perfil_avalon,perfil_nombre,zona,localidad,unidad_organica,unidad_nombre,almacen,supervisor_avalon,rol,alcance,activo,sincronizado_en) VALUES (:codigo,:usuario,:nombre,:perfil,:perfil_nombre,:zona,:localidad,:unidad,:unidad_nombre,:almacen,:supervisor,:rol,:alcance,:activo,NOW()) ON DUPLICATE KEY UPDATE usuario=VALUES(usuario),nombre=VALUES(nombre),perfil_avalon=VALUES(perfil_avalon),perfil_nombre=VALUES(perfil_nombre),zona=VALUES(zona),localidad=VALUES(localidad),unidad_organica=VALUES(unidad_organica),unidad_nombre=VALUES(unidad_nombre),almacen=VALUES(almacen),supervisor_avalon=VALUES(supervisor_avalon),sincronizado_en=NOW()';
        $stmt = db()->prepare($sql);
        $count = 0;
        foreach ($usuarios as $user) {
            $perfil = $user['CPERFILUSR'];
            $global = $perfil === '01';
            $stmt->execute(['codigo' => $user['CCODUSUARI'], 'usuario' => $user['CNOMUSUARI'], 'nombre' => $user['CNOMUSUARI'], 'perfil' => $perfil, 'perfil_nombre' => $perfiles[$perfil] ?? 'Perfil no identificado', 'zona' => $user['ZONA'], 'localidad' => $user['LOCA'], 'unidad' => $user['CODUNIORG'], 'unidad_nombre' => $unidades[$user['CODUNIORG']] ?? null, 'almacen' => $user['NUMALM'], 'supervisor' => $user['USUARIOSUP'], 'rol' => $perfil === '01' ? 'ADMIN' : 'CONSULTA', 'alcance' => $global ? 'GLOBAL' : 'UNIDAD', 'activo' => $user['ACTIVO'] === 'T' ? 1 : 0]);
            $count++;
        }
        return $count;
    }
    public function listar(): array
    {
        $this->instalarPermisos();
        $this->instalarRolesAcceso();
        self::instalarPermisosDocumentales();
        RolModel::instalar();
        $rows=db()->query('SELECT id,rol_id,usuario,nombre,avalon_codigo,rol,perfil_portal,rol_acceso,perfil_nombre,unidad_organica,unidad_nombre,alcance,permiso_seguimiento,permiso_ver_montos,permiso_ver_detalle,alcance_seguimiento,permiso_ordenes,alcance_ordenes,permiso_ordenes_montos,permiso_ordenes_detalle,permiso_patrimonio,permiso_presupuesto,alcance_presupuesto,permiso_responsabilidades,permiso_usuarios,activo,clave_hash IS NOT NULL AS tiene_clave FROM usuarios ORDER BY nombre')->fetchAll();
        $roles=[];foreach(db()->query('SELECT id,nombre,activo,permisos_json FROM roles_acceso')->fetchAll()as$r)$roles[$r['id']]=$r;
        return array_map(static fn($u)=>RolModel::aplicar($u,$roles[$u['rol_id']]??[]),$rows);
    }
    public function asignarRol(int $id,int $rolId,bool $activo,string $clave):void
    {
        RolModel::instalar();$pdo=db();$pdo->beginTransaction();
        try{
            RolModel::bloquearAdministracion();
            $q=$pdo->prepare('SELECT id FROM usuarios WHERE id=?');$q->execute([$id]);if(!$q->fetch())throw new DomainException('La cuenta ya no existe.');
            $q=$pdo->prepare('SELECT id FROM roles_acceso WHERE id=? AND activo=1');$q->execute([$rolId]);if(!$q->fetch())throw new DomainException('Seleccione un rol activo.');
            $params=[$rolId,$activo?1:0];$sql='UPDATE usuarios SET rol_id=?,activo=?';
            if($clave!==''){$sql.=',clave_hash=?';$params[]=password_hash($clave,PASSWORD_DEFAULT);}
            $params[]=$id;$pdo->prepare($sql.' WHERE id=?')->execute($params);
            RolModel::asegurarAdministrador();$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private function instalarPermisos(): void {
        if(db()->query("SHOW COLUMNS FROM usuarios LIKE 'permiso_seguimiento'")->fetch())return;
        foreach(explode(';',(string)file_get_contents(APP_ROOT.'/database/migrations/013_permisos_modulares.sql')) as $sql) if(trim($sql)!=='')db()->exec($sql);
    }
    public static function instalarPermisosDocumentales(): void
    {
        static $instalado = false;
        if ($instalado) return;
        foreach (['permiso_ver_montos', 'permiso_ver_detalle'] as $columna) {
            if (!db()->query("SHOW COLUMNS FROM usuarios LIKE '" . $columna . "'")->fetch()) {
                db()->exec('ALTER TABLE usuarios ADD COLUMN ' . $columna . ' TINYINT(1) NOT NULL DEFAULT 1');
            }
        }
        if (!db()->query("SHOW COLUMNS FROM usuarios LIKE 'permiso_patrimonio'")->fetch()) {
            db()->exec('ALTER TABLE usuarios ADD COLUMN permiso_patrimonio TINYINT(1) NOT NULL DEFAULT 0');
            db()->exec("UPDATE usuarios SET permiso_patrimonio=1 WHERE rol='ADMIN'");
        }
        self::instalarPermisosOrdenes();
        $instalado = true;
    }
    private static function instalarPermisosOrdenes(): void
    {
        $columnas = [
            'permiso_ordenes'=>['TINYINT(1) NOT NULL DEFAULT 0', 'permiso_seguimiento'],
            'alcance_ordenes'=>["VARCHAR(15) NOT NULL DEFAULT 'UNIDAD'", "IF(alcance_seguimiento='GLOBAL','GLOBAL','UNIDAD')"],
            'permiso_ordenes_montos'=>['TINYINT(1) NOT NULL DEFAULT 0', 'IF(permiso_seguimiento=1,permiso_ver_montos,0)'],
            'permiso_ordenes_detalle'=>['TINYINT(1) NOT NULL DEFAULT 0', 'IF(permiso_seguimiento=1,permiso_ver_detalle,0)'],
        ];
        foreach ($columnas as $nombre=>[$definicion,$valorAnterior]) {
            if (!db()->query("SHOW COLUMNS FROM usuarios LIKE '".$nombre."'")->fetch()) {
                db()->exec('ALTER TABLE usuarios ADD COLUMN '.$nombre.' '.$definicion);
                db()->exec('UPDATE usuarios SET '.$nombre.'='.$valorAnterior);
            }
        }
    }
    private function instalarAlcancePropio(): void
    {
        $tipo=(string)(db()->query("SHOW COLUMNS FROM usuarios LIKE 'alcance'")->fetch()['Type']??'');
        if(strpos($tipo,"'PROPIO'")!==false)return;
        db()->exec((string)file_get_contents(APP_ROOT.'/database/migrations/014_alcance_propio.sql'));
    }
    private function instalarRolesAcceso(): void
    {
        if (db()->query("SHOW COLUMNS FROM usuarios LIKE 'rol_acceso'")->fetch()) return;
        foreach (explode(';', (string) file_get_contents(APP_ROOT.'/database/migrations/015_roles_acceso.sql')) as $sql) {
            if (trim($sql) !== '') db()->exec($sql);
        }
    }
}
