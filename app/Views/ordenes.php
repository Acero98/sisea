<?php
$pageTitle = "\xc3\x93\x72\x64\x65\x6e\x65\x73\x20\x64\x65\x20\x63\x6f\x6d\x70\x72\x61\x20\x79\x20\x73\x65\x72\x76\x69\x63\x69\x6f"; $pageDescription = "\x43\x6f\x6e\x73\x75\x6c\x74\x61\x20\x65\x6e\x20\x73\x6f\x6c\x6f\x20\x6c\x65\x63\x74\x75\x72\x61\x20\x64\x65\x20\x6c\x61\x73\x20\xc3\xb3\x72\x64\x65\x6e\x65\x73\x20\x76\x69\x6e\x63\x75\x6c\x61\x64\x61\x73\x20\x61\x20\x70\x65\x64\x69\x64\x6f\x73\x20\x64\x65\x6e\x74\x72\x6f\x20\x64\x65\x20\x74\x75\x20\x61\x6c\x63\x61\x6e\x63\x65\x2e"; $breadcrumb = "\x4c\x6f\x67\xc3\xad\x73\x74\x69\x63\x61\x20\x2f\x20\xc3\x93\x72\x64\x65\x6e\x65\x73"; $activeNav = "\x6f\x72\x64\x65\x6e\x65\x73"; $useDataTables = true; if (!isset($ordenesIniciales)) $ordenesIniciales = []; require APP_ROOT . "\x2f\x61\x70\x70\x2f\x56\x69\x65\x77\x73\x2f\x70\x61\x72\x74\x69\x61\x6c\x73\x2f\x68\x65\x61\x64\x65\x72\x2e\x70\x68\x70"; ?>
<?php if ($ordenesIniciales): ?><section class="card orders-budget-context">
        <div><strong>Órdenes seleccionadas desde Presupuesto</strong>
            <p>El listado conserva los filtros de acceso de tu usuario.</p>
        </div><a class="orders-clear" href="<?= e(url("\x6f\x72\x64\x65\x6e\x65\x73")) ?>">Ver todas las órdenes</a>
    </section><?php endif; ?>
<section class="card orders-filter-card min-w-0">
    <div class="orders-heading flex items-center gap-3">
        <span class="orders-heading-icon"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i></span>
        <div>
            <h2>Consulta de órdenes</h2>
            <p>Busca y filtra órdenes vinculadas a los pedidos permitidos para tu unidad o alcance.</p>
        </div>
        <span class="orders-readonly"><i class="bi bi-lock" aria-hidden="true"></i> Solo lectura</span>
    </div>
    <div class="orders-filters" id="orders-filters" role="group" aria-label="Filtros de órdenes">
        <div class="orders-filter-primary">
            <label><span>Tipo de orden</span>
                <select class="js-select2" id="orders-type" aria-label="Tipo de orden">
                    <option value="todas">Todas las órdenes</option>
                    <option value="orden_compra">Orden de compra</option>
                    <option value="orden_servicio">Orden de servicio</option>
                </select>
            </label>
            <label><span>Estado</span>
                <select class="js-select2" id="orders-state" aria-label="Estado de la orden">
                    <option value="todos">Todos los estados</option>
                    <option value="vigentes">Vigentes</option>
                    <option value="anuladas">Anuladas</option>
                </select>
            </label>
            <label><span>Desde</span><input type="date" id="orders-from" aria-label="Fecha desde"></label>
            <label><span>Hasta</span><input type="date" id="orders-to" aria-label="Fecha hasta"></label>

        </div>
        <div class="orders-filter-parties">
            <label><span>Unidad orgánica</span><select class="js-select2" data-searchable id="orders-unit" aria-label="Unidad orgánica">
                    <option value="">Todas las unidades permitidas</option><?php foreach ($opcionesFiltros["\x75\x6e\x69\x64\x61\x64\x65\x73"] as $codigo => $nombre): ?><option value="<?= e((string)$codigo) ?>"><?= e($nombre) ?></option><?php endforeach; ?>
                </select></label>
            <label><span>Proveedor</span><select class="js-select2" data-searchable id="orders-provider" aria-label="Proveedor">
                    <option value="">Todos los proveedores</option><?php foreach ($opcionesFiltros["\x70\x72\x6f\x76\x65\x65\x64\x6f\x72\x65\x73"] as $codigo => $nombre): ?><option value="<?= e((string)$codigo) ?>"><?= e($nombre) ?></option><?php endforeach; ?>
                </select></label>
            <button type="button" class="orders-clear" id="orders-clear"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Limpiar filtros</button>
        </div>
    </div>
</section>

<section class="card orders-list-card min-w-0">
    <div class="section-heading orders-list-heading">
        <div>
            <h2>Órdenes vinculadas</h2>
            <p>Usa la búsqueda para encontrar por orden, pedido, unidad, responsable o descripción.</p>
        </div>
    </div>
    <div class="table-scroll orders-table-scroll">
        <table id="tabla-ordenes" class="display orders-table" aria-label="Listado de órdenes de compra y servicio">
            <thead>
                <tr>
                    <th class="order-identity">Orden</th>
                    <th class="order-date">Fecha</th>
                    <th class="order-pedidos">Pedidos vinculados</th>
                    <th class="order-description">Unidad / descripción</th>
                    <th class="order-provider">Proveedor / RUC</th>
                    <th class="order-status">Estado</th><?php if ($mostrarMontos): ?><th class="order-amount">Importe sin IGV</th>
                        <th class="order-amount">Importe total</th><?php endif; ?><?php if ($puedeVerDetalle): ?><th class="order-action">Detalle</th><?php endif; ?>
                </tr>
            </thead>
        </table>
    </div>
</section>

<script src="<?= e(APP_URL) ?>assets/vendor/jquery.dataTables.min.js"></script>
<script>
    (function() {
        var escapeHtml = function(value) {
            return $('<span>').text(value == null ? '' : String(value)).html();
        };
        var table = $('#tabla-ordenes').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 350,
            pageLength: 10,
            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],
            order: [
                [1, 'desc']
            ],
            autoWidth: false,
            ajax: {
                url: <?= json_encode(url("\x6f\x72\x64\x65\x6e\x65\x73\x5f\x64\x61\x74\x6f\x73"), JSON_UNESCAPED_SLASHES) ?>,
                data: function(request) {
                    request.tipo = $('#orders-type').val();
                    request.estado = $('#orders-state').val();
                    request.unidad = $('#orders-unit').val();
                    request.proveedor = $('#orders-provider').val();
                    request.desde = $('#orders-from').val();
                    request.hasta = $('#orders-to').val();
                    request.ordenes = <?= json_encode($ordenesIniciales, JSON_UNESCAPED_SLASHES) ?>;
                }
            },
            columns: [{
                    data: 'numero_label',
                    className: 'order-identity',
                    render: function(value, type, row) {
                        return type === 'display' ? '<strong class="order-number-label">' + escapeHtml(value) + '</strong><div><span class="order-type-tag">' + escapeHtml(row.tipo_label) + '</span></div>' : value;
                    }
                },
                {
                    data: 'fecha',
                    className: 'order-date',
                    render: function(value, type, row) {
                        return type === 'display' ? escapeHtml(row.fecha_label) : (value || '');
                    }
                },
                {
                    data: 'pedidos_relacionados',
                    className: 'order-pedidos',
                    render: function(value, type, row) {
                        if (type !== 'display') return value;
                        var pedidos = String(value || row.nro_pedido || '').split(', ').filter(Boolean);
                        var visible = pedidos.slice(0, 2).join(', ');
                        var restantes = pedidos.length - 2;
                        var resumen = restantes > 0 ? visible + ' (+' + restantes + ')' : pedidos.join(', ');
                        return '<span class="order-pedido" title="Pedidos vinculados: ' + escapeHtml(pedidos.join(', ')) + '">' + escapeHtml(resumen || '—') + '</span>';
                    }
                },
                {
                    data: 'busqueda_unidad',
                    className: 'order-description',
                    render: function(value, type, row) {
                        if (type !== 'display') return [value, row.sustentacion].filter(Boolean).join(' ');
                        var description = row.sustentacion ? '<small>' + escapeHtml(row.sustentacion) + '</small>' : '';
                        return '<span class="order-unit">' + escapeHtml(value || 'Unidad no identificada') + '</span>' + description;
                    }
                },
                {
                    data: 'proveedor_nombre',
                    orderable: false,
                    className: 'order-provider',
                    render: function(value, type, row) {
                        if (type !== 'display') return [value, row.proveedor_ruc, row.proveedor_codigo].join(' ');
                        var identificador = row.proveedor_ruc ? 'RUC: ' + row.proveedor_ruc : 'Código: ' + (row.proveedor_codigo || 'No disponible');
                        return '<span>' + escapeHtml(value) + '</span><small>' + escapeHtml(identificador) + '</small>';
                    }
                },
                {
                    data: 'estado_label',
                    className: 'order-status',
                    render: function(value, type, row) {
                        if (type !== 'display') return value;
                        var cancelled = String(row.estado || '').trim().toUpperCase() === 'A';
                        return '<span class="order-state ' + (cancelled ? 'is-cancelled' : 'is-current') + '">' + escapeHtml(value) + '</span>';
                    }
                },
                <?php if ($mostrarMontos): ?> {
                        data: 'importe_sin_igv',
                        orderable: false,
                        className: 'order-amount',
                        render: $.fn.dataTable.render.text()
                    },
                    {
                        data: 'importe',
                        orderable: false,
                        className: 'order-amount',
                        render: $.fn.dataTable.render.text()
                    },
                <?php endif; ?>
                <?php if ($puedeVerDetalle): ?> {
                        data: 'detalle_url',
                        orderable: false,
                        searchable: false,
                        className: 'order-action',
                        render: function(value, type, row) {
                            if (type !== 'display') return value;
                            return '<a class="table-action order-view-action" href="' + escapeHtml(value) + '" aria-label="Ver detalle de ' + escapeHtml(row.numero_label) + '" title="Ver detalle"><i class="bi bi-eye" aria-hidden="true"></i></a>';
                        }
                    }
                <?php endif; ?>
            ],
            createdRow: function(row) {
                var labels = <?= json_encode(array_merge(["\x4f\x72\x64\x65\x6e", "\x46\x65\x63\x68\x61", "\x50\x65\x64\x69\x64\x6f\x73\x20\x76\x69\x6e\x63\x75\x6c\x61\x64\x6f\x73", "\x55\x6e\x69\x64\x61\x64\x20\x2f\x20\x64\x65\x73\x63\x72\x69\x70\x63\x69\xc3\xb3\x6e", "\x50\x72\x6f\x76\x65\x65\x64\x6f\x72\x20\x2f\x20\x52\x55\x43", "\x45\x73\x74\x61\x64\x6f"], $mostrarMontos ? ["\x49\x6d\x70\x6f\x72\x74\x65\x20\x73\x69\x6e\x20\x49\x47\x56", "\x49\x6d\x70\x6f\x72\x74\x65\x20\x74\x6f\x74\x61\x6c"] : [], $puedeVerDetalle ? ["\x44\x65\x74\x61\x6c\x6c\x65"] : []), JSON_UNESCAPED_UNICODE) ?>;
                $('td', row).each(function(index) {
                    this.setAttribute('data-label', labels[index] || '');
                });
            },
            language: {
                processing: 'Consultando órdenes…',
                search: 'Buscar:',
                searchPlaceholder: 'Orden, pedido, unidad, proveedor o RUC',
                lengthMenu: 'Mostrar _MENU_ por página',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ órdenes',
                infoEmpty: 'No hay órdenes disponibles',
                infoFiltered: '(filtradas de _MAX_ en total)',
                zeroRecords: 'No encontramos órdenes con esos criterios.',
                emptyTable: 'No hay órdenes vinculadas dentro del alcance disponible.',
                paginate: {
                    first: '«',
                    last: '»',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });

        $('#tabla-ordenes').on('xhr.dt', function(event, settings, response, xhr) {
            var message = (response && response.error) || (xhr && xhr.responseJSON && xhr.responseJSON.error);
            if (!message) return;
            if (window.Swal) Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 6000
            }).fire({
                icon: 'error',
                title: message
            });
        });

        $('#orders-type, #orders-state, #orders-unit, #orders-provider').on('change', function() {
            table.ajax.reload();
        });
        $('#orders-from, #orders-to').on('change', function() {
            table.ajax.reload();
        });
        $('#orders-clear').on('click', function() {
            ['orders-type', 'orders-state', 'orders-unit', 'orders-provider'].forEach(function(id) {
                var element = document.getElementById(id);
                element.value = id === 'orders-type' ? 'todas' : (id === 'orders-state' ? 'todos' : '');
                $(element).trigger('change.select2');
            });
            $('#orders-from, #orders-to').val('');
            table.search('').draw();
        });
    }());
</script>
<?php require APP_ROOT . "\x2f\x61\x70\x70\x2f\x56\x69\x65\x77\x73\x2f\x70\x61\x72\x74\x69\x61\x6c\x73\x2f\x66\x6f\x6f\x74\x65\x72\x2e\x70\x68\x70"; ?>