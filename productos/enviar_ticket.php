<?php
require_once __DIR__ . '/correo_compra.php';

function enviarTicketPedido(PDO $pdo, int $pedidoId, string $correoCliente, string $nombreCliente, int $usuarioId): bool
{
    enviarCorreoPedido($pdo, $pedidoId, $nombreCliente, $correoCliente, $usuarioId);
    return true;
}
