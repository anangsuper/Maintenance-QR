<?php
/**
 * SETUP GOOGLE SHEET — Buat tab & isi data contoh
 * Akses halaman ini sekali saja: https://domain-vercel-anda/setup_sheets.php
 */
require __DIR__ . '/bootstrap.php';

if (!is_google_cloud_mode()) {
    echo "Mode Google Cloud tidak aktif. Pastikan environment variable sudah diset.";
    exit;
}

$client = google_sheets_v4_client();
if (!$client) {
    echo "Client Google Sheets tidak tersedia. Cek GOOGLE_SPREADSHEET_ID, GOOGLE_CLIENT_EMAIL, GOOGLE_PRIVATE_KEY.";
    exit;
}

$results = [];

// ====== 1. Tab Cabang ======
$client->createSheetIfNotExists('Cabang');
$existing = $client->getValues('Cabang!A1:B1');
if (empty($existing)) {
    $client->appendValues('Cabang!A:B', [
        ['id', 'nama_cabang'],
        [1, 'Head Office'],
        [2, 'Cabang Jakarta'],
        [3, 'Cabang Surabaya'],
    ]);
    $results[] = "✅ Tab Cabang — dibuat + data contoh";
} else {
    $results[] = "⏭️ Tab Cabang — sudah ada";
}

// ====== 2. Tab Divisi ======
$client->createSheetIfNotExists('Divisi');
$existing = $client->getValues('Divisi!A1:B1');
if (empty($existing)) {
    $client->appendValues('Divisi!A:B', [
        ['id', 'nama_divisi'],
        [1, 'IT / MIS'],
        [2, 'Operasional'],
        [3, 'Finance'],
    ]);
    $results[] = "✅ Tab Divisi — dibuat + data contoh";
} else {
    $results[] = "⏭️ Tab Divisi — sudah ada";
}

// ====== 3. Tab Karyawan ======
$client->createSheetIfNotExists('Karyawan');
$existing = $client->getValues('Karyawan!A1:D1');
if (empty($existing)) {
    $client->appendValues('Karyawan!A:D', [
        ['id', 'nama_karyawan', 'id_cabang', 'id_divisi'],
        [1, 'Ahmad Staff IT', 1, 1],
        [2, 'Budi Teknisi', 1, 1],
        [3, 'Siti Operasional', 2, 2],
    ]);
    $results[] = "✅ Tab Karyawan — dibuat + data contoh";
} else {
    $results[] = "⏭️ Tab Karyawan — sudah ada";
}

// ====== 4. Tab Kategori_Aset ======
$client->createSheetIfNotExists('Kategori_Aset');
$existing = $client->getValues('Kategori_Aset!A1:B1');
if (empty($existing)) {
    $client->appendValues('Kategori_Aset!A:B', [
        ['id', 'nama_kategori'],
        [1, 'Laptop'],
        [2, 'PC Desktop'],
        [3, 'Printer'],
        [4, 'Monitor'],
    ]);
    $results[] = "✅ Tab Kategori_Aset — dibuat + data contoh";
} else {
    $results[] = "⏭️ Tab Kategori_Aset — sudah ada";
}

// ====== 5. Tab Assets ======
$client->createSheetIfNotExists('Assets');
$existing = $client->getValues('Assets!A1:K1');
if (empty($existing)) {
    $client->appendValues('Assets!A:K', [
        ['id', 'kode_inventaris', 'merk', 'model', 'serial_number', 'id_kategori', 'id_cabang', 'id_divisi', 'id_karyawan', 'status', 'keterangan'],
        [1, 'INV-IT-001', 'Lenovo', 'ThinkPad T14', 'SN-LNV-001', 1, 1, 1, 1, 'Aktif', 'Laptop utama IT'],
        [2, 'INV-IT-002', 'Dell', 'OptiPlex 3080', 'SN-DLL-002', 2, 1, 2, 2, 'Aktif', 'PC Operasional'],
        [3, 'INV-IT-003', 'HP', 'LaserJet Pro', 'SN-HP-003', 3, 1, 1, 1, 'Aktif', 'Printer Lantai 1'],
        [4, 'INV-IT-004', 'Asus', 'VivoBook 14', 'SN-ASS-004', 1, 2, 2, 3, 'Aktif', 'Laptop Cabang Jakarta'],
        [5, 'INV-IT-005', 'Lenovo', 'V14 G3', 'SN-LNV-005', 1, 3, 1, 2, 'Aktif', 'Laptop Cabang Surabaya'],
    ]);
    $results[] = "✅ Tab Assets — dibuat + 5 aset contoh";
} else {
    $results[] = "⏭️ Tab Assets — sudah ada";
}

// ====== 6. Tab Asset_QR_Tokens ======
$client->createSheetIfNotExists('Asset_QR_Tokens');
$existing = $client->getValues('Asset_QR_Tokens!A1:F1');
if (empty($existing)) {
    $client->appendValues('Asset_QR_Tokens!A:F', [
        ['id', 'asset_id', 'token', 'placement_label', 'is_active', 'created_at'],
        [1, 1, 'a1b2c3d4e5f678901234567890abcdef', 'Bodi Atas', 1, date('Y-m-d H:i:s')],
        [2, 2, 'b2c3d4e5f678901234567890abcdef1a', 'Samping CPU', 1, date('Y-m-d H:i:s')],
        [3, 3, 'c3d4e5f678901234567890abcdef1a2b', 'Badan Printer', 1, date('Y-m-d H:i:s')],
        [4, 4, 'd4e5f678901234567890abcdef1a2b3c', 'Meja Kerja', 1, date('Y-m-d H:i:s')],
        [5, 5, 'e5f678901234567890abcdef1a2b3c4d', 'Cover Laptop', 1, date('Y-m-d H:i:s')],
    ]);
    $results[] = "✅ Tab Asset_QR_Tokens — dibuat + 5 QR token";
} else {
    $results[] = "⏭️ Tab Asset_QR_Tokens — sudah ada";
}

// ====== 7. Tab Maintenance_Scan ======
$client->createSheetIfNotExists('Maintenance_Scan');
$existing = $client->getValues('Maintenance_Scan!A1:K1');
if (empty($existing)) {
    $client->appendValues('Maintenance_Scan!A:K', [
        ['id', 'asset_id', 'technician_user_id', 'technician_name', 'maintenance_date', 'maintenance_time', 'maintenance_month', 'maintenance_year', 'status', 'source', 'created_at'],
    ]);
    $results[] = "✅ Tab Maintenance_Scan — dibuat (header only)";
} else {
    $results[] = "⏭️ Tab Maintenance_Scan — sudah ada";
}

// ====== 8. Tab Maintenance_Findings ======
$client->createSheetIfNotExists('Maintenance_Findings');
$existing = $client->getValues('Maintenance_Findings!A1:L1');
if (empty($existing)) {
    $client->appendValues('Maintenance_Findings!A:L', [
        ['id', 'maintenance_scan_id', 'asset_id', 'kategori_temuan', 'deskripsi_temuan', 'tindakan_diperlukan', 'status', 'reported_by', 'reported_at', 'resolved_by', 'resolved_at', 'catatan_penyelesaian'],
    ]);
    $results[] = "✅ Tab Maintenance_Findings — dibuat (header only)";
} else {
    $results[] = "⏭️ Tab Maintenance_Findings — sudah ada";
}

// ====== Tampilkan Hasil ======
$html = '<div class="card p-4"><h3>🔧 Setup Google Sheet — Selesai!</h3><hr>';
foreach ($results as $r) {
    $html .= '<div class="mb-2">' . $r . '</div>';
}
$html .= '<hr><a class="btn btn-primary mt-3" href="' . e(module_url('dashboard.php')) . '">Buka Dashboard</a></div>';

render_page('Setup Google Sheet', '<div class="row justify-content-center"><div class="col-md-8">' . $html . '</div></div>');
