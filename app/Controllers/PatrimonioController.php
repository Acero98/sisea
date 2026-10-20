<?php
declare(strict_types=1);
final class PatrimonioController
{
    private function acceso(): void
    {
        requiereLogin();
        if(!tienePermiso('patrimonio')) { http_response_code(403); exit('No tiene acceso a Patrimonio.'); }
    }
    public function index(): void
    {
        $this->acceso();
        $pageTitle='Patrimonio'; $pageDescription='Consulta y búsqueda de activos registrados en Avalon.';
        $breadcrumb='Patrimonio / Activos'; $activeNav='patrimonio'; $useDataTables=true;
        $opciones=[]; $error='';
        try { $model=new PatrimonioModel(); $opciones=$model->opciones($model->catalogo()); }
        catch(Throwable $e) { error_log('[Patrimonio] '.$e->getMessage()); $error='No se pudo leer el catálogo de Patrimonio. Revise la ruta de Avalon y los permisos de lectura.'; }
        require APP_ROOT.'/app/Views/patrimonio.php';
    }
    public function datos(): void
    {
        $this->acceso(); liberarSesion();
        header('Content-Type: application/json; charset=utf-8');
        try { $result=(new PatrimonioModel())->listar($_GET); }
        catch(Throwable $e) {
            error_log('[Patrimonio] '.$e->getMessage());
            http_response_code($e instanceof DomainException?422:503);
            $result=['draw'=>max(0,(int)($_GET['draw']??0)),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],
                'error'=>$e instanceof DomainException?$e->getMessage():'No se pudo consultar Patrimonio. Verifique la conexión con Avalon e intente nuevamente.'];
        }
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
