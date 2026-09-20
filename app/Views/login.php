<?php
$flash = takeFlash();
$sesionReemplazada = (string)($_GET['motivo'] ?? '') === 'sesion_reemplazada'
    || (string)($_COOKIE['sisea_motivo_salida'] ?? '') === 'sesion_reemplazada';
if ($sesionReemplazada && isset($_COOKIE['sisea_motivo_salida'])) {
    setcookie('sisea_motivo_salida','',time()-3600,APP_URL,'',false,true);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/app.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/login.css?v=20260913">
    <title>Acceso · Avalon</title>
</head>
<body class="login-page">
<main class="access-shell">
    <section class="access-story" aria-labelledby="story-title">
        <a class="access-brand" href="<?= e(url('login')) ?>" aria-label="Avalon, inicio">
            <span class="access-mark" aria-hidden="true">A<span></span></span>
            <span><strong>AVALON</strong><small>Seguimiento de logística</small></span>
        </a>
        <div class="access-copy">
            <span class="access-eyebrow">CADA DOCUMENTO, CONECTADO</span>
            <h1 id="story-title">La claridad que tu <br>logística necesita<span>.</span></h1>
            <p>Consulta el avance de tus pedidos, cotizaciones y órdenes desde un solo lugar.</p>
            <div class="access-flow" aria-label="Pedidos, cotizaciones y órdenes">
                <div><span>01</span><strong>Pedidos</strong></div>
                <i aria-hidden="true">→</i>
                <div><span>02</span><strong>Cotizaciones</strong></div>
                <i aria-hidden="true">→</i>
                <div><span>03</span><strong>Órdenes</strong></div>
            </div>
        </div>
        <div class="access-story-footer"><span aria-hidden="true">✦</span> Información conectada. Seguimiento más simple.</div>
    </section>
    <section class="access-form-side" aria-labelledby="access-title">
        <div class="access-card">
            <div class="access-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></div>
            <span class="access-eyebrow">PORTAL DE CONSULTAS</span>
            <h2 id="access-title">Bienvenido de nuevo</h2>
            <p>Ingresa con tu cuenta para continuar.</p>
            <?php if ($sesionReemplazada): ?>
                <div class="alert error" role="alert">
                    Su sesión se cerró porque esta cuenta inició sesión en otro equipo o navegador.
                </div>
            <?php elseif ($flash): ?>
                <div class="alert error" role="alert"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('auth')) ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" autocomplete="username" placeholder="Tu usuario" required>
                <label for="clave">Contraseña</label>
                <input id="clave" type="password" name="clave" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
                <button type="submit">Ingresar al sistema <span aria-hidden="true">→</span></button>
            </form>
            <div class="access-help">¿Necesitas acceso? Contacta al administrador del sistema.</div>
        </div>
        <footer class="access-footer">AVALON <span>·</span> Seguimiento documental</footer>
    </section>
</main>
</body>
</html>
