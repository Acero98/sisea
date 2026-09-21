<?php
declare(strict_types=1);
final class SeguimientoController
{
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
        $historial = [];
        $esAdministrador = (usuario()['rol'] ?? '') === 'ADMIN';
        if ($esAdministrador && $result) {
            $stmtHistorial = db()->prepare('SELECT h.creado_en,h.tipo_documento,h.numero_documento,h.evento,h.campo,h.valor_anterior,h.valor_nuevo,COALESCE(u.usuario,\'Proceso programado\') actualizado_por FROM seguimiento_historial h LEFT JOIN usuarios u ON u.id=h.usuario_id WHERE h.nro_pedido=:pedido ORDER BY h.id DESC LIMIT 100');
            $stmtHistorial->execute(['pedido' => $result['pedido']['NRO_PEDIDO']]);
            $historial = $stmtHistorial->fetchAll();
        }
        $recent = db()->prepare('SELECT termino,tipo,creado_en FROM consultas WHERE usuario_id=:usuario ORDER BY id DESC LIMIT 5'); $recent->execute(['usuario' => usuario()['id']]); $recent = $recent->fetchAll();
        require APP_ROOT . '/app/Views/seguimiento.php';
    }
}
