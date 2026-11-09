# Bibliotecas de QR incluidas

Se incluyen copias del código `src/` sin modificaciones, con autocarga PSR-4
propia en `lib/qr/autoload.php`, aislada del `vendor` existente de la tienda.
No se utilizan servicios externos para generar el código QR.

- **chillerlan/php-qrcode 5.0.5**:
  https://github.com/chillerlan/php-qrcode/releases/tag/5.0.5
  Licencia MIT; partes derivadas de ZXing bajo Apache 2.0.
  Se incluyen `LICENSE-MIT`, `LICENSE-APACHE-2.0` y `NOTICE` del paquete.
- **chillerlan/php-settings-container 2.1.6**:
  https://github.com/chillerlan/php-settings-container/releases/tag/2.1.6
  Licencia MIT, incluida en la carpeta del paquete.

Se eligieron versiones compatibles con PHP 8.0 o posterior. Los archivos
`*-version.json` registran la versión y la referencia exactas utilizadas.
El generador usa GD para PNG y mbstring. No requiere conexión a Internet para
generar los QR. La ficha sí necesita acceso al servidor de la tienda para leer
los datos actuales.

Documentación oficial de esta versión:
https://php-qrcode.readthedocs.io/en/v5.0.x/Usage/Advanced-usage.html
https://php-qrcode.readthedocs.io/en/v5.0.x/Built-In-Output/QRGdImage.html
