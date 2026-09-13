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
        $headers = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40014.DBF', ['NRO_PEDIDO','CODUNIORG','EST_PEDIDO','FEC_EMISIO','PRIORIDAD','TIPO_PED','COMENTARIO','TOTCANTSAL','CERTIFICA','DOCCERTI'], 'NRO_PEDIDO', $term);
        $header = $headers[0] ?? null;
        if (!$header) return null;
        $header['ES_RETIRO_ALMACEN'] = $header['PRIORIDAD'] === '4';
        $details = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40007.DBF', ['NRO_PEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','CAN_ORDENA','CENCOS','COD_PROY','CODACTIVID'], 'NRO_PEDIDO', $term);
        foreach ($details as &$detail) {
            $sourceFile = $detail['TIPOFAMI'] === 'S' ? '/sum40003.DBF' : '/sum40004.DBF';
            $codeField = $detail['TIPOFAMI'] === 'S' ? 'SEC_SERV' : 'SEC_BIEN';
            $nameField = $detail['TIPOFAMI'] === 'S' ? 'DESC_SERV' : 'DESC_MAT';
            $sourceFields = ['TIPOFAMI','GRUPO','SUBGRUPO',$codeField,$nameField,'UNIMED'];
            $source = $this->reader->findExact(AVALON_LOGISTICA_DATA . $sourceFile, $sourceFields, $codeField, $detail['SEC_BIEN']);
            foreach ($source as $item) if ($detail['TIPOFAMI'] === $item['TIPOFAMI'] && $detail['GRUPO'] === $item['GRUPO'] && $detail['SUBGRUPO'] === $item['SUBGRUPO'] && $detail['SEC_BIEN'] === $item[$codeField]) { $detail['DESC_MAT'] = $item[$nameField]; $detail['UNIMED'] = $item['UNIMED']; break; }
            $detail['DESC_MAT'] = $detail['DESC_MAT'] ?? 'Recurso sin descripción'; $detail['UNIMED'] = $detail['UNIMED'] ?? '—';
            $centro = $detail['CENCOS'] !== '' ? $this->reader->findExact('C:/Avalon/contab/data/cct0004.DBF', ['CCTA','DESC'], 'CCTA', $detail['CENCOS']) : [];
            $detail['CENTRO_COSTO'] = $centro ? trim($centro[0]['CCTA'] . ' · ' . $centro[0]['DESC']) : ($detail['CENCOS'] ?: 'No registrado');
            $detail['COSTO_ABC'] = $detail['CODACTIVID'] ?: 'No registrado';
            $actividad = $detail['COD_PROY'] !== '' ? $this->reader->findExact(AVALON_PLANIF_DATA . '/mae_proy.dbf', ['COD_PROY','DESC_PROY'], 'COD_PROY', $detail['COD_PROY']) : [];
            $detail['ACTIVIDAD_PRESUPUESTAL'] = $actividad ? trim($actividad[0]['COD_PROY'] . ' · ' . $actividad[0]['DESC_PROY']) : ($detail['COD_PROY'] ?: 'No registrada');
        }
        unset($detail);
        $retirosAlmacen = $header['ES_RETIRO_ALMACEN']
            ? $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40008.DBF', ['NRO_PEDIDO','IDMOVIM','IDTRANSAC','TIPO_MOV','DOC_REF','FECHA','CANTIDAD','NUMALM','ALM_REF','ESTMOV','ENTREGADOA','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','SEC_ITEMMO'], 'NRO_PEDIDO', $term)
            : [];
        foreach ($retirosAlmacen as &$movimiento) {
            $movimiento['RECURSO'] = 'Recurso no identificado';
            foreach ($details as $detail) {
                if ($movimiento['TIPOFAMI'] === $detail['TIPOFAMI'] && $movimiento['GRUPO'] === $detail['GRUPO'] && $movimiento['SUBGRUPO'] === $detail['SUBGRUPO'] && $movimiento['SEC_BIEN'] === $detail['SEC_BIEN']) {
                    $movimiento['RECURSO'] = $detail['DESC_MAT'];
                    break;
                }
            }
        }
        unset($movimiento);
        $cotizaciones = []; $certificaciones = []; $ordenCompra = null; $ordenServicio = null; $ordenCompraRows = []; $ordenServicioRows = [];
        if (!$header['ES_RETIRO_ALMACEN']) {
            $cotizaciones = $this->reader->findContains(AVALON_LOGISTICA_DATA . '/sum40025.DBF', ['NRO_EXPE','FEC_COTIZA','REFERENCIA','ESTADO'], 'REFERENCIA', $term);
            // Un expediente puede tener varias líneas internas en Avalon. Para el
            // seguimiento documental representa una sola cotización/expediente.
            $cotizacionesPorExpediente = [];
            foreach ($cotizaciones as $cotizacion) {
                if ($cotizacion['NRO_EXPE'] !== '') $cotizacionesPorExpediente[$cotizacion['NRO_EXPE']] = $cotizacion;
            }
            $cotizaciones = array_values($cotizacionesPorExpediente);
        }
        if ($header['DOCCERTI'] !== '') {
            $directa = $this->reader->findExact(AVALON_PLANIF_DATA . '/cabcertifica.DBF', ['CODCER','FECHA','PROCESO','ESTADO'], 'CODCER', $header['DOCCERTI']);
            foreach ($directa as $certificacionDirecta) $certificaciones[$certificacionDirecta['CODCER']] = $certificacionDirecta;
        }
        $ordenes = [];
        foreach ($cotizaciones as &$cotizacion) {
            $expediente = $cotizacion['NRO_EXPE'];
            $cotizacion['CERTIFICACIONES'] = $this->reader->findContains(AVALON_PLANIF_DATA . '/cabcertifica.DBF', ['CODCER','FECHA','PROCESO','ESTADO'], 'PROCESO', $expediente);
            foreach ($cotizacion['CERTIFICACIONES'] as $certificacion) $certificaciones[$certificacion['CODCER']] = $certificacion;
            $comprasCotizacion = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40015.DBF', ['NRO_ORDCMP','NRO_EXPE','FEC_EMISIO','EST_ORDCMP','ESTFACTU','CERTIFICA','REFER'], 'NRO_EXPE', $expediente);
            $serviciosCotizacion = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40017.DBF', ['NRO_SERVIC','NRO_EXPE','FEC_EMISIO','ESTADO','ESTFACTU','CERTIFICA','REFER'], 'NRO_EXPE', $expediente);
            $cotizacion['ORDENES'] = [];
            foreach ($comprasCotizacion as $orden) $cotizacion['ORDENES'][] = ['TIPO' => 'Orden de compra', 'NUMERO' => $orden['NRO_ORDCMP'], 'FECHA' => $orden['FEC_EMISIO'], 'ESTADO' => $orden['EST_ORDCMP'], 'CAMPO_COMPROBANTE' => 'NRO_ORDCMP', 'PREFIJO' => 'OC', 'EXPEDIENTE' => $expediente];
            foreach ($serviciosCotizacion as $orden) $cotizacion['ORDENES'][] = ['TIPO' => 'Orden de servicio', 'NUMERO' => $orden['NRO_SERVIC'], 'FECHA' => $orden['FEC_EMISIO'], 'ESTADO' => $orden['ESTADO'], 'CAMPO_COMPROBANTE' => 'NRO_SERVIC', 'PREFIJO' => 'OS', 'EXPEDIENTE' => $expediente];
            foreach ($cotizacion['ORDENES'] as $orden) $ordenes[] = $orden;
            $ordenCompraRows = array_merge($ordenCompraRows, $comprasCotizacion);
            $ordenServicioRows = array_merge($ordenServicioRows, $serviciosCotizacion);
        }
        unset($cotizacion);
        if (!$cotizaciones) {
            $comprasDirectas = $this->reader->findContains(AVALON_LOGISTICA_DATA . '/sum40015.DBF', ['NRO_ORDCMP','NRO_EXPE','FEC_EMISIO','EST_ORDCMP','ESTFACTU','CERTIFICA','REFER'], 'REFER', $term);
            $serviciosDirectos = $this->reader->findContains(AVALON_LOGISTICA_DATA . '/sum40017.DBF', ['NRO_SERVIC','NRO_EXPE','FEC_EMISIO','ESTADO','ESTFACTU','CERTIFICA','REFER'], 'REFER', $term);
            foreach ($comprasDirectas as $orden) $ordenes[] = ['TIPO' => 'Orden de compra', 'NUMERO' => $orden['NRO_ORDCMP'], 'FECHA' => $orden['FEC_EMISIO'], 'ESTADO' => $orden['EST_ORDCMP'], 'CAMPO_COMPROBANTE' => 'NRO_ORDCMP', 'PREFIJO' => 'OC', 'EXPEDIENTE' => $orden['NRO_EXPE']];
            foreach ($serviciosDirectos as $orden) $ordenes[] = ['TIPO' => 'Orden de servicio', 'NUMERO' => $orden['NRO_SERVIC'], 'FECHA' => $orden['FEC_EMISIO'], 'ESTADO' => $orden['ESTADO'], 'CAMPO_COMPROBANTE' => 'NRO_SERVIC', 'PREFIJO' => 'OS', 'EXPEDIENTE' => $orden['NRO_EXPE']];
        }
        $resumenDocumental = ['comprobantes' => 0, 'devengados' => 0, 'pagos' => 0];
        foreach ($ordenes as &$orden) {
            $orden['COMPROBANTES'] = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40030.DBF', ['COMPROB','NRO_SERVIC','NRO_ORDCMP','DOCSER','DOCNRO','FECCOMP','ESTADO','PAGADO','FECPAGO','NOMPROV'], $orden['CAMPO_COMPROBANTE'], $orden['NUMERO']);
            $orden['DEVENGADOS'] = $this->reader->findContains('C:/Avalon/contab/data/FCON0024.DBF', ['FCONTABLE','DOC1','FECT','OCOS'], 'OCOS', $orden['PREFIJO'] . $orden['NUMERO']);
            $resumenDocumental['comprobantes'] += count($orden['COMPROBANTES']);
            $resumenDocumental['devengados'] += count($orden['DEVENGADOS']);
            foreach ($orden['COMPROBANTES'] as &$comprobanteOrden) {
                $comprobanteOrden['PAGOS'] = $this->reader->findExact('C:/Avalon/Finanzas/data/rfin0019.DBF', ['FCONTABLE','CMPBTE','DOC1','COMPROB','IMPORTE','ESTADO'], 'COMPROB', $comprobanteOrden['COMPROB']);
                $resumenDocumental['pagos'] += count($comprobanteOrden['PAGOS']);
            }
            unset($comprobanteOrden);
        }
        unset($orden);
        foreach ($cotizaciones as &$cotizacion) {
            foreach ($cotizacion['ORDENES'] as &$ordenCotizacion) {
                foreach ($ordenes as $ordenCompleta) {
                    if ($ordenCompleta['TIPO'] === $ordenCotizacion['TIPO'] && $ordenCompleta['NUMERO'] === $ordenCotizacion['NUMERO']) {
                        $ordenCotizacion = $ordenCompleta;
                        break;
                    }
                }
            }
            unset($ordenCotizacion);
        }
        unset($cotizacion);
        $cotizacionesValidas = array_values(array_filter($cotizaciones, fn(array $cotizacion): bool => !$this->isAnulado($cotizacion['ESTADO'] ?? '')));
        $cotizacionesAnuladas = count($cotizaciones) - count($cotizacionesValidas);
        $certificacionesValidas = array_values(array_filter($certificaciones, fn(array $certificacion): bool => !$this->isAnulado($certificacion['ESTADO'] ?? '')));
        $certificacionesAnuladas = count($certificaciones) - count($certificacionesValidas);
        $totalCertificacionesRuta = $cotizacionesValidas ? count($cotizacionesValidas) : count($certificaciones);
        $certificacionesCompletadasRuta = $cotizacionesValidas ? min(count($cotizacionesValidas), count($certificacionesValidas)) : count($certificacionesValidas);
        $ordenesValidas = array_values(array_filter($ordenes, fn(array $orden): bool => !$this->isAnulado($orden['ESTADO'] ?? '')));
        $ordenesAnuladas = count($ordenes) - count($ordenesValidas);
        $expedientesConOrden = count(array_unique(array_filter(array_map(fn(array $orden): string => $orden['EXPEDIENTE'] ?? '', $ordenesValidas))));
        $ordenesConComprobante = 0;
        $ordenesConDevengado = 0;
        $comprobantesValidos = [];
        $comprobantesAnulados = 0;
        $pagosAnulados = 0;
        $cantidadPagosValidos = 0;
        foreach ($ordenesValidas as $orden) {
            $comprobantesOrden = array_values(array_filter($orden['COMPROBANTES'], fn(array $comprobante): bool => !$this->isAnulado($comprobante['ESTADO'] ?? '')));
            $comprobantesAnulados += count($orden['COMPROBANTES']) - count($comprobantesOrden);
            if ($comprobantesOrden) $ordenesConComprobante++;
            if ($comprobantesOrden && $orden['DEVENGADOS']) $ordenesConDevengado++;
            foreach ($comprobantesOrden as $comprobante) {
                $pagosValidos = array_filter($comprobante['PAGOS'], fn(array $pago): bool => !$this->isAnulado($pago['ESTADO'] ?? ''));
                $pagosAnulados += count($comprobante['PAGOS']) - count($pagosValidos);
                $cantidadPagosValidos += count($pagosValidos);
                $comprobantesValidos[] = $comprobante;
            }
        }
        $avance = [
            'cotizaciones' => $this->etapaAvance(count($cotizacionesValidas), count($cotizaciones), $cotizacionesAnuladas),
            'certificaciones' => $this->etapaAvance($certificacionesCompletadasRuta, $totalCertificacionesRuta, $certificacionesAnuladas),
            'ordenes' => $this->etapaAvance(count($ordenesValidas), count($ordenes), $ordenesAnuladas, count($ordenesValidas) . ' orden(es) válida(s)'),
            'comprobantes' => $this->etapaAvance($ordenesConComprobante, count($ordenesValidas), $comprobantesAnulados),
            'devengados' => $this->etapaAvance($ordenesConDevengado, $ordenesConComprobante, 0),
            'pagos' => $this->etapaAvance($cantidadPagosValidos, $cantidadPagosValidos + $pagosAnulados, $pagosAnulados),
        ];
        $comprobante = $ordenes[0]['COMPROBANTES'][0] ?? null;
        $contable = $ordenes[0]['DEVENGADOS'][0] ?? null;
        $pago = $comprobante['PAGOS'][0] ?? null;
        $header['TIPO_LABEL'] = $this->labelTipo($header['TIPO_PED']);
        $header['PRIORIDAD_LABEL'] = $this->labelPrioridad($header['PRIORIDAD']);
        $header['ESTADO_LABEL'] = $this->labelEstado($header['EST_PEDIDO']);
        $unidad = $header['CODUNIORG'] !== '' ? $this->reader->findExact(AVALON_PLANIF_DATA . '/unidad.dbf', ['COD_UNIDAD','DES_UNIDAD'], 'COD_UNIDAD', $header['CODUNIORG']) : [];
        $header['UNIDAD_LABEL'] = $unidad ? trim($unidad[0]['COD_UNIDAD'] . ' · ' . $unidad[0]['DES_UNIDAD']) : ($header['CODUNIORG'] ?: 'Sin código en el origen');
        $result = ['pedido' => $header, 'detalle' => $details, 'retiros_almacen' => $retirosAlmacen, 'cotizaciones' => $cotizaciones, 'certificaciones' => array_values($certificaciones), 'ordenes' => $ordenes, 'resumen_documental' => $resumenDocumental, 'avance' => $avance, 'orden_compra' => $ordenCompraRows[0] ?? null, 'orden_servicio' => $ordenServicioRows[0] ?? null, 'comprobante' => $comprobante, 'contable' => $contable, 'pago' => $pago];
        $this->saveCache($term, $result);
        return $result;
    }
    private function first(array $rows, string $field, string $value): ?array { foreach ($rows as $row) if (trim($row[$field]) === trim($value)) return $row; return null; }
    private function contains(array $rows, string $field, string $value): ?array { foreach ($rows as $row) if (stripos($row[$field], $value) !== false) return $row; return null; }
    private function labelTipo(string $code): string { $map = ['E' => 'Existencia', 'S' => 'Servicios', 'A' => 'Activos']; return ($map[$code] ?? 'Tipo no identificado') . ' (' . ($code ?: 'sin código') . ')'; }
    private function labelPrioridad(string $code): string { $map = ['1' => 'Normal', '2' => 'Urgente', '3' => 'Emergencia', '4' => 'Retiro de almacén']; return ($map[$code] ?? 'Prioridad no identificada') . ' (' . ($code ?: 'sin código') . ')'; }
    private function labelEstado(string $code): string { $map = ['A' => 'Anulado', 'P' => 'Pendiente', 'T' => 'Atendida']; return ($map[$code] ?? 'Estado no identificado') . ' (' . ($code ?: 'sin código') . ')'; }
    private function isAnulado(string $estado): bool { return mb_strtoupper(trim($estado)) === 'A'; }
    private function etapaAvance(int $completados, int $total, int $anulados = 0, string $detalle = ''): array
    {
        $pendientes = max(0, $total - $completados - $anulados);
        if ($anulados > 0) {
            $estado = $completados > $anulados ? 'complete' : ($completados === $anulados ? 'partial' : 'issue');
        } else {
            $estado = $total === 0 ? 'pending' : ($completados >= $total ? 'complete' : ($completados > 0 ? 'partial' : 'pending'));
        }
        $nota = $detalle;
        if ($anulados > 0) $nota = trim($nota . ' · ' . $anulados . ' anulado(s)');
        elseif ($pendientes > 0) $nota = trim($nota . ' · ' . $pendientes . ' pendiente(s)');
        return ['estado' => $estado, 'cantidad' => $total > 0 ? $completados . '/' . $total : '—', 'nota' => trim($nota, ' ·')];
    }
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
    private function cacheFile(string $term): string { return APP_ROOT . '/storage/cache/seguimiento-v18-' . sha1($term) . '.json'; }
}
