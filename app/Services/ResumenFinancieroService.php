<?php
declare(strict_types=1);

final class ResumenFinancieroService
{
    /** Importes del comprobante en su moneda original; no convertir ni mezclar monedas. */
    public static function calcular(array $cabecera, array $comprobantes, string $campoTotal): array
    {
        $moneda = trim((string)($cabecera['MONEDA'] ?? ''));
        $total = self::centavos($cabecera[$campoTotal] ?? null);
        $facturado = 0;
        $pagado = 0;
        $vistos = [];
        foreach ($comprobantes as $comprobante) {
            if (strtoupper(trim((string)($comprobante['ESTADO'] ?? ''))) === 'A') continue;
            $id = trim((string)($comprobante['COMPROB'] ?? ''));
            if ($id !== '' && isset($vistos[$id])) continue;
            if ($id !== '') $vistos[$id] = true;
            if ($moneda === '' || trim((string)($comprobante['MONEDA'] ?? '')) !== $moneda) {
                $facturado = $pagado = null;
                break;
            }
            $importe = self::centavos($comprobante['PREVENT'] ?? null);
            $abono = self::centavos($comprobante['PAGADO'] ?? null);
            $facturado = $facturado === null || $importe === null ? null : $facturado + $importe;
            $pagado = $pagado === null || $abono === null ? null : $pagado + $abono;
        }
        return [
            'moneda' => $moneda,
            'total' => $total,
            'facturado' => $facturado,
            'pagado' => $pagado,
            'por_facturar' => $total === null || $facturado === null ? null : $total - $facturado,
            'por_pagar' => $facturado === null || $pagado === null ? null : $facturado - $pagado,
        ];
    }

    /** @param mixed $valor */
    private static function centavos($valor): ?int
    {
        return is_numeric($valor) ? (int)round((float)$valor * 100) : null;
    }
}
