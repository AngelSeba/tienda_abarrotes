<?php
require_once __DIR__.'/funciones.php';
ini_set('display_errors','0');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
$cookiePath=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
$cookiePath=rtrim($cookiePath,'/').'/';
session_name('instalar_'.substr(hash('sha256',instRaiz()),0,16));
session_set_cookie_params(['lifetime'=>0,'path'=>$cookiePath,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
session_start();
$cerrado=instInstalado();
$error=''; $mensaje='';
$accion=$_POST['accion'] ?? '';
if (!is_string($accion)) $accion='';
$json=$accion==='comprobar';
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    try {
        if ($cerrado) { http_response_code(410); throw new RuntimeException('El instalador ya está cerrado.'); }
        if (!instCsrf()) { http_response_code(403); throw new RuntimeException('La sesión del formulario venció. Recarga la página.'); }
        if ($accion==='desbloquear') {
            $clave=instTexto($_POST,'clave_instalacion'); $hash=instClaveHash();
            if (!$hash || !hash_equals($hash,hash('sha256',$clave))) {
                http_response_code(403); throw new RuntimeException('Clave de instalación incorrecta.');
            }
            session_regenerate_id(true);
            $_SESSION['inst_clave']=$hash; $_SESSION['inst_hasta']=time()+900;
        } else {
            if (!instAutorizado()) { http_response_code(403); throw new RuntimeException('Desbloquea el instalador para continuar.'); }
            if (!instRequisitosListos()) throw new RuntimeException('Corrige los requisitos pendientes antes de continuar.');
            if (is_file(instRaiz().'/config_database.php') && !is_file(instPendienteRuta())) throw new RuntimeException('Esta carpeta ya contiene una configuración. Usa una carpeta nueva.');
            if ($accion==='comprobar') {
                if (is_file(instPendienteRuta())) throw new RuntimeException('Hay una instalación pendiente. Revisa la guía.');
                $mensaje=instComprobar(instDatosConexion($_POST),isset($_POST['crear_base']));
            } elseif ($accion==='instalar') {
                $password=instTexto($_POST,'password');
                if (!hash_equals($password,instTexto($_POST,'confirmacion'))) throw new RuntimeException('Las contraseñas del administrador no coinciden.');
                instEjecutar(instDatosConexion($_POST),[
                    'nombre'=>trim(instTexto($_POST,'nombre')),'correo'=>strtolower(trim(instTexto($_POST,'correo'))),'password'=>$password,
                ],trim(instTexto($_POST,'negocio')),isset($_POST['crear_base']));
                $_SESSION=[]; session_regenerate_id(true);
                header('Location: ./?completa=1'); exit;
            } elseif ($accion==='reanudar') {
                instReanudar(); $_SESSION=[]; session_regenerate_id(true);
                header('Location: ./?completa=1'); exit;
            } else { throw new RuntimeException('Acción no válida.'); }
        }
    } catch (PDOException $e) {
        error_log('Instalador: error de base de datos; SQLSTATE '.$e->getCode());
        $error='No se pudo completar la operación con la base. Revisa servidor, usuario, contraseña y permisos. Si ya se crearon tablas, consulta «Instalación interrumpida» en la guía.';
    } catch (Throwable $e) {
        if ($e instanceof RuntimeException && !($e instanceof PDOException)) $error=$e->getMessage();
        else { error_log('Instalador: error interno de tipo '.get_class($e)); $error='No se pudo completar la instalación. Revisa los requisitos y la guía.'; }
    }
    if ($json) {
        if ($error!=='' && http_response_code()<400) http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>$error==='','mensaje'=>$error!==''?$error:$mensaje],JSON_UNESCAPED_UNICODE); exit;
    }
}
$autorizado=instAutorizado();
$requisitos=instRequisitos();
$listo=!in_array(false,$requisitos,true);
$pendiente=is_file(instPendienteRuta());
$existente=is_file(instRaiz().'/config_database.php') && !$pendiente;
if (!$cerrado && !$autorizado && !is_file(__DIR__.'/clave_instalacion.php')) http_response_code(403);
function campo(string $clave,string $defecto=''): string {
    // Las contraseñas nunca se repueblan ni se guardan en la sesión.
    $valor=$_POST[$clave] ?? $defecto;
    return instEsc(is_string($valor)?$valor:$defecto);
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalar mi tienda · 1.9</title><link rel="stylesheet" href="instalador.css"><script src="instalador.js" defer></script></head>
<body><main class="panel"><div class="etiqueta">MI TIENDA · VERSIÓN 1.9</div>
<?php if ($cerrado): ?>
<h1>Tu tienda está lista</h1><p>El instalador quedó cerrado. Puedes entrar con el correo y la contraseña que elegiste.</p>
<a class="boton" href="../">Ir a mi tienda</a><p class="nota">Conserva instalado.lock. Puedes retirar la carpeta install después de leer la guía. Configura el correo SMTP antes de probar el envío de tickets.</p>
<?php else: ?>
<h1>Prepara tu tienda</h1><p class="intro">Una instalación nueva, con tus datos y tu primer administrador.</p>
<?php if ($error): ?><div class="aviso error" role="alert"><?= instEsc($error) ?></div><?php endif; ?>
<?php if (!$autorizado): ?>
<?php if (is_file(__DIR__.'/clave_instalacion.php')): ?>
<h2>Desbloquea la instalación</h2><p>Introduce la clave que generaste en tu computadora. Se autoriza esta sesión durante 15 minutos.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= instEsc(instToken()) ?>"><input type="hidden" name="accion" value="desbloquear">
<label>Clave de instalación<input type="password" name="clave_instalacion" required autocomplete="off" maxlength="128"></label><button>Desbloquear</button></form>
<?php else: ?>
<div class="aviso">Por seguridad, la instalación directa solo se permite desde localhost. Para un hosting, genera primero una clave con install/crear_clave.php y sigue la guía incluida.</div>
<?php endif; ?>
<?php elseif ($existente): ?>
<div class="aviso">Esta carpeta ya contiene config_database.php. No se instalará sobre ella. Usa una copia nueva del paquete y otra base vacía.</div>
<?php else: ?>
<ol class="pasos" aria-label="Etapas"><li>1 · Requisitos</li><li>2 · Base de datos</li><li>3 · Tu negocio</li></ol>
<div class="etapa" data-etapa="0"><h2>Comprueba el entorno</h2>
<ul class="requisitos"><?php foreach ($requisitos as $nombre=>$cumple): ?><li><span><?= instEsc($nombre) ?></span><strong class="<?= $cumple?'bien':'mal' ?>"><?= $cumple?'Listo':'Pendiente' ?></strong></li><?php endforeach; ?></ul>
<p class="nota">En XAMPP inicia Apache y MySQL. Si activas una extensión en php.ini, reinicia Apache y recarga esta página.</p>
<?php if ($listo && !$pendiente): ?><button type="button" data-siguiente>Continuar</button><?php endif; ?>
</div>
<?php if ($pendiente): ?>
<div class="aviso">Hay una instalación pendiente. Si la base terminó de prepararse, puedes finalizar el guardado de su configuración. Si falló la creación de tablas, consulta la guía; este asistente no borra datos.</div>
<form method="post"><input type="hidden" name="csrf" value="<?= instEsc(instToken()) ?>"><button name="accion" value="reanudar" <?= $listo?'':'disabled' ?>>Finalizar instalación pendiente</button></form>
<?php elseif ($listo): ?>
<form method="post" id="instalar" autocomplete="off">
<input type="hidden" name="csrf" value="<?= instEsc(instToken()) ?>"><input type="hidden" name="accion" value="instalar">
<section class="etapa" data-etapa="1"><h2>Conecta una base vacía</h2>
<div class="aviso">Elige una base nueva. Una base que ya contenga tablas o vistas será rechazada.</div>
<div class="columnas"><label>Servidor<input name="host" value="<?= campo('host','127.0.0.1') ?>" required maxlength="253"></label><label>Puerto<input type="number" name="puerto" value="<?= campo('puerto','3306') ?>" min="1" max="65535" required></label></div>
<label>Base de datos<input name="base_datos" value="<?= campo('base_datos','tienda_nueva') ?>" pattern="[a-zA-Z0-9_]{1,64}" required maxlength="64"></label>
<div class="columnas"><label>Usuario de MySQL<input name="usuario" value="<?= campo('usuario','root') ?>" required maxlength="128"></label><label>Contraseña de MySQL<input type="password" name="clave" autocomplete="new-password" maxlength="512"></label></div>
<label class="casilla"><input type="checkbox" name="crear_base" value="1" <?= !isset($_POST['accion']) || isset($_POST['crear_base'])?'checked':'' ?>> Crear la base si no existe (requiere permiso CREATE DATABASE)</label>
<p class="nota">Utiliza el usuario, puerto y contraseña configurados en tu Laragon/XAMPP. En hosting usa los datos de tu proveedor.</p>
<div id="resultado" class="aviso" role="status" hidden></div><div class="acciones"><button type="button" class="secundario" data-anterior>Volver</button><button type="button" id="comprobar">Comprobar conexión y continuar</button></div>
</section>
<section class="etapa" data-etapa="2"><h2>Dale identidad a tu negocio</h2>
<label>Nombre del negocio<input name="negocio" value="<?= campo('negocio','Mi negocio') ?>" required maxlength="100"></label>
<label>Nombre del administrador<input name="nombre" value="<?= campo('nombre') ?>" required maxlength="100" autocomplete="name"></label>
<label>Correo del administrador<input type="email" name="correo" value="<?= campo('correo') ?>" required maxlength="150" autocomplete="email"></label>
<div class="columnas"><label>Contraseña<input type="password" name="password" required minlength="15" maxlength="72" autocomplete="new-password"></label><label>Repetir contraseña<input type="password" name="confirmacion" required minlength="15" maxlength="72" autocomplete="new-password"></label></div>
<p class="nota">Usa una frase de al menos 15 caracteres y máximo 72 bytes. Guarda tu contraseña: no hay una cuenta ni contraseña predeterminada.</p>
<p class="nota">Se creará únicamente este administrador. Podrás agregar productos, imágenes, usuarios y el logo después de entrar.</p>
<div class="acciones"><button type="button" class="secundario" data-anterior>Volver</button><button type="submit" id="instalar-boton">Crear mi tienda</button></div><p id="espera" role="status" hidden>Preparando tu tienda. Mantén esta página abierta…</p>
</section></form>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<footer>Instalación nueva · PHP y MySQL/MariaDB · Consulta LEEME_INSTALACION.md</footer></main></body></html>
