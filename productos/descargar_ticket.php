<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
protegerSesion();
require_once __DIR__.'/../version17.php'; requerirVersion17($pdo);
if (esAdministrador()) { http_response_code(403); exit('Solo clientes.'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('Pedido no encontrado.'); }
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) { http_response_code(503); exit('Falta instalar las dependencias con Composer.'); }
require_once $autoload;
require_once __DIR__ . '/ticket_funciones.php';
try {
    $usuarioId = (int)$_SESSION['usuario']['id'];
    try { [$pedido,$detalles]=obtenerPedidoConDetalles($pdo,$id,$usuarioId); }
    catch (RuntimeException $e) { http_response_code(404); exit('Pedido no encontrado.'); }
    $bytes = obtenerTicketPdfCache($pdo,$pedido,$detalles,obtenerConfiguracionTienda($pdo));
    session_write_close();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="ticket_pedido_' . $id . '.pdf"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
} catch (RuntimeException $e) {
    http_response_code(503);
    exit('No fue posible preparar el ticket. Si el pedido es tuyo, inténtalo de nuevo en unos segundos.');
} catch (Throwable $e) {
    error_log('Error de PDF: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo generar el PDF. Revisa el registro de PHP y las extensiones GD, DOM y mbstring.');
}
