<?php
$flash = takeFlash();
$date = function ($value) {
    if (!$value || $value === '00000000') return '—';
    $date = DateTime::createFromFormat('Ymd', $value);
    return $date ? $date->format('d/m/Y') : $value;
};
$ordenes = $result['ordenes'] ?? [];
$cotizaciones = $result['cotizaciones'] ?? [];
$certificaciones = $result['certificaciones'] ?? [];
$avance = $result['avance'] ?? [];
$resumenDocumental = $result['resumen_documental'] ?? ['comprobantes' => 0, 'devengados' => 0, 'pagos' => 0];
$isService = !empty($result['orden_servicio']);
$isWarehouse = !empty($result) && !empty($result['pedido']['ES_RETIRO_ALMACEN']);
$isCancelled = !empty($result) && ($result['pedido']['EST_PEDIDO'] ?? '') === 'A';
$orderLabel = count($ordenes) > 1 ? 'Órdenes emitidas' : ($isService ? 'Orden de servicio' : 'Orden de compra');
$stages = $isWarehouse
    ? [['label' => 'Pedido', 'estado' => 'complete', 'cantidad' => '1/1', 'nota' => ''], ['label' => 'Atención de almacén', 'estado' => !empty($result['retiros_almacen']) ? 'complete' : 'pending', 'cantidad' => !empty($result['retiros_almacen']) ? '1/1' : '0/1', 'nota' => ''], ['label' => 'Retiro de almacén', 'estado' => !empty($result['retiros_almacen']) ? 'complete' : 'pending', 'cantidad' => !empty($result['retiros_almacen']) ? '1/1' : '0/1', 'nota' => '']]
    : [
        ['label' => 'Pedido', 'estado' => $isCancelled ? 'issue' : 'complete', 'cantidad' => '1/1', 'nota' => $isCancelled ? 'Pedido anulado' : ''],
        array_merge(['label' => 'Cotización'], $avance['cotizaciones'] ?? ['estado' => 'pending', 'cantidad' => '—', 'nota' => '']),
        array_merge(['label' => 'Certificación'], $avance['certificaciones'] ?? ['estado' => 'pending', 'cantidad' => '—', 'nota' => '']),
        array_merge(['label' => $orderLabel], $avance['ordenes'] ?? ['estado' => 'pending', 'cantidad' => '—', 'nota' => '']),
        array_merge(['label' => 'Factura o boleta'], $avance['comprobantes'] ?? ['estado' => 'pending', 'cantidad' => '—', 'nota' => '']),
        array_merge(['label' => 'Devengado'], $avance['devengados'] ?? ['estado' => 'pending', 'cantidad' => '—', 'nota' => '']),
        array_merge(['label' => 'Pago'], $avance['pagos'] ?? ['estado' => 'pending', 'cantidad' => '—', 'nota' => '']),
    ];
$estadoFinal = $avance['pagos']['estado'] ?? 'pending';
$final = $isCancelled ? 'Pedido anulado' : ($isWarehouse ? (!empty($result['retiros_almacen']) ? 'Retiro de almacén registrado' : 'Pendiente de atención de almacén') : ($estadoFinal === 'complete' ? 'Proceso completado' : ($estadoFinal === 'issue' ? 'Proceso con incidencias' : ($estadoFinal === 'partial' ? 'Avance parcial' : 'En proceso'))));
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/app.css?v=20260914">
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
            <p>Busca por pedido, orden de compra u orden de servicio.</p>
            <form><input type="hidden" name="r" value="seguimiento">
                <input type="hidden" name="tipo" id="tipo-busqueda" value="<?= e($tipo) ?>">
                <div class="types"><button type="button" class="<?= $tipo === 'todo' ? 'selected' : '' ?>" data-tipo="todo">Todo</button><button type="button" class="<?= $tipo === 'pedido' ? 'selected' : '' ?>" data-tipo="pedido">Pedido</button><button type="button" class="<?= $tipo === 'orden_compra' ? 'selected' : '' ?>" data-tipo="orden_compra">Orden de compra</button><button type="button" class="<?= $tipo === 'orden_servicio' ? 'selected' : '' ?>" data-tipo="orden_servicio">Orden de servicio</button></div>
                <div class="search-line"><input name="q" aria-label="Número de documento" value="<?= e($term) ?>" placeholder="Ejemplo: 2602201" required><button>Buscar seguimiento</button><a class="clear-search" href="<?= e(url('seguimiento')) ?>">Limpiar</a></div>
            </form>
        </section>
        <?php if ($term !== '' && !$error && !$result): ?><section class="empty">
                <h2>No se encontró el pedido <?= e($term) ?></h2>
                <p>Verifique el número o consulte un pedido existente de Logística.</p>
            </section><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($result): ?><section class="status-card">
                <div class="status-pill">▣ <?= e($final) ?></div>
                <div><b><?= $isCancelled ? 'Avalon registra el pedido como anulado' : ($resumenDocumental['comprobantes'] > 0 ? $resumenDocumental['comprobantes'] . ' comprobante(s), ' . $resumenDocumental['devengados'] . ' devengado(s) y ' . $resumenDocumental['pagos'] . ' pago(s) encontrados' : 'Estado calculado con los documentos encontrados') ?></b>
                    <p>La ruta diferencia órdenes de compra y órdenes de servicio.</p>
                </div>
            </section>
            <section class="card">
                <h2>Ruta del proceso</h2>
                <p class="route-note"><?= $isWarehouse ? 'Ruta de existencia: se sigue el movimiento de salida registrado por Almacén.' : 'Ruta de adquisición: se sigue la documentación vinculada a la compra o al servicio.' ?></p>
                <div class="timeline"><?php foreach ($stages as $stage): ?><div class="stage <?= e($stage['estado']) ?>" title="<?= e($stage['nota']) ?>"><small class="stage-count"><?= e($stage['cantidad']) ?></small><i><?= $stage['estado'] === 'complete' ? '✓' : ($stage['estado'] === 'partial' ? '!' : ($stage['estado'] === 'issue' ? '×' : '○')) ?></i><span><?= e($stage['label']) ?></span><?php if ($stage['nota'] !== ''): ?><em><?= e($stage['nota']) ?></em><?php endif; ?></div><?php endforeach; ?></div>
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
                    <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de documentos; desplazamiento horizontal disponible"><table>
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Número / referencia</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Pedido</td>
                                <td><?= e($result['pedido']['NRO_PEDIDO']) ?></td>
                                <td><?= e($date($result['pedido']['FEC_EMISIO'])) ?></td>
                                <td><?= e($result['pedido']['ESTADO_LABEL']) ?></td>
                            </tr><?php foreach ($cotizaciones as $cotizacion): ?><tr>
                                    <td><b>Cotización / expediente</b></td>
                                    <td><b><?= e($cotizacion['NRO_EXPE']) ?></b></td>
                                    <td><?= e($date($cotizacion['FEC_COTIZA'])) ?></td>
                                    <td><?= e('Código Avalon: ' . ($cotizacion['ESTADO'] ?: '—')) ?></td>
                                </tr><?php foreach ($cotizacion['CERTIFICACIONES'] as $certificacion): ?><tr>
                                    <td>↳ Certificación</td>
                                    <td><?= e($certificacion['CODCER']) ?></td>
                                    <td><?= e($date($certificacion['FECHA'])) ?></td>
                                    <td><?= e('Código Avalon: ' . ($certificacion['ESTADO'] ?? '—')) ?></td>
                                </tr><?php endforeach; ?><?php foreach ($cotizacion['ORDENES'] as $orden): ?><tr>
                                    <td>↳ <b><?= e($orden['TIPO']) ?></b></td>
                                    <td><b><?= e($orden['NUMERO']) ?></b></td>
                                    <td><?= e($date($orden['FECHA'])) ?></td>
                                    <td><?= e('Código Avalon: ' . ($orden['ESTADO'] ?: '—')) ?></td>
                                </tr><?php if (!$orden['COMPROBANTES']): ?><tr>
                                    <td>　↳ Comprobante</td><td>Sin factura o boleta registrada</td><td>—</td><td>Pendiente</td>
                                </tr><?php endif; ?><?php foreach ($orden['COMPROBANTES'] as $comprobante): ?><tr>
                                    <td>　↳ Factura o boleta</td>
                                    <td><?= e($comprobante['DOCSER'] . '-' . $comprobante['DOCNRO']) ?></td>
                                    <td><?= e($date($comprobante['FECCOMP'])) ?></td>
                                    <td><?= e('Código Avalon: ' . ($comprobante['ESTADO'] ?: '—')) ?></td>
                                </tr><?php foreach ($comprobante['DEVENGADOS'] as $devengado): ?><tr>
                                    <td>　　　↳ Devengado</td>
                                    <td><?= e($devengado['DOC1'] ?: 'Registrado en Contabilidad') ?></td>
                                    <td><?= e($date($devengado['FECT'])) ?></td>
                                    <td>Registrado</td>
                                </tr><?php endforeach; ?><?php foreach ($comprobante['PAGOS'] as $pago): ?><tr>
                                    <td>　　　↳ Pago</td>
                                    <td><?= e($pago['CMPBTE'] . ' ' . $pago['DOC1'] . ' · ' . $comprobante['DOCSER'] . '-' . $comprobante['DOCNRO']) ?></td>
                                    <td><?= e($date($pago['FCONTABLE'])) ?></td>
                                    <td><?= e('Código Avalon: ' . ($pago['ESTADO'] ?: '—')) ?></td>
                                </tr><?php endforeach; ?><?php endforeach; ?><?php foreach ($orden['DEVENGADOS_SIN_COMPROBANTE'] as $devengado): ?><tr>
                                    <td>　↳ Devengado sin comprobante asociado</td>
                                    <td><?= e($devengado['DOC1'] ?: 'Registrado en Contabilidad') ?></td>
                                    <td><?= e($date($devengado['FECT'])) ?></td>
                                    <td>Registrado</td>
                                </tr><?php endforeach; ?><?php endforeach; ?><?php endforeach; ?>
                            <?php if (!$cotizaciones): ?><?php foreach ($certificaciones as $certificacion): ?><tr>
                                <td>Certificación</td>
                                <td><?= e($certificacion['CODCER']) ?></td>
                                <td><?= e($date($certificacion['FECHA'])) ?></td>
                                <td><?= e('Código Avalon: ' . ($certificacion['ESTADO'] ?? '—')) ?></td>
                            </tr><?php endforeach; ?><?php foreach ($ordenes as $orden): ?><tr>
                                <td><b><?= e($orden['TIPO']) ?></b></td>
                                <td><b><?= e($orden['NUMERO']) ?></b></td>
                                <td><?= e($date($orden['FECHA'])) ?></td>
                                <td><?= e('Código Avalon: ' . ($orden['ESTADO'] ?: '—')) ?></td>
                            </tr><?php if (!$orden['COMPROBANTES']): ?><tr>
                                <td>↳ Comprobante</td><td>Sin factura o boleta registrada</td><td>—</td><td>Pendiente</td>
                            </tr><?php endif; ?><?php foreach ($orden['COMPROBANTES'] as $comprobante): ?><tr>
                                <td>↳ Factura o boleta</td>
                                <td><?= e($comprobante['DOCSER'] . '-' . $comprobante['DOCNRO']) ?></td>
                                <td><?= e($date($comprobante['FECCOMP'])) ?></td>
                                <td><?= e('Código Avalon: ' . ($comprobante['ESTADO'] ?: '—')) ?></td>
                            </tr><?php foreach ($comprobante['DEVENGADOS'] as $devengado): ?><tr>
                                <td>　↳ Devengado</td>
                                <td><?= e($devengado['DOC1'] ?: 'Registrado en Contabilidad') ?></td>
                                <td><?= e($date($devengado['FECT'])) ?></td>
                                <td>Registrado</td>
                            </tr><?php endforeach; ?><?php foreach ($comprobante['PAGOS'] as $pago): ?><tr>
                                <td>　↳ Pago</td>
                                <td><?= e($pago['CMPBTE'] . ' ' . $pago['DOC1'] . ' · ' . $comprobante['DOCSER'] . '-' . $comprobante['DOCNRO']) ?></td>
                                <td><?= e($date($pago['FCONTABLE'])) ?></td>
                                <td><?= e('Código Avalon: ' . ($pago['ESTADO'] ?: '—')) ?></td>
                            </tr><?php endforeach; ?><?php endforeach; ?><?php foreach ($orden['DEVENGADOS_SIN_COMPROBANTE'] as $devengado): ?><tr>
                                <td>↳ Devengado sin comprobante asociado</td>
                                <td><?= e($devengado['DOC1'] ?: 'Registrado en Contabilidad') ?></td>
                                <td><?= e($date($devengado['FECT'])) ?></td>
                                <td>Registrado</td>
                            </tr><?php endforeach; ?><?php endforeach; ?><?php endif; ?>
                        </tbody>
                    </table></div>
                </section>
            </div>
            <section class="card sustentacion">
                <h2>Sustentación general</h2>
                <p><?= trim($result['pedido']['COMENTARIO'] ?? '') !== '' ? nl2br(e($result['pedido']['COMENTARIO'])) : 'No registrada en Avalon para este pedido.' ?></p>
            </section>
            <?php if ($isWarehouse): ?><section class="card">
                    <h2>Movimientos de retiro de almacén</h2>
                    <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de documentos; desplazamiento horizontal disponible"><table>
                        <thead>
                            <tr>
                                <th>Movimiento</th>
                                <th>Ítem / recurso retirado</th>
                                <th>Fecha</th>
                                <th>Almacén</th>
                                <th>Cantidad retirada</th>
                                <th>Transacción</th>
                                <th>Estado Avalon</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($result['retiros_almacen'] as $movimiento): ?><tr>
                                    <td><?= e($movimiento['IDMOVIM']) ?></td>
                                    <td><?= e(($movimiento['SEC_ITEMMO'] ?: '—') . ' · ' . $movimiento['RECURSO']) ?></td>
                                    <td><?= e($date($movimiento['FECHA'])) ?></td>
                                    <td><?= e($movimiento['NUMALM'] ?: '—') ?></td>
                                    <td><?= e($movimiento['CANTIDAD']) ?></td>
                                    <td><?= e($movimiento['IDTRANSAC'] . ' · tipo ' . $movimiento['TIPO_MOV']) ?></td>
                                    <td><?= e($movimiento['ESTMOV'] ?: 'Sin código de estado') ?></td>
                                </tr><?php endforeach; ?></tbody>
                    </table></div>
                </section><?php endif; ?>
            <section class="card">
                <h2>Detalle solicitado</h2>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de documentos; desplazamiento horizontal disponible"><table>
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
                </table></div>
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
            const tipoInput = document.getElementById('tipo-busqueda');
            document.querySelectorAll('[data-tipo]').forEach((option) => option.addEventListener('click', () => {
                tipoInput.value = option.dataset.tipo;
                document.querySelectorAll('[data-tipo]').forEach((item) => item.classList.toggle('selected', item === option));
            }));
        })();
    </script>
</body>

</html>
