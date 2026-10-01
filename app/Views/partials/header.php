<?php
$pageTitle = $pageTitle ?? 'Avalon Seguimiento';
$pageDescription = $pageDescription ?? '';
$breadcrumb = $breadcrumb ?? 'Logística';
$activeNav = $activeNav ?? 'seguimiento';
$currentUser = usuario() ?? [];
$flash = $flash ?? takeFlash();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/tailwind.css?v=20260930c">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/css/inter.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/vendor/select2/select2.min.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/app.css?v=20261001c">
    <?php if (!empty($useDataTables)): ?><link rel="stylesheet" href="<?= e(APP_URL) ?>assets/vendor/jquery.dataTables.min.css"><?php endif; ?>
    <script src="<?= e(APP_URL) ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <title><?= e($pageTitle) ?> · Avalon</title>
</head>
<body>
    <?php if ($flash): ?><div class="app-toast-message" role="status" aria-live="polite" data-toast-type="<?= e(($flash['type'] ?? '') === 'error' ? 'error' : 'success') ?>" data-toast-message="<?= e($flash['message'] ?? '') ?>" hidden></div><?php endif; ?>
    <header class="app-topbar">
        <div class="topbar-brand"><span aria-hidden="true">◈</span><strong>AVALON</strong></div>
        <nav class="topbar-links" aria-label="Navegación principal">
            <?php if(tienePermiso('seguimiento',$currentUser)): ?><a class="<?= $activeNav === 'seguimiento' ? 'active' : '' ?>" href="<?= e(url('seguimiento')) ?>"><i class="bi bi-search" aria-hidden="true"></i><span>Seguimiento</span></a>
            <a class="<?= $activeNav === 'ordenes' ? 'active' : '' ?>" href="<?= e(url('ordenes')) ?>"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i><span>Órdenes</span></a>
            <a class="<?= $activeNav === 'notas' ? 'active' : '' ?>" href="<?= e(url('notas')) ?>"><i class="bi bi-journal-text" aria-hidden="true"></i><span>Mis notas</span></a><?php endif; ?>
            <?php if (tienePermiso('presupuesto',$currentUser)): ?><a class="<?= $activeNav === 'presupuesto' ? 'active' : '' ?>" href="<?= e(url('presupuesto')) ?>"><i class="bi bi-wallet2" aria-hidden="true"></i><span>Presupuesto</span></a><?php endif; ?>
            <?php if (tienePermiso('responsabilidades',$currentUser)): ?><a class="<?= $activeNav === 'presupuesto_responsabilidades' ? 'active' : '' ?>" href="<?= e(url('presupuesto_responsabilidades')) ?>"><i class="bi bi-sliders" aria-hidden="true"></i><span>Responsabilidades</span></a><?php endif; ?>
            <?php if (tienePermiso('usuarios',$currentUser)): ?><a class="<?= $activeNav === 'usuarios' ? 'active' : '' ?>" href="<?= e(url('usuarios')) ?>"><i class="bi bi-people" aria-hidden="true"></i><span>Usuarios</span></a><?php endif; ?>
        </nav>
        <div class="topbar-user"><span class="topbar-user-name"><?= e($currentUser['nombre'] ?? '') ?></span><a href="<?= e(url('logout')) ?>" aria-label="Cerrar sesión"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Salir</span></a></div>
    </header>
    <main class="main">
        <div id="sync-global-status" class="sync-global-status" hidden role="status" aria-live="polite">
            <span class="sync-spinner" aria-hidden="true"></span>
            <div><b>Actualización de Avalon en proceso</b><small id="sync-global-detail">Consultando el estado…</small></div>
        </div>
        <?php if (empty($hidePageHeader)): ?><header>
            <div><span class="crumb"><?= e($breadcrumb) ?></span><h1><?= e($pageTitle) ?></h1><?php if ($pageDescription !== ''): ?><p><?= e($pageDescription) ?></p><?php endif; ?></div>
            <span class="read-only">◉ Fuente Avalon · solo lectura</span>
        </header><?php endif; ?>







