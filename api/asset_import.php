<?php
/**
 * IMPORT MASSAL ASET IT (EXCEL / CSV)
 * Mendukung pembacaan file CSV & XLSX, validasi kolom, pemetaan cabang otomatis, dan pendaftaran QR Token
 */
require __DIR__ . '/bootstrap.php';
require_login();

$flash = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

$importResult = null;
$previewRows = null;

// Helper untuk konversi referensi kolom Excel (A, B, C, ... AA) ke indeks 0-based
function xlsx_col_to_index(string $cellRef): int {
    if (preg_match('/^([A-Z]+)/i', $cellRef, $matches)) {
        $letters = strtoupper($matches[1]);
        $len = strlen($letters);
        $num = 0;
        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $num - 1;
    }
    return -1;
}

// Fungsi Parser CSV Cerdas (Mampu mendeteksi pemisah ; , \t dan membuang UTF-8 BOM)
function parse_uploaded_csv(string $filepath): array {
    if (!file_exists($filepath)) return [];

    $firstLine = '';
    $f = @fopen($filepath, 'r');
    if ($f) {
        $firstLine = (string)fgets($f);
        fclose($f);
    }
    $delim = (strpos($firstLine, ';') !== false) ? ';' : ',';

    $handle = fopen($filepath, 'r');
    if (!$handle) return [];

    // Skip BOM pada handle
    $bomCheck = fread($handle, 3);
    if ($bomCheck !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $rows = [];
    while (($data = fgetcsv($handle, 4096, $delim)) !== false) {
        $trimmed = array_map(function($v) {
            return trim((string)$v);
        }, $data);
        // Lewati jika seluruh baris kosong
        if (implode('', $trimmed) === '') {
            continue;
        }
        $rows[] = $trimmed;
    }
    fclose($handle);
    return $rows;
}

// Fungsi Parser XLSX Ringan (Native XML ZIP parsing tanpa library eksternal)
function parse_uploaded_xlsx(string $filepath): array {
    if (!class_exists('ZipArchive')) {
        return [];
    }
    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        return [];
    }

    // 1. Baca shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $xml = @simplexml_load_string($ssXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $txt = '';
                    foreach ($si->r as $r) {
                        $txt .= (string)($r->t ?? '');
                    }
                    $sharedStrings[] = $txt;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    // 2. Baca sheet1.xml
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $rows = [];
    if ($sheetXml !== false) {
        $xml = @simplexml_load_string($sheetXml);
        if ($xml && isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $r) {
                $rowCells = [];
                $maxCol = 0;
                foreach ($r->c as $c) {
                    $cRef = (string)($c['r'] ?? '');
                    $colIdx = xlsx_col_to_index($cRef);
                    if ($colIdx < 0) {
                        $colIdx = $maxCol;
                    }

                    $type = (string)($c['t'] ?? '');
                    $val = '';
                    if ($type === 's') {
                        $sIdx = (int)($c->v ?? 0);
                        $val = $sharedStrings[$sIdx] ?? '';
                    } elseif ($type === 'inlineStr') {
                        if (isset($c->is->t)) {
                            $val = (string)$c->is->t;
                        } elseif (isset($c->is->r)) {
                            foreach ($c->is->r as $ir) {
                                $val .= (string)($ir->t ?? '');
                            }
                        }
                    } else {
                        $val = (string)($c->v ?? '');
                    }

                    $rowCells[$colIdx] = trim($val);
                    if ($colIdx >= $maxCol) {
                        $maxCol = $colIdx + 1;
                    }
                }

                if ($maxCol > 0) {
                    $fullRow = [];
                    $hasAnyContent = false;
                    for ($i = 0; $i < $maxCol; $i++) {
                        $cellVal = $rowCells[$i] ?? '';
                        $fullRow[$i] = $cellVal;
                        if ($cellVal !== '') {
                            $hasAnyContent = true;
                        }
                    }
                    if ($hasAnyContent) {
                        $rows[] = $fullRow;
                    }
                }
            }
        }
    }
    $zip->close();
    return $rows;
}

// Master data mapping helper
function get_master_maps(): array {
    $client = is_google_cloud_mode() ? google_sheets_v4_client() : null;
    $cabangs = [];
    $divisis = [];
    $kategoris = [];
    $karyawans = [];

    if ($client) {
        $client->preloadSheets(['Cabang', 'Divisi', 'Kategori_Aset', 'Karyawan']);
        $cabangs = $client->getSheetData('Cabang');
        $divisis = $client->getSheetData('Divisi');
        $kategoris = $client->getSheetData('Kategori_Aset');
        $karyawans = $client->getSheetData('Karyawan');
    } else {
        try {
            $pdo = db();
            $cabangs = $pdo->query("SELECT id, nama_cabang FROM cabang")->fetchAll(PDO::FETCH_ASSOC);
            $divisis = $pdo->query("SELECT id, nama_divisi FROM divisi")->fetchAll(PDO::FETCH_ASSOC);
            $kategoris = $pdo->query("SELECT id, nama_kategori FROM kategori_aset")->fetchAll(PDO::FETCH_ASSOC);
            $karyawans = $pdo->query("SELECT id, nama_karyawan, cabang_id, divisi_id FROM karyawan")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
    }

    return [
        'cabangs'   => $cabangs,
        'divisis'   => $divisis,
        'kategoris' => $kategoris,
        'karyawans' => $karyawans,
    ];
}

// Helper untuk mencocokkan Nama Cabang ke ID Cabang
function resolve_cabang_id(string $input, array $cabangList): int {
    $clean = strtolower(trim($input));
    if ($clean === '') return 1;

    // Cek kode numerik (01, 02, 03, 04, 05 atau 1, 2, 3, 4, 5)
    if (preg_match('/^(\d{1,2})/', $clean, $m)) {
        $num = (int)$m[1];
        if ($num > 0) return $num;
    }

    $map = [
        'pusat'     => 1,
        'kpo'       => 1,
        'head'      => 1,
        'batulicin' => 2,
        'martapura' => 3,
        'tanjung'   => 4,
        'handil'    => 5,
    ];

    foreach ($map as $kw => $cId) {
        if (strpos($clean, $kw) !== false) {
            return $cId;
        }
    }

    foreach ($cabangList as $c) {
        $name = strtolower(trim((string)($c['nama_cabang'] ?? $c['nama'] ?? '')));
        if (strpos($name, $clean) !== false || strpos($clean, $name) !== false) {
            return (int)($c['id'] ?? 1);
        }
    }
    return 1;
}

// Helper untuk mencocokkan Divisi ke ID Divisi
function resolve_divisi_id(string $input, array &$divisiList, $client = null): int {
    $clean = strtolower(trim($input));
    if ($clean === '') return 1;

    foreach ($divisiList as $d) {
        $name = strtolower(trim((string)($d['nama_divisi'] ?? $d['nama'] ?? '')));
        if ($name === $clean || strpos($name, $clean) !== false) {
            return (int)($d['id'] ?? 1);
        }
    }

    // Auto-create divisi baru jika belum ada
    $newId = count($divisiList) + 1;
    if ($client && is_google_cloud_mode()) {
        $client->appendValues('Divisi!A:C', [[$newId, trim($input), 'Dibuat via Import Excel']]);
    }
    $divisiList[] = ['id' => $newId, 'nama_divisi' => trim($input)];
    return $newId;
}

// Helper untuk mencocokkan Kategori ke ID Kategori
function resolve_kategori_id(string $input, array &$kategoriList, $client = null): int {
    $clean = strtolower(trim($input));
    if ($clean === '') return 1;

    foreach ($kategoriList as $k) {
        $name = strtolower(trim((string)($k['nama_kategori'] ?? $k['nama'] ?? '')));
        if ($name === $clean || strpos($name, $clean) !== false) {
            return (int)($k['id'] ?? 1);
        }
    }

    // Auto-create kategori baru jika belum ada
    $newId = count($kategoriList) + 1;
    if ($client && is_google_cloud_mode()) {
        $client->appendValues('Kategori_Aset!A:B', [[$newId, trim($input)]]);
    }
    $kategoriList[] = ['id' => $newId, 'nama_kategori' => trim($input)];
    return $newId;
}

// Helper untuk normalisasi Status Unit dari Excel/Dropdown
function normalize_asset_status(string $input): string {
    $clean = strtolower(trim($input));
    if ($clean === '') return 'Aktif';
    if (strpos($clean, 'backup') !== false || strpos($clean, 'cadangan') !== false) {
        return 'Backup';
    }
    if (strpos($clean, 'rusak') !== false || strpos($clean, 'perbaikan') !== false || strpos($clean, 'servis') !== false) {
        return 'Perbaikan';
    }
    if (strpos($clean, 'nonaktif') !== false || strpos($clean, 'afkir') !== false || strpos($clean, 'mati') !== false) {
        return 'Nonaktif';
    }
    return 'Aktif';
}

// PROSES POST FORM: TAHAP 1 (PARSING & PREVIEW)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_import') {
    verify_csrf();

    if (empty($_FILES['file_excel']['tmp_name']) || !is_uploaded_file($_FILES['file_excel']['tmp_name'])) {
        $_SESSION['flash_error'] = 'Silakan pilih file Excel (.xlsx) atau CSV (.csv) untuk diunggah.';
        redirect(module_url('asset_import.php'));
    }

    $fileName = $_FILES['file_excel']['name'] ?? '';
    $tmpPath = $_FILES['file_excel']['tmp_name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $rawRows = [];
    if ($ext === 'xlsx') {
        $rawRows = parse_uploaded_xlsx($tmpPath);
    } else {
        $rawRows = parse_uploaded_csv($tmpPath);
    }

    if (empty($rawRows) || count($rawRows) < 2) {
        $_SESSION['flash_error'] = 'File yang diunggah kosong atau tidak memiliki baris data aset.';
        redirect(module_url('asset_import.php'));
    }

    // Baris 0 = Header
    $headerRow = array_map('strtolower', $rawRows[0]);
    $dataRows = array_slice($rawRows, 1);

    // Pemetaan Indeks Kolom
    $colMap = [
        'kode'      => -1,
        'kategori'  => -1,
        'merk'      => -1,
        'model'     => -1,
        'sn'        => -1,
        'cabang'    => -1,
        'divisi'    => -1,
        'karyawan'  => -1,
        'ip'        => -1,
        'printer'   => -1,
        'status'    => -1,
        'ket'       => -1
    ];

    foreach ($headerRow as $idx => $h) {
        $hClean = trim($h);
        if (strpos($hClean, 'kode') !== false) $colMap['kode'] = $idx;
        elseif (strpos($hClean, 'kategori') !== false || strpos($hClean, 'jenis') !== false) $colMap['kategori'] = $idx;
        elseif (strpos($hClean, 'merk') !== false || strpos($hClean, 'brand') !== false) $colMap['merk'] = $idx;
        elseif (strpos($hClean, 'model') !== false || strpos($hClean, 'tipe') !== false) $colMap['model'] = $idx;
        elseif (strpos($hClean, 'serial') !== false || strpos($hClean, 'sn') !== false) $colMap['sn'] = $idx;
        elseif (strpos($hClean, 'cabang') !== false || strpos($hClean, 'lokasi') !== false) $colMap['cabang'] = $idx;
        elseif (strpos($hClean, 'divisi') !== false || strpos($hClean, 'unit') !== false || strpos($hClean, 'bagian') !== false) $colMap['divisi'] = $idx;
        elseif (strpos($hClean, 'pengguna') !== false || strpos($hClean, 'karyawan') !== false || strpos($hClean, 'user') !== false || strpos($hClean, 'pic') !== false) $colMap['karyawan'] = $idx;
        elseif (strpos($hClean, 'ip') !== false || strpos($hClean, 'alamat') !== false) $colMap['ip'] = $idx;
        elseif (strpos($hClean, 'printer') !== false) $colMap['printer'] = $idx;
        elseif (strpos($hClean, 'status') !== false || strpos($hClean, 'kondisi') !== false) $colMap['status'] = $idx;
        elseif (strpos($hClean, 'keterangan') !== false || strpos($hClean, 'ket') !== false || strpos($hClean, 'notes') !== false) $colMap['ket'] = $idx;
    }

    // Default fallback urutan kolom jika header standar (0..11)
    if ($colMap['kode'] === -1) $colMap['kode'] = 0;
    if ($colMap['kategori'] === -1) $colMap['kategori'] = 1;
    if ($colMap['merk'] === -1) $colMap['merk'] = 2;
    if ($colMap['model'] === -1) $colMap['model'] = 3;
    if ($colMap['sn'] === -1) $colMap['sn'] = 4;
    if ($colMap['cabang'] === -1) $colMap['cabang'] = 5;
    if ($colMap['divisi'] === -1) $colMap['divisi'] = 6;
    if ($colMap['karyawan'] === -1) $colMap['karyawan'] = 7;
    if ($colMap['ip'] === -1) $colMap['ip'] = 8;
    if ($colMap['printer'] === -1) $colMap['printer'] = 9;
    if ($colMap['status'] === -1) $colMap['status'] = 10;
    if ($colMap['ket'] === -1) $colMap['ket'] = 11;

    // Ambil aset yang sudah ada untuk mendeteksi duplikasi kode inventaris
    $existingAssets = map_sheets_assets(true);
    $existingCodes = [];
    foreach ($existingAssets as $ea) {
        $cNorm = strtoupper(trim((string)$ea['kode_inventaris']));
        if ($cNorm !== '') $existingCodes[$cNorm] = true;
    }

    $previewRows = [];
    $seenInFile = [];

    foreach ($dataRows as $row) {
        $kode = trim($row[$colMap['kode']] ?? '');
        $kategoriStr = trim($row[$colMap['kategori']] ?? '');
        $merk = trim($row[$colMap['merk']] ?? '');
        $model = trim($row[$colMap['model']] ?? '');
        $sn = trim($row[$colMap['sn']] ?? '');
        $cabangStr = trim($row[$colMap['cabang']] ?? '');
        $divisiStr = trim($row[$colMap['divisi']] ?? '');
        $karyawanStr = trim($row[$colMap['karyawan']] ?? '');
        $ip = trim($row[$colMap['ip']] ?? '');
        $printer = trim($row[$colMap['printer']] ?? '');
        $status = normalize_asset_status((string)($row[$colMap['status']] ?? 'Aktif'));
        $ket = trim($row[$colMap['ket']] ?? '');

        // Abaikan jika baris kosong total
        if ($kode === '' && $merk === '' && $model === '' && $kategoriStr === '' && $sn === '' && $cabangStr === '' && $karyawanStr === '') {
            continue;
        }

        $normKode = strtoupper($kode);
        $isDuplicate = false;
        if ($normKode !== '') {
            if (isset($existingCodes[$normKode]) || isset($seenInFile[$normKode])) {
                $isDuplicate = true;
            }
            $seenInFile[$normKode] = true;
        }

        if ($kategoriStr === '') {
            $mLow = strtolower($merk . ' ' . $model);
            if (strpos($mLow, 'laptop') !== false || strpos($mLow, 'notebook') !== false || strpos($mLow, 'victus') !== false || strpos($mLow, 'vivobook') !== false) {
                $kategoriStr = 'Laptop';
            } elseif (strpos($mLow, 'printer') !== false || strpos($mLow, 'ecotank') !== false || strpos($mLow, 'laserjet') !== false) {
                $kategoriStr = 'Printer';
            } elseif (strpos($mLow, 'monitor') !== false) {
                $kategoriStr = 'Monitor';
            } else {
                $kategoriStr = 'PC Desktop';
            }
        }

        if ($cabangStr === '') {
            $cabangStr = 'Kantor Pusat Operasional';
        }

        $previewRows[] = [
            'kode'          => $kode,
            'kategori'      => $kategoriStr,
            'merk'          => $merk,
            'model'         => $model,
            'sn'            => $sn,
            'cabang'        => $cabangStr,
            'divisi'        => $divisiStr,
            'karyawan'      => $karyawanStr,
            'ip'            => $ip,
            'printer'       => $printer,
            'status'        => $status,
            'ket'           => $ket,
            'is_duplicate'  => $isDuplicate
        ];
    }

    if (empty($previewRows)) {
        $_SESSION['flash_error'] = 'Tidak ada baris data valid yang dapat diproses dari file tersebut.';
        redirect(module_url('asset_import.php'));
    }
}

// PROSES POST FORM: TAHAP 2 (CONFIRM & ACCEPT SIMPAN KE DATABASE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    verify_csrf();

    $submittedItems = $_POST['items'] ?? [];
    if (!is_array($submittedItems) || empty($submittedItems)) {
        $_SESSION['flash_error'] = 'Tidak ada data aset yang dipilih untuk disimpan.';
        redirect(module_url('asset_import.php'));
    }

    $masters = get_master_maps();
    $client = is_google_cloud_mode() ? google_sheets_v4_client() : null;

    $successCount = 0;
    $skipCount = 0;
    $importedItems = [];

    // Ambil aset yang sudah ada
    $existingAssets = map_sheets_assets(true);
    $existingCodes = [];
    foreach ($existingAssets as $ea) {
        $cNorm = strtoupper(trim((string)$ea['kode_inventaris']));
        if ($cNorm !== '') $existingCodes[$cNorm] = true;
    }

    foreach ($submittedItems as $item) {
        $kode = trim((string)($item['kode'] ?? ''));
        $kategoriStr = trim((string)($item['kategori'] ?? ''));
        $merk = trim((string)($item['merk'] ?? ''));
        $model = trim((string)($item['model'] ?? ''));
        $sn = trim((string)($item['sn'] ?? ''));
        $cabangStr = trim((string)($item['cabang'] ?? ''));
        $divisiStr = trim((string)($item['divisi'] ?? ''));
        $karyawanStr = trim((string)($item['karyawan'] ?? ''));
        $ip = trim((string)($item['ip'] ?? ''));
        $printer = trim((string)($item['printer'] ?? ''));
        $status = normalize_asset_status((string)($item['status'] ?? 'Aktif'));
        $ket = trim((string)($item['ket'] ?? ''));

        // Abaikan baris kosong
        if ($kode === '' && $merk === '' && $model === '' && $kategoriStr === '' && $sn === '' && $cabangStr === '') {
            continue;
        }

        // Cek duplikasi kode inventaris jika diisi
        $normKode = strtoupper($kode);
        if ($normKode !== '' && isset($existingCodes[$normKode])) {
            $skipCount++;
            continue;
        }

        $idCab = resolve_cabang_id($cabangStr, $masters['cabangs']);
        $idDiv = resolve_divisi_id($divisiStr, $masters['divisis'], $client);
        $idKat = resolve_kategori_id($kategoriStr, $masters['kategoris'], $client);

        if ($merk === '' && $model === '') {
            $model = $kategoriStr !== '' ? $kategoriStr : 'Perangkat IT';
        }

        $payload = [
            'kode_inventaris'   => $kode,
            'merk'              => $merk,
            'model'             => $model,
            'serial_number'     => $sn,
            'id_kategori'       => $idKat,
            'id_cabang'         => $idCab,
            'id_divisi'         => $idDiv,
            'nama_karyawan'     => $karyawanStr,
            'ip_address'        => $ip,
            'printer'           => $printer,
            'status'            => $status,
            'keterangan'        => $ket,
            'placement_label'   => 'Bodi Casing'
        ];

        $res = create_new_asset($payload);
        if (!empty($res['success'])) {
            $successCount++;
            $assignedKode = (string)($res['kode_inventaris'] ?? ($kode !== '' ? $kode : ('INV-IT-' . ($res['asset_id'] ?? $res['id'] ?? $successCount))));
            if ($assignedKode !== '') {
                $existingCodes[strtoupper($assignedKode)] = true;
            }
            $importedItems[] = [
                'kode'      => $assignedKode,
                'nama'      => trim($merk . ' ' . $model) ?: ($kategoriStr ?: 'Perangkat IT'),
                'cabang'    => $cabangStr ?: ('Cabang #' . $idCab),
                'status'    => 'Berhasil'
            ];
        }
    }

    // Refresh memory cache
    map_sheets_assets(true);

    $importResult = [
        'success'   => true,
        'count'     => $successCount,
        'skipped'   => $skipCount,
        'items'     => $importedItems
    ];
}

$body = '
<!-- Header Navigasi -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <div class="tech-label mb-1">ASSET REGISTRY / DATA MIGRATION</div>
    <h1 class="h3 mb-1 fw-bold text-dark d-flex align-items-center gap-2">
      <i class="bi bi-file-earmark-arrow-up text-primary"></i> Import Data Komputer (Excel / CSV)
    </h1>
    <p class="text-secondary small mb-0">Impor massal data komputer, laptop, dan printer ke dalam sistem katalog IT & label QR Bank Mitra.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="'.e(module_url('assets.php')).'" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-arrow-left"></i> Kembali ke Katalog Aset
    </a>
  </div>
</div>';

// Flash Messages
if ($flash) {
    $body .= '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
if ($flashError) {
    $body .= '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($flashError).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// 1. TAMPILAN JIKA BERHASIL DI-IMPORT (HASIL AKHIR)
if ($importResult) {
    $body .= '
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; overflow: hidden; border-left: 5px solid #10B981 !important;">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success-subtle p-3 text-success d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
              <i class="bi bi-check-lg fs-3"></i>
            </div>
            <div>
              <h2 class="h5 fw-bold text-dark mb-1">Import Data Berhasil Diselesaikan!</h2>
              <p class="text-secondary small mb-0">Sebanyak <strong>'.$importResult['count'].' unit aset baru</strong> berhasil didaftarkan dan token QR dibuat otomatis.'.($importResult['skipped'] > 0 ? ' ('.$importResult['skipped'].' dilewati karena kode duplikat)' : '').'</p>
            </div>
          </div>
          <div class="d-flex gap-2">
            <a href="'.e(module_url('assets.php')).'" class="btn btn-primary fw-semibold px-4"><i class="bi bi-pc-display me-1"></i> Buka Asset Registry &raquo;</a>
            <a href="'.e(module_url('asset_import.php')).'" class="btn btn-light border px-3">Import File Lain</a>
          </div>
        </div>';

    if (!empty($importResult['items'])) {
        $body .= '
        <div class="table-responsive mt-3 border rounded">
          <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 50px;">No</th>
                <th>Kode Inventaris</th>
                <th>Perangkat</th>
                <th>Kantor Cabang</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>';
        $no = 1;
        foreach (array_slice($importResult['items'], 0, 20) as $item) {
            $body .= '
              <tr>
                <td class="text-muted small">'.$no++.'</td>
                <td><span class="font-monospace fw-bold text-primary small">'.e($item['kode']).'</span></td>
                <td><span class="fw-semibold text-dark small">'.e($item['nama']).'</span></td>
                <td><span class="badge-chip chip-secondary">'.e($item['cabang']).'</span></td>
                <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check2-circle me-1"></i>Tersimpan</span></td>
              </tr>';
        }
        $body .= '
            </tbody>
          </table>
        </div>';
        if (count($importResult['items']) > 20) {
            $body .= '<div class="text-muted small text-center mt-2">... dan ' . (count($importResult['items']) - 20) . ' unit lainnya berhasil diimpor.</div>';
        }
    }

    $body .= '
      </div>
    </div>';

// 2. TAMPILAN PREVIEW & PERBAIKAN DATA (REVIEW SEBELUM ACCEPT)
} elseif ($previewRows !== null) {
    $kategoriOpts = ['Laptop', 'PC Desktop', 'Printer', 'Monitor', 'Server', 'Scanner', 'UPS', 'Network Device'];
    $cabangOpts = ['Kantor Pusat Operasional', 'Cabang Batulicin', 'Cabang Martapura', 'Cabang Tanjung', 'Cabang Handil'];
    $divisiOpts = ['IT / MIS', 'Operasional', 'Akunting', 'Kredit', 'Direksi', 'SKAI', 'SDM & UMUM', 'Kepatuhan'];
    $statusOpts = ['Aktif', 'Backup', 'Perbaikan', 'Nonaktif'];

    $duplicateCount = count(array_filter($previewRows, fn($r) => !empty($r['is_duplicate'])));

    $body .= '
    <style>
      .import-preview-wrapper {
        border-radius: 12px;
      }
      #previewTable {
        min-width: 2300px;
        font-size: 0.85rem;
      }
      #previewTable th {
        white-space: nowrap;
        padding: 12px 14px;
        font-size: 0.78rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        font-weight: 700;
        vertical-align: middle;
      }
      #previewTable td {
        padding: 10px 12px;
        vertical-align: middle;
      }
      #previewTable .form-control-sm,
      #previewTable .form-select-sm {
        font-size: 0.84rem;
        padding: 0.4rem 0.65rem;
        border-radius: 6px;
      }
      #previewTable .form-select-sm {
        padding-right: 2rem;
      }
    </style>
    <div class="card border-0 shadow-sm mb-4 bg-white import-preview-wrapper">
      <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <span class="badge bg-warning text-dark px-2 py-1 mb-1 font-monospace fw-bold"><i class="bi bi-eye-fill me-1"></i> REVIEW &amp; PERBAIKAN DATA</span>
          <h2 class="h5 mb-0 fw-bold text-dark">Periksa dan Edit Data Sebelum Disimpan</h2>
          <p class="text-secondary small mb-0 mt-1">Anda dapat langsung mengedit nilai pada tabel di bawah ini, atau menghapus baris yang tidak diinginkan sebelum menekan tombol Accept.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge bg-light text-secondary border px-3 py-2 fw-medium">
            <i class="bi bi-arrows-expand me-1 text-primary"></i> Geser tabel ke samping untuk melihat semua kolom
          </span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-semibold" id="rowCountBadge">
            <i class="bi bi-list-ol me-1"></i> '.count($previewRows).' Baris Siap Diimport
          </span>
          '.($duplicateCount > 0 ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fw-semibold"><i class="bi bi-exclamation-octagon-fill me-1"></i> '.$duplicateCount.' Kode Duplikat</span>' : '').'
        </div>
      </div>
      
      <div class="card-body p-4">
        <form method="post" id="formConfirmImport">
          <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
          <input type="hidden" name="action" value="confirm_import">

          <div class="table-responsive border rounded-3 mb-3 custom-scrollbar shadow-sm bg-white" style="max-height: 600px; overflow: auto;">
            <table class="table table-hover align-middle mb-0 table-bordered" id="previewTable">
              <thead class="table-dark align-middle sticky-top" style="z-index: 5;">
                <tr>
                  <th style="width: 50px; min-width: 50px;" class="text-center">No</th>
                  <th style="width: 210px; min-width: 210px;">Kode Inventaris</th>
                  <th style="width: 170px; min-width: 170px;">Kategori</th>
                  <th style="width: 150px; min-width: 150px;">Merk</th>
                  <th style="width: 230px; min-width: 230px;">Model / Tipe</th>
                  <th style="width: 180px; min-width: 180px;">Serial Number</th>
                  <th style="width: 260px; min-width: 260px;">Kantor Cabang</th>
                  <th style="width: 190px; min-width: 190px;">Divisi / Satker</th>
                  <th style="width: 180px; min-width: 180px;">Pengguna / PIC</th>
                  <th style="width: 150px; min-width: 150px;">Alamat IP</th>
                  <th style="width: 170px; min-width: 170px;">Printer</th>
                  <th style="width: 150px; min-width: 150px;">Status</th>
                  <th style="width: 240px; min-width: 240px;">Keterangan</th>
                  <th style="width: 60px; min-width: 60px;" class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody id="previewTbody">';

    foreach ($previewRows as $idx => $r) {
        $rowNum = $idx + 1;
        $isDup = !empty($r['is_duplicate']);
        $rowClass = $isDup ? 'table-warning' : '';

        $body .= '
                <tr class="'.$rowClass.'" data-row-idx="'.$idx.'">
                  <td class="text-center text-muted fw-bold row-num">'.$rowNum.'</td>
                  <td>
                    <input type="text" name="items['.$idx.'][kode]" class="form-control form-control-sm font-monospace '.($isDup ? 'is-invalid border-danger fw-bold' : '').'" value="'.e($r['kode']).'" placeholder="Otomatis jika kosong">
                    '.($isDup ? '<div class="text-danger small fw-semibold mt-1" style="font-size:0.75rem;"><i class="bi bi-exclamation-circle-fill me-1"></i>Kode sudah ada di sistem</div>' : '').'
                  </td>
                  <td>
                    <select name="items['.$idx.'][kategori]" class="form-select form-select-sm">';
        foreach ($kategoriOpts as $ko) {
            $sel = (strcasecmp($r['kategori'], $ko) === 0 || strpos(strtolower($r['kategori']), strtolower($ko)) !== false) ? 'selected' : '';
            $body .= '<option value="'.e($ko).'" '.$sel.'>'.e($ko).'</option>';
        }
        $body .= '
                    </select>
                  </td>
                  <td><input type="text" name="items['.$idx.'][merk]" class="form-control form-control-sm" value="'.e($r['merk']).'" placeholder="Merk"></td>
                  <td><input type="text" name="items['.$idx.'][model]" class="form-control form-control-sm" value="'.e($r['model']).'" placeholder="Model/Tipe"></td>
                  <td><input type="text" name="items['.$idx.'][sn]" class="form-control form-control-sm font-monospace" value="'.e($r['sn']).'" placeholder="Serial Number"></td>
                  <td>
                    <select name="items['.$idx.'][cabang]" class="form-select form-select-sm">';
        foreach ($cabangOpts as $co) {
            $sel = (strcasecmp($r['cabang'], $co) === 0 || strpos(strtolower($co), strtolower($r['cabang'])) !== false) ? 'selected' : '';
            $body .= '<option value="'.e($co).'" '.$sel.'>'.e($co).'</option>';
        }
        $body .= '
                    </select>
                  </td>
                  <td>
                    <select name="items['.$idx.'][divisi]" class="form-select form-select-sm">';
        foreach ($divisiOpts as $do) {
            $sel = (strcasecmp($r['divisi'], $do) === 0 || strpos(strtolower($do), strtolower($r['divisi'])) !== false) ? 'selected' : '';
            $body .= '<option value="'.e($do).'" '.$sel.'>'.e($do).'</option>';
        }
        if (!in_array($r['divisi'], $divisiOpts, true) && $r['divisi'] !== '') {
            $body .= '<option value="'.e($r['divisi']).'" selected>'.e($r['divisi']).'</option>';
        }
        $body .= '
                    </select>
                  </td>
                  <td><input type="text" name="items['.$idx.'][karyawan]" class="form-control form-control-sm" value="'.e($r['karyawan']).'" placeholder="PIC"></td>
                  <td><input type="text" name="items['.$idx.'][ip]" class="form-control form-control-sm font-monospace" value="'.e($r['ip']).'" placeholder="192.168.x.x"></td>
                  <td><input type="text" name="items['.$idx.'][printer]" class="form-control form-control-sm" value="'.e($r['printer']).'" placeholder="Printer"></td>
                  <td>
                    <select name="items['.$idx.'][status]" class="form-select form-select-sm">';
        foreach ($statusOpts as $so) {
            $sel = (strcasecmp($r['status'], $so) === 0) ? 'selected' : '';
            $body .= '<option value="'.e($so).'" '.$sel.'>'.e($so).'</option>';
        }
        $body .= '
                    </select>
                  </td>
                  <td><input type="text" name="items['.$idx.'][ket]" class="form-control form-control-sm" value="'.e($r['ket']).'" placeholder="Catatan"></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" title="Hapus baris ini" onclick="deleteRow(this)">
                      <i class="bi bi-trash-fill"></i>
                    </button>
                  </td>
                </tr>';
    }

    $body .= '
              </tbody>
            </table>
          </div>

          <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pt-2 border-top">
            <div>
              <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" onclick="addNewRow()">
                <i class="bi bi-plus-circle"></i> Tambah Baris Kosong
              </button>
            </div>
            <div class="d-flex align-items-center gap-2">
              <a href="'.e(module_url('asset_import.php')).'" class="btn btn-light border px-3">
                <i class="bi bi-x-circle me-1"></i> Batal / Upload Ulang
              </a>
              <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm d-inline-flex align-items-center gap-2" id="btnAccept">
                <i class="bi bi-check2-circle fs-5"></i> Accept &amp; Simpan ke Database
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <script>
      function updateRowNumbers() {
        const rows = document.querySelectorAll("#previewTbody tr");
        rows.forEach((tr, index) => {
          const numCell = tr.querySelector(".row-num");
          if (numCell) numCell.textContent = (index + 1);
        });
        const badge = document.getElementById("rowCountBadge");
        if (badge) {
          badge.innerHTML = `<i class="bi bi-list-ol me-1"></i> ${rows.length} Baris Siap Diimport`;
        }
      }

      function deleteRow(btn) {
        const row = btn.closest("tr");
        if (row) {
          row.remove();
          updateRowNumbers();
        }
      }

      let newRowCounter = 9000;
      function addNewRow() {
        newRowCounter++;
        const tbody = document.getElementById("previewTbody");
        const idx = newRowCounter;
        const tr = document.createElement("tr");
        tr.setAttribute("data-row-idx", idx);
        tr.innerHTML = `
          <td class="text-center text-muted fw-bold row-num">-</td>
          <td><input type="text" name="items[${idx}][kode]" class="form-control form-control-sm font-monospace" placeholder="Otomatis jika kosong"></td>
          <td>
            <select name="items[${idx}][kategori]" class="form-select form-select-sm">
              <option value="PC Desktop">PC Desktop</option>
              <option value="Laptop">Laptop</option>
              <option value="Printer">Printer</option>
              <option value="Monitor">Monitor</option>
              <option value="Server">Server</option>
              <option value="Scanner">Scanner</option>
              <option value="UPS">UPS</option>
              <option value="Network Device">Network Device</option>
            </select>
          </td>
          <td><input type="text" name="items[${idx}][merk]" class="form-control form-control-sm" placeholder="Merk"></td>
          <td><input type="text" name="items[${idx}][model]" class="form-control form-control-sm" placeholder="Model/Tipe"></td>
          <td><input type="text" name="items[${idx}][sn]" class="form-control form-control-sm font-monospace" placeholder="Serial Number"></td>
          <td>
            <select name="items[${idx}][cabang]" class="form-select form-select-sm">
              <option value="Kantor Pusat Operasional">Kantor Pusat Operasional</option>
              <option value="Cabang Batulicin">Cabang Batulicin</option>
              <option value="Cabang Martapura">Cabang Martapura</option>
              <option value="Cabang Tanjung">Cabang Tanjung</option>
              <option value="Cabang Handil">Cabang Handil</option>
            </select>
          </td>
          <td>
            <select name="items[${idx}][divisi]" class="form-select form-select-sm">
              <option value="IT / MIS">IT / MIS</option>
              <option value="Operasional">Operasional</option>
              <option value="Akunting">Akunting</option>
              <option value="Kredit">Kredit</option>
              <option value="Direksi">Direksi</option>
              <option value="SKAI">SKAI</option>
              <option value="SDM & UMUM">SDM & UMUM</option>
              <option value="Kepatuhan">Kepatuhan</option>
            </select>
          </td>
          <td><input type="text" name="items[${idx}][karyawan]" class="form-control form-control-sm" placeholder="PIC"></td>
          <td><input type="text" name="items[${idx}][ip]" class="form-control form-control-sm font-monospace" placeholder="192.168.x.x"></td>
          <td><input type="text" name="items[${idx}][printer]" class="form-control form-control-sm" placeholder="Printer"></td>
          <td>
            <select name="items[${idx}][status]" class="form-select form-select-sm">
              <option value="Aktif">Aktif</option>
              <option value="Backup">Backup</option>
              <option value="Perbaikan">Perbaikan</option>
              <option value="Nonaktif">Nonaktif</option>
            </select>
          </td>
          <td><input type="text" name="items[${idx}][ket]" class="form-control form-control-sm" placeholder="Catatan"></td>
          <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" title="Hapus baris ini" onclick="deleteRow(this)">
              <i class="bi bi-trash-fill"></i>
            </button>
          </td>
        `;
        tbody.appendChild(tr);
        updateRowNumbers();
      }

      document.getElementById("formConfirmImport")?.addEventListener("submit", function() {
        const btn = document.getElementById("btnAccept");
        if (btn) {
          btn.disabled = true;
          btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan Aset...`;
        }
      });
    </script>';

// 3. TAMPILAN DEFAULT FORM UPLOAD FILE (STEP 1)
} else {
    $body .= '
    <div class="row g-4">
      <!-- Kolom Kiri: Langkah 1 & Form Upload File -->
      <div class="col-lg-7">
        
        <!-- STEP 1: DOWNLOAD TEMPLATE RESMI -->
        <div class="card border shadow-sm mb-4 bg-white" style="border-radius: 12px; border-color: var(--app-border) !important;">
          <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 26px; height: 26px; font-size: 0.8rem;">1</span>
              <h2 class="h6 mb-0 fw-bold text-dark">Unduh Format Template Excel</h2>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <a href="'.e(module_url('asset_import_template.php', ['format' => 'xlsx'])).'" class="btn btn-sm btn-success fw-bold px-3 shadow-sm d-inline-flex align-items-center gap-1">
                <i class="bi bi-file-earmark-excel-fill"></i> Download Excel (.xlsx) &mdash; Ada Dropdown
              </a>
              <a href="'.e(module_url('asset_import_template.php', ['format' => 'csv'])).'" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                <i class="bi bi-filetype-csv"></i> Format CSV
              </a>
            </div>
          </div>
          <div class="card-body p-4">
            <p class="text-secondary small mb-3">Gunakan template resmi yang telah disiapkan. Template Excel (<code>.xlsx</code>) telah dilengkapi <strong>Dropdown Pilihan Interaktif</strong> (Kategori, Cabang, Divisi, dan Status) sehingga Anda tinggal memilih dari daftar opsi yang sama persis dengan form sistem.</p>
            
            <div class="p-3 bg-light rounded-3 border">
              <div class="d-flex align-items-start gap-2">
                <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                <div class="small text-secondary">
                  <strong>Alur Import dengan Preview &amp; Perbaikan:</strong>
                  <ul class="mb-0 ps-3 mt-1" style="font-size: 0.78rem;">
                    <li><strong>Review Dulu:</strong> Setelah file diunggah, Anda akan melihat tabel preview seluruh data dan dapat mengedit atau menghapus baris sebelum disimpan.</li>
                    <li><strong>Kode Inventaris:</strong> Boleh diisi nomor register internal atau dikosongkan agar digenerate otomatis oleh sistem.</li>
                    <li><strong>Dropdown Otomatis:</strong> Kolom Kategori, Kantor Cabang, Divisi, dan Status Unit sudah dilengkapi dropdown.</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- STEP 2: FORM UPLOAD FILE -->
        <div class="card border shadow-sm bg-white" style="border-radius: 12px; border-color: var(--app-border) !important;">
          <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 26px; height: 26px; font-size: 0.8rem;">2</span>
            <h2 class="h6 mb-0 fw-bold text-dark">Unggah File untuk Di-Review</h2>
          </div>
          <div class="card-body p-4">
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
              <input type="hidden" name="action" value="process_import">

              <div class="mb-4">
                <label class="form-label fw-bold text-dark small">Pilih File Excel (.xlsx) atau CSV (.csv)</label>
                <div class="input-group">
                  <input type="file" name="file_excel" class="form-control" accept=".csv, .xlsx, .xls, text/csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                </div>
                <div class="form-text text-muted small mt-1">Mendukung format Microsoft Excel <code>.xlsx</code> dan <code>.csv</code>. Data tidak akan langsung disimpan ke database sebelum Anda me-review dan menekan tombol Accept.</div>
              </div>

              <div class="d-flex justify-content-end gap-2">
                <a href="'.e(module_url('assets.php')).'" class="btn btn-light border px-3">Batal</a>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm d-inline-flex align-items-center gap-2">
                  <i class="bi bi-eye-fill"></i> Upload &amp; Review Data
                </button>
              </div>
            </form>
          </div>
        </div>

      </div>

      <!-- Kolom Kanan: Panduan Struktur Kolom & Integrasi Database -->
      <div class="col-lg-5">
        <div class="card border shadow-sm bg-white" style="border-radius: 12px; border-color: var(--app-border) !important;">
          <div class="card-header bg-white py-3 px-4 border-bottom">
            <h2 class="h6 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
              <i class="bi bi-layout-text-window-reverse text-primary"></i> Struktur Kolom & Opsi Pilihan
            </h2>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
              <table class="table table-sm table-striped align-middle mb-0" style="font-size: 0.8rem;">
                <thead class="table-light">
                  <tr>
                    <th>Nama Kolom</th>
                    <th>Keterangan / Opsi Dropdown</th>
                    <th>Tipe</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><code>Kode Inventaris</code></td>
                    <td>Kode unit unik (misal: INV-KPO-001)</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Opsional</span></td>
                  </tr>
                  <tr>
                    <td><code>Kategori</code></td>
                    <td><strong>Dropdown:</strong> Laptop, PC Desktop, Printer, Monitor, Server, Scanner, UPS, Network Device</td>
                    <td><span class="badge bg-success-subtle text-success">Dropdown</span></td>
                  </tr>
                  <tr>
                    <td><code>Merk</code></td>
                    <td>Lenovo, Dell, HP, MSI, Epson, Asus, dll.</td>
                    <td><span class="badge bg-success-subtle text-success">Disarankan</span></td>
                  </tr>
                  <tr>
                    <td><code>Model / Tipe</code></td>
                    <td>ThinkCentre M70q, OptiPlex 3090, L3211, dll.</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Teks Bebas</span></td>
                  </tr>
                  <tr>
                    <td><code>Serial Number</code></td>
                    <td>Nomor seri pabrikan perangkat IT</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Teks Bebas</span></td>
                  </tr>
                  <tr>
                    <td><code>Kantor Cabang</code></td>
                    <td><strong>Dropdown:</strong> Kantor Pusat Operasional, Cabang Batulicin, Cabang Martapura, Cabang Tanjung, Cabang Handil</td>
                    <td><span class="badge bg-danger-subtle text-danger">Dropdown</span></td>
                  </tr>
                  <tr>
                    <td><code>Divisi / Unit Kerja</code></td>
                    <td><strong>Dropdown:</strong> IT / MIS, Operasional, Akunting, Kredit, Direksi, SKAI, SDM & UMUM, Kepatuhan</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Dropdown</span></td>
                  </tr>
                  <tr>
                    <td><code>Pengguna / PIC</code></td>
                    <td>Nama staf / pemegang unit (Teller, CS, dll)</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Teks Bebas</span></td>
                  </tr>
                  <tr>
                    <td><code>Alamat IP</code></td>
                    <td>Alamat IP lokal (misal: 192.168.1.50)</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Teks Bebas</span></td>
                  </tr>
                  <tr>
                    <td><code>Printer Terhubung</code></td>
                    <td>Nama printer yang terpasang</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Teks Bebas</span></td>
                  </tr>
                  <tr>
                    <td><code>Status Unit</code></td>
                    <td><strong>Dropdown:</strong> Aktif (Digunakan), Backup / Cadangan, Sedang Dalam Perbaikan, Nonaktif</td>
                    <td><span class="badge bg-warning-subtle text-warning-emphasis">Dropdown</span></td>
                  </tr>
                  <tr>
                    <td><code>Keterangan</code></td>
                    <td>Catatan penempatan perangkat atau fungsi khusus</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Teks Bebas</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>';
}

render_page('Import Data Komputer (Excel / CSV)', $body);
