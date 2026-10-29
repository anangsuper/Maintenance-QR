<?php
require __DIR__ . '/bootstrap.php';
require_login();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method tidak diizinkan']);
    exit;
}

$inputRaw = file_get_contents('php://input');
$data = json_decode($inputRaw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload JSON tidak valid']);
    exit;
}

$action = trim((string)($data['action'] ?? 'save'));
$cabangId = (int)($data['cabang_id'] ?? 0);

if ($cabangId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID Cabang tidak valid']);
    exit;
}

// =========================================================================
// ACTION: TAMBAH ZONA / RUANGAN BARU
// =========================================================================
if ($action === 'add_zone') {
    $title = trim((string)($data['title'] ?? ''));
    $subtitle = trim((string)($data['subtitle'] ?? ''));
    $color = trim((string)($data['color'] ?? '#0284C7'));
    $icon = trim((string)($data['icon'] ?? 'bi-door-closed'));

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nama ruangan / zona wajib diisi']);
        exit;
    }

    // Tentukan warna latar & border berdasarkan warna aksen
    $colorMap = [
        '#0284C7' => ['bg' => '#F0F9FF', 'border' => '#BAE6FD'], // Biru
        '#D97706' => ['bg' => '#FFFBEB', 'border' => '#FDE68A'], // Oranye
        '#059669' => ['bg' => '#ECFDF5', 'border' => '#A7F3D0'], // Hijau
        '#4F46E5' => ['bg' => '#EEF2FF', 'border' => '#C7D2FE'], // Indigo
        '#9333EA' => ['bg' => '#FAF5FF', 'border' => '#E9D5FF'], // Ungu
        '#DC2626' => ['bg' => '#FEF2F2', 'border' => '#FECACA'], // Merah
        '#64748B' => ['bg' => '#F8FAFC', 'border' => '#E2E8F0'], // Abu-abu
    ];
    $theme = $colorMap[$color] ?? ['bg' => '#F8FAFC', 'border' => '#E2E8F0'];

    // Generate zone_key unik
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $title));
    $slug = trim($slug, '_');
    if ($slug === '') $slug = 'ruang';
    $zoneKey = $slug . '_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 6);

    try {
        $maxOrder = (int)db()->query("SELECT MAX(sort_order) FROM floor_plan_zones WHERE cabang_id = {$cabangId}")->fetchColumn();
        $newOrder = $maxOrder + 1;

        $ins = db()->prepare("
            INSERT INTO floor_plan_zones 
            (cabang_id, zone_key, title, subtitle, icon, color, bg_light, border_color, sort_order, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $ins->execute([
            $cabangId,
            $zoneKey,
            $title,
            $subtitle,
            $icon,
            $color,
            $theme['bg'],
            $theme['border'],
            $newOrder
        ]);
        $newId = (int)db()->lastInsertId();

        record_audit_log('CREATE', 'DENAH_ZONA', $newId, $title, "Menambahkan ruangan/zona baru di cabang #{$cabangId}: {$title}");

        echo json_encode([
            'success' => true,
            'message' => "Ruangan '{$title}' berhasil ditambahkan ke denah!",
            'zone' => [
                'id' => $newId,
                'zone_key' => $zoneKey,
                'title' => $title,
                'subtitle' => $subtitle,
                'icon' => $icon,
                'color' => $color,
                'bg_light' => $theme['bg'],
                'border' => $theme['border']
            ]
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Gagal menambah zona: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// ACTION: EDIT NAMA & TEMA ZONA / RUANGAN
// =========================================================================
if ($action === 'edit_zone') {
    $zoneKey = trim((string)($data['zone_key'] ?? ''));
    $title = trim((string)($data['title'] ?? ''));
    $subtitle = trim((string)($data['subtitle'] ?? ''));
    $color = trim((string)($data['color'] ?? '#0284C7'));
    $icon = trim((string)($data['icon'] ?? 'bi-grid'));

    if ($zoneKey === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Zone key tidak valid']);
        exit;
    }

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nama ruangan / zona wajib diisi']);
        exit;
    }

    $colorMap = [
        '#0284C7' => ['bg' => '#F0F9FF', 'border' => '#BAE6FD'], // Biru
        '#D97706' => ['bg' => '#FFFBEB', 'border' => '#FDE68A'], // Oranye
        '#059669' => ['bg' => '#ECFDF5', 'border' => '#A7F3D0'], // Hijau
        '#4F46E5' => ['bg' => '#EEF2FF', 'border' => '#C7D2FE'], // Indigo
        '#9333EA' => ['bg' => '#FAF5FF', 'border' => '#E9D5FF'], // Ungu
        '#DC2626' => ['bg' => '#FEF2F2', 'border' => '#FECACA'], // Merah
        '#64748B' => ['bg' => '#F8FAFC', 'border' => '#E2E8F0'], // Abu-abu
    ];
    $theme = $colorMap[$color] ?? ['bg' => '#F8FAFC', 'border' => '#E2E8F0'];

    try {
        $stCheck = db()->prepare("SELECT id FROM floor_plan_zones WHERE cabang_id = ? AND zone_key = ?");
        $stCheck->execute([$cabangId, $zoneKey]);
        $row = $stCheck->fetch();

        if ($row) {
            $upd = db()->prepare("
                UPDATE floor_plan_zones 
                SET title = ?, subtitle = ?, icon = ?, color = ?, bg_light = ?, border_color = ?
                WHERE cabang_id = ? AND zone_key = ?
            ");
            $upd->execute([
                $title,
                $subtitle,
                $icon,
                $color,
                $theme['bg'],
                $theme['border'],
                $cabangId,
                $zoneKey
            ]);
        } else {
            // Jika baris belum ada di floor_plan_zones, sisipkan baru
            $maxOrder = (int)db()->query("SELECT MAX(sort_order) FROM floor_plan_zones WHERE cabang_id = {$cabangId}")->fetchColumn();
            $newOrder = $maxOrder + 1;
            $ins = db()->prepare("
                INSERT INTO floor_plan_zones 
                (cabang_id, zone_key, title, subtitle, icon, color, bg_light, border_color, sort_order, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $ins->execute([
                $cabangId,
                $zoneKey,
                $title,
                $subtitle,
                $icon,
                $color,
                $theme['bg'],
                $theme['border'],
                $newOrder
            ]);
        }

        record_audit_log('UPDATE', 'DENAH_ZONA', $cabangId, $zoneKey, "Mengubah nama/detail zona '{$title}' ({$zoneKey}) di cabang #{$cabangId}");

        echo json_encode([
            'success' => true,
            'message' => "Nama ruangan '{$title}' berhasil diperbarui!",
            'zone' => [
                'zone_key' => $zoneKey,
                'title' => $title,
                'subtitle' => $subtitle,
                'icon' => $icon,
                'color' => $color,
                'bg_light' => $theme['bg'],
                'border' => $theme['border']
            ]
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Gagal mengubah zona: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// ACTION: HAPUS ZONA (HANYA BILA KOSONG)
// =========================================================================
if ($action === 'delete_zone') {
    $zoneKey = trim((string)($data['zone_key'] ?? ''));

    if ($zoneKey === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Zone key tidak valid']);
        exit;
    }

    try {
        // 1. Cek apakah ada meja / aset yang secara kustom tersimpan di zona ini
        $cntAdp = (int)db()->query("SELECT COUNT(*) FROM asset_desk_positions WHERE cabang_id = {$cabangId} AND zone_key = " . db()->quote($zoneKey))->fetchColumn();

        if ($cntAdp > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Zona tidak dapat dihapus karena masih berisi {$cntAdp} meja workstation. Silakan geser atau pindahkan meja ke zona lain terlebih dahulu."
            ]);
            exit;
        }

        // 2. Cek apakah ada aset tanpa posisi kustom yang secara default jatuh ke zona ini
        $cDiv = name_column('divisi') ?: 'id';
        $cKar = name_column('karyawan') ?: 'id';
        $unassignedAssets = db()->query("
            SELECT a.id, d.`{$cDiv}` AS divisi_nama, k.`{$cKar}` AS karyawan_nama, a.ruangan
            FROM assets a
            LEFT JOIN divisi d ON d.id = a.id_divisi
            LEFT JOIN karyawan k ON k.id = a.id_karyawan
            LEFT JOIN asset_desk_positions adp ON adp.asset_id = a.id AND adp.cabang_id = a.id_cabang
            WHERE a.id_cabang = {$cabangId} AND adp.asset_id IS NULL
        ")->fetchAll();

        $defaultInZone = 0;
        foreach ($unassignedAssets as $ua) {
            $div = strtolower((string)($ua['divisi_nama'] ?? ''));
            $ruang = strtolower((string)($ua['ruangan'] ?? ''));
            $pem = strtolower((string)($ua['karyawan_nama'] ?? ''));

            $matchedKey = 'other';
            if (str_contains($div, 'it') || str_contains($div, 'mis') || str_contains($ruang, 'server') || str_contains($ruang, 'it')) {
                $matchedKey = 'it_server';
            } elseif (str_contains($div, 'direksi') || str_contains($pem, 'pimpinan') || str_contains($pem, 'kepala cabang') || str_contains($ruang, 'direksi') || str_contains($ruang, 'pimpinan') || str_contains($ruang, 'rapat') || str_contains($pem, 'kadiv') || str_contains($pem, 'meeting')) {
                $matchedKey = 'pimpinan';
            } elseif (str_contains($div, 'kredit') || str_contains($div, 'analis') || str_contains($div, 'collect') || str_contains($div, 'remedial') || str_contains($div, 'bisnis')) {
                $matchedKey = 'kredit';
            } elseif (str_contains($div, 'teller') || str_contains($div, 'kasir') || str_contains($div, 'customer') || str_contains($div, 'cs') || str_contains($div, 'operasional') || str_contains($ruang, 'teller') || str_contains($ruang, 'kasir') || str_contains($ruang, 'cs')) {
                $matchedKey = 'front_office';
            } elseif (str_contains($div, 'akunting') || str_contains($div, 'akuntansi') || str_contains($div, 'kepatuhan') || str_contains($div, 'skai') || str_contains($div, 'icu') || str_contains($div, 'sdm') || str_contains($div, 'umum') || str_contains($div, 'pembukuan')) {
                $matchedKey = 'back_office';
            }

            if ($matchedKey === $zoneKey) {
                $defaultInZone++;
            }
        }

        if ($defaultInZone > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Zona tidak dapat dihapus karena masih terdapat {$defaultInZone} meja bawaan divisi di zona ini. Silakan pindahkan meja ke zona lain terlebih dahulu."
            ]);
            exit;
        }

        // Hapus zona dari tabel floor_plan_zones
        $del = db()->prepare("DELETE FROM floor_plan_zones WHERE cabang_id = ? AND zone_key = ?");
        $del->execute([$cabangId, $zoneKey]);

        record_audit_log('DELETE', 'DENAH_ZONA', $cabangId, $zoneKey, "Menghapus ruangan/zona kosong ({$zoneKey}) di cabang #{$cabangId}");

        echo json_encode([
            'success' => true,
            'message' => 'Zona kosong berhasil dihapus dari denah.'
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Gagal menghapus zona: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// ACTION: RESET URUTAN & TATA LETAK
// =========================================================================
if ($action === 'reset') {
    try {
        $st = db()->prepare("DELETE FROM asset_desk_positions WHERE cabang_id = ?");
        $st->execute([$cabangId]);

        record_audit_log('RESET', 'DENAH', $cabangId, 'Cabang #' . $cabangId, 'Mereset tata letak denah meja kantor ke default');

        echo json_encode([
            'success' => true,
            'message' => 'Tata letak meja berhasil direset ke pengaturan bawaan divisi.'
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// ACTION: SIMPAN POSISI MEJA (DRAG & DROP)
// =========================================================================
$positions = $data['positions'] ?? [];
if (!is_array($positions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Data posisi meja tidak valid']);
    exit;
}

try {
    $db = db();
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO asset_desk_positions (asset_id, cabang_id, zone_key, sort_order)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            cabang_id = VALUES(cabang_id),
            zone_key = VALUES(zone_key),
            sort_order = VALUES(sort_order),
            updated_at = NOW()
    ");

    $savedCount = 0;
    foreach ($positions as $p) {
        $assetId = (int)($p['asset_id'] ?? 0);
        $zoneKey = trim((string)($p['zone_key'] ?? 'other')) ?: 'other';
        $sortOrder = (int)($p['sort_order'] ?? 0);

        if ($assetId > 0) {
            $stmt->execute([$assetId, $cabangId, $zoneKey, $sortOrder]);
            $savedCount++;
        }
    }

    $db->commit();

    record_audit_log('UPDATE', 'DENAH', $cabangId, 'Cabang #' . $cabangId, "Menyimpan posisi drag & drop untuk {$savedCount} meja workstation");

    echo json_encode([
        'success' => true,
        'message' => "Tata letak {$savedCount} meja berhasil disimpan!",
        'saved_count' => $savedCount
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal menyimpan: ' . $e->getMessage()]);
}
