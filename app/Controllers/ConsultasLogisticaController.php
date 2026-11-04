<?php
declare(strict_types=1);
final class ConsultasLogisticaController
{
    public function index(): void
    {
        requiereLogin();
        if (!tienePermiso('ordenes')) { http_response_code(403); exit('No tiene acceso a consultas de Logística.'); }
        $acceso=usuario() ?? []; liberarSesion();
        $modo=(string)($_GET['r'] ?? 'almacen');
        $modelo=new ConsultasLogisticaModel();
        $q=trim((string)($_GET['q'] ?? '')); $codigo=trim((string)($_GET['codigo'] ?? ''));
        $anio=trim((string)($_GET['anio'] ?? '')); if (!preg_match('/^\d{4}$/',$anio)) $anio='';
        $pagina=max(1,(int)($_GET['pagina'] ?? 1));
        $catalogo=$modelo->catalogo($modo); $seleccion=null;
        foreach ($catalogo as $r) if ((string)$r['codigo']===$codigo) { $seleccion=$r; break; }
        if (!$seleccion && $codigo!=='' && in_array($modo,['proveedores','cuentas'],true)) $seleccion=$modo==='proveedores'?ProveedorService::resolver($codigo):['codigo'=>$codigo,'nombre'=>'Cuenta '.$codigo.' · nombre no disponible'];
        $historial=null;
        if ($seleccion) $historial=$modelo->historial($modo,$codigo,$acceso,$anio,$pagina);
        $coincidencias=array_values(array_filter($catalogo,static fn($r)=>$q==='' || mb_stripos($r['codigo'].' '.$r['nombre'],$q)!==false));
        $totalCatalogo=count($coincidencias); $coincidencias=array_slice($coincidencias,($pagina-1)*25,25);
        $mostrarMontos=tienePermiso('ordenes_montos',$acceso);
        require APP_ROOT.'/app/Views/consultas_logistica.php';
    }
}
