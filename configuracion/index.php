<?php
require_once __DIR__ . '/../funciones.php';
protegerAdministrador();
$guardado=$_SESSION['aviso_configuracion'] ?? ''; unset($_SESSION['aviso_configuracion']);
$cfg=obtenerConfiguracionTienda($pdo); $form=$cfg; $error=''; $versionFormulario=(int)$cfg['version'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    $nuevoLogo=null;
    try {
        foreach (['nombre','correo','telefono','direccion','color_principal','color_fondo'] as $campo) $form[$campo]=textoPost($campo);
        $datos=tcValidarConfiguracion($form);
        $version=filter_var(textoPost('version'),FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        $versionFormulario=$version===false ? -1 : $version;
        if ($version===false) throw new RuntimeException('La versión del formulario no es válida. Recarga la página.');
        $quitar=textoPost('quitar_logo')==='1';
        if ($quitar && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) throw new RuntimeException('Elige subir un logo nuevo o quitarlo, no ambas acciones.');
        if (!$pdo->query('SELECT id FROM configuracion_tienda WHERE id=1')->fetchColumn()) throw new RuntimeException('Importa la migración 1.3 antes de guardar.');
        $nuevoLogo=tcGuardarLogo($_FILES['logo'] ?? []);
        tcGuardarConfiguracion($pdo,$datos,$version,$nuevoLogo,$quitar);
        $_SESSION['aviso_configuracion']='Configuración guardada. Los nuevos tickets y correos usarán estos datos.';
        header('Location: index.php',true,303); exit;
    } catch (Throwable $e) {
        if ($nuevoLogo) {
            try {
                $q=$pdo->prepare('SELECT COUNT(*) FROM configuracion_tienda WHERE logo=?'); $q->execute([$nuevoLogo]);
                if (!(int)$q->fetchColumn()) tcBorrarLogo($nuevoLogo);
            } catch (Throwable $limpieza) { error_log('No se pudo comprobar la limpieza del logo nuevo.'); }
        }
        error_log('Configuración del negocio: '.$e->getMessage());
        $error=$e instanceof RuntimeException && !($e instanceof PDOException)
            ? $e->getMessage() : 'No fue posible guardar. Revisa que importaste la migración 1.3 y consulta el registro de PHP.';
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Configuración | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/ventas.css"><link rel="stylesheet" href="../css/configuracion.css"><?= tiendaEstilos('../') ?></head><body>
<header class="barra"><a class="marca" href="../dashboard.php"><?= tiendaMarca('../') ?></a><div><span class="etiqueta">Administración</span><?= tcEsc($_SESSION['usuario']['nombre']) ?> <?= botonCerrarSesion('../logout.php') ?></div></header>
<main><nav><a href="../dashboard.php">← Panel principal</a><a href="../pedidos/index.php">Pedidos</a><a href="../ventas.php">Reporte de ventas</a><a href="index.php" aria-current="page">Configuración</a><a href="qr.php">Códigos QR</a></nav>
<div class="titulo"><div><p class="sobre">IDENTIDAD DEL NEGOCIO</p><h1>Personaliza tu tienda</h1><p class="muted">Nombre, contacto, logo y colores para tu sistema y comprobantes.</p></div></div>
<?php if ($guardado): ?><p role="status" class="aviso mensaje-exito"><?= tcEsc($guardado) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert" class="aviso mensaje-error"><?= tcEsc($error) ?></p><?php endif; ?>
<div class="config-grid"><form class="panel config-form" method="post" enctype="multipart/form-data">
<?= campoCsrf() ?><input type="hidden" name="version" value="<?= $versionFormulario ?>">
<fieldset><legend>Datos del negocio</legend><div class="config-campos">
<label class="ancho">Nombre del negocio<input id="nombre" name="nombre" maxlength="100" value="<?= tcEsc($form['nombre']) ?>" required autocomplete="organization"></label>
<label>Correo de contacto<input type="email" id="correo" name="correo" maxlength="150" value="<?= tcEsc($form['correo']) ?>" autocomplete="email"></label>
<label>Teléfono<input type="tel" id="telefono" name="telefono" maxlength="30" value="<?= tcEsc($form['telefono']) ?>" autocomplete="tel"></label>
<label class="ancho">Dirección<input id="direccion" name="direccion" maxlength="255" value="<?= tcEsc($form['direccion']) ?>" autocomplete="street-address"></label>
</div><p class="nota">Los datos de contacto son opcionales. El correo de contacto se muestra en comprobantes y se usa para las respuestas al correo de compra; no cambia la cuenta SMTP que envía los mensajes.</p></fieldset>
<fieldset><legend>Logo del negocio</legend>
<?php if (tcRutaLogo($cfg['logo'])): ?><img class="logo-actual" src="../assets/marca/<?= tcEsc($cfg['logo']) ?>" alt="Logo actual"><?php endif; ?>
<label>Seleccionar nuevo logo<input id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label><small>PNG, JPEG o WebP reales, hasta 2 MB y 3000 píxeles por lado. Para fondos transparentes utiliza PNG.</small>
<?php if ($cfg['logo']): ?><label class="config-checkbox"><input type="checkbox" id="quitar-logo" name="quitar_logo" value="1">Quitar el logo actual</label><?php endif; ?>
</fieldset>
<fieldset><legend>Colores de la interfaz</legend><div class="config-colores">
<label>Color principal<input id="color-principal" type="color" name="color_principal" value="<?= tcEsc(preg_match('/^#[a-f0-9]{6}$/iD',$form['color_principal']) ? $form['color_principal'] : '#20a86b') ?>"></label>
<label>Fondo oscuro<input id="color-fondo" type="color" name="color_fondo" value="<?= tcEsc(preg_match('/^#[a-f0-9]{6}$/iD',$form['color_fondo']) ? $form['color_fondo'] : '#0b0d0c') ?>"></label>
<button class="boton secundario" type="button" id="restaurar-colores">Restaurar colores originales</button></div><p class="nota">Se conserva la interfaz oscura. Al guardar se verifica el contraste para que los textos y controles sigan siendo legibles.</p></fieldset>
<div class="config-acciones"><button class="boton" type="submit">Guardar configuración</button><a href="../dashboard.php">Volver al panel</a></div>
</form><aside class="panel config-preview" id="preview"><h2>Vista previa</h2><div class="preview-escena"><div class="preview-cabecera"><span id="preview-logo"><?= tiendaLogoHtml('../') ?></span><strong id="preview-nombre"><?= tcEsc($form['nombre']) ?></strong></div><p>Tu pedido se registró correctamente.</p><div class="preview-linea"></div><span class="preview-boton">Ver productos</span><p class="nota" id="preview-contacto"></p></div><p class="nota">Los cambios se aplican al guardar. Los documentos ya enviados no se modifican.</p></aside></div>
<footer>La personalización no modifica precios, existencias, usuarios ni pedidos.</footer></main>
<script>
(()=>{
 const byId=id=>document.getElementById(id); const vista=byId('preview'); let temporal=null;
 function actualizar(){
  byId('preview-nombre').textContent=byId('nombre').value || 'Nombre del negocio';
  byId('preview-contacto').textContent=['direccion','telefono','correo'].map(id=>byId(id).value).filter(Boolean).join(' · ');
  vista.style.setProperty('--preview-primary',byId('color-principal').value);
  vista.style.setProperty('--preview-bg',byId('color-fondo').value);
 }
 for(const id of ['nombre','correo','telefono','direccion','color-principal','color-fondo'])byId(id).addEventListener('input',actualizar);
 byId('restaurar-colores').addEventListener('click',()=>{byId('color-principal').value='#20a86b';byId('color-fondo').value='#0b0d0c';actualizar();});
 const logoOriginal=Array.from(byId('preview-logo').childNodes,n=>n.cloneNode(true));
 function actualizarLogo(){
  if(temporal){URL.revokeObjectURL(temporal);temporal=null;}
  if(byId('quitar-logo')?.checked){const icono=document.createElement('span');icono.textContent='🏪';byId('preview-logo').replaceChildren(icono);return;}
  const file=byId('logo').files[0];
  if(!file){byId('preview-logo').replaceChildren(...logoOriginal.map(n=>n.cloneNode(true)));return;}
  temporal=URL.createObjectURL(file);const img=document.createElement('img');img.src=temporal;img.alt='Vista previa del logo';byId('preview-logo').replaceChildren(img);
 }
 byId('logo').addEventListener('change',actualizarLogo);
 byId('quitar-logo')?.addEventListener('change',actualizarLogo);
 actualizar();
})();
</script></body></html>
