@echo off
:: Skrip Otomatis Prioritas Kabel LAN Bank Mitra
:: Klik kanan file ini lalu pilih "Run as administrator"
echo ========================================================
echo  MENGATUR PRIORITAS KABEL LAN & MEMPERBAIKI KONEKSI IDLE
echo ========================================================
echo.

:: 1. Atur Metric Kabel Ethernet ke 5 (Prioritas Paling Tinggi)
netsh interface ip set interface "Ethernet" metric=5
echo [OK] Kabel LAN Ethernet diatur ke Metric 5 (Prioritas Utama)

:: 2. Atur Metric Wi-Fi ke 50 (Prioritas Cadangan)
netsh interface ip set interface "Wi-Fi" metric=50
echo [OK] Wi-Fi diatur ke Metric 50 (Cadangan)

:: 3. Tambahkan Route Langsung ke Server Bank Mitra (172.20.75.0/24) via Gateway LAN
route add 172.20.75.0 mask 255.255.255.0 192.168.88.1 metric 1 -p
echo [OK] Rute statis permanen ke server 172.20.75.2 via kabel LAN berhasil dipasang!

echo.
echo ========================================================
echo  SELESAI! Sekarang Windows selalu memprioritaskan kabel LAN.
echo  Website tidak akan lagi time-out / macet setelah idle.
echo ========================================================
echo.
pause
