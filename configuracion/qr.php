<?php
require_once __DIR__.'/../funciones.php';
protegerAdministrador();
require_once __DIR__.'/../qr/funciones.php';
$cfg=obtenerConfiguracionTienda($pdo);$error='';$guardado=$_SESSION['aviso_qr'] ?? '';unset($_SESSION['aviso_qr']);
$form=(string)$cfg['url_publica'];$version=(int)$cfg['version'];
$migrado=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracion_tienda' AND COLUMN_NAME='url_publica'")->fetchColumn();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    try {
        if (!$migrado) throw new RuntimeException('Primero importa migracion/01_qr_productos.sql en la base de datos de esta tienda.');
        $form=textoPost('url_publica');
        $v=filter_var(textoPost('version'),FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if ($v===false) throw new RuntimeException('Recarga la página antes de guardar.');
        $version=$v;qrGuardarBase($pdo,$form,$version);
        $_SESSION['aviso_qr']='Dirección guardada. Los QR del carrito y los tickets que descargues usarán esta dirección.';
        header('Location: qr.php',true,303);exit;
    } catch (Throwable $e) {
        error_log('Configuración QR: '.$e->getMessage());
        $error=($e instanceof RuntimeException && !($e instanceof PDOException)) ? $e->getMessage() : 'No se pudo guardar la dirección. Consulta el registro de PHP.';
    }
}
$ejemplo=$pdo->query('SELECT id FROM productos ORDER BY id LIMIT 1')->fetchColumn();
$url=$ejemplo ? qrUrlProducto($cfg,(int)$ejemplo) : '';
$imagen=qrImagen($url);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Códigos QR | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/ventas.css"><link rel="stylesheet" href="../css/configuracion.css"><?= tiendaEstilos('../') ?></head><body>
<header class="barra"><a class="marca" href="../dashboard.php"><?= tiendaMarca('../') ?></a><div>Administración <?= botonCerrarSesion('../logout.php') ?></div></header>
<main><nav><a href="../dashboard.php">← Panel principal</a><a href="index.php">Configuración</a><a href="qr.php" aria-current="page">Códigos QR</a></nav>
<div class="titulo"><div><p class="sobre">CATÁLOGO EN TU CELULAR</p><h1>Códigos QR de productos</h1><p class="muted">Cada QR abre la imagen, el precio y las existencias actuales de un producto, sin iniciar sesión.</p></div></div>
<?php if ($guardado): ?><p class="aviso mensaje-exito" role="status"><?= tcEsc($guardado) ?></p><?php endif; ?>
<?php if ($error): ?><p class="aviso mensaje-error" role="alert"><?= tcEsc($error) ?></p><?php endif; ?>
<?php if (!$migrado): ?><p class="aviso mensaje-error">Falta importar la migración 1.9: <strong>01_qr_productos.sql</strong>. La tienda puede seguir funcionando mientras la importas.</p><?php endif; ?>
<?php if (!qrDependenciasListas()): ?><p class="aviso mensaje-error">Para generar QR, activa GD y mbstring en el PHP de Apache y en el PHP de la tarea de correos. Comprueba que copiaste completa la carpeta lib/qr.</p><?php endif; ?>
<div class="config-grid"><form class="panel config-form" method="post">
<?= campoCsrf() ?><input type="hidden" name="version" value="<?= $version ?>">
<fieldset><legend>Dirección accesible de esta tienda</legend><label>URL de la tienda<input type="url" name="url_publica" maxlength="200" placeholder="http://192.168.1.20/tienda_refrescos" value="<?= tcEsc($form) ?>" autocomplete="off"></label>
<p class="nota">Ejemplo en Laragon/XAMPP: <strong>http://192.168.1.20/tienda_refrescos</strong>. Usa la IPv4 real de tu PC y la carpeta de esta tienda. Incluye el puerto si Apache usa otro, por ejemplo <strong>:8080</strong>. No agregues index.php ni productos/ficha.php.</p>
<p class="nota">Desde el celular, <strong>localhost y 127.0.0.1 apuntan al celular</strong>. Para una tienda local, conecta el celular a la misma Wi-Fi que la PC, mantén Apache y MySQL activos y prueba primero esta dirección en el navegador del celular. No necesitas abrir puertos en tu router.</p>
<p class="nota">Si publicas la tienda, usa su dirección HTTPS. Dejar el campo vacío desactiva los QR. Los tickets ya enviados conservan sus enlaces; si cambias la IP o la dirección, esos enlaces dejarán de funcionar. Conviene reservar una IP local para la PC.</p></fieldset>
<button class="boton" type="submit" <?= !$migrado ? 'disabled' : '' ?>>Guardar dirección</button></form>
<aside class="panel config-preview"><h2>Prueba de escaneo</h2>
<?php if ($imagen!==''): ?><img src="<?= tcEsc($imagen) ?>" width="180" height="180" alt="QR de prueba de un producto" style="background:#fff;max-width:100%;height:auto"><p><a class="boton" href="<?= tcEsc($url) ?>" target="_blank" rel="noopener noreferrer">Abrir ficha de prueba</a></p><p class="nota" style="overflow-wrap:anywhere"><?= tcEsc($url) ?></p><p class="nota">Escanea este QR con el celular antes de enviar nuevos tickets. Guardar la dirección no comprueba la conectividad de tu red.</p>
<?php else: ?><p>Guarda una dirección y agrega al menos un producto para ver el QR de prueba.</p><?php endif; ?>
<p class="nota">La ficha muestra únicamente información pública del producto. Los pedidos y los datos del cliente mantienen su acceso privado.</p></aside></div></main></body></html>
