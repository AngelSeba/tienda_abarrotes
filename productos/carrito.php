<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
protegerSesion();
require_once __DIR__.'/../qr/funciones.php';
$tiendaQr=obtenerConfiguracionTienda($pdo);
if (esAdministrador()) { header('Location: index.php'); exit; }
if (empty($_SESSION['csrf_carrito'])) $_SESSION['csrf_carrito'] = bin2hex(random_bytes(32));
$carrito = $_SESSION['carrito'] ?? [];
$items = []; $total = 0;
if ($carrito) {
    $ids = array_values(array_filter(array_keys($carrito), fn($id) => filter_var($id, FILTER_VALIDATE_INT) && $id > 0));
    if ($ids) {
        $stmt = $pdo->prepare('SELECT id, nombre, precio, stock FROM productos WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $cantidad = min(max(0, (int)$carrito[$p['id']]), max(0, (int)$p['stock']));
            if (!$cantidad) continue;
            $p['cantidad'] = $cantidad; $p['subtotal'] = $cantidad * (float)$p['precio'];
            $items[] = $p; $total += $p['subtotal'];
        }
    }
}
$mensaje = $_SESSION['mensaje'] ?? ''; unset($_SESSION['mensaje']);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mi carrito | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/estilo.css"><?= tiendaEstilos('../') ?></head>
<body><header class="topbar"><div class="brand"><?= tiendaMarca('../') ?></div><div class="user-info"><?= htmlspecialchars($_SESSION['usuario']['nombre']) ?> <?= botonCerrarSesion('../logout.php') ?></div></header>
<div class="layout"><aside class="sidebar"><a href="../dashboard.php">🏠 Inicio</a><a href="index.php">🛍️ Productos</a><a class="active" href="carrito.php">🛒 Mi carrito</a><a href="mis_compras.php">📦 Mis compras</a></aside><main class="content">
<div class="page-header"><h1>🛒 Mi carrito</h1><a class="btn" href="index.php">Seguir comprando</a></div>
<?php if ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
<?php if (!$items): ?><p>Tu carrito está vacío o los productos ya no tienen existencias.</p><?php else: ?>
<div class="table-container"><table><thead><tr><th>Producto</th><th>Ficha QR</th><th>Precio</th><th>Cantidad</th><th>Subtotal</th><th>Acción</th></tr></thead><tbody>
<?php foreach ($items as $p): $qrUrl=qrUrlProducto($tiendaQr,(int)$p['id']);$qrPng=qrImagen($qrUrl); ?><tr><td><?= htmlspecialchars($p['nombre']) ?></td><td><?php if ($qrPng!==''): ?><a href="<?= tcEsc($qrUrl) ?>" target="_blank" rel="noopener noreferrer"><img src="<?= tcEsc($qrPng) ?>" width="132" height="132" alt="QR: ficha de <?= tcEsc($p['nombre']) ?>" style="display:block;background:#fff">Ver ficha</a><small style="display:block;max-width:145px">Imagen, precio y stock actuales</small><?php else: ?><span>Ficha QR no disponible</span><?php endif; ?></td><td>$<?= number_format((float)$p['precio'],2) ?></td><td><form action="carrito_accion.php" method="post" style="display:flex;gap:8px"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_carrito']) ?>"><input type="hidden" name="accion" value="actualizar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="number" name="cantidad" value="<?= (int)$p['cantidad'] ?>" min="0" max="<?= (int)$p['stock'] ?>" style="width:70px" required><button class="btn small">Actualizar</button></form></td><td>$<?= number_format((float)$p['subtotal'],2) ?></td><td><form action="carrito_accion.php" method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_carrito']) ?>"><input type="hidden" name="accion" value="quitar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn danger small">Quitar</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><h2 style="text-align:right">Total: $<?= number_format($total,2) ?> MXN</h2>
<form action="comprar.php" method="post" style="text-align:right"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_carrito']) ?>"><button class="btn" type="submit" onclick="return confirm('¿Confirmar la compra?')">✅ Confirmar compra</button></form>
<?php endif; ?></main></div></body></html>
