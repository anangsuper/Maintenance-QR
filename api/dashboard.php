<?php
require __DIR__ . '/bootstrap.php';
require_login();

// 1. Tangani Aksi POST (Tambah/Edit/Hapus/Impor Kartu & Update Perbaikan Temuan)
require __DIR__ . '/views/dashboard/post_actions.php';

// 2. Persiapan Parameter Filter, Statistik Cabang & Ringkasan Kepatuhan
require __DIR__ . '/views/dashboard/data_prep.php';

// 3. Generasi Data & Baris Tabel Kartu Inventaris
require __DIR__ . '/views/dashboard/tab_kartu_data.php';

$head = '<script src="' . e(module_url('assets/vendor/qrcode.min.js')) . '"></script>';

// 4. Header Eksekutif, Kartu Metrik, Widget Sebaran Cabang & Navigasi Tab
require __DIR__ . '/views/dashboard/overview_header.php';

// 5. Konten Tab Aktif (Kartu Inventaris / Temuan Terbuka / Antrean Pemeliharaan)
if ($activeTab === 'kartu') {
    require __DIR__ . '/views/dashboard/tab_kartu.php';
} elseif ($activeTab === 'temuan') {
    require __DIR__ . '/views/dashboard/tab_temuan.php';
} else {
    require __DIR__ . '/views/dashboard/tab_antrean.php';
}

$body .= '
</div><!-- End max-w-[1152px] -->
<div class="h-8 shrink-0" data-kid="1-2-2-2" data-name="bottom spacer div"></div>';

// 6. Modals Terpadu & Logika JavaScript
require __DIR__ . '/views/dashboard/modals.php';
require __DIR__ . '/views/dashboard/scripts.php';

render_page($headerTitle, $body, $head, $extraScript);
