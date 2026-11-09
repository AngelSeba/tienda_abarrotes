<?php
require_once __DIR__ . '/../funciones.php';
protegerAdministrador();
validarCsrf();
require_once __DIR__ . '/funciones.php';
$id = filter_var(textoPost('id'), FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Pedido no válido.'); }
try {
    $_SESSION['aviso_pedido'] = ['tipo' => 'exito', 'texto' => pdCambiarEstado($pdo, $id, textoPost('estado'), textoPost('estado_anterior'), textoPost('motivo'))];
} catch (Throwable $e) {
    error_log('Estado pedido #' . $id . ': ' . $e->getMessage());
    $mensaje = $e instanceof RuntimeException && !($e instanceof PDOException)
        ? $e->getMessage() : 'No fue posible confirmar el cambio. Recarga el pedido para revisar su estado.';
    $_SESSION['aviso_pedido'] = ['tipo' => 'error', 'texto' => $mensaje];
}
header('Location: detalle.php?id=' . (int)$id, true, 303); exit;
