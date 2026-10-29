// SMART OCR & CORE BANKING SCREEN SCANNER
// =========================================================================
// SMART OCR & CORE BANKING SCREEN SCANNER
// =========================================================================
function toggleOcrTextInput() {
  var area = document.getElementById("ocrTextInputArea");
  if (area) {
    if (area.classList.contains("d-none")) {
      area.classList.remove("d-none");
      var txt = document.getElementById("ocrManualText");
      if (txt) txt.focus();
    } else {
      area.classList.add("d-none");
    }
  }
}

function resetOcrScanner(e) {
  if (e) e.stopPropagation();
  var promptEl = document.getElementById("ocrPrompt");
  var previewContainer = document.getElementById("ocrImagePreviewContainer");
  var previewImg = document.getElementById("ocrImagePreview");
  var fileInput = document.getElementById("ocrFileInput");
  var successMsg = document.getElementById("ocrSuccessMsg");
  var loadingEl = document.getElementById("ocrLoading");

  if (previewImg) previewImg.src = "";
  if (previewContainer) {
    previewContainer.classList.add("d-none");
    previewContainer.classList.remove("d-flex");
  }
  if (promptEl) promptEl.classList.remove("d-none");
  if (fileInput) fileInput.value = "";
  if (successMsg) successMsg.classList.add("d-none");
  if (loadingEl) loadingEl.classList.add("d-none");
}

function parseCoreBankingText(text) {
  var data = {};
  var count = 0;
  if (!text) return { data: data, count: count };

  // 1. Nomor Rekening
  var mRek = text.match(/Nomor\s*Rekening[^\d]*([0-9]{2}[\.\s][0-9]{1,2}[\.\s][0-9]{4,5})/i) ||
             text.match(/\b([0-9]{2}\.[0-9]{1,2}\.[0-9]{4,5})\b/) ||
             text.match(/\b([0-9]{2}\.[0-9]{2}\.[0-9]{4})\b/);
  if (mRek) {
    data.rekening = mRek[1].replace(/\s+/g, ".");
    count++;
  }

  // 2. Nomor Seri Inventaris (format: 01.00.03.300926.001)
  var mSeri = text.match(/Nomor\s*Seri[^\d]*([0-9]{2}[\.][0-9]{2}[\.][0-9]{2}[\.][0-9]{6}[\.][0-9]{3})/i) ||
              text.match(/\b([0-9]{2}\.[0-9]{2}\.[0-9]{2}\.[0-9]{6}\.[0-9]{3})\b/);
  if (mSeri) {
    data.no_seri = mSeri[1];
    count++;
  }

  // 3. Nama Barang
  var mNama = text.match(/Nama\s*Barang[\s:]*([^\r\n]+)/i);
  if (mNama) {
    var rawNama = mNama[1].trim();
    rawNama = rawNama.replace(/^(MOBIL|MOTOR|LAPTOP|PC|PRINTER|MEJA|KURSI)\s+/i, function(match) { return match.toUpperCase(); });
    data.nama_barang = rawNama;
    count++;
  }

  // 4. Tanggal Perolehan (dd/mm/yyyy)
  var mDate = text.match(/Tanggal\s*Perolehan[^\d]*([0-9]{1,2}[\/\-\SingleDot][0-9]{1,2}[\/\-\SingleDot][0-9]{4})/i) ||
              text.match(/Tanggal\s*Perolehan[^\d]*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{4})/i) ||
              text.match(/\b([0-9]{2}\/[0-9]{2}\/[0-9]{4})\b/);
  if (mDate) {
    data.tanggal = mDate[1].replace(/[\-\.]/g, "/");
    count++;
  }

  // 5. Jenis Inventaris
  var mJenis = text.match(/Jenis\s*Inventaris[^\r\n]*/i);
  var jenisLine = mJenis ? mJenis[0].toUpperCase() : text.toUpperCase();
  var jenisList = [
    { code: "1", name: "TANAH", full: "1 : TANAH" },
    { code: "2", name: "BANGUNAN", full: "2 : BANGUNAN" },
    { code: "3", name: "PERALATAN KANTOR", full: "3 : PERALATAN KANTOR" },
    { code: "4", name: "KENDARAAN", full: "4 : KENDARAAN" },
    { code: "5", name: "MESIN", full: "5 : MESIN" },
    { code: "6", name: "BERWUJUD LAINNYA", full: "6 : BERWUJUD LAINNYA" },
    { code: "7", name: "PROGRAM KOMPUTER", full: "7 : PROGRAM KOMPUTER" }
  ];
  for (var i = 0; i < jenisList.length; i++) {
    var j = jenisList[i];
    if (jenisLine.indexOf(j.name) !== -1 || new RegExp("\\b" + j.code + "\\b").test(jenisLine)) {
      data.jenis = j.full;
      count++;
      break;
    }
  }

  // 6. Golongan
  var mGol = text.match(/Golongan[^\r\n]*/i);
  var golLine = mGol ? mGol[0].toUpperCase() : text.toUpperCase();
  var golList = [
    { code: "01", name: "TANAH & GEDUNG", full: "01 : TANAH & GEDUNG" },
    { code: "04", name: "GOLONGAN III", full: "04 : GOLONGAN III" },
    { code: "03", name: "GOLONGAN II", full: "03 : GOLONGAN II" },
    { code: "02", name: "GOLONGAN I", full: "02 : GOLONGAN I" },
    { code: "05", name: "ASET TIDAK BERWUJUD", full: "05 : ASET TIDAK BERWUJUD" }
  ];
  for (var k = 0; k < golList.length; k++) {
    var g = golList[k];
    if (golLine.indexOf(g.name) !== -1 || new RegExp("\\b" + g.code + "\\b").test(golLine)) {
      data.golongan = g.full;
      count++;
      break;
    }
  }

  // 7. Sumber Perolehan
  var mSum = text.match(/Sumber\s*Perolehan[^\r\n]*/i);
  var sumLine = mSum ? mSum[0].toUpperCase() : text.toUpperCase();
  var sumList = [
    { code: "01", name: "MODAL AWAL", full: "01 : MODAL AWAL" },
    { code: "02", name: "MODAL DISETOR", full: "02 : MODAL DISETOR" },
    { code: "03", name: "PEMBELIAN INVENTARIS", full: "03 : PEMBELIAN INVENTARIS" },
    { code: "04", name: "HIBAH / LAINNYA", full: "04 : HIBAH / LAINNYA" }
  ];
  for (var s = 0; s < sumList.length; s++) {
    var sm = sumList[s];
    if (sumLine.indexOf(sm.name) !== -1 || new RegExp("\\b" + sm.code + "\\b").test(sumLine)) {
      data.sumber = sm.full;
      count++;
      break;
    }
  }

  // 8. Lokasi / Kantor Bank
  var mLok = text.match(/Kantor\s*Bank[^\r\n]*/i);
  if (mLok) {
    var lokVal = mLok[0].toUpperCase();
    if (lokVal.indexOf("PUSAT") !== -1 || lokVal.indexOf("KPO") !== -1) {
      data.lokasi = "Kantor Pusat (KPO)";
      count++;
    } else if (lokVal.indexOf("HANDIL") !== -1) {
      data.lokasi = "Handil Bakti";
      count++;
    } else if (lokVal.indexOf("MARABAHAN") !== -1) {
      data.lokasi = "Marabahan";
      count++;
    } else if (lokVal.indexOf("PASAR PANAS") !== -1) {
      data.lokasi = "Pasar Panas";
      count++;
    }
  }

  // 9. Keterangan & Nilai Perolehan
  var mKet = text.match(/Keterangan[\s:]*([^\r\n]+)/i);
  var mNilai = text.match(/Nilai\s*Perolehan[\s:]*([0-9\.\,]+)/i);
  var ketStr = "";
  if (mKet) {
    ketStr = mKet[1].trim();
  }
  if (mNilai && mNilai[1]) {
    var cleanNilai = mNilai[1].trim();
    var nilaiNote = " · Nilai: Rp " + cleanNilai;
    if (ketStr.indexOf("Nilai:") === -1) {
      ketStr = ketStr ? ketStr + nilaiNote : "Nilai: Rp " + cleanNilai;
    }
  }
  if (ketStr) {
    data.keterangan = ketStr;
    count++;
  }

  return { data: data, count: count };
}

function applyParsedData(result) {
  if (!result || !result.data) return;
  var d = result.data;
  var applied = 0;

  function flashInput(el) {
    if (!el) return;
    el.style.transition = "background-color 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease";
    el.style.backgroundColor = "#e8f5e9";
    el.style.borderColor = "#2e7d32";
    el.style.boxShadow = "0 0 0 0.2rem rgba(46, 125, 50, 0.25)";
    setTimeout(function() {
      el.style.backgroundColor = "";
      el.style.borderColor = "";
      el.style.boxShadow = "";
    }, 2500);
  }

  // Rekening
  if (d.rekening) {
    var elRek = document.getElementById("addCardRekening");
    if (elRek) {
      elRek.value = d.rekening;
      flashInput(elRek);
      applied++;
      if (typeof handleRekeningInput === "function") {
        handleRekeningInput(elRek, { type: "input" });
      }
    }
  }

  // Nama Barang
  if (d.nama_barang) {
    var elNama = document.getElementById("addCardNamaBarang") || document.querySelector('#addCardModal input[name="nama_barang"]');
    if (elNama) {
      elNama.value = d.nama_barang;
      flashInput(elNama);
      applied++;
    }
  }

  // No Seri
  if (d.no_seri) {
    var elSeri = document.getElementById("addCardNoSeri");
    if (elSeri) {
      elSeri.value = d.no_seri;
      flashInput(elSeri);
      applied++;
    }
  }

  // Tanggal
  if (d.tanggal) {
    var elTgl = document.getElementById("addCardTanggal");
    if (elTgl) {
      elTgl.value = d.tanggal;
      flashInput(elTgl);
      applied++;
      if (typeof handleDateInput === "function") {
        handleDateInput(elTgl, { type: "input" });
      }
    }
  }

  // Jenis
  if (d.jenis) {
    var elJenis = document.getElementById("addCardJenis");
    if (elJenis) {
      for (var i = 0; i < elJenis.options.length; i++) {
        if (elJenis.options[i].value === d.jenis || elJenis.options[i].text.indexOf(d.jenis) !== -1) {
          elJenis.selectedIndex = i;
          flashInput(elJenis);
          applied++;
          break;
        }
      }
    }
  }

  // Golongan
  if (d.golongan) {
    var elGol = document.getElementById("addCardGolongan");
    if (elGol) {
      for (var g = 0; g < elGol.options.length; g++) {
        if (elGol.options[g].value === d.golongan || elGol.options[g].text.indexOf(d.golongan) !== -1) {
          elGol.selectedIndex = g;
          flashInput(elGol);
          applied++;
          break;
        }
      }
    }
  }

  // Sumber Perolehan
  if (d.sumber) {
    var elSum = document.getElementById("addCardSumber");
    if (elSum) {
      for (var s = 0; s < elSum.options.length; s++) {
        if (elSum.options[s].value === d.sumber || elSum.options[s].text.indexOf(d.sumber) !== -1) {
          elSum.selectedIndex = s;
          flashInput(elSum);
          applied++;
          break;
        }
      }
    }
  }

  // Lokasi
  if (d.lokasi) {
    var elLok = document.getElementById("addCardLokasi");
    if (elLok) {
      elLok.value = d.lokasi;
      flashInput(elLok);
      applied++;
    }
  }

  // Keterangan
  if (d.keterangan) {
    var elKet = document.getElementById("addCardKeterangan") || document.querySelector('#addCardModal textarea[name="keterangan"]');
    if (elKet) {
      elKet.value = d.keterangan;
      flashInput(elKet);
      applied++;
    }
  }

  // Update UI Alert
  var successMsg = document.getElementById("ocrSuccessMsg");
  var successCount = document.getElementById("ocrSuccessCount");
  if (successMsg && successCount) {
    successCount.textContent = applied;
    successMsg.classList.remove("d-none");
  }
}

function parseManualText() {
  var txtEl = document.getElementById("ocrManualText");
  if (!txtEl || !txtEl.value.trim()) {
    alert("Silakan tempel teks formulir Core Banking terlebih dahulu.");
    return;
  }
  var parsed = parseCoreBankingText(txtEl.value);
  applyParsedData(parsed);
  toggleOcrTextInput();
}

function processOcrImage(fileOrBlob) {
  if (!fileOrBlob) return;

  var promptEl = document.getElementById("ocrPrompt");
  var previewContainer = document.getElementById("ocrImagePreviewContainer");
  var previewImg = document.getElementById("ocrImagePreview");
  var previewName = document.getElementById("ocrImageName");
  var loadingEl = document.getElementById("ocrLoading");
  var statusText = document.getElementById("ocrStatusText");
  var progressBar = document.getElementById("ocrProgressBar");
  var successMsg = document.getElementById("ocrSuccessMsg");

  if (successMsg) successMsg.classList.add("d-none");

  // Show thumbnail
  var reader = new FileReader();
  reader.onload = function(e) {
    if (previewImg) previewImg.src = e.target.result;
    if (previewContainer) {
      previewContainer.classList.remove("d-none");
      previewContainer.classList.add("d-flex");
    }
    if (promptEl) promptEl.classList.add("d-none");
  };
  reader.readAsDataURL(fileOrBlob);

  if (previewName) {
    previewName.textContent = fileOrBlob.name || "Screenshot-CoreBanking.png";
  }

  // Show loading
  if (loadingEl) loadingEl.classList.remove("d-none");
  if (statusText) statusText.textContent = "Menyiapkan mesin OCR...";
  if (progressBar) progressBar.style.width = "15%";

  function runRecognition() {
    if (statusText) statusText.textContent = "Menganalisis teks screenshot Core Banking...";
    if (progressBar) progressBar.style.width = "40%";

    Tesseract.recognize(fileOrBlob, "ind+eng", {
      logger: function(m) {
        if (m && m.status && statusText) {
          if (m.status === "recognizing text") {
            var pct = Math.round((m.progress || 0) * 100);
            statusText.textContent = "Membaca teks dari gambar (" + pct + "%)...";
            if (progressBar) progressBar.style.width = (40 + (pct * 0.55)) + "%";
          }
        }
      }
    }).then(function(res) {
      if (loadingEl) loadingEl.classList.add("d-none");
      if (progressBar) progressBar.style.width = "100%";
      var rawText = (res && res.data && res.data.text) ? res.data.text : "";
      var parsed = parseCoreBankingText(rawText);
      applyParsedData(parsed);
    }).catch(function(err) {
      console.warn("Retrying OCR with eng profile...", err);
      if (statusText) statusText.textContent = "Mencoba profil cadangan...";
      Tesseract.recognize(fileOrBlob, "eng").then(function(res2) {
        if (loadingEl) loadingEl.classList.add("d-none");
        var rawText2 = (res2 && res2.data && res2.data.text) ? res2.data.text : "";
        var parsed2 = parseCoreBankingText(rawText2);
        applyParsedData(parsed2);
      }).catch(function(err2) {
        if (loadingEl) loadingEl.classList.add("d-none");
        alert("Gagal membaca teks otomatis dari gambar ini. Silakan gunakan tombol 'Tempel Teks Manual'.");
      });
    });
  }

  if (window.Tesseract) {
    runRecognition();
  } else {
    if (statusText) statusText.textContent = "Memuat modul OCR Tesseract...";
    var s = document.createElement("script");
    s.src = "https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js";
    s.onload = function() {
      runRecognition();
    };
    s.onerror = function() {
      if (loadingEl) loadingEl.classList.add("d-none");
      alert("Tidak dapat memuat modul OCR. Pastikan internet terhubung atau gunakan tombol 'Tempel Teks Manual'.");
    };
    document.head.appendChild(s);
  }
}

// SETUP EVENT LISTENERS FOR OCR DROPZONE & PASTE
document.addEventListener("DOMContentLoaded", function() {
  var dropZone = document.getElementById("ocrDropZone");
  var fileInput = document.getElementById("ocrFileInput");
  var addCardModalEl = document.getElementById("addCardModal");

  if (dropZone && fileInput) {
    dropZone.addEventListener("click", function(e) {
      if (e.target.closest("#ocrImagePreviewContainer") || e.target.closest(".btn-close")) return;
      fileInput.click();
    });

    fileInput.addEventListener("change", function() {
      if (this.files && this.files[0]) {
        processOcrImage(this.files[0]);
      }
    });

    dropZone.addEventListener("dragover", function(e) {
      e.preventDefault();
      dropZone.style.borderColor = "#0d6efd";
      dropZone.style.backgroundColor = "rgba(13, 110, 253, 0.05)";
    });

    dropZone.addEventListener("dragleave", function(e) {
      e.preventDefault();
      dropZone.style.borderColor = "";
      dropZone.style.backgroundColor = "";
    });

    dropZone.addEventListener("drop", function(e) {
      e.preventDefault();
      dropZone.style.borderColor = "";
      dropZone.style.backgroundColor = "";
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        processOcrImage(e.dataTransfer.files[0]);
      }
    });
  }

  // GLOBAL CLIPBOARD PASTE (Ctrl + V) LISTENER
  document.addEventListener("paste", function(e) {
    if (!addCardModalEl || !addCardModalEl.classList.contains("show")) return;

    var activeEl = document.activeElement;
    var isInput = activeEl && (activeEl.tagName === "INPUT" || activeEl.tagName === "TEXTAREA");

    // A. Check if image pasted
    if (e.clipboardData && e.clipboardData.items) {
      var items = e.clipboardData.items;
      for (var i = 0; i < items.length; i++) {
        if (items[i].type.indexOf("image") !== -1) {
          e.preventDefault();
          var blob = items[i].getAsFile();
          if (blob) {
            processOcrImage(blob);
            return;
          }
        }
      }
    }

    // B. Check if text pasted and NOT in an input field
    if (!isInput && e.clipboardData) {
      var pastedText = e.clipboardData.getData("text");
      if (pastedText && (pastedText.indexOf("Rekening") !== -1 || pastedText.indexOf("Inventaris") !== -1 || pastedText.indexOf("Barang") !== -1)) {
        e.preventDefault();
        var parsed = parseCoreBankingText(pastedText);
        applyParsedData(parsed);
      }
    }
  });
});