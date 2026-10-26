<?php

declare(strict_types=1);

final class NotasController
{
    public function index(): void
    {
        requiereLogin();
        if (!tienePermiso('seguimiento')) { http_response_code(403); exit('No tiene acceso a las notas.'); }

        $acceso = $this->acceso();
        $vistaSolicitada = (string) ($_GET['vista'] ?? '');

        $esAdministradorGlobal = ($acceso['alcance'] ?? '') === 'GLOBAL';

        $tieneCodigoAvalon =
            trim((string) ($acceso['avalon_codigo'] ?? '')) !== '';

        if (alcanceModulo('seguimiento',$acceso)==='PROPIO') {
            $alcance='mis';
        } elseif ($vistaSolicitada === 'unidad') {
            $alcance = 'unidad';
        } elseif ($vistaSolicitada === 'mis' && $tieneCodigoAvalon) {
            $alcance = 'mis';
        } elseif ($esAdministradorGlobal || !$tieneCodigoAvalon) {
            $alcance = 'unidad';
        } else {
            $alcance = 'mis';
        }

        $busqueda = trim((string) ($_GET['q'] ?? ''));
        [$desde, $hasta, $errorFechas] = $this->fechas();
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

        $porPaginaSolicitado = (int) ($_GET['por_pagina'] ?? 10);
        $porPagina = in_array($porPaginaSolicitado, [10, 25, 50], true)
            ? $porPaginaSolicitado
            : 10;

        $modelo = new NotasModel();

        $resultado = $errorFechas !== ''
            ? ['items' => [], 'total' => 0, 'pagina' => 1, 'por_pagina' => $porPagina, 'paginas' => 1]
            : $modelo->listar(
            $acceso,
            $alcance,
            $busqueda,
            $pagina,
            $porPagina,
            $desde,
            $hasta
        );

        $notas = $resultado['items'];
        $ultimaSincronizacion = $modelo->ultimaSincronizacion();

        require APP_ROOT . '/app/Views/notas.php';
    }

    public function sincronizar(): void
    {
        requiereLogin();
        if (!tienePermiso('seguimiento')) { http_response_code(403); exit('No tiene acceso a las notas.'); }
        validarCsrf();

        $acceso = $this->acceso();
        $tipo = (string) ($_POST['alcance'] ?? '');

        $esAdministradorGlobal = ($acceso['alcance'] ?? '') === 'GLOBAL';

        $codigoAvalon = trim(
            (string) ($acceso['avalon_codigo'] ?? '')
        );

        $unidadOrganica = trim(
            (string) ($acceso['unidad_organica'] ?? '')
        );

        $filtro = [];
        $vistaRedireccion = 'mis';
        $descripcion = '';

        if ($tipo === 'global') {
            if (!$esAdministradorGlobal) {
                http_response_code(403);
                exit('No tiene autorización para actualizar todas las notas.');
            }

            /*
             * Un filtro vacío indica al servicio que debe realizar
             * una sincronización completa desde Avalon.
             */
            $filtro = [];
            $vistaRedireccion = 'unidad';
            $descripcion = 'todas las notas';
        } elseif ($tipo === 'unidad') {
            if(alcanceModulo('seguimiento',$acceso)==='PROPIO'){http_response_code(403);exit('Su rol solo permite actualizar sus propias notas.');}
            if ($unidadOrganica === '' || $unidadOrganica === '0000') {
                $this->errorAlcance(
                    'No se puede determinar la unidad orgánica de este usuario.',
                    'unidad'
                );
            }

            $filtro = [
                'tipo' => 'unidad',
                'valor' => $unidadOrganica,
            ];

            $vistaRedireccion = 'unidad';
            $descripcion = 'las notas de su unidad';
        } elseif ($tipo === 'mis') {
            if ($codigoAvalon === '') {
                $this->errorAlcance(
                    'No se puede determinar el código Avalon de este usuario.',
                    'mis'
                );
            }

            $filtro = [
                'tipo' => 'mis',
                'valor' => $codigoAvalon,
            ];

            $vistaRedireccion = 'mis';
            $descripcion = 'sus notas';
        } else {
            http_response_code(400);
            exit('El alcance de actualización solicitado no es válido.');
        }

        /* La lectura de Avalon puede tardar. No conservar bloqueado el archivo
         * de sesión mientras se ejecuta para permitir otras pestañas. */
        liberarSesion();
        $control = new SincronizacionControlService();
        if (!$control->iniciar($acceso, $tipo, ucfirst($descripcion))) {
            reanudarSesion();
            flash(
                'error',
                'Ya hay una actualización en proceso. Espere unos minutos e inténtelo nuevamente.'
            );

            header(
                'Location: ' . url(
                    'notas',
                    ['vista' => $vistaRedireccion]
                )
            );

            exit;
        }

        try {
            $cantidad = (new SeguimientoSnapshotService())
                ->sincronizar($filtro, (int)($acceso['id'] ?? 0) ?: null);
            $mensajeResultado = $cantidad . ' registros correspondientes a ' . $descripcion . ' fueron revisados desde Avalon.';
            $control->finalizar(true, $mensajeResultado);
            reanudarSesion();
            flash(
                'ok',
                $mensajeResultado
            );
        } catch (Throwable $e) {
            $control->finalizar(false, $e->getMessage());
            reanudarSesion();
            flash(
                'error',
                'No se pudo actualizar el seguimiento: '
                    . $e->getMessage()
            );
        }

        header(
            'Location: ' . url(
                'notas',
                ['vista' => $vistaRedireccion]
            )
        );

        exit;
    }
    public function exportar(): void
    {
        requiereLogin();
        if (!tienePermiso('seguimiento')) { http_response_code(403); exit('No tiene acceso a las notas.'); }
        [$desde, $hasta, $errorFechas] = $this->fechas();
        if ($errorFechas !== '') {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            exit($errorFechas);
        }
        $acceso = $this->acceso();
        $vista = (string)($_GET['vista'] ?? 'unidad');
        $alcance = $vista === 'mis' && trim((string)($acceso['avalon_codigo'] ?? '')) !== '' ? 'mis' : 'unidad';
        $filas = (new NotasModel())->exportar($acceso, $alcance, trim((string)($_GET['q'] ?? '')), $desde, $hasta);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="notas-' . $alcance . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Nota','Fecha de emisión','Unidad orgánica','Nombre de unidad','Glosa / sustentación','Tipo','Prioridad','Última etapa','Estado Logística','Código usuario','Registrado por'], ';');
        foreach ($filas as $fila) fputcsv($out, [$fila['nro_pedido'],$fila['fecha_emision'],$fila['unidad_organica'],$fila['unidad_nombre'],$fila['sustentacion'],$fila['tipo_pedido'],$fila['prioridad'],$fila['etapa'],$fila['estado_logistica'],$fila['usuario_codigo'],$fila['usuario_nombre']], ';');
        fclose($out); exit;
    }

    private function fechas(): array
    {
        $desde = is_string($_GET['desde'] ?? '') ? trim($_GET['desde'] ?? '', " \t\n\r") : 'inválida';
        $hasta = is_string($_GET['hasta'] ?? '') ? trim($_GET['hasta'] ?? '', " \t\n\r") : 'inválida';
        foreach ([$desde, $hasta] as $valor) {
            if ($valor === '') continue;
            $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $valor)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;
            if (!$fecha || $fecha->format('Y-m-d') !== $valor || $valor < '1000-01-01' || $valor > '9999-12-31') {
                return [$desde, $hasta, 'Ingrese fechas válidas para la búsqueda.'];
            }
        }
        $error = $desde !== '' && $hasta !== '' && $desde > $hasta
            ? 'La fecha Desde debe ser anterior o igual a la fecha Hasta.' : '';
        return [$desde, $hasta, $error];
    }

    private function errorAlcance(
        string $mensaje,
        string $vista
    ): void {
        flash('error', $mensaje);

        header(
            'Location: ' . url(
                'notas',
                ['vista' => $vista]
            )
        );

        exit;
    }

    private function acceso(): array
    {
        $acceso = usuario() ?? [];

        if (
            empty($acceso['avalon_codigo'])
            && !empty($acceso['id'])
        ) {
            $stmt = db()->prepare(
                'SELECT avalon_codigo, unidad_organica
                 FROM usuarios
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $acceso['id'],
            ]);

            $acceso = array_merge(
                $acceso,
                $stmt->fetch() ?: []
            );

            $_SESSION['usuario'] = $acceso;
        }

        return $acceso;
    }
}
