<?php
require_once __DIR__.'/../conexion.php';
require_once __DIR__.'/../funciones.php';
require_once __DIR__.'/funciones.php';
protegerAdministrador();
header('X-Content-Type-Options: nosniff');
$error=''; $tipo=class_exists(ZipArchive::class)?'completo':'sql';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf(); $tipo=textoPost('tipo');
    $temporal=null; $lock=false; $lockNombre='';
    try {
        if (!in_array($tipo,['completo','sql','imagenes'],true)) throw new RuntimeException('Selecciona un tipo de respaldo válido.');
        $confirmacion=rbConfirmar($pdo,textoPost('actual')); unset($_POST['actual']);
        if (!empty($confirmacion['revocada'])) authSalir('revocada');
        if (!$confirmacion['ok']) {
            $espera=(int)($confirmacion['espera'] ?? 0);
            if ($espera>0) { http_response_code(429); header('Retry-After: '.$espera); throw new RuntimeException('Demasiados intentos incorrectos. Espera 15 minutos antes de volver a confirmar.'); }
            http_response_code(403); throw new RuntimeException('La contraseña actual no es correcta.');
        }
        $base=(string)$pdo->query('SELECT DATABASE()')->fetchColumn(); $lockNombre='tienda_respaldo_'.substr(hash('sha256',strtolower($base)),0,40);
        $q=$pdo->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lockNombre]); $lock=(int)$q->fetchColumn()===1;
        if (!$lock) { http_response_code(409); throw new RuntimeException('Ya se está preparando otro respaldo de esta tienda. Espera e inténtalo de nuevo.'); }
        @set_time_limit(120); ignore_user_abort(true);
        $temporal=rbTemporal(); $descarga=rbPreparar($pdo,$tipo,$temporal);
        $estado=authEstadoSesion($pdo,false);
        if (!$estado['vigente'] || !esAdministrador()) { http_response_code(403); throw new RuntimeException('Tu acceso cambió durante la preparación. Inicia sesión nuevamente.'); }
        registrarBitacora($pdo,'Preparó respaldo '.$tipo.' SHA256 '.$descarga['sha256']);
        $q=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lockNombre]); $lock=false;
        $f=fopen($descarga['ruta'],'rb');
        if (!$f) throw new RuntimeException('No se pudo abrir la descarga.');
        session_write_close();
        while (ob_get_level()>0) ob_end_clean();
        header('Content-Type: '.$descarga['mime']);
        header('Content-Disposition: attachment; filename="'.$descarga['nombre'].'"');
        header('Cache-Control: private, no-store, max-age=0'); header('Pragma: no-cache');
        header('Content-Length: '.filesize($descarga['ruta']));
        try { fpassthru($f); } finally { fclose($f); }
        exit;
    } catch (Throwable $e) {
        error_log('Respaldos: operación no completada; tipo '.get_class($e).'; código '.$e->getCode());
        if (http_response_code()<400) http_response_code(422);
        $error=$e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'No se pudo preparar el respaldo. Revisa espacio, permisos y conexión a la base.';
    } finally {
        if ($lock) { try { $q=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lockNombre]); } catch (Throwable $e) {} }
        if ($temporal!==null) rbLimpiar($temporal);
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Respaldos | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/ventas.css"><link rel="stylesheet" href="../css/respaldos.css"><?= tiendaEstilos('../') ?></head><body>
<header class="barra"><a class="marca" href="../dashboard.php"><?= tiendaMarca('../') ?></a><div><?= tcEsc($_SESSION['usuario']['nombre']) ?> <?= botonCerrarSesion('../logout.php') ?></div></header>
<main><nav><a href="../dashboard.php">← Panel principal</a><a href="../configuracion/index.php">Configuración</a><a href="../bitacora/index.php">Bitácora</a></nav>
<div class="titulo"><div><p class="sobre">ADMINISTRACIÓN · VERSIÓN 1.7</p><h1>Respaldos de tu tienda</h1><p class="muted">Descarga una copia y guárdala en tu computadora.</p></div></div>
<?php if ($error): ?><div class="aviso error" role="alert"><?= tcEsc($error) ?></div><?php endif; ?>
<form method="post" class="panel respaldo-form"><?= campoCsrf() ?>
<h2>Elige qué guardar</h2><div class="respaldo-opciones">
<?php foreach (['completo'=>['Respaldo completo','Base SQL, imágenes de productos, logo, manifiesto y guía de recuperación. Recomendado.'],'sql'=>['Solo base de datos','Estructura y datos de usuarios, productos, pedidos, configuración y bitácora. Archivo SQL.'],'imagenes'=>['Solo imágenes','Imágenes de productos y logo conservando sus carpetas. Archivo ZIP.']] as $valor=>$opcion): ?>
<label class="respaldo-opcion"><input type="radio" name="tipo" value="<?= $valor ?>" <?= $tipo===$valor?'checked':'' ?> <?= $valor!=='sql' && !class_exists(ZipArchive::class)?'disabled':'' ?> required><span><strong><?= tcEsc($opcion[0]) ?></strong><small><?= tcEsc($opcion[1]) ?></small></span></label>
<?php endforeach; ?></div>
<?php if (!class_exists(ZipArchive::class)): ?><p class="aviso">Para descargar ZIP, activa extension=zip en el php.ini de Apache y reinicia Apache. Puedes descargar SQL mientras tanto.</p><?php endif; ?>
<div class="respaldo-confirmar"><label>Confirma tu contraseña de administrador<input type="password" name="actual" autocomplete="current-password" maxlength="4096" required></label><button class="boton" type="submit">Preparar y descargar respaldo</button></div>
<p class="nota">Cada descarga requiere tu contraseña. Se registra su preparación en la bitácora. Límite: 250 MiB y 10000 imágenes.</p></form>
<div class="respaldo-notas"><section class="panel"><h2>Antes de descargar</h2><p>Evita editar productos, eliminar imágenes o cambiar el logo durante la preparación. Guarda el respaldo en una carpeta privada fuera de htdocs.</p><p>El SQL contiene datos personales y hashes de contraseñas. El archivo descargado no está cifrado.</p></section><section class="panel"><h2>Para recuperar la tienda</h2><p>Utiliza una copia de la misma versión del código y una base vacía en phpMyAdmin. El ZIP incluye RESTAURAR.md con los pasos.</p><p>Los respaldos no incluyen las credenciales de MySQL o SMTP. Conserva esos ajustes por separado.</p></section></div>
</main></body></html>
