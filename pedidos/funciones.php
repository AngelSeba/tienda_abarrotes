<?php
require_once __DIR__.'/../inventario/funciones.php';
function pdEsc($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function pdDinero($valor): string { return '$' . number_format((float)$valor, 2) . ' MXN'; }
function pdEstados(): array {
    return ['confirmado' => 'Confirmado', 'preparando' => 'En preparación', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado'];
}
function pdTransiciones(string $estado): array {
    return ['confirmado' => ['preparando', 'cancelado'], 'preparando' => ['entregado', 'cancelado']][$estado] ?? [];
}
function pdInsignia(string $estado): string {
    $clase = array_key_exists($estado, pdEstados()) ? $estado : 'desconocido';
    return '<span class="estado estado-' . $clase . '">' . pdEsc(pdEstados()[$estado] ?? $estado) . '</span>';
}
function pdFecha(string $valor): ?DateTimeImmutable {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $valor)) return null;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $fecha && $fecha->format('Y-m-d') === $valor && (int)$fecha->format('Y') >= 1000
        && (int)$fecha->format('Y') <= 9998 ? $fecha : null;
}
/** Llamar únicamente después de protegerAdministrador() y validarCsrf(). */
function pdCambiarEstado(PDO $pdo, int $id, string $nuevo, string $esperado, string $motivo): string {
    if ($id < 1 || !isset(pdEstados()[$nuevo]) || !isset(pdEstados()[$esperado])) {
        throw new RuntimeException('Pedido o estado no válido.');
    }
    $motivo = preg_replace('/\s+/u', ' ', trim($motivo));
    if (!is_string($motivo) || mb_strlen($motivo) > 140) {
        throw new RuntimeException('El motivo admite hasta 140 caracteres.');
    }
    if ($nuevo === 'cancelado' && mb_strlen($motivo) < 5) {
        throw new RuntimeException('Explica el motivo de cancelación (entre 5 y 140 caracteres).');
    }
    require_once __DIR__.'/../version17.php'; requerirVersion17($pdo);
    try {
        $pdo->beginTransaction();
        // Bloquea el pedido antes de consultar su estado: dos cancelaciones
        // concurrentes no pueden devolver dos veces el mismo inventario.
        $q = $pdo->prepare('SELECT id, estado FROM pedidos WHERE id = ? FOR UPDATE');
        $q->execute([$id]);
        $pedido = $q->fetch();
        if (!$pedido) throw new RuntimeException('El pedido no existe.');
        $actual = (string)$pedido['estado'];
        if ($actual === $nuevo) {
            $pdo->commit();
            return 'El pedido ya tiene ese estado. No se modificaron las existencias.';
        }
        if ($actual !== $esperado) {
            throw new RuntimeException('Otro administrador cambió este pedido. Recarga y revisa su estado actual.');
        }
        if (!in_array($nuevo, pdTransiciones($actual), true)) {
            throw new RuntimeException('Ese cambio de estado no está permitido. Los pedidos entregados o cancelados son definitivos.');
        }
        if ($nuevo === 'cancelado') {
            $q = $pdo->prepare('SELECT producto_id, SUM(cantidad) AS cantidad FROM pedido_detalles WHERE pedido_id = ? GROUP BY producto_id ORDER BY producto_id');
            $q->execute([$id]);
            $detalles = $q->fetchAll();
            if (!$detalles) throw new RuntimeException('El pedido no tiene detalles. Revísalo antes de cancelarlo.');
            $buscar = $pdo->prepare('SELECT id, stock, nombre FROM productos WHERE id = ? FOR UPDATE');
            $devolver = [];
            // Mismo orden ascendente de productos que utiliza comprar.php.
            foreach ($detalles as $detalle) {
                $cantidad = filter_var($detalle['cantidad'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
                if ($cantidad === false) throw new RuntimeException('Las cantidades del pedido no son válidas.');
                $buscar->execute([$detalle['producto_id']]);
                $producto = $buscar->fetch();
                if (!$producto) {
                    throw new RuntimeException('El producto #' . (int)$detalle['producto_id'] . ' ya no existe. No se canceló el pedido ni se devolvieron existencias. Revisa el caso antes de continuar.');
                }
                if ((int)$producto['stock'] < 0 || (int)$producto['stock'] > 2147483647 - $cantidad) {
                    throw new RuntimeException('No se puede devolver esa cantidad al inventario. Revisa las existencias.');
                }
                $devolver[] = [(int)$cantidad,(int)$producto['id'],(int)$producto['stock'],$producto['nombre']];
            }
            $q = $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?');
            foreach ($devolver as $item) { $q->execute([$item[0],$item[1]]); invMovimiento($pdo,['id'=>$item[1],'nombre'=>$item[3]],'cancelacion',$item[2],$item[2]+$item[0],$motivo,$id); }
            $q=$pdo->prepare("UPDATE correos_pendientes SET estado='cancelado' WHERE pedido_id=? AND estado<>'enviado'"); $q->execute([$id]);
        }
        $q = $pdo->prepare('UPDATE pedidos SET estado = ? WHERE id = ? AND estado = ?');
        $q->execute([$nuevo, $id, $actual]);
        if ($q->rowCount() !== 1) throw new RuntimeException('El pedido cambió mientras se actualizaba. Vuelve a consultarlo.');
        registrarBitacora($pdo, 'Pedido #' . $id . ': ' . $actual . ' -> ' . $nuevo . ($motivo !== '' ? '. Motivo: ' . $motivo : ''));
        $pdo->commit();
        return $nuevo === 'cancelado' ? 'Pedido cancelado. Las existencias se devolvieron una sola vez.' : 'Estado del pedido actualizado correctamente.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
function pdCabecera(string $titulo): void {
    ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= pdEsc($titulo) ?> | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/ventas.css"><link rel="stylesheet" href="../css/pedidos.css"><?= tiendaEstilos('../') ?></head><body>
<header class="barra"><a class="marca" href="../dashboard.php"><?= tiendaMarca('../') ?></a><div><span class="etiqueta">Administración</span><?= pdEsc($_SESSION['usuario']['nombre']) ?> <?= botonCerrarSesion('../logout.php') ?></div></header>
<main><nav aria-label="Navegación principal"><a href="../dashboard.php">← Panel principal</a><a href="index.php" aria-current="page">Pedidos</a><a href="../ventas.php">Reporte de ventas</a><a href="../configuracion/index.php">Configuración</a><a href="../productos/index.php">Productos</a><a href="../bitacora/index.php">Bitácora</a></nav>
<?php
}
function pdPie(): void {
    echo '<footer>Los estados describen la preparación y entrega. No acreditan un cobro ni realizan reembolsos bancarios.</footer></main></body></html>';
}
