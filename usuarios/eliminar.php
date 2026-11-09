<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../funciones.php';
protegerAdministrador();
validarCsrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1 || $id === (int)$_SESSION['usuario']['id']) {
    $_SESSION['mensaje'] = 'No puedes eliminar tu propia cuenta o una cuenta no válida.';
    header('Location: index.php'); exit;
}
try {
    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT correo FROM usuarios WHERE id = ? FOR UPDATE');
    $q->execute([$id]);
    $usuario = $q->fetch();
    if ($usuario) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE usuario_id = ?');
        $q->execute([$id]);
        if ((int)$q->fetchColumn() > 0) throw new RuntimeException('Este usuario tiene pedidos. Se conserva su cuenta para mantener el historial.');
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
        registrarBitacora($pdo, 'Eliminó usuario: ' . $usuario['correo']);
    }
    $pdo->commit();
    $_SESSION['mensaje'] = $usuario ? 'Usuario eliminado correctamente.' : 'El usuario ya no existe.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Eliminar usuario: ' . $e->getMessage());
    $_SESSION['mensaje'] = ($e instanceof RuntimeException && !($e instanceof PDOException)) ? $e->getMessage() : 'No se pudo eliminar el usuario; puede tener pedidos asociados.';
}
header('Location: index.php'); exit;
