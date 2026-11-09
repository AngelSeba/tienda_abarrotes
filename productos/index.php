<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
protegerSesion();
require_once __DIR__.'/../version17.php'; requerirVersion17($pdo);

if (empty($_SESSION['csrf_carrito'])) { $_SESSION['csrf_carrito'] = bin2hex(random_bytes(32)); }
$productos = $pdo->query("SELECT * FROM productos ORDER BY id DESC")->fetchAll();
$cantidadCarrito = array_sum($_SESSION['carrito'] ?? []);
$mensaje = $_SESSION['mensaje'] ?? '';
$mensajeTipo = ($_SESSION['mensaje_tipo'] ?? '') === 'error' ? 'error' : 'success';
unset($_SESSION['mensaje'], $_SESSION['mensaje_tipo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Productos | <?= tcEsc(tiendaNombre()) ?></title>
<link rel="stylesheet" href="../css/estilo.css">
<?= tiendaEstilos('../') ?></head>
<body>
<header class="topbar">
    <div class="brand"><?= tiendaMarca('../') ?></div>
    <div class="user-info"><?= htmlspecialchars($_SESSION['usuario']['nombre']) ?> <?= botonCerrarSesion('../logout.php') ?></div>
</header>

<div class="layout">
<aside class="sidebar">
    <a href="../dashboard.php">🏠 Inicio</a>
    <a class="active" href="index.php">🛍️ Productos</a>
    <?php if (esAdministrador()): ?>
        <a href="../pedidos/index.php">📦 Pedidos</a>
    <a href="../configuracion/index.php">⚙️ Configuración</a><a href="../inventario/index.php">📦 Inventario y mínimos</a>
        <a href="../usuarios/index.php">👥 Usuarios</a>
        <a href="../bitacora/index.php">📋 Bitácora</a>
    <?php endif; ?>
    <?php if (!esAdministrador()): ?>
        <a href="carrito.php">🛒 Mi carrito (<?= (int)$cantidadCarrito ?>)</a>
        <a href="mis_compras.php">📦 Mis compras</a>
    <?php endif; ?>
</aside>

<main class="content">
    <div class="page-header">
        <div>
            <h1>Productos</h1>
            <p><?= esAdministrador() ? 'Administra el catálogo y las existencias.' : 'Encuentra productos y agrégalos a tu carrito.' ?></p>
        </div>
        <?php if (esAdministrador()): ?>
            <a class="btn" href="crear.php">+ Agregar producto</a>
        <?php else: ?>
            <a class="btn" href="carrito.php">🛒 Ver carrito (<?= (int)$cantidadCarrito ?>)</a>
        <?php endif; ?>
    </div>

    <?php if ($mensaje): ?><div class="alert <?= $mensajeTipo ?>"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Imagen</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Fecha</th><th>Hora</th>
                <th><?= esAdministrador() ? "Acciones" : "Comprar" ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($productos as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td>
                    <?php if ($p['imagen'] && file_exists(__DIR__ . '/imagenes/' . $p['imagen'])): ?>
                        <img class="product-img" src="imagenes/<?= htmlspecialchars($p['imagen']) ?>" alt="Producto">
                    <?php else: ?>
                        <div class="no-image">🛍️</div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['nombre']) ?></td>
                <td>$<?= number_format((float)$p['precio'], 2) ?></td>
                <td><span class="stock <?= $p['stock'] <= $p['stock_minimo'] ? 'low' : '' ?>"><?= $p['stock'] ?></span></td>
                <td><?= htmlspecialchars($p['fecha']) ?></td>
                <td><?= htmlspecialchars($p['hora']) ?></td>
                <?php if (esAdministrador()): ?>
                <td class="actions">
                    <a class="btn small" href="editar.php?id=<?= $p['id'] ?>">Editar</a>
                    <form action="eliminar.php" method="POST" onsubmit="return confirm('¿Eliminar este producto?');">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button class="btn danger small" type="submit">Eliminar</button>
                    </form>
                </td>
                <?php else: ?>
                <td>
                    <?php if ((int)$p['stock'] > 0): ?>
                    <form action="carrito_accion.php" method="POST" style="display:flex;gap:8px;align-items:center">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_carrito']) ?>">
                        <input type="hidden" name="accion" value="agregar">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="number" name="cantidad" value="1" min="1" max="<?= (int)$p['stock'] ?>" style="width:65px" required>
                        <button type="submit" class="btn small">Agregar al carrito</button>
                    </form>
                    <?php else: ?>Agotado<?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$productos): ?>
            <tr><td colspan="8" class="empty">No hay productos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</main>
</div>
</body>
</html>
