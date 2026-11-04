<?php
declare(strict_types=1);
final class AlmacenReportesController
{
    private function acceso(): void
    {
        requiereLogin();
        if(!tienePermiso('almacen_reportes')){http_response_code(403);exit('Su rol no tiene acceso a reportes de Almacén.');}
        liberarSesion();
    }
    public function index(): void
    {
        $this->acceso();$f=AlmacenReportesModel::filtros($_GET);$datos=(new AlmacenReportesModel())->cargar($f);
        $total=count($datos['filas']);
        $filas=$datos['filas'];
        require APP_ROOT.'/app/Views/almacen_reportes.php';
    }
    public function exportar(): void
    {
        $this->acceso();$f=AlmacenReportesModel::filtros($_GET);$datos=(new AlmacenReportesModel())->cargar($f);
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="almacen-'.$f['reporte'].'-'.date('Ymd').'.csv"');header('Cache-Control: no-store');
        $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['Código','Artículo','Unidad','Fecha de alta','Último movimiento','Base de evaluación','Días sin movimiento / desde alta','Tramo','Origen de la fecha','Líneas de ingreso del mes','Cantidad ingresada del mes','Mes consultado','Fecha de corte','Reporte'], ';');
        foreach($datos['filas'] as $r){
            $fila=[$r['codigo'],$r['nombre'],$r['unidad'],$r['alta']?$r['alta']->format('Y-m-d'):'',$r['ultimo']?$r['ultimo']->format('Y-m-d'):'',$r['base']?$r['base']->format('Y-m-d'):'',$r['dias']??'',AlmacenReportesModel::TRAMOS[$r['tramo']],$r['origen'],$r['ingresos'],$r['cantidad_ingreso'],$f['mes'],$datos['corte']->format('Y-m-d'),$f['reporte']];
            fputcsv($out,array_map([AlmacenReportesModel::class,'celdaCsv'],$fila),';');
        }
        fclose($out);
    }
}
