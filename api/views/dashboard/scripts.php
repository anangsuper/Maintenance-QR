<?php
// =========================================================================
// 9. JAVASCRIPT LOGIC
// =========================================================================
$extraScript = '
<script>
window.NEXT_KODE_INV_MAP = ' . json_encode($nextKodeInventarisMap ?? []) . ';

function getSelectedCardIds() {
  const cbs = document.querySelectorAll(".card-checkbox:checked");
  const ids = [];
  cbs.forEach(cb => {
    if (!ids.includes(cb.value)) ids.push(cb.value);
  });
  return ids;
}

function updateCardCounters(sourceCb) {
  if (sourceCb && sourceCb.value) {
    document.querySelectorAll(".card-checkbox[value=\\"" + sourceCb.value + "\\"]").forEach(function(cb) {
      cb.checked = sourceCb.checked;
    });
  }
  const ids = getSelectedCardIds();
  const count = ids.length;
  const countDelSpan = document.getElementById("countDelete");
  const countPrintSpan = document.getElementById("countPrint");
  const countExcelSpan = document.getElementById("countExcel");
  const countSelectedText = document.getElementById("countSelectedText");
  const selBar = document.getElementById("selectionCardBar");

  if (countDelSpan) countDelSpan.innerText = count;
  if (countPrintSpan) countPrintSpan.innerText = count;
  if (countExcelSpan) countExcelSpan.innerText = count;
  if (countSelectedText) countSelectedText.innerText = count;

  if (selBar) {
    if (count > 0) {
      selBar.classList.remove("d-none");
      selBar.classList.add("d-flex");
    } else {
      selBar.classList.add("d-none");
      selBar.classList.remove("d-flex");
    }
  }

  const checkAll = document.getElementById("checkAllRows");
  const allUnique = new Set();
  document.querySelectorAll(".card-checkbox").forEach(cb => allUnique.add(cb.value));
  const totalCount = allUnique.size;
  if (checkAll && totalCount > 0) {
    checkAll.checked = count === totalCount;
    checkAll.indeterminate = count > 0 && count < totalCount;
  }
}

function toggleSelectAllCards(masterCb) {
  const cbs = document.querySelectorAll(".card-checkbox");
  cbs.forEach(cb => cb.checked = masterCb.checked);
  updateCardCounters();
}

function deselectAllCards() {
  const checkAll = document.getElementById("checkAllRows");
  if (checkAll) {
    checkAll.checked = false;
    checkAll.indeterminate = false;
  }
  document.querySelectorAll(".card-checkbox").forEach(cb => cb.checked = false);
  updateCardCounters();
}

function exportExcelSelectedCards() {
  const ids = getSelectedCardIds();
  let targetUrl = "print_inventory_card.php?source=inventaris_kartu&export=excel";
  if (ids.length > 0) {
    targetUrl += "&ids=" + encodeURIComponent(ids.join(","));
  }
  window.open(targetUrl, "_blank");
}

function printSelectedCards() {
  const ids = getSelectedCardIds();
  let targetUrl = "print_inventory_card.php?source=inventaris_kartu";
  if (ids.length > 0) {
    targetUrl += "&ids=" + encodeURIComponent(ids.join(","));
  }
  window.open(targetUrl, "_blank");
}

function bulkDeleteCards() {
  const ids = getSelectedCardIds();
  if (ids.length === 0) return;
  if (confirm("Hapus " + ids.length + " data kartu inventaris terpilih?")) {
    document.getElementById("deleteBatchCardIds").value = ids.join(",");
    document.getElementById("deleteBatchCardForm").submit();
  }
}

function confirmDeleteCard(id, label) {
  if (confirm("Hapus kartu inventaris: " + label + "?")) {
    document.getElementById("deleteTargetCardId").value = id;
    document.getElementById("deleteCardForm").submit();
  }
}

function handleRekeningInput(el, event) {
  if (!el) return;
  const isDelete = event && event.inputType && event.inputType.startsWith("delete");
  let digits = el.value.replace(/[^0-9]/g, "");

  if (digits.length === 0) {
    el.value = "";
    return;
  }

  let p0 = digits.slice(0, 2); // 2 digit kode cabang (01 s/d 05)
  let p1 = digits.slice(2, 3); // 1 digit kelompok aktiva (5)
  let p2 = digits.slice(3, 8); // 5 digit nomor urut (contoh: 00496)

  if (digits.length < 2) {
    el.value = digits;
  } else if (digits.length === 2) {
    el.value = isDelete ? p0 : p0 + ".";
  } else if (digits.length === 3) {
    el.value = isDelete ? (p0 + "." + p1) : (p0 + "." + p1 + ".");
  } else {
    el.value = p0 + "." + p1 + "." + p2;
  }

  if (p0.length === 2) {
    autoSuggestBranchLokasi(p0);
  }
  if (el.id === "addCardRekening") {
    autoGenerateCardNoSeri("add");
  }
}

function selectAddCardBranch(code) {
  const rekInput = document.getElementById("addCardRekening");
  if (rekInput && window.NEXT_KODE_INV_MAP && window.NEXT_KODE_INV_MAP[code]) {
    rekInput.value = window.NEXT_KODE_INV_MAP[code];
  } else if (rekInput) {
    rekInput.value = code + ".5.00001";
  }
  const addLok = document.getElementById("addCardLokasi");
  const mapLok = {
    "01": "Kantor Pusat (KPO)",
    "02": "Batulicin",
    "03": "Martapura",
    "04": "Tanjung",
    "05": "Handil Bakti"
  };
  if (addLok && mapLok[code]) {
    addLok.value = mapLok[code];
  }
  autoGenerateCardNoSeri("add");
}

function handleDateInput(el, event) {
  if (!el) return;
  const isDelete = event && event.inputType && event.inputType.startsWith("delete");
  let digits = el.value.replace(/[^0-9]/g, "");
  if (digits.length === 0) {
    el.value = "";
    return;
  }
  let d = digits.slice(0, 2);
  let m = digits.slice(2, 4);
  let y = digits.slice(4, 8);

  if (digits.length < 2) {
    el.value = digits;
  } else if (digits.length === 2) {
    el.value = isDelete ? d : d + "/";
  } else if (digits.length < 4) {
    el.value = d + "/" + digits.slice(2);
  } else if (digits.length === 4) {
    el.value = isDelete ? (d + "/" + m) : (d + "/" + m + "/");
  } else {
    el.value = d + "/" + m + "/" + y;
  }
}

function autoSuggestBranchLokasi(code) {
  const addLok = document.getElementById("addCardLokasi");
  if (!addLok || addLok.dataset.customized === "true") return;
  const map = {
    "01": "Kantor Pusat / ",
    "02": "Batulicin / ",
    "03": "Martapura / ",
    "04": "Tanjung / ",
    "05": "Handil Bakti / "
  };
  if (map[code]) {
    addLok.value = map[code];
  }
}

function handleLokasiKeydown(e, el) {
  if (!e || !el) return;
  if (e.key === "Enter") {
    e.preventDefault();
    el.dataset.customized = "true";
    const start = el.selectionStart !== null ? el.selectionStart : el.value.length;
    const end = el.selectionEnd !== null ? el.selectionEnd : el.value.length;
    const val = el.value;
    const before = val.substring(0, start);
    const after = val.substring(end);

    if (before.trimEnd().endsWith("/")) {
      if (!before.endsWith(" ")) {
        el.value = before + " " + after;
        el.selectionStart = el.selectionEnd = start + 1;
      }
      return;
    }

    let insert = " / ";
    if (before.length === 0) {
      insert = "/ ";
    } else if (before.endsWith(" ")) {
      insert = "/ ";
    }

    el.value = before + insert + after;
    const newPos = start + insert.length;
    el.selectionStart = el.selectionEnd = newPos;
  }
}

function autoGenerateCardNoSeri(prefix) {
  var isEdit = (prefix === "edit");
  var rekInput = document.getElementById(isEdit ? "editCardRekening" : "addCardRekening");
  var tglInput = document.getElementById(isEdit ? "editCardTanggal" : "addCardTanggal");
  var idInput = document.getElementById("editCardId");

  var kdCab = "01";
  var seqNum = "001";
  if (rekInput && rekInput.value) {
    var mCab = rekInput.value.trim().match(/^(\d{2})/);
    if (mCab) kdCab = mCab[1];
    var digits = rekInput.value.replace(/[^0-9]/g, "");
    if (digits.length >= 6) {
      var tail = parseInt(digits.slice(-3), 10);
      if (!isNaN(tail) && tail > 0) {
        seqNum = String(tail).padStart(3, "0");
      }
    }
  }
  if (isEdit && idInput && idInput.value) {
    var idVal = parseInt(idInput.value, 10);
    if (!isNaN(idVal) && idVal > 0) {
      seqNum = String(idVal % 1000 || 1).padStart(3, "0");
    }
  }

  var d = new Date();
  var dd = String(d.getDate()).padStart(2, "0");
  var mm = String(d.getMonth() + 1).padStart(2, "0");
  var yy = String(d.getFullYear()).slice(-2);
  if (tglInput && tglInput.value) {
    var mTgl = tglInput.value.trim().match(/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/);
    if (mTgl) {
      dd = mTgl[1];
      mm = mTgl[2];
      yy = mTgl[3].slice(-2);
    }
  }

  var selR = document.getElementById(isEdit ? "editCardRuangan" : "addCardRuangan");
  var kdR = "05";
  if (selR && selR.value) {
    var mR = selR.value.match(/^(\d{2})/);
    if (mR) kdR = mR[1];
  }

  var selG = document.getElementById(isEdit ? "editCardGolongan" : "addCardGolongan");
  var kdG = "02";
  if (selG && selG.value) {
    var mG = selG.value.match(/^(\d{2})/);
    if (mG) kdG = mG[1];
  }

  var target = document.getElementById(isEdit ? "editCardNoSeri" : "addCardNoSeri");
  if (target) {
    target.value = kdCab + "." + kdR + "." + kdG + "." + dd + mm + yy + "." + seqNum;
  }
}

function submitQuickRuanganDashboard() {
  const inputNama = document.getElementById("inputQuickNamaRuanganDashboard");
  const inputKode = document.getElementById("inputQuickKodeRuanganDashboard");
  const alertEl = document.getElementById("quickRuanganDashboardAlert");
  const btn = document.getElementById("btnQuickSubmitRuanganDashboard");

  const nama = inputNama ? inputNama.value.trim() : "";
  const kode = inputKode ? inputKode.value.trim() : "";

  if (!nama) {
    if (alertEl) {
      alertEl.textContent = "Nama ruangan tidak boleh kosong.";
      alertEl.classList.remove("d-none");
    }
    if (inputNama) inputNama.focus();
    return;
  }

  if (alertEl) alertEl.classList.add("d-none");
  if (btn) btn.disabled = true;

  const csrfInput = document.querySelector(\'input[name="_csrf"]\');
  const csrfToken = csrfInput ? csrfInput.value : "";

  const formData = new FormData();
  formData.append("_csrf", csrfToken);
  formData.append("nama_ruangan", nama);
  formData.append("kode_ruangan", kode);

  fetch("ruangan_ajax_add", {
    method: "POST",
    body: formData
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (btn) btn.disabled = false;
    if (!data.success) {
      if (alertEl) {
        alertEl.textContent = data.error || "Gagal menambahkan ruangan baru.";
        alertEl.classList.remove("d-none");
      }
      return;
    }

    const newVal = data.value || (data.kode + " : " + data.nama);
    const selAdd = document.getElementById("addCardRuangan");
    const selEdit = document.getElementById("editCardRuangan");

    [selAdd, selEdit].forEach(function(sel) {
      if (sel) {
        const opt = document.createElement("option");
        opt.value = newVal;
        opt.textContent = newVal;
        sel.appendChild(opt);
        sel.value = newVal;
      }
    });

    const modalEl = document.getElementById("modalQuickAddRuanganDashboard");
    if (modalEl && typeof bootstrap !== "undefined") {
      const bsModal = bootstrap.Modal.getInstance(modalEl);
      if (bsModal) bsModal.hide();
    }
  })
  .catch(function(err) {
    if (btn) btn.disabled = false;
    if (alertEl) {
      alertEl.textContent = "Terjadi kesalahan jaringan atau server.";
      alertEl.classList.remove("d-none");
    }
  });
}

function openCardEditModal(card) {
  document.getElementById("editCardId").value = card.id || "";
  document.getElementById("editCardRekening").value = card.nomor_rekening || "";
  document.getElementById("editCardNama").value = card.nama_barang || "";
  document.getElementById("editCardNoSeri").value = card.nomor_seri_inventaris || "";

  if (card.jenis_inventaris) {
    document.getElementById("editCardJenis").value = card.jenis_inventaris;
  }
  if (card.golongan) {
    document.getElementById("editCardGolongan").value = card.golongan;
  }
  if (card.ruangan) {
    const selR = document.getElementById("editCardRuangan");
    if (selR) {
      let matched = false;
      for (let i = 0; i < selR.options.length; i++) {
        if (selR.options[i].value.toLowerCase() === card.ruangan.toLowerCase()) {
          selR.selectedIndex = i;
          matched = true;
          break;
        }
      }
      if (!matched) {
        const opt = document.createElement("option");
        opt.value = card.ruangan;
        opt.textContent = card.ruangan;
        selR.appendChild(opt);
        selR.value = card.ruangan;
      }
    }
  }
  if (card.sumber_perolehan) {
    document.getElementById("editCardSumber").value = card.sumber_perolehan;
  }
  if (card.status_inventaris) {
    document.getElementById("editCardStatusInv").value = card.status_inventaris;
  }

  let rawDate = (card.tanggal_perolehan || "").trim();
  let formattedDate = rawDate;
  if (rawDate.includes("-")) {
    const parts = rawDate.split("-");
    if (parts.length === 3) {
      formattedDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
  }
  document.getElementById("editCardTanggal").value = formattedDate;
  document.getElementById("editCardLokasi").value = card.lokasi || "";
  document.getElementById("editCardBarcode").value = card.barcode_data || "";
  document.getElementById("editCardKeterangan").value = card.keterangan || "";

  const modal = new bootstrap.Modal(document.getElementById("editCardModal"));
  modal.show();
}

function buildCardDataForPng(card) {
  const rek = card.nomor_rekening || "";
  const tglRaw = (card.tanggal_perolehan || "").trim();
  let tglDisplay = "-";
  if (tglRaw) {
    if (tglRaw.includes("/")) {
      tglDisplay = tglRaw;
    } else if (tglRaw.includes("-")) {
      const parts = tglRaw.split("-");
      if (parts.length === 3) {
        tglDisplay = `${parts[2]}/${parts[1]}/${parts[0]}`;
      } else {
        tglDisplay = tglRaw;
      }
    } else {
      tglDisplay = tglRaw;
    }
  }
  const qrTarget = card.id ? (window.location.origin + "/kartu_detail.php?id=" + card.id) : (card.barcode_data || window.location.origin);
  return {
    id: card.id || 0,
    kode: rek,
    nomor_asset: card.gabungan || rek,
    nama: card.nama_barang || "-",
    tgl: tglDisplay,
    lokasi: card.ruangan || card.lokasi || "KPO",
    qr_url: qrTarget
  };
}

function downloadCardPngFromRow(card, btnEl) {
  const payload = buildCardDataForPng(card);
  if (typeof window.downloadOrSharePhysicalCard === "function") {
    window.downloadOrSharePhysicalCard(payload, "download", btnEl);
  }
}

function toggleCardPrintStatus(cardId, btnEl) {
  if (!cardId) return;
  const curPrinted = btnEl && btnEl.getAttribute("data-printed") === "1";
  const nextStatus = curPrinted ? "unprinted" : "printed";
  const csrfInput = document.querySelector("input[name=\\"_csrf\\"]");
  const csrfToken = csrfInput ? csrfInput.value : "";

  const fd = new FormData();
  fd.append("ajax_action", "toggle_print_status");
  fd.append("id", String(cardId));
  fd.append("status", nextStatus);
  fd.append("_csrf", csrfToken);

  fetch("print_inventory_card.php", { method: "POST", body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.success) return;
      const badges = [
        document.getElementById("print-badge-" + cardId),
        document.getElementById("print-badge-mob-" + cardId)
      ];
      badges.forEach(function(b) {
        if (!b) return;
        if (res.is_printed) {
          b.setAttribute("data-printed", "1");
          b.className = "px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0] hover:bg-[#D1FAE5] transition-colors inline-flex items-center gap-1 cursor-pointer";
          b.title = "Sudah dicetak pada " + (res.printed_label || "") + " — Klik untuk ubah ke Belum Dicetak";
          b.innerHTML = "<i class=\\"bi bi-check-circle-fill\\"></i><span>Sudah Dicetak</span>";
        } else {
          b.setAttribute("data-printed", "0");
          b.className = "px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A] hover:bg-[#FEF3C7] transition-colors inline-flex items-center gap-1 cursor-pointer";
          b.title = "Belum pernah dicetak — Klik untuk tandai Sudah Dicetak";
          b.innerHTML = "<i class=\\"bi bi-hourglass-split\\"></i><span>Belum Dicetak</span>";
        }
      });
    })
    .catch(function() {});
}

function bulkMarkPrintedCards() {
  const ids = getSelectedCardIds();
  if (ids.length === 0) return;
  const csrfInput = document.querySelector("input[name=\\"_csrf\\"]");
  const csrfToken = csrfInput ? csrfInput.value : "";
  const fd = new FormData();
  fd.append("ajax_action", "mark_printed");
  fd.append("ids", ids.join(","));
  fd.append("_csrf", csrfToken);
  fetch("print_inventory_card.php", { method: "POST", body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.success) {
        window.location.reload();
      }
    });
}

function openCardPreviewModal(card) {
  const payload = buildCardDataForPng(card);
  window.CURRENT_PREVIEW_CARD = payload;

  document.getElementById("pvNomorAsset").innerText = payload.nomor_asset;
  document.getElementById("pvNamaAsset").innerText = payload.nama;
  document.getElementById("pvTglPerolehan").innerText = payload.tgl;
  document.getElementById("pvLokasi").innerText = payload.lokasi;

  const qrBox = document.getElementById("pvQrBox");
  qrBox.innerHTML = "";
  new QRCode(qrBox, {
    text: payload.qr_url,
    width: 70,
    height: 70,
    colorDark: "#000000",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M
  });

  const printBtn = document.getElementById("pvPrintDirectBtn");
  if (printBtn) {
    printBtn.href = "print_inventory_card.php?source=inventaris_kartu&id=" + (card.id || "");
  }

  const modal = new bootstrap.Modal(document.getElementById("previewCardModal"));
  modal.show();
}

function openProsesPerbaikanModal(data) {
  document.getElementById("modalFindingId").value = data.finding_id || 0;
  document.getElementById("modalScanId").value = data.scan_id || 0;
  document.getElementById("modalAssetId").value = data.asset_id || 0;
  document.getElementById("modalKodePerangkat").textContent = data.kode || "-";
  document.getElementById("modalNamaPerangkat").textContent = (data.perangkat || "-") + " (" + (data.pengguna || "-") + ")";
  document.getElementById("modalDeskripsiTemuan").textContent = data.finding || "-";
  
  var statusSel = document.getElementById("modalStatusSelect");
  var st = (data.status || "").toLowerCase();
  if (st === "resolved" || st === "selesai") {
    statusSel.value = "Resolved";
  } else if (st === "in progress" || st === "proses" || st === "dalam proses") {
    statusSel.value = "In Progress";
  } else {
    statusSel.value = "In Progress";
  }

  document.getElementById("modalProsesTextarea").value = data.proses_perbaikan || "";
  if (data.teknisi) {
    document.getElementById("modalTeknisiInput").value = data.teknisi;
  }

  var modalEl = document.getElementById("modalProsesPerbaikan");
  if (modalEl) {
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}

function appendModalAction(text) {
  var ta = document.getElementById("modalProsesTextarea");
  if (!ta) return;
  if (ta.value.trim() !== "") {
    ta.value += "\n" + text;
  } else {
    ta.value = text;
  }
}

document.addEventListener("DOMContentLoaded", function() {
  const addModalEl = document.getElementById("addCardModal");
  if (addModalEl) {
    addModalEl.addEventListener("shown.bs.modal", function() {
      const addNoSeri = document.getElementById("addCardNoSeri");
      if (addNoSeri && !addNoSeri.value.trim()) {
        autoGenerateCardNoSeri("add");
      }
      const addLok = document.getElementById("addCardLokasi");
      if (addLok && !addLok.value.trim()) {
        addLok.value = "Kantor Pusat (KPO)";
      }
    });
  }
  ["addCardRuangan", "addCardGolongan", "addCardTanggal"].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener("change", function() { autoGenerateCardNoSeri("add"); });
  });

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("add") === "1" || window.location.hash === "#addCardModal") {
    const addCardModalEl = document.getElementById("addCardModal");
    if (addCardModalEl) {
      if (urlParams.get("from_memo")) {
        const namaInput = addCardModalEl.querySelector("input[name=\"nama_barang\"]");
        if (namaInput && urlParams.get("nama")) {
          namaInput.value = urlParams.get("nama");
        }

        const tglInput = document.getElementById("addCardTanggal");
        if (tglInput && urlParams.get("tgl")) {
          const raw = urlParams.get("tgl");
          if (raw.includes("-")) {
            const p = raw.split("-");
            if (p.length === 3) tglInput.value = `${p[2]}/${p[1]}/${p[0]}`;
          } else {
            tglInput.value = raw;
          }
        }

        const lokasiInput = document.getElementById("addCardLokasi");
        if (lokasiInput && urlParams.get("cabang")) {
          lokasiInput.value = urlParams.get("cabang");
        }

        const ketInput = addCardModalEl.querySelector("textarea[name=\"keterangan\"]");
        if (ketInput && urlParams.get("ket")) {
          let extra = urlParams.get("ket");
          if (urlParams.get("harga")) {
            const val = parseInt(urlParams.get("harga"), 10);
            if (!isNaN(val) && val > 0) {
              extra += " · Biaya: Rp. " + val.toLocaleString("id-ID");
            }
          }
          ketInput.value = extra;
        }

        // Auto generate no seri inventaris
        if (typeof autoGenerateCardNoSeri === "function") {
          setTimeout(function() {
            autoGenerateCardNoSeri("add");
          }, 300);
        }
      }

      const modal = bootstrap.Modal.getOrCreateInstance(addCardModalEl);
      modal.show();
    }
  }
});
</script>
<script>
' . get_card_png_exporter_js() . '
</script>
';

if (!$isHrdOnly) {
    $extraScript .= '
<script>
window.REALTIME_CONFIG = {
  month: ' . (int)$month . ',
  year: ' . (int)$year . ',
  cabang: ' . (int)$cabangId . ',
  wsPort: ' . (int)(getenv('WS_PORT') ?: 8080) . ',
  initialEventId: ' . (int)(microtime(true) * 1000) . '
};
</script>
<script src="realtime_dashboard.js"></script>';
}
