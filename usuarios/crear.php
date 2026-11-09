<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
protegerAdministrador();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $nombre = trim(textoPost('nombre'));
    $correo = trim(textoPost('correo'));
    $password = textoPost('password');
    $rol = textoPost('rol');

    if ($nombre === '' || mb_strlen($nombre) > 100 || strlen($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL) || !authClaveNuevaValida($password)) {
        $error = 'Completa correctamente los campos. La contraseña debe tener al menos 15 caracteres y un máximo de 72 bytes.';
    } elseif (!in_array($rol, ['usuario','administrador'], true)) {
        $error = 'Rol no válido.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (nombre, correo, password, rol) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$nombre, $correo, authHash($password), $rol]);

            registrarBitacora($pdo, "Creó usuario: $correo");
            $pdo->commit();
            $_SESSION['mensaje'] = 'Usuario creado correctamente.';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Crear usuario: '.$e->getMessage());
            $error = $e->getCode() === '23000' ? 'Ese correo ya está registrado.' : 'No se pudo crear el usuario.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Crear usuario | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/estilo.css"><?= tiendaEstilos('../') ?></head>
<body>
<div class="form-page">
<div class="form-card">
<a class="back" href="index.php">← Volver</a>
<h1>Agregar usuario</h1>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST">
<?= campoCsrf() ?>
<label>Nombre</label>
<input type="text" name="nombre" required maxlength="100">
<label>Correo</label>
<input type="email" name="correo" required maxlength="150">
<label>Contraseña</label>
<input type="password" name="password" required minlength="15" maxlength="72" autocomplete="new-password">
<label>Rol</label>
<select name="rol"><option value="usuario">Usuario</option><option value="administrador">Administrador</option></select>
<button class="btn" type="submit">Crear usuario</button>
</form>
</div>
</div>
</body>
</html>
