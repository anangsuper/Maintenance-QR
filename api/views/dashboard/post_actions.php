<?php
// =========================================================================
// 1. TANGANI AKSI POST KARTU INVENTARIS CR80
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // A. Tambah Data Kartu Baru
    if ($action === 'create_card' || $action === 'create') {
        $ruanganVal = trim((string)($_POST['ruangan'] ?? ''));
        $ruanganBaru = trim((string)($_POST['nama_ruangan_baru'] ?? ''));
        if ($ruanganBaru !== '') {
            $ruanganVal = add_master_ruangan($ruanganBaru, trim((string)($_POST['kode_ruangan_baru'] ?? '')) ?: null);
        }

        $res = insert_inventaris_kartu([
            'nomor_rekening'        => $_POST['nomor_rekening'] ?? '',
            'nama_barang'           => $_POST['nama_barang'] ?? '',
            'nomor_seri_inventaris' => $_POST['nomor_seri_inventaris'] ?? '',
            'jenis_inventaris'      => $_POST['jenis_inventaris'] ?? '',
            'golongan'              => $_POST['golongan'] ?? '',
            'ruangan'               => $ruanganVal,
            'sumber_perolehan'      => $_POST['sumber_perolehan'] ?? '',
            'status_inventaris'     => $_POST['status_inventaris'] ?? '1 : Aktif',
            'keterangan'            => $_POST['keterangan'] ?? '',
            'tanggal_perolehan'     => $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
            'barcode_data'          => $_POST['barcode_data'] ?? '',
            'lokasi'                => $_POST['lokasi'] ?? ''
        ]);
        if (!empty($res['success'])) {
            $_SESSION['flash'] = 'Data kartu inventaris baru berhasil disimpan.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menyimpan kartu: ' . ($res['error'] ?? 'Terjadi kesalahan.');
        }
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }

    // B. Edit Data Kartu
    if ($action === 'update_card' || $action === 'update') {
        $cardId = (int)($_POST['id'] ?? 0);
        $ruanganVal = trim((string)($_POST['ruangan'] ?? ''));
        $ruanganBaru = trim((string)($_POST['nama_ruangan_baru'] ?? ''));
        if ($ruanganBaru !== '') {
            $ruanganVal = add_master_ruangan($ruanganBaru, trim((string)($_POST['kode_ruangan_baru'] ?? '')) ?: null);
        }

        $ok = update_inventaris_kartu($cardId, [
            'nomor_rekening'        => $_POST['nomor_rekening'] ?? '',
            'nama_barang'           => $_POST['nama_barang'] ?? '',
            'nomor_seri_inventaris' => $_POST['nomor_seri_inventaris'] ?? '',
            'jenis_inventaris'      => $_POST['jenis_inventaris'] ?? '',
            'golongan'              => $_POST['golongan'] ?? '',
            'ruangan'               => $ruanganVal,
            'sumber_perolehan'      => $_POST['sumber_perolehan'] ?? '',
            'status_inventaris'     => $_POST['status_inventaris'] ?? '1 : Aktif',
            'keterangan'            => $_POST['keterangan'] ?? '',
            'tanggal_perolehan'     => $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
            'barcode_data'          => $_POST['barcode_data'] ?? '',
            'lokasi'                => $_POST['lokasi'] ?? ''
        ]);
        if ($ok) {
            $_SESSION['flash'] = 'Data kartu inventaris berhasil diperbarui.';
        } else {
            $_SESSION['flash_error'] = 'Gagal memperbarui data kartu.';
        }
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }

    // C. Hapus Data Kartu Satuan
    if ($action === 'delete_card' || $action === 'delete') {
        $cardId = (int)($_POST['id'] ?? 0);
        if ($cardId > 0 && delete_inventaris_kartu($cardId)) {
            $_SESSION['flash'] = 'Kartu inventaris berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus kartu inventaris.';
        }
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }

    // D. Hapus Terpilih (Bulk Delete)
    if ($action === 'delete_batch_card' || $action === 'delete_batch') {
        $rawIds = trim((string)($_POST['ids'] ?? ''));
        $ids = [];
        foreach (explode(',', $rawIds) as $item) {
            $cId = (int)trim($item);
            if ($cId > 0) $ids[] = $cId;
        }
        if (!empty($ids) && delete_inventaris_kartu($ids)) {
            $_SESSION['flash'] = count($ids) . ' kartu inventaris terpilih berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus kartu terpilih.';
        }
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }

    // E. Import dari Aset IT
    if ($action === 'import_from_assets') {
        $selectedAssetIds = $_POST['asset_ids'] ?? [];
        if (is_string($selectedAssetIds)) {
            $selectedAssetIds = explode(',', $selectedAssetIds);
        }
        $importedCount = 0;
        foreach ($selectedAssetIds as $aid) {
            $aid = (int)$aid;
            if ($aid <= 0) continue;
            $asset = get_asset_by_id($aid);
            if ($asset) {
                $token = !empty($asset['qr_token']) ? $asset['qr_token'] : get_static_qr_token($aid);
                $qrUrl = module_url('scan.php', ['t' => $token]);
                $cId = (int)($asset['id_cabang'] ?? $asset['cabang_id'] ?? 0);
                $cabangName = $asset['cabang_nama'] ?? 'KPO';
                $divName = !empty($asset['divisi_nama']) && $asset['divisi_nama'] !== '-' ? $asset['divisi_nama'] : '';
                $lokasi = $divName !== '' ? "{$cabangName} / {$divName}" : $cabangName;
                $pengguna = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : 'Umum / Pool';

                $res = insert_inventaris_kartu([
                    'nomor_rekening'   => $asset['kode_inventaris'] ?? ('INV-IT-' . $aid),
                    'nama_barang'      => asset_title($asset),
                    'tanggal_perolehan'=> $asset['tanggal_perolehan'] ?? $asset['created_at'] ?? date('Y-m-d'),
                    'barcode_data'     => $qrUrl,
                    'lokasi'           => $lokasi
                ]);
                if (!empty($res['success'])) {
                    $importedCount++;
                }
            }
        }
        $_SESSION['flash'] = "{$importedCount} aset IT berhasil diimpor ke tabel Kartu Inventaris.";
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }

    // F. Update Proses Perbaikan dari Tab Temuan
    if ($action === 'save_proses_perbaikan') {
        $pFindingId = (int)($_POST['finding_id'] ?? 0);
        $pScanId = (int)($_POST['scan_id'] ?? 0);
        $pAssetId = (int)($_POST['asset_id'] ?? 0);
        $pStatus = trim((string)($_POST['status'] ?? 'In Progress'));
        $pNotes = trim((string)($_POST['proses_perbaikan'] ?? ''));
        $pTech = trim((string)($_POST['technician_name'] ?? current_user_name()));

        $res = update_finding_progress($pFindingId, $pScanId, $pAssetId, $pStatus, $pNotes, $pTech);
        if (!empty($res['success'])) {
            $_SESSION['flash'] = 'Proses perbaikan perangkat berhasil disimpan.';
        } else {
            $_SESSION['flash_error'] = $res['error'] ?? 'Gagal menyimpan proses perbaikan.';
        }
        header('Location: ' . module_url('dashboard.php', [
            'tab' => 'temuan',
            'bulan' => max(1, min(12, (int)($_POST['bulan'] ?? date('n')))),
            'tahun' => max(2020, min(2100, (int)($_POST['tahun'] ?? date('Y')))),
            'cabang' => max(0, (int)($_POST['cabang'] ?? 0))
        ]));
        exit;
    }
}
