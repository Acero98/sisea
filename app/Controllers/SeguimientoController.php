<?php
declare(strict_types=1);
final class SeguimientoController
{
    public function index(): void
    {
        requiereLogin(); $flash=takeFlash(); $term = trim((string)($_GET['q'] ?? '')); $tipo = (string)($_GET['tipo'] ?? 'todo'); $result = null; $error = null;
        liberarSesion();
        if (!in_array($tipo, ['todo', 'pedido', 'cotizacion', 'certificacion', 'orden_compra', 'orden_servicio', 'comprobante', 'devengado', 'pago'], true)) $tipo = 'todo';
        if ($term !== '') {
            try { $result = (new SeguimientoModel())->buscar($term, $tipo, usuario() ?? []); db()->prepare('INSERT INTO consultas(usuario_id,termino,tipo) VALUES(:usuario,:termino,:tipo)')->execute(['usuario' => usuario()['id'], 'termino' => $term, 'tipo' => strtoupper($tipo)]); }
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
