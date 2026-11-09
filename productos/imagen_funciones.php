<?php
// Guarda imágenes reales, no confía en la extensión enviada por el navegador.
function guardarImagenProducto(array $archivo): ?string {
    $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK || !isset($archivo['tmp_name']) || !is_string($archivo['tmp_name'])
        || !is_uploaded_file($archivo['tmp_name'])) {
        throw new RuntimeException('No se recibió una imagen válida.');
    }
    $temporal = $archivo['tmp_name'];
    $tamano = filesize($temporal);
    if ($tamano === false || $tamano < 1 || $tamano > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen debe tener un tamaño de hasta 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporal);
    $formatos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $medidas = @getimagesize($temporal);
    if (!isset($formatos[$mime]) || !$medidas || ($medidas['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Selecciona una imagen JPEG, PNG, GIF o WebP real.');
    }
    if ($medidas[0] > 5000 || $medidas[1] > 5000 || $medidas[0] * $medidas[1] > 12000000) {
        throw new RuntimeException('La imagen admite hasta 5000 píxeles por lado y 12 megapíxeles.');
    }
    if ($mime === 'image/webp' && !function_exists('imagecreatefromwebp')) {
        throw new RuntimeException('Activa GD con soporte WebP en PHP o utiliza una imagen JPEG real.');
    }
    $nombre = bin2hex(random_bytes(16)) . '.' . $formatos[$mime];
    if (!move_uploaded_file($temporal, __DIR__ . '/imagenes/' . $nombre)) {
        throw new RuntimeException('No se pudo guardar la imagen. Revisa los permisos de la carpeta.');
    }
    return $nombre;
}
function borrarImagenProducto(?string $nombre): void {
    if (!$nombre || basename($nombre) !== $nombre || !preg_match('/^[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|gif|webp)$/D', $nombre)) return;
    $ruta = __DIR__ . '/imagenes/' . $nombre;
    if (is_file($ruta) && !unlink($ruta)) error_log('No se pudo eliminar imagen de producto.');
}
