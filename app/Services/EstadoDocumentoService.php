<?php
declare(strict_types=1);

final class EstadoDocumentoService
{
    // Equivalencias verificadas: docs/ESTADOS_AVALON_VERIFICADOS.md.
    public static function etiqueta(string $tipo, ?string $codigo): string
    {
        $codigo = strtoupper(trim($codigo ?? ''));
        $mapas = [
            'expediente' => ['P'=>'Pendiente', 'C'=>'Atendida', 'A'=>'Anulada'],
            'orden' => ['P'=>'Pendiente de atención', 'C'=>'Atendida', 'S'=>'Parcialmente atendida', 'A'=>'Anulada'],
            'comprobante' => ['P'=>'Pagada', 'A'=>'Anulada'],
            'pago' => ['T'=>'Total', 'P'=>'Parcial', 'A'=>'Anulado'],
            'pago_orden' => ['C'=>'Pagada completamente'],
        ];
        if ($codigo === '') return $tipo === 'comprobante' ? 'Registrada' : 'Estado no disponible';
        return isset($mapas[$tipo][$codigo]) ? $mapas[$tipo][$codigo].' ('.$codigo.')' : 'Estado '.$codigo.' — por confirmar';
    }

    public static function estadoExpediente(array $cotizacion, array $cabeceras): string
    {
        $coincidencias = array_values(array_filter($cabeceras, static function (array $fila) use ($cotizacion): bool {
            foreach (['ZONA','LOCA','NRO_EXPE'] as $campo) {
                if (trim((string)($cotizacion[$campo] ?? '')) === '' || trim((string)($fila[$campo] ?? '')) !== trim((string)$cotizacion[$campo])) return false;
            }
            return true;
        }));
        return count($coincidencias) === 1 ? trim((string)($coincidencias[0]['ESTADO'] ?? '')) : '';
    }
}
