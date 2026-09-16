<?php

declare(strict_types=1);

final class NotasController
{
    public function index(): void
    {
        requiereLogin();

        $acceso = $this->acceso();
        $vistaSolicitada = (string) ($_GET['vista'] ?? '');

        $esAdministradorGlobal =
            ($acceso['rol'] ?? '') === 'ADMIN'
            && ($acceso['alcance'] ?? '') === 'GLOBAL';

        $tieneCodigoAvalon =
            trim((string) ($acceso['avalon_codigo'] ?? '')) !== '';

        if ($vistaSolicitada === 'unidad') {
            $alcance = 'unidad';
        } elseif ($vistaSolicitada === 'mis' && $tieneCodigoAvalon) {
            $alcance = 'mis';
        } elseif ($esAdministradorGlobal || !$tieneCodigoAvalon) {
            $alcance = 'unidad';
        } else {
            $alcance = 'mis';
        }

        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

        $porPaginaSolicitado = (int) ($_GET['por_pagina'] ?? 10);
        $porPagina = in_array($porPaginaSolicitado, [10, 25, 50], true)
            ? $porPaginaSolicitado
            : 10;

        $modelo = new NotasModel();

        $resultado = $modelo->listar(
            $acceso,
            $alcance,
            $busqueda,
            $pagina,
            $porPagina
        );

        $notas = $resultado['items'];
        $ultimaSincronizacion = $modelo->ultimaSincronizacion();

        require APP_ROOT . '/app/Views/notas.php';
    }

    public function sincronizar(): void
    {
        requiereLogin();
        validarCsrf();

        $acceso = $this->acceso();
        $tipo = (string) ($_POST['alcance'] ?? '');

        $esAdministradorGlobal =
            ($acceso['rol'] ?? '') === 'ADMIN'
            && ($acceso['alcance'] ?? '') === 'GLOBAL';

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

        $lock = 'avalon-seguimiento-sincronizacion';

        $stmt = db()->prepare(
            'SELECT GET_LOCK(:lock_name, 0)'
        );

        $stmt->execute([
            'lock_name' => $lock,
        ]);

        if ((int) $stmt->fetchColumn() !== 1) {
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
                ->sincronizar($filtro);

            flash(
                'ok',
                $cantidad . ' registros correspondientes a '
                    . $descripcion
                    . ' fueron actualizados desde Avalon.'
            );
        } catch (Throwable $e) {
            flash(
                'error',
                'No se pudo actualizar el seguimiento: '
                    . $e->getMessage()
            );
        } finally {
            db()->prepare(
                'SELECT RELEASE_LOCK(:lock_name)'
            )->execute([
                'lock_name' => $lock,
            ]);
        }

        header(
            'Location: ' . url(
                'notas',
                ['vista' => $vistaRedireccion]
            )
        );

        exit;
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
                'SELECT avalon_codigo, unidad_organica, rol, alcance
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
