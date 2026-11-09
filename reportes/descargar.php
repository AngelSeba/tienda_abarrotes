<?php
require_once __DIR__.'/../conexion.php';require_once __DIR__.'/../funciones.php';
protegerAdministrador();
ini_set('display_errors','0');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit('Usa el enlace de descarga del reporte.');}
require_once __DIR__.'/funciones.php';
try {
    $formato=$_GET['formato'] ?? 'csv';$seccion=$_GET['seccion'] ?? 'pedidos';
    if(!is_string($formato) || !in_array($formato,['csv','pdf'],true) || !is_string($seccion))throw new InvalidArgumentException('Formato de reporte no válido.');
    if($formato==='csv' && !in_array($seccion,['pedidos','productos','diario'],true))throw new InvalidArgumentException('Sección CSV no válida.');
    [$desde,$hasta]=rePeriodo($pdo,$_GET);$r=reCargar($pdo,$desde,$hasta);@set_time_limit(90);
    if($formato==='pdf'){require_once __DIR__.'/pdf.php';$bytes=rePdf($r);$mime='application/pdf';$seccion='resumen';}
    else{$bytes=reCsv($r,$seccion);$mime='text/csv; charset=UTF-8';}
    $estado=authEstadoSesion($pdo,false);
    if(!$estado['vigente'] || !esAdministrador()){http_response_code(403);exit('Tu acceso cambió. Inicia sesión nuevamente.');}
    $nombre='ventas_'.$seccion.'_'.$desde->format('Ymd').'_'.$hasta->format('Ymd').'.'.$formato;
    session_write_close();while(ob_get_level()>0)ob_end_clean();
    header('Content-Type: '.$mime);header('Content-Disposition: attachment; filename="'.$nombre.'"');header('Content-Length: '.strlen($bytes));
    if($_SERVER['REQUEST_METHOD']!=='HEAD')echo $bytes;
}catch(InvalidArgumentException $e){http_response_code(400);header('Content-Type: text/plain; charset=UTF-8');echo $e->getMessage();}
catch(ReporteError $e){http_response_code(422);header('Content-Type: text/plain; charset=UTF-8');echo $e->getMessage();}
catch(Throwable $e){error_log('Reportes18: '.get_class($e).'; código '.$e->getCode());http_response_code(503);header('Content-Type: text/plain; charset=UTF-8');echo 'No se pudo preparar el reporte. Revisa conexión, dependencias y el registro de PHP.';}
