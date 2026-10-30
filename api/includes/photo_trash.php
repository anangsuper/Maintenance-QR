<?php
/**
 * Modul Manajemen 2 Foto (Dual Photo) & Folder Sampah Foto (Retensi 7 Hari + Restore)
 * Mendukung Kartu Inventaris (inventaris_kartu) dan Perangkat IT (assets)
 */

/**
 * Pastikan kolom foto_2 tersedia di tabel inventaris_kartu dan assets,
 * serta tabel photo_trash siap digunakan.
 */
function ensure_photo_trash_and_dual_photo_schema(): void {
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;

    if (function_exists('is_google_cloud_mode') && is_google_cloud_mode()) {
        return;
    }

    try {
        $pdo = db();

        // 1. Pastikan kolom foto_2 ada di inventaris_kartu
        try {
            $st1 = $pdo->query("SHOW COLUMNS FROM inventaris_kartu LIKE 'foto_2'");
            if (!$st1 || !$st1->fetch()) {
                $pdo->exec("ALTER TABLE inventaris_kartu ADD COLUMN foto_2 TEXT NULL DEFAULT NULL AFTER foto");
            }
        } catch (Throwable $e) {}

        // 2. Pastikan kolom foto_2 ada di assets
        try {
            $st2 = $pdo->query("SHOW COLUMNS FROM assets LIKE 'foto_2'");
            if (!$st2 || !$st2->fetch()) {
                $pdo->exec("ALTER TABLE assets ADD COLUMN foto_2 TEXT NULL DEFAULT NULL AFTER foto");
            }
        } catch (Throwable $e) {}

        // 3. Buat tabel photo_trash jika belum ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS photo_trash (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_type VARCHAR(40) NOT NULL DEFAULT 'inventaris_kartu',
                item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                photo_slot TINYINT UNSIGNED NOT NULL DEFAULT 1,
                file_path TEXT NOT NULL,
                kode_inventaris VARCHAR(120) NULL DEFAULT NULL,
                nomor_seri VARCHAR(120) NULL DEFAULT NULL,
                nama_barang VARCHAR(255) NOT NULL DEFAULT '-',
                kategori_jenis VARCHAR(150) NULL DEFAULT NULL,
                cabang_lokasi VARCHAR(255) NULL DEFAULT NULL,
                pengguna_divisi VARCHAR(255) NULL DEFAULT NULL,
                keterangan_detail TEXT NULL DEFAULT NULL,
                deleted_by VARCHAR(180) NULL DEFAULT NULL,
                deleted_reason VARCHAR(255) NULL DEFAULT NULL,
                deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                restored_at DATETIME NULL DEFAULT NULL,
                INDEX idx_trash_expires (expires_at),
                INDEX idx_trash_source_item (source_type, item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {
        error_log("Gagal menyiapkan skema photo_trash: " . $e->getMessage());
    }
}

/**
 * Cek apakah sebuah file_path masih dipakai aktif di inventaris_kartu, assets, atau item photo_trash lain
 */
function is_photo_file_still_referenced(string $filePath, int $excludeTrashId = 0): bool {
    $filePath = trim($filePath);
    if ($filePath === '' || str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
        return true;
    }

    try {
        $pdo = db();

        $st1 = $pdo->prepare("SELECT COUNT(*) FROM inventaris_kartu WHERE foto = ? OR foto_2 = ?");
        $st1->execute([$filePath, $filePath]);
        if ((int)$st1->fetchColumn() > 0) return true;

        $st2 = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE foto = ? OR foto_2 = ?");
        $st2->execute([$filePath, $filePath]);
        if ((int)$st2->fetchColumn() > 0) return true;

        if ($excludeTrashId > 0) {
            $st3 = $pdo->prepare("SELECT COUNT(*) FROM photo_trash WHERE file_path = ? AND id != ? AND restored_at IS NULL");
            $st3->execute([$filePath, $excludeTrashId]);
        } else {
            $st3 = $pdo->prepare("SELECT COUNT(*) FROM photo_trash WHERE file_path = ? AND restored_at IS NULL");
            $st3->execute([$filePath]);
        }
        if ((int)$st3->fetchColumn() > 0) return true;

        return false;
    } catch (Throwable $e) {
        return true; // Aman: jangan hapus file fisik bila query gagal
    }
}

/**
 * Hapus file fisik foto dari disk jika sudah tidak direferensikan di tabel mana pun
 */
function unlink_unreferenced_photo_file(string $filePath, int $excludeTrashId = 0): bool {
    $filePath = trim($filePath);
    if ($filePath === '' || str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
        return false;
    }
    if (is_photo_file_still_referenced($filePath, $excludeTrashId)) {
        return false;
    }

    $cleanRel = ltrim(str_replace('\\', '/', $filePath), '/');
    // Pastikan hanya menghapus di dalam uploads/assets/
    if (!str_starts_with($cleanRel, 'uploads/')) {
        return false;
    }

    $absPath = dirname(__DIR__) . '/' . $cleanRel;
    if (file_exists($absPath) && is_file($absPath)) {
        return @unlink($absPath);
    }
    return false;
}

/**
 * Bersihkan otomatis foto di Folder Sampah yang sudah melewati batas 7 hari (expires_at <= NOW())
 */
function purge_expired_photo_trash(): int {
    static $purgedThisRequest = false;
    if ($purgedThisRequest) return 0;
    $purgedThisRequest = true;

    ensure_photo_trash_and_dual_photo_schema();

    try {
        $pdo = db();
        $now = date('Y-m-d H:i:s');

        $st = $pdo->prepare("SELECT * FROM photo_trash WHERE restored_at IS NULL AND expires_at <= ?");
        $st->execute([$now]);
        $expiredRows = $st->fetchAll();

        $purgedCount = 0;
        foreach ($expiredRows as $row) {
            $tid = (int)($row['id'] ?? 0);
            $fPath = trim((string)($row['file_path'] ?? ''));

            // Hapus baris dari photo_trash terlebih dahulu, lalu cek apakah file fisik masih dipakai
            $del = $pdo->prepare("DELETE FROM photo_trash WHERE id = ?");
            $del->execute([$tid]);

            if ($fPath !== '') {
                unlink_unreferenced_photo_file($fPath, $tid);
            }
            $purgedCount++;
        }

        // Bersihkan juga log yang sudah di-restore lebih dari 7 hari lalu
        $pdo->prepare("DELETE FROM photo_trash WHERE restored_at IS NOT NULL AND restored_at <= DATE_SUB(?, INTERVAL 7 DAY)")
            ->execute([$now]);

        return $purgedCount;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Pindahkan foto yang dihapus / diganti ke Folder Sampah (Tersimpan 7 Hari sebelum hilang permanen)
 */
function move_photo_to_trash(
    string $sourceType,
    int $itemId,
    int $photoSlot,
    ?string $filePath,
    string $deletedBy = '',
    string $deletedReason = 'Dihapus oleh petugas'
): ?int {
    $filePath = trim((string)$filePath);
    if ($filePath === '') return null;

    ensure_photo_trash_and_dual_photo_schema();
    purge_expired_photo_trash();

    $photoSlot = ($photoSlot === 2) ? 2 : 1;
    $sourceType = ($sourceType === 'assets') ? 'assets' : 'inventaris_kartu';
    if ($deletedBy === '') {
        $deletedBy = function_exists('is_logged_in') && is_logged_in()
            ? (current_user_name() ?: 'Admin / Petugas')
            : 'Petugas / Teknisi';
    }

    $kodeInv = '-';
    $nomorSeri = '-';
    $namaBarang = '-';
    $kategoriJenis = '-';
    $cabangLokasi = '-';
    $penggunaDivisi = '-';
    $ketDetail = '';

    try {
        if ($sourceType === 'inventaris_kartu') {
            $card = function_exists('get_inventaris_kartu_by_id') ? get_inventaris_kartu_by_id($itemId) : null;
            if ($card) {
                $rek = (string)($card['nomor_rekening'] ?? '');
                $kodeInv = function_exists('format_kode_inventaris') ? format_kode_inventaris($rek) : $rek;
                $nomorSeri = !empty($card['nomor_seri_inventaris'])
                    ? (string)$card['nomor_seri_inventaris']
                    : (function_exists('generate_default_nomor_seri') ? generate_default_nomor_seri($card) : '-');
                $namaBarang = trim((string)($card['nama_barang'] ?? 'Barang Inventaris #' . $itemId));
                $jenis = trim((string)($card['jenis_inventaris'] ?? '3 : PERALATAN KANTOR'));
                $gol = trim((string)($card['golongan'] ?? '02 : GOLONGAN I'));
                $kategoriJenis = $jenis . ($gol !== '' ? ' · ' . $gol : '');

                $cBranch = function_exists('get_cabang_from_nomor_rekening') ? get_cabang_from_nomor_rekening($kodeInv) : null;
                $branchName = $cBranch ? ($cBranch['lokasi'] ?? $cBranch['name'] ?? 'Kantor Pusat') : 'Kantor Pusat (KPO)';
                $ruang = trim((string)($card['ruangan'] ?? $card['lokasi'] ?? ''));
                $cabangLokasi = $branchName . ($ruang !== '' && $ruang !== $branchName ? ' — ' . $ruang : '');

                $pengguna = trim((string)($card['pengguna'] ?? 'Umum / Operasional'));
                $sumber = trim((string)($card['sumber_perolehan'] ?? ''));
                $penggunaDivisi = $pengguna . ($sumber !== '' ? ' (' . $sumber . ')' : '');

                $tgl = function_exists('format_indo_date') ? format_indo_date((string)($card['tanggal_perolehan'] ?? '')) : (string)($card['tanggal_perolehan'] ?? '-');
                $statusInv = trim((string)($card['status_inventaris'] ?? '1 : Aktif'));
                $ket = trim((string)($card['keterangan'] ?? ''));
                $ketDetail = "Tgl Perolehan: {$tgl} | Status: {$statusInv}" . ($ket !== '' ? " | Ket: {$ket}" : '');
            }
        } else {
            $asset = function_exists('get_asset_by_id') ? get_asset_by_id($itemId) : null;
            if ($asset) {
                $kodeInv = trim((string)($asset['kode_inventaris'] ?? ('INV-IT-' . sprintf('%03d', $itemId))));
                $nomorSeri = trim((string)($asset['serial_number'] ?? '-')) ?: '-';
                $namaBarang = function_exists('asset_title') ? asset_title($asset) : trim(($asset['merk'] ?? '') . ' ' . ($asset['model'] ?? ''));
                $kategoriJenis = trim((string)($asset['kategori_nama'] ?? 'Perangkat IT')) ?: 'Perangkat IT';

                $cab = trim((string)($asset['cabang_nama'] ?? 'Kantor Pusat'));
                $ruang = trim((string)($asset['ruangan'] ?? ''));
                $cabangLokasi = $cab . ($ruang !== '' ? ' — ' . $ruang : '');

                $kar = trim((string)($asset['karyawan_nama'] ?? 'Umum / Pool'));
                $div = trim((string)($asset['divisi_nama'] ?? ''));
                $penggunaDivisi = $kar . ($div !== '' && $div !== '-' ? " ({$div})" : '');

                $ip = trim((string)($asset['ip_address'] ?? $asset['ip'] ?? '-'));
                $spec = trim((string)($asset['keterangan'] ?? ''));
                $ketDetail = "IP: {$ip}" . ($spec !== '' ? " | Spesifikasi: {$spec}" : '');
            }
        }

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        $st = db()->prepare("
            INSERT INTO photo_trash (
                source_type, item_id, photo_slot, file_path,
                kode_inventaris, nomor_seri, nama_barang, kategori_jenis,
                cabang_lokasi, pengguna_divisi, keterangan_detail,
                deleted_by, deleted_reason, deleted_at, expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $sourceType, $itemId, $photoSlot, $filePath,
            $kodeInv, $nomorSeri, $namaBarang, $kategoriJenis,
            $cabangLokasi, $penggunaDivisi, $ketDetail,
            $deletedBy, $deletedReason, $now, $expiresAt
        ]);
        $trashId = (int)db()->lastInsertId();

        if (function_exists('record_audit_log')) {
            try {
                record_audit_log(
                    'DELETE',
                    $sourceType === 'assets' ? 'FOTO_ASET' : 'FOTO_KARTU',
                    $itemId,
                    "{$kodeInv} - {$namaBarang}",
                    "Foto Ke-{$photoSlot} dipindahkan ke Folder Sampah (tersimpan 7 hari s/d " . date('d/m/Y H:i', strtotime($expiresAt)) . ") oleh {$deletedBy}. Alasan: {$deletedReason}"
                );
            } catch (Throwable $e) {}
        }

        return $trashId;
    } catch (Throwable $e) {
        error_log("Gagal memindahkan foto ke photo_trash: " . $e->getMessage());
        return null;
    }
}

/**
 * Ambil daftar foto di Folder Sampah yang masih aktif (belum di-restore dan belum lewat 7 hari)
 */
function get_photo_trash_list(array $filters = []): array {
    ensure_photo_trash_and_dual_photo_schema();
    purge_expired_photo_trash();

    try {
        $where = ["restored_at IS NULL", "expires_at > NOW()"];
        $params = [];

        if (!empty($filters['source_type']) && in_array($filters['source_type'], ['inventaris_kartu', 'assets'], true)) {
            $where[] = "source_type = ?";
            $params[] = $filters['source_type'];
        }
        if (!empty($filters['item_id'])) {
            $where[] = "item_id = ?";
            $params[] = (int)$filters['item_id'];
        }
        if (!empty($filters['search'])) {
            $q = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(nama_barang LIKE ? OR kode_inventaris LIKE ? OR nomor_seri LIKE ? OR cabang_lokasi LIKE ? OR pengguna_divisi LIKE ? OR deleted_by LIKE ?)";
            array_push($params, $q, $q, $q, $q, $q, $q);
        }

        $sql = "SELECT * FROM photo_trash WHERE " . implode(' AND ', $where) . " ORDER BY deleted_at DESC, id DESC";
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        $nowTs = time();
        foreach ($rows as &$r) {
            $expTs = strtotime((string)$r['expires_at']);
            $diffSec = max(0, $expTs - $nowTs);
            $daysLeft = (int)floor($diffSec / 86400);
            $hoursLeft = (int)floor(($diffSec % 86400) / 3600);
            $r['days_left'] = $daysLeft;
            $r['hours_left'] = $hoursLeft;
            $r['seconds_left'] = $diffSec;
            if ($daysLeft > 0) {
                $r['countdown_label'] = "{$daysLeft} hari {$hoursLeft} jam lagi";
            } elseif ($hoursLeft > 0) {
                $r['countdown_label'] = "{$hoursLeft} jam lagi";
            } else {
                $minsLeft = max(1, (int)ceil($diffSec / 60));
                $r['countdown_label'] = "{$minsLeft} menit lagi";
            }

            // Resolusi URL Foto Fisik (Kompatibel dengan semua hosting & subdirektori)
            $fPath = trim((string)($r['file_path'] ?? ''));
            if ($fPath !== '') {
                if (str_starts_with($fPath, 'http://') || str_starts_with($fPath, 'https://') || str_starts_with($fPath, '//')) {
                    $r['photo_url'] = $fPath;
                } elseif (function_exists('asset_image_url')) {
                    $r['photo_url'] = asset_image_url($fPath);
                } else {
                    $r['photo_url'] = module_url(ltrim($fPath, '/'));
                }
            } else {
                $r['photo_url'] = '';
            }

            $slotNum = (int)($r['photo_slot'] ?? 1);
            $r['slot_label'] = ($slotNum === 2) ? 'Foto Ke-2 (Detail / S/N)' : 'Foto Ke-1 (Utama / Unit)';
        }
        unset($r);

        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Hitung jumlah foto aktif di Folder Sampah (untuk badge menu sidebar & tombol)
 */
function get_photo_trash_count(?string $sourceType = null, int $itemId = 0): int {
    ensure_photo_trash_and_dual_photo_schema();
    purge_expired_photo_trash();

    try {
        $where = ["restored_at IS NULL", "expires_at > NOW()"];
        $params = [];
        if ($sourceType !== null && in_array($sourceType, ['inventaris_kartu', 'assets'], true)) {
            $where[] = "source_type = ?";
            $params[] = $sourceType;
        }
        if ($itemId > 0) {
            $where[] = "item_id = ?";
            $params[] = $itemId;
        }
        $st = db()->prepare("SELECT COUNT(*) FROM photo_trash WHERE " . implode(' AND ', $where));
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Pulihkan (Restore) foto dari Folder Sampah kembali ke Kartu Inventaris atau Aset IT
 */
function restore_photo_from_trash(int $trashId, ?string $restoredBy = null): array {
    if ($trashId <= 0) {
        return ['success' => false, 'error' => 'ID sampah foto tidak valid.'];
    }
    ensure_photo_trash_and_dual_photo_schema();

    try {
        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM photo_trash WHERE id = ? AND restored_at IS NULL LIMIT 1");
        $st->execute([$trashId]);
        $trash = $st->fetch();

        if (!$trash) {
            return ['success' => false, 'error' => 'Data foto di Folder Sampah tidak ditemukan atau sudah dipulihkan.'];
        }

        if (strtotime((string)$trash['expires_at']) <= time()) {
            purge_expired_photo_trash();
            return ['success' => false, 'error' => 'Maaf, masa simpan 7 hari untuk foto ini telah berakhir.'];
        }

        $sourceType = (string)$trash['source_type'];
        $itemId = (int)$trash['item_id'];
        $preferredSlot = ((int)$trash['photo_slot'] === 2) ? 2 : 1;
        $filePath = trim((string)$trash['file_path']);
        $actor = $restoredBy ?: (function_exists('is_logged_in') && is_logged_in() ? (current_user_name() ?: 'Admin') : 'Petugas IT');
        $now = date('Y-m-d H:i:s');

        $targetSlot = $preferredSlot;

        if ($sourceType === 'inventaris_kartu') {
            $card = get_inventaris_kartu_by_id($itemId);
            if (!$card) {
                return ['success' => false, 'error' => 'Data Kartu Inventaris asal (#' . $itemId . ') sudah tidak ditemukan.'];
            }
            $curFoto1 = trim((string)($card['foto'] ?? ''));
            $curFoto2 = trim((string)($card['foto_2'] ?? ''));

            if ($preferredSlot === 1) {
                if ($curFoto1 !== '' && $curFoto2 === '') {
                    // Jika slot 1 sudah diisi foto baru dan slot 2 kosong, isi ke slot 2 agar tidak menimpa
                    $targetSlot = 2;
                } elseif ($curFoto1 !== '' && $curFoto1 !== $filePath) {
                    // Pindahkan foto yang sedang menempati slot 1 ke sampah agar aman
                    move_photo_to_trash('inventaris_kartu', $itemId, 1, $curFoto1, $actor, 'Digantikan saat restore foto sebelumnya');
                }
            } else {
                if ($curFoto2 !== '' && $curFoto1 === '') {
                    $targetSlot = 1;
                } elseif ($curFoto2 !== '' && $curFoto2 !== $filePath) {
                    move_photo_to_trash('inventaris_kartu', $itemId, 2, $curFoto2, $actor, 'Digantikan saat restore foto sebelumnya');
                }
            }

            $colName = ($targetSlot === 2) ? 'foto_2' : 'foto';
            $up = $pdo->prepare("UPDATE inventaris_kartu SET `{$colName}` = ?, foto_updated_by = ?, foto_updated_at = ? WHERE id = ?");
            $up->execute([$filePath, $actor . ' (Restore Sampah)', $now, $itemId]);
        } else {
            $asset = get_asset_by_id($itemId);
            if (!$asset) {
                return ['success' => false, 'error' => 'Data Perangkat IT asal (#' . $itemId . ') sudah tidak ditemukan.'];
            }
            $curFoto1 = trim((string)($asset['foto'] ?? ''));
            $curFoto2 = trim((string)($asset['foto_2'] ?? ''));

            if ($preferredSlot === 1) {
                if ($curFoto1 !== '' && $curFoto2 === '') {
                    $targetSlot = 2;
                } elseif ($curFoto1 !== '' && $curFoto1 !== $filePath) {
                    move_photo_to_trash('assets', $itemId, 1, $curFoto1, $actor, 'Digantikan saat restore foto sebelumnya');
                }
            } else {
                if ($curFoto2 !== '' && $curFoto1 === '') {
                    $targetSlot = 1;
                } elseif ($curFoto2 !== '' && $curFoto2 !== $filePath) {
                    move_photo_to_trash('assets', $itemId, 2, $curFoto2, $actor, 'Digantikan saat restore foto sebelumnya');
                }
            }

            $colName = ($targetSlot === 2) ? 'foto_2' : 'foto';
            $up = $pdo->prepare("UPDATE assets SET `{$colName}` = ?, foto_updated_by = ?, foto_updated_at = ? WHERE id = ?");
            $up->execute([$filePath, $actor . ' (Restore Sampah)', $now, $itemId]);
        }

        // Tandai sudah di-restore (atau hapus dari antrean sampah aktif)
        $pdo->prepare("UPDATE photo_trash SET restored_at = ? WHERE id = ?")->execute([$now, $trashId]);

        if (function_exists('record_audit_log')) {
            try {
                record_audit_log(
                    'UPDATE',
                    $sourceType === 'assets' ? 'FOTO_ASET' : 'FOTO_KARTU',
                    $itemId,
                    (string)($trash['kode_inventaris'] . ' - ' . $trash['nama_barang']),
                    "Foto berhasil di-restore dari Folder Sampah ke Slot Foto Ke-{$targetSlot} oleh {$actor}"
                );
            } catch (Throwable $e) {}
        }

        return [
            'success' => true,
            'source_type' => $sourceType,
            'item_id' => $itemId,
            'restored_slot' => $targetSlot,
            'nama_barang' => (string)$trash['nama_barang'],
            'kode_inventaris' => (string)$trash['kode_inventaris']
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Gagal memulihkan foto: ' . $e->getMessage()];
    }
}

/**
 * Hapus permanen 1 foto dari Folder Sampah sebelum 7 hari
 */
function permanently_delete_photo_from_trash(int $trashId, ?string $deletedBy = null): array {
    if ($trashId <= 0) {
        return ['success' => false, 'error' => 'ID sampah foto tidak valid.'];
    }
    ensure_photo_trash_and_dual_photo_schema();

    try {
        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM photo_trash WHERE id = ? LIMIT 1");
        $st->execute([$trashId]);
        $trash = $st->fetch();
        if (!$trash) {
            return ['success' => false, 'error' => 'Data sampah foto sudah tidak ada.'];
        }

        $fPath = trim((string)($trash['file_path'] ?? ''));
        $pdo->prepare("DELETE FROM photo_trash WHERE id = ?")->execute([$trashId]);

        if ($fPath !== '') {
            unlink_unreferenced_photo_file($fPath, $trashId);
        }

        $actor = $deletedBy ?: (function_exists('is_logged_in') && is_logged_in() ? (current_user_name() ?: 'Admin') : 'Petugas IT');
        if (function_exists('record_audit_log')) {
            try {
                record_audit_log(
                    'DELETE',
                    'FOLDER_SAMPAH_FOTO',
                    (int)$trash['item_id'],
                    (string)($trash['kode_inventaris'] . ' - ' . $trash['nama_barang']),
                    "Foto Ke-{$trash['photo_slot']} dihapus permanen dari Folder Sampah oleh {$actor}"
                );
            } catch (Throwable $e) {}
        }

        return ['success' => true, 'nama_barang' => (string)$trash['nama_barang']];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

