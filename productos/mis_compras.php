<?php
require_once __DIR__ . '/../conexion.php'; require_once __DIR__ . '/../funciones.php'; protegerSesion();
require_once __DIR__.'/../version17.php'; requerirVersion17($pdo);
if (esAdministrador()) { header('Location: index.php'); exit; }
$etiquetasEstado = ['confirmado'=>'Confirmado','preparando'=>'En preparación','entregado'=>'Entregado','cancelado'=>'Cancelado'];
$nombre = (string)($_SESSION['usuario']['nombre'] ?? '');
$stmt = $pdo->prepare('SELECT p.id,p.total,p.estado,p.fecha,c.estado AS correo_estado FROM pedidos p LEFT JOIN correos_pendientes c ON c.pedido_id=p.id WHERE p.usuario_id=? ORDER BY p.id DESC');
$stmt->execute([(int)$_SESSION['usuario']['id']]); $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$mensaje = $_SESSION['mensaje'] ?? ''; unset($_SESSION['mensaje']);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mis compras | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/estilo.css"><?= tiendaEstilos('../') ?></head><body>
<header class="topbar"><div class="brand"><?= tiendaMarca('../') ?></div><div class="user-info"><?= htmlspecialchars($nombre) ?> <?= botonCerrarSesion('../logout.php') ?></div></header>
<div class="layout"><aside class="sidebar"><a href="../dashboard.php">🏠 Inicio</a><a href="index.php">🛍️ Productos</a><a href="carrito.php">🛒 Mi carrito</a><a class="active" href="mis_compras.php">📦 Mis compras</a></aside><main class="content"><div class="page-header"><h1>Mis compras</h1></div>
<?php if ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
<div class="table-container"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Detalle</th><th>Correo</th><th>Ticket PDF</th></tr></thead><tbody><?php foreach ($pedidos as $pedido): ?><tr><td>#<?= (int)$pedido['id'] ?></td><td><?= htmlspecialchars($pedido['fecha']) ?></td><td>$<?= number_format((float)$pedido['total'],2) ?></td><td><?= htmlspecialchars($etiquetasEstado[$pedido['estado']] ?? $pedido['estado']) ?></td><td><a class="btn small" href="detalle_compra.php?id=<?= (int)$pedido['id'] ?>">Ver productos</a></td><td><?= htmlspecialchars(['pendiente'=>'Pendiente de envío','enviando'=>'Procesando','enviado'=>'Enviado','error'=>'Requiere revisión','incierto'=>'Requiere revisión','cancelado'=>'Cancelado'][$pedido['correo_estado'] ?? ''] ?? 'Sin registro de envío') ?></td><td><a class="btn small" href="ticket_pdf.php?id=<?= (int)$pedido['id'] ?>">Descargar ticket</a></td></tr><?php endforeach; ?><?php if (!$pedidos): ?><tr><td colspan="7">Aún no has realizado compras.</td></tr><?php endif; ?></tbody></table></div></main></div></body></html>
