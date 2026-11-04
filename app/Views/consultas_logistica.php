<?php
$titulos=['almacen'=>'Almacén · Artículos y solicitudes','cuentas'=>'Consulta por cuenta','proveedores'=>'Proveedores'];
$pageTitle=$titulos[$modo]; $activeNav=$modo; $breadcrumb='Logística / '.$pageTitle;
$pageDescription='Explora el catálogo y consulta los pedidos y compras dentro de tu alcance.';
require APP_ROOT.'/app/Views/partials/header.php';
$fecha=static function($v){$d=DateTime::createFromFormat('!Ymd',(string)$v);return $d?$d->format('d/m/Y'):'—';};
$numero=static fn($v)=>is_numeric($v)?number_format((float)$v,2,'.',','):'—';
?>
<style>
.log-query{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:20px}.log-query label{display:grid;gap:6px;flex:1;min-width:180px}.log-query input{width:100%;padding:11px;border:1px solid #c6d4e0;border-radius:8px}.log-table{width:100%;border-collapse:collapse;min-width:850px}.log-table td,.log-table th{padding:13px 12px;text-align:left;border-bottom:1px solid #dce6ef;vertical-align:top}.log-table th{background:#edf4f8}.log-table small{display:block;color:#62758a;margin-top:4px}.log-table a{color:#0875d1}.log-pager{display:flex;justify-content:space-between;gap:20px;margin-top:18px}.log-order+.log-order{border-top:1px solid #dce6ef;margin-top:10px;padding-top:10px}.log-note{padding:12px 16px;background:#edf4f8;border-radius:8px;margin:12px 0 20px}.log-query button{padding:12px 20px}.log-table .log-money{white-space:nowrap}
</style>
<?php if (!$seleccion): ?>
<section class="card">
<form method="get" class="log-query"><input type="hidden" name="r" value="<?= e($modo) ?>"><label>Buscar por nombre o código<input name="q" value="<?= e($q) ?>" placeholder="Escribe un nombre o código"></label><button type="submit">Buscar</button></form>
<p><?= $totalCatalogo ?> registros del catálogo. El historial muestra únicamente documentos permitidos.</p>
<div class="table-scroll"><table class="log-table"><thead><tr><th>Código<?= $modo==='proveedores'?' / RUC':'' ?></th><th>Nombre</th><?php if($modo==='almacen'): ?><th>Tipo / unidad</th><?php endif; ?><th>Consulta</th></tr></thead><tbody>
<?php foreach($coincidencias as $r): ?><tr><td><?= e($r['codigo']) ?></td><td><?= e($r['nombre']) ?></td><?php if($modo==='almacen'): ?><td><?= e($r['tipo'].' · '.$r['unidad']) ?></td><?php endif; ?><td><a href="<?= e(url($modo,['codigo'=>$r['codigo']])) ?>">Ver historial →</a></td></tr><?php endforeach; ?>
<?php if(!$coincidencias): ?><tr><td colspan="4">No hay coincidencias. Prueba con otro nombre o código.</td></tr><?php endif; ?>
</tbody></table></div>
<?php $total=$totalCatalogo; $params=['q'=>$q]; ?>
<?php else: ?>
<section class="card"><a href="<?= e(url($modo)) ?>">← Volver al catálogo</a><h2><?= e($seleccion['nombre']) ?></h2><p><?= e($seleccion['codigo']) ?><?php if(isset($seleccion['unidad'])): ?> · Unidad: <?= e($seleccion['unidad']) ?><?php endif; ?></p>
<?php if($modo==='almacen'): ?><p>Cuenta configurada en el catálogo: <strong><?= e($seleccion['cuenta'] ?: 'No registrada') ?></strong>. Esta configuración no sustituye una validación presupuestal.</p><?php endif; ?>
<?php if($modo==='cuentas'): ?>
<div class="log-note">El historial indica dónde se utilizó la cuenta; no acredita autorización para nuevas compras. Las coincidencias del catálogo se muestran por separado.</div>
<details><summary>Recursos con esta cuenta configurada (<?= count($historial['configurados']) ?>)</summary>
<?php if(!$historial['configurados']): ?><p>No hay una coincidencia directa con la cuenta del catálogo. Esto no significa que no se pueda utilizar.</p><?php else: ?><ul><?php foreach($historial['configurados'] as $r): ?><li><a href="<?= e(url('almacen',['codigo'=>$r['codigo']])) ?>"><?= e($r['nombre']) ?></a></li><?php endforeach; ?></ul><?php endif; ?></details>
<?php endif; ?>
<form method="get" class="log-query"><input type="hidden" name="r" value="<?= e($modo) ?>"><input type="hidden" name="codigo" value="<?= e($codigo) ?>"><label>Año del pedido<input name="anio" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="<?= e($anio) ?>" placeholder="Todos los años"></label><button type="submit">Filtrar</button></form>
<div class="log-note">Solicitante según el registro de Avalon. Las compras se vinculan al detalle del pedido. Sin vínculo no se atribuye un proveedor a una entrega. Las cantidades de salida e ingreso son acumulados del pedido, no el saldo actual del almacén.</div>
<h3>Pedidos y compras relacionadas · <?= $historial['total'] ?> detalles</h3>
<div class="table-scroll"><table class="log-table"><thead><tr><th>Pedido / fecha</th><th>Solicitante / unidad</th><th>Recurso</th><th>Cuenta / actividad</th><th>Cantidades</th><th>Compras y proveedor</th></tr></thead><tbody>
<?php foreach($historial['filas'] as $fila): $p=$fila['pedido'];$d=$fila['detalle'];$recurso=$fila['recurso']; ?>
<tr><td><?= e($p['NRO_PEDIDO']) ?><small><?= e($fecha($p['FEC_EMISIO'])) ?></small></td><td><?= e($p['USRNOMBRE'] ?: 'Nombre no registrado') ?><small>Usuario: <?= e($p['USRCODIGO'] ?: '—') ?></small><small>Unidad: <?= e($p['CODUNIORG'].' · '.($p['UNIDAD_NOMBRE'] ?? '')) ?></small></td>
<td><a href="<?= e(url('almacen',['codigo'=>$recurso['codigo']])) ?>"><?= e($recurso['nombre']) ?></a><small><?= e($recurso['unidad']) ?></small></td>
<td><a href="<?= e(url('cuentas',['codigo'=>$d['CTA_CON']])) ?>"><?= e(($d['CTA_CON'] ?: 'No registrada').' · '.($d['CUENTA_NOMBRE'] ?? '')) ?></a><small>Actividad: <?= e($d['COD_PROY'] ?: '—') ?></small></td>
<td>Solicitado: <?= e($numero($d['CAN_ORDENA'])) ?><?php if(($recurso['tipo'] ?? '')!=='Servicio'): ?><small>Salida: <?= e($numero($d['CAN_SALI'])) ?></small><small>Ingreso: <?= e($numero($d['CAN_INGRE'])) ?></small><?php endif; ?></td>
<td><?php if(!$fila['ordenes']): ?>Sin compra vinculada al detalle<?php endif; ?><?php foreach($fila['ordenes'] as $o): ?><div class="log-order"><?php if(isset($o['url'])): ?><a href="<?= e($o['url']) ?>"><?php endif; ?><?= e(($o['tipo']==='orden_compra'?'OC ':'OS ').$o['numero']) ?><?php if(isset($o['url'])): ?></a><?php endif; ?><small><?= e($fecha($o['FEC_EMISIO'])) ?> · Cantidad: <?= e($numero($o['cantidad'])) ?></small><a href="<?= e(url('proveedores',['codigo'=>$o['CODPROV']])) ?>"><?= e($o['proveedor']['nombre']) ?></a><small>Código: <?= e($o['CODPROV']) ?></small><?php if($mostrarMontos): ?><small class="log-money">Importe del detalle sin IGV: <?= e(($o['MONEDA']==='01'?'S/':$o['MONEDA']).' '.$numero($o['importe'])) ?></small><?php endif; ?></div><?php endforeach; ?></td></tr>
<?php endforeach; ?>
<?php if(!$historial['filas']): ?><tr><td colspan="6">No hay detalles vinculados dentro de tu alcance y los filtros seleccionados.</td></tr><?php endif; ?>
</tbody></table></div>
<p>Abre una orden para consultar sus datos. La consulta documental permite seguir sus facturas y pagos.</p>
<?php $total=$historial['total'];$params=['codigo'=>$codigo,'anio'=>$anio]; ?>
<?php endif; ?>
<nav class="log-pager" aria-label="Paginación"><?php if($pagina>1): ?><a href="<?= e(url($modo,array_merge($params,['pagina'=>$pagina-1]))) ?>">← Anterior</a><?php endif; ?><span>Página <?= $pagina ?> · <?= $total ?> registros</span><?php if($pagina*25<$total): ?><a href="<?= e(url($modo,array_merge($params,['pagina'=>$pagina+1]))) ?>">Siguiente →</a><?php endif; ?></nav>
</section>
<?php require APP_ROOT.'/app/Views/partials/footer.php'; ?>
