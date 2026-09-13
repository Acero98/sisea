<?php
declare(strict_types=1);
$config = require __DIR__ . '/../config/local.php';
$pdo = new PDO('mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec((string)file_get_contents(__DIR__ . '/schema.sql'));
$stmt = $pdo->prepare('SELECT id FROM avalon_seguimiento.usuarios WHERE usuario=?');
$stmt->execute(['admin']);
if (!$stmt->fetchColumn()) {
    $pdo->prepare("INSERT INTO avalon_seguimiento.usuarios(usuario,nombre,clave_hash,rol) VALUES(?,?,?,'ADMIN')")->execute(['admin', 'Administrador', password_hash('Avalon2026*', PASSWORD_DEFAULT)]);
}
echo "Instalación completada." . PHP_EOL;
