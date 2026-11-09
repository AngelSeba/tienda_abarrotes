<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
require_once __DIR__ . '/imagen_funciones.php';
protegerAdministrador();
require_once __DIR__.'/../version17.php'; requerirVersion17($pdo);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { header('Location: index.php'); exit; }
$q = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
$q->execute([$id]);
$producto = $q->fetch();
if (!$producto) { http_response_code(404); exit('Producto no encontrado.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $nombre = trim(textoPost('nombre'));

    $precio = trim(textoPost('precio'));
    $precioValido = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $precio) === 1 && (float)$precio > 0;
    if ($nombre === '' || mb_strlen($nombre) > 100 || !$precioValido) {
        $error = 'Revisa el nombre (hasta 100 caracteres), las existencias y el precio positivo con hasta dos decimales.';
    } else {
        $nuevaImagen = null;
        try {
            $nuevaImagen = guardarImagenProducto($_FILES['imagen'] ?? []);
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT imagen FROM productos WHERE id = ? FOR UPDATE');
            $q->execute([$id]);
            $vigente = $q->fetch();
            if (!$vigente) throw new RuntimeException('El producto ya no existe.');
            $q = $pdo->prepare('UPDATE productos SET nombre = ?, imagen = ?, precio = ?, fecha = CURDATE(), hora = CURTIME() WHERE id = ?');
            $q->execute([$nombre, $nuevaImagen ?? $vigente['imagen'], $precio, $id]);
            registrarBitacora($pdo, 'Actualizó producto: ' . $nombre);
            $pdo->commit();
            if ($nuevaImagen) borrarImagenProducto($vigente['imagen']);
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
<title>Editar producto | <?= tcEsc(tiendaNombre()) ?></title>
<link rel="stylesheet" href="../css/estilo.css">
<?= tiendaEstilos('../') ?></head>
<body>
<div class="form-page">
    <div class="form-card">
        <a class="back" href="index.php">← Volver</a>
        <h1>Editar producto</h1>

        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
<?= campoCsrf() ?>
            <label>Nombre del producto</label>
            <input type="text" name="nombre" maxlength="100" required value="<?= htmlspecialchars($producto['nombre']) ?>">

            <label>Precio (MXN)</label>
            <input type="number" name="precio" min="0.01" max="99999999.99" step="0.01" required value="<?= htmlspecialchars((string)($_POST['precio'] ?? $producto['precio'])) ?>">

            <p>Stock actual: <?= (int)$producto['stock'] ?>. <a href="../inventario/index.php">Registrar entrada o ajuste de inventario</a>.</p>

            <label>Nueva imagen (opcional)</label>
            <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.gif,.webp">

            <?php if ($producto['imagen'] && file_exists(__DIR__ . '/imagenes/' . $producto['imagen'])): ?>
                <img class="preview" src="imagenes/<?= htmlspecialchars($producto['imagen']) ?>" alt="Imagen actual">
            <?php endif; ?>

            <button class="btn" type="submit">Guardar cambios</button>
        </form>
    </div>
</div>
</body>
</html>
