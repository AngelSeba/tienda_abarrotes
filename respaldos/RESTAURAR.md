# Recuperar una tienda desde un respaldo

El respaldo completo contiene `base_datos.sql`, las carpetas `productos/imagenes` y `assets/marca`, `manifest.json` con tamaños y SHA256, y esta guía. Un ZIP de solo imágenes no contiene SQL. El archivo SQL por separado tampoco incluye imágenes.

El archivo no está cifrado. Contiene datos privados, incluidos correos y hashes de contraseñas. Guárdalo fuera de htdocs y no lo publiques ni lo agregues a GitHub. Las credenciales de conexión y SMTP se conservan por separado: no forman parte del respaldo.

## Qué necesitas

- Una copia del código de la misma versión de tu tienda y su carpeta vendor.
- Un respaldo completo, o un SQL más un ZIP de imágenes correspondiente al mismo momento.
- Apache/PHP y una versión compatible del mismo motor de base de datos. La exportación usa SHOW CREATE TABLE del servidor de origen: si cambias entre MariaDB y MySQL o a versiones anteriores, revisa primero compatibilidad de cotejamientos y sintaxis en una copia de prueba.
- Los datos de conexión MySQL y SMTP de la instalación de destino.

## Recuperar en otra carpeta de XAMPP

1. Conserva la tienda original. Copia el código de la tienda a otra carpeta de `C:\xampp\htdocs`, por ejemplo `tienda_recuperada`. Mantén esa copia sin acceso de compradores hasta terminar.
2. En phpMyAdmin crea una base NUEVA y VACÍA, con un nombre diferente, por ejemplo `tienda_recuperada_db`, cotejamiento `utf8mb4_unicode_ci`.
3. Selecciona esa base en el menú izquierdo. Comprueba que no contiene tablas. Usa Importar y selecciona `base_datos.sql`. Espera a la confirmación. Si el archivo excede los límites de phpMyAdmin, ajusta upload_max_filesize/post_max_size y reinicia Apache, o utiliza la herramienta de importación del servidor.
4. Comprueba que aparecen usuarios, productos, bitacora, pedidos, pedido_detalles, configuracion_tienda y autenticacion_intentos. Las tiendas creadas mediante el instalador también incluyen sistema_instalacion.
5. En la pestaña SQL de ESA BASE RECUPERADA ejecuta:

```sql
UPDATE usuarios SET sesion_version = sesion_version + 1;
DELETE FROM autenticacion_intentos;
```

Esto invalida sesiones antiguas y limpia límites de intentos que ya no corresponden al nuevo entorno. No cambia las contraseñas de las cuentas.

6. Copia el contenido de `productos/imagenes` del respaldo a la carpeta del mismo nombre dentro de la copia del código. Copia `assets/marca` de la misma manera. Conserva los nombres originales y los archivos .htaccess del código. No copies el ZIP completo dentro de htdocs. Si el respaldo estaba vacío de imágenes, las carpetas del código seguirán vacías.
7. En la raíz de la copia crea o ajusta `config_database.php` con los datos de la base RECUPERADA. El formato es:

```php
<?php
return [
    'host' => '127.0.0.1',
    'puerto' => 3306,
    'base_datos' => 'tienda_recuperada_db',
    'usuario' => 'root',
    'clave' => '',
];
```

Usa un editor de código y tus propios datos. Si la contraseña contiene comillas o barras, conserva el archivo generado de tu instalación original y modifica solo los campos necesarios utilizando sintaxis PHP válida. No compartas este archivo.

8. Si el código proviene de una instalación 1.5/1.6, conserva su marcador `instalado.lock`. Si proviene del ZIP limpio de instalación y todavía no tiene ese archivo, créalo en la raíz SOLO después de haber importado y comprobado la base, con este contenido:

```json
{"version":"1.6","restaurada":true}
```

No ejecutes el asistente para recuperar datos: está diseñado exclusivamente para bases vacías antes de crear tablas. Un SQL existente se recupera mediante importación. El marcador deja cerrado ese asistente. Después puedes retirar install de la copia recuperada.

9. Configura `config_correo.php` con tus credenciales SMTP guardadas por separado. No envíes un ticket de prueba a clientes reales sin que lo hayas decidido.
10. Abre `http://localhost/tienda_recuperada/`, inicia sesión con una cuenta del respaldo y comprueba productos, imágenes, usuarios, pedidos, totales y descarga de ticket. Verifica el envío de correo con una cuenta de prueba propia cuando configures SMTP.

No importes sobre la base original ni sobre una tienda ya instalada: el SQL no contiene DROP, USE, CREATE DATABASE ni una operación de fusión de datos. Un error al importar puede dejar tablas parciales porque MySQL/MariaDB no deshacen todo el DDL como una sola transacción. Corrige la causa y repite en OTRA base nueva vacía; no continúes sobre una importación parcial.

## Alcance del respaldo

Incluye las siete tablas conocidas de las versiones 1.4+ y, si existe, sistema_instalacion. Una base con tablas o vistas adicionales, disparadores detectables o motores distintos de InnoDB se rechaza para no presentar una exportación incompleta como respaldo de ese esquema personalizado. Usa herramientas de administración para bases personalizadas, rutinas/eventos o instalaciones grandes.

Las filas SQL se leen en una instantánea InnoDB REPEATABLE READ. Las imágenes se copian por separado: durante la preparación evita editar productos, eliminar archivos, cambiar el logo o ejecutar migraciones de esquema. Si falta una imagen referenciada, el respaldo ZIP se rechaza para que revises sus archivos.

El módulo prepara archivos en una carpeta temporal privada fuera del proyecto y del directorio público, descarga por una solicitud autenticada y retira los temporales al terminar normalmente o al cerrar PHP. Una caída del sistema o terminación forzada puede dejar temporales `tienda_respaldo_*` en el directorio temporal del sistema. Comprueba que no haya respaldos activos antes de retirarlos manualmente. No hay una carpeta pública de respaldos.

Límites de esta versión: 250 MiB de contenido preparado o ZIP final, 10000 imágenes y tiempo de ejecución solicitado de 120 segundos (el servidor/proxy puede imponer menos). Prevé espacio temporal suficiente para el contenido y el ZIP. Para tiendas mayores usa las herramientas del servidor.

Conserva juntas una copia del código, el respaldo completo y tus configuraciones privadas. Un ZIP de datos no sustituye la copia del código.


## Datos añadidos en la versión 1.7

Además de las tablas anteriores, la base incluye movimientos_inventario, correos_pendientes y tickets_cache; productos tiene stock_minimo. Usa el código 1.7 para abrir una base recuperada de esta versión.

El historial y los mínimos se conservan. tickets_cache se exporta con su estructura y sin PDF: es información derivada y se regenera al descargar o procesar un correo. Esto evita inflar los respaldos con tickets que pueden reconstruirse.

Las filas de correos_pendientes que no estaban enviadas ni canceladas se exportan en estado error con una nota de revisión. La base original conserva su estado. Así, una copia recuperada no envía automáticamente mensajes antiguos. Revisa destinatario y recepción antes de autorizar un reintento. No configures una tarea automática en una copia de prueba salvo que quieras realizar envíos reales con ella.

Conserva las tareas programadas por separado y revisa la ruta del ejecutor antes de activarlo en otro equipo. Nunca apuntes dos tiendas independientes a la misma base por error.


## Después de restaurar una tienda con QR (actualización 1.9)

El respaldo de datos conserva `configuracion_tienda.url_publica`, pero el respaldo
completo de este módulo contiene SQL e imágenes, no el código PHP. Restaura los
archivos de tu tienda con las actualizaciones hasta 1.9, incluida la carpeta
`lib/qr`, antes de usar los QR. La migración `01_qr_productos.sql` es repetible:
si recuperaste la columna desde el SQL del respaldo, no cambia su valor.

Entra como administrador en **Configuración → Códigos QR** y revisa la dirección:
si cambiaste de PC, IP, puerto o carpeta, guarda la dirección nueva. Abre la ficha
de prueba desde el celular conectado a la misma Wi-Fi. Los nuevos tickets que
descargues usan la dirección guardada; los PDF ya descargados o enviados por
correo conservan sus enlaces originales. No se reenvían correos por cambiarla.


Para el paquete completo Laragon/XAMPP 1.9, consulta también MIGRAR_TIENDA.md
entregado junto a la carpeta del proyecto. En Laragon usa www como carpeta
pública, una conexión propia y un marcador restaurado con versión 1.9.
