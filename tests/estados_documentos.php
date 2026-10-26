<?php
declare(strict_types=1);
require __DIR__.'/../config/app.php';
$casos = [
 ['expediente','C','Atendida (C)'], ['orden','P','Pendiente de atención (P)'],
 ['comprobante','P','Pagada (P)'], ['pago','P','Parcial (P)'],
 ['pago','T','Total (T)'], ['pago_orden','C','Pagada completamente (C)'],
 ['certificacion','G','Estado G — por confirmar'], ['orden','X','Estado X — por confirmar'],
];
foreach ($casos as [$tipo,$codigo,$esperado]) {
 if (EstadoDocumentoService::etiqueta($tipo,$codigo)!==$esperado) throw new RuntimeException('Traducción incorrecta: '.$tipo);
}
$c=['NRO_EXPE'=>'2000680','ZONA'=>'01','LOCA'=>'01'];
$filas=[array_merge($c,['ESTADO'=>'C']),array_merge($c,['ZONA'=>'02','ESTADO'=>'A'])];
if (EstadoDocumentoService::estadoExpediente($c,$filas)!=='C') throw new RuntimeException('Cruce entre zonas');
if (EstadoDocumentoService::estadoExpediente($c,[])!=='') throw new RuntimeException('Estado supuesto');
if (EstadoDocumentoService::estadoExpediente($c,[$filas[0],$filas[0]])!=='') throw new RuntimeException('Ambigüedad no detectada');
echo "OK: traducciones por documento, claves de expediente y estados desconocidos.\n";
