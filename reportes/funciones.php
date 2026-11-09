<?php
require_once __DIR__.'/../tienda_funciones.php';
const RE_MAX_PEDIDOS=20000;
class ReporteError extends RuntimeException {}
function reEsc($v): string { return htmlspecialchars((string)$v,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'); }
function reFecha($v): ?DateTimeImmutable {
    if(!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v) || substr($v,0,4)<'1001' || substr($v,0,4)>'9998')return null;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);
    return $d && $d->format('Y-m-d')===$v?$d:null;
}
function rePeriodo(PDO $pdo,array $entrada): array {
    $hoy=new DateTimeImmutable((string)$pdo->query('SELECT CURDATE()')->fetchColumn());
    $d=array_key_exists('desde',$entrada)?reFecha($entrada['desde']):$hoy->modify('-29 days');
    $h=array_key_exists('hasta',$entrada)?reFecha($entrada['hasta']):$hoy;
    if(!$d || !$h || $d>$h || $d->diff($h)->days>365)throw new InvalidArgumentException('Selecciona fechas válidas, en orden y con un máximo de 366 días.');
    return [$d,$h];
}
function reCentavos($v): int {
    $v=(string)$v;
    if(!preg_match('/^(\d{1,15})(?:\.(\d{1,2}))?$/D',$v,$m))throw new ReporteError('Hay importes no válidos o fuera de capacidad en los pedidos. Revisa los datos.');
    return (int)$m[1]*100+(int)str_pad($m[2] ?? '',2,'0');
}
function reDecimal(int $c): string { return ($c<0?'-':'').intdiv(abs($c),100).'.'.str_pad((string)(abs($c)%100),2,'0',STR_PAD_LEFT); }
function reDinero($v): string { $c=reCentavos($v); return '$'.number_format(intdiv($c,100),0,'.',',').'.'.str_pad((string)($c%100),2,'0',STR_PAD_LEFT).' MXN'; }
function reCantidad($v): int {
    if(!preg_match('/^\d{1,17}$/D',(string)$v))throw new ReporteError('Hay cantidades no válidas en los detalles de pedidos.');
    return (int)$v;
}
function reConsulta(PDO $pdo,string $sql,array $params=[]): array { $q=$pdo->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC); }
function rePromedio(int $total,int $n): string {
    if(!$n)return '0.00'; $c=intdiv($total,$n); if(($total%$n)*2>=$n)++$c;return reDecimal($c);
}
function reResumenAnterior(PDO $pdo,array $params): array {
    $p=reConsulta($pdo,"SELECT COUNT(*) AS pedidos,COALESCE(SUM(total),0) AS importe FROM pedidos WHERE estado IN ('confirmado','preparando','entregado') AND fecha>=? AND fecha<?",$params)[0];
    $u=reConsulta($pdo,"SELECT COALESCE(SUM(d.cantidad),0) AS unidades FROM pedido_detalles d JOIN pedidos p ON p.id=d.pedido_id WHERE p.estado IN ('confirmado','preparando','entregado') AND p.fecha>=? AND p.fecha<?",$params)[0];
    $c=reCentavos($p['importe']);return ['pedidos'=>(int)$p['pedidos'],'importe'=>reDecimal($c),'unidades'=>reCantidad($u['unidades']),'promedio'=>rePromedio($c,(int)$p['pedidos'])];
}
function reCargar(PDO $pdo,DateTimeImmutable $desde,DateTimeImmutable $hasta): array {
    $fin=$hasta->modify('+1 day');$dias=(int)$desde->diff($fin)->days;$anteriorDesde=$desde->modify('-'.$dias.' days');
    $params=[$desde->format('Y-m-d'),$fin->format('Y-m-d')];
    if($pdo->inTransaction())throw new ReporteError('Espera a que termine la operación actual antes de consultar el reporte.');
    try {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();
        $n=(int)reConsulta($pdo,'SELECT COUNT(*) AS n FROM pedidos WHERE fecha>=? AND fecha<?',$params)[0]['n'];
        if($n>RE_MAX_PEDIDOS)throw new ReporteError('El período supera 20000 pedidos. Reduce el rango de fechas para consultar o exportar.');
        $pedidos=reConsulta($pdo,'SELECT p.id,p.fecha,p.usuario_nombre,p.estado,p.total,COALESCE(SUM(d.cantidad),0) AS unidades,COALESCE(SUM(d.subtotal),0) AS detalle_importe FROM pedidos p LEFT JOIN pedido_detalles d ON d.pedido_id=p.id WHERE p.fecha>=? AND p.fecha<? GROUP BY p.id,p.fecha,p.usuario_nombre,p.estado,p.total ORDER BY p.fecha,p.id',$params);
        $productos=reConsulta($pdo,"SELECT d.producto_id,MAX(d.producto_nombre) AS nombre,SUM(d.cantidad) AS unidades,SUM(d.subtotal) AS importe,COUNT(DISTINCT p.id) AS pedidos FROM pedido_detalles d JOIN pedidos p ON p.id=d.pedido_id WHERE p.estado IN ('confirmado','preparando','entregado') AND p.fecha>=? AND p.fecha<? GROUP BY d.producto_id ORDER BY unidades DESC,importe DESC,d.producto_id ASC LIMIT 20001",$params);
        if(count($productos)>20000)throw new ReporteError('El período supera 20000 productos distintos. Reduce el rango de fechas.');
        $estados=[];
        foreach(['confirmado'=>'Confirmados','preparando'=>'En preparación','entregado'=>'Entregados','cancelado'=>'Cancelados','otros'=>'No reconocidos'] as $k=>$titulo)$estados[$k]=['nombre'=>$titulo,'pedidos'=>0,'centavos'=>0,'importe'=>'0.00'];
        $serie=[];
        for($d=$desde;$d<$fin;$d=$d->modify('+1 day'))$serie[$d->format('Y-m-d')]=['dia'=>$d->format('Y-m-d'),'pedidos'=>0,'unidades'=>0,'centavos'=>0,'importe'=>'0.00'];
        $actual=['pedidos'=>0,'unidades'=>0,'importe'=>'0.00','promedio'=>'0.00'];$total=0;$detalle=0;
        foreach($pedidos as &$p) {
            $c=reCentavos($p['total']);$u=reCantidad($p['unidades']);$p['total']=reDecimal($c);$p['unidades']=$u;
            $p['vigente']=in_array($p['estado'],['confirmado','preparando','entregado'],true);
            $e=array_key_exists($p['estado'],$estados)?$p['estado']:'otros';++$estados[$e]['pedidos'];$estados[$e]['centavos']+=$c;
            if($p['vigente']) {
                ++$actual['pedidos'];$actual['unidades']+=$u;$total+=$c;$detalle+=reCentavos($p['detalle_importe']);
                $dia=substr($p['fecha'],0,10);++$serie[$dia]['pedidos'];$serie[$dia]['unidades']+=$u;$serie[$dia]['centavos']+=$c;
            }
        }
        unset($p);
        foreach($estados as &$e)$e['importe']=reDecimal($e['centavos']);unset($e);
        foreach($serie as &$s)$s['importe']=reDecimal($s['centavos']);unset($s);
        foreach($productos as &$p){$p['importe']=reDecimal(reCentavos($p['importe']));$p['unidades']=reCantidad($p['unidades']);$p['porcentaje']=$actual['unidades']?$p['unidades']*100/$actual['unidades']:0;}unset($p);
        $actual['importe']=reDecimal($total);$actual['promedio']=rePromedio($total,$actual['pedidos']);
        $anterior=reResumenAnterior($pdo,[$anteriorDesde->format('Y-m-d'),$desde->format('Y-m-d')]);
        $tienda=obtenerConfiguracionTienda($pdo);$generado=(string)$pdo->query('SELECT NOW()')->fetchColumn();
        $pdo->commit();
        return ['desde'=>$desde,'hasta'=>$hasta,'dias'=>$dias,'anteriorDesde'=>$anteriorDesde,'actual'=>$actual,'anterior'=>$anterior,'productos'=>$productos,'pedidos'=>$pedidos,'serie'=>array_values($serie),'estados'=>$estados,'diferencia_centavos'=>$total-$detalle,'total_pedidos'=>count($pedidos),'tienda'=>$tienda,'generado'=>$generado];
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
/** CSV para lectura humana en Excel: los textos peligrosos se neutralizan. */
function reCsvTexto($v): string {
    $s=preg_replace('/[\x00-\x1f\x7f]/u',' ',(string)$v);
    if(!is_string($s))$s='Texto no válido';
    $primero=preg_replace('/^[\s\p{Z}\x{FEFF}\x{200B}]+/u','',$s);
    if(preg_match('/^[=+\-@＝＋－＠]/u',$primero))$s="\t".$s;
    return $s;
}
function reCsv(array $r,string $seccion): string {
    if(!in_array($seccion,['pedidos','productos','diario'],true))throw new InvalidArgumentException('Selecciona un CSV válido: pedidos, productos o diario.');
    $f=fopen('php://memory','w+b');$linea=fopen('php://memory','w+b');fwrite($f,"\xEF\xBB\xBF");
    $escribir=static function(array $c)use($f,$linea):void{
        ftruncate($linea,0);rewind($linea);
        if(fputcsv($linea,$c,',','"','')===false)throw new ReporteError('No se pudo generar el CSV.');
        rewind($linea);$s=stream_get_contents($linea);$s=substr($s,0,-1)."\r\n";
        if(ftell($f)+strlen($s)>20*1024*1024)throw new ReporteError('El CSV supera 20 MiB. Reduce el período.');
        if(fwrite($f,$s)!==strlen($s))throw new ReporteError('No se pudo completar el CSV.');
    };
    $d=$r['desde']->format('Y-m-d');$h=$r['hasta']->format('Y-m-d');
    try {
        if($seccion==='pedidos'){
            $escribir(['Desde','Hasta','Pedido_ID','Fecha_servidor','Cliente','Estado','Vigente','Total_pedido_MXN','Importe_vigente_MXN','Unidades_pedido','Unidades_vigentes','Total_detalles_MXN','Diferencia_total_detalles_MXN']);
            foreach($r['pedidos']as $p)$escribir([$d,$h,(int)$p['id'],$p['fecha'],reCsvTexto($p['usuario_nombre']),reCsvTexto($p['estado']),$p['vigente']?'si':'no',$p['total'],$p['vigente']?$p['total']:'0.00',$p['unidades'],$p['vigente']?$p['unidades']:0,reDecimal(reCentavos($p['detalle_importe'])),reDecimal(reCentavos($p['total'])-reCentavos($p['detalle_importe']))]);
        }elseif($seccion==='productos'){
            $escribir(['Desde','Hasta','Producto_ID','Nombre_historico_referencia','Unidades_vigentes','Pedidos_vigentes_con_producto','Importe_detalles_MXN','Participacion_unidades_pct']);
            foreach($r['productos']as $p)$escribir([$d,$h,(int)$p['producto_id'],reCsvTexto($p['nombre']),$p['unidades'],(int)$p['pedidos'],$p['importe'],number_format($p['porcentaje'],2,'.','')]);
        }else{
            $escribir(['Desde','Hasta','Fecha_servidor','Pedidos_vigentes','Unidades_vigentes','Importe_vigente_MXN']);
            foreach($r['serie']as $s)$escribir([$d,$h,$s['dia'],$s['pedidos'],$s['unidades'],$s['importe']]);
        }
        rewind($f);return stream_get_contents($f);
    }finally{fclose($f);fclose($linea);}
}
