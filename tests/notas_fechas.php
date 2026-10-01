<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/app.php';
require APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/Models/NotasModel.php';
require_once APP_ROOT . '/app/Controllers/NotasController.php';

function comprobar(bool $condicion, string $caso): void {
    if (!$condicion) throw new RuntimeException($caso);
}

// A connection-local temporary table shadows the real table without modifying it.
$pdo = db();
$pdo->exec('CREATE TEMPORARY TABLE seguimiento_notas (
    nro_pedido VARCHAR(20), fecha_emision DATE NULL, fecha_registro DATE,
    unidad_organica VARCHAR(10), unidad_nombre VARCHAR(60), usuario_codigo VARCHAR(10),
    usuario_nombre VARCHAR(60), sustentacion VARCHAR(100), tipo_pedido VARCHAR(1),
    prioridad VARCHAR(1), etapa VARCHAR(30), estado_logistica VARCHAR(1)
)');
try {
    $insertar = $pdo->prepare("INSERT INTO seguimiento_notas VALUES (?, ?, ?, ?, 'Unidad', ?, 'Usuario', ?, 'S', '1', 'Pedido', 'P')");
    foreach ([
        ['1', '2026-09-01', 'A', '001', 'Papel'],
        ['2', '2026-09-30', 'A', '001', 'Toner'],
        ['3', '2026-10-01', 'A', '001', 'Papel'],
        ['4', '2026-09-15', 'B', '002', 'Papel'],
        ['5', null, 'A', '001', 'Papel'],
        ['6', '2026-09-20', 'A', '003', 'Papel'],
    ] as [$numero, $fecha, $unidad, $usuario, $glosa]) {
        $insertar->execute([$numero, $fecha, $fecha, $unidad, $usuario, $glosa]);
    }
    $modelo = new NotasModel();
    $acceso = ['alcance' => 'GLOBAL', 'unidad_organica' => 'A', 'avalon_codigo' => '001'];
    $listado = $modelo->listar($acceso, 'unidad', '', 1, 2, '2026-09-01', '2026-09-30');
    comprobar($listado['total'] === 4 && $listado['paginas'] === 2 && count($listado['items']) === 2, 'Conteo y paginación filtrados');
    $exportado = $modelo->exportar($acceso, 'unidad', '', '2026-09-01', '2026-09-30');
    $segunda = $modelo->listar($acceso, 'unidad', '', 2, 2, '2026-09-01', '2026-09-30');
    comprobar(array_column(array_merge($listado['items'], $segunda['items']), 'nro_pedido') === array_column($exportado, 'nro_pedido'), 'CSV contiene todas las páginas del mismo rango');
    comprobar(array_column($exportado, 'nro_pedido') === ['2', '6', '4', '1'], 'Ambos límites inclusivos, sin fechas nulas ni externas');
    comprobar(count($modelo->exportar($acceso, 'mis', '', '2026-09-01', '2026-09-30')) === 2, 'Alcance personal');
    $acceso['alcance'] = 'UNIDAD';
    comprobar(count($modelo->exportar($acceso, 'unidad', '', '2026-09-01', '2026-09-30')) === 3, 'Alcance de unidad');
    comprobar(count($modelo->exportar($acceso, 'unidad', 'Toner', '2026-09-01', '2026-09-30')) === 1, 'Texto combinado con fechas');
    comprobar(count($modelo->exportar($acceso, 'mis', '', '2026-09-30', '2026-09-30')) === 1, 'Un solo día');
    comprobar(count($modelo->exportar($acceso, 'mis', '', '2026-10-01', '')) === 1, 'Solo Desde');
    comprobar(count($modelo->exportar($acceso, 'mis', '', '', '2026-09-01')) === 1, 'Solo Hasta');
    comprobar(count($modelo->exportar($acceso, 'mis', '')) === 4, 'Sin rango conserva fechas nulas');
    comprobar($modelo->listar($acceso, 'mis', '', 1, 10, '2027-01-01', '')['total'] === 0, 'Sin resultados');
    $validar = new ReflectionMethod(NotasController::class, 'fechas');
    $validar->setAccessible(true);
    foreach ([['2026-02-30', ''], ['2026-10-01', '2026-09-01'], ['mal', ''], [[], ''], ["2026-01-01\0", '']] as [$desde, $hasta]) {
        $_GET = compact('desde', 'hasta');
        comprobar($validar->invoke(new NotasController())[2] !== '', 'Rechazar rango inválido');
    }
    $_GET = ['desde' => '2024-02-29', 'hasta' => '2024-02-29'];
    comprobar($validar->invoke(new NotasController())[2] === '', 'Fecha bisiesta válida');
    echo "OK: fechas inclusivas, paginación, exportación, permisos y validación.\n";
} finally {
    $pdo->exec('DROP TEMPORARY TABLE seguimiento_notas');
}
