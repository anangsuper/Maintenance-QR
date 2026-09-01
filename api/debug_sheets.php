<?php
/**
 * DIAGNOSA — Cek koneksi Google Sheet dan data QR Token
 * Akses: https://domain-vercel/debug_sheets.php
 */
require __DIR__ . '/bootstrap.php';

$results = [];

// 1. Cek mode
$results[] = is_google_cloud_mode() ? '✅ Mode Google Cloud AKTIF' : '❌ Mode Google Cloud TIDAK aktif';

$client = google_sheets_v4_client();
if (!$client) {
    $results[] = '❌ Client Google Sheets tidak tersedia';
    $html = '<div class="card p-4"><h3>Diagnosa Google Sheet</h3><hr>';
    foreach ($results as $r) $html .= '<div class="mb-2">' . $r . '</div>';
    $html .= '</div>';
    render_page('Diagnosa', '<div class="row justify-content-center"><div class="col-md-10">' . $html . '</div></div>');
    exit;
}

// 2. Cek tab-tab
$tabs = ['Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Assets', 'Asset_QR_Tokens', 'Maintenance_Scan', 'Maintenance_Findings'];
foreach ($tabs as $tab) {
    $data = $client->getSheetData($tab);
    $count = count($data);
    $results[] = ($count > 0)
        ? "✅ Tab <strong>{$tab}</strong> — {$count} baris data"
        : "⚠️ Tab <strong>{$tab}</strong> — KOSONG (0 baris)";
}

// 3. Cek QR Tokens detail
$results[] = '<hr><h5>Detail QR Tokens:</h5>';
$qrRows = $client->getSheetData('Asset_QR_Tokens');
if (empty($qrRows)) {
    $results[] = '❌ Tidak ada data QR Token di sheet';
} else {
    foreach ($qrRows as $q) {
        $token = $q['token'] ?? '(kosong)';
        $assetId = $q['asset_id'] ?? '(kosong)';
        $active = $q['is_active'] ?? '(kosong)';
        $scanUrl = module_url('scan.php', ['t' => $token]);
        $results[] = "🔹 Asset #{$assetId} | Token: <code>{$token}</code> | Active: {$active} | <a href=\"{$scanUrl}\" target=\"_blank\">Test Scan</a>";
    }
}

// 4. Cek Assets mapping
$results[] = '<hr><h5>Assets + QR Mapping:</h5>';
$assets = map_sheets_assets();
if (empty($assets)) {
    $results[] = '❌ Tidak ada aset terbaca';
} else {
    foreach ($assets as $a) {
        $hasQr = !empty($a['qr_token']) ? '✅ QR: <code>' . e($a['qr_token']) . '</code>' : '❌ Belum ada QR';
        $activeLabel = ($a['qr_active'] === 1) ? '(AKTIF)' : '(NONAKTIF)';
        $results[] = "🔹 #{$a['id']} {$a['kode_inventaris']} — {$a['merk']} {$a['model']} | {$hasQr} {$activeLabel}";
    }
}

// 5. Test token lookup
$results[] = '<hr><h5>Test Token Lookup:</h5>';
foreach ($qrRows as $q) {
    $token = $q['token'] ?? '';
    if (!$token) continue;
    $found = get_asset_by_token($token);
    if ($found) {
        $results[] = "✅ Token <code>{$token}</code> → Aset #{$found['id']} {$found['kode_inventaris']}";
    } else {
        $results[] = "❌ Token <code>{$token}</code> → TIDAK DITEMUKAN (ini penyebab error saat scan!)";
    }
}

$html = '<div class="card p-4"><h3>🔍 Diagnosa Google Sheet</h3><hr>';
foreach ($results as $r) $html .= '<div class="mb-2">' . $r . '</div>';
$html .= '<hr><a class="btn btn-primary mt-2" href="' . e(module_url('dashboard.php')) . '">Dashboard</a>';
$html .= ' <a class="btn btn-success mt-2" href="' . e(module_url('setup_sheets.php')) . '">Setup Ulang Sheet</a>';
$html .= '</div>';
render_page('Diagnosa Google Sheet', '<div class="row justify-content-center"><div class="col-md-10">' . $html . '</div></div>');
