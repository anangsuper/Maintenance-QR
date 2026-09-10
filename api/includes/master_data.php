<?php
declare(strict_types=1);

function get_cabang_list(): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $rows = $client ? $client->getSheetData('Cabang') : [];
        return array_map(function($c) {
            if (isset($c['telepon'])) {
                $c['telepon'] = format_phone_number((string)$c['telepon']);
            }
            return $c;
        }, $rows);
    }
    try {
        $cName = name_column('cabang') ?: 'id';
        $rows = db()->query("SELECT id, `{$cName}` AS nama, `{$cName}` AS nama_cabang FROM cabang ORDER BY `{$cName}`")->fetchAll();
        return array_map(function($c) {
            if (isset($c['telepon'])) {
                $c['telepon'] = format_phone_number((string)$c['telepon']);
            }
            return $c;
        }, $rows);
    } catch (Throwable $e) {
        return [];
    }
}

function get_cabang_by_id(int $id): ?array {
    if ($id <= 0) return null;
    $cabangs = get_cabang_list();
    foreach ($cabangs as $c) {
        if ((int)($c['id'] ?? 0) === $id) return $c;
    }
    return null;
}

function create_new_cabang(array $data): array {
    $nama = trim((string)($data['nama_cabang'] ?? $data['nama'] ?? ''));
    $alamat = trim((string)($data['alamat'] ?? ''));
    $telepon = trim((string)($data['telepon'] ?? ''));
    $penanggungJawab = trim((string)($data['penanggung_jawab'] ?? ''));

    if ($nama === '') {
        return ['success' => false, 'error' => 'Nama cabang wajib diisi'];
    }

    $teleponFormatted = format_phone_number($telepon);
    $teleponStored = ($teleponFormatted !== '-' && $teleponFormatted !== '') ? $teleponFormatted : $telepon;
    $teleponSheet = ($teleponStored !== '' && $teleponStored !== '-') ? "'" . $teleponStored : '-';

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        $rows = $client->getSheetData('Cabang', true);
        $maxId = 0;
        foreach ($rows as $r) {
            $cid = (int)($r['id'] ?? 0);
            if ($cid > $maxId) $maxId = $cid;
            $existName = trim((string)($r['nama_cabang'] ?? $r['nama'] ?? ''));
            if (strcasecmp($existName, $nama) === 0) {
                return ['success' => false, 'error' => 'Nama cabang sudah terdaftar'];
            }
        }

        $newId = max(count($rows) + 1, $maxId + 1);
        $appended = $client->appendValues('Cabang!A:E', [[
            $newId,
            $nama,
            $alamat,
            $teleponSheet,
            $penanggungJawab
        ]]);

        if (!$appended) {
            return ['success' => false, 'error' => 'Gagal menyimpan data cabang ke Google Sheets'];
        }

        $client->clearCache('Cabang');

        return [
            'success' => true,
            'id' => $newId,
            'nama' => $nama
        ];
    }

    // MySQL Mode
    try {
        $cName = name_column('cabang') ?: 'nama_cabang';
        $checkSt = db()->prepare("SELECT id FROM cabang WHERE LOWER(`{$cName}`) = LOWER(?) LIMIT 1");
        $checkSt->execute([$nama]);
        if ($checkSt->fetchColumn()) {
            return ['success' => false, 'error' => 'Nama cabang sudah terdaftar'];
        }

        $ins = db()->prepare("INSERT INTO cabang (`{$cName}`) VALUES (?)");
        $ins->execute([$nama]);
        $newId = (int)db()->lastInsertId();

        return [
            'success' => true,
            'id' => $newId,
            'nama' => $nama
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function update_cabang(int $id, array $data): array {
    if ($id <= 0) return ['success' => false, 'error' => 'ID cabang tidak valid'];
    $nama = trim((string)($data['nama_cabang'] ?? $data['nama'] ?? ''));
    $alamat = trim((string)($data['alamat'] ?? ''));
    $telepon = trim((string)($data['telepon'] ?? ''));
    $penanggungJawab = trim((string)($data['penanggung_jawab'] ?? ''));

    if ($nama === '') {
        return ['success' => false, 'error' => 'Nama cabang wajib diisi'];
    }

    $teleponFormatted = format_phone_number($telepon);
    $teleponStored = ($teleponFormatted !== '-' && $teleponFormatted !== '') ? $teleponFormatted : $telepon;
    $teleponSheet = ($teleponStored !== '' && $teleponStored !== '-') ? "'" . $teleponStored : '-';

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        $rows = $client->getSheetData('Cabang', true);
        $targetRow = null;
        foreach ($rows as $r) {
            if ((int)($r['id'] ?? 0) === $id) {
                $targetRow = $r;
                break;
            }
        }

        if (!$targetRow) {
            return ['success' => false, 'error' => 'Data cabang tidak ditemukan'];
        }

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum <= 1) {
            return ['success' => false, 'error' => 'Gagal menentukan baris data cabang'];
        }

        $updated = $client->updateValues("Cabang!A{$rowNum}:E{$rowNum}", [[
            $id,
            $nama,
            $alamat,
            $teleponSheet,
            $penanggungJawab
        ]]);

        if (!$updated) {
            return ['success' => false, 'error' => 'Gagal memperbarui data di Google Sheets'];
        }

        $client->clearCache('Cabang');
        map_sheets_assets(true);

        return ['success' => true, 'id' => $id, 'nama' => $nama];
    }

    // MySQL Mode
    try {
        $cName = name_column('cabang') ?: 'nama_cabang';
        $up = db()->prepare("UPDATE cabang SET `{$cName}` = ? WHERE id = ?");
        $up->execute([$nama, $id]);
        return ['success' => true, 'id' => $id, 'nama' => $nama];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_divisi_list(): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        return $client ? $client->getSheetData('Divisi') : [];
    }
    try {
        $dName = name_column('divisi') ?: 'id';
        return db()->query("SELECT id, `{$dName}` AS nama, `{$dName}` AS nama_divisi FROM divisi ORDER BY `{$dName}`")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_divisi_by_id(int $id): ?array {
    if ($id <= 0) return null;
    $divisis = get_divisi_list();
    foreach ($divisis as $d) {
        if ((int)($d['id'] ?? 0) === $id) return $d;
    }
    return null;
}

function create_new_divisi(array $data): array {
    $nama = trim((string)($data['nama_divisi'] ?? $data['nama'] ?? ''));
    $keterangan = trim((string)($data['keterangan'] ?? ''));

    if ($nama === '') {
        return ['success' => false, 'error' => 'Nama divisi wajib diisi'];
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        $rows = $client->getSheetData('Divisi', true);
        $maxId = 0;
        foreach ($rows as $r) {
            $did = (int)($r['id'] ?? 0);
            if ($did > $maxId) $maxId = $did;
            $existName = trim((string)($r['nama_divisi'] ?? $r['nama'] ?? ''));
            if (strcasecmp($existName, $nama) === 0) {
                return ['success' => false, 'error' => 'Nama divisi sudah terdaftar'];
            }
        }

        $newId = max(count($rows) + 1, $maxId + 1);
        $appended = $client->appendValues('Divisi!A:C', [[
            $newId,
            $nama,
            $keterangan
        ]]);

        if (!$appended) {
            return ['success' => false, 'error' => 'Gagal menyimpan data divisi ke Google Sheets'];
        }

        $client->clearCache('Divisi');

        return [
            'success' => true,
            'id' => $newId,
            'nama' => $nama
        ];
    }

    // MySQL Mode
    try {
        $dName = name_column('divisi') ?: 'nama_divisi';
        $checkSt = db()->prepare("SELECT id FROM divisi WHERE LOWER(`{$dName}`) = LOWER(?) LIMIT 1");
        $checkSt->execute([$nama]);
        if ($checkSt->fetchColumn()) {
            return ['success' => false, 'error' => 'Nama divisi sudah terdaftar'];
        }

        $cols = table_columns('divisi');
        if (in_array('keterangan', $cols, true)) {
            $ins = db()->prepare("INSERT INTO divisi (`{$dName}`, `keterangan`) VALUES (?, ?)");
            $ins->execute([$nama, $keterangan]);
        } else {
            $ins = db()->prepare("INSERT INTO divisi (`{$dName}`) VALUES (?)");
            $ins->execute([$nama]);
        }
        $newId = (int)db()->lastInsertId();

        return [
            'success' => true,
            'id' => $newId,
            'nama' => $nama
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function update_divisi(int $id, array $data): array {
    if ($id <= 0) return ['success' => false, 'error' => 'ID divisi tidak valid'];
    $nama = trim((string)($data['nama_divisi'] ?? $data['nama'] ?? ''));
    $keterangan = trim((string)($data['keterangan'] ?? ''));

    if ($nama === '') {
        return ['success' => false, 'error' => 'Nama divisi wajib diisi'];
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        $rows = $client->getSheetData('Divisi', true);
        $targetRow = null;
        foreach ($rows as $r) {
            if ((int)($r['id'] ?? 0) === $id) {
                $targetRow = $r;
                break;
            }
        }

        if (!$targetRow) {
            return ['success' => false, 'error' => 'Data divisi tidak ditemukan'];
        }

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum <= 1) {
            return ['success' => false, 'error' => 'Gagal menentukan baris data divisi'];
        }

        $updated = $client->updateValues("Divisi!A{$rowNum}:C{$rowNum}", [[
            $id,
            $nama,
            $keterangan
        ]]);

        if (!$updated) {
            return ['success' => false, 'error' => 'Gagal memperbarui data divisi di Google Sheets'];
        }

        $client->clearCache('Divisi');
        map_sheets_assets(true);

        return ['success' => true, 'id' => $id, 'nama' => $nama];
    }

    // MySQL Mode
    try {
        $dName = name_column('divisi') ?: 'nama_divisi';
        $cols = table_columns('divisi');
        if (in_array('keterangan', $cols, true)) {
            $up = db()->prepare("UPDATE divisi SET `{$dName}` = ?, `keterangan` = ? WHERE id = ?");
            $up->execute([$nama, $keterangan, $id]);
        } else {
            $up = db()->prepare("UPDATE divisi SET `{$dName}` = ? WHERE id = ?");
            $up->execute([$nama, $id]);
        }
        return ['success' => true, 'id' => $id, 'nama' => $nama];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_kategori_list(): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        return $client ? $client->getSheetData('Kategori_Aset') : [];
    }
    try {
        $kName = name_column('kategori_aset') ?: 'id';
        return db()->query("SELECT id, `{$kName}` AS nama, `{$kName}` AS nama_kategori FROM kategori_aset ORDER BY `{$kName}`")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_karyawan_list(int $cabangId = 0): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $rows = $client ? $client->getSheetData('Karyawan') : [];
        if ($cabangId > 0) {
            $rows = array_filter($rows, function($k) use ($cabangId) {
                return (int)($k['id_cabang'] ?? 0) === $cabangId;
            });
        }
        return array_values($rows);
    }
    try {
        $kName = name_column('karyawan') ?: 'id';
        $sql = "SELECT id, `{$kName}` AS nama, `{$kName}` AS nama_karyawan, id_cabang, id_divisi FROM karyawan";
        $params = [];
        if ($cabangId > 0) {
            $sql .= " WHERE id_cabang = ?";
            $params[] = $cabangId;
        }
        $sql .= " ORDER BY `{$kName}`";
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

