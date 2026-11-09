-- Selecciona la base de TU tienda. Respáldala y pausa compras/ediciones primero.
-- Importación repetible. No reconstruye tablas ni elimina datos existentes.
SET @inv_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='productos' AND COLUMN_NAME='stock_minimo'), 'SELECT 1', 'ALTER TABLE productos ADD COLUMN stock_minimo INT UNSIGNED NOT NULL DEFAULT 5');
PREPARE inv_stmt FROM @inv_sql;
EXECUTE inv_stmt;
DEALLOCATE PREPARE inv_stmt;
CREATE TABLE IF NOT EXISTS movimientos_inventario (
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
INSERT INTO movimientos_inventario(producto_id,producto_nombre,tipo,cantidad,anterior,saldo,motivo,usuario_nombre,pedido_id)
SELECT p.id,p.nombre,'inicial',p.stock,0,p.stock,'Saldo existente al activar versión 1.7','Migración 1.7',0
FROM productos p WHERE NOT EXISTS(SELECT 1 FROM movimientos_inventario m WHERE m.producto_id=p.id AND m.tipo='inicial');
CREATE TABLE IF NOT EXISTS correos_pendientes (
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
CREATE TABLE IF NOT EXISTS tickets_cache (
 pedido_id INT NOT NULL PRIMARY KEY,
 firma CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 pdf MEDIUMBLOB NOT NULL,
 creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
SELECT 'Actualización 1.7 completada. Configura el ejecutor de correo.' AS resultado;
