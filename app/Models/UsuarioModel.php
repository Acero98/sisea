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
        return db()->query('SELECT id,usuario,nombre,avalon_codigo,rol,perfil_portal,rol_acceso,perfil_nombre,unidad_organica,unidad_nombre,alcance,permiso_seguimiento,permiso_ver_montos,permiso_ver_detalle,alcance_seguimiento,permiso_patrimonio,permiso_presupuesto,alcance_presupuesto,permiso_responsabilidades,permiso_usuarios,activo,clave_hash IS NOT NULL AS tiene_clave FROM usuarios ORDER BY nombre')->fetchAll();
    }
    public function guardarAcceso(int $id, string $clave, array $datos): void
    {
        $this->instalarPermisos();
        $this->instalarAlcancePropio();
        $this->instalarRolesAcceso();
        self::instalarPermisosDocumentales();
        $roles=['USUARIO','LOGISTICA','PRESUPUESTO','CONTABILIDAD','FINANZAS','ADMINISTRADOR'];
        $rolAcceso=in_array($datos['rol_acceso']??'', $roles,true)?$datos['rol_acceso']:'USUARIO';
        $perfil=$rolAcceso==='ADMINISTRADOR'?'ADMINISTRADOR':($rolAcceso==='USUARIO'?'USUARIO':'SUPERVISOR');
        $scope=static fn($v)=>in_array($v,['PROPIO','UNIDAD','GLOBAL'],true)?$v:'UNIDAD';
        $sql = 'UPDATE usuarios SET perfil_portal=:perfil,rol_acceso=:rol_acceso,rol=:rol,alcance=:alcance,permiso_seguimiento=:ps,permiso_ver_montos=:pm,permiso_ver_detalle=:pd,alcance_seguimiento=:as_,permiso_patrimonio=:pat,permiso_presupuesto=:pp,alcance_presupuesto=:ap,permiso_responsabilidades=:pr,permiso_usuarios=:pu,activo=:activo' . ($clave !== '' ? ', clave_hash=:clave' : '') . ' WHERE id=:id';
        $seguimiento=!empty($datos['permiso_seguimiento']);
        $params=['pat'=>!empty($datos['permiso_patrimonio'])?1:0,'perfil'=>$perfil,'rol_acceso'=>$rolAcceso,'rol'=>$perfil==='ADMINISTRADOR'?'ADMIN':'CONSULTA','alcance'=>$scope($datos['alcance_seguimiento']??''),'ps'=>$seguimiento?1:0,'pm'=>$seguimiento&&!empty($datos['permiso_ver_montos'])?1:0,'pd'=>$seguimiento&&!empty($datos['permiso_ver_detalle'])?1:0,'as_'=>$scope($datos['alcance_seguimiento']??''),'pp'=>!empty($datos['permiso_presupuesto'])?1:0,'ap'=>$scope($datos['alcance_presupuesto']??''),'pr'=>!empty($datos['permiso_responsabilidades'])?1:0,'pu'=>!empty($datos['permiso_usuarios'])?1:0,'activo'=>!empty($datos['activo'])?1:0,'id'=>$id];
        if ($clave !== '') $params['clave'] = password_hash($clave, PASSWORD_DEFAULT);
        db()->prepare($sql)->execute($params);
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
        $instalado = true;
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
