<?php
declare(strict_types=1);
final class AuthModel
{
    public function login(string $user, string $password): ?array
    {
        $stmt = db()->prepare('SELECT id,usuario,nombre,clave_hash,rol FROM usuarios WHERE usuario=:usuario AND activo=1 LIMIT 1');
        $stmt->execute(['usuario' => $user]); $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['clave_hash'])) return null;
        unset($row['clave_hash']); return $row;
    }
}
