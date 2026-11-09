<?php
require_once __DIR__ . '/ticket_funciones.php';

/** Se llama únicamente DESPUÉS del commit exitoso. */
function prepararCorreoPedido(PDO $pdo, int $pedidoId, string $nombre, string $correo, int $usuarioId): \PHPMailer\PHPMailer\PHPMailer
{
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El usuario no tiene un correo válido en su sesión.');
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('No está instalado vendor/autoload.php.');
    }
    require_once $autoload;
    $configFile = dirname(__DIR__) . '/config_correo.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Falta el archivo config_correo.php.');
    }
    $config = require $configFile;
    foreach (['host', 'puerto', 'usuario', 'clave', 'remitente'] as $campo) {
        if (empty($config[$campo])) throw new RuntimeException('SMTP no configurado: ' . $campo);
    }
    [$pedido, $detalles] = obtenerPedidoConDetalles($pdo, $pedidoId, $usuarioId);
    $tienda=obtenerConfiguracionTienda($pdo);
    $pdf = obtenerTicketPdfCache($pdo,$pedido,$detalles,$tienda);
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Timeout=15; $mail->getSMTPInstance()->Timelimit=30;
    $mail->SMTPDebug=0;
    $mail->Username = $config['usuario'];
    $mail->Password = $config['clave'];
    $mail->Port = (int)$config['puerto'];
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['remitente'], $tienda['nombre']);
    if ($tienda['correo'] !== '') $mail->addReplyTo($tienda['correo'], $tienda['nombre']);
    $mail->addAddress($correo, $nombre);
    $logo=null; $rutaLogo=ticketRutaImagen('assets/marca',$tienda['logo'] ?? null);
    $logoUri=$rutaLogo?ticketImagenCompacta($rutaLogo,280,200):'';
    if ($logoUri!=='') $logo=['bytes'=>base64_decode(substr($logoUri,strpos($logoUri,',')+1)),'mime'=>'image/jpeg','nombre'=>'logo.jpg'];
    $contenido=tcContenidoCorreo($tienda,$pedido,$nombre,(bool)$logo);
    $mail->Subject=$contenido['asunto'];
    $mail->isHTML(true);
    if ($logo) $mail->addStringEmbeddedImage($logo['bytes'],'logo-negocio',$logo['nombre'],'base64',$logo['mime']);
    $mail->Body=$contenido['html'];
    $mail->AltBody=$contenido['texto'];
    $mail->addStringAttachment($pdf, 'ticket_pedido_' . $pedidoId . '.pdf', 'base64', 'application/pdf');
    return $mail;
}

/** Único punto que transmite: se llama después de guardar la compra. */
function enviarCorreoPedido(PDO $pdo, int $pedidoId, string $nombre, string $correo, int $usuarioId): void
{
    prepararCorreoPedido($pdo,$pedidoId,$nombre,$correo,$usuarioId)->send();
}
