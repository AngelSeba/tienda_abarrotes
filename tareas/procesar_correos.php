<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../conexion.php';
require_once __DIR__.'/../correos/funciones.php';
require_once __DIR__.'/../version17.php';
try {
    requerirVersion17($pdo);
    $resultado=cqProcesar($pdo,5);
    echo json_encode($resultado,JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit($resultado['errores']>0?2:0);
} catch (Throwable $e) { error_log('Ejecutor17: '.get_class($e)); fwrite(STDERR,"No se pudo procesar la cola. Revisa base, migración y permisos.\n"); exit(1); }
