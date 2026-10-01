<?php
declare(strict_types=1);

final class OrdenesController
{
    private function validarAcceso(): array
    {
        requiereLogin();
        if (!tienePermiso('seguimiento')) {
            http_response_code(403);
            exit('No tiene acceso al módulo de órdenes.');
        }
        return usuario() ?? [];
    }

    public function index(): void
    {
        $acceso = $this->validarAcceso();
        $pageTitle = 'Órdenes de compra y servicio';
        $pageDescription = 'Consulta en solo lectura de las órdenes vinculadas a pedidos dentro de tu alcance.';
        $breadcrumb = 'Logística / Órdenes';
        $activeNav = 'ordenes';
        $useDataTables = true;
        $mostrarMontos = tienePermiso('ver_montos', $acceso);
        $puedeVerDetalle = tienePermiso('ver_detalle', $acceso);
        $ordenesIniciales = array_values(array_unique(array_filter(array_map(static function ($valor): string {
            $valor = trim((string)$valor);
            return preg_match('/^(orden_compra|orden_servicio)\|[A-Za-z0-9._-]{1,40}$/', $valor) ? $valor : '';
        }, (array)($_GET['ordenes'] ?? [])))));
        require APP_ROOT . '/app/Views/ordenes.php';
    }

    public function datos(): void
    {
        $acceso = $this->validarAcceso();
        liberarSesion();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $resultado = (new OrdenesModel())->listar($acceso, $_GET);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $error) {
            error_log('[SISEA órdenes] ' . get_class($error) . ': ' . $error->getMessage());
            http_response_code(500);
            echo json_encode([
                'draw' => max(0, (int)($_GET['draw'] ?? 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'No se pudo consultar la copia de Avalon. Inténtalo de nuevo en unos minutos.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
