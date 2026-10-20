<?php
declare(strict_types=1);
final class AuthModel
{
    public function login(string $user, string $password): ?array
    {
        UsuarioModel::instalarPermisosDocumentales();
        $stmt = db()->prepare('SELECT id,usuario,nombre,clave_hash,rol,perfil_portal,alcance,avalon_codigo,unidad_organica,unidad_nombre,zona,localidad,perfil_nombre,permiso_seguimiento,permiso_ver_montos,permiso_ver_detalle,alcance_seguimiento,permiso_patrimonio,permiso_presupuesto,alcance_presupuesto,permiso_responsabilidades,permiso_usuarios FROM usuarios WHERE usuario=:usuario AND activo=1 LIMIT 1');
        $stmt->execute(['usuario' => $user]); $row = $stmt->fetch();
        if (!$row || !$row['clave_hash'] || !password_verify($password, $row['clave_hash'])) return null;
        unset($row['clave_hash']); $row['alcance']=$row['alcance_seguimiento'] ?: $row['alcance']; return $row;
    }

    public function registrarSesion(int $usuarioId, string $token): void
    {
        db()->prepare('UPDATE usuarios SET sesion_token=:token,sesion_iniciada_en=NOW() WHERE id=:id')
            ->execute(['token' => $token, 'id' => $usuarioId]);
    }

    public function sesionVigente(int $usuarioId, string $token): bool
    {
        $stmt=db()->prepare('SELECT 1 FROM usuarios WHERE id=:id AND activo=1 AND sesion_token=:token');
        $stmt->execute(['id'=>$usuarioId,'token'=>$token]);
        return (bool)$stmt->fetchColumn();
    }
    public function datosSesion(int $usuarioId): ?array
    {
        UsuarioModel::instalarPermisosDocumentales();
        $stmt=db()->prepare('SELECT id,usuario,nombre,rol,perfil_portal,alcance_seguimiento AS alcance,avalon_codigo,unidad_organica,unidad_nombre,zona,localidad,perfil_nombre,permiso_seguimiento,permiso_ver_montos,permiso_ver_detalle,alcance_seguimiento,permiso_patrimonio,permiso_presupuesto,alcance_presupuesto,permiso_responsabilidades,permiso_usuarios FROM usuarios WHERE id=:id AND activo=1');
        $stmt->execute(['id'=>$usuarioId]); $fila=$stmt->fetch(); return $fila?:null;
    }

    public function cerrarSesion(int $usuarioId, string $token): void
    {
        db()->prepare('UPDATE usuarios SET sesion_token=NULL WHERE id=:id AND sesion_token=:token')
            ->execute(['id'=>$usuarioId,'token'=>$token]);
    }

}

