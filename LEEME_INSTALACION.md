# Instalar una tienda nueva 1.9

Esta opción crea una base vacía y un administrador nuevo. Para recuperar los
productos y ventas de la tienda de Angel, utiliza `MIGRAR_TIENDA.md` incluido en
la raíz del ZIP, sin ejecutar el instalador sobre la base recuperada.

## Requisitos

- Windows con Laragon o XAMPP, usando Apache y PHP 8.0 o posterior.
- MySQL 8.0+ o MariaDB 10.4+. La instalación se probó con PHP 8.3 y MariaDB 10.11.
- Extensiones PHP: pdo_mysql, mbstring, dom, GD con JPEG/PNG/WebP, fileinfo,
  openssl, ctype, session y zip. El asistente comprueba los requisitos.
- Conserva `vendor/` y `lib/qr/` completas: ya vienen incluidas en el ZIP.

## Instalar

1. Extrae `tienda_refrescos` en `C:\laragon\www\` (o en `C:\xampp\htdocs\`).
2. Inicia Apache y MySQL/MariaDB. Evita iniciar ambos entornos al mismo tiempo si
   usan los mismos puertos.
3. En el navegador de la PC abre:

   ```
   http://localhost/tienda_refrescos/install/
   ```

4. Corrige los requisitos pendientes que aparezcan. Si activas extensiones,
   reinicia Apache.
5. Introduce los datos del servidor de TU equipo: host, puerto, usuario y
   contraseña reales. Puedes usar `tienda_companero_db` como nombre de una base
   nueva; no selecciones una base con datos. La clave no es necesariamente la
   del equipo de Angel.
6. Crea tu administrador indicando su nombre, correo y una contraseña de al
   menos 15 caracteres y máximo 72 bytes. No hay una contraseña predeterminada.
7. Finaliza y abre `http://localhost/tienda_refrescos/`.

El esquema ya incluye las 11 tablas y las columnas de las actualizaciones 1.7 y
1.9. **No necesitas importar migraciones después de una instalación nueva.**
La carpeta `migraciones/` se conserva para mantenimiento de copias anteriores;
no es una base con productos ni ventas.

## Activar correo y QR

El correo requiere configurar `config_correo.php` con los datos SMTP que quieras
usar. Si descargaste el proyecto desde GitHub, copia primero
`config_correo.example.php` con ese nombre. El campo `clave` es la contraseña de
aplicación cuando el proveedor así lo requiera; no viene en el paquete.

En Laragon, abre su Terminal y ejecuta `where php` para identificar el PHP activo.
Configura una tarea de Windows con:

- Programa: ruta completa a ese `php.exe`, sin añadir ahí el script.
- Argumentos: `"C:\laragon\www\tienda_refrescos\tareas\procesar_correos.php"`.
- Desencadenador: diariamente, repetir cada 1 minuto indefinidamente.
- Si ya se está ejecutando: no iniciar una instancia nueva.

Prueba una ejecución manual antes de activar la tarea. Desde la Terminal de
Laragon, situado en la carpeta del proyecto, puedes ejecutar:

```
php tareas\procesar_correos.php
```

La tarea debe usar el mismo PHP y sus extensiones, no depender del PATH de una
ventana abierta. Si cambias la versión de PHP, actualiza su ruta en la tarea.
También se incluye `tareas/ejecutar_correos_laragon.bat` para una tanda manual;
acepta la ruta completa de PHP como primer argumento.

Para el QR, en **Configuración → Códigos QR** guarda la dirección que el celular
puede abrir, por ejemplo `http://192.168.1.30/tienda_refrescos`. Usa la IPv4 de
esta nueva PC, no la de Angel. Ambos equipos deben estar en la misma red.
Prueba la dirección en el navegador del celular antes de escanear.
`localhost` o los dominios locales `.test` no se resuelven automáticamente en el
celular. Los PDF ya enviados conservan los enlaces originales.

## Documentación

- `README.md`: funciones y estructura del proyecto.
- `TERCEROS_QR.md`: versiones y licencias de las bibliotecas de QR.
- Documentación de Laragon: https://laragon.org/docs/directory-structure
- Añadir phpMyAdmin si no está incluido: https://laragon.org/docs/operations
