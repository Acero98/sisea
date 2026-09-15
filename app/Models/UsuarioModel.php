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
        $sql = 'INSERT INTO usuarios (avalon_codigo,usuario,nombre,perfil_avalon,perfil_nombre,zona,localidad,unidad_organica,unidad_nombre,almacen,supervisor_avalon,rol,alcance,activo,sincronizado_en) VALUES (:codigo,:usuario,:nombre,:perfil,:perfil_nombre,:zona,:localidad,:unidad,:unidad_nombre,:almacen,:supervisor,:rol,:alcance,:activo,NOW()) ON DUPLICATE KEY UPDATE usuario=VALUES(usuario),nombre=VALUES(nombre),perfil_avalon=VALUES(perfil_avalon),perfil_nombre=VALUES(perfil_nombre),zona=VALUES(zona),localidad=VALUES(localidad),unidad_organica=VALUES(unidad_organica),unidad_nombre=VALUES(unidad_nombre),almacen=VALUES(almacen),supervisor_avalon=VALUES(supervisor_avalon),rol=VALUES(rol),alcance=VALUES(alcance),activo=VALUES(activo),sincronizado_en=NOW()';
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
        return db()->query('SELECT id,usuario,nombre,avalon_codigo,rol,perfil_nombre,unidad_organica,unidad_nombre,alcance,activo,clave_hash IS NOT NULL AS tiene_clave FROM usuarios ORDER BY nombre')->fetchAll();
    }
    public function guardarAcceso(int $id, string $clave, string $alcance, bool $activo): void
    {
        $sql = 'UPDATE usuarios SET alcance=CASE WHEN rol=\'ADMIN\' THEN :alcance ELSE \'UNIDAD\' END, activo=:activo' . ($clave !== '' ? ', clave_hash=:clave' : '') . ' WHERE id=:id';
        $params = ['alcance' => $alcance === 'GLOBAL' ? 'GLOBAL' : 'UNIDAD', 'activo' => $activo ? 1 : 0, 'id' => $id];
        if ($clave !== '') $params['clave'] = password_hash($clave, PASSWORD_DEFAULT);
        db()->prepare($sql)->execute($params);
    }
}
