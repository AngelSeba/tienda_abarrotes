-- Actualización 1.9. Selecciona primero la base de datos de TU tienda en phpMyAdmin.
-- Repetible: conserva productos, pedidos, correos y configuración existente.
SET @qr19_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
     AND TABLE_NAME='configuracion_tienda' AND COLUMN_NAME='url_publica')=0,
  CONCAT('ALTER TABLE configuracion_tienda ADD COLUMN url_publica VARCHAR(255) NOT NULL DEFAULT ',CHAR(39),CHAR(39)),
  'SELECT 1 AS qr_19_columna_existente'
);
PREPARE qr19_stmt FROM @qr19_sql;
EXECUTE qr19_stmt;
DEALLOCATE PREPARE qr19_stmt;
