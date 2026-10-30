<?php
// =========================================================================
// 8. MODALS TERPADU UNTUK KARTU INVENTARIS CR80 & TEMUAN
// =========================================================================
$body .= '
<!-- MODAL: UPDATE PROSES PERBAIKAN PERANGKAT -->
<div class="modal fade" id="modalProsesPerbaikan" tabindex="-1" aria-labelledby="modalProsesPerbaikanLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content border-0 shadow">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="save_proses_perbaikan">
      <input type="hidden" name="finding_id" id="modalFindingId" value="0">
      <input type="hidden" name="scan_id" id="modalScanId" value="0">
      <input type="hidden" name="asset_id" id="modalAssetId" value="0">
      <input type="hidden" name="bulan" value="'.$month.'">
      <input type="hidden" name="tahun" value="'.$year.'">
      <input type="hidden" name="cabang" value="'.$cabangId.'">
      
      <div class="modal-header bg-primary text-white py-3">
        <h5 class="modal-title fw-bold fs-6 mb-0" id="modalProsesPerbaikanLabel"><i class="bi bi-tools me-2"></i>Update Proses Perbaikan Perangkat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">
        <!-- Info Perangkat & Kendala -->
        <div class="p-3 bg-light rounded-3 mb-3 border">
          <div class="fw-bold text-primary font-monospace" id="modalKodePerangkat">-</div>
          <div class="small fw-semibold text-dark mb-2" id="modalNamaPerangkat">-</div>
          <div class="alert alert-danger py-2 px-3 small mb-0">
            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan:</strong>
            <span id="modalDeskripsiTemuan">-</span>
          </div>
        </div>

        <!-- Status Hasil Perbaikan -->
        <div class="mb-3">
          <label class="form-label small fw-bold text-dark">Status Proses Perbaikan <span class="text-danger">*</span></label>
          <select class="form-select fw-semibold" name="status" id="modalStatusSelect" required>
            <option value="In Progress" class="text-warning fw-bold">⏳ Dalam Proses Perbaikan (Sedang Dikerjakan / Tunggu Sparepart)</option>
            <option value="Resolved" class="text-success fw-bold">✓ Terselesaikan (Perbaikan Berhasil & Komputer Normal)</option>
            <option value="Perlu Perbaikan" class="text-danger fw-bold">⚠️ Perlu Perbaikan (Belum Ditangani)</option>
          </select>
        </div>

        <!-- Catatan / Progres Perbaikan -->
        <div class="mb-3">
          <label class="form-label small fw-bold text-dark">Catatan / Uraian Tindak Lanjut <span class="text-danger">*</span></label>
          <textarea class="form-control" name="proses_perbaikan" id="modalProsesTextarea" rows="3" placeholder="Contoh: Telah diajukan pengadaan SSD 256GB / Sedang backup data & install OS..." required></textarea>
          
          <div class="mt-2">
            <div class="small text-muted mb-1"><i class="bi bi-tag me-1"></i> Klik untuk isi cepat tindakan:</div>
            <div class="d-flex flex-wrap gap-1">
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Sedang diajukan pengadaan sparepart (SSD/RAM)\')">📦 Tunggu Sparepart</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Sedang proses backup data & instalasi ulang OS Windows\')">💻 Backup & Reinstall OS</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Telah diganti sparepart baru dan berfungsi normal kembali\')">✓ Ganti Sparepart Selesai</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Pembersihan debu hardware & pergantian thermal paste\')">💨 Bersih Hardware</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Perbaikan driver & konfigurasi jaringan/LAN selesai\')">🌐 Driver & LAN Normal</button>
            </div>
          </div>
        </div>

        <!-- Teknisi -->
        <div class="mb-2">
          <label class="form-label small fw-bold text-secondary">Teknisi Penanggung Jawab</label>
          <input type="text" class="form-control" name="technician_name" id="modalTeknisiInput" value="'.e(current_user_name()).'" placeholder="Nama teknisi...">
        </div>
      </div>
      <div class="modal-footer bg-light py-2 px-4">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save-fill me-1"></i> Simpan Proses Perbaikan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: TAMBAH DATA KARTU INVENTARIS -->
<div class="modal fade" id="addCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="create_card">
        <div class="modal-header py-3 px-4 text-white" style="background: #0E5C58 !important;">
          <div>
            <h5 class="modal-title fw-bold mb-0"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Data Kartu Inventaris</h5>
            <small class="text-white-50">Sistem Inventaris Core Banking BPR Mitratama</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <!-- Section 1: Identitas Rekening & Barang -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace small">01 · REKENING & IDENTITAS BARANG</span>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-bold text-dark mb-0">Nomor Rekening / Kode Inventaris <span class="text-danger">*</span></label>
                <span class="badge bg-light text-primary border font-monospace" style="font-size: 0.68rem;">Auto Urut Cabang</span>
              </div>
              <input type="text" name="nomor_rekening" id="addCardRekening" class="form-control font-monospace fw-bold text-primary" value="'.e($nextKodeInventarisMap['01'] ?? '01.5.00497').'" placeholder="Contoh: 01.5.00497" maxlength="10" required oninput="handleRekeningInput(this, event)">
              <div class="d-flex flex-wrap align-items-center gap-1 mt-1.5">
                <span class="text-muted" style="font-size: 0.70rem;">Pilih Cabang:</span>
                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1.5 font-monospace" style="font-size: 0.68rem;" onclick="selectAddCardBranch(\'01\')">01 KPO</button>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 font-monospace" style="font-size: 0.68rem;" onclick="selectAddCardBranch(\'02\')">02 BTL</button>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 font-monospace" style="font-size: 0.68rem;" onclick="selectAddCardBranch(\'03\')">03 MTP</button>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 font-monospace" style="font-size: 0.68rem;" onclick="selectAddCardBranch(\'04\')">04 TJG</button>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 font-monospace" style="font-size: 0.68rem;" onclick="selectAddCardBranch(\'05\')">05 HDB</button>
              </div>
              <div class="form-text text-muted" style="font-size: 0.70rem;">Format resmi: <strong>XX.X.XXXXX</strong> (contoh: <code>'.e($nextKodeInventarisMap['01'] ?? '01.5.00497').'</code>)</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nama Barang <span class="text-danger">*</span></label>
              <input type="text" name="nama_barang" class="form-control" placeholder="Contoh: MOBIL AVANZA DA 1532 BV" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nomor Seri Inventaris Bank</label>
              <div class="input-group">
                <input type="text" name="nomor_seri_inventaris" id="addCardNoSeri" class="form-control font-monospace" placeholder="Contoh: 01.05.02.200224.001">
                <button class="btn btn-light border" type="button" onclick="autoGenerateCardNoSeri(\'add\')" title="Generate Format Bank"><i class="bi bi-magic me-1"></i>Auto</button>
              </div>
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format resmi: [Cabang].[Ruang].[Gol].[ddMMyy].[No]</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Tanggal Perolehan <span class="text-danger">*</span></label>
              <input type="text" name="tanggal_perolehan" id="addCardTanggal" class="form-control font-monospace" placeholder="dd/mm/yyyy" value="'.date('d/m/Y').'" maxlength="10" required oninput="handleDateInput(this, event)">
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format: <strong>dd/mm/yyyy</strong></div>
            </div>
          </div>

          <!-- Section 2: Master Core Banking Bank -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace small">02 · MASTER DATA CORE BANKING</span>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Jenis Inventaris</label>
              <select class="form-select" name="jenis_inventaris" id="addCardJenis">
                '.$optCardJenis.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Golongan Inventaris</label>
              <select class="form-select" name="golongan" id="addCardGolongan">
                '.$optCardGolongan.'
              </select>
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-bold text-dark mb-0">Ruangan Penempatan</label>
                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold text-primary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuanganDashboard" style="font-size: 0.78rem;">
                  <i class="bi bi-plus-circle me-1"></i>+ Tambah Ruangan
                </button>
              </div>
              <div class="input-group">
                <select class="form-select" name="ruangan" id="addCardRuangan">
                  '.$optCardRuangan.'
                </select>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuanganDashboard" title="Tambah Ruangan Baru">
                  <i class="bi bi-plus-lg text-primary"></i>
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Sumber Perolehan</label>
              <select class="form-select" name="sumber_perolehan" id="addCardSumber">
                '.$optCardSumber.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Status Inventaris</label>
              <select class="form-select" name="status_inventaris" id="addCardStatusInv">
                '.$optCardStatusInv.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Lokasi Barang / Kantor</label>
              <input type="text" name="lokasi" id="addCardLokasi" class="form-control" placeholder="Contoh: Kantor Pusat, Handil Bakti, Marabahan..." list="listLokasiBank">
              <datalist id="listLokasiBank">
                <option value="Kantor Pusat (KPO)">
                <option value="Handil Bakti">
                <option value="Marabahan">
                <option value="Pasar Panas">
              </datalist>
            </div>
          </div>

          <!-- Section 3: QR Code & Catatan -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace small">03 · KODE QR & KETERANGAN</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Kode QR / Barcode (Data QR Code)</label>
              <input type="text" name="barcode_data" class="form-control font-monospace" placeholder="Salin/tempel link QR atau biarkan kosong">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Keterangan / Spesifikasi</label>
              <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan spesifikasi unit perangkat atau nomor polisi..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2.5 px-4 bg-light d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary rounded-pill fw-semibold px-4"><i class="bi bi-save me-1"></i> Simpan Data Kartu</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: EDIT DATA KARTU INVENTARIS -->
<div class="modal fade" id="editCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="update_card">
        <input type="hidden" name="id" id="editCardId">
        <div class="modal-header bg-warning text-dark py-3 px-4">
          <div>
            <h5 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2">
              <i class="bi bi-pencil-square"></i> Edit Kartu Inventaris
            </h5>
            <small class="text-dark opacity-75">Perbarui data spesifikasi dan klasifikasi Core Banking</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <!-- Section 1: Identitas Rekening & Barang -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2 py-1 font-monospace small">01 · REKENING & IDENTITAS BARANG</span>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nomor Rekening / Kode Inventaris <span class="text-danger">*</span></label>
              <input type="text" name="nomor_rekening" id="editCardRekening" class="form-control font-monospace fw-bold text-primary" placeholder="Contoh: 01.5.00496" maxlength="10" required oninput="handleRekeningInput(this, event)">
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format resmi: <strong>XX.X.XXXXX</strong></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nama Barang <span class="text-danger">*</span></label>
              <input type="text" name="nama_barang" id="editCardNama" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nomor Seri Inventaris Bank</label>
              <div class="input-group">
                <input type="text" name="nomor_seri_inventaris" id="editCardNoSeri" class="form-control font-monospace" placeholder="Contoh: 01.05.02.200224.001">
                <button class="btn btn-light border" type="button" onclick="autoGenerateCardNoSeri(\'edit\')" title="Generate Format Bank"><i class="bi bi-magic me-1"></i>Auto</button>
              </div>
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format resmi: [Cabang].[Ruang].[Gol].[ddMMyy].[No]</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Tanggal Perolehan <span class="text-danger">*</span></label>
              <input type="text" name="tanggal_perolehan" id="editCardTanggal" class="form-control font-monospace" placeholder="dd/mm/yyyy" maxlength="10" required oninput="handleDateInput(this, event)">
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format: <strong>dd/mm/yyyy</strong></div>
            </div>
          </div>

          <!-- Section 2: Master Core Banking Bank -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2 py-1 font-monospace small">02 · MASTER DATA CORE BANKING</span>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Jenis Inventaris</label>
              <select class="form-select" name="jenis_inventaris" id="editCardJenis">
                '.$optCardJenis.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Golongan Inventaris</label>
              <select class="form-select" name="golongan" id="editCardGolongan">
                '.$optCardGolongan.'
              </select>
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-bold text-dark mb-0">Ruangan Penempatan</label>
                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold text-primary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuanganDashboard" style="font-size: 0.78rem;">
                  <i class="bi bi-plus-circle me-1"></i>+ Tambah Ruangan
                </button>
              </div>
              <div class="input-group">
                <select class="form-select" name="ruangan" id="editCardRuangan">
                  '.$optCardRuangan.'
                </select>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuanganDashboard" title="Tambah Ruangan Baru">
                  <i class="bi bi-plus-lg text-primary"></i>
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Sumber Perolehan</label>
              <select class="form-select" name="sumber_perolehan" id="editCardSumber">
                '.$optCardSumber.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Status Inventaris</label>
              <select class="form-select" name="status_inventaris" id="editCardStatusInv">
                '.$optCardStatusInv.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Lokasi Barang / Kantor</label>
              <input type="text" name="lokasi" id="editCardLokasi" class="form-control" placeholder="Contoh: Handil Bakti, KPO..." list="listLokasiBank">
            </div>
          </div>

          <!-- Section 3: QR Code & Catatan -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2 py-1 font-monospace small">03 · KODE QR & KETERANGAN</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Kode QR / Barcode (Data QR Code)</label>
              <input type="text" name="barcode_data" id="editCardBarcode" class="form-control font-monospace" placeholder="Salin/tempel kode QR di sini">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Keterangan / Spesifikasi</label>
              <textarea name="keterangan" id="editCardKeterangan" class="form-control" rows="2" placeholder="Catatan spesifikasi perangkat..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2.5 px-4 bg-light d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary rounded-pill fw-semibold px-4"><i class="bi bi-check-lg me-1"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Quick Add Ruangan di Dashboard -->
<div class="modal fade" id="modalQuickAddRuanganDashboard" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow border-0" style="border-radius: 16px;">
      <div class="modal-header border-bottom py-3 px-4">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
          <i class="bi bi-door-open text-primary"></i> Tambah Ruangan Penempatan
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div id="quickRuanganDashboardAlert" class="alert alert-danger d-none py-2 small mb-3"></div>
        <div class="mb-3">
          <label class="form-label text-secondary small fw-semibold">Kode Ruangan (2 Digit)</label>
          <input type="text" class="form-control font-monospace" id="inputQuickKodeRuanganDashboard" placeholder="Contoh: 12 (Kosongkan utk otomatis)">
          <div class="form-text small text-muted">Biarkan kosong untuk penomoran otomatis.</div>
        </div>
        <div class="mb-2">
          <label class="form-label text-secondary small fw-semibold">Nama Ruangan <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="inputQuickNamaRuanganDashboard" placeholder="Contoh: RUANG AUDIT & SKAI...">
        </div>
      </div>
      <div class="modal-footer border-top bg-light py-2 px-4 d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btnQuickSubmitRuanganDashboard" onclick="submitQuickRuanganDashboard()">
          <i class="bi bi-check2 me-1"></i> Simpan Ruangan
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: PILIH DARI ASET IT (IMPORT CATALOG) -->
<div class="modal fade" id="importAssetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="import_from_assets">
        <div class="modal-header bg-primary text-white py-2 px-3">
          <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-box-seam"></i> Pilih Perangkat dari Katalog Aset IT
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3">
          <p class="small text-muted mb-2">Pilih perangkat di bawah ini untuk didaftarkan ke tabel Kartu Inventaris:</p>
          <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 36px;"></th>
                  <th>Kode Inventaris</th>
                  <th>Nama Perangkat</th>
                  <th>Pengguna</th>
                  <th>Cabang</th>
                </tr>
              </thead>
              <tbody>';
foreach ($rawAssetsForModal as $a) {
    $aid = (int)($a['id'] ?? 0);
    $body .= '
                <tr>
                  <td class="text-center">
                    <input class="form-check-input" type="checkbox" name="asset_ids[]" value="'.$aid.'">
                  </td>
                  <td><span class="font-monospace fw-bold text-primary small">'.e($a['kode_inventaris'] ?? ('ASET-' . $aid)).'</span></td>
                  <td><span class="fw-semibold text-dark small">'.e(asset_title($a)).'</span></td>
                  <td><span class="text-muted small">'.e($a['karyawan_nama'] ?? '-').'</span></td>
                  <td><span class="badge-chip chip-secondary">'.e($a['cabang_nama'] ?? 'KPO').'</span></td>
                </tr>';
}
$body .= '
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light">
          <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3"><i class="bi bi-box-arrow-in-down me-1"></i> Impor ke Kartu Inventaris</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: PRATINJAU DESAIN FISIK KARTU -->
<div class="modal fade" id="previewCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header bg-primary text-white py-2 px-3">
        <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
          <i class="bi bi-eye-fill"></i> Pratinjau Desain Kartu Fisik
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center bg-light p-2 sm:p-4" style="overflow-x: hidden;">
        <!-- Container Skala Pratinjau (Responsif Mobile & Desktop) -->
        <div class="cr80-preview-scale-box">
          <div class="atm-card" style="text-align: left;">
            <!-- Top Header -->
            <div class="card-header-sec">
              <div class="header-logo-box">
                <img src="'.e($logoUri).'" alt="Logo Bank Mitra" onerror="this.src=\'logo.png\'">
              </div>
              <div class="header-title-box">
                <div class="header-main-title">PT BPR MITRATAMA ARTHABUANA</div>
                <div class="header-sub-sec">
                  <div class="green-banner-wrapper">
                    <div class="teal-stripe"></div>
                    <div class="green-banner">ASSET TETAP</div>
                  </div>
                  <div class="header-dots-container">
                    <div class="header-dots">
                      <span></span><span></span><span></span>
                      <span></span><span></span><span></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Data Fields Section -->
            <div class="card-fields-sec">
              <!-- 1. Nomor Asset -->
              <div class="card-field-row">
                <div class="field-icon-box">
                  <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 8.586V2zm3.5 3.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
                </div>
                <div class="field-content-box">
                  <span class="field-lbl">NOMOR ASSET</span>
                  <span class="field-sep">|</span>
                  <span class="field-val" id="pvNomorAsset">0105049326092026</span>
                </div>
              </div>
              <!-- 2. Nama Asset -->
              <div class="card-field-row">
                <div class="field-icon-box">
                  <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M12 1H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zM4 2h8a1 1 0 0 1 1 1v7H3V3a1 1 0 0 1 1-1z"/><path d="M8 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>
                </div>
                <div class="field-content-box">
                  <span class="field-lbl">NAMA ASSET</span>
                  <span class="field-sep">|</span>
                  <span class="field-val" id="pvNamaAsset">PRINTER EPSON L3211 KAS</span>
                </div>
              </div>
              <!-- 3. Tgl Perolehan -->
              <div class="card-field-row">
                <div class="field-icon-box">
                  <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
                </div>
                <div class="field-content-box">
                  <span class="field-lbl">TGL PEROLEHAN</span>
                  <span class="field-sep">|</span>
                  <span class="field-val" id="pvTglPerolehan">26/09/2026</span>
                </div>
              </div>
              <!-- 4. Lokasi -->
              <div class="card-field-row">
                <div class="field-icon-box">
                  <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>
                </div>
                <div class="field-content-box">
                  <span class="field-lbl">LOKASI</span>
                  <span class="field-sep">|</span>
                  <span class="field-val" id="pvLokasi">KPO</span>
                </div>
              </div>
            </div>

            <!-- Bottom Section -->
            <div class="card-bottom-sec">
              <!-- Background Waves SVG -->
              <div class="card-waves">
                <svg viewBox="0 0 85.6 7.5" preserveAspectRatio="none" style="width: 100%; height: 100%; display: block;">
                  <path d="M 0 3 C 8 2.5, 18 5, 24 7.5 L 0 7.5 Z" fill="#7ac142" />
                  <path d="M 5 7.5 Q 32 3, 58 6.5 T 85.6 3.5 L 85.6 7.5 Z" fill="#003b73" />
                </svg>
              </div>
              <!-- Left: Attention Disclaimer -->
              <div class="bottom-left-attention">
                <div class="attention-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="#003b73" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="attention-svg-icon">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    <line x1="12" y1="8" x2="12" y2="13" />
                    <line x1="12" y1="16.5" x2="12.01" y2="16.5" stroke-width="3" />
                  </svg>
                </div>
                <div class="attention-text-box">
                  <div class="attention-title">Perhatian</div>
                  <div class="attention-desc">Dilarang memindahkan barang inventaris ini tanpa seizin Human Resource Departement (HRD) Bank Mitra</div>
                </div>
              </div>
              <!-- Separator Line -->
              <div class="attention-qr-separator"></div>
              <!-- Right: QR Code -->
              <div class="bottom-right-qr">
                <div class="qr-border-box">
                  <div id="pvQrBox" class="card-qr-img"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2.5 px-3 bg-white justify-content-between flex-wrap gap-2">
        <div class="text-muted" style="font-size: 0.72rem;">
          <i class="bi bi-rulers text-primary me-1"></i> Ukuran Asli CR80: <strong>85.6mm &times; 54.0mm</strong> (300 DPI)
        </div>
        <div class="d-flex flex-wrap gap-1.5">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="button" class="btn btn-sm fw-bold text-white" style="background-color: #0E5C58; border-color: #0E5C58;" onclick="if(window.CURRENT_PREVIEW_CARD) downloadOrSharePhysicalCard(window.CURRENT_PREVIEW_CARD, \'download\', this)">
            <i class="bi bi-download me-1"></i> Unduh Gambar Kartu (PNG)
          </button>
          <button type="button" class="btn btn-sm btn-success fw-bold" onclick="if(window.CURRENT_PREVIEW_CARD) downloadOrSharePhysicalCard(window.CURRENT_PREVIEW_CARD, \'share\', this)">
            <i class="bi bi-whatsapp me-1"></i> Bagikan / Kirim WA
          </button>
          <a id="pvPrintDirectBtn" href="#" target="_blank" class="btn btn-sm btn-primary fw-bold">
            <i class="bi bi-printer-fill me-1"></i> Cetak A4
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Forms Hidden untuk Hapus Satuan & Hapus Massal -->
<form id="deleteCardForm" method="post" style="display: none;">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="action" value="delete_card">
  <input type="hidden" name="id" id="deleteTargetCardId">
</form>

<form id="deleteBatchCardForm" method="post" style="display: none;">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="action" value="delete_batch_card">
  <input type="hidden" name="ids" id="deleteBatchCardIds">
</form>';

// MODAL KELOLA SKIP CABANG (KHUSUS ADMIN)
$dashboardSkipBranchRowsHtml = '';
if (!empty($branchSummaries)) {
    foreach ($branchSummaries as $bsm) {
        $bId = (int)($bsm['id'] ?? 0);
        $bNm = (string)($bsm['nama'] ?? ('Cabang #' . $bId));
        $bSk = !empty($bsm['is_skipped']);
        $bRs = (string)($bsm['skip_reason'] ?? 'Tidak ada jadwal kunjungan cabang bulan ini');

        $dashboardSkipBranchRowsHtml .= '
        <div class="d-flex align-items-center justify-content-between p-2.5 rounded border '.($bSk ? 'bg-light border-secondary-subtle' : 'bg-white').' gap-2">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2">
              <span class="fw-bold text-dark small">'.e($bNm).'</span>
              '.($bSk ? '<span class="badge bg-secondary-subtle text-secondary border font-monospace" style="font-size: 0.68rem;"><i class="bi bi-calendar-x me-1"></i>Dilewati</span>' : '<span class="badge bg-success-subtle text-success border font-monospace" style="font-size: 0.68rem;"><i class="bi bi-check-circle me-1"></i>Aktif</span>').'
            </div>
            <div class="text-muted small" style="font-size: 0.75rem;">'.($bSk ? 'Alasan: '.e($bRs) : (int)($bsm['total'] ?? 0).' unit aset').'</div>
          </div>
          <div>
            '.($bSk ? '
            <form method="post" class="m-0" onsubmit="return confirm(\'Aktifkan kembali jadwal pemeliharaan '.e(addslashes($bNm)).'?\');">
              '.csrf_field().'
              <input type="hidden" name="action" value="cancel_skip_cabang">
              <input type="hidden" name="cabang_id" value="'.$bId.'">
              <input type="hidden" name="bulan" value="'.$month.'">
              <input type="hidden" name="tahun" value="'.$year.'">
              <input type="hidden" name="return_cabang" value="'.$cabangId.'">
              <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2" style="font-size: 0.75rem;">
                <i class="bi bi-calendar-check me-1"></i> Aktifkan
              </button>
            </form>' : '
            <button type="button" class="btn btn-sm btn-outline-warning text-dark py-1 px-2" style="font-size: 0.75rem;" onclick="setDashboardSkipForm('.$bId.', \''.e(addslashes($bNm)).'\')">
              <i class="bi bi-calendar-x me-1"></i> Lewati
            </button>').'
          </div>
        </div>';
    }
}

$body .= '
<!-- MODAL: KELOLA SKIP / LEWATI PEMELIHARAAN CABANG BULAN INI -->
<div class="modal fade" id="modalKelolaSkipCabang" tabindex="-1" aria-labelledby="modalKelolaSkipCabangLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-dark text-white py-3">
        <h5 class="modal-title fw-bold fs-6 mb-0" id="modalKelolaSkipCabangLabel">
          <i class="bi bi-calendar-x me-2 text-warning"></i>Atur Pengecualian / Lewati Cabang ('.($monthNames[$month] ?? 'Bulan Ini').' '.$year.')
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info py-2.5 px-3.5 small mb-3 border-0 rounded-[8px]" style="background-color: #EBF5FF; color: #1E429F;">
          <i class="bi bi-info-circle-fill me-1"></i>
          Jika sebuah cabang <strong>dilewati</strong> pada bulan ini (misal tidak ada kunjungan dinas/jadwal berkala), tandai sebagai <strong>Dilewati</strong> agar statusnya tidak menjadi <em>Perlu Dikejar</em> dan unit asetnya tidak membebani persentase Kepatuhan SLA IT.
        </div>

        <div class="d-flex flex-column gap-2 mb-3">
          '.$dashboardSkipBranchRowsHtml.'
        </div>

        <!-- Form Konfirmasi Lewati Cabang Tertentu -->
        <div id="boxFormConfirmSkip" class="p-3 bg-light rounded-[8px] border mt-3" style="display: none;">
          <form method="post">
            '.csrf_field().'
            <input type="hidden" name="action" value="skip_cabang">
            <input type="hidden" name="cabang_id" id="dashSkipCabangId" value="0">
            <input type="hidden" name="bulan" value="'.$month.'">
            <input type="hidden" name="tahun" value="'.$year.'">
            <input type="hidden" name="return_cabang" value="'.$cabangId.'">

            <div class="fw-bold text-dark mb-2 small" id="dashSkipCabangTitle">Lewati Pemeliharaan Cabang</div>
            <div class="mb-2">
              <label class="form-label small fw-semibold text-muted mb-1">Pilih Alasan Dilewati:</label>
              <select class="form-select form-select-sm" id="dashSkipSelect" onchange="document.getElementById(\'dashSkipReason\').value = this.value;">
                <option value="Tidak ada jadwal kunjungan cabang bulan ini">📅 Tidak ada jadwal kunjungan cabang bulan ini</option>
                <option value="Jadwal pemeliharaan berkala / triwulanan">🔄 Jadwal pemeliharaan berkala / triwulanan (off-schedule)</option>
                <option value="Kendala akses / dinas luar kota dialihkan">🚗 Kendala akses / dinas luar kota dialihkan</option>
                <option value="Kunjungan khusus bulan sebelumnya sudah menyeluruh">✓ Kunjungan khusus bulan sebelumnya sudah menyeluruh</option>
                <option value="Lainnya">✏️ Alasan lainnya...</option>
              </select>
            </div>
            <div class="mb-3">
              <input type="text" class="form-control form-control-sm" name="reason" id="dashSkipReason" value="Tidak ada jadwal kunjungan cabang bulan ini" required>
            </div>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById(\'boxFormConfirmSkip\').style.display = \'none\';">Batal</button>
              <button type="submit" class="btn btn-sm btn-warning fw-bold text-dark"><i class="bi bi-calendar-x me-1"></i> Simpan &amp; Lewati Cabang</button>
            </div>
          </form>
        </div>
      </div>
      <div class="modal-footer py-2 px-4 bg-light justify-content-between">
        <a href="'.e(module_url('cabang_admin.php', ['bulan'=>$month, 'tahun'=>$year])).'" class="small fw-bold text-decoration-none">Kelola Cabang Selengkapnya &rarr;</a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
function setDashboardSkipForm(id, name) {
  document.getElementById("dashSkipCabangId").value = id;
  document.getElementById("dashSkipCabangTitle").textContent = "Lewati Jadwal Pemeliharaan: " + name;
  const box = document.getElementById("boxFormConfirmSkip");
  box.style.display = "block";
  box.scrollIntoView({ behavior: "smooth", block: "nearest" });
}
</script>
';
