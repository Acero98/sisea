<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/app.php';
require APP_ROOT.'/app/Helpers.php';
function verifyPat(bool $ok,string $label): void { if(!$ok) throw new RuntimeException($label); echo 'OK '.$label.PHP_EOL; }
$m=new PatrimonioModel(); $rows=$m->catalogo();
verifyPat(count($rows)>0,'lectura de catalogo real');
$result=$m->listar(['length'=>10],$rows);
verifyPat($result['recordsTotal']===count($rows) && count($result['data'])===10,'paginacion');
$sample=$rows[0];
foreach(['unidad','ubicacion','estado'] as $field) {
    $value=$sample[$field.'_codigo'];
    $result=$m->listar([$field=>$value?:'__vacio','length'=>100],$rows);
    $expected=count(array_filter($rows,static function($r) use($field,$value){return $r[$field.'_codigo']===$value;}));
    verifyPat($expected===$result['recordsFiltered'],'filtro '.$field);
}
$result=$m->listar(['search'=>['value'=>$sample['codigo']]],$rows);
verifyPat($result['recordsFiltered']>0,'busqueda por codigo');
$result=$m->listar(['search'=>['value'=>'__SIN_COINCIDENCIA_999__']],$rows);
verifyPat($result['recordsFiltered']===0,'busqueda sin coincidencias');
$result=$m->listar(['desde'=>'2026-01-01','hasta'=>'2026-12-31','length'=>100],$rows);
foreach($result['data'] as $r) verifyPat($r['fecha']>='2026-01-01' && $r['fecha']<='2026-12-31','rango de fechas');
try { $m->listar(['desde'=>'2026-12-01','hasta'=>'2026-01-01'],$rows); throw new RuntimeException('No rechazo el rango invertido'); } catch(DomainException $e) { echo "OK rango invertido rechazado\n"; }
verifyPat(!tienePermiso('patrimonio',['rol'=>'CONSULTA','permiso_patrimonio'=>0]),'permiso denegado');
verifyPat(tienePermiso('patrimonio',['rol'=>'CONSULTA','permiso_patrimonio'=>1]),'permiso concedido');
verifyPat(!tienePermiso('patrimonio',['rol'=>'ADMIN','permiso_patrimonio'=>0]),'denegacion explicita respetada');
echo "Catalogo consultado sin escritura: ".count($rows)." registros.\n";
