<?php
require_once __DIR__.'/../qr/funciones.php';
require_once __DIR__.'/../rendimiento.php';
/** Imagen local reducida: evita incrustar fotografías de varios megapíxeles. */
function ticketImagenCompacta(string $ruta,int $ancho=180,int $alto=140): string {
    static $imagenes=[];
    if (is_link($ruta) || !is_file($ruta) || filesize($ruta)>5*1024*1024) return '';
    $clave=hash_file('sha256',$ruta).':'.$ancho.':'.$alto;
    if(isset($imagenes[$clave])) return $imagenes[$clave];
    $medidas=@getimagesize($ruta);
    if (!$medidas || $medidas[0]<1 || $medidas[1]<1 || $medidas[0]*$medidas[1]>12000000) return '';
    $crear=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/gif'=>'imagecreatefromgif','image/webp'=>'imagecreatefromwebp'][$medidas['mime'] ?? ''] ?? null;
    if (!$crear || !function_exists($crear)) return '';
    $src=@$crear($ruta); if (!$src) return '';
    $factor=min(1,$ancho/$medidas[0],$alto/$medidas[1]); $w=max(1,(int)round($medidas[0]*$factor)); $h=max(1,(int)round($medidas[1]*$factor));
    $dst=imagecreatetruecolor($w,$h); imagefill($dst,0,0,imagecolorallocate($dst,255,255,255));
    imagecopyresampled($dst,$src,0,0,0,0,$w,$h,$medidas[0],$medidas[1]);
    ob_start(); imagejpeg($dst,null,82); $bytes=ob_get_clean(); imagedestroy($src); imagedestroy($dst);
    if(count($imagenes)>30) $imagenes=[];
    return $imagenes[$clave]='data:image/jpeg;base64,'.base64_encode($bytes);
}
function ticketRutaImagen(string $carpeta,$nombre): ?string {
    if (!is_string($nombre) || !preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif|webp)$/iD',$nombre)) return null;
    $ruta=dirname(__DIR__).'/'.$carpeta.'/'.$nombre;
    return !is_link($ruta) && is_file($ruta) ? $ruta : null;
}
function ticketFirma(array $pedido,array $detalles,array $tienda): string {
    $imagenes=[]; $hashes=[];
    foreach($detalles as $d) { $ruta=ticketRutaImagen('productos/imagenes',$d['imagen'] ?? null); if($ruta && !isset($hashes[$ruta]))$hashes[$ruta]=hash_file('sha256',$ruta); $imagenes[]=$ruta?$hashes[$ruta]:null; }
    $logo=ticketRutaImagen('assets/marca',$tienda['logo'] ?? null); $imagenes[]=$logo?hash_file('sha256',$logo):null;
    return hash('sha256',json_encode(['plantilla'=>'1.9.0','qrDisponible'=>qrDependenciasListas(),'pedido'=>$pedido,'detalles'=>$detalles,'tienda'=>$tienda,'imagenes'=>$imagenes],JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}
/** El pedido/detalles deben obtenerse mediante la consulta de propiedad del cliente. */
function obtenerTicketPdfCache(PDO $pdo,array $pedido,array $detalles,array $tienda): string {
    $inicio=microtime(true); $firma=ticketFirma($pedido,$detalles,$tienda); $id=(int)$pedido['id'];
    $leer=$pdo->prepare('SELECT pdf FROM tickets_cache WHERE pedido_id=? AND firma=?'); $leer->execute([$id,$firma]); $pdf=$leer->fetchColumn();
    if (is_string($pdf) && strncmp($pdf,'%PDF',4)===0) { rtTiempo('pdf_cache',$inicio,['pedido'=>$id]); return $pdf; }
    $base=(string)$pdo->query('SELECT DATABASE()')->fetchColumn(); $lock='pdf17_'.substr(hash('sha256',$base.':'.$id),0,48);
    $q=$pdo->prepare('SELECT GET_LOCK(?,5)'); $q->execute([$lock]);
    if ((int)$q->fetchColumn()!==1) throw new RuntimeException('El ticket se está preparando. Vuelve a intentar la descarga en unos segundos.');
    try {
        $leer->execute([$id,$firma]); $pdf=$leer->fetchColumn();
        if (!is_string($pdf) || strncmp($pdf,'%PDF',4)!==0) {
            $pdf=generarTicketPdf($pedido,$detalles,$tienda);
            if (strlen($pdf)>15000000) throw new RuntimeException('El ticket supera el tamaño permitido.');
            $q=$pdo->prepare('INSERT INTO tickets_cache(pedido_id,firma,pdf) VALUES (?,?,?) ON DUPLICATE KEY UPDATE firma=VALUES(firma),pdf=VALUES(pdf),creado_en=CURRENT_TIMESTAMP'); $q->execute([$id,$firma,$pdf]);
        }
        rtTiempo('pdf_preparado',$inicio,['pedido'=>$id]); return $pdf;
    } finally { $q=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
}
