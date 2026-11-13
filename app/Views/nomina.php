<?php
$pageTitle = 'Nómina';
$breadcrumb = 'Nómina / '.$titulos[$reporte];
$useDataTables = $reporte !== 'resumen';
$pageDescription = 'Trabajadores registrados en planillas por mes de proceso.';
$activeNav = 'nomina';
require APP_ROOT.'/app/Views/partials/header.php';
?>
<nav class="scope-actions" aria-label="Reportes de nómina">
    <?php foreach ($titulos as $ruta=>$titulo): ?>
        <a class="<?= $reporte === $ruta ? 'selected' : '' ?>" href="<?= e(url('nomina',['reporte'=>$ruta,'mes'=>$mes,'anio'=>$anio])) ?>"><?= e($titulo) ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($reporte === 'resumen'): ?>
<section class="card">
    <h2>Resumen mensual de planillas</h2>
    <?php if ($error): ?><p role="alert"><?= e($error) ?></p>
    <?php elseif (!$resumen): ?><p>No hay períodos disponibles.</p>
    <?php else: ?>
    <form method="get" class="notes-filter">
        <input type="hidden" name="r" value="nomina">
        <label>Año de proceso<select name="anio">
            <?php foreach ($anios as $opcion): ?><option <?= $opcion === $anio ? 'selected' : '' ?>><?= e($opcion) ?></option><?php endforeach; ?>
        </select></label>
        <button type="submit">Consultar</button>
    </form>
    <?php
    $periodosAnio = array_filter(array_keys($resumen), static fn($p) => substr($p,0,4) === $anio);
    $corte = reset($periodosAnio);
    if ($corte):
        $ultimo = $resumen[$corte];
        $anterior = (new DateTimeImmutable($corte.'-01'))->modify('-1 month')->format('Y-m');
    ?>
    <h3>Último período registrado del año · <?= e($corte) ?></h3>
    <p><strong><?= e($ultimo['trabajadores']) ?> trabajadores</strong> en <?= e($ultimo['planillas']) ?> planillas.
    <?php if (isset($ultimo['aparecen'])): ?>
        En <?= e($anterior) ?> figuraron <?= e($resumen[$anterior]['trabajadores']) ?>.
        Al comparar ambos períodos, <?= e($ultimo['aparecen']) ?> figuran solo en el actual y <?= e($ultimo['ausentes']) ?> solo en el anterior.
    <?php endif; ?></p>
    <?php if (isset($ultimo['importes'])): ?>
    <div class="table-scroll"><table><caption>Importes registrados en planilla (S/)</caption>
    <thead><tr><th>Concepto</th><th><?= e($corte) ?></th><th><?= e($anterior) ?></th><th>Variación</th></tr></thead><tbody>
    <?php foreach ($ultimo['importes'] as $nombre=>$importe): $previo = $resumen[$anterior]['importes'][$nombre] ?? null; ?>
    <tr><th scope="row"><?= e($nombre) ?></th><td><?= number_format($importe/100,2) ?></td><td><?= $previo===null?'—':number_format($previo/100,2) ?></td><td><?= $previo===null?'—':number_format(($importe-$previo)/100,2) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; endif; ?>
    <h3>Evolución mensual</h3>
    <div class="table-scroll"><table>
        <thead><tr><th>Período</th><th>Planillas con trabajadores</th><th>Trabajadores distintos</th><th>Variación mensual</th><th>Detalle</th></tr></thead>
        <tbody>
        <?php foreach ($resumen as $periodo => $fila): if (substr($periodo, 0, 4) !== $anio) continue; ?>
            <tr><td><?= e($periodo) ?></td><td><?= e($fila['planillas']) ?></td><td><?= e($fila['trabajadores']) ?></td><td><?= isset($fila['aparecen'])?sprintf('%+d',$fila['aparecen']-$fila['ausentes']):'—' ?></td><td><a href="<?= e(url('nomina',['reporte'=>'personal','mes'=>$periodo])) ?>">Ver cambios por centro</a></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php else: ?>
<section class="card">
    <h2><?= e($titulos[$reporte]) ?></h2>
    <form method="get" class="notes-filter">
        <input type="hidden" name="r" value="nomina"><input type="hidden" name="reporte" value="<?= e($reporte) ?>">
        <label>Mes de proceso<input type="month" name="mes" required value="<?= e($mes) ?>"></label>
        <button type="submit">Consultar</button>
    </form>
    <?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php else:
        $columnas = [
            'personal'=>['Régimen','Centro de costo','Mes anterior','Mes consultado','Variación'],
            'conceptos'=>['Régimen','Tipo','Código','Concepto','Líneas','Importe registrado'],
            'distribucion'=>['Sucursal','Actividad','Cuenta','Centro de costo','Fuente','Importe distribuido','Resumen mensual Avalon','Diferencia'],
            'control'=>['Hallazgo','Sucursal | régimen | tipo | planilla','Código de trabajador','Régimen | tipo | concepto']
        ];
        $ayuda = [
            'personal'=>'Trabajadores distintos por grupo. Una persona puede aparecer en más de un centro. La comparación usa el mes calendario anterior.',
            'conceptos'=>'Importes por régimen, tipo y concepto. Las líneas de sistema y los códigos pendientes de identificar se muestran por separado.',
            'distribucion'=>'Se compara la suma distribuida por actividad, cuenta, centro y fuente con el resumen mensual de Avalon. Una diferencia requiere revisión.',
            'control'=>'Cada fila identifica un cruce pendiente de revisión. Un registro puede presentar más de un hallazgo.'
        ]; ?>
    <p><?= e($ayuda[$reporte]) ?></p>
    <?php if ($reporte === 'personal' && $filas):
        $prevMes = (new DateTimeImmutable($mes.'-01'))->modify('-1 month')->format('Y-m');
        if (isset($resumen[$prevMes],$resumen[$mes])):
        usort($filas,static fn($a,$b)=>abs($b[4])<=>abs($a[4]) ?: strcmp($a[1],$b[1]));
        $cambios = count(array_filter($filas,static fn($fila)=>$fila[4] !== 0)); ?>
        <p><strong><?= $cambios ?> de <?= count($filas) ?> grupos cambiaron</strong> entre <?= e($prevMes) ?> y <?= e($mes) ?>. Los mayores cambios aparecen primero.</p>
    <?php else: ?>
        <p>Faltan registros de planilla en uno de los dos meses para calcular la variación.</p>
        <?php foreach ($filas as &$grupo) { if (!isset($resumen[$prevMes])) $grupo[2]='—'; if (!isset($resumen[$mes])) $grupo[3]='—'; $grupo[4]='—'; } unset($grupo); ?>
    <?php endif; endif; ?>
    <div class="table-scroll"><table id="nomina-reporte" class="display">
        <thead><tr><?php foreach ($columnas[$reporte] as $campo): ?><th><?= e($campo) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($filas as $fila): ?><tr><?php foreach ($fila as $valor): ?><td><?= e($valor) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
    </table></div>
    <script src="<?= e(APP_URL) ?>assets/vendor/jquery.dataTables.min.js"></script>
    <script>
        $('#nomina-reporte').DataTable({pageLength:10,autoWidth:false,order:[],language:{
            search:'Buscar:',lengthMenu:'Mostrar _MENU_ por página',info:'_START_ a _END_ de _TOTAL_ registros',
            infoEmpty:'Sin registros',infoFiltered:'(de _MAX_ en total)',emptyTable:'Sin registros para el período seleccionado.',
            zeroRecords:'Sin coincidencias.',paginate:{previous:'Anterior',next:'Siguiente'}
        }});
    </script>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require APP_ROOT.'/app/Views/partials/footer.php'; ?>
