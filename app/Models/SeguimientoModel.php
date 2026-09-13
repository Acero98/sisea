<?php
declare(strict_types=1);

final class SeguimientoModel
{
    private DbfReader $reader;
    public function __construct() { $this->reader = new DbfReader(); }
    public function buscar(string $term): ?array
    {
        $term = mb_strtoupper(trim($term)); if ($term === '') return null;
        $cached = $this->cache($term);
        if ($cached !== null) return $cached;
        $headers = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40014.DBF', ['NRO_PEDIDO','CODUNIORG','EST_PEDIDO','FEC_EMISIO','PRIORIDAD','TIPO_PED','COMENTARIO','TOTCANTSAL'], 'NRO_PEDIDO', $term);
        $header = $headers[0] ?? null;
        if (!$header) return null;
        $details = array_values(array_filter($this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40007.DBF', ['NRO_PEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','CAN_ORDENA','CENCOS','COD_PROY','CODACTIVID']), function ($row) use ($term) { return $row['NRO_PEDIDO'] === $term; }));
        $catalog = $this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40004.DBF', ['TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','DESC_MAT','UNIMED']);
        $services = $this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40003.DBF', ['TIPOFAMI','GRUPO','SUBGRUPO','SEC_SERV','DESC_SERV','UNIMED']);
        foreach ($details as &$detail) {
            $source = $detail['TIPOFAMI'] === 'S' ? $services : $catalog;
            $codeField = $detail['TIPOFAMI'] === 'S' ? 'SEC_SERV' : 'SEC_BIEN';
            $nameField = $detail['TIPOFAMI'] === 'S' ? 'DESC_SERV' : 'DESC_MAT';
            foreach ($source as $item) if ($detail['TIPOFAMI'] === $item['TIPOFAMI'] && $detail['GRUPO'] === $item['GRUPO'] && $detail['SUBGRUPO'] === $item['SUBGRUPO'] && $detail['SEC_BIEN'] === $item[$codeField]) { $detail['DESC_MAT'] = $item[$nameField]; $detail['UNIMED'] = $item['UNIMED']; break; }
            $detail['DESC_MAT'] = $detail['DESC_MAT'] ?? 'Recurso sin descripción'; $detail['UNIMED'] = $detail['UNIMED'] ?? '—';
            $centro = $detail['CENCOS'] !== '' ? $this->reader->findExact('C:/Avalon/contab/data/cct0004.DBF', ['CCTA','DESC'], 'CCTA', $detail['CENCOS']) : [];
            $detail['CENTRO_COSTO'] = $centro ? trim($centro[0]['CCTA'] . ' · ' . $centro[0]['DESC']) : ($detail['CENCOS'] ?: 'No registrado');
            $detail['COSTO_ABC'] = $detail['CODACTIVID'] ?: 'No registrado';
            $actividad = $detail['COD_PROY'] !== '' ? $this->reader->findExact(AVALON_PLANIF_DATA . '/mae_proy.dbf', ['COD_PROY','DESC_PROY'], 'COD_PROY', $detail['COD_PROY']) : [];
            $detail['ACTIVIDAD_PRESUPUESTAL'] = $actividad ? trim($actividad[0]['COD_PROY'] . ' · ' . $actividad[0]['DESC_PROY']) : ($detail['COD_PROY'] ?: 'No registrada');
        }
        unset($detail);
        $retirosAlmacen = $header['TIPO_PED'] === 'E'
            ? $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40008.DBF', ['NRO_PEDIDO','IDMOVIM','IDTRANSAC','TIPO_MOV','DOC_REF','FECHA','CANTIDAD','NUMALM','ALM_REF','ESTMOV','ENTREGADOA'], 'NRO_PEDIDO', $term)
            : [];
        $expediente = null; $certificacion = null; $ordenCompra = null; $ordenServicio = null;
        if ($header['TIPO_PED'] !== 'E') {
            $cotizaciones = $this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40025.DBF', ['NRO_EXPE','FEC_COTIZA','REFERENCIA','ESTADO']);
            $expediente = $this->contains($cotizaciones, 'REFERENCIA', $term);
            $certificacion = $expediente ? $this->contains($this->reader->rows(AVALON_PLANIF_DATA . '/cabcertifica.DBF', ['CODCER','FECHA','PROCESO']), 'PROCESO', $expediente['NRO_EXPE']) : null;
            $ordenesCompra = $this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40015.DBF', ['NRO_ORDCMP','NRO_EXPE','FEC_EMISIO','EST_ORDCMP','ESTFACTU','CERTIFICA','REFER']);
            $ordenesServicio = $this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40017.DBF', ['NRO_SERVIC','NRO_EXPE','FEC_EMISIO','ESTADO','ESTFACTU','CERTIFICA','REFER']);
            $ordenCompra = $expediente ? $this->first($ordenesCompra, 'NRO_EXPE', $expediente['NRO_EXPE']) : $this->contains($ordenesCompra, 'REFER', $term);
            $ordenServicio = $expediente ? $this->first($ordenesServicio, 'NRO_EXPE', $expediente['NRO_EXPE']) : $this->contains($ordenesServicio, 'REFER', $term);
        }
        $orden = $ordenServicio ?: $ordenCompra;
        $campoOrden = $ordenServicio ? 'NRO_SERVIC' : 'NRO_ORDCMP';
        $comprobante = null; $contable = null; $pago = null;
        if ($orden) {
            $comprobantes = $this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40030.DBF', ['COMPROB','NRO_SERVIC','NRO_ORDCMP','DOCSER','DOCNRO','FECCOMP','PREVENT','ESTADO','PAGADO','FECPAGO','NOMPROV']);
            $comprobante = $this->first($comprobantes, $campoOrden, $orden[$campoOrden]);
            if ($comprobante) {
                $prefijo = $ordenServicio ? 'OS' : 'OC';
                $contable = $this->contains($this->reader->rows('C:/Avalon/contab/data/FCON0024.DBF', ['FCONTABLE','DOC1','FECT','OCOS']), 'OCOS', $prefijo . $orden[$campoOrden]);
                $pago = $this->first($this->reader->rows('C:/Avalon/Finanzas/data/rfin0019.DBF', ['FCONTABLE','CMPBTE','DOC1','COMPROB','IMPORTE','ESTADO']), 'COMPROB', $comprobante['COMPROB']);
            }
        }
        $header['TIPO_LABEL'] = $this->labelTipo($header['TIPO_PED']);
        $header['PRIORIDAD_LABEL'] = $this->labelPrioridad($header['PRIORIDAD']);
        $header['ESTADO_LABEL'] = $this->labelEstado($header['EST_PEDIDO']);
        $unidad = $header['CODUNIORG'] !== '' ? $this->reader->findExact(AVALON_PLANIF_DATA . '/unidad.dbf', ['COD_UNIDAD','DES_UNIDAD'], 'COD_UNIDAD', $header['CODUNIORG']) : [];
        $header['UNIDAD_LABEL'] = $unidad ? trim($unidad[0]['COD_UNIDAD'] . ' · ' . $unidad[0]['DES_UNIDAD']) : ($header['CODUNIORG'] ?: 'Sin código en el origen');
        $result = ['pedido' => $header, 'detalle' => $details, 'retiros_almacen' => $retirosAlmacen, 'cotizacion' => $expediente, 'certificacion' => $certificacion, 'orden_compra' => $ordenCompra, 'orden_servicio' => $ordenServicio, 'comprobante' => $comprobante, 'contable' => $contable, 'pago' => $pago];
        $this->saveCache($term, $result);
        return $result;
    }
    private function first(array $rows, string $field, string $value): ?array { foreach ($rows as $row) if (trim($row[$field]) === trim($value)) return $row; return null; }
    private function contains(array $rows, string $field, string $value): ?array { foreach ($rows as $row) if (stripos($row[$field], $value) !== false) return $row; return null; }
    private function labelTipo(string $code): string { $map = ['E' => 'Existencia', 'S' => 'Servicios', 'A' => 'Activos']; return ($map[$code] ?? 'Tipo no identificado') . ' (' . ($code ?: 'sin código') . ')'; }
    private function labelPrioridad(string $code): string { $map = ['1' => 'Normal', '2' => 'Urgente']; return ($map[$code] ?? 'Prioridad no identificada') . ' (' . ($code ?: 'sin código') . ')'; }
    private function labelEstado(string $code): string { $map = ['A' => 'Anulado', 'P' => 'Pendiente', 'T' => 'Atendida']; return ($map[$code] ?? 'Estado no identificado') . ' (' . ($code ?: 'sin código') . ')'; }
    private function cache(string $term): ?array
    {
        $file = $this->cacheFile($term);
        if (!is_file($file) || filemtime($file) < time() - 300) return null;
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }
    private function saveCache(string $term, array $data): void
    {
        $directory = dirname($this->cacheFile($term));
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        @file_put_contents($this->cacheFile($term), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    private function cacheFile(string $term): string { return APP_ROOT . '/storage/cache/seguimiento-v6-' . sha1($term) . '.json'; }
}
