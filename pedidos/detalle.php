<?php
require_once __DIR__ . '/../funciones.php';
protegerAdministrador();
require_once __DIR__ . '/funciones.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('Pedido no encontrado.'); }
$q = $pdo->prepare('SELECT p.*, u.nombre AS nombre_actual, u.correo FROM pedidos p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?');
$q->execute([$id]); $pedido = $q->fetch();
if (!$pedido) { http_response_code(404); exit('Pedido no encontrado.'); }
$q = $pdo->prepare('SELECT d.producto_id, d.producto_nombre, d.cantidad, d.precio_unitario, d.subtotal, p.id AS producto_actual_id FROM pedido_detalles d LEFT JOIN productos p ON p.id = d.producto_id WHERE d.pedido_id = ? ORDER BY d.id');
$q->execute([$id]); $detalles = $q->fetchAll();
$q = $pdo->prepare('SELECT nombre, accion, fecha, hora FROM bitacora WHERE accion LIKE ? ORDER BY id DESC LIMIT 50');
$q->execute(['Pedido #' . $id . ':%']); $historial = $q->fetchAll();
$aviso = $_SESSION['aviso_pedido'] ?? null; unset($_SESSION['aviso_pedido']);
$opciones = pdTransiciones($pedido['estado']);
$faltantes = array_filter($detalles, static fn($d) => !$d['producto_actual_id']);
pdCabecera('Pedido #' . $id);
?>
<div class="titulo"><div><p class="sobre">DETALLE DE COMPRA</p><h1>Pedido #<?= $id ?></h1><p class="muted">Registrado el <?= pdEsc($pedido['fecha']) ?></p></div><div class="acciones-pedido"><?= pdInsignia($pedido['estado']) ?><button class="boton secundario" onclick="window.print()">Imprimir detalle</button></div></div>
<?php if (is_array($aviso)): ?><p role="status" class="aviso <?= ($aviso['tipo'] ?? '') === 'exito' ? 'aviso-exito' : 'aviso-error' ?>"><?= pdEsc($aviso['texto'] ?? '') ?></p><?php endif; ?>
<div class="detalle-pedido"><div>
<section class="panel"><h2>Cliente y compra</h2><dl class="datos-pedido"><div><dt>Nombre al comprar</dt><dd><?= pdEsc($pedido['usuario_nombre']) ?></dd></div><div><dt>Cuenta del cliente</dt><dd>#<?= (int)$pedido['usuario_id'] ?> · <?= pdEsc($pedido['nombre_actual'] ?? 'No disponible') ?></dd></div><div><dt>Correo actual</dt><dd><?= pdEsc($pedido['correo'] ?? 'No disponible') ?></dd></div><div><dt>Unidades del pedido</dt><dd><?= number_format(array_sum(array_column($detalles,'cantidad'))) ?></dd></div></dl>
<div class="tabla"><table><thead><tr><th>Producto</th><th class="numero">Cantidad</th><th class="numero">Precio</th><th class="numero">Subtotal</th></tr></thead><tbody>
<?php foreach ($detalles as $detalle): ?><tr><td><?= pdEsc($detalle['producto_nombre']) ?><?php if (!$detalle['producto_actual_id']): ?><br><small class="muted">Producto retirado del catálogo</small><?php endif; ?></td><td class="numero"><?= (int)$detalle['cantidad'] ?></td><td class="numero"><?= pdDinero($detalle['precio_unitario']) ?></td><td class="numero"><?= pdDinero($detalle['subtotal']) ?></td></tr><?php endforeach; ?>
<?php if (!$detalles): ?><tr><td colspan="4">Este pedido no tiene detalles registrados.</td></tr><?php endif; ?>
</tbody></table></div><p class="total-pedido"><?= pdDinero($pedido['total']) ?></p><p class="nota">Precios y nombres históricos del pedido. El estado no confirma que exista un pago.</p></section>
<section class="panel"><h2>Historial de cambios</h2><p class="muted">Últimos 50 cambios registrados desde la actualización 1.2.</p><div class="tabla"><table class="historial"><thead><tr><th>Fecha</th><th>Administrador</th><th>Acción y motivo</th></tr></thead><tbody><?php foreach ($historial as $evento): ?><tr><td><?= pdEsc($evento['fecha'] . ' ' . $evento['hora']) ?></td><td><?= pdEsc($evento['nombre']) ?></td><td><?= pdEsc($evento['accion']) ?></td></tr><?php endforeach; ?><?php if (!$historial): ?><tr><td colspan="3">Aún no hay cambios de estado registrados para este pedido.</td></tr><?php endif; ?></tbody></table></div></section>
</div><aside class="panel"><h2>Gestionar estado</h2>
<?php if ($opciones): ?><p class="muted">Confirma cada cambio. Al entregar o cancelar, el estado será definitivo.</p>
<?php if ($faltantes): ?><p class="aviso aviso-error">Hay productos retirados del catálogo. La cancelación está bloqueada porque no sería posible devolver todas las existencias.</p><?php endif; ?>
<form class="cambiar-estado" action="estado.php" method="post" onsubmit="return confirm('¿Confirmar este cambio? Si cancelas, se devolverán las existencias y el pedido no podrá reactivarse.');">
<?= campoCsrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="estado_anterior" value="<?= pdEsc($pedido['estado']) ?>">
<label>Nuevo estado<select name="estado" required><?php foreach ($opciones as $estado): ?><option value="<?= $estado ?>" <?= $estado === 'cancelado' && ($faltantes || !$detalles) ? 'disabled' : '' ?>><?= pdEsc(pdEstados()[$estado]) ?></option><?php endforeach; ?></select></label>
<label>Motivo o nota<textarea name="motivo" maxlength="140" placeholder="Obligatorio para cancelar: entre 5 y 140 caracteres."></textarea></label>
<button class="boton" type="submit">Guardar cambio</button></form>
<p class="nota">Cancelar devuelve las cantidades de este pedido, excluye su importe de las gráficas y registra el motivo. No envía un correo nuevo ni procesa un reembolso.</p>
<?php else: ?><p class="aviso"><?= $pedido['estado'] === 'cancelado' ? 'Pedido cancelado. No se permite devolver existencias otra vez ni reactivarlo.' : ($pedido['estado'] === 'entregado' ? 'Pedido entregado. No se permite cancelarlo desde este módulo.' : 'Estado no reconocido. Revisa el registro antes de modificarlo.') ?></p><?php endif; ?>
<p><a href="index.php">← Volver a pedidos</a></p></aside></div>
<?php pdPie(); ?>
