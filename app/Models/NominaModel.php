<?php
declare(strict_types=1);

final class NominaModel
{
    public function resumenMensual(bool $mostrarImportes = false): array
    {
        $lector = new DbfReader(false);
        $campos = ['ANIO', 'MES', 'CODSUCUR', 'CODREGIME', 'TIPPLANIL', 'CODPLANIL', 'CODTRABAJ'];
        $meses = [];
        $importes = ['IMPTOTING'=>'Ingresos', 'IMPTOTDSC'=>'Descuentos', 'IMPTOTAPO'=>'Aportes', 'IMPNETPAG'=>'Neto a pagar'];
        if ($mostrarImportes) $campos = array_merge($campos, array_keys($importes));
        foreach ($lector->indexedRows(AVALON_NOMINA_DATA.'/rh113000.dbf', $campos) as $registro) {
            $fila = $registro['datos'];
            if ($fila === null) continue;
            $periodo = $fila['ANIO'].'-'.$fila['MES'];
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $periodo)) continue;
            $planilla = implode('|', [$fila['CODSUCUR'], $fila['CODREGIME'], $fila['TIPPLANIL'], $fila['CODPLANIL']]);
            $meses[$periodo]['planillas'][$planilla] = true;
            if ($mostrarImportes) {
                foreach ($importes as $campo=>$nombre) {
                    $meses[$periodo]['importes'][$nombre] = ($meses[$periodo]['importes'][$nombre] ?? 0) + (int)round((float)$fila[$campo]*100);
                }
            }
            if ($fila['CODTRABAJ'] !== '') $meses[$periodo]['trabajadores'][$fila['CODTRABAJ']] = true;
        }
        krsort($meses);
        $resumen = [];
        foreach ($meses as $periodo => $datos) {
            $resumen[$periodo] = ['planillas'=>count($datos['planillas']), 'trabajadores'=>count($datos['trabajadores'] ?? [])];
            $anterior = (new DateTimeImmutable($periodo.'-01'))->modify('-1 month')->format('Y-m');
            if (isset($meses[$anterior])) {
                $actuales = $datos['trabajadores'] ?? [];
                $previos = $meses[$anterior]['trabajadores'] ?? [];
                $resumen[$periodo]['aparecen'] = count(array_diff_key($actuales,$previos));
                $resumen[$periodo]['ausentes'] = count(array_diff_key($previos,$actuales));
            }
            if ($mostrarImportes) $resumen[$periodo]['importes'] = $datos['importes'];
        }
        return $resumen;
    }
}
