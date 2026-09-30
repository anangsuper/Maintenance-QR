@echo off
title QR Maintenance - WebSocket Server Real-Time
color 0A

echo =========================================================================
echo   PT BPR MITRATAMA ARTHABUANA - QR MAINTENANCE SYSTEM
echo   Standalone Real-Time WebSocket Server (RFC 6455)
echo =========================================================================
echo.

:: 1. Deteksi executable PHP
set PHP_BIN=
where php.exe >nul 2>&1
if %ERRORLEVEL% equ 0 (
    set PHP_BIN=php
    goto :FOUND_PHP
)

if exist "C:\xampp\php\php.exe" (
    set PHP_BIN=C:\xampp\php\php.exe
    goto :FOUND_PHP
)

if exist "D:\xampp\php\php.exe" (
    set PHP_BIN=D:\xampp\php\php.exe
    goto :FOUND_PHP
)

if exist "C:\php\php.exe" (
    set PHP_BIN=C:\php\php.exe
    goto :FOUND_PHP
)

if exist "D:\php\php.exe" (
    set PHP_BIN=D:\php\php.exe
    goto :FOUND_PHP
)

:: Cek instalasi Laragon
for /d %%D in ("C:\laragon\bin\php\php-*") do (
    if exist "%%D\php.exe" (
        set PHP_BIN=%%D\php.exe
        goto :FOUND_PHP
    )
)
for /d %%D in ("D:\laragon\bin\php\php-*") do (
    if exist "%%D\php.exe" (
        set PHP_BIN=%%D\php.exe
        goto :FOUND_PHP
    )
)

:NOT_FOUND
color 0C
echo [ERROR] PHP tidak ditemukan di PATH, XAMPP, maupun Laragon.
echo Pastikan PHP telah terinstal atau tambahkan folder PHP ke Environment PATH.
echo.
pause
exit /b 1

:FOUND_PHP
echo [*] PHP terdeteksi : %PHP_BIN%
echo [*] Port WebSocket : 8080 (Default)
echo.
echo Menjalankan WebSocket Server...
echo Tekan Ctrl+C untuk menghentikan server kapan saja.
echo -------------------------------------------------------------------------
echo.

"%PHP_BIN%" "%~dp0websocket_server.php" 8080

pause
