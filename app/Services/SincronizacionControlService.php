<?php
declare(strict_types=1);

final class SincronizacionControlService
{
    private const LOCK_NAME = 'sisea-sincronizacion-avalon';

    public function instalar(): void
    {
        $sql = (string)file_get_contents(APP_ROOT . '/database/migrations/009_control_sincronizacion_sesion.sql');
        foreach (explode(';', $sql) as $sentencia) {
            if (trim($sentencia) !== '') db()->exec($sentencia);
        }
        try { db()->exec('ALTER TABLE usuarios ADD COLUMN sesion_token VARCHAR(64) NULL'); } catch (PDOException $e) {}
        try { db()->exec('ALTER TABLE usuarios ADD COLUMN sesion_iniciada_en DATETIME NULL'); } catch (PDOException $e) {}
    }

    public function iniciar(array $usuario, string $alcance, string $descripcion): bool
    {
        $stmt = db()->prepare('SELECT GET_LOCK(:nombre, 0)');
        $stmt->execute(['nombre' => self::LOCK_NAME]);
        if ((int)$stmt->fetchColumn() !== 1) return false;

        db()->prepare("UPDATE sincronizacion_control SET estado='EJECUTANDO',usuario_id=:usuario_id,usuario_nombre=:usuario_nombre,alcance=:alcance,descripcion=:descripcion,iniciado_en=NOW(),terminado_en=NULL,mensaje=NULL WHERE id=1")
            ->execute([
                'usuario_id' => (int)($usuario['id'] ?? 0) ?: null,
                'usuario_nombre' => (string)($usuario['nombre'] ?? $usuario['usuario'] ?? 'Usuario'),
                'alcance' => $alcance,
                'descripcion' => $descripcion,
            ]);
        return true;
    }

    public function finalizar(bool $correcto, string $mensaje): void
    {
        $estado = $correcto ? 'LIBRE' : 'ERROR';
        db()->prepare('UPDATE sincronizacion_control SET estado=:estado,terminado_en=NOW(),mensaje=:mensaje WHERE id=1')
            ->execute(['estado' => $estado, 'mensaje' => mb_substr($mensaje, 0, 500)]);
        db()->prepare('SELECT RELEASE_LOCK(:nombre)')->execute(['nombre' => self::LOCK_NAME]);
    }

    public function estado(): array
    {
        $fila = db()->query('SELECT * FROM sincronizacion_control WHERE id=1')->fetch() ?: ['estado' => 'LIBRE'];
        if (($fila['estado'] ?? '') === 'EJECUTANDO') {
            $stmt = db()->prepare('SELECT IS_FREE_LOCK(:nombre)');
            $stmt->execute(['nombre' => self::LOCK_NAME]);
            if ((int)$stmt->fetchColumn() === 1) {
                db()->exec("UPDATE sincronizacion_control SET estado='ERROR',terminado_en=NOW(),mensaje='La actualización fue interrumpida antes de finalizar' WHERE id=1");
                $fila = db()->query('SELECT * FROM sincronizacion_control WHERE id=1')->fetch() ?: ['estado' => 'ERROR'];
            }
        }
        $fila['en_curso'] = ($fila['estado'] ?? '') === 'EJECUTANDO';
        return $fila;
    }
}
