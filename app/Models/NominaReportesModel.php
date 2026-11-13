<?php
declare(strict_types=1);

final class NominaReportesModel
{
    private const PLANILLA = ['CODSUCUR','CODREGIME','TIPPLANIL','CODPLANIL'];
    private const MESES = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SETIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];

    private function filas(string $tabla, array $campos, string $mes): iterable
    {
        $lector = new DbfReader(false);
        $archivo = AVALON_NOMINA_DATA.'/'.$tabla.'.dbf';
        if ($tabla === 'rh427000') {
            yield from $lector->findExact($archivo, $campos, 'PERIODO', str_replace('-', '', $mes));
            return;
        }
        $campos[] = 'MES';
        foreach ($lector->findExact($archivo, $campos, 'ANIO', substr($mes, 0, 4)) as $fila) {
            if ($fila['MES'] === substr($mes, 5)) yield $fila;
        }
    }

    private function clave(array $fila, array $campos): string
    {
        return implode('|', array_map(static fn($campo) => $fila[$campo], $campos));
    }

    private function catalogoConceptos(): array
    {
        $catalogo = [];
        $campos = ['CODREGIME','TIPCONCEP','CODCONCEP'];
        foreach ((new DbfReader(false))->rows(AVALON_NOMINA_DATA.'/rh109000.dbf', array_merge($campos,['DESCONCEP'])) as $fila) {
            $catalogo[$this->clave($fila,$campos)][$fila['DESCONCEP']] = true;
        }
        return $catalogo;
    }

    public function personal(string $mes): array
    {
        $regimenes = [];
        foreach ((new DbfReader(false))->rows(AVALON_NOMINA_DATA.'/rh102000.dbf',['CODREGIME','DESREGIME']) as $fila) {
            $regimenes[$fila['CODREGIME']] = $fila['DESREGIME'];
        }
        $anterior = (new DateTimeImmutable($mes.'-01'))->modify('-1 month')->format('Y-m');
        $grupos = [];
        foreach ([$anterior,$mes] as $periodo) {
            foreach ($this->filas('rh113000',['CODREGIME','CODCENCOS','CODTRABAJ'],$periodo) as $fila) {
                if ($fila['CODTRABAJ'] === '') continue;
                $clave = $this->clave($fila,['CODREGIME','CODCENCOS']);
                $grupos[$clave][$periodo][$fila['CODTRABAJ']] = true;
            }
        }
        ksort($grupos);
        $salida = [];
        foreach ($grupos as $clave=>$grupo) {
            [$regimen,$centro] = explode('|',$clave);
            $actual = count($grupo[$mes] ?? []);
            $previo = count($grupo[$anterior] ?? []);
            $salida[] = [trim($regimen.' '.($regimenes[$regimen] ?? '')), $centro ?: 'Sin centro', $previo, $actual, $actual-$previo];
        }
        return $salida;
    }

    public function conceptos(string $mes): array
    {
        $catalogo = $this->catalogoConceptos();
        $grupos = [];
        $campos = ['CODREGIME','TIPCONCEP','CONCEPPLA'];
        foreach ($this->filas('rh113100',array_merge($campos,['IMPCONCEP']),$mes) as $fila) {
            $clave = $this->clave($fila,$campos);
            $grupos[$clave]['lineas'] = ($grupos[$clave]['lineas'] ?? 0)+1;
            $grupos[$clave]['centimos'] = ($grupos[$clave]['centimos'] ?? 0)+(int)round((float)$fila['IMPCONCEP']*100);
        }
        ksort($grupos);
        $salida = [];
        $tipos = ['01'=>'Ingresos','02'=>'Descuentos','03'=>'Descuentos','04'=>'Aportes','SI'=>'Registro de sistema'];
        foreach ($grupos as $clave=>$grupo) {
            [$regimen,$tipo,$codigo] = explode('|',$clave);
            $nombres = array_keys($catalogo[$clave] ?? []);
            $nombre = count($nombres) === 1 ? $nombres[0] : ($nombres ? 'Nombre ambiguo' : 'Sin coincidencia en catálogo');
            if ($tipo === 'SI' && $codigo === '001' && !$nombres) {
                $nombre = 'Línea de sistema · contrastada con el neto en Control';
            }
            $salida[] = [$regimen, $tipo.' · '.($tipos[$tipo] ?? 'Por clasificar'), $codigo, $nombre, $grupo['lineas'], number_format($grupo['centimos']/100,2)];
        }
        return $salida;
    }

    public function distribucion(string $mes): array
    {
        $campos = ['SUCURSAL','ACTIVIDAD','CUENTA','COSTO','CODFUENTE'];
        $grupos = [];
        foreach ($this->filas('rh427000',array_merge($campos,['MONTO']),$mes) as $fila) {
            $clave = $this->clave($fila,$campos);
            $grupos[$clave]['detalle'] = ($grupos[$clave]['detalle'] ?? 0)+(int)round((float)$fila['MONTO']*100);
        }
        $columna = self::MESES[(int)substr($mes,5)-1];
        $lector = new DbfReader(false);
        foreach ($lector->findExact(AVALON_NOMINA_DATA.'/rh426000.dbf',array_merge($campos,[$columna]),'ANIO',substr($mes,0,4)) as $fila) {
            $clave = $this->clave($fila,$campos);
            $importe = (int)round((float)$fila[$columna]*100);
            if ($importe === 0 && !isset($grupos[$clave])) continue;
            $grupos[$clave]['resumen'] = ($grupos[$clave]['resumen'] ?? 0)+$importe;
        }
        ksort($grupos);
        $salida = [];
        foreach ($grupos as $clave=>$grupo) {
            $detalle = $grupo['detalle'] ?? null;
            $resumen = $grupo['resumen'] ?? null;
            $diferencia = isset($detalle,$resumen) ? number_format(($detalle-$resumen)/100,2) : 'Sin contraparte';
            $salida[] = array_merge(explode('|',$clave),[isset($detalle)?number_format($detalle/100,2):'—',isset($resumen)?number_format($resumen/100,2):'—',$diferencia]);
        }
        return $salida;
    }

    public function control(string $mes): array
    {
        $cabeceras = [];
        $personal = [];
        $incidencias = [];
        $catalogo = $this->catalogoConceptos();
        foreach ($this->filas('rh112000',self::PLANILLA,$mes) as $fila) {
            $cabeceras[$this->clave($fila,self::PLANILLA)] = true;
        }
        $campos = array_merge(self::PLANILLA,['CODTRABAJ']);
        foreach ($this->filas('rh113000',array_merge($campos,['IMPNETPAG']),$mes) as $fila) {
            $planilla = $this->clave($fila,self::PLANILLA);
            $personal[$this->clave($fila,$campos)] = (int)round((float)$fila['IMPNETPAG']*100);
            if (!isset($cabeceras[$planilla])) $incidencias[] = ['Trabajador sin cabecera',$planilla,$fila['CODTRABAJ'],'—'];
        }
        foreach ($this->filas('rh113100',array_merge($campos,['TIPCONCEP','CONCEPPLA','IMPCONCEP']),$mes) as $fila) {
            $concepto = $this->clave($fila,['CODREGIME','TIPCONCEP','CONCEPPLA']);
            $referencia = [$this->clave($fila,self::PLANILLA),$fila['CODTRABAJ'],$concepto];
            $trabajador = $this->clave($fila,$campos);
            if (!isset($personal[$trabajador])) $incidencias[] = array_merge(['Detalle sin trabajador en planilla'],$referencia);
            if ($fila['TIPCONCEP'] === 'SI' && $fila['CONCEPPLA'] === '001') {
                if (isset($personal[$trabajador]) && $personal[$trabajador] !== (int)round((float)$fila['IMPCONCEP']*100)) {
                    $incidencias[] = array_merge(['Línea SI 001 distinta del neto registrado'],$referencia);
                }
                continue;
            }
            if (count($catalogo[$concepto] ?? []) !== 1) $incidencias[] = array_merge([isset($catalogo[$concepto])?'Concepto ambiguo':'Concepto sin catálogo'],$referencia);
        }
        return $incidencias;
    }
}
