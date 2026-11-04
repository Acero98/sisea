<?php
declare(strict_types=1);
final class ProveedorService
{
    private static ?array $catalogo = null;
    public static function catalogo(): array
    {
        if (self::$catalogo !== null) return self::$catalogo;
        $datos = [];
        foreach ((new DbfReader(false))->rows(AVALON_LOGISTICA_DATA.'/sum40013.dbf', ['CODPROV','NOMBRE']) as $r) {
            $codigo = trim($r['CODPROV']);
            if ($codigo !== '') $datos[$codigo] = ['codigo'=>$codigo,'nombre'=>trim($r['NOMBRE']) ?: 'Nombre no disponible','ruc'=>preg_match('/^\d{11}$/',$codigo) ? $codigo : ''];
        }
        return self::$catalogo = $datos;
    }
    public static function resolver(string $codigo, array $comprobantes = []): array
    {
        $codigo = trim($codigo);
        try { $p = self::catalogo()[$codigo] ?? null; } catch (RuntimeException $e) { $p = null; }
        if ($p && $p['nombre'] !== 'Nombre no disponible') return $p;
        $opciones = [];
        foreach ($comprobantes as $c) {
            if (trim((string)($c['RUCPROV'] ?? '')) !== $codigo || trim((string)($c['NOMPROV'] ?? '')) === '') continue;
            $opciones[trim($c['NOMPROV'])] = ['codigo'=>$codigo,'nombre'=>trim($c['NOMPROV']),'ruc'=>trim($c['RUCPROV'])];
        }
        return count($opciones) === 1 ? reset($opciones) : ($p ?? ['codigo'=>$codigo,'nombre'=>'Nombre no disponible','ruc'=>preg_match('/^\d{11}$/',$codigo) ? $codigo : '']);
    }
}
