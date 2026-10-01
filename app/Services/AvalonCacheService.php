<?php
declare(strict_types=1);

/** Mantiene en MySQL la copia de consulta. Avalon se abre únicamente en lectura. */
final class AvalonCacheService
{
    private DbfReader $reader;

    public function __construct()
    {
        $this->reader = new DbfReader(false);
    }

    public function sincronizar(?callable $progreso = null): array
    {
        $this->instalar();
        $resultado = [];
        foreach ($this->fuentes() as $fuente) {
            if ($progreso) $progreso('Leyendo '.$fuente['archivo']);
            $resultado[$fuente['archivo']] = $this->sincronizarFuente($fuente);
            if ($progreso) $progreso('Finalizado '.$fuente['archivo']);
        }
        return $resultado;
    }

    private function sincronizarFuente(array $fuente): array
    {
        $pdo = db();
        $archivo = strtolower(basename($fuente['ruta']));
        clearstatcache(true, $fuente['ruta']);
        $tamano = (int)filesize($fuente['ruta']);
        $modificado = (int)filemtime($fuente['ruta']);
        $estructura = sha1(json_encode([$fuente['campos'], $fuente['indices']]));
        $estado = $pdo->prepare('SELECT tamano,modificado_en,estructura FROM avalon_cache_fuentes WHERE archivo=?');
        $estado->execute([$archivo]);
        $estadoAnterior = $estado->fetch();
        if ($estadoAnterior && (int)$estadoAnterior['tamano'] === $tamano && (int)$estadoAnterior['modificado_en'] === $modificado && (string)$estadoAnterior['estructura'] === $estructura) {
            return ['nuevos'=>0, 'modificados'=>0, 'eliminados'=>0, 'omitido'=>true];
        }
        $insertar = $pdo->prepare('INSERT INTO avalon_cache_filas(archivo,numero_fila,contenido,huella,actualizado_en) VALUES(?,?,?,?,NOW())');
        $actualizar = $pdo->prepare('UPDATE avalon_cache_filas SET contenido=?,huella=?,actualizado_en=NOW() WHERE id=?');
        $borrarFila = $pdo->prepare('DELETE FROM avalon_cache_filas WHERE archivo=? AND numero_fila=?');
        $borrarIndices = $pdo->prepare('DELETE FROM avalon_cache_indices WHERE fila_id=?');
        $insertarIndice = $pdo->prepare('INSERT INTO avalon_cache_indices(fila_id,archivo,campo,valor) VALUES(?,?,?,?)');
        $ultimo = 0; $nuevos = 0; $modificados = 0; $eliminados = 0;
        $contar = $pdo->prepare('SELECT COUNT(*) FROM avalon_cache_filas WHERE archivo=?');
        $contar->execute([$archivo]);
        $fuenteVacia = (int)$contar->fetchColumn() === 0;
        $lote = []; $bloqueActual = -1; $anteriores = [];

        $pdo->beginTransaction();
        try {
            foreach ($this->reader->indexedRows($fuente['ruta'], $fuente['campos']) as $fila) {
                $numero = (int)$fila['numero_fila'];
                $ultimo = $numero;
                if ($fuenteVacia) {
                    if ($fila['datos'] !== null) $lote[] = $fila;
                    if (count($lote) >= 500) {
                        $nuevos += $this->insertarLote($pdo, $archivo, $fuente['indices'], $lote);
                        $lote = [];
                    }
                    continue;
                }
                $bloque = intdiv(max(0, $numero - 1), 5000);
                if ($bloque !== $bloqueActual) {
                    $desde = $bloque * 5000 + 1; $hasta = $desde + 4999;
                    $stmtBloque = $pdo->prepare('SELECT id,numero_fila,huella FROM avalon_cache_filas WHERE archivo=? AND numero_fila BETWEEN ? AND ?');
                    $stmtBloque->execute([$archivo, $desde, $hasta]);
                    $anteriores = [];
                    foreach ($stmtBloque->fetchAll() as $existente) $anteriores[(int)$existente['numero_fila']] = $existente;
                    $bloqueActual = $bloque;
                }
                $anterior = $anteriores[$numero] ?? false;
                if ($fila['datos'] === null) {
                    if ($anterior) { $borrarFila->execute([$archivo, $numero]); $eliminados++; }
                    continue;
                }
                $json = json_encode($fila['datos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($json === false) throw new RuntimeException('No se pudo preparar una fila de ' . $archivo . '.');
                $huella = sha1($json);
                if ($anterior && hash_equals((string)$anterior['huella'], $huella)) continue;
                if ($anterior) {
                    $id = (int)$anterior['id'];
                    $actualizar->execute([$json, $huella, $id]);
                    $borrarIndices->execute([$id]);
                    $modificados++;
                } else {
                    $insertar->execute([$archivo, $numero, $json, $huella]);
                    $id = (int)$pdo->lastInsertId();
                    $nuevos++;
                }
                foreach ($fuente['indices'] as $campo) {
                    $valor = trim((string)($fila['datos'][$campo] ?? ''));
                    if ($valor !== '') $insertarIndice->execute([$id, $archivo, $campo, mb_substr($valor, 0, 255)]);
                }
            }
            if ($lote) $nuevos += $this->insertarLote($pdo, $archivo, $fuente['indices'], $lote);
            $borrarSobrantes = $pdo->prepare('DELETE FROM avalon_cache_filas WHERE archivo=? AND numero_fila>?');
            $borrarSobrantes->execute([$archivo, $ultimo]);
            $eliminados += $borrarSobrantes->rowCount();
            $guardarEstado = $pdo->prepare('INSERT INTO avalon_cache_fuentes(archivo,ruta,tamano,modificado_en,estructura,sincronizado_en) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE ruta=VALUES(ruta),tamano=VALUES(tamano),modificado_en=VALUES(modificado_en),estructura=VALUES(estructura),sincronizado_en=NOW()');
            $guardarEstado->execute([$archivo, str_replace('\\', '/', $fuente['ruta']), $tamano, $modificado, $estructura]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return ['nuevos'=>$nuevos, 'modificados'=>$modificados, 'eliminados'=>$eliminados];
    }

    private function insertarLote(PDO $pdo, string $archivo, array $indices, array $lote): int
    {
        $valores = []; $params = [];
        foreach ($lote as $fila) {
            $json = json_encode($fila['datos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) throw new RuntimeException('No se pudo preparar una fila de ' . $archivo . '.');
            $valores[] = '(?,?,?,?,NOW())';
            array_push($params, $archivo, (int)$fila['numero_fila'], $json, sha1($json));
        }
        $pdo->prepare('INSERT INTO avalon_cache_filas(archivo,numero_fila,contenido,huella,actualizado_en) VALUES '.implode(',', $valores))->execute($params);
        $desde = (int)$lote[0]['numero_fila'];
        $hasta = (int)$lote[count($lote)-1]['numero_fila'];
        $buscarIds = $pdo->prepare('SELECT id,numero_fila FROM avalon_cache_filas WHERE archivo=? AND numero_fila BETWEEN ? AND ?');
        $buscarIds->execute([$archivo, $desde, $hasta]);
        $ids = [];
        foreach ($buscarIds->fetchAll() as $registro) $ids[(int)$registro['numero_fila']] = (int)$registro['id'];
        $valoresIndice = []; $paramsIndice = [];
        foreach ($lote as $fila) {
            $id = $ids[(int)$fila['numero_fila']] ?? null;
            if ($id === null) continue;
            foreach ($indices as $campo) {
                $valor = trim((string)($fila['datos'][$campo] ?? ''));
                if ($valor === '') continue;
                $valoresIndice[] = '(?,?,?,?)';
                array_push($paramsIndice, $id, $archivo, $campo, mb_substr($valor, 0, 255));
            }
        }
        if ($valoresIndice) $pdo->prepare('INSERT INTO avalon_cache_indices(fila_id,archivo,campo,valor) VALUES '.implode(',', $valoresIndice))->execute($paramsIndice);
        return count($lote);
    }

    private function instalar(): void
    {
        $sql = (string)file_get_contents(APP_ROOT . '/database/migrations/012_avalon_cache.sql');
        foreach (explode(';', $sql) as $sentencia) if (trim($sentencia) !== '') db()->exec($sentencia);
        try { db()->exec('ALTER TABLE avalon_cache_fuentes ADD COLUMN estructura CHAR(40) NULL AFTER modificado_en'); }
        catch (PDOException $e) { /* La columna ya existe. */ }
    }

    private function fuentes(): array
    {
        return [
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40014.DBF', ['NRO_PEDIDO','CODUNIORG','USR_COD','USRCODIGO','USRNOMBRE','EST_PEDIDO','FEC_EMISIO','PRIORIDAD','TIPO_PED','COMENTARIO','TOTCANTSAL','CERTIFICA','DOCCERTI'], ['NRO_PEDIDO']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40007.DBF', ['NRO_PEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','CAN_ORDENA','CENCOS','COD_PROY','CODACTIVID','CTA_CON'], ['NRO_PEDIDO']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40003.DBF', ['TIPOFAMI','GRUPO','SUBGRUPO','SEC_SERV','DESC_SERV','UNIMED'], ['SEC_SERV']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40004.DBF', ['TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','DESC_MAT','UNIMED'], ['SEC_BIEN']),
            $this->fuente(AVALON_CONTAB_DATA . '/cct0004.DBF', ['CCTA','DESC'], ['CCTA']),
            $this->fuente(AVALON_PLANIF_DATA.'/tab_recu.dbf', ['COD_CCP1','DESCRIP'], ['COD_CCP1']),
            $this->fuente(AVALON_PLANIF_DATA.'/mae_proy.dbf', ['COD_PROY','DESC_PROY'], ['COD_PROY']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40008.DBF', ['NRO_PEDIDO','IDMOVIM','IDTRANSAC','TIPO_MOV','DOC_REF','FECHA','CANTIDAD','NUMALM','ALM_REF','ESTMOV','ENTREGADOA','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','SEC_ITEMMO'], ['NRO_PEDIDO']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40025.DBF', ['NRO_EXPE','FEC_COTIZA','REFERENCIA','ESTADO'], ['NRO_EXPE','REFERENCIA']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40026.DBF', ['NRO_EXPE','NRO_SOLCOT','CODPROV','FECOFER','VALOFER','FORENTR','PLAZENTR'], ['NRO_EXPE']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40028.DBF', ['NRO_EXPE','NRO_SOLCOT','CODPROV','NRO_PEDIDO','SECPEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','SEC_SERV','CAN_PED','CAN_PRO','PRE_PRO','PRO_TOT','CTA_CTBLE','CODPROY','CENCOS','ADJUDIC'], ['NRO_EXPE','NRO_PEDIDO']),
            $this->fuente(AVALON_PLANIF_DATA.'/cabcertifica.DBF', ['CODCER','FECHA','PROCESO','ESTADO'], ['CODCER','PROCESO']),
            $this->fuente(AVALON_PLANIF_DATA.'/detcertifica.DBF', ['CODCER','SEC','CUENTA','SOLICITADO','DISPONIBLE','SALDO','RECURSO'], ['CODCER']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40015.DBF', ['NRO_ORDCMP','NRO_EXPE','FEC_EMISIO','ZONA','LOCA','CODPROV','FOR_PAGO','LUG_ENTREG','VAL_TOTORD','MONIMPTO','MONOTROS','EST_ORDCMP','ESTFACTU','CERTIFICA','REFCERTIFI','REFER','OBSERVAS','OBRA','TOTALOC','MONEDA'], ['NRO_ORDCMP','NRO_EXPE','REFER']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40016.DBF', ['NRO_ORDCMP','NRO_PEDIDO','SECPEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','SEC_ITEMOC','CAN_ORDENA','CAN_RECIBI','PRE_UNITAR','PRE_TOT','MONIMPTO','CTA_CON','COD_PROY','CODUNIORG','ACTIVIDAD','CODACTIVID','DETLIN','ESTADO'], ['NRO_ORDCMP','NRO_PEDIDO']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40017.DBF', ['NRO_SERVIC','NRO_EXPE','FEC_EMISIO','ZONA','LOCA','CODPROV','FOR_PAGO','VAL_TOTORD','MONIMPTO','ESTADO','ESTFACTU','CERTIFICA','REFCERTIFI','REFER','OBSERVAS','TOTALOS','MONEDA'], ['NRO_SERVIC','NRO_EXPE','REFER']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40018.DBF', ['NRO_SERVIC','NRO_PEDIDO','SECPEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_SERV','SECITEMOS','CAN_ORDENA','PRE_UNITAR','PRE_TOT','MONIMPTO','CTA_CON','CODPROY','CCOSTO','ACTIVIDAD','CODACTIVID','DETLIN','ESTADO'], ['NRO_SERVIC','NRO_PEDIDO']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40030.DBF', ['COMPROB','NRO_SERVIC','NRO_ORDCMP','DOCSER','DOCNRO','FECCOMP','ESTADO','PAGADO','FECPAGO','NOMPROV','RUCPROV','PREVENT','MONEDA'], ['COMPROB','NRO_SERVIC','NRO_ORDCMP']),
            $this->fuente(AVALON_LOGISTICA_DATA.'/sum40031.DBF', ['COMPROB','NRO_SERVIC','NRO_ORDCMP','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','SEC_SERV','VALOR','CCOSTO','CTA_CTBLE','CODPROY','ESTADO'], ['COMPROB']),
            $this->fuente(AVALON_CONTAB_DATA . '/FCON0024.DBF', ['FCONTABLE','DOC1','FECT','OCOS','SERIE','NUMERO','MON_SOL'], ['OCOS']),
            $this->fuente(AVALON_FINANZAS_DATA . '/rfin0019.DBF', ['FCONTABLE','CMPBTE','DOC1','COMPROB','IMPORTE','ESTADO'], ['COMPROB']),
            $this->fuente(AVALON_PLANIF_DATA.'/unidad.dbf', ['COD_UNIDAD','DES_UNIDAD'], ['COD_UNIDAD']),
        ];
    }

    private function fuente(string $ruta, array $campos, array $indices): array
    {
        return ['ruta'=>$ruta, 'archivo'=>strtolower(basename($ruta)), 'campos'=>$campos, 'indices'=>$indices];
    }
}
