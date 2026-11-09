<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
protegerAdministrador();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if (!$id || $id<1) { header('Location: index.php'); exit; }
$q=$pdo->prepare('SELECT id,nombre,correo,rol FROM usuarios WHERE id=?'); $q->execute([$id]); $usuario=$q->fetch();
if (!$usuario) { http_response_code(404); exit('Usuario no encontrado.'); }
$error=''; $propia=(int)$id===(int)$_SESSION['usuario']['id'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    $nombre=trim(textoPost('nombre')); $correo=trim(textoPost('correo')); $password=textoPost('password'); $rol=textoPost('rol');
    if ($nombre==='' || mb_strlen($nombre)>100 || strlen($correo)>150 || !filter_var($correo,FILTER_VALIDATE_EMAIL) || !in_array($rol,['usuario','administrador'],true)) {
        $error='Completa correctamente los campos.';
    } elseif ($propia && $password!=='') {
        $error='Para cambiar tu propia contraseña utiliza Mi cuenta y confirma tu contraseña actual.';
    } elseif ($password!=='' && !authClaveNuevaValida($password)) {
        $error='La nueva contraseña necesita al menos 15 caracteres y un máximo de 72 bytes.';
    } elseif ($propia && $rol!=='administrador') {
        $error='No puedes quitarte tu propio acceso de administrador.';
    } else {
        try {
            $pdo->beginTransaction();
            $q=$pdo->prepare('SELECT id,rol FROM usuarios WHERE id=? FOR UPDATE'); $q->execute([$id]); $vigente=$q->fetch();
            if (!$vigente) throw new RuntimeException('El usuario ya no existe.');
            $revocar=$password!=='' || $vigente['rol']!==$rol;
            if ($password!=='') {
                $q=$pdo->prepare('UPDATE usuarios SET nombre=?,correo=?,password=?,rol=?,sesion_version=sesion_version+1 WHERE id=?');
                $q->execute([$nombre,$correo,authHash($password),$rol,$id]);
            } else {
                $q=$pdo->prepare('UPDATE usuarios SET nombre=?,correo=?,rol=?,sesion_version=sesion_version+? WHERE id=?');
                $q->execute([$nombre,$correo,$rol,$revocar?1:0,$id]);
            }
            registrarBitacora($pdo,'Actualizó usuario: '.$correo.($revocar?' y revocó sus sesiones.':''));
            $pdo->commit();
            if ($propia) {
                $_SESSION['usuario']['nombre']=$nombre; $_SESSION['usuario']['correo']=$correo;
            }
            $_SESSION['mensaje']='Usuario actualizado correctamente.'.($revocar?' Se cerrarán sus sesiones anteriores.':'');
            header('Location: index.php',true,303); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Editar usuario: '.$e->getMessage());
            $error=$e instanceof PDOException && $e->getCode()==='23000' ? 'Ese correo ya está registrado.' : 'No se pudo actualizar el usuario.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Editar usuario | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/estilo.css"><?= tiendaEstilos('../') ?></head>
<body>
<div class="form-page">
<div class="form-card">
<a class="back" href="index.php">← Volver</a>
<h1>Editar usuario</h1>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST">
<?= campoCsrf() ?>
<label>Nombre</label>
<input type="text" name="nombre" required maxlength="100" value="<?= htmlspecialchars($usuario['nombre']) ?>">
<label>Correo</label>
<input type="email" name="correo" required maxlength="150" value="<?= htmlspecialchars($usuario['correo']) ?>">
<?php if ($propia): ?>
<p><a class="back" href="../cuenta/index.php">Cambiar mi contraseña desde Mi cuenta</a></p>
<?php else: ?>
<label>Nueva contraseña</label>
<input type="password" name="password" minlength="15" maxlength="72" autocomplete="new-password" placeholder="Dejar vacío para conservar">
<p class="nota">Si cambias la contraseña o el rol, se revocan las sesiones de este usuario.</p>
<?php endif; ?>
<label>Rol</label>
<select name="rol">
<option value="usuario" <?= $usuario['rol']==='usuario'?'selected':'' ?>>Usuario</option>
<option value="administrador" <?= $usuario['rol']==='administrador'?'selected':'' ?>>Administrador</option>
</select>
<button class="btn" type="submit">Guardar cambios</button>
</form>
</div>
</div>
</body>
</html>
