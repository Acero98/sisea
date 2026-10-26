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
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/app.css?v=20261025d">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/css/seguridad.css?v=20261025a">
    <?php if (!empty($useDataTables)): ?><link rel="stylesheet" href="<?= e(APP_URL) ?>assets/vendor/jquery.dataTables.min.css"><?php endif; ?>
    <script src="<?= e(APP_URL) ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <title><?= e($pageTitle) ?> · Avalon</title>
</head>
<body>
    <?php if ($flash): ?><div class="app-toast-message" role="status" aria-live="polite" data-toast-type="<?= e(($flash['type'] ?? '') === 'error' ? 'error' : 'success') ?>" data-toast-message="<?= e($flash['message'] ?? '') ?>" hidden></div><?php endif; ?>
    <header class="app-topbar">
        <div class="topbar-brand"><span aria-hidden="true">◈</span><strong>AVALON</strong></div>
        <nav class="topbar-links module-navigation" aria-label="Navegación principal">
        <?php
        $navegacion=[
          ['nombre'=>'Logística','icono'=>'bi-boxes','items'=>[['ruta'=>'seguimiento','nombre'=>'Seguimiento documental','permiso'=>'seguimiento','icono'=>'bi-search'],['ruta'=>'notas','nombre'=>'Mis notas','permiso'=>'seguimiento','icono'=>'bi-journal-text'],['ruta'=>'ordenes','nombre'=>'Órdenes de compra y servicio','permiso'=>'ordenes','icono'=>'bi-receipt-cutoff']]],
          ['nombre'=>'Patrimonio','icono'=>'bi-box-seam','items'=>[['ruta'=>'patrimonio','nombre'=>'Consulta de activos','permiso'=>'patrimonio','icono'=>'bi-box-seam']]],
          ['nombre'=>'Presupuesto','icono'=>'bi-wallet2','items'=>[['ruta'=>'presupuesto','nombre'=>'Consulta presupuestal','permiso'=>'presupuesto','icono'=>'bi-wallet2'],['ruta'=>'presupuesto_responsabilidades','nombre'=>'Responsabilidades','permiso'=>'responsabilidades','icono'=>'bi-sliders']]],
          ['nombre'=>'Seguridad','icono'=>'bi-shield-lock','items'=>[['ruta'=>'usuarios','nombre'=>'Usuarios','permiso'=>'usuarios','icono'=>'bi-people'],['ruta'=>'roles','nombre'=>'Roles','permiso'=>'usuarios','icono'=>'bi-person-badge']]],
        ];
        foreach($navegacion as$modulo):$opciones=array_values(array_filter($modulo['items'],static fn($item)=>tienePermiso($item['permiso'],$currentUser)));if(!$opciones)continue;
        $seleccionado=in_array($activeNav,array_column($opciones,'ruta'),true);
        if($modulo['nombre']==='Patrimonio'): ?><a class="<?= $seleccionado?'active':'' ?>" href="<?= e(url('patrimonio')) ?>" <?= $seleccionado?'aria-current="page"':'' ?>><i class="bi <?= e($modulo['icono']) ?>" aria-hidden="true"></i><span><?= e($modulo['nombre']) ?></span></a>
        <?php else: ?><details class="module-nav <?= $seleccionado?'is-current':'' ?>"><summary><i class="bi <?= e($modulo['icono']) ?>" aria-hidden="true"></i><span><?= e($modulo['nombre']) ?></span><i class="bi bi-chevron-down module-nav-chevron" aria-hidden="true"></i></summary><div class="module-nav-menu"><small><?= e($modulo['nombre']) ?></small>
        <?php foreach($opciones as$item): ?><a href="<?= e(url($item['ruta'])) ?>" class="<?= $activeNav===$item['ruta']?'active':'' ?>" <?= $activeNav===$item['ruta']?'aria-current="page"':'' ?>><i class="bi <?= e($item['icono']) ?>" aria-hidden="true"></i><span><?= e($item['nombre']) ?></span></a><?php endforeach; ?></div></details>
        <?php endif;endforeach; ?>
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
            <span class="read-only"><?= in_array($activeNav,['usuarios','roles'],true)?'Seguridad · Administración de acceso':'◉ Fuente Avalon · solo lectura' ?></span>
        </header><?php endif; ?>







