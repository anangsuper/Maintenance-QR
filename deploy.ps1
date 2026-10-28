<#
.SYNOPSIS
    Script Deploy Cepat (1-Click) untuk Server Produksi Bank Mitra
    Target: adminmitra@172.20.75.2:/home/adminmitra/Maintenance-QR/
#>

param(
    [switch]$All,        # Upload seluruh folder api/
    [switch]$RestartFpm  # Restart php-fpm di server setelah upload
)

$ErrorActionPreference = "Stop"
$SERVER_USER = "adminmitra"
$SERVER_IP   = "172.20.75.2"
$REMOTE_DIR  = "/home/adminmitra/Maintenance-QR"

Write-Host "`n[1/3] Menjalankan Pemeriksaan Sintaks PHP Lokal..." -ForegroundColor Cyan

# 1. Cek Sintaks PHP lokal terlebih dahulu agar tidak terjadi Parse Error di server
$phpFiles = Get-ChildItem -Path "api" -Filter "*.php" -Recurse | Where-Object { $_.FullName -notmatch "vendor" }
$hasError = $false

foreach ($file in $phpFiles) {
    $content = Get-Content -Path $file.FullName -Raw -Encoding UTF8
    # Abaikan <?xml ?> di dalam string
    $clean = [regex]::Replace($content, '<\?xml[^\?>]*\?>', '')
    $opens = ([regex]::Matches($clean, '<\?(php|=)')).Count
    $closes = ([regex]::Matches($clean, '\?>')).Count
    if ($closes -gt $opens) {
        Write-Host "[X] Error sintaks terdeteksi pada: $($file.Name)" -ForegroundColor Red
        $hasError = $true
    }
}

if ($hasError) {
    Write-Host "[!] Deploy dibatalkan karena ada potensi error sintaks!" -ForegroundColor Red
    exit 1
}
Write-Host "[OK] Sintaks PHP valid dan aman." -ForegroundColor Green

# 2. Tentukan file yang akan diupload
Write-Host "`n[2/3] Mengunggah file ke $SERVER_USER@$SERVER_IP..." -ForegroundColor Cyan

if ($All) {
    Write-Host "Mengunggah seluruh folder api/ ..." -ForegroundColor Yellow
    scp -r "api" "${SERVER_USER}@${SERVER_IP}:${REMOTE_DIR}/"
} else {
    # Cek file yang berubah berdasarkan git status
    $gitStatus = git status --porcelain
    $changedFiles = @()
    
    if ($gitStatus) {
        foreach ($line in $gitStatus) {
            $f = $line.Substring(3).Trim().Trim('"')
            if (($f -like "api/*" -or $f -eq "index.php" -or $f -eq "output.html") -and ($f -notlike "api/uploads*")) {
                $changedFiles += $f
            }
        }
    }

    if ($changedFiles.Count -eq 0) {
        Write-Host "Tidak ada file api/ yang baru dimodifikasi. Mengunggah file utama dashboard & detail..." -ForegroundColor Yellow
        $changedFiles = @(
            "api/dashboard.php",
            "api/kartu_detail.php",
            "api/memo_pengajuan.php",
            "api/sw.js",
            "api/print_inventory_card.php",
            "api/index.php",
            "api/includes/inventaris_kartu.php",
            "api/realtime_dashboard.js"
        )
    }

    $validFiles = @()
    foreach ($file in $changedFiles) {
        if (Test-Path $file) {
            $p = ($file -replace '\\', '/').Trim().TrimEnd('/')
            if ($p) {
                if ((Get-Item $p).PSIsContainer) {
                    $subFiles = Get-ChildItem -Path $p -File -Recurse | ForEach-Object {
                        $_.FullName.Substring($PWD.Path.Length + 1) -replace '\\', '/'
                    }
                    $validFiles += $subFiles
                } else {
                    $validFiles += $p
                }
            }
        }
    }

    if ($validFiles.Count -eq 0) {
        Write-Host "  -> Tidak ada file yang perlu dikirim." -ForegroundColor Yellow
    } elseif ($validFiles.Count -eq 1 -and -not (Get-Item $validFiles[0]).PSIsContainer) {
        # Jika cuma 1 file: kirim langsung via SCP tunggal
        $singleFile = $validFiles[0]
        $remoteTarget = "${REMOTE_DIR}/" + $singleFile
        Write-Host "  -> Mengirim file tunggal: $singleFile" -ForegroundColor White
        scp "$singleFile" "${SERVER_USER}@${SERVER_IP}:${remoteTarget}"
    } else {
        # Jika banyak file: kemas ke dalam paket kilat .tar.gz (Sangat cepat, hanya 1 kali koneksi)
        Write-Host "  -> Mengemas $($validFiles.Count) file ke dalam paket kilat (ringan)..." -ForegroundColor Yellow
        foreach ($vf in $validFiles) {
            Write-Host "     + $vf" -ForegroundColor DarkGray
        }

        $bundleFile = "_deploy_bundle.tar.gz"
        tar -czf $bundleFile $validFiles

        Write-Host "  -> Mengunggah paket sekaligus ke server..." -ForegroundColor Cyan
        scp $bundleFile "${SERVER_USER}@${SERVER_IP}:${REMOTE_DIR}/"

        Write-Host "  -> Mengekstrak otomatis di server..." -ForegroundColor Cyan
        ssh "${SERVER_USER}@${SERVER_IP}" "tar -xzf ${REMOTE_DIR}/${bundleFile} -C ${REMOTE_DIR} && rm -f ${REMOTE_DIR}/${bundleFile}"

        Remove-Item -Force $bundleFile -ErrorAction SilentlyContinue
    }
}

# 3. Opsional: Reload / Restart service jika diminta
if ($RestartFpm) {
    Write-Host "`n[3/3] Mereload PHP-FPM di server..." -ForegroundColor Cyan
    ssh "${SERVER_USER}@${SERVER_IP}" "sudo systemctl reload php8.3-fpm"
}

Write-Host "`n[SUKSES] DEPLOY SELESAI DENGAN SUKSES! Website siap diakses.`n" -ForegroundColor Green
