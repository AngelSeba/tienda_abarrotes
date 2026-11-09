<?php
require_once __DIR__ . '/funciones.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
if (!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)) { http_response_code(405); header('Allow: GET, POST'); echo '{"error":"Método no permitido"}'; exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') validarCsrf();
try {
    if (!isset($_SESSION['usuario']['id'])) {
        http_response_code(401); echo json_encode(['vigente'=>false,'inicio'=>rutaInicio()]); exit;
    }
    require_once __DIR__ . '/conexion.php';
    $estado=authEstadoSesion($pdo,$_SERVER['REQUEST_METHOD']==='POST');
    if (!$estado['vigente']) { authLimpiarSesion($estado['motivo']); http_response_code(401); }
    echo json_encode(['vigente'=>$estado['vigente'],'restante'=>$estado['restante'] ?? 0,'inicio'=>rutaInicio()],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('No se pudo comprobar la sesión: '.$e->getMessage());
    http_response_code(503); echo '{"error":"No se pudo comprobar la sesión"}';
}
