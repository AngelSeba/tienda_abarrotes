@echo off
setlocal
rem Ejecuta una tanda. No deja procesos infinitos abiertos.
if not exist "C:\xampp\php\php.exe" (
  echo Ajusta la ruta de PHP en este archivo a tu instalacion de XAMPP.
  exit /b 1
)
"C:\xampp\php\php.exe" "%~dp0procesar_correos.php"
exit /b %ERRORLEVEL%
