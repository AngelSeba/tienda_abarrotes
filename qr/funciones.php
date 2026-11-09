<?php
/** QR local: contiene sólo la URL pública del producto, nunca datos del pedido. */
function qrValidarBase(string $entrada): string {
    $url=rtrim(trim($entrada),'/');
    if ($url==='') return '';
    if (strlen($url)>200 || preg_match('/[\\\\\s\x00-\x20\x7f]/',$url) || !filter_var($url,FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Escribe una dirección completa http:// o https://, de hasta 200 caracteres y sin espacios.');
    }
    $p=parse_url($url);
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''),['http','https'],true) || empty($p['host'])
        || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
        throw new RuntimeException('La dirección debe comenzar con http:// o https:// y no llevar usuario, contraseña, consultas ni fragmentos.');
    }
    $ruta=rawurldecode($p['path'] ?? '');
    if (preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\\\\x00-\x20\x7f]~',$ruta)) throw new RuntimeException('Revisa la carpeta de la tienda en la dirección.');
    return $url;
}
function qrUrlProducto(array $tienda,int $productoId): string {
    if ($productoId<1) return '';
    try { $base=qrValidarBase((string)($tienda['url_publica'] ?? '')); }
    catch (RuntimeException $e) { return ''; }
    return $base==='' ? '' : $base.'/productos/ficha.php?id='.$productoId;
}
function qrDependenciasListas(): bool {
    return function_exists('imagepng') && extension_loaded('mbstring')
        && is_file(__DIR__.'/../lib/qr/autoload.php');
}
/** PNG opaco, módulos cuadrados y margen blanco de cuatro módulos. No usa servicios externos. */
function qrImagen(string $url): string {
    if ($url==='') return '';
    static $cache=[];
    if (isset($cache[$url])) return $cache[$url];
    if (!qrDependenciasListas()) { error_log('QR 1.9: faltan GD, mbstring o lib/qr.'); return ''; }
    try {
        require_once __DIR__.'/../lib/qr/autoload.php';
        $op=new \chillerlan\QRCode\QROptions([
            'outputType'=>\chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'outputBase64'=>true,'scale'=>6,'addQuietzone'=>true,'quietzoneSize'=>4,
            'eccLevel'=>\chillerlan\QRCode\Common\EccLevel::M,
            'imageTransparent'=>false,'drawCircularModules'=>false,'bgColor'=>[255,255,255],
        ]);
        $png=(new \chillerlan\QRCode\QRCode($op))->render($url);
        if (!is_string($png) || strpos($png,'data:image/png;base64,')!==0) throw new RuntimeException('Salida QR inesperada.');
        if (count($cache)<100) $cache[$url]=$png;
        return $png;
    } catch (Throwable $e) { error_log('QR 1.9: no se pudo generar el código. '.$e->getMessage()); return ''; }
}
function qrGuardarBase(PDO $pdo,string $entrada,int $version): void {
    $base=qrValidarBase($entrada);
    $q=$pdo->prepare('UPDATE configuracion_tienda SET url_publica=?,version=version+1 WHERE id=1 AND version=?');
    $q->execute([$base,$version]);
    if ($q->rowCount()!==1) throw new RuntimeException('Otro administrador cambió la configuración. Recarga antes de guardar.');
}
function qrRutaImagenProducto(?string $nombre): string {
    if (!$nombre || !preg_match('/^[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|gif|webp)$/iD',$nombre)) return '';
    $p=__DIR__.'/../productos/imagenes/'.$nombre;
    if (!is_file($p) || is_link($p)) return '';
    return 'imagenes/'.rawurlencode($nombre);
}
