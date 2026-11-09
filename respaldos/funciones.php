<?php
const RB_VERSION='1.7';
const RB_LIMITE=262144000; // 250 MiB por respaldo; preparar fuera de la carpeta pública.
const RB_MAX_IMAGENES=10000;
function rbConfirmar(PDO $pdo,string $actual): array {
    $id=(int)$_SESSION['usuario']['id'];
    $usuarioClave=authClaveLimite('respaldo_usuario',(string)$id);
    $ipClave=authClaveLimite('respaldo_ip',authIp());
    try {
        $pdo->beginTransaction(); $ahora=(int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
        $limites=authLimites($pdo,[$usuarioClave=>5,$ipClave=>30],$ahora);
        $espera=authEspera($limites,$ahora);
        if ($espera>0) { $pdo->commit(); return ['ok'=>false,'espera'=>$espera]; }
        $q=$pdo->prepare('SELECT password,rol,sesion_version FROM usuarios WHERE id=? FOR UPDATE'); $q->execute([$id]); $u=$q->fetch();
        if (!$u || $u['rol']!=='administrador' || (int)$u['sesion_version']!==($_SESSION['sesion_version'] ?? null)) {
            $pdo->rollBack(); return ['ok'=>false,'revocada'=>true];
        }
        if (strlen($actual)>4096 || strpos($actual,"\0")!==false || !password_verify($actual,$u['password'])) {
            $espera=authFallo($pdo,$limites,$ahora); $pdo->commit(); return ['ok'=>false,'espera'=>$espera];
        }
        authReiniciarLimite($pdo,$usuarioClave,$ahora); $pdo->commit(); return ['ok'=>true];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function rbDentro(string $ruta,string $padre): bool {
    $ruta=strtolower(str_replace('\\','/',$ruta)); $padre=rtrim(strtolower(str_replace('\\','/',$padre)),'/');
    return $ruta===$padre || strpos($ruta,$padre.'/')===0;
}
function rbTemporal(): string {
    $base=realpath(sys_get_temp_dir()); $raiz=realpath(dirname(__DIR__));
    $publico=realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if (!$base || !$raiz || !is_writable($base) || rbDentro($base,$raiz) || ($publico && rbDentro($base,$publico))) {
        throw new RuntimeException('Configura sys_temp_dir en PHP con una carpeta de escritura fuera de htdocs y del proyecto.');
    }
    $ruta=$base.'/tienda_respaldo_'.bin2hex(random_bytes(16));
    if (!mkdir($ruta,0700)) throw new RuntimeException('No se pudo crear el temporal privado.');
    register_shutdown_function('rbLimpiar',$ruta); return $ruta;
}
function rbLimpiar(string $ruta): void {
    if (!is_dir($ruta) || is_link($ruta)) return;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { if ($f->isDir() && !$f->isLink()) @rmdir($f->getPathname()); else @unlink($f->getPathname()); }
    @rmdir($ruta);
}
function rbId(string $nombre): string { return '`'.str_replace('`','``',$nombre).'`'; }
function rbEscribir($f,string $texto,int &$bytes): void {
    $n=strlen($texto);
    if ($bytes+$n>RB_LIMITE) throw new RuntimeException('El respaldo supera 250 MiB. Usa una herramienta de respaldo del servidor para esta tienda.');
    for ($pos=0;$pos<$n;) {
        $w=fwrite($f,substr($texto,$pos));
        if ($w===false || $w===0) throw new RuntimeException('No hay espacio o permiso para preparar el respaldo.');
        $pos+=$w;
    }
    $bytes+=$n;
}
function rbReferencia(string $carpeta,$nombre,array &$refs): void {
    if ($nombre===null || $nombre==='') return;
    if (!is_string($nombre) || !preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif|webp)$/iD',$nombre)) throw new RuntimeException('Hay una referencia de imagen no válida. Revisa el catálogo o el logo antes de respaldar.');
    $refs[$carpeta.'/'.$nombre]=true;
}
function rbSql(PDO $pdo,string $archivo,int &$bytes): array {
    $esperadas=['usuarios','productos','bitacora','pedidos','pedido_detalles','configuracion_tienda','autenticacion_intentos'];
    $permitidas=array_merge($esperadas,['sistema_instalacion','movimientos_inventario','correos_pendientes','tickets_cache']);
    $objetos=$pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM); $presentes=[];
    foreach ($objetos as $o) {
        if ($o[1]!=='BASE TABLE' || !in_array($o[0],$permitidas,true)) throw new RuntimeException('La base tiene tablas o vistas adicionales. Utiliza un respaldo completo desde phpMyAdmin para ese esquema personalizado.');
        $presentes[]=$o[0];
    }
    if (array_diff($esperadas,$presentes)) throw new RuntimeException('El esquema no corresponde a la versión 1.4 o posterior. Revisa las actualizaciones de la tienda.');
    if ($pdo->query('SHOW TRIGGERS')->fetch()) throw new RuntimeException('La base tiene disparadores personalizados. Utiliza phpMyAdmin para exportarlos junto con el esquema.');
    $q=$pdo->query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');
    foreach ($q->fetchAll() as $t) if (strtoupper((string)$t['ENGINE'])!=='INNODB') throw new RuntimeException('Todas las tablas deben usar InnoDB para obtener una copia consistente.');
    $tablas=array_values(array_filter($permitidas,static fn($t)=>in_array($t,$presentes,true)));
    $f=fopen($archivo,'x+b'); if (!$f) throw new RuntimeException('No se pudo preparar el archivo SQL.'); @chmod($archivo,0600);
    $conteos=[]; $refs=[]; $buffered=$pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    $zona=(string)$pdo->query('SELECT @@session.time_zone')->fetchColumn();
    try {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $pdo->beginTransaction();
        // La primera lectura InnoDB establece la misma instantánea para todas las filas.
        $pdo->query('SELECT id FROM usuarios LIMIT 1')->fetchAll();
        rbEscribir($f,"-- Mi tienda: respaldo 1.7\n-- UTC ".gmdate('c')."\n-- Importar SOLO en una base VACIA. No contiene CREATE DATABASE, USE ni DROP.\nSET NAMES utf8mb4;\nSET @RB_FK=@@FOREIGN_KEY_CHECKS;\nSET FOREIGN_KEY_CHECKS=0;\nSET @RB_MODE=@@SQL_MODE;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET @RB_TZ=@@time_zone;\nSET time_zone='+00:00';\n\n",$bytes);
        // Adquirir metadatos antes de leer datos; no ejecutar DDL durante el respaldo.
        $esquemas=[];
        foreach ($tablas as $t) { $row=$pdo->query('SHOW CREATE TABLE '.rbId($t))->fetch(PDO::FETCH_NUM); $esquemas[$t]=$row[1]; }
        $pdo->exec("SET time_zone='+00:00'");
        foreach ($tablas as $t) {
            rbEscribir($f,$esquemas[$t].";\n",$bytes);
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
            $cursor=$pdo->query('SELECT * FROM '.rbId($t).($t==='tickets_cache'?' WHERE 1=0':'')); $n=0;
            try {
                while ($fila=$cursor->fetch(PDO::FETCH_ASSOC)) {
                    // Restaurar una copia no debe enviar correos antiguos automáticamente.
                    if($t==='correos_pendientes' && !in_array($fila['estado'],['enviado','cancelado'],true)) { $fila['estado']='error'; $fila['iniciado_en']=null; $fila['ultimo_error']='Copia restaurada: revisar recepción y autorizar reintento.'; }
                    $columnas=implode(',',array_map('rbId',array_keys($fila)));
                    $valores=array_map(static fn($v)=>$v===null?'NULL':($v===''?"''":'CONVERT(0x'.bin2hex((string)$v).' USING utf8mb4)'),array_values($fila));
                    rbEscribir($f,'INSERT INTO '.rbId($t).' ('.$columnas.') VALUES ('.implode(',',$valores).");\n",$bytes); ++$n;
                    if ($t==='productos') rbReferencia('productos/imagenes',$fila['imagen'],$refs);
                    if ($t==='configuracion_tienda') rbReferencia('assets/marca',$fila['logo'],$refs);
                }
            } finally { $cursor->closeCursor(); $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered); }
            $conteos[$t]=$n; rbEscribir($f,"\n",$bytes);
        }
        rbEscribir($f,"SET SQL_MODE=@RB_MODE;\nSET FOREIGN_KEY_CHECKS=@RB_FK;\nSET time_zone=@RB_TZ;\n",$bytes);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    finally { $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered); fclose($f); $pdo->exec('SET time_zone='.$pdo->quote($zona)); }
    return ['tablas'=>$conteos,'referencias'=>array_keys($refs)];
}
function rbImagenes(string $temporal,array $refs,int &$bytes): array {
    $archivos=[]; $raiz=dirname(__DIR__);
    foreach (['productos/imagenes','assets/marca'] as $carpeta) {
        $origen=$raiz.'/'.$carpeta;
        if (!is_dir($origen) || is_link($origen) || realpath($origen)!==realpath($raiz).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$carpeta)) throw new RuntimeException('Las carpetas de imágenes deben ser directorios locales, sin enlaces simbólicos.');
        $destino=$temporal.'/'.$carpeta;
        if (!mkdir($destino,0700,true)) throw new RuntimeException('No se pudieron preparar las imágenes.');
        $it=new DirectoryIterator($origen);
        foreach ($it as $f) {
            $nombre=$f->getFilename();
            if (!preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif|webp)$/iD',$nombre) || $f->isLink() || !$f->isFile()) continue;
            if (count($archivos)>=RB_MAX_IMAGENES) throw new RuntimeException('Hay más de 10000 imágenes. Utiliza un respaldo del servidor.');
            $ruta=$f->getPathname(); $antes=lstat($ruta);
            if (!$antes || $antes['size']>RB_LIMITE-$bytes || !@getimagesize($ruta)) throw new RuntimeException('Una imagen no es válida o el respaldo supera 250 MiB. Revisa tus archivos.');
            $in=fopen($ruta,'rb'); $out=fopen($destino.'/'.$nombre,'x+b');
            if (!$in || !$out) { if (is_resource($in)) fclose($in); if (is_resource($out)) fclose($out); throw new RuntimeException('No se pudo copiar una imagen. Revisa los permisos.'); }
            @chmod($destino.'/'.$nombre,0600);
            try {
                $abierto=fstat($in);
                if (!$abierto || $abierto['ino']!==$antes['ino'] || $abierto['dev']!==$antes['dev']) throw new RuntimeException('Las imágenes cambiaron durante el respaldo. Reintenta sin editar el catálogo.');
                $copiados=0;
                while (!feof($in)) { $bloque=fread($in,65536); if ($bloque===false) throw new RuntimeException('No se pudo leer una imagen.'); rbEscribir($out,$bloque,$bytes); $copiados+=strlen($bloque); }
            } finally { fclose($in); fclose($out); }
            clearstatcache(true,$ruta); $despues=lstat($ruta);
            if (!$despues || is_link($ruta) || $antes['ino']!==$despues['ino'] || $antes['size']!==$copiados || $antes['size']!==$despues['size'] || $antes['mtime']!==$despues['mtime']) throw new RuntimeException('Las imágenes cambiaron durante el respaldo. Reintenta sin editar el catálogo.');
            $archivos[$carpeta.'/'.$nombre]=['bytes'=>$copiados,'sha256'=>hash_file('sha256',$destino.'/'.$nombre)];
        }
    }
    if (array_diff($refs,array_keys($archivos))) throw new RuntimeException('Faltan imágenes usadas por productos o por el logo. Revisa los archivos y vuelve a respaldar.');
    return $archivos;
}
function rbPreparar(PDO $pdo,string $tipo,string $temporal): array {
    if (!in_array($tipo,['completo','sql','imagenes'],true)) throw new RuntimeException('Tipo de respaldo no válido.');
    if ($tipo!=='sql' && !class_exists(ZipArchive::class)) throw new RuntimeException('Activa la extensión zip en php.ini y reinicia Apache para descargar archivos ZIP.');
    $bytes=0; $datos=['tablas'=>[],'referencias'=>[]]; $archivos=[];
    if ($tipo!=='imagenes') $datos=rbSql($pdo,$temporal.'/base_datos.sql',$bytes);
    else {
        $refs=[];
        foreach ($pdo->query('SELECT imagen FROM productos')->fetchAll() as $p) rbReferencia('productos/imagenes',$p['imagen'],$refs);
        $logo=$pdo->query('SELECT logo FROM configuracion_tienda WHERE id=1')->fetchColumn();
        rbReferencia('assets/marca',$logo===false?null:$logo,$refs); $datos['referencias']=array_keys($refs);
    }
    $marca=gmdate('Ymd_His').'Z';
    if ($tipo==='sql') return ['ruta'=>$temporal.'/base_datos.sql','nombre'=>'respaldo_base_'.$marca.'.sql','mime'=>'application/sql','sha256'=>hash_file('sha256',$temporal.'/base_datos.sql')];
    $archivos=rbImagenes($temporal,$datos['referencias'],$bytes);
    if ($tipo==='completo') $archivos['base_datos.sql']=['bytes'=>filesize($temporal.'/base_datos.sql'),'sha256'=>hash_file('sha256',$temporal.'/base_datos.sql')];
    $manifest=['version'=>RB_VERSION,'tipo'=>$tipo,'creado_utc'=>gmdate('c'),'base_origen'=>$pdo->query('SELECT DATABASE()')->fetchColumn(),'tablas'=>(object)$datos['tablas'],'archivos'=>(object)$archivos,
        'nota'=>'Sin código PHP, contraseñas SMTP ni configuración de conexión. No es una copia cifrada. Mantén el catálogo sin cambios durante el respaldo.'];
    $zip=new ZipArchive(); $ruta=$temporal.'/respaldo.zip';
    if ($zip->open($ruta,ZipArchive::CREATE | ZipArchive::OVERWRITE)!==true) throw new RuntimeException('No se pudo crear el ZIP.');
    $cerrado=false;
    try {
        foreach ($archivos as $nombre=>$info) if (!$zip->addFile($temporal.'/'.$nombre,$nombre)) throw new RuntimeException('No se pudo agregar un archivo al ZIP.');
        if (!$zip->addFromString('manifest.json',json_encode($manifest,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) throw new RuntimeException('No se pudo crear el manifiesto.');
        if (!$zip->addFile(__DIR__.'/RESTAURAR.md','RESTAURAR.md')) throw new RuntimeException('No se pudo incluir la guía.');
        $cerrado=$zip->close();
        if (!$cerrado || !is_file($ruta) || filesize($ruta)>RB_LIMITE) throw new RuntimeException('No se pudo finalizar el ZIP o supera 250 MiB. Revisa espacio y permisos.');
    } finally { if (!$cerrado) { unset($zip); } }
    @chmod($ruta,0600);
    return ['ruta'=>$ruta,'nombre'=>'respaldo_'.$tipo.'_'.$marca.'.zip','mime'=>'application/zip','sha256'=>hash_file('sha256',$ruta)];
}
