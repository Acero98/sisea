<?php
declare(strict_types=1);

final class OrdenesModel
{
    private DbfReader $reader;

    public function __construct()
    {
        $this->reader = new DbfReader();
    }

    public function listar(array $acceso, array $entrada): array
    {
        $draw = max(0, (int)($entrada['draw'] ?? 0));
        $start = max(0, (int)($entrada['start'] ?? 0));
        $length = (int)($entrada['length'] ?? 25);
        $length = $length < 1 ? 25 : min($length, 100);
        $tipo = (string)($entrada['tipo'] ?? 'todas');
        if (!in_array($tipo, ['todas', 'orden_compra', 'orden_servicio'], true)) $tipo = 'todas';
        $estado = (string)($entrada['estado'] ?? 'todos');
        if (!in_array($estado, ['todos', 'vigentes', 'anuladas'], true)) $estado = 'todos';
        $desde = $this->fechaFiltro((string)($entrada['desde'] ?? ''));
        $hasta = $this->fechaFiltro((string)($entrada['hasta'] ?? ''));
        $busqueda = trim((string)($entrada['search']['value'] ?? ''));
        $ordenesSeleccionadas = array_values(array_unique(array_filter(array_map(static function ($valor): string {
            $valor = trim((string)$valor);
            return preg_match('/^(orden_compra|orden_servicio)\|[A-Za-z0-9._-]{1,40}$/', $valor) ? $valor : '';
        }, (array)($entrada['ordenes'] ?? [])))));

        $global = alcanceModulo('seguimiento', $acceso) === 'GLOBAL';
        $unidad = trim((string)($acceso['unidad_organica'] ?? ''));
        $sinAlcance = !$global && ($unidad === '' || $unidad === '0000');

        $where = ["d.tipo IN ('orden_compra','orden_servicio')"];
        $params = [];
        if (!$global) {
            if ($sinAlcance) $where[] = '1=0';
            else { $where[] = 'n.unidad_organica=:unidad'; $params['unidad'] = $unidad; }
        }
        if ($tipo !== 'todas') { $where[] = 'd.tipo=:tipo'; $params['tipo'] = $tipo; }
        if ($estado === 'anuladas') $where[] = "UPPER(TRIM(COALESCE(d.estado,'')))='A'";
        if ($estado === 'vigentes') $where[] = "UPPER(TRIM(COALESCE(d.estado,'')))<>'A'";
        if ($desde !== null) { $where[] = 'd.fecha>=:desde'; $params['desde'] = $desde; }
        if ($hasta !== null) { $where[] = 'd.fecha<=:hasta'; $params['hasta'] = $hasta; }
        if ($ordenesSeleccionadas) {
            $seleccion = [];
            foreach ($ordenesSeleccionadas as $i => $valor) {
                [$tipoSeleccionado, $numeroSeleccionado] = explode('|', $valor, 2);
                $tipoParam = 'sel_tipo_' . $i; $numeroParam = 'sel_numero_' . $i;
                $seleccion[] = '(d.tipo=:' . $tipoParam . ' AND d.numero=:' . $numeroParam . ')';
                $params[$tipoParam] = $tipoSeleccionado; $params[$numeroParam] = $numeroSeleccionado;
            }
            $where[] = '(' . implode(' OR ', $seleccion) . ')';
        }

        $filtro = implode(' AND ', $where);
        $total = $this->contar($filtro, $params);
        $filtroBusqueda = $filtro;
        $parametrosBusqueda = $params;
        if ($busqueda !== '') {
            $filtroBusqueda .= ' AND (d.numero LIKE :q_numero OR n.nro_pedido LIKE :q_pedido OR n.unidad_organica LIKE :q_unidad OR n.unidad_nombre LIKE :q_nombre_unidad OR n.usuario_nombre LIKE :q_usuario OR n.sustentacion LIKE :q_glosa)';
            $like = '%' . $busqueda . '%';
            foreach (['numero', 'pedido', 'unidad', 'nombre_unidad', 'usuario', 'glosa'] as $campo) $parametrosBusqueda['q_' . $campo] = $like;
        }
        $filtrados = $this->contar($filtroBusqueda, $parametrosBusqueda);

        $ordenes = [0 => 'd.numero', 1 => 'd.tipo', 2 => 'fecha', 3 => 'MIN(d.nro_pedido)', 4 => 'unidad_label', 5 => 'estado'];
        $columna = (int)($entrada['order'][0]['column'] ?? 2);
        $ordenSql = $ordenes[$columna] ?? 'd.fecha';
        $direccion = strtolower((string)($entrada['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $sql = 'SELECT MIN(d.nro_pedido) AS nro_pedido,d.tipo,d.numero,MAX(d.estado) AS estado,MAX(d.fecha) AS fecha,'
            . 'GROUP_CONCAT(DISTINCT d.nro_pedido ORDER BY d.nro_pedido SEPARATOR ", ") AS pedidos_relacionados,'
            . 'GROUP_CONCAT(DISTINCT CONCAT_WS(" · ",NULLIF(n.unidad_organica,""),NULLIF(n.unidad_nombre,"")) ORDER BY n.unidad_organica SEPARATOR " / ") AS unidad_label,'
            . 'MIN(n.sustentacion) AS sustentacion '
            . 'FROM seguimiento_documentos d INNER JOIN seguimiento_notas n ON n.nro_pedido=d.nro_pedido '
            . 'WHERE ' . $filtroBusqueda . ' GROUP BY d.tipo,d.numero '
            . 'ORDER BY ' . $ordenSql . ' ' . $direccion . ', d.numero DESC LIMIT :limite OFFSET :inicio';
        $stmt = db()->prepare($sql);
        foreach ($parametrosBusqueda as $campo => $valor) $stmt->bindValue(':' . $campo, $valor);
        $stmt->bindValue(':limite', $length, PDO::PARAM_INT);
        $stmt->bindValue(':inicio', $start, PDO::PARAM_INT);
        $stmt->execute();
        $filas = $stmt->fetchAll();
        $this->completarImportes($filas, tienePermiso('ver_montos', $acceso), tienePermiso('ver_detalle', $acceso));

        return ['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtrados, 'data' => $filas];
    }

    private function contar(string $filtro, array $params): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM (SELECT d.tipo,d.numero FROM seguimiento_documentos d INNER JOIN seguimiento_notas n ON n.nro_pedido=d.nro_pedido WHERE ' . $filtro . ' GROUP BY d.tipo,d.numero) ordenes');
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function completarImportes(array &$filas, bool $mostrarMontos, bool $puedeVerDetalle): void
    {
        $necesarios = ['orden_compra' => [], 'orden_servicio' => []];
        foreach ($filas as $fila) $necesarios[$fila['tipo']][(string)$fila['numero']] = true;
        $campos = [
            'orden_compra' => ['NRO_ORDCMP','TOTALOC','VAL_TOTORD','MONEDA'],
            'orden_servicio' => ['NRO_SERVIC','TOTALOS','VAL_TOTORD','MONEDA'],
        ];
        $archivos = ['orden_compra' => 'sum40015.DBF', 'orden_servicio' => 'sum40017.DBF'];
        $keys = ['orden_compra' => 'NRO_ORDCMP', 'orden_servicio' => 'NRO_SERVIC'];
        $cache = [];
        foreach ($mostrarMontos ? $necesarios : [] as $tipo => $numeros) {
            foreach (array_keys($numeros) as $numero) {
                try {
                    $cabeceras = $this->reader->findExact(AVALON_LOGISTICA_DATA . '/' . $archivos[$tipo], $campos[$tipo], $keys[$tipo], (string)$numero);
                    $cache[$tipo][$numero] = $cabeceras[0] ?? [];
                } catch (RuntimeException $error) {
                    $cache[$tipo][$numero] = [];
                }
            }
        }
        foreach ($filas as &$fila) {
            if ($mostrarMontos) {
                $cabecera = $cache[$fila['tipo']][(string)$fila['numero']] ?? [];
                $total = $fila['tipo'] === 'orden_compra' ? ($cabecera['TOTALOC'] ?? '') : ($cabecera['TOTALOS'] ?? '');
                if (!is_numeric($total) || (float)$total === 0.0) $total = $cabecera['VAL_TOTORD'] ?? '';
                $moneda = trim((string)($cabecera['MONEDA'] ?? ''));
                $prefijo = $moneda === '01' ? 'S/ ' : ($moneda !== '' ? $moneda . ' ' : '');
                $fila['importe'] = is_numeric($total) ? $prefijo . number_format((float)$total, 2, '.', ',') : '—';
            }
            $fila['tipo_label'] = $fila['tipo'] === 'orden_compra' ? 'Compra' : 'Servicio';
            $fila['numero_label'] = $fila['tipo'] === 'orden_compra' ? 'OC ' . $fila['numero'] : 'OS ' . $fila['numero'];
            $fila['estado_label'] = trim((string)$fila['estado']) === '' ? 'Sin estado' : (strtoupper(trim((string)$fila['estado'])) === 'A' ? 'Anulada' : 'Avalon: ' . trim((string)$fila['estado']));
            $instante = $fila['fecha'] ? strtotime((string)$fila['fecha']) : false;
            $fila['fecha_label'] = $instante !== false ? date('d/m/Y', $instante) : '—';
            if ($puedeVerDetalle) $fila['detalle_url'] = url('detalle_documento', ['pedido' => $fila['nro_pedido'], 'tipo' => $fila['tipo'], 'numero' => $fila['numero']]);
            $fila['busqueda_unidad'] = trim((string)($fila['unidad_label'] ?? 'Unidad no identificada'));
            $fila['sustentacion'] = trim((string)$fila['sustentacion']);
        }
        unset($fila);
    }

    private function fechaFiltro(string $fecha): ?string
    {
        $fecha = trim($fecha);
        if ($fecha === '') return null;
        $date = DateTime::createFromFormat('!Y-m-d', $fecha);
        return $date && $date->format('Y-m-d') === $fecha ? $fecha : null;
    }
}
