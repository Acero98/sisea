<?php
declare(strict_types=1);
final class AuthModel
{
    public function login(string $user, string $password): ?array
    {
        $stmt = db()->prepare('SELECT id,usuario,nombre,clave_hash,rol,alcance,avalon_codigo,unidad_organica,zona,localidad,perfil_nombre FROM usuarios WHERE usuario=:usuario AND activo=1 LIMIT 1');
        $stmt->execute(['usuario' => $user]); $row = $stmt->fetch();
        if (!$row || !$row['clave_hash'] || !password_verify($password, $row['clave_hash'])) return null;
        unset($row['clave_hash']); return $row;
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

    public function cerrarSesion(int $usuarioId, string $token): void
    {
        db()->prepare('UPDATE usuarios SET sesion_token=NULL WHERE id=:id AND sesion_token=:token')
            ->execute(['id'=>$usuarioId,'token'=>$token]);
    }

}

