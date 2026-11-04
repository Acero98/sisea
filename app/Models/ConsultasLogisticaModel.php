<?php
declare(strict_types=1);
final class ConsultasLogisticaModel
{
    private DbfReader $reader;
    public function __construct() { $this->reader = new DbfReader(false); }
    public static function clave(array $r, string $ultimo): string
    { return implode('|', array_map(static fn($c)=>(string)($r[$c] ?? ''), ['PERIODO','ZONA','LOCA',$ultimo])); }
    public static function recurso(array $r): string
    { return implode('|',[$r['TIPOFAMI'] ?? '',$r['GRUPO'] ?? '',$r['SUBGRUPO'] ?? '',$r['SEC_BIEN'] ?? $r['SEC_SERV'] ?? '']); }
    public static function permitido(array $r, array $a): bool
    {
        if (!tienePermiso('ordenes',$a)) return false;
        $scope = alcanceModulo('ordenes',$a);
        if ($scope === 'GLOBAL') return true;
        if ($scope === 'PROPIO') {
            $codigo=trim((string)($a['avalon_codigo'] ?? ''));
            return $codigo!=='' && trim((string)($r['USRCODIGO'] ?? ''))!=='' && ltrim(trim($r['USRCODIGO']),'0')===ltrim($codigo,'0');
        }
        $unidad=trim((string)($a['unidad_organica'] ?? ''));
        return $unidad!=='' && $unidad!=='0000' && trim((string)($r['CODUNIORG'] ?? ''))===$unidad;
    }
    private function filas(string $file,array $fields): iterable
    {
        foreach ($this->reader->indexedRows(AVALON_LOGISTICA_DATA.'/'.$file,$fields) as $row) if ($row['datos']!==null) yield $row['datos'];
    }
    public function catalogo(string $modo): array
    {
        if ($modo==='proveedores') return array_values(ProveedorService::catalogo());
        if ($modo==='cuentas') {
            $out=[];
            foreach ($this->reader->rows(AVALON_PLANIF_DATA.'/tab_recu.dbf',['COD_CCP1','DESCRIP']) as $r) $out[$r['COD_CCP1']]=['codigo'=>$r['COD_CCP1'],'nombre'=>$r['DESCRIP']];
            return array_values($out);
        }
        $out=[];
        foreach ([['sum40004.dbf','SEC_BIEN','DESC_MAT'],['sum40003.dbf','SEC_SERV','DESC_SERV']] as [$file,$key,$name]) {
            foreach ($this->filas($file,['TIPOFAMI','GRUPO','SUBGRUPO',$key,$name,'UNIMED','CTACTBLE']) as $r) $out[]=['codigo'=>self::recurso($r),'nombre'=>$r[$name],'unidad'=>$r['UNIMED'],'cuenta'=>$r['CTACTBLE'],'tipo'=>$key==='SEC_SERV'?'Servicio':'Bien'];
        }
        return $out;
    }
    public function historial(string $modo,string $codigo,array $acceso,string $anio,int $pagina): array
    {
        $pedidos=[]; $referencias=[];
        foreach ($this->filas('sum40014.DBF',['PERIODO','ZONA','LOCA','NRO_PEDIDO','CODUNIORG','USRCODIGO','USRNOMBRE','FEC_EMISIO']) as $r) {
            $referencias[$r['ZONA'].'|'.$r['LOCA'].'|'.$r['NRO_PEDIDO']][self::clave($r,'NRO_PEDIDO')]=self::clave($r,'NRO_PEDIDO');
            if (self::permitido($r,$acceso) && ($anio==='' || substr($r['FEC_EMISIO'],0,4)===$anio)) $pedidos[self::clave($r,'NRO_PEDIDO')]=$r;
        }
        // El período de la orden puede ser distinto al mes del pedido.
        // Resolver la referencia solo cuando identifica una cabecera inequívoca.
        $recursos=[];
        foreach ($this->catalogo('almacen') as $r) $recursos[$r['codigo']]=$r;
        $unidades=[];
        foreach ($this->reader->rows(AVALON_PLANIF_DATA.'/unidad.dbf',['COD_UNIDAD','DES_UNIDAD']) as $u) $unidades[$u['COD_UNIDAD']]=$u['DES_UNIDAD'];
        $cuentas=[];
        foreach ($this->catalogo('cuentas') as $c) $cuentas[$c['codigo']]=$c['nombre'];
        $camposDetalle=['PERIODO','ZONA','LOCA','NRO_PEDIDO','SECPEDIDO','TIPOFAMI','GRUPO','SUBGRUPO','SEC_BIEN','CAN_ORDENA','CAN_SALI','CAN_INGRE','CTA_CON','COD_PROY'];
        $preseleccion=null; $pretotal=0; $clavesPagina=[];
        if ($modo!=='proveedores') {
            $preseleccion=[];
            foreach ($this->filas('sum40007.DBF',$camposDetalle) as $d) {
                $pk=self::clave($d,'NRO_PEDIDO'); $rk=self::recurso($d);
                if (!isset($pedidos[$pk]) || ($modo==='almacen' && $rk!==$codigo) || ($modo==='cuentas' && trim($d['CTA_CON'])!==$codigo)) continue;
                $pretotal++;
                if ($pretotal<=($pagina-1)*25 || count($preseleccion)>=25) continue;
                $preseleccion[]=$d; $clavesPagina[$pk.'|'.$d['SECPEDIDO'].'|'.$rk]=true;
            }
        }
        $compras=[];
        $cabeceras=[];
        foreach ([['sum40015.DBF','NRO_ORDCMP','orden_compra'],['sum40017.DBF','NRO_SERVIC','orden_servicio']] as [$file,$key,$tipo]) {
            foreach ($this->filas($file,['PERIODO','ZONA','LOCA',$key,'CODPROV','FEC_EMISIO','MONEDA']) as $r) {
                if ($modo==='proveedores' && trim($r['CODPROV'])!==$codigo) continue;
                $r['tipo']=$tipo; $r['numero']=$r[$key];
                $cabeceras[$tipo.'|'.self::clave($r,$key)]=$r;
            }
        }
        foreach ([['sum40016.DBF','NRO_ORDCMP','SEC_BIEN','orden_compra'],['sum40018.DBF','NRO_SERVIC','SEC_SERV','orden_servicio']] as [$file,$key,$sec,$tipo]) {
            foreach ($this->filas($file,['PERIODO','ZONA','LOCA',$key,'NRO_PEDIDO','SECPEDIDO','TIPOFAMI','GRUPO','SUBGRUPO',$sec,'CAN_ORDENA','PRE_TOT']) as $r) {
                $candidatos=$referencias[$r['ZONA'].'|'.$r['LOCA'].'|'.$r['NRO_PEDIDO']] ?? [];
                if (count($candidatos)!==1) continue;
                $pk=reset($candidatos);
                if (!isset($pedidos[$pk])) continue;
                $cab=$cabeceras[$tipo.'|'.self::clave($r,$key)] ?? null;
                if (!$cab) continue;
                $rk=self::recurso($r);
                if ($preseleccion!==null && !isset($clavesPagina[$pk.'|'.$r['SECPEDIDO'].'|'.$rk])) continue;
                if ($modo==='almacen' && $rk!==$codigo) continue;
                $compras[$pk.'|'.$r['SECPEDIDO'].'|'.$rk][]=array_merge($cab,['cantidad'=>$r['CAN_ORDENA'],'importe'=>$r['PRE_TOT']]);
            }
        }
        $total=0; $resultados=[]; $offset=($pagina-1)*25;
        foreach ($preseleccion ?? $this->filas('sum40007.DBF',$camposDetalle) as $r) {
            $pk=self::clave($r,'NRO_PEDIDO'); $rk=self::recurso($r);
            if (!isset($pedidos[$pk]) || ($modo==='almacen' && $rk!==$codigo) || ($modo==='cuentas' && trim($r['CTA_CON'])!==$codigo)) continue;
            $ordenes=$compras[$pk.'|'.$r['SECPEDIDO'].'|'.$rk] ?? [];
            if ($modo==='proveedores' && !$ordenes) continue;
            $total++;
            if (($preseleccion===null && $total<=$offset) || count($resultados)>=25) continue;
            $p=$pedidos[$pk];
            $p['UNIDAD_NOMBRE']=$unidades[$p['CODUNIORG']] ?? '';
            $r['CUENTA_NOMBRE']=$cuentas[$r['CTA_CON']] ?? '';
            foreach ($ordenes as &$orden) {
                $orden['proveedor']=ProveedorService::resolver($orden['CODPROV']);
                if (tienePermiso('ordenes_detalle',$acceso)) $orden['url']=url('detalle_documento',['origen'=>'ordenes','pedido'=>$p['NRO_PEDIDO'],'tipo'=>$orden['tipo'],'numero'=>$orden['numero']]);
                if (!tienePermiso('ordenes_montos',$acceso)) unset($orden['importe']);
            }
            unset($orden);
            $resultados[]=['pedido'=>$p,'detalle'=>$r,'recurso'=>$recursos[$rk] ?? ['codigo'=>$rk,'nombre'=>'Descripción no disponible','unidad'=>''],'ordenes'=>$ordenes];
        }
        $configurados=[];
        if ($modo==='cuentas') foreach ($recursos as $r) if ($r['cuenta']!=='' && $r['cuenta']===$codigo) $configurados[]=$r;
        return ['filas'=>$resultados,'total'=>$preseleccion!==null?$pretotal:$total,'configurados'=>$configurados];
    }
}
