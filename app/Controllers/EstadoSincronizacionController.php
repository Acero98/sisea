<?php
declare(strict_types=1);

final class EstadoSincronizacionController
{
    public function index(): void
    {
        requiereLogin();
        liberarSesion();
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode((new SincronizacionControlService())->estado(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
