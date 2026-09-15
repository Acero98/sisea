<?php
$pageTitle = $pageTitle ?? 'Avalon Seguimiento';
$pageDescription = $pageDescription ?? '';
$breadcrumb = $breadcrumb ?? 'Logística';
$activeNav = $activeNav ?? 'seguimiento';
$currentUser = usuario() ?? [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/app.css?v=20260914b">
    <?php if (!empty($useDataTables)): ?><link rel="stylesheet" href="<?= e(APP_URL) ?>assets/vendor/jquery.dataTables.min.css"><?php endif; ?>
    <title><?= e($pageTitle) ?> · Avalon</title>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">◈ <b>AVALON</b><small>Seguimiento</small></div>
        <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Contraer menú" aria-expanded="true">☰</button>
        <nav>
            <a class="<?= $activeNav === 'seguimiento' ? 'active' : '' ?>" href="<?= e(url('seguimiento')) ?>">⌕ <span>Seguimiento</span></a>
            <a class="<?= $activeNav === 'notas' ? 'active' : '' ?>" href="<?= e(url('notas')) ?>">▤ <span>Mis notas</span></a>
            <?php if (($currentUser['rol'] ?? '') === 'ADMIN'): ?><a class="<?= $activeNav === 'usuarios' ? 'active' : '' ?>" href="<?= e(url('usuarios')) ?>">👥 <span>Usuarios</span></a><?php endif; ?>
        </nav>
        <div class="sidebar-user"><b><?= e($currentUser['nombre'] ?? '') ?></b><small><?= e($currentUser['perfil_nombre'] ?? $currentUser['rol'] ?? '') ?></small><a href="<?= e(url('logout')) ?>">↪ <span>Cerrar sesión</span></a></div>
    </aside>
    <main class="main">
        <header>
            <div><span class="crumb"><?= e($breadcrumb) ?></span><h1><?= e($pageTitle) ?></h1><?php if ($pageDescription !== ''): ?><p><?= e($pageDescription) ?></p><?php endif; ?></div>
            <span class="read-only">◉ Fuente Avalon · solo lectura</span>
        </header>
