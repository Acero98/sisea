<?php
 $flash = takeFlash(); $esAdministradorGlobal = alcanceModulo("\x73\x65\x67\x75\x69\x6d\x69\x65\x6e\x74\x6f",$acceso) === "\x47\x4c\x4f\x42\x41\x4c"; $codigoAvalon = trim( (string) ($acceso["\x61\x76\x61\x6c\x6f\x6e\x5f\x63\x6f\x64\x69\x67\x6f"] ?? "") ); $unidadOrganica = trim( (string) ($acceso["\x75\x6e\x69\x64\x61\x64\x5f\x6f\x72\x67\x61\x6e\x69\x63\x61"] ?? "") ); $tieneCodigoAvalon = $codigoAvalon !== ""; $tieneUnidad = $unidadOrganica !== "" && $unidadOrganica !== "\x30\x30\x30\x30"; if ($alcance === "\x6d\x69\x73") { $pageTitle = "\x4d\x69\x73\x20\x6e\x6f\x74\x61\x73\x20\x64\x65\x20\x70\x65\x64\x69\x64\x6f"; } elseif ($esAdministradorGlobal) { $pageTitle = "\x4e\x6f\x74\x61\x73\x20\x64\x65\x20\x74\x6f\x64\x61\x73\x20\x6c\x61\x73\x20\x75\x6e\x69\x64\x61\x64\x65\x73"; } else { $pageTitle = "\x4e\x6f\x74\x61\x73\x20\x64\x65\x20\x6d\x69\x20\x75\x6e\x69\x64\x61\x64\x20\x6f\x72\x67\xc3\xa1\x6e\x69\x63\x61"; } $pageDescription = "\x43\x6f\x6e\x73\x75\x6c\x74\x61\x20\x70\x61\x67\x69\x6e\x61\x64\x61\x3a\x20\x73\x6f\x6c\x6f\x20\x73\x65\x20\x63\x61\x72\x67\x61\x6e\x20\x6c\x61\x73\x20\x6e\x6f\x74\x61\x73\x20\x64\x65\x20\x6c\x61\x20\x70\xc3\xa1\x67\x69\x6e\x61\x20\x61\x63\x74\x75\x61\x6c\x2e"; $breadcrumb = "\x4c\x6f\x67\xc3\xad\x73\x74\x69\x63\x61\x20\x2f\x20\x4e\x6f\x74\x61\x73\x20\x64\x65\x20\x70\x65\x64\x69\x64\x6f"; $activeNav = "\x6e\x6f\x74\x61\x73"; $date = static function (string $value): string { $d = DateTime::createFromFormat("\x59\x6d\x64", $value); return $d ? $d->format("\x64\x2f\x6d\x2f\x59") : "\xe2\x80\x94"; }; $routeClass = static function (string $route): string { return [ "\x50\x65\x64\x69\x64\x6f" => "\x70\x65\x64\x69\x64\x6f", "\x43\x6f\x74\x69\x7a\x61\x63\x69\xc3\xb3\x6e" => "\x63\x6f\x74\x69\x7a\x61\x63\x69\x6f\x6e", "\x43\x65\x72\x74\x69\x66\x69\x63\x61\x63\x69\xc3\xb3\x6e" => "\x63\x65\x72\x74\x69\x66\x69\x63\x61\x63\x69\x6f\x6e", "\x4f\x72\x64\x65\x6e\x20\x64\x65\x20\x63\x6f\x6d\x70\x72\x61" => "\x6f\x72\x64\x65\x6e", "\x4f\x72\x64\x65\x6e\x20\x64\x65\x20\x73\x65\x72\x76\x69\x63\x69\x6f" => "\x6f\x72\x64\x65\x6e", "\x46\x61\x63\x74\x75\x72\x61\x20\x6f\x20\x62\x6f\x6c\x65\x74\x61" => "\x63\x6f\x6d\x70\x72\x6f\x62\x61\x6e\x74\x65", "\x44\x65\x76\x65\x6e\x67\x61\x64\x6f" => "\x64\x65\x76\x65\x6e\x67\x61\x64\x6f", "\x50\x61\x67\x6f" => "\x70\x61\x67\x6f", "\x52\x65\x74\x69\x72\x6f\x20\x64\x65\x20\x61\x6c\x6d\x61\x63\xc3\xa9\x6e" => "\x72\x65\x74\x69\x72\x6f", "\x50\x65\x6e\x64\x69\x65\x6e\x74\x65\x20\x64\x65\x20\x72\x65\x74\x69\x72\x6f\x20\x64\x65\x20\x61\x6c\x6d\x61\x63\xc3\xa9\x6e" => "\x70\x65\x6e\x64\x69\x65\x6e\x74\x65", "\x50\x65\x64\x69\x64\x6f\x20\x61\x6e\x75\x6c\x61\x64\x6f" => "\x61\x6e\x75\x6c\x61\x64\x6f", ][$route] ?? "\x70\x65\x64\x69\x64\x6f"; }; $tipoActualizacion = null; $textoActualizar = null; $descripcionActualizacion = null; if ($alcance === "\x6d\x69\x73" && $tieneCodigoAvalon) { $tipoActualizacion = "\x6d\x69\x73"; $textoActualizar = "\x41\x63\x74\x75\x61\x6c\x69\x7a\x61\x72\x20\x6d\x69\x73\x20\x6e\x6f\x74\x61\x73"; $descripcionActualizacion = "\x41\x63\x74\x75\x61\x6c\x69\x7a\x61\x20\xc3\xba\x6e\x69\x63\x61\x6d\x65\x6e\x74\x65\x20\x6c\x61\x73\x20\x6e\x6f\x74\x61\x73\x20\x72\x65\x67\x69\x73\x74\x72\x61\x64\x61\x73\x20\x70\x6f\x72\x20\x73\x75\x20\x75\x73\x75\x61\x72\x69\x6f\x2e"; } elseif ($alcance === "\x75\x6e\x69\x64\x61\x64" && $esAdministradorGlobal) { $tipoActualizacion = "\x67\x6c\x6f\x62\x61\x6c"; $textoActualizar = "\x41\x63\x74\x75\x61\x6c\x69\x7a\x61\x72\x20\x74\x6f\x64\x61\x73\x20\x6c\x61\x73\x20\x6e\x6f\x74\x61\x73"; $descripcionActualizacion = "\x41\x63\x74\x75\x61\x6c\x69\x7a\x61\x20\x64\x65\x73\x64\x65\x20\x41\x76\x61\x6c\x6f\x6e\x20\x6c\x61\x73\x20\x6e\x6f\x74\x61\x73\x20\x64\x65\x20\x74\x6f\x64\x61\x73\x20\x6c\x61\x73\x20\x75\x6e\x69\x64\x61\x64\x65\x73\x2e"; } elseif ($alcance === "\x75\x6e\x69\x64\x61\x64" && $tieneUnidad) { $tipoActualizacion = "\x75\x6e\x69\x64\x61\x64"; $textoActualizar = "\x41\x63\x74\x75\x61\x6c\x69\x7a\x61\x72\x20\x6d\x69\x20\x75\x6e\x69\x64\x61\x64"; $descripcionActualizacion = "\x41\x63\x74\x75\x61\x6c\x69\x7a\x61\x20\xc3\xba\x6e\x69\x63\x61\x6d\x65\x6e\x74\x65\x20\x6c\x61\x73\x20\x6e\x6f\x74\x61\x73\x20\x63\x6f\x72\x72\x65\x73\x70\x6f\x6e\x64\x69\x65\x6e\x74\x65\x73\x20\x61\x20\x73\x75\x20\x75\x6e\x69\x64\x61\x64\x20\x6f\x72\x67\xc3\xa1\x6e\x69\x63\x61\x2e"; } $useDataTables = true; require APP_ROOT . "\x2f\x61\x70\x70\x2f\x56\x69\x65\x77\x73\x2f\x70\x61\x72\x74\x69\x61\x6c\x73\x2f\x68\x65\x61\x64\x65\x72\x2e\x70\x68\x70"; ?>

<section class="card notes-intro">
    <div>
        <h2>Consulta organizada de notas</h2>

        <p>
            La lista consulta la copia local actualizada desde Avalon;
            se muestran
            <?= e((string) $resultado["\x70\x6f\x72\x5f\x70\x61\x67\x69\x6e\x61"]) ?>
            registros por página.
        </p>

        <?php if ($ultimaSincronizacion): ?>
            <small>
                Última sincronización:
                <?= e($ultimaSincronizacion["\x74\x65\x72\x6d\x69\x6e\x61\x64\x6f\x5f\x65\x6e"]) ?>
                ·
                <?= e((string) $ultimaSincronizacion["\x6e\x6f\x74\x61\x73\x5f\x70\x72\x6f\x63\x65\x73\x61\x64\x61\x73"]) ?>
                notas.
            </small>
        <?php endif; ?>
    </div>

    <div class="scope-actions">
        <?php if ($tieneCodigoAvalon): ?>
            <a
                class="<?= $alcance === "\x6d\x69\x73" ? "\x73\x65\x6c\x65\x63\x74\x65\x64" : "" ?>"
                href="<?= e(url("\x6e\x6f\x74\x61\x73", ["\x76\x69\x73\x74\x61" => "\x6d\x69\x73"])) ?>"
            >
                ▤ Mis notas
            </a>
        <?php endif; ?>

        <?php if ($esAdministradorGlobal): ?>
            <a
                class="<?= $alcance === "\x75\x6e\x69\x64\x61\x64" ? "\x73\x65\x6c\x65\x63\x74\x65\x64" : "" ?>"
                href="<?= e(url("\x6e\x6f\x74\x61\x73", ["\x76\x69\x73\x74\x61" => "\x75\x6e\x69\x64\x61\x64"])) ?>"
            >
                ⌂ Todas las unidades
            </a>
        <?php elseif ($tieneUnidad && alcanceModulo("\x73\x65\x67\x75\x69\x6d\x69\x65\x6e\x74\x6f",$acceso)!=="\x50\x52\x4f\x50\x49\x4f"): ?>
            <a
                class="<?= $alcance === "\x75\x6e\x69\x64\x61\x64" ? "\x73\x65\x6c\x65\x63\x74\x65\x64" : "" ?>"
                href="<?= e(url("\x6e\x6f\x74\x61\x73", ["\x76\x69\x73\x74\x61" => "\x75\x6e\x69\x64\x61\x64"])) ?>"
            >
                ⌂ Mi unidad orgánica
            </a>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <?php if ($errorFechas !== ""): ?>
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
                <?php foreach($opcionesFiltros["\x75\x6e\x69\x64\x61\x64\x65\x73"] as $codigo=>$nombre): ?><option value="<?= e((string)$codigo) ?>" <?= (string)$codigo===$filtroUnidad?"\x73\x65\x6c\x65\x63\x74\x65\x64":"" ?>><?= e($nombre) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Registrado por
            <select name="registrado_por" class="js-select2" data-searchable><option value="">Todos los usuarios permitidos</option>
                <?php foreach($opcionesFiltros["\x75\x73\x75\x61\x72\x69\x6f\x73"] as $codigo=>$nombre): ?><option value="<?= e((string)$codigo) ?>" <?= (string)$codigo===$filtroUsuario?"\x73\x65\x6c\x65\x63\x74\x65\x64":"" ?>><?= e($nombre) ?></option><?php endforeach; ?>
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

        <?php if ($errorFechas === ""): ?>
        <a id="notes-export" class="clear-search" href="<?= e(url("\x65\x78\x70\x6f\x72\x74\x61\x72\x5f\x6e\x6f\x74\x61\x73", ["\x76\x69\x73\x74\x61" => $alcance, "\x71" => $busqueda, "\x64\x65\x73\x64\x65" => $desde, "\x68\x61\x73\x74\x61" => $hasta, "\x75\x6e\x69\x64\x61\x64"=>$filtroUnidad, "\x72\x65\x67\x69\x73\x74\x72\x61\x64\x6f\x5f\x70\x6f\x72"=>$filtroUsuario])) ?>">Exportar CSV</a>
        <?php endif; ?>

        <a
            class="clear-search"
            href="<?= e(url("\x6e\x6f\x74\x61\x73", ["\x76\x69\x73\x74\x61" => $alcance])) ?>"
        >
            Limpiar
        </a>
        </div>
    </form>

    <div class="section-heading">
        <div>
            <h2>
                <?php if ($alcance === "\x6d\x69\x73"): ?>
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
            action="<?= e(url("\x73\x69\x6e\x63\x72\x6f\x6e\x69\x7a\x61\x72\x5f\x73\x65\x67\x75\x69\x6d\x69\x65\x6e\x74\x6f")) ?>"
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
        ajax:{url:<?= json_encode(url("\x6e\x6f\x74\x61\x73")) ?>,data:function(d){
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
<?php require APP_ROOT . "\x2f\x61\x70\x70\x2f\x56\x69\x65\x77\x73\x2f\x70\x61\x72\x74\x69\x61\x6c\x73\x2f\x66\x6f\x6f\x74\x65\x72\x2e\x70\x68\x70"; ?>
