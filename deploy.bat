@echo off
setlocal
cd /d "%~dp0"
title Auto Deploy - Maintenance QR Bank Mitra
echo ========================================================
echo   AUTO-DEPLOY KE SERVER 172.20.75.2 (BANK MITRA)
echo ========================================================
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy.ps1"

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Deploy mengalami kendala!
) else (
    echo.
    echo [SUKSES] Semua file berhasil diperbarui di server.
)

echo.
pause
