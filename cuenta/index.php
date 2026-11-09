<?php
require_once __DIR__ . '/../funciones.php';
protegerSesion();
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    $actual=textoPost('actual'); $nueva=textoPost('nueva'); $confirmacion=textoPost('confirmacion');
    try {
        if ($nueva!==$confirmacion) throw new RuntimeException('La nueva contraseña y su confirmación no coinciden.');
        $resultado=authCambiarClave($pdo,(int)$_SESSION['usuario']['id'],(int)$_SESSION['sesion_version'],$actual,$nueva);
        if ($resultado['ok']) authSalir('clave_cambiada');
        if (!empty($resultado['revocada'])) authSalir('revocada');
        if (($resultado['espera'] ?? 0)>0) {
            http_response_code(429);header('Retry-After: '.(int)$resultado['espera']);
            $error='Se acumularon intentos incorrectos. Espera unos minutos antes de cambiar la contraseña.';
        } else $error='La contraseña actual no es correcta.';
    } catch (Throwable $e) {
        error_log('Cambio de contraseña: '.$e->getMessage());
        $error=$e instanceof RuntimeException && !($e instanceof PDOException)
            ? $e->getMessage() : 'No fue posible confirmar el cambio. Vuelve a iniciar sesión y revisa tu cuenta.';
    }
    unset($actual,$nueva,$confirmacion);
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mi cuenta | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/ventas.css"><?= tiendaEstilos('../') ?></head><body>
<header class="barra"><a class="marca" href="../dashboard.php"><?= tiendaMarca('../') ?></a><div><?= tcEsc($_SESSION['usuario']['nombre']) ?> <?= botonCerrarSesion('../logout.php') ?></div></header>
<main><nav><a href="../dashboard.php">← Panel principal</a><a href="../productos/index.php">Productos</a><?php if (!esAdministrador()): ?><a href="../productos/mis_compras.php">Mis compras</a><?php endif; ?></nav>
<div class="titulo"><div><p class="sobre">ACCESO PERSONAL</p><h1>Mi cuenta</h1><p class="muted">Actualiza tu contraseña y protege el acceso a tu cuenta.</p></div></div>
<?php if ($error): ?><p class="aviso cuenta-error" role="alert"><?= tcEsc($error) ?></p><?php endif; ?>
<div class="cuenta-grid"><section class="panel"><h2>Cambiar contraseña</h2><p class="muted">Necesitas la contraseña actual. Al guardar, se cerrarán todas tus sesiones, incluida esta.</p>
<form method="post" class="clave-form"><?= campoCsrf() ?>
<label>Contraseña actual<input type="password" name="actual" autocomplete="current-password" maxlength="4096" required></label>
<label>Nueva contraseña<input type="password" name="nueva" autocomplete="new-password" minlength="15" maxlength="72" required></label>
<label>Confirmar nueva contraseña<input type="password" name="confirmacion" autocomplete="new-password" minlength="15" maxlength="72" required></label>
<p class="nota">Usa una frase de al menos 15 caracteres. No es obligatorio combinar mayúsculas, números o símbolos. Máximo 72 bytes; con muchos acentos o emojis, el límite se alcanza antes.</p>
<button type="submit" class="boton">Cambiar contraseña y cerrar sesiones</button></form></section>
<aside class="panel"><h2>Datos de tu cuenta</h2><dl class="cuenta-datos"><dt>Nombre</dt><dd><?= tcEsc($_SESSION['usuario']['nombre']) ?></dd><dt>Correo</dt><dd><?= tcEsc($_SESSION['usuario']['correo']) ?></dd><dt>Rol</dt><dd><?= esAdministrador() ? 'Administrador' : 'Cliente' ?></dd></dl><p class="nota">La sesión termina después de 30 minutos sin navegación ni solicitudes autenticadas. Antes aparecerá un aviso para continuarla. Los cambios de contraseña bloquean el uso de las sesiones anteriores.</p></aside></div></main></body></html>
