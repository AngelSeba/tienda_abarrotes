<?php
const INST_VERSION='1.9';
function instRaiz(): string { return dirname(__DIR__); }
function instEsc($valor): string { return htmlspecialchars((string)$valor,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'); }
function instInstalado(): bool { return is_file(instRaiz().'/instalado.lock'); }
function instPendienteRuta(): string { return instRaiz().'/.instalacion_pendiente.php'; }
function instToken(): string {
    if (empty($_SESSION['inst_csrf'])) $_SESSION['inst_csrf']=bin2hex(random_bytes(32));
    return $_SESSION['inst_csrf'];
}
function instCsrf(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '')==='POST' && is_string($_POST['csrf'] ?? null)
        && is_string($_SESSION['inst_csrf'] ?? null) && hash_equals($_SESSION['inst_csrf'],$_POST['csrf']);
}
function instLocal(): bool {
    $ip=$_SERVER['REMOTE_ADDR'] ?? '';
    return $ip==='127.0.0.1' || $ip==='::1' || $ip==='::ffff:127.0.0.1';
}
function instClaveHash(): ?string {
    $archivo=__DIR__.'/clave_instalacion.php';
    if (!is_file($archivo)) return null;
    $valor=require $archivo;
    return is_string($valor) && preg_match('/^[a-f0-9]{64}$/D',$valor) ? $valor : null;
}
function instAutorizado(): bool {
    $hash=instClaveHash();
    if ($hash===null) return instLocal() && !is_file(__DIR__.'/clave_instalacion.php');
    return ($_SESSION['inst_hasta'] ?? 0)>time() && is_string($_SESSION['inst_clave'] ?? null)
        && hash_equals($hash,$_SESSION['inst_clave']);
}
function instRequisitos(): array {
    $r=['PHP 8.0 o posterior'=>PHP_VERSION_ID>=80000];
    foreach (['pdo_mysql','mbstring','dom','gd','fileinfo','openssl','ctype','session','zip'] as $ext) $r['Extensión '.$ext]=extension_loaded($ext);
    $r['GD con JPEG, PNG y WebP']=function_exists('imagecreatefromwebp') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng');
    $r['Biblioteca QR incluida']=is_file(instRaiz().'/lib/qr/autoload.php');
    $r['Librerías incluidas']=is_file(instRaiz().'/vendor/autoload.php');
    if ($r['PHP 8.0 o posterior'] && $r['Librerías incluidas']) {
        try {
            require_once instRaiz().'/vendor/autoload.php';
            $r['Dompdf y PHPMailer disponibles']=class_exists(\Dompdf\Dompdf::class) && class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
        } catch (Throwable $e) { $r['Dompdf y PHPMailer disponibles']=false; }
    }
    foreach ([''=>'Carpeta del proyecto','/install'=>'Carpeta del instalador','/productos/imagenes'=>'Imágenes de productos','/assets/marca'=>'Logo del negocio','/vendor/dompdf/dompdf/lib/fonts'=>'Fuentes PDF'] as $ruta=>$nombre) {
        $r[$nombre.' con escritura']=is_dir(instRaiz().$ruta) && is_writable(instRaiz().$ruta);
    }
    $r['Directorio temporal con escritura']=is_dir(sys_get_temp_dir()) && is_writable(sys_get_temp_dir());
    return $r;
}
function instRequisitosListos(): bool { return !in_array(false,instRequisitos(),true); }
function instTexto(array $entrada,string $clave): string {
    if (!isset($entrada[$clave]) || !is_string($entrada[$clave])) throw new RuntimeException('Revisa los campos del formulario.');
    return $entrada[$clave];
}
function instDatosConexion(array $entrada): array {
    $host=trim(instTexto($entrada,'host')); $base=trim(instTexto($entrada,'base_datos'));
    $usuario=trim(instTexto($entrada,'usuario')); $clave=instTexto($entrada,'clave');
    $puerto=filter_var(instTexto($entrada,'puerto'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
    if (!preg_match('/^[a-z0-9_.:-]{1,253}$/iD',$host) || !preg_match('/^[a-z0-9_]{1,64}$/iD',$base)
        || !$puerto || $usuario==='' || strlen($usuario)>128 || strpos($usuario,"\0")!==false
        || strlen($clave)>512 || strpos($clave,"\0")!==false) {
        throw new RuntimeException('Revisa servidor, puerto y usuario. La base admite hasta 64 letras, números o guiones bajos.');
    }
    return ['host'=>$host,'puerto'=>$puerto,'base_datos'=>$base,'usuario'=>$usuario,'clave'=>$clave];
}
function instPdo(array $cfg,bool $sinBase=false): PDO {
    $dsn='mysql:host='.$cfg['host'].';port='.$cfg['puerto'].';charset=utf8mb4'.($sinBase?'':';dbname='.$cfg['base_datos']);
    return new PDO($dsn,$cfg['usuario'],$cfg['clave'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5,
    ]);
}
function instVersionServidor(PDO $pdo): void {
    $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
    if (stripos($version,'MariaDB')!==false) {
        // Algunas versiones anuncian un prefijo de compatibilidad 5.5.5-.
        $version=preg_replace('/^5\.5\.5-/','',$version);
        if (version_compare($version,'10.4','<')) throw new RuntimeException('Se requiere MariaDB 10.4 o posterior.');
    } elseif (version_compare($version,'8.0','<')) throw new RuntimeException('Se requiere MySQL 8.0 o posterior.');
}
function instObjetos(PDO $pdo): array {
    // Incluye tablas y vistas. No borra ni reutiliza una base con datos previos.
    return $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
}
function instComprobar(array $cfg,bool $crear): string {
    try { $pdo=instPdo($cfg); }
    catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0)===1049 && $crear) {
            $pdo=instPdo($cfg,true); instVersionServidor($pdo);
            return 'Conexión al servidor correcta. La base se creará al instalar si el usuario tiene permiso CREATE DATABASE.';
        }
        throw $e;
    }
    instVersionServidor($pdo);
    if (instObjetos($pdo)) throw new RuntimeException('La base contiene tablas o vistas. Elige una base vacía; no se modificaron sus datos.');
    return 'Conexión correcta y base vacía. Puedes crear el primer administrador.';
}
function instNombreLock(array $cfg): string { return 'tienda_install_'.substr(hash('sha256',strtolower($cfg['base_datos'])),0,40); }
function instBloquearDb(PDO $pdo,array $cfg): void {
    $q=$pdo->prepare('SELECT GET_LOCK(?,0)'); $q->execute([instNombreLock($cfg)]);
    if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Otra instalación está utilizando esa base. Espera y vuelve a intentarlo.');
}
function instLiberarDb(PDO $pdo,array $cfg): void {
    try { $q=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([instNombreLock($cfg)]); }
    catch (Throwable $e) { error_log('No se pudo liberar explícitamente el bloqueo de instalación.'); }
}
function instEscribirPhp(string $archivo,array $datos): void {
    $contenido="<?php\nreturn ".var_export($datos,true).";\n";
    if (is_file($archivo)) {
        if (file_get_contents($archivo)===$contenido) return;
        throw new RuntimeException('Ya existe un archivo de configuración diferente. No se sobrescribió.');
    }
    $temporal=$archivo.'.'.bin2hex(random_bytes(8)).'.tmp.php';
    try {
        if (file_put_contents($temporal,$contenido)!==strlen($contenido)) throw new RuntimeException('No se pudo preparar la configuración. Revisa los permisos.');
        @chmod($temporal,0600);
        if (!rename($temporal,$archivo)) throw new RuntimeException('No se pudo guardar la configuración.');
    } finally { if (is_file($temporal)) @unlink($temporal); }
}
function instPendiente(): ?array {
    if (!is_file(instPendienteRuta())) return null;
    $datos=require instPendienteRuta();
    if (!is_array($datos) || !isset($datos['config'],$datos['instancia'])
        || !is_array($datos['config']) || !is_string($datos['instancia']) || !preg_match('/^[a-f0-9]{64}$/D',$datos['instancia'])) {
        throw new RuntimeException('El registro local de instalación está dañado. Revisa la carpeta antes de continuar.');
    }
    return $datos;
}
function instSoltarMutex(string $propietario): void {
    // Cada ejecución retira solo su propia marca. Su shutdown no puede retirar
    // el mutex de una ejecución posterior que ya haya ocupado la carpeta.
    if (is_file($propietario)) {
        @unlink($propietario);
        @rmdir(dirname($propietario));
    }
}
function instMutex(): string {
    $ruta=__DIR__.'/.ejecucion';
    if (!@mkdir($ruta,0700)) throw new RuntimeException('Hay una instalación en curso o una ejecución interrumpida. Espera; si el proceso terminó, revisa la guía antes de retirar install/.ejecucion.');
    $propietario=$ruta.'/'.bin2hex(random_bytes(16));
    if (file_put_contents($propietario,'1')!==1) { @rmdir($ruta); throw new RuntimeException('No se pudo registrar el bloqueo de instalación.'); }
    register_shutdown_function('instSoltarMutex',$propietario);
    return $propietario;
}
function instFinalizarArchivos(array $pendiente): void {
    instEscribirPhp(instRaiz().'/config_database.php',$pendiente['config']);
    $archivo=instRaiz().'/instalado.lock';
    if (!is_file($archivo)) {
        $datos=json_encode(['version'=>INST_VERSION,'instancia'=>$pendiente['instancia'],'instalada_en'=>gmdate('c')],JSON_UNESCAPED_UNICODE);
        $tmp=instRaiz().'/.cierre_'.bin2hex(random_bytes(8)).'.tmp.php';
        try {
            // Es un marcador sin credenciales; no se incluye código ejecutable.
            if (file_put_contents($tmp,$datos)!==strlen($datos) || !rename($tmp,$archivo)) throw new RuntimeException('La base se preparó, pero no se pudo cerrar el instalador. Utiliza Finalizar instalación pendiente.');
        } finally { if (is_file($tmp)) @unlink($tmp); }
    }
    if (is_file(instPendienteRuta())) @unlink(instPendienteRuta());
    if (is_file(__DIR__.'/clave_instalacion.php')) @unlink(__DIR__.'/clave_instalacion.php');
}
function instEjecutar(array $cfg,array $admin,string $nombreTienda,bool $crear): void {
    $cfg=instDatosConexion(array_merge($cfg,['puerto'=>(string)$cfg['puerto']]));
    if (instInstalado()) throw new RuntimeException('La instalación ya está cerrada.');
    if (is_file(instRaiz().'/config_database.php') || is_file(instPendienteRuta())) throw new RuntimeException('Esta carpeta ya tiene configuración o una preparación pendiente. No se reinstala sobre una tienda existente.');
    if (!instRequisitosListos()) throw new RuntimeException('Primero corrige los requisitos pendientes.');
    require_once instRaiz().'/seguridad.php';
    if ($nombreTienda==='' || mb_strlen($nombreTienda)>100 || preg_match('/[\x00-\x1f\x7f]/',$nombreTienda)
        || $admin['nombre']==='' || mb_strlen($admin['nombre'])>100 || preg_match('/[\x00-\x1f\x7f]/',$admin['nombre'])
        || strlen($admin['correo'])>150 || !filter_var($admin['correo'],FILTER_VALIDATE_EMAIL) || !authClaveNuevaValida($admin['password'])) {
        throw new RuntimeException('Revisa nombre del negocio, administrador y correo. La contraseña necesita 15 caracteres y máximo 72 bytes.');
    }
    $mutex=instMutex(); $pdo=null; $dbLock=false;
    try {
        // Revalidar después de obtener el mutex de la carpeta.
        if (instInstalado() || is_file(instRaiz().'/config_database.php') || is_file(instPendienteRuta())) throw new RuntimeException('Otro proceso ya preparó esta carpeta. Recarga el instalador.');
        try { $pdo=instPdo($cfg); }
        catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0)!==1049 || !$crear) throw $e;
            $pdo=instPdo($cfg,true); instVersionServidor($pdo); instBloquearDb($pdo,$cfg); $dbLock=true;
            // El identificador se validó como ASCII alfanumérico con guiones bajos.
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$cfg['base_datos'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `'.$cfg['base_datos'].'`');
        }
        if (!$dbLock) { instBloquearDb($pdo,$cfg); $dbLock=true; }
        instVersionServidor($pdo);
        if (instObjetos($pdo)) throw new RuntimeException('La base contiene tablas o vistas. No se modificó. Elige otra base vacía.');
        $pendiente=['config'=>$cfg,'instancia'=>bin2hex(random_bytes(32)),'version'=>INST_VERSION];
        instEscribirPhp(instPendienteRuta(),$pendiente);
        $esquema=preg_replace('/^\s*--.*$/m','',file_get_contents(__DIR__.'/esquema.sql'));
        foreach (explode(';',$esquema) as $sql) if (trim($sql)!=='') $pdo->exec($sql);
        $pdo->beginTransaction();
        $q=$pdo->prepare("INSERT INTO usuarios (nombre,correo,password,rol) VALUES (?,?,?,'administrador')");
        $q->execute([$admin['nombre'],$admin['correo'],authHash($admin['password'])]);
        $q=$pdo->prepare('INSERT INTO configuracion_tienda (id,nombre) VALUES (1,?)'); $q->execute([$nombreTienda]);
        $q=$pdo->prepare("INSERT INTO bitacora (nombre,correo,accion,fecha,hora) VALUES (?,?,'Instalación inicial completada',CURDATE(),CURTIME())"); $q->execute([$admin['nombre'],$admin['correo']]);
        $q=$pdo->prepare('INSERT INTO sistema_instalacion (id,instancia,version) VALUES (1,?,?)'); $q->execute([$pendiente['instancia'],INST_VERSION]);
        $pdo->commit();
        instFinalizarArchivos($pendiente);
    } catch (Throwable $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        if ($pdo && $dbLock) instLiberarDb($pdo,$cfg);
        instSoltarMutex($mutex);
    }
}
function instReanudar(): void {
    if (instInstalado()) throw new RuntimeException('La instalación ya está cerrada.');
    if (!instRequisitosListos()) throw new RuntimeException('Corrige los requisitos pendientes antes de finalizar.');
    $mutex=instMutex(); $pdo=null; $pendiente=null; $bloqueo=false;
    try {
        $pendiente=instPendiente();
        if (!$pendiente) throw new RuntimeException('No hay una instalación pendiente.');
        $pdo=instPdo($pendiente['config']); instBloquearDb($pdo,$pendiente['config']); $bloqueo=true;
        $fila=$pdo->query('SELECT instancia,version FROM sistema_instalacion WHERE id=1')->fetch();
        if (!$fila || !hash_equals($pendiente['instancia'],$fila['instancia']) || $fila['version']!==INST_VERSION) {
            throw new RuntimeException('La base no tiene una instalación completada que pertenezca a esta carpeta. No se reutilizó ni modificó.');
        }
        instFinalizarArchivos($pendiente);
    } finally {
        if ($pdo && $bloqueo) instLiberarDb($pdo,$pendiente['config']);
        instSoltarMutex($mutex);
    }
}
