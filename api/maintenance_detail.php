<?php
require __DIR__ . '/bootstrap.php';
require_login();

$id = max(0, (int)($_GET['id'] ?? 0));
if ($id <= 0) {
    http_response_code(400);
    render_page('Parameter Tidak Valid', '<div class="alert alert-danger">ID log maintenance tidak valid.</div>');
    exit;
}

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Handle Edit / Update Checklist POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'update_detail')) {
    verify_csrf();

    $newStatus = trim((string)($_POST['status'] ?? 'Selesai'));
    $newFindings = trim((string)($_POST['findings'] ?? ''));
    $newRecommendation = trim((string)($_POST['recommendation'] ?? ''));

    $newChecklists = [];
    $fixedItems = get_fixed_checklists();
    foreach ($fixedItems as $num => $name) {
        $checked = !empty($_POST['chk_' . $num]) ? 1 : 0;
        $notes = trim((string)($_POST['notes_' . $num] ?? ''));
        $newChecklists[$num] = [
            'checked' => $checked,
            'notes' => $notes
        ];
    }

    $res = update_maintenance_detail($id, [
        'status' => $newStatus,
        'findings' => $newFindings,
        'recommendation' => $newRecommendation,
        'checklists' => $newChecklists
    ]);

    if (!empty($res['success'])) {
        $_SESSION['flash'] = "Data checklist maintenance #{$id} berhasil diperbarui.";
        header('Location: ' . module_url('maintenance_detail.php', ['id' => $id]));
        exit;
    } else {
        $error = $res['error'] ?? 'Gagal memperbarui checklist maintenance.';
    }
}

$detail = get_maintenance_detail($id);
if (!$detail) {
    http_response_code(404);
    render_page('Data Tidak Ditemukan', '<div class="alert alert-warning">Data rincian maintenance dengan ID #'.$id.' tidak ditemukan.</div>');
    exit;
}

$scan = $detail['scan'];
$asset = $detail['asset'];
$checklists = $detail['checklists'];

$status = $scan['status'] ?? 'Selesai';
$statusBadge = ($status === 'Temuan' || $status === 'Perlu Perbaikan')
    ? '<span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perlu Perbaikan</span>'
    : ($status === 'Proses'
        ? '<span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> Sedang Proses</span>'
        : '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Selesai</span>');

// Checklist 9 item table & Edit form inputs
$chkTableRows = '';
$editChecklistRows = '';
$totalChecked = 0;

foreach ($checklists as $num => $c) {
    $isDone = !empty($c['checked']);
    if ($isDone) $totalChecked++;

    $icon = $isDone
        ? '<span class="badge bg-success bg-opacity-10 text-success fs-6 fw-bold px-3 py-1 border border-success border-opacity-25"><i class="bi bi-check2-circle me-1"></i> OK / Normal</span>'
        : '<span class="badge bg-danger bg-opacity-10 text-danger fs-6 fw-bold px-3 py-1 border border-danger border-opacity-25"><i class="bi bi-exclamation-triangle me-1"></i> Belum Selesai</span>';

    $noteText = !empty($c['notes']) ? e($c['notes']) : ($isDone ? 'Normal' : '-');

    $chkTableRows .= '
    <tr class="'.($isDone ? '' : 'table-warning text-dark').'">
      <td class="text-center fw-bold text-secondary" style="width: 45px;">'.$num.'</td>
      <td class="fw-semibold text-dark">'.e($c['name']).'</td>
      <td class="text-center" style="width: 150px;">'.$icon.'</td>
      <td><span class="fw-semibold text-dark">'.$noteText.'</span></td>
    </tr>';

    $editChecklistRows .= '
    <div class="col-md-6 mb-3">
      <div class="p-3 border rounded-3 bg-light h-100">
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" role="switch" name="chk_'.$num.'" id="modal_chk_'.$num.'" value="1" '.($isDone ? 'checked' : '').'>
          <label class="form-check-label fw-bold text-dark" for="modal_chk_'.$num.'">'.$num.'. '.e($c['name']).'</label>
        </div>
        <input type="text" class="form-control form-control-sm" name="notes_'.$num.'" value="'.e($c['notes'] ?? ($isDone ? 'Normal' : '')).'" placeholder="Catatan / keterangan...">
      </div>
    </div>';
}

$dateStr = format_id_date($scan['maintenance_date'] ?? '');
$timeStr = substr((string)($scan['maintenance_time'] ?? ''), 0, 5);
$techName = $scan['technician_name'] ?? 'Teknisi';
$findings = $scan['findings'] ?? '-';
$recommendation = $scan['recommendation'] ?? '-';
$mType = $scan['source'] ?? $scan['maintenance_type'] ?? 'Maintenance';

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show no-print"><i class="bi bi-check-circle-fill me-2"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show no-print"><i class="bi bi-exclamation-triangle-fill me-2"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$body = '
'.$flashHtml.'
'.$errorHtml.'

<div class="row justify-content-center">
  <div class="col-lg-10 col-md-11">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 no-print">
      <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-medical text-primary me-2"></i>Rincian Hasil Maintenance #'.$id.'</h3>
        <div class="text-secondary">Pencatatan 9 checklist pemeliharaan perangkat IT resmi untuk keperluan audit & verifikasi.</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="'.e(module_url('audit.php')).'"><i class="bi bi-arrow-left me-1"></i> Riwayat Audit</a>
        <button type="button" class="btn btn-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#editChecklistModal"><i class="bi bi-pencil-square me-1"></i> Edit Checklist</button>
        <button class="btn btn-primary fw-semibold" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak Detail</button>
      </div>
    </div>

    <!-- Card 1: Detail Perangkat -->
    <div class="card p-4 border-0 shadow-sm mb-4">
      <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <h5 class="fw-bold text-primary mb-0"><i class="bi bi-laptop me-2"></i>1. DETAIL PERANGKAT</h5>
        <span class="badge bg-light text-dark border">ID Aset #'.(int)($asset['id'] ?? 0).'</span>
      </div>
      <div class="row g-3 small">
        <div class="col-md-4">
          <div class="text-secondary">Kode Inventaris:</div>
          <div class="fw-bold text-primary fs-6">'.e($asset['kode_inventaris'] ?? '-').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Serial Number:</div>
          <div class="fw-semibold text-dark">'.e($asset['serial_number'] ?? '-').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Jenis / Kategori:</div>
          <div class="fw-semibold text-dark">'.e($asset['kategori_nama'] ?? 'Perangkat IT').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Perangkat (Merk & Model):</div>
          <div class="fw-bold text-dark">'.e(asset_title($asset)).'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Pemilik / User:</div>
          <div class="fw-bold text-dark"><i class="bi bi-person-circle text-primary me-1"></i>'.e($asset['karyawan_nama'] ?? '-').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Lokasi Cabang & Divisi:</div>
          <div class="fw-semibold text-dark">'.e($asset['cabang_nama'] ?? '-').' · '.e($asset['divisi_nama'] ?? '-').'</div>
        </div>
      </div>
    </div>

    <!-- Card 2: Detail Pelaksanaan Maintenance -->
    <div class="card p-4 border-0 shadow-sm mb-4">
      <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <h5 class="fw-bold text-primary mb-0"><i class="bi bi-calendar2-check me-2"></i>2. DETAIL PELAKSANAAN MAINTENANCE</h5>
        '.$statusBadge.'
      </div>
      <div class="row g-3 small mb-4">
        <div class="col-md-3">
          <div class="text-secondary">Tanggal Pelaksanaan:</div>
          <div class="fw-bold text-dark fs-6">'.e($dateStr).'</div>
        </div>
        <div class="col-md-3">
          <div class="text-secondary">Waktu / Jam:</div>
          <div class="fw-semibold text-dark">'.e($timeStr).' WITA</div>
        </div>
        <div class="col-md-3">
          <div class="text-secondary">Petugas / Teknisi:</div>
          <div class="fw-bold text-primary">'.e($techName).'</div>
        </div>
        <div class="col-md-3">
          <div class="text-secondary">Jenis Maintenance:</div>
          <div class="fw-semibold text-dark">'.e($mType).'</div>
        </div>
      </div>

      <!-- 3. Checklist 9 Item -->
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-check2-square text-primary me-1"></i>HASIL 9 CHECKLIST PEMELIHARAAN ('.$totalChecked.'/9 OK):</h6>
        <button type="button" class="btn btn-sm btn-outline-primary no-print" data-bs-toggle="modal" data-bs-target="#editChecklistModal"><i class="bi bi-pencil me-1"></i> Ubah Catatan Checklist</button>
      </div>
      
      <div class="table-responsive rounded-3 border mb-4">
        <table class="table table-bordered align-middle mb-0 small">
          <thead class="table-light">
            <tr class="text-center fw-bold">
              <th style="width: 45px;">No</th>
              <th class="text-start">Item Pemeliharaan</th>
              <th style="width: 150px;">Status Checklist</th>
              <th class="text-start">Keterangan / Hasil Pemeriksaan</th>
            </tr>
          </thead>
          <tbody>'.$chkTableRows.'</tbody>
        </table>
      </div>

      <!-- 4. Temuan & Rekomendasi -->
      <div class="row g-3">
        <div class="col-md-6">
          <div class="p-3 bg-light rounded-3 border h-100">
            <h6 class="fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan / Masalah:</h6>
            <div class="small text-dark">'.nl2br(e($findings)).'</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="p-3 bg-light rounded-3 border h-100">
            <h6 class="fw-bold text-success mb-2"><i class="bi bi-lightbulb-fill me-1"></i>Rekomendasi / Tindakan:</h6>
            <div class="small text-dark">'.nl2br(e($recommendation)).'</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal Edit Checklist -->
<div class="modal fade" id="editChecklistModal" tabindex="-1" aria-labelledby="editChecklistModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="update_detail">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold" id="editChecklistModalLabel"><i class="bi bi-pencil-square me-2"></i>Perbarui 9 Checklist Maintenance #'.$id.'</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label fw-bold">Status Hasil Maintenance</label>
              <select class="form-select" name="status">
                <option value="Selesai" '.($status==='Selesai'?'selected':'').'>✅ Selesai (Normal)</option>
                <option value="Temuan" '.($status==='Temuan'?'selected':'').'>⚠️ Temuan (Ada Masalah)</option>
                <option value="Perlu Perbaikan" '.($status==='Perlu Perbaikan'?'selected':'').'>🚨 Perlu Perbaikan</option>
                <option value="Proses" '.($status==='Proses'?'selected':'').'>⏳ Sedang Proses</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Temuan / Catatan Kerusakan</label>
              <input type="text" class="form-control" name="findings" value="'.e($findings !== '-' ? $findings : '').'" placeholder="Ketik temuan jika ada...">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Rekomendasi / Tindakan</label>
              <input type="text" class="form-control" name="recommendation" value="'.e($recommendation !== '-' ? $recommendation : '').'" placeholder="Tindakan yang dilakukan...">
            </div>
          </div>

          <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-check2-square text-primary me-1"></i>9 Item Pemeriksaan:</h6>
          <div class="row">
            '.$editChecklistRows.'
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save me-1"></i> Simpan Perubahan Checklist</button>
        </div>
      </form>
    </div>
  </div>
</div>
';

render_page('Detail Maintenance #' . $id, $body);
