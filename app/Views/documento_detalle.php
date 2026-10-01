<?php
declare(strict_types=1);
$esOrden = in_array($detalle['tipo'] ?? '', ['orden_compra','orden_servicio'], true);
$pageTitle = $detalle['titulo'] ?? 'Detalle documental';
$pageDescription = $esOrden ? 'Detalle de la orden según el formato de Avalon.' : 'Consulta de solo lectura desde la copia local de Avalon.';
$breadcrumb = 'Logística / Seguimiento documental / Detalle';
$activeNav = 'seguimiento';
$hidePageHeader = true;
$date = static function ($value): string {
    $value = trim((string)$value);
    if ($value === '' || $value === '00000000') return '—';
    $fecha = DateTime::createFromFormat('Ymd', $value) ?: DateTime::createFromFormat('Y-m-d', $value);
    return $fecha ? $fecha->format('d/m/Y') : $value;
};
$money = static function ($value): string {
    return is_numeric($value) ? number_format((float)$value, 2, '.', ',') : '—';
};
require APP_ROOT.'/app/Views/partials/header.php';
?>
<div class="detail-content">
<?php if (!$detalle): ?>
    <section class="card"><h1>Documento no disponible</h1><p><?= e($error ?? 'No se encontró el documento solicitado.') ?></p><a class="table-action" href="<?= e(url('seguimiento')) ?>">Volver a seguimiento</a></section>
<?php else: ?>
    <header>
        <div><div class="breadcrumb"><?= e($breadcrumb) ?></div><h1><?= e($pageTitle) ?></h1><p><?= e($detalle['nota']['unidad_organica'].' · '.$detalle['nota']['unidad_nombre']) ?></p></div>
        <a class="clear-search" href="<?= e(url('seguimiento',['q'=>$detalle['pedido'],'tipo'=>'pedido'])) ?>">← Volver al expediente</a>
    </header>
    <?php if ($esOrden): ?>
        <?php $datos = $detalle['meta'][0] ?? []; $orden = $detalle['orden']; ?>
        <section class="card order-sheet">
            <div class="order-sheet-heading"><div><span class="order-sheet-kicker">EPS ILO S.A. · <?= e($detalle['tipo']==='orden_compra'?'Gasto operativo':'Servicio') ?></span><h2><?= e($detalle['titulo']) ?></h2><p>Pedido <?= e($detalle['pedido']) ?><?= !empty($datos['Expediente de cotización']) ? ' · Expediente '.e($datos['Expediente de cotización']) : '' ?></p></div><span class="order-sheet-date">Emisión<br><b><?= e($date($datos['Fecha de emisión'] ?? '')) ?></b></span></div>
            <h3>Datos de la orden</h3>
            <dl class="order-sheet-fields">
                <?php foreach (['Proveedor','RUC','Código de proveedor','Certificación','Referencia','Lugar de entrega','Forma de pago','Estado'] as $campo): if (trim((string)($datos[$campo] ?? '')) !== ''): ?>
                    <dt><?= e($campo) ?></dt><dd><?= nl2br(e($datos[$campo])) ?></dd>
                <?php endif; endforeach; ?>
            </dl>
            <h3>Detalle de artículos y servicios</h3>
            <div class="table-scroll order-lines-scroll" tabindex="0" role="region" aria-label="Detalle de las líneas de la orden"><table class="order-sheet-lines">
                <thead><tr><th>Ítem</th><th>Código</th><th>Denominación</th><th>Unidad</th><th>Actividad – cuenta</th><th>Centro de costo</th><th>Cantidad</th><?php if($mostrarMontos): ?><th>Precio unitario</th><th>Precio total</th><?php endif; ?></tr></thead>
                <tbody><?php if (!$detalle['rows']): ?><tr><td colspan="<?= $mostrarMontos ? '9' : '7' ?>">No se encontraron líneas en esta orden.</td></tr><?php else: foreach ($detalle['rows'] as $linea): ?>
                    <tr><td><?= e($linea['item']) ?></td><td><?= e($linea['codigo']) ?></td><td><b><?= e($linea['denominacion']) ?></b><br><small>Pedido <?= e($linea['pedido']) ?></small></td><td><?= e($linea['unidad'] ?: '—') ?></td><td><?= e($linea['actividad_cuenta'] ?: '—') ?></td><td><?= e($linea['centro_costo'] ?: '—') ?></td><td class="order-number"><?= e($linea['cantidad']) ?><?= $linea['recibido'] !== '' ? '<br><small>Recibido: '.e($linea['recibido']).'</small>' : '' ?></td><?php if($mostrarMontos): ?><td class="order-number"><?= e($money($linea['precio_unitario'])) ?></td><td class="order-number"><?= e($money($linea['precio_total'])) ?></td><?php endif; ?></tr>
                <?php endforeach; endif; ?></tbody>
            </table></div>
            <?php if($mostrarMontos): ?><div class="order-sheet-totals"><div><span>Subtotal</span><b><?= e($money($datos['Subtotal'] ?? null)) ?></b></div><div><span>Impuesto</span><b><?= e($money($datos['Impuesto'] ?? null)) ?></b></div><div class="grand-total"><span>Total · moneda <?= e($orden['moneda'] ?: '—') ?></span><b><?= e($money($datos['Total'] ?? null)) ?></b></div></div><?php endif; ?>
        </section>
        <?php if ($orden['condiciones']): ?>
            <section class="card order-conditions"><h2>Provisión, entrega y condiciones</h2><?php foreach ($orden['condiciones'] as $condicion): ?><div class="order-condition"><?= nl2br(e($condicion)) ?></div><?php endforeach; ?></section>
        <?php endif; ?>
    <?php else: ?>
        <section class="card"><h2>Datos registrados</h2><?php if ($detalle['meta']): ?><div class="table-scroll"><table><tbody><?php foreach ($detalle['meta'] as $meta): foreach ($meta as $campo=>$valor): if ($valor!==''): ?><tr><th><?= e(ucwords(strtolower(str_replace('_',' ',$campo)))) ?></th><td><?= e($valor) ?></td></tr><?php endif; endforeach; endforeach;?></tbody></table></div><?php endif; ?>
            <div class="table-scroll" tabindex="0"><table><thead><tr><?php foreach(array_keys($detalle['rows'][0]??[]) as $campo): ?><th><?= e(ucwords(strtolower(str_replace('_',' ',$campo)))) ?></th><?php endforeach;?></tr></thead><tbody><?php if(!$detalle['rows']): ?><tr><td>No se encontraron líneas de detalle para este documento.</td></tr><?php else: foreach($detalle['rows'] as $row): ?><tr><?php foreach($row as $valor): ?><td><?= e($valor?:'—') ?></td><?php endforeach;?></tr><?php endforeach; endif;?></tbody></table></div>
        </section>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php require APP_ROOT.'/app/Views/partials/footer.php'; ?>
