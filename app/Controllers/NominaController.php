<?php
declare(strict_types=1);

final class NominaController
{
    public function index(): void
    {
        requiereLogin();
        if (!tienePermiso('nomina')) {
            http_response_code(403);
            exit('No tiene acceso a Nómina.');
        }
        $titulos = ['resumen'=>'Resumen mensual', 'personal'=>'Personal por régimen y centro de costo'];
        if (tienePermiso('nomina_detalle')) {
            $titulos += ['conceptos'=>'Importes por concepto', 'distribucion'=>'Distribución del gasto', 'control'=>'Control de consistencia'];
        }
        $reporte = $_GET['reporte'] ?? 'resumen';
        if (!is_string($reporte) || !isset($titulos[$reporte])) {
            http_response_code(403);
            exit('Reporte no disponible para su rol.');
        }
        $filas = [];
        $mes = '';
        $resumen = [];
        $error = '';
        try {
            $resumen = (new NominaModel())->resumenMensual($reporte === 'resumen' && tienePermiso('nomina_detalle'));
            $mes = $_GET['mes'] ?? (array_key_first($resumen) ?? date('Y-m'));
            if (!is_string($mes) || !preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $mes)) {
                http_response_code(400);
                exit('Seleccione un mes válido.');
            }
            if ($reporte !== 'resumen') {
                $modelo = new NominaReportesModel();
                $filas = $modelo->$reporte($mes);
            }
        } catch (Throwable $e) {
            error_log('[Nómina] '.$e->getMessage());
            $error = 'No se pudo leer la planilla de Avalon.';
        }
        $anios = array_unique(array_map(static fn($periodo) => substr($periodo, 0, 4), array_keys($resumen)));
        $anio = (string)($_GET['anio'] ?? reset($anios));
        require APP_ROOT.'/app/Views/nomina.php';
    }
}
