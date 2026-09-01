/**
 * GOOGLE APPS SCRIPT FOR QR MAINTENANCE MODULE
 * 
 * CARA PENGGUNAAN:
 * 1. Buka Google Sheets Anda.
 * 2. Klik menu Extensi -> Apps Script.
 * 3. Hapus semua kode default, lalu Paste seluruh kode ini.
 * 4. Klik "Deploy" -> "New deployment".
 * 5. Pilih Select type -> "Web app".
 * 6. Set Description: "QR Maintenance API"
 * 7. Set Execute as: "Me" (Email Anda)
 * 8. Set Who has access: "Anyone" (Siapa saja, agar PHP / Vercel dapat mengakses API ini)
 * 9. Klik "Deploy", beri izin otorisasi (Authorize Access).
 * 10. Salin "Web App URL" (URL berakhiran /exec) dan simpan ke environment variable SPREADSHEET_API_URL di Vercel.
 */

function setupSheets() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  
  var sheetsDef = {
    "Cabang": ["id", "nama_cabang"],
    "Divisi": ["id", "nama_divisi"],
    "Karyawan": ["id", "nama_karyawan", "id_cabang", "id_divisi"],
    "Kategori_Aset": ["id", "nama_kategori"],
    "Assets": ["id", "kode_inventaris", "merk", "model", "serial_number", "id_kategori", "id_cabang", "id_divisi", "id_karyawan", "status", "keterangan"],
    "Asset_QR_Tokens": ["id", "asset_id", "token", "placement_label", "is_active", "created_at"],
    "Maintenance_Scan": ["id", "asset_id", "technician_user_id", "technician_name", "maintenance_date", "maintenance_time", "maintenance_month", "maintenance_year", "status", "source", "created_at"],
    "Maintenance_Findings": ["id", "maintenance_scan_id", "asset_id", "kategori_temuan", "deskripsi_temuan", "tindakan_diperlukan", "status", "reported_by", "reported_at", "resolved_by", "resolved_at", "catatan_penyelesaian"]
  };

  for (var sheetName in sheetsDef) {
    var sheet = ss.getSheetByName(sheetName);
    if (!sheet) {
      sheet = ss.insertSheet(sheetName);
      sheet.appendRow(sheetsDef[sheetName]);
      sheet.getRange(1, 1, 1, sheetsDef[sheetName].length).setFontWeight("bold").setBackground("#e9ecef");
    }
  }

  // Isi data contoh jika Cabang & Assets masih kosong
  var cabangSheet = ss.getSheetByName("Cabang");
  if (cabangSheet.getLastRow() <= 1) {
    cabangSheet.appendRow([1, "Head Office"]);
    cabangSheet.appendRow([2, "Cabang Jakarta"]);
    cabangSheet.appendRow([3, "Cabang Surabaya"]);
  }

  var divSheet = ss.getSheetByName("Divisi");
  if (divSheet.getLastRow() <= 1) {
    divSheet.appendRow([1, "IT / MIS"]);
    divSheet.appendRow([2, "Operasional"]);
    divSheet.appendRow([3, "Finance"]);
  }

  var katSheet = ss.getSheetByName("Kategori_Aset");
  if (katSheet.getLastRow() <= 1) {
    katSheet.appendRow([1, "Laptop"]);
    katSheet.appendRow([2, "PC Desktop"]);
    katSheet.appendRow([3, "Printer"]);
  }

  var karSheet = ss.getSheetByName("Karyawan");
  if (karSheet.getLastRow() <= 1) {
    karSheet.appendRow([1, "Ahmad Staff", 1, 1]);
    karSheet.appendRow([2, "Budi Teknisi", 1, 1]);
  }

  var assetSheet = ss.getSheetByName("Assets");
  if (assetSheet.getLastRow() <= 1) {
    assetSheet.appendRow([1, "INV-IT-001", "Lenovo", "ThinkPad T14", "SN123456", 1, 1, 1, 1, "Aktif", "Laptop Utama"]);
    assetSheet.appendRow([2, "INV-IT-002", "Dell", "OptiPlex 3080", "SN654321", 2, 1, 2, 2, "Aktif", "PC Operasional"]);
  }

  var qrSheet = ss.getSheetByName("Asset_QR_Tokens");
  if (qrSheet.getLastRow() <= 1) {
    qrSheet.appendRow([1, 1, "a1b2c3d4e5f678901234567890abcdef", "Bodi Atas", 1, new Date().toISOString()]);
    qrSheet.appendRow([2, 2, "b2c3d4e5f678901234567890abcdef1a", "Samping Kanan", 1, new Date().toISOString()]);
  }
}

function getSheetData(sheetName) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(sheetName);
  if (!sheet) return [];
  var rows = sheet.getDataRange().getValues();
  if (rows.length <= 1) return [];
  var headers = rows[0];
  var result = [];
  for (var i = 1; i < rows.length; i++) {
    var row = rows[i];
    var obj = {};
    for (var j = 0; j < headers.length; j++) {
      obj[headers[j]] = row[j];
    }
    result.push(obj);
  }
  return result;
}

function doGet(e) {
  setupSheets();
  var params = e ? e.parameter : {};
  var action = params.action || "getDashboardData";

  try {
    if (action === "getDashboardData") {
      return jsonResponse(handleGetDashboardData(params));
    } else if (action === "getAssetByToken") {
      return jsonResponse(handleGetAssetByToken(params.token));
    } else if (action === "getHistory") {
      return jsonResponse(handleGetHistory(params));
    } else if (action === "getFindings") {
      return jsonResponse(handleGetFindings(params));
    } else if (action === "getQrTokens") {
      return jsonResponse(handleGetQrTokens(params));
    } else if (action === "getCabangList") {
      return jsonResponse({ success: true, data: getSheetData("Cabang") });
    } else {
      return jsonResponse({ success: false, error: "Action tidak dikenal: " + action });
    }
  } catch (err) {
    return jsonResponse({ success: false, error: err.toString() });
  }
}

function doPost(e) {
  setupSheets();
  try {
    var contents = e.postData ? e.postData.contents : "";
    var body = {};
    if (contents) {
      try { body = JSON.parse(contents); } catch(err) { body = e.parameter; }
    } else {
      body = e.parameter;
    }

    var action = body.action || "";
    if (action === "saveMaintenanceScan") {
      return jsonResponse(handleSaveMaintenanceScan(body));
    } else if (action === "saveFinding") {
      return jsonResponse(handleSaveFinding(body));
    } else if (action === "updateFinding") {
      return jsonResponse(handleUpdateFinding(body));
    } else {
      return jsonResponse({ success: false, error: "Action POST tidak dikenal: " + action });
    }
  } catch (err) {
    return jsonResponse({ success: false, error: err.toString() });
  }
}

function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

function mapAssets() {
  var assets = getSheetData("Assets");
  var cabangMap = {}; getSheetData("Cabang").forEach(function(r){ cabangMap[r.id] = r.nama_cabang; });
  var divMap = {}; getSheetData("Divisi").forEach(function(r){ divMap[r.id] = r.nama_divisi; });
  var karMap = {}; getSheetData("Karyawan").forEach(function(r){ karMap[r.id] = r.nama_karyawan; });
  var katMap = {}; getSheetData("Kategori_Aset").forEach(function(r){ katMap[r.id] = r.nama_kategori; });
  var qrMap = {}; getSheetData("Asset_QR_Tokens").forEach(function(r){ qrMap[r.asset_id] = r; });

  return assets.map(function(a) {
    var qr = qrMap[a.id] || {};
    return {
      id: Number(a.id),
      kode_inventaris: a.kode_inventaris,
      merk: a.merk,
      model: a.model,
      serial_number: a.serial_number,
      id_kategori: Number(a.id_kategori),
      id_cabang: Number(a.id_cabang),
      id_divisi: Number(a.id_divisi),
      id_karyawan: Number(a.id_karyawan),
      status: a.status || "Aktif",
      keterangan: a.keterangan || "",
      cabang_nama: cabangMap[a.id_cabang] || "-",
      divisi_nama: divMap[a.id_divisi] || "-",
      karyawan_nama: karMap[a.id_karyawan] || "-",
      kategori_nama: katMap[a.id_kategori] || "-",
      qr_token: qr.token || "",
      placement_label: qr.placement_label || "",
      qr_active: qr.is_active ? 1 : 0
    };
  });
}

function handleGetDashboardData(params) {
  var month = Number(params.bulan || (new Date().getMonth() + 1));
  var year = Number(params.tahun || new Date().getFullYear());
  var cabangId = Number(params.cabang || 0);

  var assets = mapAssets().filter(function(a) {
    var st = (a.status || "").toLowerCase();
    var active = (st === "aktif" || st === "");
    if (!active) return false;
    if (cabangId > 0 && a.id_cabang !== cabangId) return false;
    return true;
  });

  var scans = getSheetData("Maintenance_Scan").filter(function(s) {
    return Number(s.maintenance_month) === month && Number(s.maintenance_year) === year;
  });

  var scannedAssetIds = {};
  var findingAssetIds = {};
  scans.forEach(function(s) {
    scannedAssetIds[s.asset_id] = true;
    if (s.status === "Temuan") {
      findingAssetIds[s.asset_id] = true;
    }
  });

  var total = assets.length;
  var doneCount = 0;
  var findingsCount = 0;
  var pendingRows = [];

  assets.forEach(function(a) {
    if (scannedAssetIds[a.id]) {
      doneCount++;
      if (findingAssetIds[a.id]) findingsCount++;
    } else {
      pendingRows.push(a);
    }
  });

  var recentScans = getSheetData("Maintenance_Scan").filter(function(s) {
    if (Number(s.maintenance_month) !== month || Number(s.maintenance_year) !== year) return false;
    return true;
  });

  var assetMap = {};
  mapAssets().forEach(function(a){ assetMap[a.id] = a; });

  var recentRows = recentScans.map(function(s) {
    var a = assetMap[s.asset_id] || {};
    if (cabangId > 0 && a.id_cabang !== cabangId) return null;
    return {
      id: s.id,
      asset_id: s.asset_id,
      maintenance_date: String(s.maintenance_date).substring(0, 10),
      maintenance_time: String(s.maintenance_time).substring(0, 8),
      status: s.status,
      kode_inventaris: a.kode_inventaris || "-",
      merk: a.merk || "",
      model: a.model || "",
      karyawan_nama: a.karyawan_nama || "-",
      cabang_nama: a.cabang_nama || "-"
    };
  }).filter(function(r){ return r !== null; });

  return {
    success: true,
    total: total,
    done: doneCount,
    findings: findingsCount,
    pendingRows: pendingRows,
    recentRows: recentRows,
    cabangs: getSheetData("Cabang")
  };
}

function handleGetAssetByToken(token) {
  if (!token) return { success: false, error: "Token tidak diberikan" };
  var qrs = getSheetData("Asset_QR_Tokens");
  var targetQr = null;
  for (var i = 0; i < qrs.length; i++) {
    if (String(qrs[i].token) === String(token) && Number(qrs[i].is_active) === 1) {
      targetQr = qrs[i];
      break;
    }
  }

  if (!targetQr) return { success: false, error: "QR token tidak ditemukan" };

  var assets = mapAssets();
  var found = null;
  for (var j = 0; j < assets.length; j++) {
    if (Number(assets[j].id) === Number(targetQr.asset_id)) {
      found = assets[j];
      break;
    }
  }

  return { success: true, asset: found };
}

function handleSaveMaintenanceScan(body) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var scanSheet = ss.getSheetByName("Maintenance_Scan");
  var assetId = Number(body.asset_id);
  var month = Number(body.maintenance_month);
  var year = Number(body.maintenance_year);

  // Check if existing scan exists
  var scans = getSheetData("Maintenance_Scan");
  for (var i = 0; i < scans.length; i++) {
    if (Number(scans[i].asset_id) === assetId && Number(scans[i].maintenance_month) === month && Number(scans[i].maintenance_year) === year) {
      return { success: false, is_duplicate: true, existing: scans[i] };
    }
  }

  var newId = scanSheet.getLastRow();
  var dateStr = body.maintenance_date || new Date().toISOString().substring(0, 10);
  var timeStr = body.maintenance_time || new Date().toTimeString().substring(0, 8);

  scanSheet.appendRow([
    newId,
    assetId,
    Number(body.technician_user_id || 1),
    body.technician_name || "Teknisi",
    dateStr,
    timeStr,
    month,
    year,
    body.status || "Selesai",
    body.source || "QR",
    new Date().toISOString()
  ]);

  return { success: true, log_id: newId };
}

function handleSaveFinding(body) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var findSheet = ss.getSheetByName("Maintenance_Findings");
  var newId = findSheet.getLastRow();

  findSheet.appendRow([
    newId,
    Number(body.maintenance_scan_id || 0),
    Number(body.asset_id),
    body.kategori_temuan || "Kerusakan",
    body.deskripsi_temuan || "",
    body.tindakan_diperlukan || "",
    "Open",
    body.reported_by || "Teknisi",
    new Date().toISOString(),
    "",
    "",
    ""
  ]);

  // Update status in Maintenance_Scan sheet to 'Temuan'
  var scanSheet = ss.getSheetByName("Maintenance_Scan");
  var scanData = scanSheet.getDataRange().getValues();
  for (var i = 1; i < scanData.length; i++) {
    if (Number(scanData[i][0]) === Number(body.maintenance_scan_id)) {
      scanSheet.getRange(i + 1, 9).setValue("Temuan");
      break;
    }
  }

  return { success: true, finding_id: newId };
}

function handleGetHistory(params) {
  var month = Number(params.bulan || 0);
  var year = Number(params.tahun || 0);
  var cabangId = Number(params.cabang || 0);
  var status = params.status || "";

  var scans = getSheetData("Maintenance_Scan");
  var assetMap = {};
  mapAssets().forEach(function(a){ assetMap[a.id] = a; });

  var rows = scans.map(function(s) {
    if (month > 0 && Number(s.maintenance_month) !== month) return null;
    if (year > 0 && Number(s.maintenance_year) !== year) return null;
    if (status !== "" && s.status !== status) return null;

    var a = assetMap[s.asset_id] || {};
    if (cabangId > 0 && a.id_cabang !== cabangId) return null;

    return {
      id: s.id,
      asset_id: s.asset_id,
      maintenance_date: String(s.maintenance_date).substring(0, 10),
      maintenance_time: String(s.maintenance_time).substring(0, 8),
      status: s.status,
      technician_name: s.technician_name || "Teknisi",
      kode_inventaris: a.kode_inventaris || "-",
      merk: a.merk || "",
      model: a.model || "",
      kategori_nama: a.kategori_nama || "-",
      karyawan_nama: a.karyawan_nama || "-",
      cabang_nama: a.cabang_nama || "-"
    };
  }).filter(function(r){ return r !== null; });

  return { success: true, rows: rows };
}

function handleGetFindings(params) {
  var status = params.status || "";
  var cabangId = Number(params.cabang || 0);

  var findings = getSheetData("Maintenance_Findings");
  var assetMap = {};
  mapAssets().forEach(function(a){ assetMap[a.id] = a; });

  var rows = findings.map(function(f) {
    if (status !== "" && f.status !== status) return null;
    var a = assetMap[f.asset_id] || {};
    if (cabangId > 0 && a.id_cabang !== cabangId) return null;

    return {
      id: f.id,
      maintenance_scan_id: f.maintenance_scan_id,
      asset_id: f.asset_id,
      kategori_temuan: f.kategori_temuan,
      deskripsi_temuan: f.deskripsi_temuan,
      tindakan_diperlukan: f.tindakan_diperlukan,
      status: f.status,
      reported_by: f.reported_by,
      reported_at: String(f.reported_at).substring(0, 19),
      resolved_by: f.resolved_by,
      resolved_at: f.resolved_at ? String(f.resolved_at).substring(0, 19) : "",
      catatan_penyelesaian: f.catatan_penyelesaian,
      kode_inventaris: a.kode_inventaris || "-",
      merk: a.merk || "",
      model: a.model || "",
      karyawan_nama: a.karyawan_nama || "-",
      cabang_nama: a.cabang_nama || "-"
    };
  }).filter(function(r){ return r !== null; });

  return { success: true, rows: rows };
}

function handleUpdateFinding(body) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var findSheet = ss.getSheetByName("Maintenance_Findings");
  var findData = findSheet.getDataRange().getValues();
  var findId = Number(body.finding_id);

  for (var i = 1; i < findData.length; i++) {
    if (Number(findData[i][0]) === findId) {
      findSheet.getRange(i + 1, 7).setValue(body.status || "Resolved"); // status
      findSheet.getRange(i + 1, 10).setValue(body.resolved_by || "Admin"); // resolved_by
      findSheet.getRange(i + 1, 11).setValue(new Date().toISOString()); // resolved_at
      findSheet.getRange(i + 1, 12).setValue(body.catatan_penyelesaian || ""); // catatan
      return { success: true };
    }
  }
  return { success: false, error: "Finding ID tidak ditemukan" };
}

function handleGetQrTokens(params) {
  var assets = mapAssets();
  var search = (params.search || "").toLowerCase();

  var rows = assets.filter(function(a) {
    if (!a.qr_token) return false;
    if (search) {
      var match = (a.kode_inventaris || "").toLowerCase().indexOf(search) >= 0 ||
                  (a.merk || "").toLowerCase().indexOf(search) >= 0 ||
                  (a.model || "").toLowerCase().indexOf(search) >= 0 ||
                  (a.karyawan_nama || "").toLowerCase().indexOf(search) >= 0;
      if (!match) return false;
    }
    return true;
  });

  return { success: true, rows: rows };
}
