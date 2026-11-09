<?php
require_once __DIR__.'/../conexion.php'; require_once __DIR__.'/../funciones.php'; require_once __DIR__.'/funciones.php';
protegerAdministrador();
require_once __DIR__.'/../version17.php'; requerirVersion17($pdo); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    try {
        $id=filter_var(textoPost('producto'),FILTER_VALIDATE_INT); $cantidad=filter_var(textoPost('cantidad'),FILTER_VALIDATE_INT);
        if (!$id || $cantidad===false) throw new RuntimeException('Revisa producto y cantidad entera.');
        $_SESSION['mensaje_inv']=invAplicar($pdo,$id,textoPost('tipo'),$cantidad,textoPost('motivo'),textoPost('operacion'));
        header('Location: index.php'); exit;
    } catch (Throwable $e) { http_response_code(422); $error=$e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'No se pudo guardar. No se modificaron existencias.'; error_log('Inventario: '.get_class($e)); }
}
$productos=$pdo->query('SELECT * FROM productos ORDER BY stock<=stock_minimo DESC,nombre,id')->fetchAll();
$solo=($_GET['bajos'] ?? '')==='1';
$movimientos=$pdo->query('SELECT * FROM movimientos_inventario ORDER BY id DESC LIMIT 200')->fetchAll();
$mensaje=$_SESSION['mensaje_inv'] ?? ''; unset($_SESSION['mensaje_inv']);
$_SESSION['inv_operaciones']=array_filter($_SESSION['inv_operaciones'] ?? [],static fn($t)=>$t>=time()-3600);
while(count($_SESSION['inv_operaciones'])>=30) array_shift($_SESSION['inv_operaciones']);
$op=bin2hex(random_bytes(32)); $_SESSION['inv_operaciones'][$op]=time();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Inventario | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/ventas.css"><link rel="stylesheet" href="../css/operacion.css"><?= tiendaEstilos('../') ?></head><body>
<header class="barra"><a class="marca" href="../dashboard.php"><?= tiendaMarca('../') ?></a><?= botonCerrarSesion('../logout.php') ?></header><main>
<nav><a href="../dashboard.php">← Panel principal</a><a href="../productos/index.php">Productos</a><a href="../correos/index.php">Correos pendientes</a><a href="?bajos=1">Solo stock bajo</a><a href="index.php">Todos</a></nav>
<div class="titulo"><div><p class="sobre">ADMINISTRACIÓN · 1.7</p><h1>Control de inventario</h1><p>Existencias, mínimos e historial desde la activación de esta versión.</p></div></div>
<?php if ($mensaje || $error): ?><p class="aviso" role="status"><?= tcEsc($error ?: $mensaje) ?></p><?php endif; ?>
<section class="panel"><h2>Registrar una operación</h2><form method="post" class="operacion-form"><?= campoCsrf() ?><input type="hidden" name="operacion" value="<?= $op ?>">
<label>Producto<select name="producto" required><?php foreach($productos as $p): ?><option value="<?= (int)$p['id'] ?>"><?= tcEsc($p['nombre']) ?> · stock <?= (int)$p['stock'] ?></option><?php endforeach; ?></select></label>
<label>Operación<select name="tipo"><option value="entrada">Entrada: sumar unidades</option><option value="ajuste">Ajuste: fijar stock final</option><option value="minimo">Cambiar mínimo de alerta</option></select></label>
<label>Cantidad / valor final<input type="number" name="cantidad" min="0" max="2147483647" required></label>
<label>Motivo<input name="motivo" minlength="5" maxlength="200" required></label><button class="boton" <?= $productos?'':'disabled' ?>>Registrar</button></form></section>
<section class="panel"><h2>Existencias</h2><div class="tabla-scroll"><table><thead><tr><th>Producto</th><th>Stock</th><th>Mínimo</th><th>Situación</th></tr></thead><tbody>
<?php foreach($productos as $p): if($solo && $p['stock']>$p['stock_minimo'])continue; ?><tr><td><?= tcEsc($p['nombre']) ?></td><td><?= (int)$p['stock'] ?></td><td><?= (int)$p['stock_minimo'] ?></td><td><?= $p['stock']==0?'Agotado':($p['stock']<=$p['stock_minimo']?'Stock bajo':'Disponible') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><h2>Últimos 200 movimientos</h2><div class="tabla-scroll"><table><thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cambio</th><th>Antes → después</th><th>Motivo</th><th>Responsable</th><th>Pedido</th></tr></thead><tbody>
<?php foreach($movimientos as $m): ?><tr><td><?= tcEsc($m['fecha']) ?></td><td><?= tcEsc($m['producto_nombre']) ?></td><td><?= tcEsc($m['tipo']) ?></td><td><?= (int)$m['cantidad'] ?></td><td><?= (int)$m['anterior'] ?> → <?= (int)$m['saldo'] ?></td><td><?= tcEsc($m['motivo']) ?></td><td><?= tcEsc($m['usuario_nombre']) ?></td><td><?= $m['pedido_id']>0?'#'.(int)$m['pedido_id']:'—' ?></td></tr><?php endforeach; ?></tbody></table></div><p class="nota">El saldo inicial refleja el stock al actualizar. Los movimientos anteriores no se reconstruyen. El respaldo SQL conserva el historial completo.</p></section></main></body></html>
