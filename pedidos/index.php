<?php
require_once __DIR__ . '/../funciones.php';
protegerAdministrador();
require_once __DIR__ . '/funciones.php';
$entrada = [];
foreach (['buscar', 'estado', 'desde', 'hasta', 'pagina'] as $clave) {
    if (isset($_GET[$clave]) && (!is_string($_GET[$clave]) || strpos($_GET[$clave], "\0") !== false)) {
        http_response_code(400); pdCabecera('Filtros no válidos');
        echo '<p class="aviso aviso-error">Los filtros no son válidos. <a href="index.php">Volver a pedidos</a></p>'; pdPie(); exit;
    }
    $entrada[$clave] = trim($_GET[$clave] ?? '');
}
$buscar = $entrada['buscar']; $estado = $entrada['estado'];
$desde = $entrada['desde'] !== '' ? pdFecha($entrada['desde']) : null;
$hasta = $entrada['hasta'] !== '' ? pdFecha($entrada['hasta']) : null;
if (mb_strlen($buscar) > 120 || ($estado !== '' && !isset(pdEstados()[$estado]))
    || ($entrada['desde'] !== '' && !$desde) || ($entrada['hasta'] !== '' && !$hasta)
    || ($desde && $hasta && $desde > $hasta)) {
    http_response_code(400); pdCabecera('Filtros no válidos');
    echo '<p class="aviso aviso-error">Revisa el estado, las fechas y la búsqueda (hasta 120 caracteres). <a href="index.php">Limpiar filtros</a></p>'; pdPie(); exit;
}
$condiciones = []; $parametros = [];
if ($buscar !== '') {
    $patron = '%' . strtr($buscar, ['=' => '==', '%' => '=%', '_' => '=_']) . '%';
    $idBuscado = filter_var(ltrim($buscar, '#'), FILTER_VALIDATE_INT);
    $condiciones[] = "(p.id = ? OR p.usuario_nombre LIKE ? ESCAPE '=' OR u.nombre LIKE ? ESCAPE '=' OR u.correo LIKE ? ESCAPE '=')";
    array_push($parametros, $idBuscado && $idBuscado > 0 ? $idBuscado : 0, $patron, $patron, $patron);
}
if ($desde) { $condiciones[] = 'p.fecha >= ?'; $parametros[] = $desde->format('Y-m-d'); }
if ($hasta) { $condiciones[] = 'p.fecha < ?'; $parametros[] = $hasta->modify('+1 day')->format('Y-m-d'); }
$base = ' FROM pedidos p LEFT JOIN usuarios u ON u.id = p.usuario_id';
$donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
$q = $pdo->prepare("SELECT COUNT(*) AS total,
    COALESCE(SUM(CASE WHEN p.estado = 'confirmado' THEN 1 ELSE 0 END),0) AS confirmados,
    COALESCE(SUM(CASE WHEN p.estado = 'preparando' THEN 1 ELSE 0 END),0) AS preparando,
    COALESCE(SUM(CASE WHEN p.estado = 'entregado' THEN 1 ELSE 0 END),0) AS entregados,
    COALESCE(SUM(CASE WHEN p.estado = 'cancelado' THEN 1 ELSE 0 END),0) AS cancelados,
    COALESCE(SUM(CASE WHEN p.estado IN ('confirmado','preparando','entregado') THEN p.total ELSE 0 END),0) AS importe" . $base . $donde);
$q->execute($parametros); $resumen = $q->fetch();
if ($estado !== '') { $condiciones[] = 'p.estado = ?'; $parametros[] = $estado; }
$donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
$q = $pdo->prepare('SELECT COUNT(*)' . $base . $donde); $q->execute($parametros); $total = (int)$q->fetchColumn();
$paginas = max(1, (int)ceil($total / 25));
$pagina = filter_var($entrada['pagina'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$pagina = min($pagina, $paginas); $offset = ($pagina - 1) * 25;
// LIMIT y OFFSET provienen de enteros calculados; los filtros se parametrizan.
$q = $pdo->prepare('SELECT p.id, p.usuario_nombre, p.usuario_id, p.total, p.estado, p.fecha, u.nombre AS nombre_actual, u.correo' . $base . $donde . ' ORDER BY p.id DESC LIMIT 25 OFFSET ' . $offset);
$q->execute($parametros); $pedidos = $q->fetchAll();
function pdPaginaUrl(array $entrada, int $pagina): string {
    $entrada['pagina'] = (string)$pagina;
    return 'index.php?' . http_build_query(array_filter($entrada, static fn($v) => $v !== ''));
}
pdCabecera('Administración de pedidos');
?>
<div class="titulo"><div><p class="sobre">OPERACIÓN DE LA TIENDA</p><h1>Administración de pedidos</h1><p class="muted">Compras, preparación, entrega y cancelaciones en un solo lugar.</p></div></div>
<form class="filtros" method="get">
<label>Cliente, correo o número de pedido<input type="search" name="buscar" maxlength="120" placeholder="Nombre, correo o #12" value="<?= pdEsc($buscar) ?>"></label>
<label>Estado<select name="estado"><option value="">Todos los estados</option><?php foreach (pdEstados() as $valor => $etiqueta): ?><option value="<?= $valor ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= pdEsc($etiqueta) ?></option><?php endforeach; ?></select></label>
<label>Desde<input type="date" name="desde" value="<?= pdEsc($entrada['desde']) ?>"></label><label>Hasta<input type="date" name="hasta" value="<?= pdEsc($entrada['hasta']) ?>"></label>
<button class="boton" type="submit">Buscar pedidos</button><a href="index.php">Limpiar</a>
</form>
<section class="metricas" aria-label="Resumen de pedidos">
<?php foreach ([['Confirmados',$resumen['confirmados']],['En preparación',$resumen['preparando']],['Entregados',$resumen['entregados']],['Cancelados',$resumen['cancelados']]] as [$etiqueta,$cantidad]): ?>
<article class="tarjeta"><p><?= pdEsc($etiqueta) ?></p><strong><?= number_format((int)$cantidad) ?></strong><small><?= (int)$resumen['total'] > 0 ? number_format($cantidad * 100 / $resumen['total'],1) : '0.0' ?>% de los pedidos del resumen</small></article>
<?php endforeach; ?>
</section>
<p class="nota">Resumen según la búsqueda y las fechas, antes del filtro por estado. Importe de pedidos vigentes: <strong><?= pdDinero($resumen['importe']) ?></strong>. No equivale a pagos cobrados.</p>
<section class="panel"><div class="cabecera"><h2>Pedidos encontrados</h2><span class="muted"><?= number_format($total) ?> resultado(s)</span></div><div class="tabla"><table>
<thead><tr><th>Pedido</th><th>Cliente</th><th>Fecha</th><th>Estado</th><th class="numero">Total</th><th>Acción</th></tr></thead><tbody>
<?php foreach ($pedidos as $pedido): ?><tr><td>#<?= (int)$pedido['id'] ?></td><td><strong><?= pdEsc($pedido['usuario_nombre']) ?></strong><br><span class="muted"><?= pdEsc($pedido['correo'] ?? 'Cuenta no disponible') ?></span><?php if ($pedido['nombre_actual'] && $pedido['nombre_actual'] !== $pedido['usuario_nombre']): ?><br><small>Nombre actual: <?= pdEsc($pedido['nombre_actual']) ?></small><?php endif; ?></td><td><?= pdEsc($pedido['fecha']) ?></td><td><?= pdInsignia($pedido['estado']) ?></td><td class="numero"><?= pdDinero($pedido['total']) ?></td><td><a class="enlace-detalle" href="detalle.php?id=<?= (int)$pedido['id'] ?>">Ver pedido →</a></td></tr><?php endforeach; ?>
<?php if (!$pedidos): ?><tr><td colspan="6">No hay pedidos con esos filtros.</td></tr><?php endif; ?>
</tbody></table></div>
<nav class="paginacion" aria-label="Páginas de pedidos"><?php if ($pagina > 1): ?><a href="<?= pdEsc(pdPaginaUrl($entrada,$pagina-1)) ?>">← Anterior</a><?php endif; ?><span class="muted">Página <?= $pagina ?> de <?= $paginas ?> · Hasta 25 pedidos por página</span><?php if ($pagina < $paginas): ?><a href="<?= pdEsc(pdPaginaUrl($entrada,$pagina+1)) ?>">Siguiente →</a><?php endif; ?></nav>
</section>
<?php pdPie(); ?>
