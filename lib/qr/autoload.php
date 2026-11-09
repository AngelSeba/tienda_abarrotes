<?php
/** Autocarga aislada para QR; conserva intacto el vendor principal de la tienda. */
spl_autoload_register(static function(string $clase): void {
    $mapa=['chillerlan\\QRCode\\'=>__DIR__.'/php-qrcode/src/',
        'chillerlan\\Settings\\'=>__DIR__.'/php-settings-container/src/'];
    foreach ($mapa as $prefijo=>$carpeta) {
        if (strpos($clase,$prefijo)!==0) continue;
        $archivo=$carpeta.str_replace('\\','/',substr($clase,strlen($prefijo))).'.php';
        if (is_file($archivo)) require $archivo;
        return;
    }
});
