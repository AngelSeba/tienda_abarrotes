-- SOLO para una base antigua sin el módulo de carrito.
-- NO ejecutar después de database.sql ni si precio ya existe.
-- MySQL / MariaDB. HAZ RESPALDO ANTES DE EJECUTAR.
-- Ejecuta la siguiente línea SOLO si productos aún no tiene columna precio.
ALTER TABLE productos ADD COLUMN precio DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Pon los precios reales antes de permitir comprar.
-- Ejemplo: UPDATE productos SET precio = 20.00 WHERE id = 1;

CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_nombre VARCHAR(255) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'confirmado',
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pedidos_usuario (usuario_nombre)
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
