<?php
$flash = $flash ?? takeFlash();
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
$documentoBuscado = $result['documento_buscado'] ?? null;
$documentosAnulados = $result['documentos_anulados'] ?? [];
$busquedaAnulada = is_array($documentoBuscado) && strtoupper(trim((string)($documentoBuscado['estado'] ?? ''))) === 'A';
$tiposBusqueda = ['todo' => 'documento', 'pedido' => 'pedido', 'cotizacion' => 'cotización', 'certificacion' => 'certificación', 'orden_compra' => 'orden de compra', 'orden_servicio' => 'orden de servicio', 'comprobante' => 'factura o boleta', 'devengado' => 'devengado', 'pago' => 'pago'];
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
<?php
$pageTitle = 'Seguimiento documental';
$pageDescription = 'Consulta el avance de un pedido y sus documentos vinculados.';
$breadcrumb = 'Logística / Seguimiento documental';
$activeNav = 'seguimiento';
require APP_ROOT . '/app/Views/partials/header.php';
?>
        <?php if ($flash): ?><div class="alert"><?= e($flash['message']) ?></div><?php endif; ?>
        <section class="search-card">
            <h2>Encuentra un documento</h2>
            <p>Busca por pedido o por cualquier documento vinculado ya sincronizado.</p>
            <form><input type="hidden" name="r" value="seguimiento">
                <input type="hidden" name="tipo" id="tipo-busqueda" value="<?= e($tipo) ?>">
                <div class="types"><button type="button" class="<?= $tipo === 'todo' ? 'selected' : '' ?>" data-tipo="todo">Todo</button><button type="button" class="<?= $tipo === 'pedido' ? 'selected' : '' ?>" data-tipo="pedido">Pedido</button><button type="button" class="<?= $tipo === 'cotizacion' ? 'selected' : '' ?>" data-tipo="cotizacion">Cotización</button><button type="button" class="<?= $tipo === 'certificacion' ? 'selected' : '' ?>" data-tipo="certificacion">Certificación</button><button type="button" class="<?= $tipo === 'orden_compra' ? 'selected' : '' ?>" data-tipo="orden_compra">Orden de compra</button><button type="button" class="<?= $tipo === 'orden_servicio' ? 'selected' : '' ?>" data-tipo="orden_servicio">Orden de servicio</button><button type="button" class="<?= $tipo === 'comprobante' ? 'selected' : '' ?>" data-tipo="comprobante">Factura o boleta</button><button type="button" class="<?= $tipo === 'devengado' ? 'selected' : '' ?>" data-tipo="devengado">Devengado</button><button type="button" class="<?= $tipo === 'pago' ? 'selected' : '' ?>" data-tipo="pago">Pago</button></div>
                <div class="search-line"><input name="q" aria-label="Número de documento" value="<?= e($term) ?>" placeholder="Ejemplo: 2602201" required><button>Buscar seguimiento</button><a class="clear-search" href="<?= e(url('seguimiento')) ?>">Limpiar</a></div>
            </form>
        </section>
        <?php if ($term !== '' && !$error && !$result): ?><section class="empty">
                <h2>No se encontró <?= e($tiposBusqueda[$tipo] ?? 'el documento') ?> <?= e($term) ?></h2>
                <p>Verifique el número o actualice primero las notas correspondientes desde Avalon.</p>
            </section><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($result): ?>
            <?php if ($busquedaAnulada): ?><div class="alert error"><b>Documento anulado en Avalon.</b> <?= e(ucfirst($tiposBusqueda[$documentoBuscado['tipo']] ?? $documentoBuscado['tipo'])) ?> <?= e($documentoBuscado['numero']) ?> no forma parte de la ruta vigente del pedido <?= e($result['pedido']['NRO_PEDIDO']) ?>.</div><?php endif; ?>
            <?php if ($isCancelled): ?><div class="alert error"><b>Nota de pedido anulada.</b> Esta nota no debe considerarse como un proceso vigente.</div><?php endif; ?>
            <?php if (!$busquedaAnulada && !$isCancelled && count($documentosAnulados) > 0): ?><div class="alert error">Se encontraron <?= e((string)count($documentosAnulados)) ?> documento(s) anulado(s). No se muestran en el detalle ni se consideran como avance válido.</div><?php endif; ?>
            <section class="status-card">
                <div class="status-pill">▣ <?= e($final) ?></div>
                <div><b><?= $isCancelled ? 'Avalon registra el pedido como anulado' : ($resumenDocumental['comprobantes'] > 0 ? $resumenDocumental['comprobantes'] . ' comprobante(s), ' . $resumenDocumental['devengados'] . ' devengado(s) y ' . $resumenDocumental['pagos'] . ' pago(s) encontrados' : 'Estado calculado con los documentos encontrados') ?></b>
                    <p>La ruta diferencia órdenes de compra y órdenes de servicio.</p>
                </div>
                <?php if (!empty($esAdministrador)): ?><button type="button" id="mostrar-historial" class="history-button">Historial de cambios</button><?php endif; ?>
            </section>
            <?php if (!empty($esAdministrador)): ?><section class="card" id="historial-cambios" hidden>
                <h2>Historial de cambios</h2>
                <p>Últimos cambios detectados durante las sincronizaciones con Avalon.</p>
                <?php if ($historial): ?><div class="table-scroll"><table>
                    <thead><tr><th>Fecha</th><th>Documento</th><th>Cambio</th><th>Campo</th><th>Antes</th><th>Después</th><th>Actualizado por</th></tr></thead>
                    <tbody><?php foreach ($historial as $cambio): ?><tr>
                        <td><?= e($cambio['creado_en']) ?></td>
                        <td><?= e(ucfirst(str_replace('_', ' ', $cambio['tipo_documento'])) . ($cambio['numero_documento'] ? ' · ' . $cambio['numero_documento'] : '')) ?></td>
                        <td><span class="history-event history-<?= e(strtolower($cambio['evento'])) ?>"><?= e(ucfirst(strtolower($cambio['evento']))) ?></span></td>
                        <td><?= e($cambio['campo'] ?: 'Documento') ?></td>
                        <td><?= e($cambio['valor_anterior'] ?? '—') ?></td>
                        <td><?= e($cambio['valor_nuevo'] ?? '—') ?></td>
                        <td><?= e($cambio['actualizado_por']) ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div><?php else: ?><p>Aún no se han detectado cambios para esta nota desde que se activó el historial.</p><?php endif; ?>
            </section><?php endif; ?>
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
                            <th>Cuenta contable</th>
                            <th>Centro de costo</th>
                            <th>Costos ABC</th>
                            <th>Actividad presupuestal</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($result['detalle'] as $item): ?><tr>
                                <td><?= e($item['DESC_MAT']) ?></td>
                                <td><?= e($item['UNIMED']) ?></td>
                                <td><?= e($item['CAN_ORDENA']) ?></td>
                                <td><?= e($item['CUENTA_CONTABLE']) ?></td>
                                <td><?= e($item['CENTRO_COSTO']) ?></td>
                                <td><?= e($item['COSTO_ABC']) ?></td>
                                <td><?= e($item['ACTIVIDAD_PRESUPUESTAL']) ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table></div>
            </section>
            <?php endif; ?>
        <section class="recent">
            <h2>Búsquedas recientes</h2><?php if ($recent): ?><ul><?php foreach ($recent as $item): ?><li><b><?= e($item['termino']) ?></b><span><?= e($item['tipo']) ?> · <?= e($item['creado_en']) ?></span></li><?php endforeach; ?></ul><?php else: ?><p>Aún no hay consultas registradas.</p><?php endif; ?>
        </section><script>
(function () {
    var tipoInput = document.getElementById('tipo-busqueda');
    document.querySelectorAll('[data-tipo]').forEach(function (option) { option.addEventListener('click', function () { tipoInput.value = option.dataset.tipo; document.querySelectorAll('[data-tipo]').forEach(function (item) { item.classList.toggle('selected', item === option); }); }); });
    var historyButton = document.getElementById('mostrar-historial');
    var historyPanel = document.getElementById('historial-cambios');
    if (historyButton && historyPanel) historyButton.addEventListener('click', function () { historyPanel.hidden = !historyPanel.hidden; historyButton.textContent = historyPanel.hidden ? 'Historial de cambios' : 'Ocultar historial'; });
}());
</script>
<?php require APP_ROOT . '/app/Views/partials/footer.php'; ?>
