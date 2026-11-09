<?php
require_once __DIR__.'/../conexion.php';
require_once __DIR__.'/../tienda_funciones.php';
require_once __DIR__.'/../qr/funciones.php';
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
header('Content-Type: text/html; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD']!=='GET' && $_SERVER['REQUEST_METHOD']!=='HEAD') { header('Allow: GET, HEAD'); http_response_code(405); exit; }
$raw=$_GET['id'] ?? null;
$id=is_string($raw) ? filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]) : false;
$producto=false;
if ($id!==false) {
    $q=$pdo->prepare('SELECT id,nombre,precio,stock,imagen FROM productos WHERE id=?');$q->execute([$id]);$producto=$q->fetch(PDO::FETCH_ASSOC);
}
if (!$producto) http_response_code(404);
$tienda=obtenerConfiguracionTienda($pdo);
$imagen=$producto ? qrRutaImagenProducto($producto['imagen']) : '';
$color=tcColorPapel($tienda['color_principal']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title><?= tcEsc($producto ? $producto['nombre'] : 'Producto no disponible') ?> | <?= tcEsc($tienda['nombre']) ?></title>
<style>*{box-sizing:border-box}body{margin:0;background:#f1f5f3;color:#142d24;font-family:system-ui,sans-serif;line-height:1.5}header{padding:22px;color:white;background:<?= tcEsc($color) ?>;text-align:center;font-weight:700}main{max-width:520px;margin:28px auto;padding:0 18px}.card{background:white;border:1px solid #dce5df;border-radius:20px;padding:24px;box-shadow:0 12px 36px #142d2410}.foto{width:100%;height:250px;object-fit:contain}.sin-foto{height:180px;display:grid;place-items:center;background:#f1f5f3;border-radius:12px;color:#60736a}h1{font-size:26px;line-height:1.2;margin:24px 0 18px;overflow-wrap:anywhere}.dato{display:flex;justify-content:space-between;align-items:center;gap:12px;border-top:1px solid #e2e9e5;padding:16px 0}.precio{font-size:28px;font-weight:750;color:<?= tcEsc($color) ?>}.badge{padding:5px 12px;border-radius:30px;background:#e4f4e9;font-weight:700}.agotado{background:#fce8e6;color:#9f2921}.nota,footer{font-size:13px;color:#5d7066}footer{text-align:center;padding:20px}</style></head><body>
<header><?= tcEsc($tienda['nombre']) ?></header><main><article class="card">
<?php if (!$producto): ?><h1>Producto no disponible</h1><p>Este producto ya no está en el catálogo o el enlace no es válido.</p>
<?php else: ?>
<?php if ($imagen!==''): ?><img class="foto" src="<?= tcEsc($imagen) ?>" alt="<?= tcEsc($producto['nombre']) ?>"><?php else: ?><div class="sin-foto">Imagen no disponible</div><?php endif; ?>
<h1><?= tcEsc($producto['nombre']) ?></h1>
<div class="dato"><span>Precio actual</span><strong class="precio">$<?= number_format((float)$producto['precio'],2) ?> <small>MXN</small></strong></div>
<div class="dato"><span>Stock actual</span><strong class="badge <?= (int)$producto['stock']<1 ? 'agotado' : '' ?>"><?= (int)$producto['stock']<1 ? 'Agotado' : number_format((int)$producto['stock']).' unidades' ?></strong></div>
<p class="nota">Información consultada al abrir esta ficha. Recarga para ver cambios en precio o existencias. El ticket conserva el precio de tu compra.</p>
<?php endif; ?></article><footer>Ficha de producto · <?= tcEsc($tienda['nombre']) ?></footer></main></body></html>
