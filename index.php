<?php
if (!is_file(__DIR__.'/config_database.php') && !is_file(__DIR__.'/instalado.lock')) {
    header('Location: install/'); exit;
}
require_once __DIR__ . '/funciones.php';

if (isset($_SESSION['usuario'])) {
    protegerSesion();
    header('Location: dashboard.php');
    exit;
}

$aviso = $_SESSION['aviso_acceso'] ?? '';
unset($_SESSION['aviso_acceso']);
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | <?= tcEsc(tiendaNombre()) ?></title>
    <link rel="stylesheet" href="css/login.css">
<?= tiendaEstilos('') ?></head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="logo"><?= tiendaLogoHtml() ?></div>
        <h1><?= tcEsc(tiendaNombre()) ?></h1>
        <p class="subtitle">Inicia sesión para continuar</p>

        <?php if ($aviso && !$error): ?><div class="aviso-acceso" role="status"><?= tcEsc($aviso) ?></div><?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <?= campoCsrf() ?>
            <label for="correo">Correo</label>
            <input type="email" id="correo" name="correo" autocomplete="username" maxlength="150" required placeholder="correo@ejemplo.com">

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" autocomplete="current-password" maxlength="4096" required placeholder="••••••••">

            <button type="submit">Iniciar sesión</button>
        </form>
        <?php $contacto=tiendaActual(); ?>
        <div class="contacto-negocio">
            <?php foreach (['direccion','telefono','correo'] as $campo): if ($contacto[$campo] !== ''): ?>
            <span><?= tcEsc($contacto[$campo]) ?></span>
            <?php endif; endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
