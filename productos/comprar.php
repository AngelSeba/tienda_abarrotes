<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';

protegerSesion();
require_once __DIR__.'/../version17.php'; requerirVersion17($pdo);
require_once __DIR__.'/../inventario/funciones.php';
require_once __DIR__.'/../correos/funciones.php';
$inicioCompra=microtime(true);

// Solo los clientes pueden comprar.
if (esAdministrador()) {
    http_response_code(403);
    exit('Solo clientes.');
}

// Validar la solicitud y el token del carrito.
validarCsrfCarrito();

// Recuperar carrito.
$carrito = $_SESSION['carrito'] ?? [];

if (!$carrito) {
    $_SESSION['mensaje'] = 'Tu carrito está vacío.';
    header('Location: carrito.php');
    exit;
}

// Identificar al cliente por ID; el nombre se conserva como dato histórico.
$usuarioId = (int)$_SESSION['usuario']['id'];
$nombre = trim(
    (string)($_SESSION['usuario']['nombre'] ?? '')
);

$correo = trim(
    (string)($_SESSION['usuario']['correo'] ?? '')
);

if ($nombre === '') {
    $_SESSION['mensaje'] = 'No se pudo identificar al usuario.';
    header('Location: carrito.php');
    exit;
}

$compraGuardada = false;

try {

    // Iniciar transacción.
    $pdo->beginTransaction();

    $ids = array_keys($carrito);

    // Validar productos y cantidades.
    foreach ($ids as $id) {

        if (
            !filter_var($id, FILTER_VALIDATE_INT) ||
            (int)$id <= 0 ||
            !filter_var(
                $carrito[$id],
                FILTER_VALIDATE_INT
            ) ||
            (int)$carrito[$id] <= 0
        ) {
            throw new RuntimeException(
                'Hay productos inválidos en el carrito.'
            );
        }
    }

    sort($ids, SORT_NUMERIC);

    // Bloquear productos durante la compra.
    $stmt = $pdo->prepare(
        'SELECT id, nombre, precio, stock
         FROM productos
         WHERE id = ?
         FOR UPDATE'
    );

    $items = [];
    $totalCentavos = 0;

    foreach ($ids as $id) {

        $stmt->execute([$id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        $cantidad = (int)$carrito[$id];

        if (
            !$producto ||
            $cantidad > (int)$producto['stock']
        ) {
            throw new RuntimeException(
                'No hay suficiente stock. Revisa tu carrito.'
            );
        }

        $precio = (float)$producto['precio'];

        if (!is_finite($precio) || $precio <= 0) {
            throw new RuntimeException(
                'El producto necesita un precio mayor que cero. Solicita al administrador que lo actualice.'
            );
        }

        $precioCentavos = (int)round($precio * 100);
        $limiteCentavos = 999999999999; // Capacidad de DECIMAL(12,2).
        if ($precioCentavos < 1 || $cantidad > intdiv($limiteCentavos - $totalCentavos, $precioCentavos)) {
            throw new RuntimeException('El importe del pedido supera el máximo permitido. Reduce las cantidades.');
        }
        $subtotalCentavos = $precioCentavos * $cantidad;

        $totalCentavos += $subtotalCentavos;

        $items[] = [
            'id' => (int)$id,
            'nombre' => $producto['nombre'],
            'cantidad' => $cantidad,
            'stock_antes'=>(int)$producto['stock'],
            'precio' => number_format(
                $precioCentavos / 100,
                2,
                '.',
                ''
            ),
            'subtotal' => number_format(
                $subtotalCentavos / 100,
                2,
                '.',
                ''
            )
        ];
    }

    // Registrar pedido.
    $pedido = $pdo->prepare(
        'INSERT INTO pedidos
         (usuario_id, usuario_nombre, total, estado)
         VALUES (?, ?, ?, ?)'
    );

    $pedido->execute([
        $usuarioId,
        $nombre,
        number_format($totalCentavos / 100, 2, '.', ''),
        'confirmado'
    ]);

    $pedidoId = (int)$pdo->lastInsertId();

    // Registrar detalles.
    $detalle = $pdo->prepare(
        'INSERT INTO pedido_detalles
         (
             pedido_id,
             producto_id,
             producto_nombre,
             cantidad,
             precio_unitario,
             subtotal
         )
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    // Actualizar inventario.
    $descontar = $pdo->prepare(
        'UPDATE productos
         SET stock = stock - ?
         WHERE id = ? AND stock >= ?'
    );

    foreach ($items as $item) {

        $detalle->execute([
            $pedidoId,
            $item['id'],
            $item['nombre'],
            $item['cantidad'],
            $item['precio'],
            $item['subtotal']
        ]);

        $descontar->execute([
            $item['cantidad'],
            $item['id'],
            $item['cantidad']
        ]);

        if ($descontar->rowCount() !== 1) {
            throw new RuntimeException(
                'Stock insuficiente. Intenta nuevamente.'
            );
        }
    }

    // La cola y los movimientos son parte de la misma transacción que el pedido.
    foreach ($items as $item) invMovimiento($pdo,['id'=>$item['id'],'nombre'=>$item['nombre']],'venta',$item['stock_antes'],$item['stock_antes']-$item['cantidad'],'Compra registrada',$pedidoId);
    cqEncolar($pdo,$pedidoId,$usuarioId,$correo,$nombre);
    // Confirmar la compra sin generar PDF ni contactar SMTP.
    $pdo->commit();
    $compraGuardada = true;
    rtTiempo('compra_guardada',$inicioCompra,['pedido'=>$pedidoId]);

    // Vaciar el carrito y renovar el token.
    $_SESSION['carrito'] = [];
    $_SESSION['csrf_carrito'] = bin2hex(
        random_bytes(32)
    );

    $avisoCorreo=' El ticket quedó pendiente de envío. Puedes descargarlo desde Mis compras.';

    // Mensaje de compra exitosa.
    $_SESSION['mensaje'] =
        'Compra #' . $pedidoId .
        ' registrada correctamente. ' .
        '(No se ha procesado ningún pago en línea).' .
        $avisoCorreo;

    // Redirigir al historial.
    header('Location: mis_compras.php');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Error en comprar.php: ' . $e->getMessage()
    );

    if ($compraGuardada) {

        $_SESSION['mensaje'] =
            'La compra quedó registrada, pero ocurrió ' .
            'un problema posterior. Revisa Mis compras.';

        header('Location: mis_compras.php');
        exit;
    }

    $_SESSION['mensaje'] =
        ($e instanceof RuntimeException && !($e instanceof PDOException))
            ? $e->getMessage()
            : 'Error al registrar la compra.';

    header('Location: carrito.php');
    exit;
}
