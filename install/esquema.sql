-- Esquema controlado para instalaciones nuevas; ejecutar mediante el asistente.
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
    stock_minimo INT UNSIGNED NOT NULL DEFAULT 5,
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

CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    usuario_nombre VARCHAR(255) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'confirmado',
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pedidos_usuario_id (usuario_id),
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE pedido_detalles (
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

CREATE TABLE configuracion_tienda (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL DEFAULT '',
    telefono VARCHAR(30) NOT NULL DEFAULT '',
    direccion VARCHAR(255) NOT NULL DEFAULT '',
    url_publica VARCHAR(255) NOT NULL DEFAULT '',
    logo VARCHAR(50) DEFAULT NULL,
    color_principal CHAR(7) NOT NULL DEFAULT '#20a86b',
    color_fondo CHAR(7) NOT NULL DEFAULT '#0b0d0c',
    version INT UNSIGNED NOT NULL DEFAULT 0,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE autenticacion_intentos (
    clave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    fallos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    inicio BIGINT UNSIGNED NOT NULL,
    bloqueo_hasta BIGINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_intentos_inicio (inicio)
) ENGINE=InnoDB;

CREATE TABLE sistema_instalacion (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    instancia CHAR(64) NOT NULL,
    version VARCHAR(16) NOT NULL,
    completada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE movimientos_inventario (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 producto_id INT NOT NULL,
 producto_nombre VARCHAR(100) NOT NULL,
 tipo VARCHAR(20) NOT NULL,
 cantidad INT NOT NULL,
 anterior INT NOT NULL,
 saldo INT NOT NULL,
 motivo VARCHAR(200) NOT NULL,
 usuario_id INT NULL,
 usuario_nombre VARCHAR(100) NOT NULL,
 pedido_id INT NULL,
 operacion CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_mov_pedido(tipo,pedido_id,producto_id),
 INDEX idx_mov_producto(producto_id,id), INDEX idx_mov_fecha(fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE correos_pendientes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pedido_id INT NOT NULL UNIQUE,
 usuario_id INT NOT NULL,
 correo VARCHAR(150) NOT NULL,
 nombre VARCHAR(100) NOT NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
 intentos INT UNSIGNED NOT NULL DEFAULT 0,
 creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 iniciado_en DATETIME NULL,
 enviado_en DATETIME NULL,
 ultimo_error VARCHAR(200) NOT NULL DEFAULT '',
 INDEX idx_cola_estado(estado,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets_cache (
 pedido_id INT NOT NULL PRIMARY KEY,
 firma CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 pdf MEDIUMBLOB NOT NULL,
 creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
