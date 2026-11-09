<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
require_once __DIR__ . '/imagen_funciones.php';
require_once __DIR__.'/../inventario/funciones.php';
protegerAdministrador();
require_once __DIR__.'/../version17.php'; requerirVersion17($pdo);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $nombre = trim(textoPost('nombre'));
    $stock = filter_var(textoPost('stock'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
    $precio = trim(textoPost('precio'));
    $precioValido = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $precio) === 1 && (float)$precio > 0;
    if ($nombre === '' || mb_strlen($nombre) > 100 || $stock === false || !$precioValido) {
        $error = 'Revisa el nombre (hasta 100 caracteres), las existencias y el precio positivo con hasta dos decimales.';
    } else {
        $nuevaImagen = null;
        try {
            $nuevaImagen = guardarImagenProducto($_FILES['imagen'] ?? []);
            $pdo->beginTransaction();
            $q = $pdo->prepare('INSERT INTO productos (nombre, stock, imagen, precio, fecha, hora) VALUES (?, ?, ?, ?, CURDATE(), CURTIME())');
            $q->execute([$nombre, $stock, $nuevaImagen, $precio]);
            $nuevoId=(int)$pdo->lastInsertId();
            invMovimiento($pdo,['id'=>$nuevoId,'nombre'=>$nombre],'inicial',0,$stock,'Alta de producto',0);
            registrarBitacora($pdo, 'Agregó producto: ' . $nombre);
            $pdo->commit();
            $_SESSION['mensaje'] = 'Producto guardado correctamente.';
            header('Location: index.php'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // Una imagen nueva solo se elimina si no quedó asociada al producto.
            // Si hubo un error antes de abrir la transacción, también se retira.
            if ($nuevaImagen) {
                try {
                    $q = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE imagen = ?');
                    $q->execute([$nuevaImagen]);
                    if (!(int)$q->fetchColumn()) borrarImagenProducto($nuevaImagen);
                } catch (Throwable $errorLimpieza) {
                    error_log('No se pudo verificar la limpieza de imagen.');
                }
            }
            error_log('Producto: ' . $e->getMessage());
            $error = ($e instanceof RuntimeException && !($e instanceof PDOException)) ? $e->getMessage() : 'No se pudo guardar el producto.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agregar producto | <?= tcEsc(tiendaNombre()) ?></title>
<link rel="stylesheet" href="../css/estilo.css">
<?= tiendaEstilos('../') ?></head>
<body>
<div class="form-page">
    <div class="form-card">
        <a class="back" href="index.php">← Volver</a>
        <h1>Agregar producto</h1>
        <p>Registra un producto en el catálogo.</p>

        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
<?= campoCsrf() ?>
            <label>Nombre del producto</label>
            <input type="text" name="nombre" maxlength="100" required value="<?= htmlspecialchars(textoPost('nombre')) ?>">

            <label>Precio (MXN)</label>
            <input type="number" name="precio" min="0.01" max="99999999.99" step="0.01" required value="<?= htmlspecialchars((string)($_POST['precio'] ?? '')) ?>">

            <label>Stock</label>
            <input type="number" name="stock" min="0" required value="<?= htmlspecialchars(textoPost('stock') ?: '0') ?>">

            <label>Imagen</label>
            <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.gif,.webp">

            <button class="btn" type="submit">Guardar producto</button>
        </form>
    </div>
</div>
</body>
</html>
