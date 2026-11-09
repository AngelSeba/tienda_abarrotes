-- INSTALACIÓN NUEVA: importar una sola vez desde phpMyAdmin.
-- No importar sobre una base con datos. Para migrar datos de Laragon, ver LEEME_XAMPP.md.
CREATE DATABASE IF NOT EXISTS tienda_refrescos
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE tienda_refrescos;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('usuario','administrador') NOT NULL DEFAULT 'usuario',
    sesion_version INT UNSIGNED NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 ) ENGINE=InnoDB;

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    imagen VARCHAR(255) DEFAULT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL
 ) ENGINE=InnoDB;

CREATE TABLE bitacora (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    accion VARCHAR(255) NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL
 ) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    usuario_nombre VARCHAR(255) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'confirmado',
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pedidos_usuario_id (usuario_id),
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedido_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    producto_id INT NOT NULL,
    producto_nombre VARCHAR(255) NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE RESTRICT,
    INDEX idx_detalles_pedido (pedido_id)
) ENGINE=InnoDB;

-- Usuarios de prueba.
-- Contraseña para ambos: password
INSERT INTO usuarios (nombre, correo, password, rol) VALUES
('Administrador', 'admin@tienda.com', '$2y$10$rwnvsbXZW8JFQPMnjD0VueL20L7XauJ1oh1DwALhPvIIDKXIz70DC', 'administrador'),
('Usuario', 'usuario@tienda.com', '$2y$10$rwnvsbXZW8JFQPMnjD0VueL20L7XauJ1oh1DwALhPvIIDKXIz70DC', 'usuario');

INSERT INTO productos (nombre, stock, precio, imagen, fecha, hora) VALUES
('Coca-Cola 600ml', 20, 20.00, NULL, CURDATE(), CURTIME()),
('Sprite 600ml', 15, 18.00, NULL, CURDATE(), CURTIME()),
('Pepsi 600ml', 30, 19.00, NULL, CURDATE(), CURTIME()),
('Fanta Naranja 600ml', 12, 18.00, NULL, CURDATE(), CURTIME());

-- Selecciona la base de tu tienda en phpMyAdmin antes de importar.
-- Actualización 1.3: añade configuración. No modifica usuarios, pedidos o productos.
-- Puede repetirse: no sobrescribe una configuración ya guardada.
CREATE TABLE IF NOT EXISTS configuracion_tienda (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL DEFAULT '',
    telefono VARCHAR(30) NOT NULL DEFAULT '',
    direccion VARCHAR(255) NOT NULL DEFAULT '',
    logo VARCHAR(50) DEFAULT NULL,
    color_principal CHAR(7) NOT NULL DEFAULT '#20a86b',
    color_fondo CHAR(7) NOT NULL DEFAULT '#0b0d0c',
    version INT UNSIGNED NOT NULL DEFAULT 0,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracion_tienda (id,nombre)
SELECT 1,'Tienda de Refrescos'
WHERE NOT EXISTS (SELECT 1 FROM configuracion_tienda WHERE id=1);

CREATE TABLE IF NOT EXISTS autenticacion_intentos (
    clave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    fallos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    inicio BIGINT UNSIGNED NOT NULL,
    bloqueo_hasta BIGINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_intentos_inicio (inicio)
) ENGINE=InnoDB;
