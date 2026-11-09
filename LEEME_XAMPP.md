# Tienda de refrescos — XAMPP

## Qué contiene esta versión

Proyecto PHP para copiar en htdocs. La base sigue siendo MySQL/MariaDB;
phpMyAdmin es la herramienta con la que la importas y administras.
Se conserva vendor: no es necesario reinstalar Composer para empezar.
Usa PHP 8.1 o posterior con PDO MySQL, DOM, mbstring y fileinfo; GD es
necesaria para determinados formatos de imagen en los PDF.

## 1. Copiar e iniciar

1. Extrae el ZIP. Copia la carpeta tienda_refrescos_xampp completa en:
   C:\xampp\htdocs\tienda_refrescos_xampp
2. Comprueba que index.php está directamente en esa carpeta, sin una
   segunda carpeta tienda_refrescos_xampp dentro.
3. En el panel de XAMPP inicia Apache y MySQL. Si Laragon sigue abierto,
   detén sus servicios cuando ocupen los mismos puertos.
4. Esta versión supone Apache en 80 y MySQL en 3306. Si Apache usa 8080,
   añade :8080 a las direcciones localhost de esta guía. El puerto de
   Apache y el de MySQL son configuraciones distintas.

## 2. Elegir UNA forma de preparar la base

### A. Empezar desde cero con datos de ejemplo

1. Abre http://localhost/phpmyadmin/
2. Entra a Importar y selecciona database.sql de este proyecto.
3. Conserva UTF-8 y ejecuta la importación. El archivo crea la base
   tienda_refrescos y las cinco tablas: usuarios, productos, bitacora,
   pedidos y pedido_detalles.
4. Importa este archivo una sola vez y sobre una instalación nueva.
   No ejecutes después carrito_mysql.sql: su columna precio ya está incluida.
5. Los productos de ejemplo tienen precio 0. Entra como administrador y
   edita sus precios antes de comprar; no se permiten pedidos con precio 0.

Si ya existe una base tienda_refrescos con datos, no la borres ni importes
encima este archivo. Usa la opción B o respalda antes y elige una base distinta.
Para una base con otro nombre, cambia tanto CREATE DATABASE y USE del SQL
como base_datos en config_database.php.

### B. Conservar los datos de Laragon

El ZIP original NO contiene una exportación de los registros actuales de
Laragon: sus SQL son estructura y ejemplos. Para conservar tus registros:

1. Con Laragon y su servidor de base de datos activos, exporta la base
   tienda_refrescos a SQL con estructura y datos. Si usas phpMyAdmin en
   Laragon, selecciona esa base y usa Exportar, formato SQL.
2. Conserva ese archivo como respaldo. Copia también las fotografías de
   productos/imagenes/ de tu proyecto anterior.
3. Detén los servicios de Laragon si entran en conflicto con XAMPP.
4. Inicia Apache y MySQL de XAMPP. En su phpMyAdmin crea tienda_refrescos
   con utf8mb4_unicode_ci si la exportación no crea la base, selecciónala
   e importa TU exportación, no database.sql de este ZIP.
5. Comprueba que productos tenga precio y que existan pedidos y
   pedido_detalles. Si tu base todavía no tenía carrito, revisa
   carrito_mysql.sql y ejecútalo únicamente para incorporar lo que falte.
   La instrucción ADD COLUMN precio solo corresponde si precio no existe.
6. Copia las fotografías conservadas a productos/imagenes/ de esta versión.

## 3. Conexión

Edita config_database.php si tu XAMPP usa otros valores:

- host: 127.0.0.1
- puerto: 3306
- base_datos: tienda_refrescos
- usuario: root
- clave: vacía (valor habitual de una instalación local nueva)

No cambies conexion.php para ajustar estos datos. No cambies la configuración
de phpMyAdmin salvo que tu propia instalación lo requiera.

## 4. Abrir y probar

Abre http://localhost/tienda_refrescos_xampp/
No abras index.php directamente con doble clic ni con Live Server.

Solo si importaste los ejemplos:

| Rol | Correo | Contraseña |
| --- | --- | --- |
| Administrador | admin@tienda.com | password |
| Cliente | usuario@tienda.com | password |

Si importaste tu exportación de Laragon, usa tus usuarios anteriores.

Prueba en este orden:

1. Administrador: editar un precio y verificar existencias; crear un
   producto con precio e imagen y comprobar que se muestra.
2. Cerrar sesión, entrar como cliente y agregar un producto al carrito.
3. Cambiar su cantidad y confirmar el pedido.
4. Abrir Mis compras, consultar el detalle y descargar el ticket PDF.
5. Volver como administrador y confirmar que disminuyeron las existencias.
6. Abrir directamente /productos/carrito.php con la sesión cerrada:
   debe llevar al inicio de sesión del proyecto.

Confirmar un pedido no cobra una tarjeta ni realiza pagos electrónicos.

## 5. Correo

El archivo se llama ahora config_correo.php, que es el nombre que carga
el código. Conserva los valores de configuración incluidos en el original;
comprueba que usuario, clave y remitente sean los que quieres utilizar.
XAMPP no sustituye la configuración de tu proveedor SMTP.

Para un proveedor que use STARTTLS en 587, conserva esa combinación.
Si usas Gmail con usuario y contraseña SMTP, la clave debe ser la contraseña
de aplicación que corresponda a esa cuenta. No es la contraseña de XAMPP.
El envío depende de las credenciales, el acceso a Internet y el proveedor.
Si el correo falla, el pedido queda guardado y el PDF sigue disponible
para descargar desde Mis compras.

## Problemas habituales

- Error de conexión: revisa que MySQL esté iniciado y que puerto,
  usuario, clave y nombre de base coincidan en config_database.php.
- Tabla o columna inexistente: revisa qué SQL importaste; una instalación
  nueva solo necesita database.sql.
- Página no encontrada: comprueba la carpeta en htdocs y el puerto de Apache.
- Error de extensión PHP al generar PDF: revisa C:\xampp\php\php.ini,
  habilita la extensión indicada si está disponible y reinicia Apache.
- Errores de PHP: revisa los registros desde el panel de XAMPP, en Logs.

## Cambios y alcance de la revisión

- Conexión configurable para XAMPP en 3306, con PDO y utf8mb4.
- SQL de instalación nueva unificado, con las cinco tablas en InnoDB.
- Rutas de inclusión basadas en __DIR__ y redirección de sesión corregida.
- Sin líneas en blanco antes de la apertura PHP que afecten las cabeceras.
- Crear y editar productos permiten definir un precio positivo.
- Envío y descarga de tickets comparten la generación del PDF.
- Estilos compartidos para botones y formularios.

Se revisaron estáticamente las rutas, el esquema, las consultas modificadas
y la integridad del ZIP. Este entorno no tiene PHP ni MySQL, por lo que no
se ejecutaron pruebas reales de inicio de sesión, SQL, PDF o SMTP. Completa
las pruebas de la sección 4 en tu XAMPP. No se modificaron las librerías de vendor.
