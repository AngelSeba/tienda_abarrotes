<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
protegerAdministrador();

$usuarios = $pdo->query("SELECT id, nombre, correo, rol, creado_en FROM usuarios ORDER BY id DESC")->fetchAll();
$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuarios | <?= tcEsc(tiendaNombre()) ?></title>
<link rel="stylesheet" href="../css/estilo.css">
<?= tiendaEstilos('../') ?></head>
<body>
<header class="topbar">
    <div class="brand"><?= tiendaMarca('../') ?></div>
    <div class="user-info"><?= htmlspecialchars($_SESSION['usuario']['nombre']) ?> <?= botonCerrarSesion('../logout.php') ?></div>
</header>
<div class="layout">
<aside class="sidebar">
    <a href="../dashboard.php">🏠 Inicio</a>
    <a href="../pedidos/index.php">📦 Pedidos</a>
    <a href="../configuracion/index.php">⚙️ Configuración</a>
    <a href="../productos/index.php">🛍️ Productos</a>
    <a class="active" href="index.php">👥 Usuarios</a>
    <a href="../bitacora/index.php">📋 Bitácora</a>
</aside>
<main class="content">
    <div class="page-header">
        <div><h1>Usuarios</h1><p>Administración de usuarios y roles.</p></div>
        <a class="btn" href="crear.php">+ Agregar usuario</a>
    </div>

    <?php if ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <div class="table-container">
    <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Rol</th><th>Creado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['nombre']) ?></td>
            <td><?= htmlspecialchars($u['correo']) ?></td>
            <td><span class="role-badge <?= $u['rol'] === 'administrador' ? 'admin' : '' ?>"><?= htmlspecialchars($u['rol']) ?></span></td>
            <td><?= htmlspecialchars($u['creado_en']) ?></td>
            <td class="actions">
                <a class="btn small" href="editar.php?id=<?= $u['id'] ?>">Editar</a>
                <?php if ((int)$u['id'] !== (int)$_SESSION['usuario']['id']): ?>
                <form action="eliminar.php" method="POST" onsubmit="return confirm('¿Eliminar este usuario?');">
                        <?= campoCsrf() ?>
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button class="btn danger small" type="submit">Eliminar</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</main>
</div>
</body>
</html>
