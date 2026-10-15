@echo off
REM Arranca peluWeb con PHP 8.4 (C:\php84) sin tocar el PHP de XAMPP.
REM Uso: doble clic, o en la terminal:  .\dev.cmd
set "PATH=C:\php84;%PATH%"
REM OpenSSL de PHP en Windows no encuentra su configuracion solo; sin esto no puede
REM cifrar las notificaciones push.
set "OPENSSL_CONF=C:\php84\extras\ssl\openssl.cnf"
cd /d "%~dp0"
php C:\ProgramData\ComposerSetup\bin\composer.phar dev
