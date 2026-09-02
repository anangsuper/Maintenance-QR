<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Data 44 Komputer KPO untuk di-import
$rawData = [
    ['kode'=>'0150035418012023','model'=>'HP Victus 15-FB0012AX','merk'=>'HP','lokasi'=>'LT.4/KPO/IT','sn'=>'SN5CD235GFRS','pemilik'=>'Arya','jabatan'=>'Kepala Satker MIS & IT'],
    ['kode'=>'02008104112016','model'=>'PC DESKTOP','merk'=>'PC','lokasi'=>'LT.4/KPO/IT','sn'=>'Tidak Ada','pemilik'=>'Hafizh','jabatan'=>'Staff Satker MIS & IT'],
    ['kode'=>'0150018606012021','model'=>'ASUS Vivobook','merk'=>'Asus','lokasi'=>'LT.4/KPO','sn'=>'LBN0CX01L884451','pemilik'=>'Noni','jabatan'=>'Internal Control Unit'],
    ['kode'=>'0150044624012025','model'=>'HP 14S','merk'=>'HP','lokasi'=>'LT.4/KPI/KadivKredit','sn'=>'5CD4256MS3','pemilik'=>'Rifqi','jabatan'=>'Kadiv Kredit'],
    ['kode'=>'011202061223001','model'=>'HP 14s-dq5xxx','merk'=>'HP','lokasi'=>'LT.4/KPO/Hrd','sn'=>'5CD337BKRN','pemilik'=>'Defie','jabatan'=>'Kabag Umum & SDM'],
    ['kode'=>'0150031008082022','model'=>'Lenovo IdeaPad 3','merk'=>'Lenovo','lokasi'=>'LT.4/KPO/SKAI','sn'=>'PF3NZNGN','pemilik'=>'Vina','jabatan'=>'Staff Satuan Kerja Audit Internal'],
    ['kode'=>'0150024603112021','model'=>'HP 14s-cf2xxx','merk'=>'HP','lokasi'=>'LT.4/KPO/SKAI','sn'=>'5CG13356NJ','pemilik'=>'Tri','jabatan'=>'Kepala Satuan Kerja Audit Internal'],
    ['kode'=>'0150010418102018','model'=>'Asus A442UF','merk'=>'Asus','lokasi'=>'KPO/Laptop Meeting','sn'=>'J5N0CV03Y147196','pemilik'=>'Laptop Meeting','jabatan'=>'Staff Satker MIS & IT'],
    ['kode'=>'Tidak Ada','model'=>'Lenovo IdeaPad 3','merk'=>'Lenovo','lokasi'=>'LT.3/KPO/KaCab','sn'=>'5CG1181ZFC','pemilik'=>'Wahyu','jabatan'=>'Kepala Cabang'],
    ['kode'=>'0150030012042022','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/AC','sn'=>'8CC2030GCK','pemilik'=>'Roni','jabatan'=>'Kabag Analis Kredit'],
    ['kode'=>'0150020709072021','model'=>'Acer AIO','merk'=>'Acer','lokasi'=>'LT.3/KPO/AC','sn'=>'DQBG0SN00111402A163000','pemilik'=>'Anwar','jabatan'=>'Analis Kredit'],
    ['kode'=>'0150045370042025','model'=>'Asus Mini PC','merk'=>'Asus','lokasi'=>'LT.3/KPO/AO','sn'=>'Tidak Ada','pemilik'=>'AO1','jabatan'=>'Account Officer'],
    ['kode'=>'0150045430042025','model'=>'Asus Mini PC','merk'=>'Asus','lokasi'=>'LT.3/KPO/AO','sn'=>'Tidak Ada','pemilik'=>'AO2','jabatan'=>'Account Officer'],
    ['kode'=>'015026502122021','model'=>'Lenovo IdeaCentre AIO 3-24ITL6','merk'=>'Lenovo','lokasi'=>'LT.3/KPO/DirUt','sn'=>'MP20W1QQ','pemilik'=>'Anton','jabatan'=>'Direktur Utama'],
    ['kode'=>'015025919112021','model'=>'Dell AIO 5400','merk'=>'Dell','lokasi'=>'LT.3/KPO/DirBis','sn'=>'4QGJG92','pemilik'=>'Didiet','jabatan'=>'Direktur Bisnis'],
    ['kode'=>'010802061223001','model'=>'HP Laptop 14s-dq5xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/DirDivKrd','sn'=>'5CD336MQR4','pemilik'=>'Roy','jabatan'=>'Kadiv Kredit'],
    ['kode'=>'0150031226092022','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/AdmColl','sn'=>'8CC213298B','pemilik'=>'Recha','jabatan'=>'Admin Collection'],
    ['kode'=>'0150030801082022','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/KaBagColl','sn'=>'PF3P3SE9','pemilik'=>'Deni','jabatan'=>'Kabag Collection'],
    ['kode'=>'02002810122007','model'=>'PC DESKTOP','merk'=>'PC','lokasi'=>'LT.3/KPO/KaBagRecov','sn'=>'Tidak Ada','pemilik'=>'Yanoor','jabatan'=>'Kabag Remedial & Recovery'],
    ['kode'=>'02002810122007','model'=>'PC DESKTOP','merk'=>'PC','lokasi'=>'LT.3/KPO/Coll','sn'=>'Tidak Ada','pemilik'=>'Hilman','jabatan'=>'Collection Officer'],
    ['kode'=>'02008104112016','model'=>'PC DESKTOP','merk'=>'PC','lokasi'=>'LT.3/KPO/Coll','sn'=>'Tidak Ada','pemilik'=>'Mahbub','jabatan'=>'Collection Officer'],
    ['kode'=>'0150017504092020','model'=>'Asus VivoBook X409BA','merk'=>'Asus','lokasi'=>'LT.3/KPO/FO','sn'=>'L7N0CV15Y36730B','pemilik'=>'Funding1','jabatan'=>'Funding Officer'],
    ['kode'=>'0150016104022020','model'=>'Asus VivoBook 14','merk'=>'Asus','lokasi'=>'LT.3/KPO/FO','sn'=>'KBN0GR03N553479','pemilik'=>'Funding2','jabatan'=>'Funding Officer'],
    ['kode'=>'010802061223001','model'=>'HP All-in-One 22-dd2xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/KaBagAdmKrd','sn'=>'8CC33514HS','pemilik'=>'Yanti','jabatan'=>'Kabag Admin Kredit'],
    ['kode'=>'0150047417122025','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/Legal','sn'=>'8CC2030GD0','pemilik'=>'Heny','jabatan'=>'Legal'],
    ['kode'=>'0150029812042022','model'=>'Asus Vivo AIO 22','merk'=>'Asus','lokasi'=>'LT.3/KPO/AdmKrd','sn'=>'M5PTCJ00W693215','pemilik'=>'Zada','jabatan'=>'Admin Kredit'],
    ['kode'=>'0150029912042022','model'=>'HP All-in-One 22-df1xxx','merk'=>'HP','lokasi'=>'LT.3/KPO/AdmKrd','sn'=>'8CC2011ZSR','pemilik'=>'Herli','jabatan'=>'Admin Kredit'],
    ['kode'=>'0150009518052018','model'=>'Asus X441NA','merk'=>'Asus','lokasi'=>'LT.3/KPO/Krd','sn'=>'HCN0CV15H64151G','pemilik'=>'Kredit','jabatan'=>'Kredit'],
    ['kode'=>'0150036217022023','model'=>'HP Victus 15','merk'=>'HP','lokasi'=>'LT.2/KPO/Kepatuhan','sn'=>'5CD225N6GR','pemilik'=>'Hasan','jabatan'=>'Direktur Kepatuhan'],
    ['kode'=>'0150019616042021','model'=>'Asus Vivo AIO','merk'=>'Asus','lokasi'=>'LT.2/KPO/Kepatuhan','sn'=>'M1PTCJ00T37704A','pemilik'=>'Febri','jabatan'=>'PE Kepatuhan'],
    ['kode'=>'0150047110122025','model'=>'LENOVO V15','merk'=>'Lenovo','lokasi'=>'LT.2/KPO/Kepatuhan','sn'=>'PF50HRLB','pemilik'=>'Yulika','jabatan'=>'Unit Kerja Khusus APU & PPT'],
    ['kode'=>'0150044524012025','model'=>'HP Laptop 14s-dq5xxx','merk'=>'HP','lokasi'=>'LT.2/KPO/Kepatuhan','sn'=>'5CD433D2SR','pemilik'=>'Novi','jabatan'=>'PE Manajemen Risiko'],
    ['kode'=>'0150045726062025','model'=>'Microsoft Surface Pro 7','merk'=>'Microsoft','lokasi'=>'LT.1/KPO/CS','sn'=>'63683204153','pemilik'=>'CS 1','jabatan'=>'Customer Service'],
    ['kode'=>'0150047731122025','model'=>'Lenovo IdeaPad 3','merk'=>'Lenovo','lokasi'=>'LT.1/KPO/CS','sn'=>'Tidak Ada','pemilik'=>'CS 2','jabatan'=>'Customer Service'],
    ['kode'=>'0150026402122021','model'=>'HP 14 S','merk'=>'HP','lokasi'=>'LT.1/KPO/DirOPS','sn'=>'SCD13542Q2','pemilik'=>'Kadiv Operasional','jabatan'=>'Kadiv Operasional'],
    ['kode'=>'0150035322122022','model'=>'Asus Vivo AIO','merk'=>'Asus','lokasi'=>'LT.1/KPO/KaDivOPS','sn'=>'M1PTCJ00T438046','pemilik'=>'Dir Operasional','jabatan'=>'Dir Operasional'],
    ['kode'=>'0150044705022025','model'=>'Lenovo V15 G3','merk'=>'Lenovo','lokasi'=>'LT.1/KPO/BO','sn'=>'Tidak Ada','pemilik'=>'Marinda','jabatan'=>'Staff Akunting'],
    ['kode'=>'0150036527022023','model'=>'Asus Vivobook 15','merk'=>'Asus','lokasi'=>'LT.1/KPO/BO','sn'=>'NAN0CV02E883417','pemilik'=>'Vidi','jabatan'=>'Kabag Akunting'],
    ['kode'=>'0150046228072025','model'=>'HP Laptop 15-fd0xxx','merk'=>'HP','lokasi'=>'LT.1/KPO/BO','sn'=>'5CD423GW3L','pemilik'=>'Kharissa','jabatan'=>'Supervisor Operasional'],
    ['kode'=>'0150020205052021','model'=>'Asus Vivo AIO','merk'=>'Asus','lokasi'=>'LT.1/KPO/Teller','sn'=>'M3PTCJ00602409A','pemilik'=>'Teller 1','jabatan'=>'Teller'],
    ['kode'=>'0150020105052021','model'=>'Asus Vivo AIO','merk'=>'Asus','lokasi'=>'LT.1/KPO/Teller','sn'=>'KBN0GR01Z40845C','pemilik'=>'Teller 2','jabatan'=>'Teller'],
    ['kode'=>'0150019903052021','model'=>'Microsoft Surface Pro 7','merk'=>'Microsoft','lokasi'=>'LT.1/KPO/KaDivOPS','sn'=>'5CD4256MS3','pemilik'=>'Nana','jabatan'=>'Kadiv Operasional'],
    ['kode'=>'012202090523001','model'=>'ASUS A1502A-VIPS353','merk'=>'Asus','lokasi'=>'LT.2/KPO/DirBis','sn'=>'NANOCVO2E981418','pemilik'=>'Didiet','jabatan'=>'Direktur Bisnis'],
    ['kode'=>'0150020003052021','model'=>'Microsoft Surface Pro 7','merk'=>'Microsoft','lokasi'=>'LT.1/KPO/DirOPS','sn'=>'063683204153','pemilik'=>'Kahar','jabatan'=>'Direktur Bisnis'],
];

// Tentukan kategori berdasarkan model / merk
function detect_kategori(string $model, string $merk): int {
    $m = strtolower($model);
    if (str_contains($m, 'mini pc')) return 5;
    if (str_contains($m, 'all-in-one') || str_contains($m, 'aio') || str_contains($m, 'vivo aio')) return 3;
    if (str_contains($m, 'desktop') || strtolower($merk) === 'pc') return 2;
    if (str_contains($m, 'surface') || str_contains($m, 'laptop') || str_contains($m, 'victus') || str_contains($m, 'vivobook') || str_contains($m, 'ideapad') || str_contains($m, 'thinkpad') || str_contains($m, '14s') || str_contains($m, '14 s') || str_contains($m, 'v15') || str_contains($m, 'x4') || str_contains($m, 'a442') || str_contains($m, 'a1502') || str_contains($m, '15-f') || str_contains($m, '15 ')) return 1;
    return 1; // Default laptop
}

// Normalisasi merk
function clean_merk(string $merk): string {
    $merk = trim($merk);
    $merk = preg_replace('/^Merek\s+/i', '', $merk);
    $merk = trim($merk);
    $map = [
        'hp' => 'HP', 'asus' => 'Asus', 'lenovo' => 'Lenovo', 'acer' => 'Acer', 
        'dell' => 'Dell', 'microsoft' => 'Microsoft', 'pc' => 'PC Custom',
    ];
    return $map[strtolower($merk)] ?? $merk;
}

// Cari atau buat cabang KPO
$cabangs = get_cabang_list();
$cabangKpoId = 0;
foreach ($cabangs as $c) {
    $cn = strtolower(trim($c['nama'] ?? $c['nama_cabang'] ?? ''));
    if (str_contains($cn, 'kpo') || str_contains($cn, 'kantor pusat')) {
        $cabangKpoId = (int)($c['id'] ?? 0);
        break;
    }
}

if ($cabangKpoId === 0) {
    $res = create_new_cabang(['nama_cabang' => 'KPO (Kantor Pusat Operasional)']);
    if (!empty($res['success'])) {
        $cabangKpoId = (int)$res['id'];
    }
}

// Cari atau set divisi default
$divisis = get_divisi_list();
$divisiItId = 0;
foreach ($divisis as $d) {
    $dn = strtolower(trim($d['nama'] ?? $d['nama_divisi'] ?? ''));
    if (str_contains($dn, 'it') || str_contains($dn, 'mis')) {
        $divisiItId = (int)($d['id'] ?? 0);
        break;
    }
}
if ($divisiItId === 0) $divisiItId = 1;

// Proses import
$results = [];
$successCount = 0;
$errorCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    verify_csrf();

    foreach ($rawData as $idx => $row) {
        $no = $idx + 1;
        $kode = $row['kode'];
        if (strtolower($kode) === 'tidak ada') $kode = '';
        $sn = $row['sn'];
        if (strtolower($sn) === 'tidak ada') $sn = '-';

        $merk = clean_merk($row['merk']);
        $model = trim($row['model']);
        $katId = detect_kategori($model, $row['merk']);

        $payload = [
            'kode_inventaris' => $kode,
            'merk' => $merk,
            'model' => $model,
            'serial_number' => $sn,
            'id_kategori' => $katId,
            'id_cabang' => $cabangKpoId,
            'id_divisi' => $divisiItId,
            'nama_karyawan' => $row['pemilik'],
            'placement_label' => 'Bodi Casing',
            'status' => 'Aktif',
            'keterangan' => 'Lokasi: ' . $row['lokasi'] . ' | Jabatan: ' . $row['jabatan'] . ' | Kondisi: Baik',
        ];

        $res = create_new_asset($payload);

        if (!empty($res['success'])) {
            $successCount++;
            $results[] = ['no' => $no, 'status' => 'success', 'kode' => $res['kode_inventaris'], 'model' => $model, 'pemilik' => $row['pemilik'], 'msg' => 'Berhasil'];
        } else {
            $errorCount++;
            $results[] = ['no' => $no, 'status' => 'error', 'kode' => $kode ?: '-', 'model' => $model, 'pemilik' => $row['pemilik'], 'msg' => $res['error'] ?? 'Gagal'];
        }

        // Delay sebentar untuk hindari rate limit Google Sheets API
        if (is_google_cloud_mode() && $no % 5 === 0) {
            usleep(500000); // 0.5 detik setiap 5 data
        }
    }

    // Generate QR untuk semua aset baru
    generate_missing_qr_tokens($cabangKpoId);
}

// --- TAMPILAN ---
$totalData = count($rawData);

if (!empty($results)) {
    // Tampilan Hasil Import
    $tableHtml = '';
    foreach ($results as $r) {
        $badge = $r['status'] === 'success' 
            ? '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> '.$r['msg'].'</span>'
            : '<span class="badge-chip chip-danger"><i class="bi bi-x-circle-fill"></i> '.$r['msg'].'</span>';
        $tableHtml .= '<tr>
            <td class="text-center">'.$r['no'].'</td>
            <td class="fw-bold text-primary">'.e($r['kode']).'</td>
            <td class="fw-semibold">'.e($r['model']).'</td>
            <td>'.e($r['pemilik']).'</td>
            <td>'.$badge.'</td>
        </tr>';
    }

    $body = '
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h2 class="fw-bold mb-1 text-dark"><i class="bi bi-cloud-upload text-success me-2"></i>Hasil Import Data KPO</h2>
        <div class="text-secondary">Import selesai: <strong class="text-success">'.$successCount.' berhasil</strong>, <strong class="text-danger">'.$errorCount.' gagal</strong> dari total '.$totalData.' data.</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-primary fw-bold" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-speedometer2 me-1"></i> Ke Dashboard</a>
        <a class="btn btn-outline-secondary" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-qr-code me-1"></i> Lihat QR Aset</a>
      </div>
    </div>

    <div class="card p-4 border-0 shadow-sm">
      <div class="table-responsive rounded-3 border">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th class="text-center" style="width:50px">No</th><th>Kode Inventaris</th><th>Perangkat</th><th>Pemilik</th><th>Status</th></tr></thead>
          <tbody>'.$tableHtml.'</tbody>
        </table>
      </div>
    </div>';
} else {
    // Tampilan Preview Sebelum Import
    $previewHtml = '';
    foreach ($rawData as $idx => $row) {
        $no = $idx + 1;
        $merk = clean_merk($row['merk']);
        $kode = $row['kode'];
        if (strtolower($kode) === 'tidak ada') $kode = '<span class="text-muted fst-italic">Auto-generate</span>';
        $previewHtml .= '<tr>
            <td class="text-center">'.$no.'</td>
            <td class="small fw-bold">'.$kode.'</td>
            <td class="fw-semibold text-dark">'.e($merk . ' ' . $row['model']).'</td>
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
        <div class="text-secondary">Preview <strong>'.$totalData.' data komputer</strong> yang akan dimasukkan ke sistem. Cabang: <span class="badge-chip chip-primary">KPO</span></div>
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
          <h5 class="fw-bold text-dark mb-1"><i class="bi bi-info-circle text-primary me-2"></i>Konfirmasi Import</h5>
          <div class="text-secondary small">Klik tombol untuk memulai proses import '.$totalData.' data komputer ke dalam sistem QR Maintenance.</div>
        </div>
        <form method="post">
          <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
          <input type="hidden" name="action" value="import">
          <button type="submit" class="btn btn-success btn-lg fw-bold px-5 py-3" onclick="this.disabled=true; this.innerHTML=\'<i class=\\\'bi bi-hourglass-split me-2\\\'></i> Sedang Mengimport...\'; this.form.submit();">
            <i class="bi bi-cloud-arrow-up-fill me-2"></i> Import '.$totalData.' Data Sekarang
          </button>
        </form>
      </div>
    </div>';
}

render_page('Import Data Komputer KPO', $body);
