<?php
/**
 * DIAGNOSA LENGKAP GOOGLE SHEETS & DATABASE
 * Akses: https://domain-vercel/debug_sheets.php
 */
require __DIR__ . '/bootstrap.php';

$results = [];
$alerts = [];

// 1. Cek Mode Google Cloud
$isCloud = is_google_cloud_mode();
$client = google_sheets_v4_client();

if (!$isCloud || !$client) {
    $html = '
    <div class="card p-4 border-danger shadow-sm">
      <h3 class="text-danger fw-bold"><i class="bi bi-x-circle me-2"></i>Mode Google Cloud TIDAK Aktif</h3>
      <p class="text-secondary">Environment variable Google Cloud Sheets belum lengkap atau tidak terbaca di server Vercel.</p>
      <ul>
        <li><code>GOOGLE_SPREADSHEET_ID</code>: ' . (cfg('google_spreadsheet_id', envv('GOOGLE_SPREADSHEET_ID')) ? '✅ Terisi' : '❌ KOSONG') . '</li>
        <li><code>GOOGLE_CLIENT_EMAIL</code>: ' . (cfg('google_client_email', envv('GOOGLE_CLIENT_EMAIL')) ? '✅ Terisi' : '❌ KOSONG') . '</li>
        <li><code>GOOGLE_PRIVATE_KEY</code>: ' . (cfg('google_private_key', envv('GOOGLE_PRIVATE_KEY')) ? '✅ Terisi' : '❌ KOSONG') . '</li>
      </ul>
      <div class="alert alert-info small">Aplikasi saat ini menggunakan fallback Database MySQL Lokal.</div>
    </div>';
    render_page('Diagnosa Google Sheets', '<div class="row justify-content-center"><div class="col-md-8">' . $html . '</div></div>');
    exit;
}

// 2. Handle Action Perbaikan Otomatis (?fix_users=1)
if (isset($_GET['fix_users'])) {
    $client->createSheetIfNotExists('Users');
    $client->ensureMinColumns('Users', 26);

    // Pasang 13 kolom header resmi
    $okH = $client->updateValues('Users!A1:M1', [[
        'id', 'username', 'password', 'nama', 'role', 'telepon', 'status', 'created_at',
        'face_descriptor', 'face_photo', 'face_status', 'passkey_credential', 'nama_panggilan'
    ]], 'RAW');

    // Cek apakah ada data pengguna dasar
    $existingUsers = $client->getValues('Users!A2:D3');
    if (empty($existingUsers)) {
        $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
        $teknisiHash = password_hash('teknisi123', PASSWORD_BCRYPT);
        $client->appendValues('Users!A:M', [
            [1, 'admin', $adminHash, 'Administrator', 'admin', '081234567890', 'Aktif', date('Y-m-d H:i:s'), '', '', 'none', '', 'Admin'],
            [2, 'teknisi', $teknisiHash, 'Teknisi IT', 'teknisi', '081234567891', 'Aktif', date('Y-m-d H:i:s'), '', '', 'none', '', 'Teknisi']
        ], 'RAW');
    }

    // Recovery & Perbaikan baris yang tergeser ke kanan (Kolom O..V / N..Z)
    $rawUserRows = $client->getValues('Users!A1:Z50');
    $shiftedFixed = 0;
    $cleanedCount = 0;

    foreach ($rawUserRows as $rIdx => $rRow) {
        $rowNum = $rIdx + 1;
        if ($rowNum <= 1) continue;

        $colA = trim((string)($rRow[0] ?? ''));
        $colO = trim((string)($rRow[14] ?? '')); // Indeks 14 = Kolom O

        // Jika Kolom A kosong tetapi Kolom O berisi ID numerik
        if ($colA === '' && is_numeric($colO) && (int)$colO > 0) {
            $id = (int)$colO;
            $username = (string)($rRow[15] ?? '');
            $password = (string)($rRow[16] ?? '');
            $nama = (string)($rRow[17] ?? '');
            $role = (string)($rRow[18] ?? 'teknisi');
            $telepon = (string)($rRow[19] ?? '-');
            $status = (string)($rRow[20] ?? 'Aktif');
            $createdAt = (string)($rRow[21] ?? date('Y-m-d H:i:s'));
            $fDesc = (string)($rRow[8] ?? '');
            $fPhoto = (string)($rRow[9] ?? '');
            $fStatus = (string)($rRow[10] ?? 'pending');
            $passkey = '';
            $namaPanggilan = get_nickname($nama);

            // Tulis rapi kembali ke Kolom A..M
            $fixedRow = [
                $id, $username, $password, $nama, $role, $telepon, $status, $createdAt,
                $fDesc, $fPhoto, $fStatus, $passkey, $namaPanggilan
            ];
            $client->updateValues("Users!A{$rowNum}:M{$rowNum}", [$fixedRow], 'RAW');

            // Kosongkan sel liar di Kolom N s.d. Z
            $client->clearValues("Users!N{$rowNum}:Z{$rowNum}");
            $shiftedFixed++;
        } else {
            // Bersihkan sel #ERROR! jika ada akibat formula parse di sheet
            $fDesc = $rRow[8] ?? '';
            $fPhoto = $rRow[9] ?? '';
            $needsClean = false;
            if (str_starts_with((string)$fDesc, '#') || str_starts_with((string)$fDesc, '=')) {
                $fDesc = '';
                $needsClean = true;
            }
            if (str_starts_with((string)$fPhoto, '#') || str_starts_with((string)$fPhoto, '=')) {
                $fPhoto = '';
                $needsClean = true;
            }
            if ($needsClean) {
                $client->updateValues("Users!I{$rowNum}:J{$rowNum}", [[$fDesc, $fPhoto]], 'RAW');
                $cleanedCount++;
            }
        }
    }

    $client->clearCache('Users');
    get_user_list(true);

    $alerts[] = '<div class="alert alert-success fw-bold"><i class="bi bi-check-circle-fill me-2"></i>Perbaikan tab <strong>Users</strong> berhasil! Header 13 kolom telah dipasang dan cache diperbarui.' . ($shiftedFixed > 0 ? " ({$shiftedFixed} baris tergeser ke kanan berhasil dikembalikan ke Kolom A!)" : '') . ($cleanedCount > 0 ? " ({$cleanedCount} sel #ERROR! dibersihkan)" : '') . '</div>';
}

// 3. Ambil Metadata Spreadsheet
$meta = $client->getSpreadsheetMeta();
$metaHtml = '';
$tabProperties = [];
if ($meta) {
    $sheetTitle = $meta['properties']['title'] ?? 'Google Sheet';
    $timeZone = $meta['properties']['timeZone'] ?? 'UTC';
    $sheetsList = $meta['sheets'] ?? [];

    $tabsSummary = [];
    foreach ($sheetsList as $s) {
        $p = $s['properties'] ?? [];
        $tName = $p['title'] ?? '?';
        $rCount = $p['gridProperties']['rowCount'] ?? 0;
        $cCount = $p['gridProperties']['columnCount'] ?? 0;
        $tabProperties[$tName] = ['rows' => $rCount, 'cols' => $cCount, 'id' => $p['sheetId'] ?? 0];
        $tabsSummary[] = "<strong>{$tName}</strong> ({$cCount} kol, {$rCount} baris)";
    }

    $metaHtml = '
    <div class="alert alert-success border border-success border-opacity-25 bg-success bg-opacity-10 mb-4 p-3 rounded-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-cloud-check-fill text-success fs-4"></i>
        <div>
          <strong class="text-dark">Koneksi Google Sheets API v4 Terhubung Aktif</strong><br>
          <span class="small text-muted font-monospace">Spreadsheet: ' . e($sheetTitle) . ' (' . e($timeZone) . ')</span>
        </div>
      </div>
      <div class="small text-secondary">
        <strong>Tab Ditemukan (' . count($sheetsList) . '):</strong> ' . implode(' · ', $tabsSummary) . '
      </div>
    </div>';
} else {
    $lastErr = $client->getLastError();
    $metaHtml = '
    <div class="alert alert-danger mb-4 p-3 rounded-3">
      <div class="fw-bold fs-6"><i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal Mengakses Google Sheets API:</div>
      <div class="small mt-1 font-monospace bg-white p-2 rounded text-danger">' . e($lastErr ?: 'Periksa kredensial atau izin Service Account ke Spreadsheet.') . '</div>
      <div class="small mt-2"><strong>Solusi:</strong> Buka Google Sheet Anda di browser, klik tombol <strong>Share (Bagikan)</strong>, dan tambahkan email service account Anda: <code>' . e($client->getClientEmail()) . '</code> sebagai <strong>Editor</strong>.</div>
    </div>';
}

// 4. Diagnosa Tab Users & Biometrik
$userHeaders = $client->getValues('Users!A1:M1');
$uHeaderRow = $userHeaders[0] ?? [];
$usersData = $client->getSheetData('Users', true);
$usersColsCount = $tabProperties['Users']['cols'] ?? 0;

$expectedCols = ['id', 'username', 'password', 'nama', 'role', 'telepon', 'status', 'created_at', 'face_descriptor', 'face_photo', 'face_status', 'passkey_credential', 'nama_panggilan'];
$missingCols = [];
foreach ($expectedCols as $ec) {
    if (!in_array($ec, $uHeaderRow, true)) {
        $missingCols[] = $ec;
    }
}

$userDiagnoseCard = '';
if (empty($uHeaderRow)) {
    $userDiagnoseCard = '
    <div class="card border border-danger shadow-sm p-3 mb-4 bg-danger bg-opacity-10">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h5 class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-circle-fill me-2"></i>Tab Users Belum Memiliki Header / Kosong</h5>
          <div class="small text-secondary">Tab Users belum di-setup dengan 13 kolom biometrik dan akun.</div>
        </div>
        <a href="debug_sheets.php?fix_users=1" class="btn btn-danger btn-sm fw-bold"><i class="bi bi-tools me-1"></i> Perbaiki Tab Users Sekarang</a>
      </div>
    </div>';
} elseif (!empty($missingCols) || $usersColsCount < 13) {
    $userDiagnoseCard = '
    <div class="card border border-warning shadow-sm p-3 mb-4 bg-warning bg-opacity-10">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h5 class="fw-bold text-warning mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Kolom Tab Users Belum Lengkap</h5>
          <div class="small text-secondary">Kolom terdeteksi: <strong>' . count($uHeaderRow) . '</strong> dari 13. Kolom belum ada: <code>' . implode(', ', $missingCols) . '</code></div>
        </div>
        <a href="debug_sheets.php?fix_users=1" class="btn btn-warning btn-sm fw-bold"><i class="bi bi-wrench me-1"></i> Lengkapi 13 Kolom Users</a>
      </div>
    </div>';
} else {
    $userDiagnoseCard = '
    <div class="card border border-success shadow-sm p-3 mb-4 bg-success bg-opacity-10">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h6 class="fw-bold text-success mb-0"><i class="bi bi-check-circle-fill me-2"></i>Tab Users Sempurna (13 Kolom Lengkap)</h6>
          <div class="small text-secondary mt-1">Header: <code>' . implode(' | ', $uHeaderRow) . '</code></div>
        </div>
        <a href="debug_sheets.php?fix_users=1" class="btn btn-outline-success btn-sm"><i class="bi bi-arrow-repeat me-1"></i> Refresh & Bersihkan</a>
      </div>
    </div>';
}

// 5. Tabel Ringkasan Pengguna Terkini di Google Sheets
$userRowsHtml = '';
foreach ($usersData as $u) {
    $uid = $u['id'] ?? '?';
    $unama = $u['nama'] ?? '-';
    $uuser = $u['username'] ?? '-';
    $urole = $u['role'] ?? '-';
    $ustat = $u['status'] ?? '-';
    $fStat = strtolower(trim((string)($u['face_status'] ?? 'none')));
    $fDesc = (string)($u['face_descriptor'] ?? '');
    $fPhoto = (string)($u['face_photo'] ?? '');

    $hasErr = str_starts_with($fDesc, '#') || str_starts_with($fPhoto, '#');
    $descStatus = '';
    if ($hasErr) {
        $descStatus = '<span class="badge bg-danger">❌ ' . e($fDesc) . ' (Formula Error)</span>';
    } elseif (strlen($fDesc) > 50) {
        $descStatus = '<span class="badge bg-success">✅ Vektor Wajah Terpasang (' . strlen($fDesc) . ' char)</span>';
    } else {
        $descStatus = '<span class="badge bg-secondary">Belum ada wajah</span>';
    }

    $photoHtml = $fPhoto !== '' && !str_starts_with($fPhoto, '#')
        ? '<img src="' . e($fPhoto) . '" class="rounded-circle border border-2 border-primary" style="width: 40px; height: 40px; object-fit: cover;">'
        : '<div class="bg-light rounded-circle text-muted d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="bi bi-person"></i></div>';

    $userRowsHtml .= '
    <tr>
      <td class="text-center font-monospace">' . e($uid) . '</td>
      <td>' . $photoHtml . '</td>
      <td><strong>' . e($unama) . '</strong><div class="small text-muted font-monospace">@' . e($uuser) . '</div></td>
      <td><span class="badge bg-light text-dark border">' . e($urole) . '</span></td>
      <td>' . $descStatus . '</td>
      <td><span class="badge ' . ($fStat === 'verified' ? 'bg-success' : ($fStat === 'pending' ? 'bg-warning text-dark' : 'bg-secondary')) . '">' . e($fStat) . '</span></td>
      <td><a class="btn btn-xs btn-outline-primary py-0 px-2 small" href="user_biometric_enroll.php?id=' . (int)$uid . '">Scan HP</a></td>
    </tr>';
}

// 6. Ringkasan Seluruh Tab Lain
$allTabs = ['Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Assets', 'Asset_QR_Tokens', 'Maintenance_Scan', 'Maintenance_Checklists', 'Maintenance_Findings'];
$tabsStatusHtml = '';
foreach ($allTabs as $t) {
    $tData = $client->getSheetData($t);
    $c = count($tData);
    $cols = $tabProperties[$t]['cols'] ?? '?';
    $tabsStatusHtml .= '
    <div class="col-md-6 col-lg-4 mb-2">
      <div class="p-2 border rounded bg-white shadow-sm d-flex justify-content-between align-items-center">
        <div>
          <strong class="text-dark">' . e($t) . '</strong>
          <div class="small text-muted">' . $cols . ' kolom grid</div>
        </div>
        <span class="badge ' . ($c > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle') . ' fw-bold">' . $c . ' baris</span>
      </div>
    </div>';
}

$body = '
<div class="container py-3">
  ' . implode('', $alerts) . '
  ' . $metaHtml . '

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-speedometer2 text-primary me-2"></i>Diagnosa Spreadsheet & Biometrik</h4>
      <div class="small text-secondary">Pemeriksaan struktur tab, header kolom, dan integritas data pengguna.</div>
    </div>
    <div class="d-flex gap-2">
      <a href="debug_sheets.php?fix_users=1" class="btn btn-warning fw-bold btn-sm"><i class="bi bi-wrench me-1"></i> Perbaiki & Refresh Sheet</a>
      <a href="' . e(module_url('users_admin.php')) . '" class="btn btn-primary fw-bold btn-sm"><i class="bi bi-people-fill me-1"></i> Kelola Pengguna</a>
      <a href="' . e(module_url('dashboard.php')) . '" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house me-1"></i> Dashboard</a>
    </div>
  </div>

  ' . $userDiagnoseCard . '

  <!-- Tabel Data Users di Spreadsheet -->
  <div class="card p-3 p-md-4 border-0 shadow-sm mb-4 bg-white">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
      <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-bounding-box text-primary me-2"></i>Data Pengguna & Status Biometrik di Google Sheet (' . count($usersData) . ')</h5>
      <span class="badge bg-primary-subtle text-primary">Tab: Users</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th style="width: 50px;" class="text-center">ID</th>
            <th style="width: 50px;">Foto</th>
            <th>Nama & Username</th>
            <th>Role</th>
            <th>Data Vektor AI</th>
            <th>Status Wajah</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          ' . ($userRowsHtml ?: '<tr><td colspan="7" class="text-center py-3 text-muted">Belum ada baris data pengguna di sheet Users.</td></tr>') . '
        </tbody>
      </table>
    </div>
  </div>

  <!-- Status Tab Master Data Lain -->
  <div class="card p-3 p-md-4 border-0 shadow-sm mb-4 bg-white">
    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="bi bi-table text-primary me-2"></i>Status Tab Lainnya di Google Sheets</h5>
    <div class="row">
      ' . $tabsStatusHtml . '
    </div>
  </div>

</div>';

render_page('Diagnosa Google Sheets', $body);
