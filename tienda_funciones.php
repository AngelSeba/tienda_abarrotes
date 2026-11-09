<?php
function tcEsc($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function tcPredeterminados(): array {
    return ['id'=>1,'nombre'=>'Mi negocio','correo'=>'','telefono'=>'','direccion'=>'',
        'url_publica'=>'','logo'=>null,'color_principal'=>'#20a86b','color_fondo'=>'#0b0d0c','version'=>0];
}
function tcLuminancia(string $hex): float {
    $rgb = sscanf($hex, '#%02x%02x%02x');
    $lineal = array_map(static function($c) {
        $c /= 255; return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, $rgb);
    return .2126*$lineal[0]+.7152*$lineal[1]+.0722*$lineal[2];
}
function tcMezclar(string $hex, float $blanco): string {
    $rgb = sscanf($hex, '#%02x%02x%02x');
    return sprintf('#%02x%02x%02x', ...array_map(static fn($c) => (int)round($c*(1-$blanco)+255*$blanco),$rgb));
}
function tcContraste(string $a, string $b): float {
    $x=tcLuminancia($a); $y=tcLuminancia($b); return (max($x,$y)+.05)/(min($x,$y)+.05);
}
function tcColorPapel(string $color): string {
    if (!preg_match('/^#[a-f0-9]{6}$/iD',$color)) return '#116b55';
    $rgb=sscanf($color,'#%02x%02x%02x');
    for ($i=0;$i<30 && tcContraste($color,'#ffffff')<4.5;$i++) {
        $rgb=array_map(static fn($c)=>(int)floor($c*.9),$rgb);
        $color=sprintf('#%02x%02x%02x',...$rgb);
    }
    return $color;
}
function tcColoresValidos(string $principal, string $fondo): bool {
    if (!preg_match('/^#[a-f0-9]{6}$/iD',$principal) || !preg_match('/^#[a-f0-9]{6}$/iD',$fondo)) return false;
    return tcContraste('#edf4f0',$fondo) >= 4.5 && tcLuminancia($fondo) <= .12
        && tcContraste($principal,tcMezclar($fondo,.045)) >= 4.5;
}
function obtenerConfiguracionTienda(PDO $pdo): array {
    $base=tcPredeterminados();
    try {
        $fila=$pdo->query('SELECT * FROM configuracion_tienda WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        if ($fila) $base=array_merge($base,$fila);
    } catch (PDOException $e) {
        // Mantiene disponible la tienda mientras se importa la tabla nueva.
        error_log('Configuración del negocio no disponible: ' . $e->getMessage());
    }
    if (!tcColoresValidos((string)$base['color_principal'],(string)$base['color_fondo'])) {
        $base['color_principal']='#20a86b'; $base['color_fondo']='#0b0d0c';
    }
    return $base;
}
function tiendaActual(): array {
    static $tienda=null;
    global $pdo;
    if ($tienda === null) {
        if (!isset($pdo)) require __DIR__ . '/conexion.php';
        $tienda=obtenerConfiguracionTienda($pdo);
    }
    return $tienda;
}
function tiendaNombre(): string { return (string)tiendaActual()['nombre']; }
function tcRutaLogo(?string $nombre): ?string {
    if (!$nombre || !preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D',$nombre)) return null;
    $ruta=__DIR__ . '/assets/marca/' . $nombre;
    return is_file($ruta) && filesize($ruta) <= 2*1024*1024 ? $ruta : null;
}
function tcLogoDatos(array $tienda): ?array {
    $ruta=tcRutaLogo($tienda['logo'] ?? null);
    if (!$ruta) return null;
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($ruta);
    $tipos=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($tipos[$mime]) || pathinfo($ruta,PATHINFO_EXTENSION) !== $tipos[$mime]) return null;
    return ['bytes'=>file_get_contents($ruta),'mime'=>$mime,'nombre'=>basename($ruta)];
}
function tiendaLogoHtml(string $prefijo=''): string {
    $cfg=tiendaActual();
    if (!tcRutaLogo($cfg['logo'])) return '<span class="marca-icono" aria-hidden="true">🏪</span>';
    return '<img class="logo-negocio" src="'.tcEsc($prefijo.'assets/marca/'.$cfg['logo']).'" alt="Logo de '.tcEsc($cfg['nombre']).'">';
}
function tiendaMarca(string $prefijo=''): string {
    return '<span class="marca-tienda">'.tiendaLogoHtml($prefijo).'<span>'.tcEsc(tiendaNombre()).'</span></span>';
}
function tiendaEstilos(string $prefijo=''): string {
    return '<link rel="stylesheet" href="'.tcEsc($prefijo.'tema.php').'"><link rel="stylesheet" href="'.tcEsc($prefijo.'css/acceso.css').'">'
        . (function_exists('recursosSesion') ? recursosSesion($prefijo) : '');
}
function tcGuardarLogo(array $archivo): ?string {
    $error=$archivo['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK || !isset($archivo['tmp_name']) || !is_string($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
        throw new RuntimeException('No se recibió un logo válido.');
    }
    $ruta=$archivo['tmp_name']; $tamano=filesize($ruta);
    if ($tamano === false || $tamano < 1 || $tamano > 2*1024*1024) throw new RuntimeException('El logo admite hasta 2 MB.');
    $tipos=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($ruta); $medidas=@getimagesize($ruta);
    if (!isset($tipos[$mime]) || !$medidas || ($medidas['mime'] ?? '') !== $mime) throw new RuntimeException('Utiliza un logo JPEG, PNG o WebP real.');
    if ($medidas[0] > 3000 || $medidas[1] > 3000 || $medidas[0]*$medidas[1] > 6000000) throw new RuntimeException('El logo admite hasta 3000 píxeles por lado y 6 megapíxeles.');
    if ($mime === 'image/webp' && !function_exists('imagecreatefromwebp')) throw new RuntimeException('Activa GD con WebP o utiliza un logo PNG/JPEG real.');
    if (!function_exists('imagecreatefromstring')) throw new RuntimeException('Activa la extensión GD para validar el logo.');
    $imagen=@imagecreatefromstring(file_get_contents($ruta));
    if ($imagen===false) throw new RuntimeException('El logo está dañado o no contiene una imagen completa.');
    imagedestroy($imagen);
    $nombre=bin2hex(random_bytes(16)).'.'.$tipos[$mime];
    if (!move_uploaded_file($ruta,__DIR__.'/assets/marca/'.$nombre)) throw new RuntimeException('No se pudo guardar el logo. Revisa los permisos de assets/marca.');
    return $nombre;
}
function tcBorrarLogo(?string $nombre): void {
    $ruta=tcRutaLogo($nombre);
    if ($ruta && !unlink($ruta)) error_log('No se pudo retirar el logo anterior.');
}
function tcValidarConfiguracion(array $entrada): array {
    $maximos=['nombre'=>100,'correo'=>150,'telefono'=>30,'direccion'=>255]; $datos=[];
    foreach ($maximos as $clave=>$limite) {
        if (!isset($entrada[$clave]) || !is_string($entrada[$clave])) throw new RuntimeException('Los datos del negocio no son válidos.');
        $valor=trim($entrada[$clave]);
        if (preg_match('/[\x00-\x1f\x7f]/',$valor) || !mb_check_encoding($valor,'UTF-8') || mb_strlen($valor)>$limite) {
            throw new RuntimeException('Revisa '.$clave.': máximo '.$limite.' caracteres y sin saltos de línea.');
        }
        $datos[$clave]=$valor;
    }
    if ($datos['nombre']==='') throw new RuntimeException('El nombre del negocio es obligatorio.');
    if ($datos['correo']!=='' && !filter_var($datos['correo'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El correo de contacto no es válido.');
    if ($datos['telefono']!=='' && !preg_match('/^[0-9+() .-]{5,30}$/D',$datos['telefono'])) throw new RuntimeException('Revisa el teléfono; usa números, espacios, +, paréntesis o guiones.');
    $principal=$entrada['color_principal'] ?? ''; $fondo=$entrada['color_fondo'] ?? '';
    if (!is_string($principal) || !is_string($fondo) || !tcColoresValidos($principal,$fondo)) {
        throw new RuntimeException('Elige un fondo oscuro y un color principal con buen contraste. Puedes restaurar los colores originales.');
    }
    $datos['color_principal']=strtolower($principal); $datos['color_fondo']=strtolower($fondo);
    return $datos;
}
function tcGuardarConfiguracion(PDO $pdo,array $datos,int $version,?string $nuevoLogo,bool $quitarLogo): void {
    if ($nuevoLogo && $quitarLogo) throw new RuntimeException('Elige subir un logo nuevo o quitarlo, no ambas acciones.');
    try {
        $pdo->beginTransaction();
        $actual=$pdo->query('SELECT * FROM configuracion_tienda WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if (!$actual) throw new RuntimeException('Falta importar la migración de configuración 1.3.');
        if ((int)$actual['version']!==$version) throw new RuntimeException('Otro administrador cambió la configuración. Recarga antes de guardar.');
        $logo=$quitarLogo ? null : ($nuevoLogo ?? $actual['logo']);
        $q=$pdo->prepare('UPDATE configuracion_tienda SET nombre=?,correo=?,telefono=?,direccion=?,logo=?,color_principal=?,color_fondo=?,version=version+1 WHERE id=1 AND version=?');
        $q->execute([$datos['nombre'],$datos['correo'],$datos['telefono'],$datos['direccion'],$logo,$datos['color_principal'],$datos['color_fondo'],$version]);
        if ($q->rowCount()!==1) throw new RuntimeException('La configuración cambió. Recarga para continuar.');
        registrarBitacora($pdo,'Actualizó la identidad y contacto del negocio.');
        $pdo->commit();
        if (($nuevoLogo || $quitarLogo) && $actual['logo']!==$logo) tcBorrarLogo($actual['logo']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
/** Contenido de correo comprobable sin conexión SMTP. */
function tcContenidoCorreo(array $tienda,array $pedido,string $nombre,bool $logo): array {
    $nombreSeguro=tcEsc($nombre); $marca=tcEsc($tienda['nombre']);
    $total=number_format((float)$pedido['total'],2); $id=(int)$pedido['id'];
    $contacto=array_filter([$tienda['direccion'],$tienda['telefono'],$tienda['correo']],static fn($v)=>$v!=='');
    $datos=implode(' · ',array_map('tcEsc',$contacto));
    $color=preg_match('/^#[a-f0-9]{6}$/iD',$tienda['color_principal']) ? $tienda['color_principal'] : '#20a86b';
    $cabecera=$logo ? '<img src="cid:logo-negocio" width="72" alt="'.$marca.'" style="display:block;max-height:72px;object-fit:contain;margin-bottom:12px">' : '';
    return [
        'asunto'=>'Confirmación de pedido #'.$id.' - '.$tienda['nombre'],
        'html'=>'<div style="font-family:Arial,sans-serif;color:#1c2733;border-top:4px solid '.$color.';padding:24px;max-width:600px">'.$cabecera.'<h1 style="font-size:22px">'.$marca.'</h1><p>Hola, '.$nombreSeguro.'.</p><p>Tu pedido <strong>#'.$id.'</strong> se registró correctamente por <strong>$'.$total.' MXN</strong>.</p><p>Adjuntamos tu comprobante PDF. Esta confirmación no significa que se haya procesado un pago en línea.</p><hr><p style="font-size:12px">'.$datos.'</p></div>',
        'texto'=>'Hola, '.$nombre.'. Tu pedido #'.$id.' se registró por $'.$total.' MXN. Se adjunta el comprobante. No se ha procesado ningún pago en línea.'."\n".$tienda['nombre']."\n".implode(' · ',$contacto),
    ];
}
