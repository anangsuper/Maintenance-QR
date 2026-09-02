<?php
require __DIR__ . '/bootstrap.php';
require_login();

$id = max(0, (int)($_GET['id'] ?? 0));
if ($id <= 0) {
    http_response_code(400);
    render_page('Parameter Tidak Valid', '<div class="alert alert-danger">ID log maintenance tidak valid.</div>');
    exit;
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

// Checklist 9 item table
$chkTableRows = '';
foreach ($checklists as $num => $c) {
    $isDone = !empty($c['checked']);
    $icon = $isDone
        ? '<span class="badge bg-success bg-opacity-15 text-success fs-6 fw-bold px-2 py-1"><i class="bi bi-check2"></i> ✓</span>'
        : '<span class="text-muted fw-bold">-</span>';
    $noteText = !empty($c['notes']) ? e($c['notes']) : ($isDone ? 'Normal' : '-');

    $chkTableRows .= '
    <tr class="'.($isDone ? '' : 'table-light text-muted').'">
      <td class="text-center fw-bold" style="width: 40px;">'.$num.'</td>
      <td class="fw-semibold text-dark">'.e($c['name']).'</td>
      <td class="text-center" style="width: 90px;">'.$icon.'</td>
      <td><span class="fw-medium text-dark">'.$noteText.'</span></td>
    </tr>';
}

$dateStr = format_id_date($scan['maintenance_date'] ?? '');
$timeStr = substr((string)($scan['maintenance_time'] ?? ''), 0, 5);
$techName = $scan['technician_name'] ?? 'Teknisi';
$findings = $scan['findings'] ?? '-';
$recommendation = $scan['recommendation'] ?? '-';
$mType = $scan['source'] ?? $scan['maintenance_type'] ?? 'Maintenance';

$body = '
<div class="row justify-content-center">
  <div class="col-lg-9 col-md-11">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 no-print">
      <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-medical text-primary me-2"></i>Rincian Hasil Maintenance</h3>
        <div class="text-secondary">Pencatatan pemeliharaan perangkat IT resmi untuk keperluan audit & verifikasi.</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="javascript:history.back()"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak Detail</button>
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
      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-check2-square text-primary me-1"></i>HASIL 9 CHECKLIST PEMELIHARAAN:</h6>
      <div class="table-responsive rounded-3 border mb-4">
        <table class="table table-bordered align-middle mb-0 small">
          <thead class="table-light">
            <tr class="text-center fw-bold">
              <th style="width: 40px;">No</th>
              <th class="text-start">Checklist</th>
              <th style="width: 90px;">Checklist</th>
              <th class="text-start">Keterangan</th>
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
</div>';

render_page('Detail Maintenance #' . $id, $body);
