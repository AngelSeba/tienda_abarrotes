<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
require_once __DIR__ . '/imagen_funciones.php';
protegerAdministrador();
validarCsrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { header('Location: index.php'); exit; }
try {
    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT nombre, imagen FROM productos WHERE id = ? FOR UPDATE');
    $q->execute([$id]);
    $producto = $q->fetch();
    if ($producto) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM pedido_detalles d JOIN pedidos p ON p.id = d.pedido_id WHERE d.producto_id = ? AND p.estado IN ('confirmado','preparando')");
        $q->execute([$id]);
        if ((int)$q->fetchColumn() > 0) throw new RuntimeException('Este producto tiene pedidos confirmados o en preparación. Entrega o cancela esos pedidos antes de retirarlo.');
        $pdo->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
        registrarBitacora($pdo, 'Eliminó producto: ' . $producto['nombre']);
    }
    $pdo->commit();
    if ($producto) borrarImagenProducto($producto['imagen']);
    $_SESSION['mensaje_tipo'] = 'success';
    $_SESSION['mensaje'] = $producto ? 'Producto eliminado. Los pedidos anteriores se conservan.' : 'El producto ya no existe.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Eliminar producto: ' . $e->getMessage());
    $_SESSION['mensaje_tipo'] = 'error';
    $_SESSION['mensaje'] = $e instanceof RuntimeException && !($e instanceof PDOException)
        ? $e->getMessage() : 'No se pudo eliminar el producto.';
}
header('Location: index.php'); exit;
