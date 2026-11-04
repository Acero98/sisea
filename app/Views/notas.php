<?php

$flash = takeFlash();

$esAdministradorGlobal = alcanceModulo('seguimiento',$acceso) === 'GLOBAL';

$codigoAvalon = trim(
    (string) ($acceso['avalon_codigo'] ?? '')
);

$unidadOrganica = trim(
    (string) ($acceso['unidad_organica'] ?? '')
);

$tieneCodigoAvalon = $codigoAvalon !== '';

$tieneUnidad =
    $unidadOrganica !== ''
    && $unidadOrganica !== '0000';

if ($alcance === 'mis') {
    $pageTitle = 'Mis notas de pedido';
} elseif ($esAdministradorGlobal) {
    $pageTitle = 'Notas de todas las unidades';
} else {
    $pageTitle = 'Notas de mi unidad orgánica';
}

$pageDescription =
    'Consulta paginada: solo se cargan las notas de la página actual.';

$breadcrumb = 'Logística / Notas de pedido';
$activeNav = 'notas';

$date = static function (string $value): string {
    $d = DateTime::createFromFormat('Ymd', $value);

    return $d
        ? $d->format('d/m/Y')
        : '—';
};

$routeClass = static function (string $route): string {
    return [
        'Pedido' => 'pedido',
        'Cotización' => 'cotizacion',
        'Certificación' => 'certificacion',
        'Orden de compra' => 'orden',
        'Orden de servicio' => 'orden',
        'Factura o boleta' => 'comprobante',
        'Devengado' => 'devengado',
        'Pago' => 'pago',
        'Retiro de almacén' => 'retiro',
        'Pendiente de retiro de almacén' => 'pendiente',
        'Pedido anulado' => 'anulado',
    ][$route] ?? 'pedido';
};

$tipoActualizacion = null;
$textoActualizar = null;
$descripcionActualizacion = null;

if ($alcance === 'mis' && $tieneCodigoAvalon) {
    $tipoActualizacion = 'mis';
    $textoActualizar = 'Actualizar mis notas';
    $descripcionActualizacion =
        'Actualiza únicamente las notas registradas por su usuario.';
} elseif ($alcance === 'unidad' && $esAdministradorGlobal) {
    $tipoActualizacion = 'global';
    $textoActualizar = 'Actualizar todas las notas';
    $descripcionActualizacion =
        'Actualiza desde Avalon las notas de todas las unidades.';
} elseif ($alcance === 'unidad' && $tieneUnidad) {
    $tipoActualizacion = 'unidad';
    $textoActualizar = 'Actualizar mi unidad';
    $descripcionActualizacion =
        'Actualiza únicamente las notas correspondientes a su unidad orgánica.';
}

$useDataTables = true;
require APP_ROOT . '/app/Views/partials/header.php';

?>

<section class="card notes-intro">
    <div>
        <h2>Consulta organizada de notas</h2>

        <p>
            La lista consulta la copia local actualizada desde Avalon;
            se muestran
            <?= e((string) $resultado['por_pagina']) ?>
            registros por página.
        </p>

        <?php if ($ultimaSincronizacion): ?>
            <small>
                Última sincronización:
                <?= e($ultimaSincronizacion['terminado_en']) ?>
                ·
                <?= e((string) $ultimaSincronizacion['notas_procesadas']) ?>
                notas.
            </small>
        <?php endif; ?>
    </div>

    <div class="scope-actions">
        <?php if ($tieneCodigoAvalon): ?>
            <a
                class="<?= $alcance === 'mis' ? 'selected' : '' ?>"
                href="<?= e(url('notas', ['vista' => 'mis'])) ?>"
            >
                ▤ Mis notas
            </a>
        <?php endif; ?>

        <?php if ($esAdministradorGlobal): ?>
            <a
                class="<?= $alcance === 'unidad' ? 'selected' : '' ?>"
                href="<?= e(url('notas', ['vista' => 'unidad'])) ?>"
            >
                ⌂ Todas las unidades
            </a>
        <?php elseif ($tieneUnidad && alcanceModulo('seguimiento',$acceso)!=='PROPIO'): ?>
            <a
                class="<?= $alcance === 'unidad' ? 'selected' : '' ?>"
                href="<?= e(url('notas', ['vista' => 'unidad'])) ?>"
            >
                ⌂ Mi unidad orgánica
            </a>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <?php if ($errorFechas !== ''): ?>
        <p class="alert error" role="alert"><?= e($errorFechas) ?></p>
    <?php endif; ?>
    <form class="notes-filter notes-date-filter" method="get">
        <input type="hidden" name="r" value="notas">

        <input
            type="hidden"
            name="vista"
            value="<?= e($alcance) ?>"
        >

        <label>Unidad orgánica
            <select name="unidad" class="js-select2" data-searchable><option value="">Todas las unidades permitidas</option>
                <?php foreach($opcionesFiltros['unidades'] as $codigo=>$nombre): ?><option value="<?= e((string)$codigo) ?>" <?= (string)$codigo===$filtroUnidad?'selected':'' ?>><?= e($nombre) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Registrado por
            <select name="registrado_por" class="js-select2" data-searchable><option value="">Todos los usuarios permitidos</option>
                <?php foreach($opcionesFiltros['usuarios'] as $codigo=>$nombre): ?><option value="<?= e((string)$codigo) ?>" <?= (string)$codigo===$filtroUsuario?'selected':'' ?>><?= e($nombre) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>
            Desde (emisión)
            <input type="date" name="desde" value="<?= e($desde) ?>" min="1000-01-01" max="9999-12-31">
        </label>
        <label>
            Hasta (emisión)
            <input type="date" name="hasta" value="<?= e($hasta) ?>" min="1000-01-01" max="9999-12-31">
        </label>

        <div class="notes-filter-actions">
        <button type="submit">Buscar</button>

        <?php if ($errorFechas === ''): ?>
        <a id="notes-export" class="clear-search" href="<?= e(url('exportar_notas', ['vista' => $alcance, 'q' => $busqueda, 'desde' => $desde, 'hasta' => $hasta, 'unidad'=>$filtroUnidad, 'registrado_por'=>$filtroUsuario])) ?>">Exportar CSV</a>
        <?php endif; ?>

        <a
            class="clear-search"
            href="<?= e(url('notas', ['vista' => $alcance])) ?>"
        >
            Limpiar
        </a>
        </div>
    </form>

    <div class="section-heading">
        <div>
            <h2>
                <?php if ($alcance === 'mis'): ?>
                    Notas realizadas por mí
                <?php elseif ($esAdministradorGlobal): ?>
                    Notas de todas las unidades
                <?php else: ?>
                    Notas de mi unidad orgánica
                <?php endif; ?>
            </h2>

<p>Busca por número, unidad, usuario o glosa. Los textos se muestran completos.</p>
        </div>
    </div>

    <div class="table-scroll">
        <table id="tabla-notas" class="display notes-table">
            <thead>
                <tr>
                    <th>Nota</th>
                    <th>Fecha de emisión</th>
                    <th>Unidad orgánica</th>
                    <th>Glosa / sustentación</th>
                    <th>Tipo</th>
                    <th>Prioridad</th>
                    <th>Etapa registrada</th>
                    <th>Registrado por</th>
                    <th>Acción</th>
                </tr>
            </thead>

            <tbody></tbody>
        </table>
    </div>


</section>

<?php if ($tipoActualizacion !== null): ?>
    <section class="card sync-card">
        <div>
            <h2><?= e($textoActualizar) ?></h2>

            <p>
                <?= e($descripcionActualizacion) ?>
                La lectura no modifica los archivos de Avalon.
                Si otra actualización está en proceso, la solicitud
                se bloqueará para no sobrecargar el servidor.
            </p>
        </div>

        <form data-sync-form
            method="post"
            action="<?= e(url('sincronizar_seguimiento')) ?>"
        >
            <input
                type="hidden"
                name="csrf"
                value="<?= e(csrf()) ?>"
            >

            <input
                type="hidden"
                name="alcance"
                value="<?= e($tipoActualizacion) ?>"
            >

            <button type="submit" data-sync-button>
                ↻ <?= e($textoActualizar) ?>
            </button>
        </form>
    </section>
<?php else: ?>
    <section class="card sync-card">
        <div>
            <h2>Actualización no disponible</h2>

            <p>
                Este usuario no tiene un código Avalon o una unidad
                orgánica válida asociada para realizar la actualización.
            </p>
        </div>
    </section>
<?php endif; ?>

<script src="<?= e(APP_URL) ?>assets/vendor/jquery.dataTables.min.js"></script>
<script>
(function(){
    var form=$('.notes-date-filter'), escapeHtml=function(v){return $('<span>').text(v==null?'':String(v)).html();};
    var text=$.fn.dataTable.render.text();
    var table=$('#tabla-notas').DataTable({
        processing:true,serverSide:true,autoWidth:false,searchDelay:350,
        pageLength:10,lengthMenu:[10,25,50,100],order:[[1,'desc']],
        search:{search:<?= json_encode($busqueda,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>},
        ajax:{url:<?= json_encode(url('notas')) ?>,data:function(d){
            d.datos=1;form.serializeArray().forEach(function(f){d[f.name]=f.value;});
        }},
        columns:[
            {data:'NRO_PEDIDO',render:text},
            {data:'fecha_emision',render:function(v,t){return t==='display'&&v?escapeHtml(v.split('-').reverse().join('/')):escapeHtml(v||'—');}},
            {data:'UNIDAD_LABEL',className:'notes-unit',render:text},
            {data:'sustentacion',className:'notes-description',render:function(v){return escapeHtml(v||'Sin sustentación registrada');}},
            {data:'TIPO_LABEL',render:text},
            {data:'PRIORIDAD_LABEL',render:text},
            {data:'ULTIMA_RUTA',render:function(v,t,r){return escapeHtml(v)+'<br><small>'+escapeHtml(r.ESTADO_LABEL)+'</small>';}},
            {data:'USRNOMBRE',className:'notes-user',render:function(v,t,r){return escapeHtml(v||'No registrado')+'<br><small>'+escapeHtml(r.USRCODIGO?'Código '+r.USRCODIGO:'')+'</small>';}},
            {data:'detalle_url',orderable:false,searchable:false,render:function(v,t,r){return '<a class="table-action icon-action" href="'+escapeHtml(v)+'" aria-label="Ver seguimiento de la nota '+escapeHtml(r.NRO_PEDIDO)+'"><i class="bi bi-eye" aria-hidden="true"></i></a>';}}
        ],
        createdRow:function(row){var labels=['Nota','Fecha de emisión','Unidad orgánica','Glosa / sustentación','Tipo','Prioridad','Etapa registrada','Registrado por','Acción'];$('td',row).each(function(i){this.setAttribute('data-label',labels[i]);});},
        language:{processing:'Consultando notas…',search:'Buscar:',searchPlaceholder:'Número, unidad, usuario o glosa',lengthMenu:'Mostrar _MENU_ por página',info:'Mostrando _START_ a _END_ de _TOTAL_ notas',infoEmpty:'No hay notas disponibles',infoFiltered:'(filtradas de _MAX_ en total)',zeroRecords:'No encontramos notas con esos criterios.',emptyTable:'No hay notas dentro del alcance disponible.',paginate:{first:'Primero',last:'Último',next:'Siguiente',previous:'Anterior'}}
    });
    form.on('submit',function(e){e.preventDefault();table.ajax.reload();});
    form.find('select,input[type=date]').on('change',function(){table.ajax.reload();});
    $('#notes-export').on('click',function(){
        var params=new URLSearchParams(form.serialize());params.set('r','exportar_notas');params.set('q',table.search());
        this.href=<?= json_encode(APP_URL) ?>+'?'+params.toString();
    });
}());
</script>
<?php require APP_ROOT . '/app/Views/partials/footer.php'; ?>
