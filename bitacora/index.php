<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
protegerAdministrador();

$registros = $pdo->query("SELECT * FROM bitacora ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Bitácora | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/estilo.css"><?= tiendaEstilos('../') ?></head>
<body>
<header class="topbar">
<div class="brand"><?= tiendaMarca('../') ?></div>
<div class="user-info"><?= htmlspecialchars($_SESSION['usuario']['nombre']) ?> <?= botonCerrarSesion('../logout.php') ?></div>
</header>
<div class="layout">
<aside class="sidebar">
<a href="../dashboard.php">🏠 Inicio</a>
    <a href="../pedidos/index.php">📦 Pedidos</a>
    <a href="../configuracion/index.php">⚙️ Configuración</a>
<a href="../productos/index.php">🛍️ Productos</a>
<a href="../usuarios/index.php">👥 Usuarios</a>
<a class="active" href="index.php">📋 Bitácora</a>
</aside>
<main class="content">
<div class="page-header">
<div><h1>Bitácora</h1><p>Historial de acciones realizadas en el sistema.</p></div>
</div>
<div class="table-container">
<table>
<thead><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Acción</th><th>Fecha</th><th>Hora</th></tr></thead>
<tbody>
<?php foreach ($registros as $r): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['nombre']) ?></td>
<td><?= htmlspecialchars($r['correo']) ?></td>
<td><?= htmlspecialchars($r['accion']) ?></td>
<td><?= htmlspecialchars($r['fecha']) ?></td>
<td><?= htmlspecialchars($r['hora']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$registros): ?><tr><td colspan="6" class="empty">No hay registros.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</main>
</div>
</body>
</html>
