<?php
require __DIR__ . '/bootstrap.php';
require_login();

// ================================================================
// IMPORT BATCH LANGSUNG KE GOOGLE SHEETS (1x tulis semua data)
// ================================================================

$rawData = [
    ['kode'=>'0150035418012023','model'=>'HP Victus 15-FB0012AX','merk'=>'HP','lokasi'=>'LT.4/KPO/IT','sn'=>'SN5CD235GFRS','pemilik'=>'Arya','jabatan'=>'Kepala Satker MIS & IT'],
    ['kode'=>'02008104112016','model'=>'PC DESKTOP','merk'=>'PC','lokasi'=>'LT.4/KPO/IT','sn'=>'-','pemilik'=>'Hafizh','jabatan'=>'Staff Satker MIS & IT'],
    ['kode'=>'0150018606012021','model'=>'ASUS Vivobook','merk'=>'Asus','lokasi'=>'LT.4/KPO','sn'=>'LBN0CX01L884451','pemilik'=>'Noni','jabatan'=>'Internal Control Unit'],
    ['kode'=>'0150044624012025','model'=>'HP 14S','merk'=>'HP','lokasi'=>'LT.4/KPI/KadivKredit','sn'=>'5CD4256MS3','pemilik'=>'Rifqi','jabatan'=>'Kadiv Kredit'],
    ['kode'=>'011202061223001','model'=>'HP 14s-dq5xxx','merk'=>'HP','lokasi'=>'LT.4/KPO/Hrd','sn'=>'5CD337BKRN','pemilik'=>'Defie','jabatan'=>'Kabag Umum & SDM'],
    ['kode'=>'0150031008082022','model'=>'Lenovo IdeaPad 3','merk'=>'Lenovo','lokasi'=>'LT.4/KPO/SKAI','sn'=>'PF3NZNGN','pemilik'=>'Vina','jabatan'=>'Staff SKAI'],
    ['kode'=>'0150024603112021','model'=>'HP 14s-cf2xxx','merk'=>'HP','lokasi'=>'LT.4/KPO/SKAI','sn'=>'5CG13356NJ','pemilik'=>'Tri','jabatan'=>'Kepala SKAI'],
    ['kode'=>'0150010418102018','model'=>'Asus A442UF','merk'=>'Asus','lokasi'=>'KPO/Laptop Meeting','sn'=>'J5N0CV03Y147196','pemilik'=>'Laptop Meeting','jabatan'=>'Staff Satker MIS & IT'],
    ['kode'=>'','model'=>'Lenovo IdeaPad 3','merk'=>'Lenovo','lokasi'=>'LT.3/KPO/KaCab','sn'=>'5CG1181ZFC','pemilik'=>'Wahyu','jabatan'=>'Kepala Cabang'],
    ['kode'=>'0150030012042022','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/AC','sn'=>'8CC2030GCK','pemilik'=>'Roni','jabatan'=>'Kabag Analis Kredit'],
    ['kode'=>'0150020709072021','model'=>'Acer AIO','merk'=>'Acer','lokasi'=>'LT.3/KPO/AC','sn'=>'DQBG0SN00111402A163000','pemilik'=>'Anwar','jabatan'=>'Analis Kredit'],
    ['kode'=>'0150045370042025','model'=>'Asus Mini PC','merk'=>'Asus','lokasi'=>'LT.3/KPO/AO','sn'=>'-','pemilik'=>'AO1','jabatan'=>'Account Officer'],
    ['kode'=>'0150045430042025','model'=>'Asus Mini PC','merk'=>'Asus','lokasi'=>'LT.3/KPO/AO','sn'=>'-','pemilik'=>'AO2','jabatan'=>'Account Officer'],
    ['kode'=>'015026502122021','model'=>'Lenovo IdeaCentre AIO 3-24ITL6','merk'=>'Lenovo','lokasi'=>'LT.3/KPO/DirUt','sn'=>'MP20W1QQ','pemilik'=>'Anton','jabatan'=>'Direktur Utama'],
    ['kode'=>'015025919112021','model'=>'Dell AIO 5400','merk'=>'Dell','lokasi'=>'LT.3/KPO/DirBis','sn'=>'4QGJG92','pemilik'=>'Didiet','jabatan'=>'Direktur Bisnis'],
    ['kode'=>'010802061223001','model'=>'HP Laptop 14s-dq5xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/DirDivKrd','sn'=>'5CD336MQR4','pemilik'=>'Roy','jabatan'=>'Kadiv Kredit'],
    ['kode'=>'0150031226092022','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/AdmColl','sn'=>'8CC213298B','pemilik'=>'Recha','jabatan'=>'Admin Collection'],
    ['kode'=>'0150030801082022','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/KaBagColl','sn'=>'PF3P3SE9','pemilik'=>'Deni','jabatan'=>'Kabag Collection'],
    ['kode'=>'02002810122007-A','model'=>'PC DESKTOP','merk'=>'PC Custom','lokasi'=>'LT.3/KPO/KaBagRecov','sn'=>'-','pemilik'=>'Yanoor','jabatan'=>'Kabag Remedial & Recovery'],
    ['kode'=>'02002810122007-B','model'=>'PC DESKTOP','merk'=>'PC Custom','lokasi'=>'LT.3/KPO/Coll','sn'=>'-','pemilik'=>'Hilman','jabatan'=>'Collection Officer'],
    ['kode'=>'02008104112016-B','model'=>'PC DESKTOP','merk'=>'PC Custom','lokasi'=>'LT.3/KPO/Coll','sn'=>'-','pemilik'=>'Mahbub','jabatan'=>'Collection Officer'],
    ['kode'=>'0150017504092020','model'=>'Asus VivoBook X409BA','merk'=>'Asus','lokasi'=>'LT.3/KPO/FO','sn'=>'L7N0CV15Y36730B','pemilik'=>'Funding1','jabatan'=>'Funding Officer'],
    ['kode'=>'0150016104022020','model'=>'Asus VivoBook 14','merk'=>'Asus','lokasi'=>'LT.3/KPO/FO','sn'=>'KBN0GR03N553479','pemilik'=>'Funding2','jabatan'=>'Funding Officer'],
    ['kode'=>'010802061223001-B','model'=>'HP All-in-One 22-dd2xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/KaBagAdmKrd','sn'=>'8CC33514HS','pemilik'=>'Yanti','jabatan'=>'Kabag Admin Kredit'],
    ['kode'=>'0150047417122025','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/Legal','sn'=>'8CC2030GD0','pemilik'=>'Heny','jabatan'=>'Legal'],
    ['kode'=>'0150029812042022','model'=>'Asus Vivo AIO 22','merk'=>'Asus','lokasi'=>'LT.3/KPO/AdmKrd','sn'=>'M5PTCJ00W693215','pemilik'=>'Zada','jabatan'=>'Admin Kredit'],
    ['kode'=>'0150029912042022','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/AdmKrd','sn'=>'8CC2011ZSR','pemilik'=>'Herli','jabatan'=>'Admin Kredit'],
    ['kode'=>'0150009518052018','model'=>'Asus X441NA','merk'=>'Asus','lokasi'=>'LT.3/KPO/Krd','sn'=>'HCN0CV15H64151G','pemilik'=>'Kredit','jabatan'=>'Kredit'],
    ['kode'=>'0150036217022023','model'=>'HP Victus 15','merk'=>'HP','lokasi'=>'LT.2/KPO/Kepatuhan','sn'=>'5CD225N6GR','pemilik'=>'Hasan','jabatan'=>'Direktur Kepatuhan'],
    ['kode'=>'0150019616042021','model'=>'Asus Vivo AIO','merk'=>'Asus','lokasi'=>'LT.2/KPO/Kepatuhan','sn'=>'M1PTCJ00T37704A','pemilik'=>'Febri','jabatan'=>'PE Kepatuhan'],
    ['kode'=>'0150047110122025','model'=>'LENOVO V15','merk'=>'Lenovo','lokasi'=>'LT.2/KPO/Kepatuhan','sn'=>'PF50HRLB','pemilik'=>'Yulika','jabatan'=>'Unit Kerja Khusus APU & PPT'],
    ['kode'=>'0150044524012025','model'=>'HP Laptop 14s-dq5xxx','merk'=>'HP','lokasi'=>'LT.2/KPO/Kepatuhan','sn'=>'5CD433D2SR','pemilik'=>'Novi','jabatan'=>'PE Manajemen Risiko'],
    ['kode'=>'0150045726062025','model'=>'Microsoft Surface Pro 7','merk'=>'Microsoft','lokasi'=>'LT.1/KPO/CS','sn'=>'63683204153','pemilik'=>'CS 1','jabatan'=>'Customer Service'],
    ['kode'=>'0150047731122025','model'=>'Lenovo IdeaPad 3','merk'=>'Lenovo','lokasi'=>'LT.1/KPO/CS','sn'=>'-','pemilik'=>'CS 2','jabatan'=>'Customer Service'],
    ['kode'=>'0150026402122021','model'=>'HP 14 S','merk'=>'HP','lokasi'=>'LT.1/KPO/DirOPS','sn'=>'SCD13542Q2','pemilik'=>'Kadiv Operasional','jabatan'=>'Kadiv Operasional'],
    ['kode'=>'0150035322122022','model'=>'Asus Vivo AIO','merk'=>'Asus','lokasi'=>'LT.1/KPO/KaDivOPS','sn'=>'M1PTCJ00T438046','pemilik'=>'Dir Operasional','jabatan'=>'Dir Operasional'],
    ['kode'=>'0150044705022025','model'=>'Lenovo V15 G3','merk'=>'Lenovo','lokasi'=>'LT.1/KPO/BO','sn'=>'-','pemilik'=>'Marinda','jabatan'=>'Staff Akunting'],
    ['kode'=>'0150036527022023','model'=>'Asus Vivobook 15','merk'=>'Asus','lokasi'=>'LT.1/KPO/BO','sn'=>'NAN0CV02E883417','pemilik'=>'Vidi','jabatan'=>'Kabag Akunting'],
    ['kode'=>'0150046228072025','model'=>'HP Laptop 15-fd0xxx','merk'=>'HP','lokasi'=>'LT.1/KPO/BO','sn'=>'5CD423GW3L','pemilik'=>'Kharissa','jabatan'=>'Supervisor Operasional'],
    ['kode'=>'0150020205052021','model'=>'Asus Vivo AIO','merk'=>'Asus','lokasi'=>'LT.1/KPO/Teller','sn'=>'M3PTCJ00602409A','pemilik'=>'Teller 1','jabatan'=>'Teller'],
    ['kode'=>'0150020105052021','model'=>'Asus Vivo AIO','merk'=>'Asus','lokasi'=>'LT.1/KPO/Teller','sn'=>'KBN0GR01Z40845C','pemilik'=>'Teller 2','jabatan'=>'Teller'],
    ['kode'=>'0150019903052021','model'=>'Microsoft Surface Pro 7','merk'=>'Microsoft','lokasi'=>'LT.1/KPO/KaDivOPS','sn'=>'5CD4256MS3','pemilik'=>'Nana','jabatan'=>'Kadiv Operasional'],
    ['kode'=>'012202090523001','model'=>'ASUS A1502A-VIPS353','merk'=>'Asus','lokasi'=>'LT.2/KPO/DirBis','sn'=>'NANOCVO2E981418','pemilik'=>'Didiet','jabatan'=>'Direktur Bisnis'],
    ['kode'=>'0150020003052021','model'=>'Microsoft Surface Pro 7','merk'=>'Microsoft','lokasi'=>'LT.1/KPO/DirOPS','sn'=>'063683204153','pemilik'=>'Kahar','jabatan'=>'Direktur Bisnis'],
];

$totalData = count($rawData);

// Deteksi kategori dari nama model
function detect_kat(string $model, string $merk): int {
    $m = strtolower($model . ' ' . $merk);
    if (str_contains($m, 'mini pc')) return 5;
    if (str_contains($m, 'all-in-one') || str_contains($m, 'aio') || str_contains($m, 'vivo aio') || str_contains($m, 'ideacentre')) return 3;
    if (str_contains($m, 'desktop') || $merk === 'PC Custom') return 2;
    return 1; // Laptop
}

// ====== PROSES IMPORT ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    verify_csrf();

    if (!is_google_cloud_mode()) {
        echo '<div class="alert alert-danger">Import ini khusus untuk mode Google Sheets.</div>';
        exit;
    }

    $client = google_sheets_v4_client();
    if (!$client) {
        echo '<div class="alert alert-danger">Google Sheets client tidak tersedia. Periksa konfigurasi API.</div>';
        exit;
    }

    // 1. Baca data yang sudah ada di Sheets
    $existingAssets = $client->getSheetData('Assets', true);
    $existingKaryawan = $client->getSheetData('Karyawan', true);
    $existingQr = $client->getSheetData('Asset_QR_Tokens', true);
    $existingCabang = $client->getSheetData('Cabang', true);

    // Cari maxId yang sudah ada
    $maxAssetId = 0;
    foreach ($existingAssets as $a) {
        $aid = (int)($a['id'] ?? 0);
        if ($aid > $maxAssetId) $maxAssetId = $aid;
    }

    $maxKarId = 0;
    foreach ($existingKaryawan as $k) {
        $kid = (int)($k['id'] ?? 0);
        if ($kid > $maxKarId) $maxKarId = $kid;
    }

    $maxQrId = 0;
    foreach ($existingQr as $q) {
        $qid = (int)($q['id'] ?? 0);
        if ($qid > $maxQrId) $maxQrId = $qid;
    }

    // Cari / buat cabang KPO
    $cabangKpoId = 0;
    foreach ($existingCabang as $c) {
        $cn = strtolower(trim($c['nama_cabang'] ?? $c['nama'] ?? ''));
        if (str_contains($cn, 'kpo') || str_contains($cn, 'kantor pusat')) {
            $cabangKpoId = (int)($c['id'] ?? 0);
            break;
        }
    }
    if ($cabangKpoId === 0) {
        $maxCabId = 0;
        foreach ($existingCabang as $c) {
            $cid = (int)($c['id'] ?? 0);
            if ($cid > $maxCabId) $maxCabId = $cid;
        }
        $cabangKpoId = $maxCabId + 1;
        $client->appendValues('Cabang!A:E', [[$cabangKpoId, 'KPO (Kantor Pusat Operasional)', '', '', '']]);
    }

    // Cari divisi IT (default = 1)
    $divisiId = 1;
    foreach ($existingAssets as $a) {
        // ambil divisi dari asset pertama yang ada
        if (!empty($a['id_divisi'])) { $divisiId = (int)$a['id_divisi']; break; }
    }

    // 2. Siapkan map karyawan yang sudah ada (nama => id)
    $karMap = [];
    foreach ($existingKaryawan as $k) {
        $kName = strtolower(trim($k['nama_karyawan'] ?? $k['nama'] ?? ''));
        if ($kName !== '') $karMap[$kName] = (int)($k['id'] ?? 0);
    }

    // 3. Bangun batch rows: Assets, Karyawan baru, QR Tokens
    $newAssetRows = [];
    $newKarRows = [];
    $newQrRows = [];
    $results = [];
    $nextAssetId = $maxAssetId + 1;
    $nextKarId = $maxKarId + 1;
    $nextQrId = $maxQrId + 1;

    foreach ($rawData as $idx => $row) {
        $no = $idx + 1;
        $kode = trim($row['kode']);
        if ($kode === '') $kode = sprintf('INV-KPO-%03d', $nextAssetId);

        $merk = $row['merk'];
        $model = $row['model'];
        $sn = $row['sn'];
        $pemilik = trim($row['pemilik']);
        $katId = detect_kat($model, $merk);
        $ket = 'Lokasi: ' . $row['lokasi'] . ' | Jabatan: ' . $row['jabatan'] . ' | Kondisi: Baik';

        // Cari atau siapkan karyawan
        $karId = 0;
        $pemilikLower = strtolower($pemilik);
        if (isset($karMap[$pemilikLower])) {
            $karId = $karMap[$pemilikLower];
        } else {
            $karId = $nextKarId;
            $karMap[$pemilikLower] = $karId;
            $newKarRows[] = [$nextKarId, $pemilik, $cabangKpoId, $divisiId];
            $nextKarId++;
        }

        // Asset row: id, kode, merk, model, sn, id_kategori, id_cabang, id_divisi, id_karyawan, status, keterangan
        $assetId = $nextAssetId;
        $newAssetRows[] = [$assetId, $kode, $merk, $model, $sn, $katId, $cabangKpoId, $divisiId, $karId, 'Aktif', $ket];

        // QR Token
        $token = bin2hex(random_bytes(16));
        $newQrRows[] = [$nextQrId, $assetId, $token, 'Bodi Casing', 1, date('Y-m-d H:i:s')];

        $results[] = ['no' => $no, 'kode' => $kode, 'model' => $merk . ' ' . $model, 'pemilik' => $pemilik];

        $nextAssetId++;
        $nextQrId++;
    }

    // 4. BATCH APPEND ke Google Sheets (1x tulis per tab)
    $errorMsg = '';

    if (!empty($newKarRows)) {
        $ok = $client->appendValues('Karyawan!A:D', $newKarRows);
        if (!$ok) $errorMsg .= 'Gagal tulis Karyawan. ';
    }

    if (!empty($newAssetRows)) {
        $ok = $client->appendValues('Assets!A:K', $newAssetRows);
        if (!$ok) $errorMsg .= 'Gagal tulis Assets. ';
    }

    if (!empty($newQrRows)) {
        $ok = $client->appendValues('Asset_QR_Tokens!A:F', $newQrRows);
        if (!$ok) $errorMsg .= 'Gagal tulis QR Tokens. ';
    }

    // 5. Clear all caches
    $client->clearCache();
    map_sheets_assets(true);

    // Tampilan Hasil
    $tableHtml = '';
    foreach ($results as $r) {
        $tableHtml .= '<tr>
            <td class="text-center">'.$r['no'].'</td>
            <td class="fw-bold text-primary">'.e($r['kode']).'</td>
            <td class="fw-semibold">'.e($r['model']).'</td>
            <td>'.e($r['pemilik']).'</td>
            <td><span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> OK</span></td>
        </tr>';
    }

    $statusMsg = $errorMsg
        ? '<div class="alert alert-warning border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i>Beberapa data mungkin gagal: '.e($errorMsg).'</div>'
        : '<div class="alert alert-success border-0 shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2 fs-5"></i><strong>Berhasil!</strong> Semua '.$totalData.' data komputer KPO telah dimasukkan ke Google Spreadsheet.</div>';

    $body = '
    '.$statusMsg.'
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h2 class="fw-bold mb-1 text-dark"><i class="bi bi-check2-all text-success me-2"></i>Import Selesai!</h2>
        <div class="text-secondary"><strong>'.$totalData.' data komputer</strong> berhasil ditambahkan ke Spreadsheet + QR Token otomatis di-generate.</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-primary fw-bold" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-speedometer2 me-1"></i> Ke Dashboard</a>
        <a class="btn btn-outline-secondary" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-qr-code me-1"></i> Lihat QR Aset</a>
        <a class="btn btn-outline-secondary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cabangKpoId])).'"><i class="bi bi-printer me-1"></i> Cetak Semua Stiker QR</a>
      </div>
    </div>

    <div class="card p-4 border-0 shadow-sm">
      <h5 class="fw-bold mb-3"><i class="bi bi-list-check text-primary me-2"></i>Data yang Telah Di-Import:</h5>
      <div class="table-responsive rounded-3 border" style="max-height: 500px; overflow-y: auto;">
        <table class="table table-hover align-middle mb-0">
          <thead class="sticky-top"><tr><th class="text-center" style="width:50px">No</th><th>Kode Inventaris</th><th>Perangkat</th><th>Pemilik</th><th>Status</th></tr></thead>
          <tbody>'.$tableHtml.'</tbody>
        </table>
      </div>
    </div>';

    render_page('Hasil Import KPO', $body);
    exit;
}

// ====== TAMPILAN PREVIEW (GET) ======
$previewHtml = '';
foreach ($rawData as $idx => $row) {
    $no = $idx + 1;
    $kode = $row['kode'] ?: '<span class="text-muted fst-italic">Auto</span>';
    $previewHtml .= '<tr>
        <td class="text-center">'.$no.'</td>
        <td class="small fw-bold">'.$kode.'</td>
        <td class="fw-semibold text-dark">'.e($row['merk'] . ' ' . $row['model']).'</td>
        <td class="small">'.e($row['sn']).'</td>
        <td>'.e($row['pemilik']).'</td>
        <td class="small">'.e($row['lokasi']).'</td>
        <td class="small">'.e($row['jabatan']).'</td>
    </tr>';
}

$body = '
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1 text-dark"><i class="bi bi-cloud-upload text-primary me-2"></i>Import Data Komputer KPO</h2>
    <div class="text-secondary">Preview <strong>'.$totalData.' data komputer</strong> yang akan langsung ditulis batch ke Google Spreadsheet. Cabang: <span class="badge-chip chip-primary">KPO</span></div>
  </div>
  <a class="btn btn-outline-secondary" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left me-1"></i> Batal</a>
</div>

<div class="card p-4 border-0 shadow-sm mb-4">
  <div class="table-responsive rounded-3 border" style="max-height: 500px; overflow-y: auto;">
    <table class="table table-hover align-middle mb-0 small">
      <thead class="sticky-top"><tr><th class="text-center" style="width:40px">No</th><th>Kode Inventaris</th><th>Perangkat</th><th>Serial Number</th><th>Pemilik</th><th>Lokasi</th><th>Jabatan</th></tr></thead>
      <tbody>'.$previewHtml.'</tbody>
    </table>
  </div>
</div>

<div class="card p-4 border-0 shadow-sm">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h5 class="fw-bold text-dark mb-1"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Siap Import?</h5>
      <div class="text-secondary small">Semua '.$totalData.' data akan langsung ditulis <strong>batch 1x</strong> ke Spreadsheet (Aset + Karyawan + QR Token).</div>
    </div>
    <form method="post">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="import">
      <button type="submit" class="btn btn-success btn-lg fw-bold px-5 py-3" onclick="this.disabled=true; this.innerHTML=\'<i class=\\\'bi bi-hourglass-split me-2\\\'></i> Sedang Menulis ke Spreadsheet...\'; this.form.submit();">
        <i class="bi bi-cloud-arrow-up-fill me-2"></i> Import '.$totalData.' Data Sekarang
      </button>
    </form>
  </div>
</div>';

render_page('Import Data Komputer KPO', $body);
