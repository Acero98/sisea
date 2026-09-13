<?php
$flash = takeFlash();
$date = function ($value) {
    if (!$value || $value === '00000000') return '—';
    $date = DateTime::createFromFormat('Ymd', $value);
    return $date ? $date->format('d/m/Y') : $value;
};
$isService = !empty($result['orden_servicio']);
$isWarehouse = !empty($result) && ($result['pedido']['TIPO_PED'] ?? '') === 'E';
$isCancelled = !empty($result) && ($result['pedido']['EST_PEDIDO'] ?? '') === 'A';
$orderLabel = $isService ? 'Orden de servicio' : 'Orden de compra';
$stages = $isWarehouse
    ? [['Pedido', !empty($result['pedido'])], ['Atención de almacén', !empty($result['retiros_almacen'])], ['Retiro de almacén', !empty($result['retiros_almacen'])]]
    : [['Pedido', !empty($result['pedido'])], ['Cotización', !empty($result['cotizacion'])], ['Certificación', !empty($result['certificacion'])], [$orderLabel, !empty($result['orden_compra']) || $isService], ['Factura o boleta', !empty($result['comprobante'])], ['Registro contable', !empty($result['contable'])], ['Pago', !empty($result['pago'])]];
$final = $isCancelled ? 'Pedido anulado' : ($isWarehouse ? (!empty($result['retiros_almacen']) ? 'Retiro de almacén registrado' : 'Pendiente de atención de almacén') : ($result && $result['pago'] ? 'Pago registrado' : ($result && $result['comprobante'] ? 'Factura o boleta registrada' : ($result && ($result['orden_compra'] || $result['orden_servicio']) ? $orderLabel . ' emitida' : 'En proceso'))));
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/app.css">
    <title>Seguimiento · Avalon</title>
</head>

<body>
    <aside class="sidebar">
        <div class="logo">◈ <b>AVALON</b><small>Seguimiento</small></div>
        <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Contraer menú" aria-expanded="true">☰</button>
        <nav><a class="active" href="<?= e(url('seguimiento')) ?>">⌕ <span>Seguimiento</span></a><a href="#">▤ <span>Consultas recientes</span></a><a href="#">⚙ <span>Configuración</span></a></nav>
        <div class="sidebar-user"><b><?= e(usuario()['nombre']) ?></b><small><?= e(usuario()['rol']) ?></small><a href="<?= e(url('logout')) ?>">↪ <span>Cerrar sesión</span></a></div>
    </aside>
    <main class="main">
        <header>
            <div><span class="crumb">Logística / Seguimiento documental</span>
                <h1>Seguimiento documental</h1>
                <p>Consulta el avance de un pedido y sus documentos vinculados.</p>
            </div><span class="read-only">◉ Fuente Avalon · solo lectura</span>
        </header>
        <?php if ($flash): ?><div class="alert"><?= e($flash['message']) ?></div><?php endif; ?>
        <section class="search-card">
            <h2>Encuentra un documento</h2>
            <p>Busca por pedido; próximamente por orden, certificación y comprobante.</p>
            <form><input type="hidden" name="r" value="seguimiento">
                <div class="types"><span class="selected">Todo</span><span>Pedido</span><span>Nota de pedido</span><span>Cotización</span><span>Certificación</span><span>Orden de compra</span><span>Orden de servicio</span><span>Factura o boleta</span></div>
                <div class="search-line"><input name="q" value="<?= e($term) ?>" placeholder="Ejemplo: 2602201" required><button>Buscar seguimiento</button></div>
            </form>
        </section>
        <?php if ($term !== '' && !$error && !$result): ?><section class="empty">
                <h2>No se encontró el pedido <?= e($term) ?></h2>
                <p>Verifique el número o consulte un pedido existente de Logística.</p>
            </section><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($result): ?><section class="status-card">
                <div class="status-pill">▣ <?= e($final) ?></div>
                <div><b><?= $isCancelled ? 'Avalon registra el pedido como anulado' : ($result['pago'] ? 'El proceso tiene pago registrado' : 'Estado calculado con los documentos encontrados') ?></b>
                    <p>La ruta diferencia órdenes de compra y órdenes de servicio.</p>
                </div>
            </section>
            <section class="card">
                <h2>Ruta del proceso</h2>
                <p class="route-note"><?= $isWarehouse ? 'Ruta de existencia: se sigue el movimiento de salida registrado por Almacén.' : 'Ruta de adquisición: se sigue la documentación vinculada a la compra o al servicio.' ?></p>
                <div class="timeline"><?php foreach ($stages as $stage): ?><div class="stage <?= $stage[1] ? 'done' : '' ?>"><i><?= $stage[1] ? '✓' : '○' ?></i><span><?= e($stage[0]) ?></span></div><?php endforeach; ?></div>
            </section>
            <div class="grid">
                <section class="card">
                    <h2>Resumen de la solicitud</h2>
                    <dl>
                        <dt>Número de pedido</dt>
                        <dd><?= e($result['pedido']['NRO_PEDIDO']) ?></dd>
                        <dt>Unidad orgánica</dt>
                        <dd><?= e($result['pedido']['UNIDAD_LABEL']) ?></dd>
                        <dt>Tipo</dt>
                        <dd><?= e($result['pedido']['TIPO_LABEL']) ?></dd>
                        <dt>Fecha de emisión</dt>
                        <dd><?= e($date($result['pedido']['FEC_EMISIO'])) ?></dd>
                        <dt>Prioridad</dt>
                        <dd><?= e($result['pedido']['PRIORIDAD_LABEL']) ?></dd>
                        <dt>Estado en Logística</dt>
                        <dd><?= e($result['pedido']['ESTADO_LABEL']) ?></dd>
                    </dl>
                </section>
                <section class="card">
                    <h2>Documentos vinculados</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Número / referencia</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Pedido</td>
                                <td><?= e($result['pedido']['NRO_PEDIDO']) ?></td>
                                <td><?= e($date($result['pedido']['FEC_EMISIO'])) ?></td>
                            </tr><?php if ($result['cotizacion']): ?><tr>
                                    <td>Cotización / expediente</td>
                                    <td><?= e($result['cotizacion']['NRO_EXPE']) ?></td>
                                    <td><?= e($date($result['cotizacion']['FEC_COTIZA'])) ?></td>
                                </tr><?php endif; ?><?php if ($result['certificacion']): ?><tr>
                                    <td>Certificación</td>
                                    <td><?= e($result['certificacion']['CODCER']) ?></td>
                                    <td><?= e($date($result['certificacion']['FECHA'])) ?></td>
                                </tr><?php endif; ?><?php if ($result['orden_compra']): ?><tr>
                                    <td>Orden de compra</td>
                                    <td><?= e($result['orden_compra']['NRO_ORDCMP']) ?></td>
                                    <td><?= e($date($result['orden_compra']['FEC_EMISIO'])) ?></td>
                                </tr><?php endif; ?><?php if ($result['orden_servicio']): ?><tr>
                                    <td>Orden de servicio</td>
                                    <td><?= e($result['orden_servicio']['NRO_SERVIC']) ?></td>
                                    <td><?= e($date($result['orden_servicio']['FEC_EMISIO'])) ?></td>
                                </tr><?php endif; ?><?php if ($result['comprobante']): ?><tr>
                                    <td>Factura o boleta</td>
                                    <td><?= e($result['comprobante']['DOCSER'] . '-' . $result['comprobante']['DOCNRO']) ?></td>
                                    <td><?= e($date($result['comprobante']['FECCOMP'])) ?></td>
                                </tr><?php endif; ?><?php if ($result['contable']): ?><tr>
                                    <td>Registro contable</td>
                                    <td><?= e($result['contable']['DOC1']) ?></td>
                                    <td><?= e($date($result['contable']['FECT'])) ?></td>
                                </tr><?php endif; ?><?php if ($result['pago']): ?><tr>
                                    <td>Pago</td>
                                    <td><?= e($result['pago']['CMPBTE'] . ' ' . $result['pago']['DOC1']) ?></td>
                                    <td><?= e($date($result['pago']['FCONTABLE'])) ?></td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </section>
            </div>
            <section class="card sustentacion">
                <h2>Sustentación general</h2>
                <p><?= trim($result['pedido']['COMENTARIO'] ?? '') !== '' ? nl2br(e($result['pedido']['COMENTARIO'])) : 'No registrada en Avalon para este pedido.' ?></p>
            </section>
            <?php if ($isWarehouse): ?><section class="card">
                    <h2>Movimientos de retiro de almacén</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Movimiento</th>
                                <th>Fecha</th>
                                <th>Almacén</th>
                                <th>Cantidad retirada</th>
                                <th>Transacción</th>
                                <th>Estado Avalon</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($result['retiros_almacen'] as $movimiento): ?><tr>
                                    <td><?= e($movimiento['IDMOVIM']) ?></td>
                                    <td><?= e($date($movimiento['FECHA'])) ?></td>
                                    <td><?= e($movimiento['NUMALM'] ?: '—') ?></td>
                                    <td><?= e($movimiento['CANTIDAD']) ?></td>
                                    <td><?= e($movimiento['IDTRANSAC'] . ' · tipo ' . $movimiento['TIPO_MOV']) ?></td>
                                    <td><?= e($movimiento['ESTMOV'] ?: 'Sin código de estado') ?></td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </section><?php endif; ?>
            <section class="card">
                <h2>Detalle solicitado</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Recurso</th>
                            <th>Unidad</th>
                            <th>Cantidad</th>
                            <th>Centro de costo</th>
                            <th>Costos ABC</th>
                            <th>Actividad presupuestal</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($result['detalle'] as $item): ?><tr>
                                <td><?= e($item['DESC_MAT']) ?></td>
                                <td><?= e($item['UNIMED']) ?></td>
                                <td><?= e($item['CAN_ORDENA']) ?></td>
                                <td><?= e($item['CENTRO_COSTO']) ?></td>
                                <td><?= e($item['COSTO_ABC']) ?></td>
                                <td><?= e($item['ACTIVIDAD_PRESUPUESTAL']) ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </section>
            <?php endif; ?>
        <section class="recent">
            <h2>Búsquedas recientes</h2><?php if ($recent): ?><ul><?php foreach ($recent as $item): ?><li><b><?= e($item['termino']) ?></b><span><?= e($item['tipo']) ?> · <?= e($item['creado_en']) ?></span></li><?php endforeach; ?></ul><?php else: ?><p>Aún no hay consultas registradas.</p><?php endif; ?>
        </section>
    </main>
    <script>
        (() => {
            const button = document.getElementById('menu-toggle');
            const key = 'avalon-menu-contraido';
            const apply = (collapsed) => {
                document.body.classList.toggle('menu-contraido', collapsed);
                button.setAttribute('aria-expanded', String(!collapsed));
                button.setAttribute('aria-label', collapsed ? 'Ampliar menú' : 'Contraer menú');
                button.textContent = collapsed ? '›' : '☰';
            };
            apply(localStorage.getItem(key) === '1');
            button.addEventListener('click', () => {
                const collapsed = !document.body.classList.contains('menu-contraido');
                localStorage.setItem(key, collapsed ? '1' : '0');
                apply(collapsed);
            });
        })();
    </script>
</body>

</html>
