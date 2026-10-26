<?php
declare(strict_types=1);

final class SeguimientoModel
{
    private DbfReader $reader;
    public function __construct() { $this->reader = new DbfReader(); }
    public function buscar(string $term, string $tipo = 'todo', array $acceso = [], ?string $pedidoSeleccionado = null): ?array
    {
        $term = mb_strtoupper(trim($term)); if ($term === '') return null;
        $tipo = in_array($tipo, ['todo', 'pedido', 'cotizacion', 'certificacion', 'orden_compra', 'orden_servicio', 'comprobante', 'devengado', 'pago'], true) ? $tipo : 'todo';
        $cacheKey = $tipo . '-' . $term . '-' . ($pedidoSeleccionado ?? '');
        $cached = $this->cache($cacheKey);
        if ($cached !== null && $this->puedeVerPedido($cached['pedido'], $acceso)) return $cached;
        $documentoBuscado = null;
        $headers = in_array($tipo, ['todo', 'pedido'], true) ? $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40014.DBF', ['NRO_PEDIDO','CODUNIORG','USR_COD','USRCODIGO','EST_PEDIDO','FEC_EMISIO','PRIORIDAD','TIPO_PED','COMENTARIO','TOTCANTSAL','CERTIFICA','DOCCERTI'], 'NRO_PEDIDO', $term) : [];
        if (!$headers && $tipo !== 'pedido') {
            $documentoBuscado = $this->documentoDesdeCopiaLocal($term, $tipo, $pedidoSeleccionado);
            $pedidoResuelto = ($documentoBuscado['nro_pedido'] ?? null) ?? $this->pedidoDesdeOrden($term, $tipo);
            if ($pedidoResuelto !== null) $headers = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40014.DBF', ['NRO_PEDIDO','CODUNIORG','USR_COD','USRCODIGO','EST_PEDIDO','FEC_EMISIO','PRIORIDAD','TIPO_PED','COMENTARIO','TOTCANTSAL','CERTIFICA','DOCCERTI'], 'NRO_PEDIDO', $pedidoResuelto);
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
            $centro = $detail['CENCOS'] !== '' ? $this->reader->findExact(AVALON_CONTAB_DATA . '/cct0004.DBF', ['CCTA','DESC'], 'CCTA', $detail['CENCOS']) : [];
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
            // La cabecera del expediente define su estado; las filas por proveedor
            // pueden conservar P incluso cuando el expediente ya fue atendido.
            $lectorExpedientes = new DbfReader(false);
            foreach ($cotizaciones as &$cotizacionEstado) {
                $filasProveedor = $lectorExpedientes->findExact(AVALON_LOGISTICA_DATA . '/sum40025.DBF', ['NRO_EXPE','ZONA','LOCA','REFERENCIA'], 'NRO_EXPE', $cotizacionEstado['NRO_EXPE']);
                $claves = [];
                foreach ($filasProveedor as $proveedor) {
                    if ($proveedor['REFERENCIA'] === $cotizacionEstado['REFERENCIA']) $claves[$proveedor['ZONA'].'|'.$proveedor['LOCA']] = $proveedor;
                }
                $cabecerasExpediente = $lectorExpedientes->findExact(AVALON_LOGISTICA_DATA . '/sum40022.DBF', ['NRO_EXPE','ZONA','LOCA','ESTADO'], 'NRO_EXPE', $cotizacionEstado['NRO_EXPE']);
                $cotizacionEstado['ESTADO'] = count($claves) === 1 ? EstadoDocumentoService::estadoExpediente(reset($claves), $cabecerasExpediente) : '';
            }
            unset($cotizacionEstado);
            // Un expediente puede tener varias líneas internas en Avalon. Para el
            // seguimiento documental representa una sola cotización/expediente.
            $cotizacionesPorExpediente = [];
            foreach ($cotizaciones as $cotizacion) {
                if ($cotizacion['NRO_EXPE'] !== '') $cotizacionesPorExpediente[$cotizacion['NRO_EXPE']] = $cotizacion;
            }
            $cotizaciones = array_values($cotizacionesPorExpediente);
            foreach ($cotizaciones as &$cotizacionMonto) {
                $ofertas = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40026.DBF', ['NRO_EXPE','VALOFER'], 'NRO_EXPE', $cotizacionMonto['NRO_EXPE']);
                $montosOferta = [];
                foreach ($ofertas as $oferta) if (is_numeric($oferta['VALOFER'])) $montosOferta[(string)$this->centavos($oferta['VALOFER'])] = $this->centavos($oferta['VALOFER']);
                $cotizacionMonto['MONTO_CENTS'] = count($montosOferta) === 1 ? reset($montosOferta) : null;
                $cotizacionMonto['MONEDA'] = $cotizacionMonto['MONTO_CENTS'] !== null ? '01' : '';
            }
            unset($cotizacionMonto);
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
        foreach ($certificaciones as &$certificacionMonto) {
            $detalleCertificacion = $this->reader->findExact(AVALON_PLANIF_DATA . '/detcertifica.DBF', ['CODCER','SOLICITADO'], 'CODCER', $certificacionMonto['CODCER']);
            $certificacionMonto['MONTO_CENTS'] = $this->sumarMontosCentavos($detalleCertificacion, 'SOLICITADO');
            $certificacionMonto['MONEDA'] = '01';
        }
        unset($certificacionMonto);
        $certificacionesPorCodigo = $certificaciones;
        foreach ($cotizaciones as &$cotizacionConMontos) {
            foreach ($cotizacionConMontos['CERTIFICACIONES'] as &$certificacionConMonto) {
                if (isset($certificacionesPorCodigo[$certificacionConMonto['CODCER']])) {
                    $certificacionConMonto['MONTO_CENTS'] = $certificacionesPorCodigo[$certificacionConMonto['CODCER']]['MONTO_CENTS'];
                    $certificacionConMonto['MONEDA'] = $certificacionesPorCodigo[$certificacionConMonto['CODCER']]['MONEDA'];
                }
            }
            unset($certificacionConMonto);
        }
        unset($cotizacionConMontos);
        if (!$cotizaciones) {
            $comprasDirectas = $this->reader->findContains(AVALON_LOGISTICA_DATA . '/sum40015.DBF', ['NRO_ORDCMP','NRO_EXPE','FEC_EMISIO','EST_ORDCMP','ESTFACTU','CERTIFICA','REFER'], 'REFER', $pedido);
            $serviciosDirectos = $this->reader->findContains(AVALON_LOGISTICA_DATA . '/sum40017.DBF', ['NRO_SERVIC','NRO_EXPE','FEC_EMISIO','ESTADO','ESTFACTU','CERTIFICA','REFER'], 'REFER', $pedido);
            foreach ($comprasDirectas as $orden) $ordenes[] = ['TIPO' => 'Orden de compra', 'NUMERO' => $orden['NRO_ORDCMP'], 'FECHA' => $orden['FEC_EMISIO'], 'ESTADO' => $orden['EST_ORDCMP'], 'CAMPO_COMPROBANTE' => 'NRO_ORDCMP', 'PREFIJO' => 'OC', 'EXPEDIENTE' => $orden['NRO_EXPE']];
            foreach ($serviciosDirectos as $orden) $ordenes[] = ['TIPO' => 'Orden de servicio', 'NUMERO' => $orden['NRO_SERVIC'], 'FECHA' => $orden['FEC_EMISIO'], 'ESTADO' => $orden['ESTADO'], 'CAMPO_COMPROBANTE' => 'NRO_SERVIC', 'PREFIJO' => 'OS', 'EXPEDIENTE' => $orden['NRO_EXPE']];
        }
        $ordenesUnicas = [];
        foreach ($ordenes as $orden) $ordenesUnicas[$orden['PREFIJO'] . '|' . $orden['NUMERO']] = $orden;
        $ordenes = array_values($ordenesUnicas);
        $resumenDocumental = ['comprobantes' => 0, 'devengados' => 0, 'pagos' => 0];
        foreach ($ordenes as &$orden) {
            $campoTotal = $orden['PREFIJO'] === 'OC' ? 'TOTALOC' : 'TOTALOS';
            $archivoOrden = $orden['PREFIJO'] === 'OC' ? '/sum40015.DBF' : '/sum40017.DBF';
            $cabeceraFinanciera = $this->reader->findExact(AVALON_LOGISTICA_DATA . $archivoOrden, [$orden['CAMPO_COMPROBANTE'], $campoTotal, 'VAL_TOTORD', 'MONEDA', 'ESTFACTU'], $orden['CAMPO_COMPROBANTE'], $orden['NUMERO']);
            $orden['COMPROBANTES'] = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40030.DBF', ['COMPROB','NRO_SERVIC','NRO_ORDCMP','DOCSER','DOCNRO','FECCOMP','ESTADO','PAGADO','FECPAGO','NOMPROV','PREVENT','MONEDA'], $orden['CAMPO_COMPROBANTE'], $orden['NUMERO']);
            $orden['FINANCIERO'] = ResumenFinancieroService::calcular($cabeceraFinanciera[0] ?? [], $orden['COMPROBANTES'], $campoTotal);
            $orden['MONEDA'] = $orden['FINANCIERO']['moneda'];
            $orden['ESTFACTU'] = $cabeceraFinanciera[0]['ESTFACTU'] ?? '';
            $orden['MONTO_CENTS'] = $orden['FINANCIERO']['total'];
            $orden['MONTO_SIN_IGV_CENTS'] = $this->centavos($cabeceraFinanciera[0]['VAL_TOTORD'] ?? null);
            $orden['DEVENGADOS'] = $this->reader->findContains(AVALON_CONTAB_DATA . '/FCON0024.DBF', ['FCONTABLE','DOC1','FECT','OCOS','SERIE','NUMERO','MON_SOL'], 'OCOS', $orden['PREFIJO'] . $orden['NUMERO']);
            foreach ($orden['DEVENGADOS'] as &$devengadoMonto) {
                $devengadoMonto['MONTO_CENTS'] = $this->centavos($devengadoMonto['MON_SOL']);
                $devengadoMonto['MONEDA'] = '01';
            }
            unset($devengadoMonto);
            $orden['DEVENGADOS_SIN_COMPROBANTE'] = $orden['DEVENGADOS'];
            $resumenDocumental['comprobantes'] += count($orden['COMPROBANTES']);
            $resumenDocumental['devengados'] += count($orden['DEVENGADOS']);
            foreach ($orden['COMPROBANTES'] as &$comprobanteOrden) {
                $comprobanteOrden['MONTO_CENTS'] = $this->centavos($comprobanteOrden['PREVENT']);
                $comprobanteOrden['PAGOS'] = $this->reader->findExact(AVALON_FINANZAS_DATA . '/rfin0019.DBF', ['FCONTABLE','CMPBTE','DOC1','COMPROB','IMPORTE','ESTADO'], 'COMPROB', $comprobanteOrden['COMPROB']);
                foreach ($comprobanteOrden['PAGOS'] as &$pagoMonto) {
                    $pagoMonto['MONTO_CENTS'] = $this->centavos($pagoMonto['IMPORTE']);
                    $pagoMonto['MONEDA'] = $comprobanteOrden['MONEDA'];
                }
                unset($pagoMonto);
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

        /* El historial se muestra completo. Los anulados ya fueron excluidos
           de los cálculos de avance, pero no del detalle consultable. */
        foreach ($ordenes as $orden) {
            if ($this->isAnulado($orden['ESTADO'] ?? '')) $documentosAnulados[] = ['tipo' => $orden['TIPO'], 'numero' => $orden['NUMERO'], 'estado' => $orden['ESTADO']];
            foreach ($orden['COMPROBANTES'] as $comprobanteOrden) {
                if ($this->isAnulado($comprobanteOrden['ESTADO'] ?? '')) $documentosAnulados[] = ['tipo' => 'Factura o boleta', 'numero' => $comprobanteOrden['DOCSER'] . '-' . $comprobanteOrden['DOCNRO'], 'estado' => $comprobanteOrden['ESTADO']];
                foreach ($comprobanteOrden['PAGOS'] as $pagoOrden) if ($this->isAnulado($pagoOrden['ESTADO'] ?? '')) $documentosAnulados[] = ['tipo' => 'Pago', 'numero' => trim($pagoOrden['CMPBTE'] . ' ' . $pagoOrden['DOC1']), 'estado' => $pagoOrden['ESTADO']];
            }
        }
        /*
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
        */

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
    public function detalleDocumento(string $pedido, string $tipo, string $numero, array $acceso, bool $mostrarMontos = true): array
    {
        $tipos=['pedido','cotizacion','certificacion','orden_compra','orden_servicio','comprobante','devengado','pago'];
        if ($pedido==='' || $numero==='' || !in_array($tipo,$tipos,true)) throw new RuntimeException('El documento solicitado no es válido.');
        $nota=db()->prepare('SELECT nro_pedido,unidad_organica,unidad_nombre,usuario_codigo FROM seguimiento_notas WHERE nro_pedido=? LIMIT 1'); $nota->execute([$pedido]); $nota=$nota->fetch();
        $scope=$acceso['alcance']??'UNIDAD';$unidad=trim((string)($acceso['unidad_organica']??''));$codigo=trim((string)($acceso['avalon_codigo']??''));
        $permitido=$nota&&($scope==='GLOBAL'||($scope==='PROPIO'
            ? $codigo!==''&&trim((string)$nota['usuario_codigo'])!==''&&ltrim($codigo,'0')===ltrim(trim((string)$nota['usuario_codigo']),'0')
            : $unidad!==''&&$unidad!=='0000'&&$unidad===trim((string)$nota['unidad_organica'])));
        if (!$permitido) throw new RuntimeException('No tiene acceso a este documento.');
        if(in_array($tipo,['orden_compra','orden_servicio'],true)){
            $vinculo=db()->prepare('SELECT 1 FROM seguimiento_documentos WHERE nro_pedido=? AND tipo=? AND numero=? LIMIT 1');$vinculo->execute([$pedido,$tipo,$numero]);
            if(!$vinculo->fetchColumn())throw new RuntimeException('La orden no está vinculada al pedido solicitado.');
        }
        $rows=[]; $meta=[]; $titulo='Detalle del documento';
        if($tipo==='pedido'){$titulo='Pedido '.$numero; $rows=$this->reader->findExact(AVALON_LOGISTICA_DATA.'/sum40007.DBF',['NRO_PEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','CAN_ORDENA','CENCOS','COD_PROY','CTA_CON'],'NRO_PEDIDO',$pedido);}
        elseif($tipo==='cotizacion'){$titulo='Cotización / expediente '.$numero; $rows=$this->reader->findExact(AVALON_LOGISTICA_DATA.'/sum40028.DBF',['NRO_EXPE','NRO_PEDIDO','SECPEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','SEC_SERV','CAN_PED','CAN_PRO','PRE_PRO','PRO_TOT','CENCOS','ADJUDIC'],'NRO_EXPE',$numero); $meta=$this->reader->findExact(AVALON_LOGISTICA_DATA.'/sum40026.DBF',['NRO_EXPE','CODPROV','FECOFER','VALOFER','PLAZENTR'],'NRO_EXPE',$numero);}
        elseif($tipo==='certificacion'){$titulo='Certificación '.$numero; $rows=$this->reader->findExact(AVALON_PLANIF_DATA.'/detcertifica.DBF',['CODCER','SEC','CUENTA','SOLICITADO','DISPONIBLE','SALDO','RECURSO'],'CODCER',$numero);}
        elseif($tipo==='orden_compra' || $tipo==='orden_servicio') {
            $esCompra = $tipo === 'orden_compra';
            $campoNumero = $esCompra ? 'NRO_ORDCMP' : 'NRO_SERVIC';
            $archivoCabecera = $esCompra ? '/sum40015.DBF' : '/sum40017.DBF';
            $archivoLineas = $esCompra ? '/sum40016.DBF' : '/sum40018.DBF';
            $campoTotal = $esCompra ? 'TOTALOC' : 'TOTALOS';
            $titulo = ($esCompra ? 'Orden de compra ' : 'Orden de servicio ') . $numero;
            $camposCabecera = $esCompra
                ? ['NRO_ORDCMP','NRO_EXPE','FEC_EMISIO','ZONA','LOCA','CODPROV','FOR_PAGO','LUG_ENTREG','VAL_TOTORD','MONIMPTO','MONOTROS','MONEDA','OBSERVAS','TOTALOC','REFER','CERTIFICA','REFCERTIFI','EST_ORDCMP']
                : ['NRO_SERVIC','NRO_EXPE','FEC_EMISIO','ZONA','LOCA','CODPROV','FOR_PAGO','VAL_TOTORD','MONIMPTO','MONEDA','OBSERVAS','TOTALOS','REFER','CERTIFICA','REFCERTIFI','ESTADO'];
            $cabeceras = $this->reader->findExact(AVALON_LOGISTICA_DATA . $archivoCabecera, $camposCabecera, $campoNumero, $numero);
            $cabecera = $cabeceras[0] ?? [];
            $camposLineas = $esCompra
                ? ['NRO_ORDCMP','NRO_PEDIDO','SECPEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','SEC_ITEMOC','CAN_ORDENA','CAN_RECIBI','PRE_UNITAR','PRE_TOT','MONIMPTO','CTA_CON','COD_PROY','CODUNIORG','ACTIVIDAD','CODACTIVID','DETLIN','ESTADO']
                : ['NRO_SERVIC','NRO_PEDIDO','SECPEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_SERV','SECITEMOS','CAN_ORDENA','PRE_UNITAR','PRE_TOT','MONIMPTO','CTA_CON','CODPROY','CCOSTO','ACTIVIDAD','CODACTIVID','DETLIN','ESTADO'];
            $lineasOrigen = $this->reader->findExact(AVALON_LOGISTICA_DATA . $archivoLineas, $camposLineas, $campoNumero, $numero);
            $centrosPorLinea = [];
            foreach ($lineasOrigen as $lineaOrden) {
                $pedidoLineas = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40007.DBF', ['NRO_PEDIDO','SECPEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','CENCOS'], 'NRO_PEDIDO', $lineaOrden['NRO_PEDIDO']);
                foreach ($pedidoLineas as $lineaPedido) {
                    if ($lineaPedido['SECPEDIDO'] === $lineaOrden['SECPEDIDO'] && $lineaPedido['TIPOFAMI'] === $lineaOrden['TIPOFAMI'] && $lineaPedido['GRUPO'] === $lineaOrden['GRUPO'] && $lineaPedido['SUBGRUPO'] === $lineaOrden['SUBGRUPO']) {
                        $centrosPorLinea[$lineaOrden['NRO_PEDIDO'] . '|' . $lineaOrden['SECPEDIDO']] = $lineaPedido['CENCOS'];
                        break;
                    }
                }
            }
            $proveedores = [];
            foreach ($this->reader->findExact(AVALON_LOGISTICA_DATA . '/sum40030.DBF', ['NRO_ORDCMP','NRO_SERVIC','NOMPROV','RUCPROV','COMPROB'], $campoNumero, $numero) as $comprobante) {
                if (trim($comprobante['NOMPROV']) !== '') $proveedores[$comprobante['NOMPROV']] = ['nombre'=>$comprobante['NOMPROV'],'ruc'=>$comprobante['RUCPROV']];
            }
            $proveedor = $proveedores ? reset($proveedores) : ['nombre'=>'','ruc'=>$cabecera['CODPROV'] ?? ''];
            $meta = [[
                'Fecha de emisión'=>$cabecera['FEC_EMISIO'] ?? '',
                'Proveedor'=>$proveedor['nombre'],
                'RUC'=>$proveedor['ruc'],
                'Código de proveedor'=>$cabecera['CODPROV'] ?? '',
                'Certificación'=>$cabecera['REFCERTIFI'] ?? '',
                'Expediente de cotización'=>$cabecera['NRO_EXPE'] ?? '',
                'Referencia'=>$cabecera['REFER'] ?? '',
                'Lugar de entrega'=>$cabecera['LUG_ENTREG'] ?? '',
                'Forma de pago'=>['CH'=>'CHEQUES','EF'=>'EFECTIVO','TR'=>'TRANSFERENCIA'][$cabecera['FOR_PAGO'] ?? ''] ?? ($cabecera['FOR_PAGO'] ?? ''),
                'Subtotal'=>$cabecera['VAL_TOTORD'] ?? '',
                'Impuesto'=>$cabecera['MONIMPTO'] ?? '',
                'Total'=>$cabecera[$campoTotal] ?? '',
                'Moneda Avalon'=>$cabecera['MONEDA'] ?? '',
                'Estado'=>$cabecera[$esCompra ? 'EST_ORDCMP' : 'ESTADO'] ?? '',
            ]];
            $rows = [];
            foreach ($lineasOrigen as $linea) {
                $codigoRecurso = $esCompra ? 'SEC_BIEN' : 'SEC_SERV';
                $codigo = $linea[$codigoRecurso];
                $archivoRecurso = $esCompra ? '/sum40004.DBF' : '/sum40003.DBF';
                $camposRecurso = $esCompra ? ['TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','DESC_MAT','UNIMED'] : ['TIPOFAMI','GRUPO','SUBGRUPO','SEC_SERV','DESC_SERV','UNIMED'];
                $recursos = $this->reader->findExact(AVALON_LOGISTICA_DATA . $archivoRecurso, $camposRecurso, $codigoRecurso, $codigo);
                $nombreRecurso = 'Recurso sin descripción';
                $unidad = '';
                foreach ($recursos as $recurso) {
                    if ($recurso['TIPOFAMI'] === $linea['TIPOFAMI'] && $recurso['GRUPO'] === $linea['GRUPO'] && $recurso['SUBGRUPO'] === $linea['SUBGRUPO']) {
                        $nombreRecurso = $recurso[$esCompra ? 'DESC_MAT' : 'DESC_SERV'];
                        $unidad = $recurso['UNIMED'];
                        break;
                    }
                }
                $proyecto = trim((string)($esCompra ? ($linea['COD_PROY'] ?? '') : ($linea['CODPROY'] ?? '')));
                $cuenta = trim((string)($linea['CTA_CON'] ?? ''));
                $zona = str_pad(trim((string)($cabecera['ZONA'] ?? '')), 2, '0', STR_PAD_LEFT);
                $local = str_pad(trim((string)($cabecera['LOCA'] ?? '')), 2, '0', STR_PAD_LEFT);
                $actividad = $proyecto !== '' ? $zona . '.' . $local . '.' . $proyecto : '';
                $actividadCuenta = trim($actividad . ($actividad !== '' && $cuenta !== '' ? ' - ' : '') . $cuenta);
                $centroCodigo = trim((string)($esCompra ? ($centrosPorLinea[$linea['NRO_PEDIDO'] . '|' . $linea['SECPEDIDO']] ?? '') : ($linea['CCOSTO'] ?? '')));
                $centroFilas = $centroCodigo !== '' ? $this->reader->findExact(AVALON_CONTAB_DATA . '/cct0004.DBF', ['CCTA','DESC'], 'CCTA', $centroCodigo) : [];
                $centroCosto = $centroFilas ? trim($centroFilas[0]['DESC']) : $centroCodigo;
                $rows[] = [
                    'pedido'=>$linea['NRO_PEDIDO'], 'item'=>$esCompra ? $linea['SEC_ITEMOC'] : $linea['SECITEMOS'],
                    'codigo'=>$codigo, 'denominacion'=>$nombreRecurso, 'unidad'=>$unidad,
                    'actividad_cuenta'=>$actividadCuenta, 'centro_costo'=>$centroCosto,
                    'cantidad'=>$linea['CAN_ORDENA'], 'precio_unitario'=>$linea['PRE_UNITAR'],
                    'precio_total'=>$linea['PRE_TOT'], 'recibido'=>$esCompra ? $linea['CAN_RECIBI'] : '',
                ];
            }
            $condiciones = [];
            foreach ($lineasOrigen as $linea) if (trim($linea['DETLIN']) !== '') $condiciones[trim($linea['DETLIN'])] = trim($linea['DETLIN']);
            $detalleOrden = ['cabecera'=>$cabecera,'moneda'=>$cabecera['MONEDA'] ?? '', 'condiciones'=>array_values($condiciones)];
        }
        elseif($tipo==='comprobante'){$titulo='Factura o boleta '.$numero; foreach($this->reader->rows(AVALON_LOGISTICA_DATA.'/sum40030.DBF',['COMPROB','DOCSER','DOCNRO','NOMPROV','FECCOMP','ESTADO']) as $h) if(trim($h['DOCSER'].'-'. $h['DOCNRO'])===$numero){$meta[]=$h;$rows=$this->reader->findExact(AVALON_LOGISTICA_DATA.'/sum40031.DBF',['COMPROB','NRO_SERVIC','NRO_ORDCMP','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','SEC_SERV','VALOR','CCOSTO','ESTADO'],'COMPROB',$h['COMPROB']); break;}}
        elseif($tipo==='devengado'){$titulo='Devengado '.$numero; $rows=$this->reader->findExact(AVALON_CONTAB_DATA . '/FCON0024.DBF',['DOC1','FCONTABLE','FECT','GLOS','NOMB','CTAB','MON_SOL','SERIE','NUMERO','OCOS'],'DOC1',$numero);}
        else {$titulo='Pago '.$numero; foreach($this->reader->rows(AVALON_FINANZAS_DATA . '/rfin0019.DBF',['FCONTABLE','CMPBTE','DOC1','COMPROB','DOCSER','DOCNRO','IMPORTE','ESTADO']) as $r) if(trim($r['CMPBTE'].' '.($r['DOC1']?:''))===$numero){$rows[]=$r;}}
        if (!$mostrarMontos) {
            $camposMonetarios = array_fill_keys(['PRE_PRO','PRO_TOT','VALOFER','SOLICITADO','DISPONIBLE','SALDO','VALOR','MON_SOL','IMPORTE','Subtotal','Impuesto','Total','precio_unitario','precio_total'], true);
            foreach ($meta as &$datos) $datos = array_diff_key($datos, $camposMonetarios);
            unset($datos);
            foreach ($rows as &$fila) $fila = array_diff_key($fila, $camposMonetarios);
            unset($fila);
            if (isset($detalleOrden)) {
                $detalleOrden['cabecera'] = array_diff_key($detalleOrden['cabecera'], array_fill_keys(['VAL_TOTORD','MONIMPTO','MONOTROS','TOTALOC','TOTALOS'], true));
            }
        }
        return compact('pedido','nota','tipo','numero','titulo','meta','rows') + (isset($detalleOrden) ? ['orden'=>$detalleOrden] : []);
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
    public function buscarCoincidencias(string $numero, string $tipo, array $acceso): array
    {
        $tipos = $this->tiposDocumento($tipo);
        if (!$tipos) return [];
        $marks = implode(',', array_fill(0, count($tipos), '?'));
        $sql = 'SELECT d.nro_pedido,d.tipo,d.numero,d.estado,d.fecha,d.proveedor,d.orden_tipo,d.orden_numero,n.unidad_organica,n.unidad_nombre,n.usuario_codigo,n.usuario_nombre,n.estado_logistica FROM seguimiento_documentos d INNER JOIN seguimiento_notas n ON n.nro_pedido=d.nro_pedido WHERE (d.numero=? OR (d.tipo=\'pago\' AND d.numero LIKE ?)) AND d.tipo IN (' . $marks . ')';
        $params = array_merge([$numero, '% ' . (ltrim($numero, '0') ?: '0')], $tipos);
        if (($acceso['alcance'] ?? '') === 'PROPIO') {
            $codigo=trim((string)($acceso['avalon_codigo']??''));if($codigo==='')return [];
            $sql.=" AND TRIM(n.usuario_codigo)<>'' AND TRIM(LEADING '0' FROM TRIM(n.usuario_codigo))=?";$params[]=ltrim($codigo,'0');
        } elseif (($acceso['alcance'] ?? '') !== 'GLOBAL') {
            $unidad = trim((string)($acceso['unidad_organica'] ?? ''));
            if ($unidad === '' || $unidad === '0000') return [];
            $sql .= ' AND n.unidad_organica=?';
            $params[] = $unidad;
        }
        $sql .= ' ORDER BY (UPPER(TRIM(COALESCE(d.estado,\'\')))=\'A\') ASC,d.fecha DESC,d.nro_pedido DESC';
        $stmt=db()->prepare($sql); $stmt->execute($params);
        $salida=[];
        foreach ($stmt->fetchAll() as $fila) {
            $clave=$fila['nro_pedido'].'|'.$fila['tipo'].'|'.$fila['numero'].'|'.($fila['orden_tipo']??'').'|'.($fila['orden_numero']??'');
            $salida[$clave]=$fila;
        }
        return array_values($salida);
    }

    private function documentoDesdeCopiaLocal(string $numero, string $tipo, ?string $pedidoSeleccionado = null): ?array
    {
        $tipos = $this->tiposDocumento($tipo);
        if (!$tipos) return null;
        try {
            $marks = implode(',', array_fill(0, count($tipos), '?'));
            $sql = 'SELECT nro_pedido,tipo,numero,estado,fecha FROM seguimiento_documentos WHERE (numero = ? OR (tipo = \'pago\' AND numero LIKE ?)) AND tipo IN (' . $marks . ')';
            $params = array_merge([$numero, '% ' . (ltrim($numero, '0') ?: '0')], $tipos);
            if ($pedidoSeleccionado !== null && $pedidoSeleccionado !== '') { $sql .= ' AND nro_pedido=?'; $params[]=$pedidoSeleccionado; }
            $sql .= ' ORDER BY sincronizado_en DESC LIMIT 1';
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $documento = $stmt->fetch();
            return is_array($documento) ? $documento : null;
        } catch (PDOException $exception) {
            return null;
        }
    }

    private function tiposDocumento(string $tipo): array
    {
        return [
            'cotizacion' => ['cotizacion'],
            'certificacion' => ['certificacion'],
            'orden_compra' => ['orden_compra'],
            'orden_servicio' => ['orden_servicio'],
            'comprobante' => ['comprobante', 'comprobante_interno'],
            'devengado' => ['devengado'],
            'pago' => ['pago'],
            'todo' => ['cotizacion', 'certificacion', 'orden_compra', 'orden_servicio', 'comprobante', 'comprobante_interno', 'devengado', 'pago'],
        ][$tipo] ?? [];
    }
    private function puedeVerPedido(array $pedido, array $acceso): bool
    {
        if (($acceso['alcance'] ?? '') === 'GLOBAL') return true;
        if (($acceso['alcance'] ?? '') === 'PROPIO') {
            $codigo=trim((string)($acceso['avalon_codigo']??''));
            $propietario=trim((string)($pedido['USRCODIGO']??''))?:trim((string)($pedido['USR_COD']??''));
            return $codigo!==''&&$propietario!==''&&ltrim($codigo,'0')===ltrim($propietario,'0');
        }
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
    private function centavos($valor): ?int { return is_numeric($valor) ? (int)round((float)$valor * 100) : null; }
    private function sumarMontosCentavos(array $filas, string $campo): ?int
    {
        $total = 0; $encontro = false;
        foreach ($filas as $fila) {
            if (!is_numeric($fila[$campo] ?? null)) continue;
            $total += $this->centavos($fila[$campo]); $encontro = true;
        }
        return $encontro && $total !== 0 ? $total : null;
    }
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
    private function cacheFile(string $term): string { return APP_ROOT . '/storage/cache/seguimiento-v30-' . sha1($term) . '.json'; }
}
