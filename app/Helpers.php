<?php
declare(strict_types=1);

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $route = '', array $params = []): string { return APP_URL . '?' . http_build_query(array_merge(['r' => $route], $params)); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function validarCsrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) throw new DomainException('La sesión del formulario venció. Actualice la página e inténtelo nuevamente.'); }
function usuario(): ?array { return $_SESSION['usuario'] ?? null; }
function requiereLogin(): void { if (!usuario()) { header('Location: ' . url('login')); exit; } }
function flash(string $type, string $message): void { $_SESSION['flash'] = compact('type', 'message'); }
function takeFlash(): ?array { $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }
