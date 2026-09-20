<?php
declare(strict_types=1);

final class SeguimientoModel
{
    private DbfReader $reader;
    public function __construct() { $this->reader = new DbfReader(); }
    public function buscar(string $term, string $tipo = 'todo', array $acceso = []): ?array
    {
        $term = mb_strtoupper(trim($term)); if ($term === '') return null;
        $tipo = in_array($tipo, ['todo', 'pedido', 'cotizacion', 'certificacion', 'orden_compra', 'orden_servicio', 'comprobante', 'devengado', 'pago'], true) ? $tipo : 'todo';
        $cacheKey = $tipo . '-' . $term;
        $cached = $this->cache($cacheKey);
        if ($cached !== null) return $cached;
        $documentoBuscado = null;
        $headers = in_array($tipo, ['todo', 'pedido'], true) ? $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40014.DBF', ['NRO_PEDIDO','CODUNIORG','EST_PEDIDO','FEC_EMISIO','PRIORIDAD','TIPO_PED','COMENTARIO','TOTCANTSAL','CERTIFICA','DOCCERTI'], 'NRO_PEDIDO', $term) : [];
        if (!$headers && $tipo !== 'pedido') {
            $documentoBuscado = $this->documentoDesdeCopiaLocal($term, $tipo);
            $pedidoResuelto = ($documentoBuscado['nro_pedido'] ?? null) ?? $this->pedidoDesdeOrden($term, $tipo);
            if ($pedidoResuelto !== null) $headers = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40014.DBF', ['NRO_PEDIDO','CODUNIORG','EST_PEDIDO','FEC_EMISIO','PRIORIDAD','TIPO_PED','COMENTARIO','TOTCANTSAL','CERTIFICA','DOCCERTI'], 'NRO_PEDIDO', $pedidoResuelto);
        }
        $header = $headers[0] ?? null;
        if (!$header) return null;
        if (!$this->puedeVerPedido($header, $acceso)) return null;
        $pedido = $header['NRO_PEDIDO'];
        $header['ES_RETIRO_ALMACEN'] = $header['PRIORIDAD'] === '4';
        $details = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40007.DBF', ['NRO_PEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','CAN_ORDENA','CENCOS','COD_PROY','CODACTIVID','CTA_CON'], 'NRO_PEDIDO', $pedido);
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
            $cuenta = $detail['CTA_CON'] !== '' ? $this->reader->findExact(AVALON_PLANIF_DATA . '/tab_recu.dbf', ['COD_CCP1','DESCRIP'], 'COD_CCP1', $detail['CTA_CON']) : [];
            $detail['CUENTA_CONTABLE'] = $cuenta ? trim($cuenta[0]['COD_CCP1'] . ' · ' . $cuenta[0]['DESCRIP']) : ($detail['CTA_CON'] ?: 'No registrada');
            $actividad = $detail['COD_PROY'] !== '' ? $this->reader->findExact(AVALON_PLANIF_DATA . '/mae_proy.dbf', ['COD_PROY','DESC_PROY'], 'COD_PROY', $detail['COD_PROY']) : [];
            $detail['ACTIVIDAD_PRESUPUESTAL'] = $actividad ? trim($actividad[0]['COD_PROY'] . ' · ' . $actividad[0]['DESC_PROY']) : ($detail['COD_PROY'] ?: 'No registrada');
        }
        unset($detail);
        $retirosAlmacen = $header['ES_RETIRO_ALMACEN']
            ? $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40008.DBF', ['NRO_PEDIDO','IDMOVIM','IDTRANSAC','TIPO_MOV','DOC_REF','FECHA','CANTIDAD','NUMALM','ALM_REF','ESTMOV','ENTREGADOA','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','SEC_ITEMMO'], 'NRO_PEDIDO', $pedido)
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
            $cotizaciones = $this->reader->findContains(AVALON_LOGISTICA_DATA . '/sum40025.DBF', ['NRO_EXPE','FEC_COTIZA','REFERENCIA','ESTADO'], 'REFERENCIA', $pedido);
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
            $certificacionesExpediente = $this->reader->findContains(AVALON_PLANIF_DATA . '/cabcertifica.DBF', ['CODCER','FECHA','PROCESO','ESTADO'], 'PROCESO', $expediente);
            $cotizacion['CERTIFICACIONES'] = array_values(array_filter($certificacionesExpediente, fn(array $certificacion): bool => $this->esCertificacionDeExpediente($certificacion['PROCESO'], $expediente)));
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
            $comprasDirectas = $this->reader->findContains(AVALON_LOGISTICA_DATA . '/sum40015.DBF', ['NRO_ORDCMP','NRO_EXPE','FEC_EMISIO','EST_ORDCMP','ESTFACTU','CERTIFICA','REFER'], 'REFER', $pedido);
            $serviciosDirectos = $this->reader->findContains(AVALON_LOGISTICA_DATA . '/sum40017.DBF', ['NRO_SERVIC','NRO_EXPE','FEC_EMISIO','ESTADO','ESTFACTU','CERTIFICA','REFER'], 'REFER', $pedido);
            foreach ($comprasDirectas as $orden) $ordenes[] = ['TIPO' => 'Orden de compra', 'NUMERO' => $orden['NRO_ORDCMP'], 'FECHA' => $orden['FEC_EMISIO'], 'ESTADO' => $orden['EST_ORDCMP'], 'CAMPO_COMPROBANTE' => 'NRO_ORDCMP', 'PREFIJO' => 'OC', 'EXPEDIENTE' => $orden['NRO_EXPE']];
            foreach ($serviciosDirectos as $orden) $ordenes[] = ['TIPO' => 'Orden de servicio', 'NUMERO' => $orden['NRO_SERVIC'], 'FECHA' => $orden['FEC_EMISIO'], 'ESTADO' => $orden['ESTADO'], 'CAMPO_COMPROBANTE' => 'NRO_SERVIC', 'PREFIJO' => 'OS', 'EXPEDIENTE' => $orden['NRO_EXPE']];
        }
        $resumenDocumental = ['comprobantes' => 0, 'devengados' => 0, 'pagos' => 0];
        foreach ($ordenes as &$orden) {
            $orden['COMPROBANTES'] = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40030.DBF', ['COMPROB','NRO_SERVIC','NRO_ORDCMP','DOCSER','DOCNRO','FECCOMP','ESTADO','PAGADO','FECPAGO','NOMPROV'], $orden['CAMPO_COMPROBANTE'], $orden['NUMERO']);
            $orden['DEVENGADOS'] = $this->reader->findContains('C:/Avalon/contab/data/FCON0024.DBF', ['FCONTABLE','DOC1','FECT','OCOS','SERIE','NUMERO'], 'OCOS', $orden['PREFIJO'] . $orden['NUMERO']);
            $orden['DEVENGADOS_SIN_COMPROBANTE'] = $orden['DEVENGADOS'];
            $resumenDocumental['comprobantes'] += count($orden['COMPROBANTES']);
            $resumenDocumental['devengados'] += count($orden['DEVENGADOS']);
            foreach ($orden['COMPROBANTES'] as &$comprobanteOrden) {
                $comprobanteOrden['PAGOS'] = $this->reader->findExact('C:/Avalon/Finanzas/data/rfin0019.DBF', ['FCONTABLE','CMPBTE','DOC1','COMPROB','IMPORTE','ESTADO'], 'COMPROB', $comprobanteOrden['COMPROB']);
                $comprobanteOrden['DEVENGADOS'] = [];
                foreach ($orden['DEVENGADOS'] as $indiceDevengado => $devengado) {
                    if ($this->mismoComprobante($comprobanteOrden['DOCSER'], $comprobanteOrden['DOCNRO'], $devengado['SERIE'], $devengado['NUMERO'])) {
                        $comprobanteOrden['DEVENGADOS'][] = $devengado;
                        unset($orden['DEVENGADOS_SIN_COMPROBANTE'][$indiceDevengado]);
                    }
                }
                $resumenDocumental['pagos'] += count($comprobanteOrden['PAGOS']);
            }
            $orden['DEVENGADOS_SIN_COMPROBANTE'] = array_values($orden['DEVENGADOS_SIN_COMPROBANTE']);
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
        $cantidadDevengadosValidos = 0;
        foreach ($ordenesValidas as $orden) {
            $comprobantesOrden = array_values(array_filter($orden['COMPROBANTES'], fn(array $comprobante): bool => !$this->isAnulado($comprobante['ESTADO'] ?? '')));
            $comprobantesAnulados += count($orden['COMPROBANTES']) - count($comprobantesOrden);
            if ($comprobantesOrden) $ordenesConComprobante++;
            if ($comprobantesOrden && $orden['DEVENGADOS']) $ordenesConDevengado++;
            $cantidadDevengadosValidos += count($orden['DEVENGADOS']);
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
        $resumenDocumental = ['comprobantes' => count($comprobantesValidos), 'devengados' => $cantidadDevengadosValidos, 'pagos' => $cantidadPagosValidos];

        $documentosAnulados = [];
        foreach ($cotizaciones as $item) if ($this->isAnulado($item['ESTADO'] ?? '')) $documentosAnulados[] = ['tipo' => 'Cotización', 'numero' => $item['NRO_EXPE'], 'estado' => $item['ESTADO']];
        foreach ($certificaciones as $item) if ($this->isAnulado($item['ESTADO'] ?? '')) $documentosAnulados[] = ['tipo' => 'Certificación', 'numero' => $item['CODCER'], 'estado' => $item['ESTADO']];

        $ordenesVisibles = [];
        foreach ($ordenes as $orden) {
            if ($this->isAnulado($orden['ESTADO'] ?? '')) {
                $documentosAnulados[] = ['tipo' => $orden['TIPO'], 'numero' => $orden['NUMERO'], 'estado' => $orden['ESTADO']];
                continue;
            }
            $comprobantesVisibles = [];
            foreach ($orden['COMPROBANTES'] as $comprobanteOrden) {
                if ($this->isAnulado($comprobanteOrden['ESTADO'] ?? '')) {
                    $documentosAnulados[] = ['tipo' => 'Factura o boleta', 'numero' => $comprobanteOrden['DOCSER'] . '-' . $comprobanteOrden['DOCNRO'], 'estado' => $comprobanteOrden['ESTADO']];
                    continue;
                }
                $pagosVisibles = [];
                foreach ($comprobanteOrden['PAGOS'] as $pagoOrden) {
                    if ($this->isAnulado($pagoOrden['ESTADO'] ?? '')) {
                        $documentosAnulados[] = ['tipo' => 'Pago', 'numero' => trim($pagoOrden['CMPBTE'] . ' ' . $pagoOrden['DOC1']), 'estado' => $pagoOrden['ESTADO']];
                        continue;
                    }
                    $pagosVisibles[] = $pagoOrden;
                }
                $comprobanteOrden['PAGOS'] = $pagosVisibles;
                $comprobantesVisibles[] = $comprobanteOrden;
            }
            $orden['COMPROBANTES'] = $comprobantesVisibles;
            $ordenesVisibles[] = $orden;
        }
        $ordenes = $ordenesVisibles;
        $ordenesPorClave = [];
        foreach ($ordenes as $orden) $ordenesPorClave[$orden['TIPO'] . '|' . $orden['NUMERO']] = $orden;

        $cotizacionesVisibles = [];
        foreach ($cotizaciones as $cotizacion) {
            if ($this->isAnulado($cotizacion['ESTADO'] ?? '')) continue;
            $cotizacion['CERTIFICACIONES'] = array_values(array_filter($cotizacion['CERTIFICACIONES'], fn(array $item): bool => !$this->isAnulado($item['ESTADO'] ?? '')));
            $ordenesCotizacion = [];
            foreach ($cotizacion['ORDENES'] as $orden) {
                $clave = $orden['TIPO'] . '|' . $orden['NUMERO'];
                if (isset($ordenesPorClave[$clave])) $ordenesCotizacion[] = $ordenesPorClave[$clave];
            }
            $cotizacion['ORDENES'] = $ordenesCotizacion;
            $cotizacionesVisibles[] = $cotizacion;
        }
        $cotizaciones = $cotizacionesVisibles;
        $certificaciones = array_values(array_filter($certificaciones, fn(array $item): bool => !$this->isAnulado($item['ESTADO'] ?? '')));

        $comprobante = $ordenes[0]['COMPROBANTES'][0] ?? null;
        $contable = $ordenes[0]['DEVENGADOS'][0] ?? null;
        $pago = $comprobante['PAGOS'][0] ?? null;
        $header['TIPO_LABEL'] = $this->labelTipo($header['TIPO_PED']);
        $header['PRIORIDAD_LABEL'] = $this->labelPrioridad($header['PRIORIDAD']);
        $header['ESTADO_LABEL'] = $this->labelEstado($header['EST_PEDIDO']);
        $unidad = $header['CODUNIORG'] !== '' ? $this->reader->findExact(AVALON_PLANIF_DATA . '/unidad.dbf', ['COD_UNIDAD','DES_UNIDAD'], 'COD_UNIDAD', $header['CODUNIORG']) : [];
        $header['UNIDAD_LABEL'] = $unidad ? trim($unidad[0]['COD_UNIDAD'] . ' · ' . $unidad[0]['DES_UNIDAD']) : ($header['CODUNIORG'] ?: 'Sin código en el origen');
        $result = ['pedido' => $header, 'detalle' => $details, 'retiros_almacen' => $retirosAlmacen, 'cotizaciones' => $cotizaciones, 'certificaciones' => array_values($certificaciones), 'ordenes' => $ordenes, 'documentos_anulados' => $documentosAnulados, 'documento_buscado' => $documentoBuscado, 'resumen_documental' => $resumenDocumental, 'avance' => $avance, 'orden_compra' => $ordenCompraRows[0] ?? null, 'orden_servicio' => $ordenServicioRows[0] ?? null, 'comprobante' => $comprobante, 'contable' => $contable, 'pago' => $pago];
        $this->saveCache($cacheKey, $result);
        return $result;
    }
    private function pedidoDesdeOrden(string $numeroOrden, string $tipo): ?string
    {
        $compras = $tipo === 'orden_servicio' ? [] : $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40015.DBF', ['NRO_ORDCMP','NRO_EXPE','REFER'], 'NRO_ORDCMP', $numeroOrden);
        $servicios = $tipo === 'orden_compra' ? [] : $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40017.DBF', ['NRO_SERVIC','NRO_EXPE','REFER'], 'NRO_SERVIC', $numeroOrden);
        foreach (array_merge($compras, $servicios) as $orden) {
            $pedido = $this->pedidoEnTexto($orden['REFER'] ?? '');
            if ($pedido !== null) return $pedido;
            if (($orden['NRO_EXPE'] ?? '') === '') continue;
            $cotizaciones = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40025.DBF', ['NRO_EXPE','REFERENCIA'], 'NRO_EXPE', $orden['NRO_EXPE']);
            foreach ($cotizaciones as $cotizacion) {
                $pedido = $this->pedidoEnTexto($cotizacion['REFERENCIA']);
                if ($pedido !== null) return $pedido;
            }
        }
        return null;
    }
    /** Resuelve documentos ya sincronizados sin recorrer archivos DBF de Avalon. */
    private function documentoDesdeCopiaLocal(string $numero, string $tipo): ?array
    {
        $tipos = [
            'cotizacion' => ['cotizacion'],
            'certificacion' => ['certificacion'],
            'orden_compra' => ['orden_compra'],
            'orden_servicio' => ['orden_servicio'],
            'comprobante' => ['comprobante', 'comprobante_interno'],
            'devengado' => ['devengado'],
            'pago' => ['pago'],
            'todo' => ['cotizacion', 'certificacion', 'orden_compra', 'orden_servicio', 'comprobante', 'comprobante_interno', 'devengado', 'pago'],
        ][$tipo] ?? [];
        if (!$tipos) return null;
        try {
            $marks = implode(',', array_fill(0, count($tipos), '?'));
            $sql = 'SELECT nro_pedido,tipo,numero,estado,fecha FROM seguimiento_documentos WHERE (numero = ? OR (tipo = \'pago\' AND numero LIKE ?)) AND tipo IN (' . $marks . ') ORDER BY sincronizado_en DESC LIMIT 1';
            $stmt = db()->prepare($sql);
            $stmt->execute(array_merge([$numero, '% ' . (ltrim($numero, '0') ?: '0')], $tipos));
            $documento = $stmt->fetch();
            return is_array($documento) ? $documento : null;
        } catch (PDOException $exception) {
            return null;
        }
    }
    private function puedeVerPedido(array $pedido, array $acceso): bool
    {
        if (($acceso['rol'] ?? '') === 'ADMIN' || ($acceso['alcance'] ?? '') === 'GLOBAL') return true;
        $unidad = trim((string)($acceso['unidad_organica'] ?? ''));
        if ($unidad === '' || $unidad === '0000') return false;
        return trim((string)$pedido['CODUNIORG']) === $unidad;
    }
    private function pedidoEnTexto(string $texto): ?string
    {
        if (preg_match('/PEDIDO\\s*(?:NRO|N°|Nº)?\\s*:?\\s*(\\d{7,})/iu', $texto, $coincidencia)) return $coincidencia[1];
        if (preg_match('/\\b(\\d{7,})\\b/', $texto, $coincidencia)) return $coincidencia[1];
        return null;
    }
    private function esCertificacionDeExpediente(string $proceso, string $expediente): bool
    {
        $patron = '/EXPEDIENTE\\s*(?:NRO|N°|Nº)?\\s*:?\\s*' . preg_quote($expediente, '/') . '(?!\\d)/iu';
        return preg_match($patron, $proceso) === 1;
    }
    private function mismoComprobante(string $serieDocumento, string $numeroDocumento, string $serieDevengado, string $numeroDevengado): bool
    {
        return mb_strtoupper(trim($serieDocumento)) === mb_strtoupper(trim($serieDevengado))
            && ltrim(trim($numeroDocumento), '0') === ltrim(trim($numeroDevengado), '0');
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
    private function cacheFile(string $term): string { return APP_ROOT . '/storage/cache/seguimiento-v25-' . sha1($term) . '.json'; }
}
