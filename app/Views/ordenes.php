<?php
$pageTitle = 'Órdenes de compra y servicio';
$pageDescription = 'Consulta en solo lectura de las órdenes vinculadas a pedidos dentro de tu alcance.';
$breadcrumb = 'Logística / Órdenes';
$activeNav = 'ordenes';
$useDataTables = true;
if (!isset($ordenesIniciales)) $ordenesIniciales = [];
require APP_ROOT . '/app/Views/partials/header.php';
?>
<?php if ($ordenesIniciales): ?><section class="card orders-budget-context"><div><strong>Órdenes seleccionadas desde Presupuesto</strong><p>El listado conserva los filtros de acceso de tu usuario.</p></div><a class="orders-clear" href="<?= e(url('ordenes')) ?>">Ver todas las órdenes</a></section><?php endif; ?>
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
        <button type="button" class="orders-clear" id="orders-clear"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Limpiar filtros</button>
    </div>
</section>

<section class="card orders-list-card min-w-0">
    <div class="section-heading orders-list-heading">
        <div><h2>Órdenes vinculadas</h2><p>Usa la búsqueda para encontrar por orden, pedido, unidad, responsable o descripción.</p></div>
    </div>
    <div class="table-scroll orders-table-scroll">
        <table id="tabla-ordenes" class="display orders-table" aria-label="Listado de órdenes de compra y servicio">
            <thead><tr><th>Orden</th><th>Tipo</th><th>Fecha</th><th>Pedidos vinculados</th><th>Unidad / descripción</th><th>Estado</th><th>Importe</th><th>Detalle</th></tr></thead>
        </table>
    </div>
</section>

<script src="<?= e(APP_URL) ?>assets/vendor/jquery.dataTables.min.js"></script>
<script>
(function () {
    var escapeHtml = function (value) { return $('<span>').text(value == null ? '' : String(value)).html(); };
    var table = $('#tabla-ordenes').DataTable({
        processing: true,
        serverSide: true,
        searchDelay: 350,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[2, 'desc']],
        autoWidth: false,
        ajax: {
            url: <?= json_encode(url('ordenes_datos'), JSON_UNESCAPED_SLASHES) ?>,
            data: function (request) {
                request.tipo = $('#orders-type').val();
                request.estado = $('#orders-state').val();
                request.desde = $('#orders-from').val();
                request.hasta = $('#orders-to').val();
                request.ordenes = <?= json_encode($ordenesIniciales, JSON_UNESCAPED_SLASHES) ?>;
            }
        },
        columns: [
            {data: 'numero_label', render: function (value, type) { return type === 'display' ? '<strong class="order-number-label">' + escapeHtml(value) + '</strong>' : value; }},
            {data: 'tipo_label', render: function (value, type) { return type === 'display' ? '<span class="order-type-tag">' + escapeHtml(value) + '</span>' : value; }},
            {data: 'fecha', render: function (value, type, row) { return type === 'display' ? escapeHtml(row.fecha_label) : (value || ''); }},
            {data: 'pedidos_relacionados', render: function (value, type, row) {
                if (type !== 'display') return value;
                var pedidos = String(value || row.nro_pedido || '').split(', ').filter(Boolean);
                var visible = pedidos.slice(0, 2).join(', ');
                var restantes = pedidos.length - 2;
                var resumen = restantes > 0 ? visible + ' (+' + restantes + ')' : pedidos.join(', ');
                return '<span class="order-pedido" title="Pedidos vinculados: ' + escapeHtml(pedidos.join(', ')) + '">' + escapeHtml(resumen || '—') + '</span>';
            }},
            {data: 'busqueda_unidad', render: function (value, type, row) {
                if (type !== 'display') return [value, row.sustentacion].filter(Boolean).join(' ');
                var description = row.sustentacion ? '<small>' + escapeHtml(row.sustentacion) + '</small>' : '';
                return '<span class="order-unit">' + escapeHtml(value || 'Unidad no identificada') + '</span>' + description;
            }},
            {data: 'estado_label', render: function (value, type, row) {
                if (type !== 'display') return value;
                var cancelled = String(row.estado || '').trim().toUpperCase() === 'A';
                return '<span class="order-state ' + (cancelled ? 'is-cancelled' : 'is-current') + '">' + escapeHtml(value) + '</span>';
            }},
            {data: 'importe', orderable: false, className: 'order-amount', render: $.fn.dataTable.render.text()},
            {data: 'detalle_url', orderable: false, searchable: false, className: 'order-action', render: function (value, type, row) {
                if (type !== 'display') return value;
                return '<a class="table-action order-view-action" href="' + escapeHtml(value) + '" aria-label="Ver detalle de ' + escapeHtml(row.numero_label) + '" title="Ver detalle"><i class="bi bi-eye" aria-hidden="true"></i></a>';
            }}
        ],
        createdRow: function (row) {
            var labels = ['Orden', 'Tipo', 'Fecha', 'Pedidos vinculados', 'Unidad / descripción', 'Estado', 'Importe', 'Detalle'];
            $('td', row).each(function (index) { this.setAttribute('data-label', labels[index] || ''); });
        },
        language: {
            processing: 'Consultando órdenes…',
            search: 'Buscar:',
            searchPlaceholder: 'Orden, pedido, unidad o descripción',
            lengthMenu: 'Mostrar _MENU_ por página',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ órdenes',
            infoEmpty: 'No hay órdenes disponibles',
            infoFiltered: '(filtradas de _MAX_ en total)',
            zeroRecords: 'No encontramos órdenes con esos criterios.',
            emptyTable: 'No hay órdenes vinculadas dentro del alcance disponible.',
            paginate: {first: '«', last: '»', next: 'Siguiente', previous: 'Anterior'}
        }
    });

    $('#tabla-ordenes').on('xhr.dt', function (event, settings, response, xhr) {
        var message = (response && response.error) || (xhr && xhr.responseJSON && xhr.responseJSON.error);
        if (!message) return;
        if (window.Swal) Swal.mixin({toast:true, position:'top-end', showConfirmButton:false, timer:6000}).fire({icon:'error', title:message});
    });

    $('#orders-type, #orders-state').on('change', function () { table.ajax.reload(); });
    $('#orders-from, #orders-to').on('change', function () { table.ajax.reload(); });
    $('#orders-clear').on('click', function () {
        ['orders-type', 'orders-state'].forEach(function (id) {
            var element = document.getElementById(id);
            element.value = id === 'orders-type' ? 'todas' : 'todos';
            $(element).trigger('change.select2');
        });
        $('#orders-from, #orders-to').val('');
        table.search('').draw();
    });
}());
</script>
<?php require APP_ROOT . '/app/Views/partials/footer.php'; ?>
