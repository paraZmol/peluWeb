@echo off
REM Carga los datos de ejemplo (salones, servicios, fotos y cuentas de prueba).
REM Uso: doble clic, o en la terminal:  .\demo.cmd
set "PATH=C:\php84;%PATH%"
cd /d "%~dp0"
php artisan migrate --force
php artisan db:seed --force
pause
