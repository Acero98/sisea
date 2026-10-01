<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Services/ResumenFinancieroService.php';

function verificar(bool $condicion, string $caso): void {
    if (!$condicion) throw new RuntimeException($caso);
}
$cabecera = ['TOTALOC'=>'100.00', 'MONEDA'=>'01'];
$comprobante = ['COMPROB'=>'1','ESTADO'=>'','MONEDA'=>'01','PREVENT'=>'60.00','PAGADO'=>'20.00'];
$resultado = ResumenFinancieroService::calcular($cabecera, [$comprobante, $comprobante, array_merge($comprobante, ['COMPROB'=>'2','ESTADO'=>'A'])], 'TOTALOC');
verificar($resultado['facturado'] === 6000 && $resultado['pagado'] === 2000 && $resultado['por_facturar'] === 4000 && $resultado['por_pagar'] === 4000, 'Pago parcial, duplicados y anulados');
$resultado = ResumenFinancieroService::calcular($cabecera, [], 'TOTALOC');
verificar($resultado['por_facturar'] === 10000 && $resultado['por_pagar'] === 0, 'Sin comprobantes');
$resultado = ResumenFinancieroService::calcular([], [$comprobante], 'TOTALOC');
verificar($resultado['total'] === null && $resultado['por_pagar'] === null, 'Copia sin campos financieros');
$resultado = ResumenFinancieroService::calcular($cabecera, [array_merge($comprobante, ['MONEDA'=>'02'])], 'TOTALOC');
verificar($resultado['facturado'] === null && $resultado['por_facturar'] === null, 'Monedas diferentes');
$resultado = ResumenFinancieroService::calcular(['TOTALOS'=>'0.30','MONEDA'=>'01'], [array_merge($comprobante, ['PREVENT'=>'0.30','PAGADO'=>'0.10'])], 'TOTALOS');
verificar($resultado['por_pagar'] === 20 && $resultado['por_facturar'] === 0, 'Centavos y orden de servicio');
$resultado = ResumenFinancieroService::calcular($cabecera, [array_merge($comprobante, ['PAGADO'=>''])], 'TOTALOC');
verificar($resultado['pagado'] === null && $resultado['por_pagar'] === null, 'Pago ausente no equivale a cero');
$resultado = ResumenFinancieroService::calcular($cabecera, [array_merge($comprobante, ['PREVENT'=>'120','PAGADO'=>'130'])], 'TOTALOC');
verificar($resultado['por_facturar'] === -2000 && $resultado['por_pagar'] === -1000, 'Excesos visibles');
echo "OK: 7 casos financieros\n";
