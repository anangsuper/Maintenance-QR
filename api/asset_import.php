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

// Fungsi Parser CSV Cerdas (Mampu mendeteksi pemisah ; , \t dan membuang UTF-8 BOM)
function parse_uploaded_csv(string $filepath): array {
    $content = @file_get_contents($filepath);
    if ($content === false || trim($content) === '') {
        return [];
    }

    // Bersihkan UTF-8 BOM
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }

    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    if (empty($lines)) return [];

    // Deteksi Delimiter otomatis dari baris pertama
    $firstLine = $lines[0];
    $delimiters = [';' => 0, ',' => 0, "\t" => 0, '|' => 0];
    foreach ($delimiters as $d => $count) {
        $delimiters[$d] = substr_count($firstLine, $d);
    }
    arsort($delimiters);
    $delim = key($delimiters) ?: ';';

    $handle = fopen($filepath, 'r');
    if (!$handle) return [];

    // Skip BOM pada handle
    $bomCheck = fread($handle, 3);
    if ($bomCheck !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $rows = [];
    while (($data = fgetcsv($handle, 4096, $delim)) !== false) {
        // Lewati baris kosong
        if (count($data) === 1 && trim((string)$data[0]) === '') {
            continue;
        }
        $rows[] = array_map(function($v) {
            return trim((string)$v);
        }, $data);
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
                foreach ($r->c as $c) {
                    $type = (string)($c['t'] ?? '');
                    $val = (string)($c->v ?? '');
                    if ($type === 's' && isset($sharedStrings[(int)$val])) {
                        $val = $sharedStrings[(int)$val];
                    }
                    $rowCells[] = trim($val);
                }
                if (!empty($rowCells) && implode('', $rowCells) !== '') {
                    $rows[] = $rowCells;
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

// PROSES POST FORM IMPORT
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

    $masters = get_master_maps();
    $client = is_google_cloud_mode() ? google_sheets_v4_client() : null;

    $successCount = 0;
    $skipCount = 0;
    $importedItems = [];

    // Ambil aset yang sudah ada untuk mendeteksi duplikasi kode inventaris
    $existingAssets = map_sheets_assets(true);
    $existingCodes = [];
    foreach ($existingAssets as $ea) {
        $cNorm = strtoupper(trim((string)$ea['kode_inventaris']));
        if ($cNorm !== '') $existingCodes[$cNorm] = true;
    }

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
        if ($kode === '' && $merk === '' && $model === '') {
            continue;
        }

        // Cek duplikasi kode inventaris
        $normKode = strtoupper($kode);
        if ($normKode !== '' && isset($existingCodes[$normKode])) {
            $skipCount++;
            continue;
        }

        $idCab = resolve_cabang_id($cabangStr, $masters['cabangs']);
        $idDiv = resolve_divisi_id($divisiStr, $masters['divisis'], $client);
        $idKat = resolve_kategori_id($kategoriStr, $masters['kategoris'], $client);

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
            if ($normKode !== '') {
                $existingCodes[$normKode] = true;
            }
            $importedItems[] = [
                'kode'      => $kode ?: ('ASET-' . ($res['id'] ?? $successCount)),
                'nama'      => trim($merk . ' ' . $model) ?: 'Perangkat IT',
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

// Jika ada hasil import
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
        foreach (array_slice($importResult['items'], 0, 15) as $item) {
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
        if (count($importResult['items']) > 15) {
            $body .= '<div class="text-muted small text-center mt-2">... dan ' . (count($importResult['items']) - 15) . ' unit lainnya berhasil diimpor.</div>';
        }
    }

    $body .= '
      </div>
    </div>';
}

$body .= '
<!-- Flash Messages -->
'.($flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '').'
'.($flashError ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($flashError).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '').'

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
              <strong>Fitur & Petunjuk Template Excel:</strong>
              <ul class="mb-0 ps-3 mt-1" style="font-size: 0.78rem;">
                <li><strong>Dropdown Otomatis:</strong> Kolom <em>Kategori</em>, <em>Kantor Cabang</em>, <em>Divisi</em>, dan <em>Status Unit</em> memiliki panah dropdown di Excel.</li>
                <li><strong>Kode Inventaris:</strong> Boleh diisi nomor register internal atau dikosongkan agar digenerate otomatis.</li>
                <li><strong>Kemudahan Pengisian:</strong> Cukup klik sel pada kolom bersangkutan lalu pilih opsi yang sesuai dari dropdown.</li>
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
        <h2 class="h6 mb-0 fw-bold text-dark">Unggah File yang Telah Diisi</h2>
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
            <div class="form-text text-muted small mt-1">Mendukung format Microsoft Excel <code>.xlsx</code> dan <code>.csv</code>.</div>
          </div>

          <div class="p-3 bg-light rounded-3 border mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="skipDuplicateCheck" checked disabled>
              <label class="form-check-label small fw-semibold text-dark" for="skipDuplicateCheck">
                Abaikan & lewati jika Kode Inventaris sudah terdaftar di sistem (Cegah Duplikasi)
              </label>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="'.e(module_url('assets.php')).'" class="btn btn-light border px-3">Batal</a>
            <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm d-inline-flex align-items-center gap-2">
              <i class="bi bi-cloud-arrow-up-fill"></i> Mulai Proses Import
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

render_page('Import Data Komputer (Excel / CSV)', $body);
