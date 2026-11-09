<?php
require_once __DIR__ . '/funciones.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { header('Location: index.php'); exit; }
validarCsrf();
if (isset($_SESSION['usuario'])) { protegerSesion(); header('Location: dashboard.php'); exit; }
require_once __DIR__ . '/conexion.php';
$correo=trim(textoPost('correo')); $password=textoPost('password');
if (!filter_var($correo,FILTER_VALIDATE_EMAIL) || strlen($correo)>150 || $password==='' || strlen($password)>4096) {
    $_SESSION['error']='Completa correctamente el correo y la contraseña.';
    header('Location: index.php',true,303); exit;
}
try {
    $resultado=authIntentarAcceso($pdo,$correo,$password,authIp());
    if ($resultado['ok']) {
        $usuario=$resultado['usuario']; $version=(int)$usuario['sesion_version']; unset($usuario['sesion_version']);
        $_SESSION=[]; session_regenerate_id(true);
        $_SESSION['usuario']=$usuario; $_SESSION['sesion_version']=$version; $_SESSION['ultima_actividad']=time();
        tokenCsrf(); header('Location: dashboard.php',true,303); exit;
    }
    if ($resultado['espera']>0) {
        http_response_code(429); header('Retry-After: '.(int)$resultado['espera']);
        $_SESSION['error']='Demasiados intentos. Espera unos minutos antes de volver a intentarlo.';
        require __DIR__ . '/index.php'; exit;
    }
    $_SESSION['error']='Correo o contraseña incorrectos.';
    header('Location: index.php',true,303); exit;
} catch (Throwable $e) {
    error_log('No se pudo completar el acceso: '.$e->getMessage());
    http_response_code(503);
    $_SESSION['error']='No fue posible iniciar sesión. Consulta al administrador o comprueba que se instaló la actualización 1.4.';
    require __DIR__ . '/index.php'; exit;
}
