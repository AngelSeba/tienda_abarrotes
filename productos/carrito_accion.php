<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
protegerSesion();
if (esAdministrador()) { http_response_code(403); exit('Solo clientes.'); }
validarCsrfCarrito();
$accion = $_POST['accion'] ?? '';
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
$_SESSION['carrito'] ??= [];
if ($accion === 'vaciar') { $_SESSION['carrito'] = []; }
elseif ($accion === 'quitar' && $id && $id > 0) { unset($_SESSION['carrito'][$id]); }
elseif (in_array($accion, ['agregar','actualizar'], true) && $id && $id > 0 && $cantidad !== false && $cantidad !== null && $cantidad >= 0) {
    $stmt = $pdo->prepare('SELECT stock FROM productos WHERE id = ?');
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$producto) { $_SESSION['mensaje'] = 'El producto ya no existe.'; }
    else {
        $stock = (int)$producto['stock'];
        $nueva = $accion === 'agregar' ? (int)($_SESSION['carrito'][$id] ?? 0) + $cantidad : $cantidad;
        if ($nueva > $stock) { $_SESSION['mensaje'] = 'Cantidad ajustada al stock disponible.'; $nueva = $stock; }
        if ($nueva > 0) $_SESSION['carrito'][$id] = $nueva;
        else unset($_SESSION['carrito'][$id]);
    }
} else { $_SESSION['mensaje'] = 'Acción o cantidad no válida.'; }
header('Location: ' . ($accion === 'agregar' ? 'index.php' : 'carrito.php'));
exit;
