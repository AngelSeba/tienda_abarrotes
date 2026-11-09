@echo off
setlocal
rem Uso: ejecutar_correos_laragon.bat "C:\laragon\bin\php\VERSION\php.exe"
rem Si no hay argumento, usa PHP del PATH de la Terminal de Laragon.
set "TIENDA_PHP=%~1"
if not defined TIENDA_PHP (
  for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined TIENDA_PHP set "TIENDA_PHP=%%P"
)
if not defined TIENDA_PHP (
  echo Abre la Terminal de Laragon o pasa la ruta completa de php.exe como primer argumento.
  exit /b 1
)
if not exist "%TIENDA_PHP%" (
  echo No existe el ejecutable PHP indicado.
  exit /b 1
)
"%TIENDA_PHP%" "%~dp0procesar_correos.php"
exit /b %ERRORLEVEL%
