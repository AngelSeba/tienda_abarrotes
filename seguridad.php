<?php
const AUTH_INACTIVIDAD = 1800; // 30 minutos, controlados por el servidor.
const AUTH_VENTANA = 900;      // 15 minutos.
const AUTH_FALLOS_CORREO = 5;
const AUTH_FALLOS_IP = 30;
const AUTH_FALLOS_CAMBIO = 5;
const AUTH_HASH_OPCIONES = ['cost'=>12];

function authHash(string $clave): string {
    return password_hash($clave,PASSWORD_BCRYPT,AUTH_HASH_OPCIONES);
}
function authClaveNuevaValida(string $clave): bool {
    return mb_check_encoding($clave,'UTF-8') && mb_strlen($clave,'UTF-8')>=15
        && strlen($clave)<=72 && strpos($clave,"\0")===false;
}
function authIp(): string {
    $ip=$_SERVER['REMOTE_ADDR'] ?? '';
    $bin=is_string($ip) ? @inet_pton($ip) : false;
    return $bin!==false ? inet_ntop($bin) : 'desconocida';
}
function authClaveLimite(string $tipo,string $valor): string { return hash('sha256',$tipo.':'.$valor); }
/** Llamar dentro de una transacción. Las claves se bloquean siempre en orden. */
function authLimites(PDO $pdo,array $claves,int $ahora): array {
    ksort($claves,SORT_STRING); $filas=[];
    $crear=$pdo->prepare('INSERT INTO autenticacion_intentos (clave,fallos,inicio,bloqueo_hasta) VALUES (?,0,?,0) ON DUPLICATE KEY UPDATE clave=clave');
    $leer=$pdo->prepare('SELECT fallos,inicio,bloqueo_hasta FROM autenticacion_intentos WHERE clave=? FOR UPDATE');
    $reiniciar=$pdo->prepare('UPDATE autenticacion_intentos SET fallos=0,inicio=?,bloqueo_hasta=0 WHERE clave=?');
    foreach ($claves as $clave=>$limite) {
        $crear->execute([$clave,$ahora]); $leer->execute([$clave]); $fila=$leer->fetch(PDO::FETCH_ASSOC);
        $bloqueo=(int)$fila['bloqueo_hasta'];
        $vencio=$bloqueo>0 ? $ahora >= $bloqueo : $ahora-(int)$fila['inicio']>=AUTH_VENTANA;
        if ($vencio) {
            $reiniciar->execute([$ahora,$clave]); $fila=['fallos'=>0,'inicio'=>$ahora,'bloqueo_hasta'=>0];
        }
        $filas[$clave]=array_merge($fila,['limite'=>$limite]);
    }
    return $filas;
}
function authEspera(array $filas,int $ahora): int {
    $espera=0;
    foreach ($filas as $fila) $espera=max($espera,(int)$fila['bloqueo_hasta']-$ahora);
    return max(0,$espera);
}
function authFallo(PDO $pdo,array $filas,int $ahora): int {
    $q=$pdo->prepare('UPDATE autenticacion_intentos SET fallos=?,bloqueo_hasta=? WHERE clave=?'); $espera=0;
    foreach ($filas as $clave=>$fila) {
        $fallos=(int)$fila['fallos']+1;
        $hasta=$fallos >= $fila['limite'] ? $ahora+AUTH_VENTANA : 0;
        $q->execute([$fallos,$hasta,$clave]); $espera=max($espera,$hasta-$ahora);
    }
    return max(0,$espera);
}
function authReiniciarLimite(PDO $pdo,string $clave,int $ahora): void {
    $pdo->prepare('UPDATE autenticacion_intentos SET fallos=0,inicio=?,bloqueo_hasta=0 WHERE clave=?')->execute([$ahora,$clave]);
}
function authLimpiarLimites(PDO $pdo): void {
    try { $pdo->exec('DELETE FROM autenticacion_intentos WHERE inicio < UNIX_TIMESTAMP()-86400 AND bloqueo_hasta < UNIX_TIMESTAMP() LIMIT 100'); }
    catch (PDOException $e) { error_log('No se pudieron retirar contadores de acceso antiguos.'); }
}
/** Mensajes no distinguen correos existentes de inexistentes. */
function authIntentarAcceso(PDO $pdo,string $correo,string $clave,string $ip): array {
    $correo=mb_strtolower(trim($correo),'UTF-8');
    $correoClave=authClaveLimite('correo',$correo);
    $ipClave=authClaveLimite('ip',$ip);
    try {
        $pdo->beginTransaction(); $ahora=(int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
        $limites=authLimites($pdo,[$correoClave=>AUTH_FALLOS_CORREO,$ipClave=>AUTH_FALLOS_IP],$ahora);
        $espera=authEspera($limites,$ahora);
        if ($espera>0) { $pdo->commit(); return ['ok'=>false,'espera'=>$espera]; }
        $q=$pdo->prepare('SELECT id,nombre,correo,password,rol,sesion_version FROM usuarios WHERE correo=? FOR UPDATE');
        $q->execute([$correo]); $usuario=$q->fetch(PDO::FETCH_ASSOC);
        // Un hash válido también se verifica cuando no existe la cuenta.
        $ficticio='$2y$12$UNIOp.cfdNKK8qMkDIlPPe3bcjeTyD6OU1svdev3.voq7.h4GyT2O';
        $valida=strlen($clave)<=4096 && strpos($clave,"\0")===false
            && password_verify($clave,$usuario ? $usuario['password'] : $ficticio);
        if (!$usuario || !$valida) {
            $espera=authFallo($pdo,$limites,$ahora); $pdo->commit();
            authLimpiarLimites($pdo); return ['ok'=>false,'espera'=>$espera];
        }
        if (password_needs_rehash($usuario['password'],PASSWORD_BCRYPT,AUTH_HASH_OPCIONES)) {
            // La clave sigue siendo la misma: rehash no invalida otras sesiones.
            $pdo->prepare('UPDATE usuarios SET password=? WHERE id=?')->execute([authHash($clave),$usuario['id']]);
        }
        authReiniciarLimite($pdo,$correoClave,$ahora); // No limpia los fallos globales por IP.
        $q=$pdo->prepare("INSERT INTO bitacora (nombre,correo,accion,fecha,hora) VALUES (?,?,'Inicio de sesión',CURDATE(),CURTIME())");
        $q->execute([$usuario['nombre'],$usuario['correo']]);
        $pdo->commit(); authLimpiarLimites($pdo);
        unset($usuario['password']); return ['ok'=>true,'usuario'=>$usuario];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); throw $e;
    }
}
function authMensaje(string $motivo): string {
    return [
        'inactividad'=>'Tu sesión terminó por inactividad. Inicia sesión para continuar.',
        'actualizada'=>'La seguridad de acceso se actualizó. Vuelve a iniciar sesión.',
        'revocada'=>'La contraseña o los permisos de tu cuenta cambiaron. Vuelve a iniciar sesión.',
        'sin_cuenta'=>'Tu cuenta ya no está disponible. Consulta al administrador.',
        'clave_cambiada'=>'Contraseña actualizada. Se cerraron todas tus sesiones. Entra con la nueva contraseña.',
        'salida'=>'Cerraste sesión correctamente.',
        'inicio'=>'Inicia sesión para continuar.',
    ][$motivo] ?? 'Inicia sesión para continuar.';
}
function authLimpiarSesion(string $motivo): void {
    $_SESSION=[];
    session_regenerate_id(true);
    $_SESSION['aviso_acceso']=authMensaje($motivo);
}
function authSalir(string $motivo): void {
    authLimpiarSesion($motivo);
    header('Location: '.rutaInicio(),true,($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST' ? 303 : 302); exit;
}
/** Lectura sin renovar para la comprobación del navegador; solo una acción renueva. */
function authEstadoSesion(PDO $pdo,bool $renovar): array {
    $id=filter_var($_SESSION['usuario']['id'] ?? null,FILTER_VALIDATE_INT);
    if (!$id || $id<1) return ['vigente'=>false,'motivo'=>'inicio'];
    $version=$_SESSION['sesion_version'] ?? null; $ultima=$_SESSION['ultima_actividad'] ?? null;
    if (!is_int($version) || !is_int($ultima)) return ['vigente'=>false,'motivo'=>'actualizada'];
    $ahora=time();
    if ($ahora-$ultima>=AUTH_INACTIVIDAD || $ultima>$ahora+60) return ['vigente'=>false,'motivo'=>'inactividad'];
    $q=$pdo->prepare('SELECT id,nombre,correo,rol,sesion_version FROM usuarios WHERE id=?'); $q->execute([$id]); $usuario=$q->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) return ['vigente'=>false,'motivo'=>'sin_cuenta'];
    if ((int)$usuario['sesion_version']!==$version) return ['vigente'=>false,'motivo'=>'revocada'];
    unset($usuario['sesion_version']); $_SESSION['usuario']=$usuario;
    if ($renovar) $_SESSION['ultima_actividad']=$ahora;
    return ['vigente'=>true,'restante'=>max(0,AUTH_INACTIVIDAD-($ahora-$_SESSION['ultima_actividad']))];
}
function authCambiarClave(PDO $pdo,int $id,int $version,string $actual,string $nueva): array {
    if (!authClaveNuevaValida($nueva)) throw new RuntimeException('Usa al menos 15 caracteres y como máximo 72 bytes. Con muchos acentos o emojis, reduce la longitud.');
    if (strlen($actual)>4096 || strpos($actual,"\0")!==false) throw new RuntimeException('La contraseña actual no es válida.');
    $clave=authClaveLimite('cambio',(string)$id);
    try {
        $pdo->beginTransaction(); $ahora=(int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
        $limites=authLimites($pdo,[$clave=>AUTH_FALLOS_CAMBIO],$ahora); $espera=authEspera($limites,$ahora);
        if ($espera>0) { $pdo->commit(); return ['ok'=>false,'espera'=>$espera]; }
        $q=$pdo->prepare('SELECT id,password,sesion_version FROM usuarios WHERE id=? FOR UPDATE'); $q->execute([$id]); $usuario=$q->fetch(PDO::FETCH_ASSOC);
        if (!$usuario || (int)$usuario['sesion_version']!==$version) { $pdo->rollBack(); return ['ok'=>false,'revocada'=>true]; }
        if (!password_verify($actual,$usuario['password'])) {
            $espera=authFallo($pdo,$limites,$ahora); $pdo->commit(); return ['ok'=>false,'espera'=>$espera];
        }
        authReiniciarLimite($pdo,$clave,$ahora);
        if (password_verify($nueva,$usuario['password'])) { $pdo->commit(); throw new RuntimeException('Elige una contraseña diferente de la actual.'); }
        $q=$pdo->prepare('UPDATE usuarios SET password=?,sesion_version=sesion_version+1 WHERE id=? AND sesion_version=?');
        $q->execute([authHash($nueva),$id,$version]);
        if ($q->rowCount()!==1) throw new RuntimeException('Tu cuenta cambió. Vuelve a iniciar sesión.');
        registrarBitacora($pdo,'Cambió su contraseña y revocó sus sesiones.');
        $pdo->commit(); return ['ok'=>true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); throw $e;
    }
}
