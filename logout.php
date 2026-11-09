<?php
require_once __DIR__ . '/funciones.php';
validarCsrf();
if (isset($_SESSION['usuario'])) {
    protegerSesion();
    try { registrarBitacora($pdo,'Cierre de sesión'); }
    catch (Throwable $e) { error_log('No se pudo registrar el cierre de sesión.'); }
}
authSalir('salida');
