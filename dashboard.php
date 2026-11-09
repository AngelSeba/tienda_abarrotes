<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';
protegerSesion();
require_once __DIR__.'/version17.php'; requerirVersion17($pdo);

$totalProductos = (int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$totalStock = (int)$pdo->query("SELECT COALESCE(SUM(stock),0) FROM productos")->fetchColumn();
$totalUsuarios = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$stockBajo=esAdministrador()?(int)$pdo->query('SELECT COUNT(*) FROM productos WHERE stock<=stock_minimo')->fetchColumn():0;
$totalBitacora = (int)$pdo->query("SELECT COUNT(*) FROM bitacora")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= tcEsc(tiendaNombre()) ?></title>
    <link rel="stylesheet" href="css/estilo.css">
<?= tiendaEstilos('') ?></head>
<body>
<header class="topbar">
    <div class="brand"><?= tiendaMarca('') ?></div>
    <div class="user-info">
        <?= htmlspecialchars($_SESSION['usuario']['nombre']) ?>
        <span class="role"><?= htmlspecialchars($_SESSION['usuario']['rol']) ?></span>
        <?= botonCerrarSesion('logout.php') ?>
    </div>
</header>

<div class="layout">
    <aside class="sidebar">
        <a class="active" href="dashboard.php">🏠 Inicio</a>
        <a href="productos/index.php">🛍️ Productos</a>

        <?php if (esAdministrador()): ?>
            <a href="usuarios/index.php">👥 Usuarios</a>
            <a href="bitacora/index.php">📋 Bitácora</a>
            <a href="ventas.php">📊 Reporte de ventas</a>
            <a href="pedidos/index.php">📦 Pedidos</a>
            <a href="configuracion/index.php">⚙️ Configuración</a>
            <a href="respaldos/index.php">💾 Respaldos</a><a href="inventario/index.php">📦 Inventario</a><a href="correos/index.php">✉️ Correos pendientes</a>
        <?php endif; ?>
    </aside>

    <main class="content">
        <h1>Panel principal</h1>
        <p class="welcome">Bienvenido, <?= htmlspecialchars($_SESSION['usuario']['nombre']) ?>.</p>

        <div class="cards">
            <div class="card"><span>🛍️</span><h3>Productos</h3><strong><?= $totalProductos ?></strong></div>
            <div class="card"><span>📦</span><h3>Stock total</h3><strong><?= $totalStock ?></strong></div>

            <?php if (esAdministrador()): ?>
                <div class="card"><span>👥</span><h3>Usuarios</h3><strong><?= $totalUsuarios ?></strong></div>
                <div class="card"><span>📋</span><h3>Registros</h3><strong><?= $totalBitacora ?></strong></div>
            <?php endif; ?>
        </div>

        <section class="panel">
            <h2>Accesos rápidos</h2>
            <div class="quick-links">
                <a href="productos/index.php">Ver productos</a>
                <?php if (esAdministrador()): ?>
                    <a href="productos/crear.php">Agregar producto</a>
                    <a href="usuarios/index.php">Administrar usuarios</a>
                    <a href="pedidos/index.php">Administrar pedidos</a>
                    <a href="configuracion/index.php">Personalizar negocio</a>
                    <a href="respaldos/index.php">Descargar respaldo</a><a href="inventario/index.php?bajos=1">Stock bajo: <?= $stockBajo ?></a><a href="correos/index.php">Revisar correos</a>
                    <a href="bitacora/index.php">Consultar bitácora</a>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
