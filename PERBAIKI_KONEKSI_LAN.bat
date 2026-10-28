@echo off
:: Cek dan minta hak Administrator otomatis (UAC Prompt)
net session >nul 2>&1
if %errorlevel% neq 0 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process cmd -ArgumentList '/c \"\"%~f0\"\"' -Verb RunAs"
    exit /b
)

echo ========================================================
echo  MENGATUR PRIORITAS KABEL LAN & MEMPERBAIKI KONEKSI IDLE
echo ========================================================
echo.

:: 1. Atur Metric Kabel Ethernet ke 5 (Prioritas Paling Tinggi)
netsh interface ip set interface "Ethernet" metric=5
echo [OK] Kabel LAN Ethernet diatur ke Metric 5 (Prioritas Utama)

:: 2. Atur Metric Wi-Fi ke 50 (Prioritas Cadangan)
netsh interface ip set interface "Wi-Fi" metric=50 >nul 2>&1
echo [OK] Wi-Fi diatur ke Metric 50 (Cadangan)

:: 3. Tambahkan Route Langsung ke Server Bank Mitra (172.20.75.0/24) via Gateway LAN
route add 172.20.75.0 mask 255.255.255.0 192.168.88.1 metric 1 -p >nul 2>&1
echo [OK] Rute statis permanen ke server 172.20.75.2 via kabel LAN berhasil dipasang!

:: 4. Pasang DNS Bypass Langsung di Windows hosts (Bypass Hairpin NAT 100%)
findstr /C:"172.20.75.2 it.bankmitra.co.id" "%windir%\system32\drivers\etc\hosts" >nul
if %errorlevel% neq 0 (
    echo. >> "%windir%\system32\drivers\etc\hosts"
    echo 172.20.75.2 it.bankmitra.co.id >> "%windir%\system32\drivers\etc\hosts"
    echo [OK] Domain it.bankmitra.co.id diarahkan langsung ke IP LAN 172.20.75.2 di Windows hosts!
) else (
    echo [OK] Domain it.bankmitra.co.id sudah terdaftar di Windows hosts.
)

:: 5. Flush DNS Cache Windows
ipconfig /flushdns >nul
echo [OK] DNS Cache Windows berhasil di-refresh!

echo.
echo ========================================================
echo  SELESAI! Komputer sekarang terhubung langsung via LAN.
echo  Website tidak akan timeout lagi meskipun dibiarkan idle.
echo ========================================================
echo.
pause
