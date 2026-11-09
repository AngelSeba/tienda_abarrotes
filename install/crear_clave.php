<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/funciones.php';
if (instInstalado() || (is_file(instRaiz().'/config_database.php') && !is_file(instPendienteRuta()))) {
    fwrite(STDERR,"Esta carpeta ya tiene configuración o está instalada. No se reabre.\n"); exit(1);
}
$clave=bin2hex(random_bytes(32));
$archivo=__DIR__.'/clave_instalacion.php';
if (is_file($archivo)) { fwrite(STDERR,"Ya hay una clave. No se reemplaza automáticamente.\n"); exit(1); }
$f=@fopen($archivo,'x');
if (!$f) { fwrite(STDERR,"No se pudo crear la clave o ya existe.\n"); exit(1); }
$contenido="<?php\nreturn '".hash('sha256',$clave)."';\n";
$escritos=fwrite($f,$contenido); fclose($f);
if ($escritos!==strlen($contenido)) { @unlink($archivo); fwrite(STDERR,"No se pudo guardar la clave.\n"); exit(1); }
@chmod($archivo,0600);
echo "Clave de instalación (guárdala; solo se muestra aquí):\n".$clave."\n";
echo "Sube el proyecto con install/clave_instalacion.php. No pegues la clave en una URL.\n";
