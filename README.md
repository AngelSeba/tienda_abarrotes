# Tienda PHP · versión 1.9

Sistema web de catálogo y compras con panel de administración. Desarrollado en
PHP y MySQL/MariaDB; interfaz en español y precios en MXN.

## Funciones

- Acceso por roles: administrador y cliente; protección CSRF y sesiones.
- Catálogo con precios, existencias e imágenes; carrito y pedidos.
- Tickets PDF descargables y enviados por correo mediante una cola de tareas.
- Inventario e historial de movimientos, pedidos y cancelaciones.
- Gráficas y reportes de ventas; exportación CSV y PDF.
- Personalización del negocio, respaldos SQL/imágenes y recuperación.
- QR por producto en carrito y tickets; ficha pública con imagen, precio y stock
  actuales. El ticket conserva los importes históricos de la compra.

## Ejecutar

Usa Apache con PHP 8.0+ y MySQL 8.0+ o MariaDB 10.4+. Lee
[LEEME_INSTALACION.md](LEEME_INSTALACION.md) para instalar una tienda vacía.
El paquete distribuido por ZIP incluye `vendor/` y el instalador completo 1.9.

Si obtuviste el código desde un repositorio que excluye `vendor/`, ejecuta en
esta carpeta:

```
composer install --no-dev --prefer-dist
```

Copia `config_correo.example.php` a `config_correo.php` antes de configurar SMTP.
`config_database.php` e `instalado.lock` los crea el asistente; no se publican.
No existen cuentas con una contraseña predeterminada.

## Estructura

| Carpeta | Responsabilidad |
| --- | --- |
| `install/` | Instalador para bases nuevas y vacías |
| `productos/` | Catálogo, carrito, tickets y ficha pública |
| `pedidos/` | Administración de pedidos |
| `inventario/` | Existencias e historial |
| `correos/`, `tareas/` | Cola y ejecutor de correo |
| `reportes/` | Exportaciones de ventas |
| `configuracion/` | Identidad y URL de QR |
| `respaldos/` | Exportación y guía de recuperación |
| `qr/`, `lib/qr/` | Generación local de QR |
| `migraciones/` | Mantenimiento de instalaciones anteriores |

## GitHub y demostración

Este proyecto puede almacenarse en un repositorio. GitHub Pages no ejecuta su
backend PHP ni su base de datos: para una demostración funcional necesita un
servidor con PHP/MySQL y un ejecutor periódico para la cola de correos.

El `.gitignore` excluye configuraciones privadas, respaldos, imágenes subidas por
usuarios y datos locales. No elimina archivos que ya se hayan publicado antes.
No agregues bases de datos reales, contraseñas ni tickets de clientes al repo.

## Dependencias

Dompdf y PHPMailer se reconstruyen mediante `composer.lock` en Git. El ZIP ya
incluye las bibliotecas para instalarlo sin Composer. El generador QR tiene su
propia autocarga y sus licencias: consulta [TERCEROS_QR.md](TERCEROS_QR.md).
Las licencias de terceros se conservan en sus respectivos directorios. Este
paquete no decide una licencia de distribución para tu código propio.
