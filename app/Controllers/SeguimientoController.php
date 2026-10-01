<?php
declare(strict_types=1);
final class SeguimientoController
{
    public function detalleDocumento(): void
    {
        requiereLogin(); if(!tienePermiso('seguimiento')){http_response_code(403);exit('No tiene acceso al módulo de Seguimiento.');}
        $pedido=trim((string)($_GET['pedido']??'')); $tipo=trim((string)($_GET['tipo']??'')); $numero=trim((string)($_GET['numero']??''));
        try { $detalle=(new SeguimientoModel())->detalleDocumento($pedido,$tipo,$numero,usuario()??[]); }
        catch(RuntimeException $e){ http_response_code(404); $error=$e->getMessage(); $detalle=null; }
        require APP_ROOT.'/app/Views/documento_detalle.php';
    }
    public function index(): void
    {
        requiereLogin(); if(!tienePermiso('seguimiento')){http_response_code(403);exit('No tiene acceso al módulo de Seguimiento.');} $acceso=usuario() ?? []; $flash=takeFlash(); $term = trim((string)($_GET['q'] ?? '')); $tipo = (string)($_GET['tipo'] ?? 'todo'); $pedidoSeleccionado=trim((string)($_GET['pedido'] ?? '')); $result = null; $error = null; $coincidencias=[];
        liberarSesion();
        if (!in_array($tipo, ['todo', 'pedido', 'cotizacion', 'certificacion', 'orden_compra', 'orden_servicio', 'comprobante', 'devengado', 'pago'], true)) $tipo = 'todo';
        if ($term !== '') {
            try {
                $modelo=new SeguimientoModel();
                if ($tipo !== 'pedido') {
                    $coincidencias=$modelo->buscarCoincidencias(mb_strtoupper($term),$tipo,$acceso);
                    if ($pedidoSeleccionado !== '') {
                        $coincidencias=array_values(array_filter($coincidencias,fn(array $fila):bool=>(string)$fila['nro_pedido']===$pedidoSeleccionado));
                        if (!$coincidencias) throw new RuntimeException('La selección no pertenece a su unidad orgánica o ya no está disponible.');
                    }
                }
                if (count($coincidencias) <= 1 || $pedidoSeleccionado !== '' || $tipo === 'pedido') $result=$modelo->buscar($term,$tipo,$acceso,$pedidoSeleccionado ?: ($coincidencias[0]['nro_pedido'] ?? null));
                db()->prepare('INSERT INTO consultas(usuario_id,termino,tipo) VALUES(:usuario,:termino,:tipo)')->execute(['usuario' => $acceso['id'], 'termino' => $term, 'tipo' => strtoupper($tipo)]);
            }
            catch (RuntimeException $exception) { $error = $exception->getMessage(); }
        }
        $recent = db()->prepare('SELECT termino,tipo,creado_en FROM consultas WHERE usuario_id=:usuario ORDER BY id DESC LIMIT 5'); $recent->execute(['usuario' => usuario()['id']]); $recent = $recent->fetchAll();
        require APP_ROOT . '/app/Views/seguimiento.php';
    }
}
