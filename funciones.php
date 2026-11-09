<?php
require_once __DIR__ . '/seguridad.php';
if (session_status() === PHP_SESSION_NONE) {
    session_name('tienda_'.substr(hash('sha256',__DIR__),0,12));
    ini_set('session.use_only_cookies','1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime',(string)AUTH_INACTIVIDAD);
    $rutaCookie=rtrim(str_replace('\\','/',dirname(rutaInicio())),'/').'/';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => $rutaCookie,
    ]);
    session_start();
}

function rutaInicio(): string {
    $raiz=rtrim(str_replace('\\','/',(string)($_SERVER['DOCUMENT_ROOT'] ?? '')),'/');
    $proyecto=str_replace('\\','/',__DIR__);
    if ($raiz!=='' && strcasecmp($proyecto,$raiz)===0) $base='';
    elseif ($raiz!=='' && strncasecmp($proyecto,$raiz.'/',strlen($raiz)+1)===0) $base=substr($proyecto,strlen($raiz));
    else $base='/'.basename(__DIR__);
    return $base.'/index.php';
}
function protegerSesion(): void {
    global $pdo;
    if (!isset($_SESSION['usuario']['id'])) authSalir('inicio');
    if (!isset($pdo)) require __DIR__ . '/conexion.php';
    $estado=authEstadoSesion($pdo,true);
    if (!$estado['vigente']) authSalir($estado['motivo']);
    header('Cache-Control: private, no-store');
}
function esAdministrador(): bool {
    return ($_SESSION['usuario']['rol'] ?? '') === 'administrador';
}
function protegerAdministrador(): void {
    protegerSesion();
    if (!esAdministrador()) {
        http_response_code(403);
        exit('Acceso denegado. Solo administradores.');
    }
}
function tokenCsrf(): string {
    if (empty($_SESSION['csrf_general']) || !is_string($_SESSION['csrf_general'])) {
        $_SESSION['csrf_general'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_general'];
}
function campoCsrf(): string {
    return '<input type="hidden" name="csrf_general" value="' . tokenCsrf() . '">';
}
function validarCsrf(): void {
    $esperado = $_SESSION['csrf_general'] ?? null;
    $recibido = $_POST['csrf_general'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($esperado)
        || $esperado === '' || !is_string($recibido) || !hash_equals($esperado, $recibido)) {
        http_response_code(403);
        exit('Solicitud no válida. Recarga la página e inténtalo de nuevo.');
    }
}
function validarCsrfCarrito(): void {
    $esperado = $_SESSION['csrf_carrito'] ?? null;
    $recibido = $_POST['csrf'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($esperado)
        || $esperado === '' || !is_string($recibido) || !hash_equals($esperado, $recibido)) {
        http_response_code(403);
        exit('Solicitud no válida. Recarga el carrito e inténtalo de nuevo.');
    }
}
function textoPost(string $clave): string {
    return isset($_POST[$clave]) && is_string($_POST[$clave]) ? $_POST[$clave] : '';
}
function botonCerrarSesion(string $ruta = 'logout.php'): string {
    $base=substr($ruta,0,-strlen('logout.php'));
    return '<span class="accesos-cuenta"><a class="cuenta-enlace" href="'.htmlspecialchars($base.'cuenta/index.php',ENT_QUOTES,'UTF-8').'">Mi cuenta</a>'
        . '<form class="salir-form" method="post" action="' . htmlspecialchars($ruta, ENT_QUOTES, 'UTF-8') . '">'
        . campoCsrf() . '<button class="salir-boton" type="submit">Cerrar sesión</button></form></span>';
}
function recursosSesion(string $prefijo=''): string {
    if (!isset($_SESSION['usuario'],$_SESSION['ultima_actividad'])) return '';
    $restante=max(0,AUTH_INACTIVIDAD-(time()-(int)$_SESSION['ultima_actividad']));
    return '<script defer src="'.htmlspecialchars($prefijo.'sesion.js',ENT_QUOTES,'UTF-8').'" data-sesion="1" data-restante="'.$restante.'" data-endpoint="'.htmlspecialchars($prefijo.'sesion.php',ENT_QUOTES,'UTF-8').'" data-inicio="'.htmlspecialchars($prefijo.'index.php',ENT_QUOTES,'UTF-8').'" data-csrf="'.tokenCsrf().'"></script>';
}
function registrarBitacora(PDO $pdo, string $accion): void {
    if (!isset($_SESSION['usuario'])) return;
    $stmt = $pdo->prepare('INSERT INTO bitacora (nombre, correo, accion, fecha, hora) VALUES (?, ?, ?, CURDATE(), CURTIME())');
    $stmt->execute([$_SESSION['usuario']['nombre'], $_SESSION['usuario']['correo'], $accion]);
}

require_once __DIR__ . '/tienda_funciones.php';
