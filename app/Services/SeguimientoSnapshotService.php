<?php

declare(strict_types=1);
/** Construye una copia local de solo consulta. Nunca escribe en C:\Avalon. */
final class SeguimientoSnapshotService
{
    private DbfReader $reader;
    public function __construct()
    {
        $this->reader = new DbfReader();
    }
    public function sincronizar(array $filtro = [], ?int $usuarioId = null): int
    {
        $this->instalar();
        $pdo = db();
        $pdo->exec("UPDATE seguimiento_sincronizaciones SET estado='ERROR',terminado_en=NOW(),mensaje='Sincronización interrumpida' WHERE estado='EJECUTANDO'");
        $sync = $pdo->prepare("INSERT INTO seguimiento_sincronizaciones(iniciado_en,estado) VALUES(NOW(),'EJECUTANDO')");
        $sync->execute();
        $syncId = (int)$pdo->lastInsertId();
        try {
            // Primero actualiza únicamente las tablas DBF cuyo tamaño o fecha
            // cambió. Desde este punto, el cálculo trabaja con MySQL.
            (new AvalonCacheService())->sincronizar();
            $unidades = [];
            foreach ($this->reader->rows(AVALON_PLANIF_DATA . '/unidad.dbf', ['COD_UNIDAD', 'DES_UNIDAD']) as $u) $unidades[$u['COD_UNIDAD']] = $u['DES_UNIDAD'];
            $notas = [];
            $expedientes = [];
            $ordenes = [];
            $comprobantes = [];
            $documentos = [];
            foreach ($this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40014.DBF', ['NRO_PEDIDO', 'CODUNIORG', 'USR_COD', 'USRCODIGO', 'USRNOMBRE', 'EST_PEDIDO', 'FEC_EMISIO', 'FEC_ULTACT', 'USRDATE', 'PRIORIDAD', 'TIPO_PED', 'COMENTARIO', 'DOCCERTI']) as $r) {
                $n = $r['NRO_PEDIDO'];
                if ($n === '') continue;
                if (!$this->incluye($r, $filtro)) continue;
                $nivel = $r['EST_PEDIDO'] === 'A' ? 0 : ($r['PRIORIDAD'] === '4' ? 2 : 1);
                $etapa = $r['EST_PEDIDO'] === 'A' ? 'Pedido anulado' : ($r['PRIORIDAD'] === '4' ? 'Pendiente de retiro de almacén' : 'Pedido');
                $notas[$n] = ['nro_pedido' => $n, 'unidad_organica' => $r['CODUNIORG'], 'unidad_nombre' => $unidades[$r['CODUNIORG']] ?? null, 'usuario_codigo' => $r['USRCODIGO'] ?: $r['USR_COD'], 'usuario_nombre' => $r['USRNOMBRE'], 'estado_logistica' => $r['EST_PEDIDO'], 'fecha_emision' => $this->fecha($r['FEC_EMISIO']), 'fecha_registro' => $this->fecha($r['FEC_ULTACT'] ?: $r['USRDATE']), 'tipo_pedido' => $r['TIPO_PED'], 'prioridad' => $r['PRIORIDAD'], 'sustentacion' => trim($r['COMENTARIO']), 'etapa' => $etapa, 'etapa_nivel' => $nivel, 'DOCCERTI' => $r['DOCCERTI']];
            }
            foreach ($this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40025.DBF', ['NRO_EXPE', 'REFERENCIA', 'ESTADO', 'FEC_COTIZA']) as $r) {
                $n = $this->pedido($r['REFERENCIA']);
                if ($n && isset($notas[$n])) {
                    $expedientes[$r['NRO_EXPE']] = $n;
                    $this->documento($documentos, $n, 'cotizacion', $r['NRO_EXPE'], $r['ESTADO'], $this->fecha($r['FEC_COTIZA']));
                    if (!$this->anulado($r['ESTADO'])) $this->etapa($notas, $n, 'Cotización', 2);
                }
            }
            $certificadosDirectos = [];
            foreach ($notas as $n => $nota) if (trim($nota['DOCCERTI'] ?? '') !== '') $certificadosDirectos[trim($nota['DOCCERTI'])] = (string)$n;
            foreach ($this->reader->rows(AVALON_PLANIF_DATA . '/cabcertifica.DBF', ['CODCER', 'PROCESO', 'ESTADO', 'FECHA']) as $r) {
                if (isset($certificadosDirectos[$r['CODCER']])) {
                    $n = $certificadosDirectos[$r['CODCER']];
                    $this->documento($documentos, $n, 'certificacion', $r['CODCER'], $r['ESTADO'], $this->fecha($r['FECHA']));
                    if (!$this->anulado($r['ESTADO'])) $this->etapa($notas, $n, 'Certificación', 3);
                }
                preg_match_all('/(?<!\\d)\\d{7}(?!\\d)/', $r['PROCESO'], $matches);
                $numerosProceso = array_unique($matches[0]);
                $mencionaPedido = preg_match('/\\bPEDIDO\\b/iu', $r['PROCESO']) === 1;
                $mencionaExpediente = preg_match('/\\bEXPEDIENTE\\b/iu', $r['PROCESO']) === 1;
                foreach ($numerosProceso as $numeroProceso) {
                    if ($mencionaPedido && isset($notas[$numeroProceso])) {
                        $this->documento($documentos, $numeroProceso, 'certificacion', $r['CODCER'], $r['ESTADO'], $this->fecha($r['FECHA']));
                        if (!$this->anulado($r['ESTADO'])) $this->etapa($notas, $numeroProceso, 'Certificación', 3);
                    }
                    if ($mencionaExpediente && isset($expedientes[$numeroProceso])) {
                        $pedidoExpediente = $expedientes[$numeroProceso];
                        $this->documento($documentos, $pedidoExpediente, 'certificacion', $r['CODCER'], $r['ESTADO'], $this->fecha($r['FECHA']));
                        if (!$this->anulado($r['ESTADO'])) $this->etapa($notas, $pedidoExpediente, 'Certificación', 3);
                    }
                }
            }
            foreach ($this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40015.DBF', ['NRO_ORDCMP', 'NRO_EXPE', 'REFER', 'EST_ORDCMP', 'FEC_EMISIO']) as $r) {
                $n = $expedientes[$r['NRO_EXPE']] ?? $this->pedido($r['REFER']);
                if ($n && isset($notas[$n])) {
                    $ordenes['OC' . $r['NRO_ORDCMP']] = $n;
                    $this->documento($documentos, $n, 'orden_compra', $r['NRO_ORDCMP'], $r['EST_ORDCMP'], $this->fecha($r['FEC_EMISIO']));
                    if (!$this->anulado($r['EST_ORDCMP'])) $this->etapa($notas, $n, 'Orden de compra', 4);
                }
            }
            foreach ($this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40017.DBF', ['NRO_SERVIC', 'NRO_EXPE', 'REFER', 'ESTADO', 'FEC_EMISIO']) as $r) {
                $n = $expedientes[$r['NRO_EXPE']] ?? $this->pedido($r['REFER']);
                if ($n && isset($notas[$n])) {
                    $ordenes['OS' . $r['NRO_SERVIC']] = $n;
                    $this->documento($documentos, $n, 'orden_servicio', $r['NRO_SERVIC'], $r['ESTADO'], $this->fecha($r['FEC_EMISIO']));
                    if (!$this->anulado($r['ESTADO'])) $this->etapa($notas, $n, 'Orden de servicio', 4);
                }
            }
            foreach ($this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40030.DBF', ['COMPROB', 'NRO_SERVIC', 'NRO_ORDCMP', 'DOCSER', 'DOCNRO', 'ESTADO', 'FECCOMP', 'NOMPROV']) as $r) {
                $n = $ordenes['OC' . $r['NRO_ORDCMP']] ?? $ordenes['OS' . $r['NRO_SERVIC']] ?? null;
                if ($n) {
                    $ordenTipo = $r['NRO_ORDCMP'] !== '' ? 'Orden de compra' : 'Orden de servicio';
                    $ordenNumero = $r['NRO_ORDCMP'] !== '' ? $r['NRO_ORDCMP'] : $r['NRO_SERVIC'];
                    $comprobantes[$r['COMPROB']] = ['pedido' => $n, 'proveedor' => $r['NOMPROV'], 'orden_tipo' => $ordenTipo, 'orden_numero' => $ordenNumero];
                    $numeroVisible = trim($r['DOCSER'] . ($r['DOCSER'] !== '' && $r['DOCNRO'] !== '' ? '-' : '') . $r['DOCNRO']);
                    $contexto = ['proveedor' => $r['NOMPROV'], 'orden_tipo' => $ordenTipo, 'orden_numero' => $ordenNumero];
                    $this->documento($documentos, $n, 'comprobante', $numeroVisible !== '' ? $numeroVisible : $r['COMPROB'], $r['ESTADO'], $this->fecha($r['FECCOMP']), $contexto);
                    $this->documento($documentos, $n, 'comprobante_interno', $r['COMPROB'], $r['ESTADO'], $this->fecha($r['FECCOMP']), $contexto);
                    if (!$this->anulado($r['ESTADO'])) $this->etapa($notas, $n, 'Factura o boleta', 5);
                }
            }
            foreach ($this->reader->rows('C:/Avalon/contab/data/FCON0024.DBF', ['OCOS', 'FCONTABLE', 'DOC1', 'FECT']) as $r) {
                $o = strtoupper(preg_replace('/\s+/', '', $r['OCOS']));
                if (isset($ordenes[$o])) {
                    $this->documento($documentos, $ordenes[$o], 'devengado', $r['DOC1'], '', $this->fecha($r['FECT']));
                    $this->etapa($notas, $ordenes[$o], 'Devengado', 6);
                }
            }
            foreach ($this->reader->rows('C:/Avalon/Finanzas/data/rfin0019.DBF', ['COMPROB', 'FCONTABLE', 'CMPBTE', 'DOC1', 'ESTADO']) as $r) {
                if (isset($comprobantes[$r['COMPROB']])) {
                    $contexto = $comprobantes[$r['COMPROB']];
                    $pago = trim($r['CMPBTE'] . ' ' . (ltrim($r['DOC1'], '0') ?: '0'));
                    $this->documento($documentos, $contexto['pedido'], 'pago', $pago, $r['ESTADO'], $this->fecha($r['FCONTABLE']), $contexto);
                    if (!$this->anulado($r['ESTADO'])) $this->etapa($notas, $contexto['pedido'], 'Pago', 7);
                }
            }
            foreach ($this->reader->rows(AVALON_LOGISTICA_DATA . '/sum40008.DBF', ['NRO_PEDIDO', 'ESTMOV']) as $r) {
                $n = $r['NRO_PEDIDO'];
                if (isset($notas[$n]) && $notas[$n]['prioridad'] === '4' && !$this->anulado($r['ESTMOV'])) {
                    $notas[$n]['etapa'] = 'Retiro de almacén';
                    $notas[$n]['etapa_nivel'] = 2;
                }
            }
            $insertarNota = $pdo->prepare('INSERT INTO seguimiento_notas(nro_pedido,unidad_organica,unidad_nombre,usuario_codigo,usuario_nombre,estado_logistica,fecha_emision,fecha_registro,tipo_pedido,prioridad,sustentacion,etapa,etapa_nivel,sincronizado_en) VALUES(:nro_pedido,:unidad_organica,:unidad_nombre,:usuario_codigo,:usuario_nombre,:estado_logistica,:fecha_emision,:fecha_registro,:tipo_pedido,:prioridad,:sustentacion,:etapa,:etapa_nivel,NOW())');
            $actualizarNota = $pdo->prepare('UPDATE seguimiento_notas SET unidad_organica=:unidad_organica,unidad_nombre=:unidad_nombre,usuario_codigo=:usuario_codigo,usuario_nombre=:usuario_nombre,estado_logistica=:estado_logistica,fecha_emision=:fecha_emision,fecha_registro=:fecha_registro,tipo_pedido=:tipo_pedido,prioridad=:prioridad,sustentacion=:sustentacion,etapa=:etapa,etapa_nivel=:etapa_nivel,sincronizado_en=NOW() WHERE nro_pedido=:nro_pedido');
            $insertarDocumento = $pdo->prepare('INSERT INTO seguimiento_documentos(nro_pedido,tipo,numero,estado,fecha,proveedor,orden_tipo,orden_numero,sincronizado_en) VALUES(:nro_pedido,:tipo,:numero,:estado,:fecha,:proveedor,:orden_tipo,:orden_numero,NOW())');
            $actualizarDocumento = $pdo->prepare('UPDATE seguimiento_documentos SET estado=:estado,fecha=:fecha,proveedor=:proveedor,orden_tipo=:orden_tipo,orden_numero=:orden_numero,sincronizado_en=NOW() WHERE id=:id');
            $eliminarDocumento = $pdo->prepare('DELETE FROM seguimiento_documentos WHERE id=:id');
            $consultarNotaAnterior = $pdo->prepare('SELECT nro_pedido,unidad_organica,unidad_nombre,usuario_codigo,usuario_nombre,estado_logistica,fecha_emision,fecha_registro,tipo_pedido,prioridad,sustentacion,etapa,etapa_nivel FROM seguimiento_notas WHERE nro_pedido=:nro_pedido');
            $consultarDocumentosAnteriores = $pdo->prepare('SELECT id,nro_pedido,tipo,numero,estado,fecha,proveedor,orden_tipo,orden_numero FROM seguimiento_documentos WHERE nro_pedido=:nro_pedido');
            $guardarHistorial = $pdo->prepare('INSERT INTO seguimiento_historial(sincronizacion_id,usuario_id,nro_pedido,tipo_documento,numero_documento,evento,campo,valor_anterior,valor_nuevo,creado_en) VALUES(:sincronizacion_id,:usuario_id,:nro_pedido,:tipo_documento,:numero_documento,:evento,:campo,:valor_anterior,:valor_nuevo,NOW())');
            $documentosPorPedido = [];
            foreach ($documentos as $documento) $documentosPorPedido[$documento['nro_pedido']][] = $documento;
            $pdo->beginTransaction();

            $i = 0;

            foreach ($notas as $nota) {
                $consultarNotaAnterior->execute(['nro_pedido' => $nota['nro_pedido']]);
                $notaAnterior = $consultarNotaAnterior->fetch() ?: null;
                $consultarDocumentosAnteriores->execute(['nro_pedido' => $nota['nro_pedido']]);
                $documentosAnteriores = $consultarDocumentosAnteriores->fetchAll();
                $this->registrarCambiosNota($guardarHistorial, $syncId, $usuarioId, $notaAnterior, $nota);
                $documentosActuales = $documentosPorPedido[$nota['nro_pedido']] ?? [];
                $this->registrarCambiosDocumentos($guardarHistorial, $syncId, $usuarioId, $nota['nro_pedido'], $documentosAnteriores, $documentosActuales, $notaAnterior !== null);
                $datoNota = $nota;
                unset($datoNota['DOCCERTI']);
                if ($notaAnterior === null) {
                    $insertarNota->execute($datoNota);
                } elseif ($this->cambioEnCampos($notaAnterior, $datoNota, ['unidad_organica','unidad_nombre','usuario_codigo','usuario_nombre','estado_logistica','fecha_emision','fecha_registro','tipo_pedido','prioridad','sustentacion','etapa','etapa_nivel'])) {
                    $actualizarNota->execute($datoNota);
                }

                $anterioresPorClave = [];
                foreach ($documentosAnteriores as $documentoAnterior) $anterioresPorClave[$this->claveDocumento($documentoAnterior['tipo'], $documentoAnterior['numero'])] = $documentoAnterior;
                foreach ($documentosActuales as $documentoActual) {
                    $claveDocumento = $this->claveDocumento($documentoActual['tipo'], $documentoActual['numero']);
                    $documentoAnterior = $anterioresPorClave[$claveDocumento] ?? null;
                    if ($documentoAnterior === null) {
                        $insertarDocumento->execute($documentoActual);
                    } else {
                        if ($this->cambioEnCampos($documentoAnterior, $documentoActual, ['estado','fecha','proveedor','orden_tipo','orden_numero'])) {
                            $actualizarDocumento->execute([
                                'id' => $documentoAnterior['id'],
                                'estado' => $documentoActual['estado'],
                                'fecha' => $documentoActual['fecha'],
                                'proveedor' => $documentoActual['proveedor'],
                                'orden_tipo' => $documentoActual['orden_tipo'],
                                'orden_numero' => $documentoActual['orden_numero'],
                            ]);
                        }
                        unset($anterioresPorClave[$claveDocumento]);
                    }
                }
                foreach ($anterioresPorClave as $documentoRetirado) $eliminarDocumento->execute(['id' => $documentoRetirado['id']]);

                if (++$i % 1000 === 0) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                }
            }

            $pdo->commit();

            $this->invalidarCacheConsultas();

            $mensaje = $this->mensajeSincronizacion($filtro);

            $pdo->prepare(
                "UPDATE seguimiento_sincronizaciones
     SET terminado_en = NOW(),
         notas_procesadas = :cantidad,
         estado = 'COMPLETADO',
         mensaje = :mensaje
     WHERE id = :id"
            )->execute([
                'cantidad' => count($notas),
                'mensaje' => $mensaje,
                'id' => $syncId,
            ]);

            return count($notas);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pdo->prepare("UPDATE seguimiento_sincronizaciones SET terminado_en=NOW(),estado='ERROR',mensaje=:m WHERE id=:id")->execute(['m' => mb_substr($e->getMessage(), 0, 500), 'id' => $syncId]);
            throw $e;
        }
    }
    private function instalar(): void
    {
        foreach (['003_seguimiento_local.sql', '007_seguimiento_documentos.sql', '008_seguimiento_historial.sql'] as $migration) {
            foreach (explode(";", (string)file_get_contents(APP_ROOT . '/database/migrations/' . $migration)) as $sql) if (trim($sql) !== '') db()->exec($sql);
        }
        try {
            db()->exec('ALTER TABLE seguimiento_notas ADD COLUMN sustentacion TEXT NULL AFTER prioridad');
        } catch (PDOException $e) {/* La columna ya existe. */
        }
    }
    private function incluye(array $fila, array $filtro): bool
    {
        /*
     * Un filtro vacío o de tipo global incluye todas las notas.
     */
        if (
            !$filtro
            || ($filtro['tipo'] ?? '') === 'global'
        ) {
            return true;
        }

        $tipo = (string) ($filtro['tipo'] ?? '');
        $valor = trim((string) ($filtro['valor'] ?? ''));

        if ($tipo === 'unidad') {
            return trim((string) $fila['CODUNIORG']) === $valor;
        }

        if ($tipo === 'mis') {
            $normalizar = static function (string $codigo): string {
                $codigo = ltrim(trim($codigo), '0');

                return $codigo !== ''
                    ? $codigo
                    : '0';
            };

            $codigoBuscado = $normalizar($valor);

            return
                $normalizar((string) $fila['USR_COD'])
                === $codigoBuscado
                || $normalizar((string) $fila['USRCODIGO'])
                === $codigoBuscado;
        }

        /*
     * Un tipo desconocido no debe incluir ningún registro.
     */
        return false;
    }
    private function mensajeSincronizacion(array $filtro): string
    {
        if (
            !$filtro
            || ($filtro['tipo'] ?? '') === 'global'
        ) {
            return 'Sincronización global de solo lectura desde Avalon';
        }

        $tipo = (string) ($filtro['tipo'] ?? '');
        $valor = trim((string) ($filtro['valor'] ?? ''));

        if ($tipo === 'unidad') {
            return 'Sincronización de la unidad '
                . $valor
                . ' desde Avalon';
        }

        if ($tipo === 'mis') {
            return 'Sincronización del usuario '
                . $valor
                . ' desde Avalon';
        }

        return 'Sincronización de solo lectura desde Avalon';
    }
    private function invalidarCacheConsultas(): void
    {
        foreach (glob(APP_ROOT . '/storage/cache/seguimiento-v25-*.json') ?: [] as $archivo) @unlink($archivo);
    }
    private function registrarCambiosNota(PDOStatement $stmt, int $syncId, ?int $usuarioId, ?array $anterior, array $actual): void
    {
        if ($anterior === null) {
            $this->guardarCambio($stmt, $syncId, $usuarioId, $actual['nro_pedido'], 'pedido', $actual['nro_pedido'], 'AGREGADO', null, null, 'Nota incorporada desde Avalon');
            return;
        }
        $campos = ['unidad_organica' => 'Unidad orgánica', 'estado_logistica' => 'Estado en Logística', 'fecha_emision' => 'Fecha de emisión', 'fecha_registro' => 'Fecha de registro', 'prioridad' => 'Prioridad', 'sustentacion' => 'Sustentación', 'etapa' => 'Última etapa'];
        foreach ($campos as $campo => $etiqueta) {
            $antes = (string)($anterior[$campo] ?? '');
            $despues = (string)($actual[$campo] ?? '');
            if ($antes === $despues) continue;
            $evento = $campo === 'estado_logistica' && $despues === 'A' ? 'ANULADO' : ($campo === 'estado_logistica' && $antes === 'A' ? 'REACTIVADO' : 'MODIFICADO');
            $this->guardarCambio($stmt, $syncId, $usuarioId, $actual['nro_pedido'], 'pedido', $actual['nro_pedido'], $evento, $etiqueta, $antes, $despues);
        }
    }
    private function registrarCambiosDocumentos(PDOStatement $stmt, int $syncId, ?int $usuarioId, string $pedido, array $anteriores, array $actuales, bool $notaExistia): void
    {
        $indexar = function (array $filas): array {
            $salida = [];
            foreach ($filas as $fila) $salida[$this->claveDocumento($fila['tipo'], $fila['numero'])] = $fila;
            return $salida;
        };
        $antes = $indexar($anteriores);
        $despues = $indexar($actuales);
        if ($notaExistia) foreach ($despues as $clave => $documento) {
            if (!isset($antes[$clave])) {
                $this->guardarCambio($stmt, $syncId, $usuarioId, $pedido, $documento['tipo'], $documento['numero'], 'AGREGADO', null, null, $documento['estado'] ?? 'Documento registrado');
                continue;
            }
            foreach (['estado' => 'Estado', 'fecha' => 'Fecha'] as $campo => $etiqueta) {
                $valorAnterior = (string)($antes[$clave][$campo] ?? '');
                $valorNuevo = (string)($documento[$campo] ?? '');
                if ($valorAnterior === $valorNuevo) continue;
                $evento = $campo === 'estado' && $valorNuevo === 'A' ? 'ANULADO' : ($campo === 'estado' && $valorAnterior === 'A' ? 'REACTIVADO' : 'MODIFICADO');
                $this->guardarCambio($stmt, $syncId, $usuarioId, $pedido, $documento['tipo'], $documento['numero'], $evento, $etiqueta, $valorAnterior, $valorNuevo);
            }
        }
        foreach ($antes as $clave => $documento) if (!isset($despues[$clave])) $this->guardarCambio($stmt, $syncId, $usuarioId, $pedido, $documento['tipo'], $documento['numero'], 'RETIRADO', null, $documento['estado'] ?? 'Documento relacionado', null);
    }
    private function guardarCambio(PDOStatement $stmt, int $syncId, ?int $usuarioId, string $pedido, string $tipo, ?string $numero, string $evento, ?string $campo, ?string $anterior, ?string $nuevo): void
    {
        $stmt->execute(['sincronizacion_id' => $syncId, 'usuario_id' => $usuarioId, 'nro_pedido' => $pedido, 'tipo_documento' => $tipo, 'numero_documento' => $numero, 'evento' => $evento, 'campo' => $campo, 'valor_anterior' => $anterior, 'valor_nuevo' => $nuevo]);
    }
    private function cambioEnCampos(array $anterior, array $actual, array $campos): bool
    {
        foreach ($campos as $campo) {
            if ((string)($anterior[$campo] ?? '') !== (string)($actual[$campo] ?? '')) return true;
        }
        return false;
    }
    private function documento(array &$documentos, $pedido, string $tipo, string $numero, string $estado, ?string $fecha, array $contexto = []): void
    {
        $pedido = (string)$pedido;
        $numero = mb_strtoupper(trim($numero));
        if ($numero === '') return;
        $clave = $pedido . '|' . $this->claveDocumento($tipo, $numero);
        $nuevo = ['nro_pedido' => $pedido, 'tipo' => $tipo, 'numero' => $numero, 'estado' => trim($estado) ?: null, 'fecha' => $fecha, 'proveedor' => trim((string)($contexto['proveedor'] ?? '')) ?: null, 'orden_tipo' => trim((string)($contexto['orden_tipo'] ?? '')) ?: null, 'orden_numero' => trim((string)($contexto['orden_numero'] ?? '')) ?: null];
        $anterior = $documentos[$clave] ?? null;
        // Si Avalon contiene el mismo número anulado y vigente, conservar el
        // vigente como representación del documento visible.
        if ($anterior && !$this->anulado((string)($anterior['estado'] ?? '')) && $this->anulado($estado)) return;
        $documentos[$clave] = $nuevo;
    }
    private function claveDocumento(string $tipo, string $numero): string
    {
        return mb_strtoupper(trim($tipo)) . '|' . mb_strtoupper(trim($numero));
    }
    private function etapa(array &$n, string $pedido, string $etapa, int $nivel): void
    {
        if (isset($n[$pedido]) && $n[$pedido]['etapa_nivel'] > 0 && $nivel >= $n[$pedido]['etapa_nivel']) {
            $n[$pedido]['etapa'] = $etapa;
            $n[$pedido]['etapa_nivel'] = $nivel;
        }
    }
    private function pedido(string $texto): ?string
    {
        if (preg_match('/PEDIDO\s*(?:NRO|N°|Nº)?\s*:?\s*(\d{7,})/iu', $texto, $m)) return $m[1];
        if (preg_match('/\b(\d{7,})\b/', $texto, $m)) return $m[1];
        return null;
    }
    private function certificadoExpediente(string $proceso, string $e): bool
    {
        return preg_match('/EXPEDIENTE\s*(?:NRO|N°|Nº)?\s*:?\s*' . preg_quote($e, '/') . '(?!\d)/iu', $proceso) === 1;
    }
    private function anulado(string $e): bool
    {
        return strtoupper(trim($e)) === 'A';
    }
    private function fecha(string $v): ?string
    {
        return preg_match('/^\d{8}$/', $v) ? substr($v, 0, 4) . '-' . substr($v, 4, 2) . '-' . substr($v, 6, 2) : null;
    }
}
