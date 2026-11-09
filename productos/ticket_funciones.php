<?php
require_once __DIR__ . '/../tienda_funciones.php';
require_once __DIR__.'/ticket_cache.php';
/** Funciones compartidas por la descarga y el correo del pedido. */
function obtenerPedidoConDetalles(PDO $pdo, int $id, int $usuarioId): array
{
    $q = $pdo->prepare('SELECT id, usuario_nombre, total, estado, fecha FROM pedidos WHERE id = ? AND usuario_id = ?');
    $q->execute([$id, $usuarioId]);
    $pedido = $q->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        throw new RuntimeException('Pedido no encontrado.');
    }
    $q = $pdo->prepare('SELECT d.producto_id, d.producto_nombre, d.cantidad, d.precio_unitario, d.subtotal, p.imagen FROM pedido_detalles d LEFT JOIN productos p ON p.id = d.producto_id WHERE d.pedido_id = ? ORDER BY d.producto_nombre');
    $q->execute([$id]);
    return [$pedido, $q->fetchAll(PDO::FETCH_ASSOC)];
}

function generarTicketPdf(array $pedido, array $detalles, array $tienda): string
{
    if (!class_exists(\Dompdf\Dompdf::class)) {
        throw new RuntimeException('Falta instalar Dompdf con Composer.');
    }
    $esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $money = static fn($n) => number_format((float)$n, 2, '.', ',');
    $logoRuta=ticketRutaImagen('assets/marca',$tienda['logo'] ?? null);
    $logoUri=$logoRuta?ticketImagenCompacta($logoRuta,280,200):'';
    $logoHtml=$logoUri?'<img style="max-width:90px;max-height:65px;margin-bottom:10px" src="'.$logoUri.'" alt="">':'';
    $contactos=array_filter([$tienda['direccion'],$tienda['telefono'],$tienda['correo']],static fn($v)=>$v!=='');
    $contactoHtml=$contactos ? '<div class="sub">'.implode(' · ',array_map($esc,$contactos)).'</div>' : '';
    $filas = '';
    $conQr=qrUrlProducto($tienda,1)!=='' && qrDependenciasListas();
    foreach ($detalles as $d) {
        $imagenHtml = '';
        $ruta=ticketRutaImagen('productos/imagenes',$d['imagen'] ?? null);
        $uri=$ruta?ticketImagenCompacta($ruta):'';
        if ($uri!=='') $imagenHtml='<img class="thumb" src="'.$uri.'" alt="">';
        $qrCelda='';
        if ($conQr) {
            $url=qrUrlProducto($tienda,(int)($d['producto_id'] ?? 0));$png=qrImagen($url);
            $qrCelda='<td class="qr">'.($png!=='' ? '<a href="'.$esc($url).'"><img src="'.$png.'" width="120" height="120" alt="QR del producto"></a><div>Ver ficha actual</div>' : 'Ficha no disponible').'</td>';
        }
        $filas .= '<tr><td>' . $imagenHtml . '</td><td>' . $esc($d['producto_nombre']) . '</td><td class="num">' . (int)$d['cantidad']
            . '</td><td class="num">$' . $money($d['precio_unitario']) . '</td><td class="num">$' . $money($d['subtotal']) . '</td>'.$qrCelda.'</tr>';
    }
    $avisoEstado = ($pedido['estado'] ?? '') === 'cancelado'
        ? '<p style="color:#9c233c;border:1px solid #9c233c;padding:10px;font-weight:bold">PEDIDO CANCELADO · Documento histórico. No acredita pago ni reembolso.</p>' : '';
    $colorPdf=tcColorPapel($tienda['color_principal']);
    $html = '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>
        body{font-family:DejaVu Sans,sans-serif;color:#1c2733;font-size:11px;margin:24px}
        h1{font-size:20px;color:' . $colorPdf . ';margin:0 0 6px}h2{font-size:13px;margin-top:25px}
        .sub{color:#627080}.top{border-bottom:2px solid ' . $colorPdf . ';padding-bottom:15px}
        table{table-layout:fixed;border-collapse:collapse;width:100%;margin-top:12px}th{background:#e7f3ef;text-align:left}
        td,th{word-wrap:break-word;border-bottom:1px solid #dce4e8;padding:9px 6px;vertical-align:middle}.num{text-align:right}.thumb{width:55px;height:55px;object-fit:contain}
        .qr{text-align:center;width:124px;font-size:9px}.qr img{display:block;margin:0 auto}tr{page-break-inside:avoid}thead{display:table-header-group}.total{text-align:right;font-size:17px;font-weight:bold;margin-top:20px}
        .nota{font-size:9px;color:#627080;margin-top:40px}</style></head><body>'
        . '<div class="top">' . $logoHtml . '<h1>' . $esc($tienda['nombre']) . '</h1>' . $contactoHtml . '<div class="sub">Comprobante de pedido · No es factura fiscal</div></div>'
        . $avisoEstado
        . '<h2>Pedido #' . (int)$pedido['id'] . '</h2>'
        . '<p><strong>Cliente:</strong> ' . $esc($pedido['usuario_nombre']) . '<br>'
        . '<strong>Fecha:</strong> ' . $esc($pedido['fecha']) . '<br>'
        . '<strong>Estado:</strong> ' . $esc($pedido['estado']) . '</p>'
        . '<table><thead><tr><th style="width:55px">Imagen</th><th>Producto</th><th class="num" style="width:55px">Cantidad</th><th class="num" style="width:80px">Precio unitario</th><th class="num" style="width:75px">Subtotal</th>'.($conQr ? '<th class="qr">Ficha QR</th>' : '').'</tr></thead><tbody>'
        . $filas . '</tbody></table>'
        . '<div class="total">Total: $' . $money($pedido['total']) . ' MXN</div>'
        . ($conQr ? '<p class="nota">Escanea el QR para consultar la imagen, el precio y el stock actuales. Los importes de este ticket corresponden a la compra. La ficha puede cambiar o dejar de estar disponible si se retira el producto.</p>' : '')
        . '<p class="nota">Este documento confirma el registro del pedido. No acredita el procesamiento de un pago electrónico.</p>'
        . '</body></html>';
    $pdf = new \Dompdf\Dompdf(['isRemoteEnabled'=>false,'isJavascriptEnabled'=>false,'isFontSubsettingEnabled'=>true]);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4');
    $pdf->render();
    return $pdf->output();
}
