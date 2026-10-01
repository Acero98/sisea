<?php

$flash = takeFlash();

$esAdministradorGlobal = ($acceso['alcance'] ?? '') === 'GLOBAL';

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

$glosaCorta = static function (string $texto): string {
    $palabras = preg_split(
        '/\s+/u',
        trim($texto),
        -1,
        PREG_SPLIT_NO_EMPTY
    ) ?: [];

    return count($palabras) > 4
        ? implode(' ', array_slice($palabras, 0, 4)) . '…'
        : implode(' ', $palabras);
};

$unidadCorta = static function (string $nombre): string {
    $palabras = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return count($palabras) > 3
        ? implode(' ', array_slice($palabras, 0, 3)) . '…'
        : implode(' ', $palabras);
};

$link = static function (int $pagina) use (
    $alcance,
    $busqueda,
    $desde,
    $hasta,
    $resultado
): string {
    return url(
        'notas',
        [
            'vista' => $alcance,
            'q' => $busqueda,
            'desde' => $desde,
            'hasta' => $hasta,
            'pagina' => $pagina,
            'por_pagina' => $resultado['por_pagina'],
        ]
    );
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
        <?php elseif ($tieneUnidad): ?>
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

        <label class="notes-search-field">
            Buscar notas

            <input
                type="search"
                name="q"
                value="<?= e($busqueda) ?>"
                placeholder="Número, unidad, usuario, glosa, tipo o estado"
            >
        </label>

        <label>
            Desde (emisión)
            <input type="date" name="desde" value="<?= e($desde) ?>" min="1000-01-01" max="9999-12-31">
        </label>
        <label>
            Hasta (emisión)
            <input type="date" name="hasta" value="<?= e($hasta) ?>" min="1000-01-01" max="9999-12-31">
        </label>

        <label>
            Mostrar

            <select name="por_pagina">
                <option
                    value="10"
                    <?= $resultado['por_pagina'] === 10 ? 'selected' : '' ?>
                >
                    10
                </option>

                <option
                    value="25"
                    <?= $resultado['por_pagina'] === 25 ? 'selected' : '' ?>
                >
                    25
                </option>

                <option
                    value="50"
                    <?= $resultado['por_pagina'] === 50 ? 'selected' : '' ?>
                >
                    50
                </option>
            </select>
        </label>

        <div class="notes-filter-actions">
        <button type="submit">Buscar</button>

        <?php if ($errorFechas === ''): ?>
        <a class="clear-search" href="<?= e(url('exportar_notas', ['vista' => $alcance, 'q' => $busqueda, 'desde' => $desde, 'hasta' => $hasta])) ?>">Exportar CSV</a>
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

            <p>
                <?= e((string) $resultado['total']) ?>
                nota(s) encontradas. Página
                <?= e((string) $resultado['pagina']) ?>
                de
                <?= e((string) $resultado['paginas']) ?>.
            </p>
        </div>
    </div>

    <div class="table-scroll">
        <table class="notes-table">
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

            <tbody>
                <?php foreach ($notas as $nota): ?>
                    <?php
                    $glosa = trim(
                        (string) ($nota['sustentacion'] ?? '')
                    );
                    ?>

                    <tr>
                        <td>
                            <b><?= e($nota['NRO_PEDIDO']) ?></b>
                        </td>

                        <td>
                            <?= e($date($nota['FEC_EMISIO'])) ?>
                        </td>

                        <td>
                            <?php
                            $codigoUnidad = trim((string)($nota['unidad_organica'] ?? ''));
                            $nombreUnidad = trim((string)($nota['unidad_nombre'] ?? ''));
                            $unidadCompleta = trim(($codigoUnidad ?: '—') . ' · ' . ($nombreUnidad ?: 'Unidad no identificada'));
                            ?>
                            <span class="unidad-corta" title="<?= e($unidadCompleta) ?>">
                                <?= e(($codigoUnidad ?: '—') . ' · ' . $unidadCorta($nombreUnidad ?: 'Unidad no identificada')) ?>
                            </span>
                        </td>

                        <td>
                            <span
                                class="glosa-corta"
                                title="<?= e(
                                    $glosa ?: 'Sin sustentación registrada'
                                ) ?>"
                            >
                                <?= e(
                                    $glosa !== ''
                                        ? $glosaCorta($glosa)
                                        : '—'
                                ) ?>
                            </span>
                        </td>

                        <td>
                            <?= e($nota['TIPO_LABEL']) ?>
                        </td>

                        <td>
                            <?= e($nota['PRIORIDAD_LABEL']) ?>
                        </td>

                        <td>
                            <span
                                class="route-stage route-<?= e(
                                    $routeClass($nota['ULTIMA_RUTA'])
                                ) ?>"
                            >
                                <?= e($nota['ULTIMA_RUTA']) ?>
                            </span>

                            <br>

                            <small>
                                <?= e($nota['ESTADO_LABEL']) ?>
                            </small>
                        </td>

                        <td>
                            <?= e(
                                $nota['USRNOMBRE'] ?: 'No registrado'
                            ) ?>

                            <br>

                            <small>
                                <?= e(
                                    $nota['USRCODIGO']
                                        ? 'Código ' . $nota['USRCODIGO']
                                        : ''
                                ) ?>
                            </small>
                        </td>

                        <td>
                            <a
                                class="table-action icon-action"
                                title="Ver seguimiento de la nota <?= e(
                                    $nota['NRO_PEDIDO']
                                ) ?>"
                                aria-label="Ver seguimiento de la nota <?= e(
                                    $nota['NRO_PEDIDO']
                                ) ?>"
                                href="<?= e(
                                    url(
                                        'seguimiento',
                                        [
                                            'q' => $nota['NRO_PEDIDO'],
                                            'tipo' => 'pedido',
                                        ]
                                    )
                                ) ?>"
                            >
                                <img
                                    src="<?= e(APP_URL) ?>assets/icons/bootstrap-eye.svg"
                                    alt=""
                                >
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$notas): ?>
                    <tr>
                        <td colspan="9">
                            No se encontraron notas con esos criterios.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <nav
        class="pagination"
        aria-label="Paginación de notas"
    >
        <span class="pagination-status" aria-current="page">
            Página <b><?= e((string) $resultado['pagina']) ?></b>
            de <b><?= e((string) $resultado['paginas']) ?></b>
        </span>
        <div class="pagination-actions">
            <?php if ($resultado['pagina'] > 1): ?>
                <a class="pagination-edge" href="<?= e($link(1)) ?>">« Primero</a>
                <a href="<?= e($link($resultado['pagina'] - 1)) ?>">← Anterior</a>
            <?php endif; ?>
            <?php if (
                $resultado['pagina'] < $resultado['paginas']
            ): ?>
                <a href="<?= e($link($resultado['pagina'] + 1)) ?>">
                    Siguiente →
                </a>
                <a class="pagination-edge" href="<?= e($link($resultado['paginas'])) ?>">Último »</a>
            <?php endif; ?>
        </div>
    </nav>
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

<?php require APP_ROOT . '/app/Views/partials/footer.php'; ?>
