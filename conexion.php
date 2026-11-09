<?php
if (!is_file(__DIR__.'/config_database.php') || !is_file(__DIR__.'/instalado.lock')) {
    http_response_code(503);
    exit('La instalación no está completa. Abre install/ o revisa la guía de instalación.');
}
$config = require __DIR__ . '/config_database.php';
try {
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';port=' . (int)$config['puerto']
        . ';dbname=' . $config['base_datos'] . ';charset=utf8mb4',
        $config['usuario'],
        $config['clave'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Conexión tienda: no disponible; código ' . $e->getCode());
    http_response_code(503);
    exit('No se pudo conectar a la base de datos. Inicia MySQL o MariaDB en Laragon/XAMPP y revisa config_database.php.');
}
