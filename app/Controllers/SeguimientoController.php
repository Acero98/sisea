<?php
declare(strict_types=1);
final class SeguimientoController
{
    public function index(): void
    {
        requiereLogin(); $term = trim((string)($_GET['q'] ?? '')); $result = null; $error = null;
        if ($term !== '') {
            try { $result = (new SeguimientoModel())->buscar($term); db()->prepare('INSERT INTO consultas(usuario_id,termino,tipo) VALUES(:usuario,:termino,:tipo)')->execute(['usuario' => usuario()['id'], 'termino' => $term, 'tipo' => 'PEDIDO']); }
            catch (RuntimeException $exception) { $error = $exception->getMessage(); }
        }
        $recent = db()->prepare('SELECT termino,tipo,creado_en FROM consultas WHERE usuario_id=:usuario ORDER BY id DESC LIMIT 5'); $recent->execute(['usuario' => usuario()['id']]); $recent = $recent->fetchAll();
        require APP_ROOT . '/app/Views/seguimiento.php';
    }
}
