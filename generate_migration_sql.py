import openpyxl
import datetime
import re

wb = openpyxl.load_workbook('scratch_data.xlsx', data_only=True)

def sql_val(v):
    if v is None:
        return 'NULL'
    if isinstance(v, (datetime.datetime, datetime.date)):
        return f"'{v.strftime('%Y-%m-%d %H:%M:%S')}'"
    if isinstance(v, datetime.time):
        return f"'{v.strftime('%H:%M:%S')}'"
    if isinstance(v, bool):
        return '1' if v else '0'
    if isinstance(v, (int, float)):
        if isinstance(v, float) and v.is_integer():
            return str(int(v))
        return str(v)
    s = str(v).strip()
    if s == '' or s.lower() == 'null':
        return 'NULL'
    escaped = s.replace('\\', '\\\\').replace("'", "''").replace('\r', '').replace('\n', '\\n')
    return f"'{escaped}'"

sql_lines = [
    "-- ============================================================",
    "-- FULL DATABASE MIGRATION FROM GOOGLE SPREADSHEETS (OCT 2026)",
    "-- REPLACING ALL PREVIOUS DATA",
    "-- ============================================================",
    "USE db_maintenance_qr;",
    "SET NAMES utf8mb4;",
    "SET FOREIGN_KEY_CHECKS = 0;",
    "",
]

# 1. CABANG
ws = wb['Cabang']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 1. CABANG")
sql_lines.append("TRUNCATE TABLE cabang;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        cid = int(float(r[0]))
        nama = sql_val(r[1])
        alamat = sql_val(r[2] if len(r) > 2 else None)
        telp = sql_val(r[3] if len(r) > 3 else None)
        pj = sql_val(r[4] if len(r) > 4 else None)
        sql_lines.append(f"INSERT INTO cabang (id, nama_cabang, alamat, telepon, penanggung_jawab) VALUES ({cid}, {nama}, {alamat}, {telp}, {pj});")
sql_lines.append("")

# 2. DIVISI
ws = wb['Divisi']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 2. DIVISI")
sql_lines.append("TRUNCATE TABLE divisi;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        did = int(float(r[0]))
        nama = sql_val(r[1])
        id_cabang = sql_val(r[2] if len(r) > 2 else 1)
        sql_lines.append(f"INSERT INTO divisi (id, nama_divisi, id_cabang) VALUES ({did}, {nama}, {id_cabang});")
sql_lines.append("")

# 3. KATEGORI_ASET
ws = wb['Kategori_Aset']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 3. KATEGORI_ASET")
sql_lines.append("TRUNCATE TABLE kategori_aset;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        kid = int(float(r[0]))
        nama = sql_val(r[1])
        sql_lines.append(f"INSERT INTO kategori_aset (id, nama_kategori) VALUES ({kid}, {nama});")
sql_lines.append("")

# 4. KARYAWAN
ws = wb['Karyawan']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 4. KARYAWAN")
sql_lines.append("TRUNCATE TABLE karyawan;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        kid = int(float(r[0]))
        nama = sql_val(r[1])
        id_cab = sql_val(r[2] if len(r) > 2 else 1)
        id_div = sql_val(r[3] if len(r) > 3 else 1)
        sql_lines.append(f"INSERT INTO karyawan (id, nama_karyawan, id_cabang, id_divisi) VALUES ({kid}, {nama}, {id_cab}, {id_div});")
sql_lines.append("")

# 5. ASSETS
ws = wb['Assets']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 5. ASSETS")
sql_lines.append("TRUNCATE TABLE assets;")
valid_asset_ids = set()
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        try:
            aid = int(float(r[0]))
        except ValueError:
            continue
        valid_asset_ids.add(aid)
        kode = sql_val(r[1])
        merk = sql_val(r[2])
        model = sql_val(r[3])
        sn = sql_val(r[4])
        id_kat = sql_val(r[5] if len(r) > 5 else 1)
        id_cab = sql_val(r[6] if len(r) > 6 else 1)
        id_div = sql_val(r[7] if len(r) > 7 else 1)
        id_kar = sql_val(r[8] if len(r) > 8 else None)
        status = sql_val(r[9] if len(r) > 9 else 'Aktif')
        ket = sql_val(r[10] if len(r) > 10 else None)
        
        # IP and Printer
        ip_raw = r[11] if len(r) > 11 else None
        if ip_raw is not None:
            ip_clean = str(ip_raw).strip().replace(',', '.')
        else:
            ip_clean = None
        ip = sql_val(ip_clean)
        
        printer = sql_val(r[12] if len(r) > 12 else None)
        created_at = sql_val(r[13] if len(r) > 13 and r[13] is not None else datetime.datetime.now())
        
        sql_lines.append(f"INSERT INTO assets (id, kode_inventaris, merk, model, serial_number, id_kategori, id_cabang, id_divisi, id_karyawan, status, keterangan, ip_address, printer, created_at) VALUES ({aid}, {kode}, {merk}, {model}, {sn}, {id_kat}, {id_cab}, {id_div}, {id_kar}, {status}, {ket}, {ip}, {printer}, {created_at});")
sql_lines.append("")

# 6. ASSET_QR_TOKENS
ws = wb['Asset_QR_Tokens']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 6. ASSET_QR_TOKENS")
sql_lines.append("TRUNCATE TABLE asset_qr_tokens;")
covered_assets = set()
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '' and r[1] is not None and str(r[1]).strip() != '':
        try:
            qid = int(float(r[0]))
            aid = int(float(r[1]))
        except ValueError:
            continue
        tok = sql_val(r[2])
        label = sql_val(r[3] if len(r) > 3 else 'Bodi Casing')
        active = sql_val(r[4] if len(r) > 4 else 1)
        c_at = sql_val(r[5] if len(r) > 5 and r[5] is not None else datetime.datetime.now())
        covered_assets.add(aid)
        sql_lines.append(f"INSERT INTO asset_qr_tokens (id, asset_id, token, placement_label, is_active, created_at) VALUES ({qid}, {aid}, {tok}, {label}, {active}, {c_at});")

# Generate static tokens for remaining assets not in Asset_QR_Tokens
import hashlib
for aid in sorted(valid_asset_ids - covered_assets):
    tok = hashlib.sha256(f"STATIC_QR_MAINTENANCE_KEY_SALT_{aid}".encode()).hexdigest()[:32]
    sql_lines.append(f"INSERT INTO asset_qr_tokens (asset_id, token, placement_label, is_active, created_at) VALUES ({aid}, '{tok}', 'Bodi Casing', 1, NOW());")
sql_lines.append("")

# 7. USERS
ws = wb['Users']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 7. USERS")
sql_lines.append("TRUNCATE TABLE users;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        uid = int(float(r[0]))
        uname = sql_val(r[1])
        pw = sql_val(r[2])
        nama = sql_val(r[3])
        role = sql_val(r[4])
        telp = sql_val(r[5] if len(r) > 5 else None)
        stat = sql_val(r[6] if len(r) > 6 else 'Aktif')
        c_at = sql_val(r[7] if len(r) > 7 and r[7] is not None else datetime.datetime.now())
        face_desc = sql_val(r[8] if len(r) > 8 else None)
        face_photo = sql_val(r[9] if len(r) > 9 else None)
        face_status = sql_val(r[10] if len(r) > 10 else 'none')
        passkey = sql_val(r[11] if len(r) > 11 else None)
        panggilan = sql_val(r[12] if len(r) > 12 else None)
        sql_lines.append(f"INSERT INTO users (id, username, password, nama, role, telepon, status, created_at, face_descriptor, face_photo, face_status, passkey_credential, nama_panggilan) VALUES ({uid}, {uname}, {pw}, {nama}, {role}, {telp}, {stat}, {c_at}, {face_desc}, {face_photo}, {face_status}, {passkey}, {panggilan});")
sql_lines.append("")

# 8. MAINTENANCE_SCAN
ws = wb['Maintenance_Scan']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 8. MAINTENANCE_SCAN")
sql_lines.append("TRUNCATE TABLE maintenance_scan;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        try:
            mid = int(float(r[0]))
            aid = int(float(r[1]))
        except (ValueError, TypeError):
            continue
        uid = sql_val(r[2])
        tech_name = sql_val(r[3])
        m_date = sql_val(r[4])
        m_time = sql_val(r[5])
        m_month = sql_val(r[6])
        m_year = sql_val(r[7])
        stat = sql_val(r[8] if len(r) > 8 else 'Selesai')
        source = sql_val(r[9] if len(r) > 9 else 'QR')
        c_at = sql_val(r[10] if len(r) > 10 and r[10] is not None else datetime.datetime.now())
        findings = sql_val(r[11] if len(r) > 11 else None)
        recommend = sql_val(r[12] if len(r) > 12 else None)
        bio_ver = sql_val(r[13] if len(r) > 13 else 0)
        bio_conf = sql_val(r[14] if len(r) > 14 else None)
        bio_photo = sql_val(r[15] if len(r) > 15 else None)
        lat = sql_val(r[16] if len(r) > 16 else None)
        lng = sql_val(r[17] if len(r) > 17 else None)
        sql_lines.append(f"INSERT INTO maintenance_scan (id, asset_id, technician_user_id, technician_name, maintenance_date, maintenance_time, maintenance_month, maintenance_year, status, source, created_at, findings, recommendation, biometric_verified, biometric_confidence, biometric_photo, latitude, longitude) VALUES ({mid}, {aid}, {uid}, {tech_name}, {m_date}, {m_time}, {m_month}, {m_year}, {stat}, {source}, {c_at}, {findings}, {recommend}, {bio_ver}, {bio_conf}, {bio_photo}, {lat}, {lng});")
sql_lines.append("")

# 9. MAINTENANCE_CHECKLISTS
ws = wb['Maintenance_Checklists']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 9. MAINTENANCE_CHECKLISTS")
sql_lines.append("TRUNCATE TABLE maintenance_checklists;")
for r in rows:
    if r[0] is not None and str(r[0]).strip() != '':
        try:
            cid = int(float(r[0]))
            mid = int(float(r[1]))
            aid = int(float(r[2]))
            chk_num = int(float(r[3]))
        except (ValueError, TypeError):
            continue
        chk_name = sql_val(r[4])
        checked = sql_val(r[5] if len(r) > 5 else 1)
        notes = sql_val(r[6] if len(r) > 6 else None)
        c_at = sql_val(r[7] if len(r) > 7 and r[7] is not None else datetime.datetime.now())
        sql_lines.append(f"INSERT INTO maintenance_checklists (id, maintenance_id, asset_id, checklist_number, checklist_name, checked, notes, created_at) VALUES ({cid}, {mid}, {aid}, {chk_num}, {chk_name}, {checked}, {notes}, {c_at});")
sql_lines.append("")

# 10. MAINTENANCE_FINDINGS
ws = wb['Maintenance_Findings']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 10. MAINTENANCE_FINDINGS")
sql_lines.append("TRUNCATE TABLE maintenance_findings;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        try:
            fid = int(float(r[0]))
            mid = sql_val(r[1])
            aid = int(float(r[2]))
        except (ValueError, TypeError):
            continue
        kat = sql_val(r[3] if len(r) > 3 else 'Maintenance Temuan')
        desc = sql_val(r[4] if len(r) > 4 else '-')
        action = sql_val(r[5] if len(r) > 5 else None)
        stat = sql_val(r[6] if len(r) > 6 else 'Open')
        rep_by = sql_val(r[7] if len(r) > 7 else None)
        rep_at = sql_val(r[8] if len(r) > 8 and r[8] is not None else datetime.datetime.now())
        res_by = sql_val(r[9] if len(r) > 9 else None)
        res_at = sql_val(r[10] if len(r) > 10 and r[10] is not None else None)
        notes = sql_val(r[11] if len(r) > 11 else None)
        sql_lines.append(f"INSERT INTO maintenance_findings (id, maintenance_scan_id, asset_id, finding, action_taken, severity, repair_status, status, reported_by, reported_at, resolved_by, resolved_at, catatan_penyelesaian, created_at) VALUES ({fid}, {mid}, {aid}, {desc}, {action}, 'Sedang', {stat}, {stat}, {rep_by}, {rep_at}, {res_by}, {res_at}, {notes}, {rep_at});")
sql_lines.append("")

# 11. AUDIT_TRAIL -> system_audit_logs
ws = wb['Audit_Trail']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 11. AUDIT_TRAIL (system_audit_logs)")
sql_lines.append("TRUNCATE TABLE system_audit_logs;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        try:
            lid = int(float(r[0]))
        except (ValueError, TypeError):
            continue
        c_at = sql_val(r[1] if len(r) > 1 and r[1] is not None else datetime.datetime.now())
        uid = sql_val(r[2] if len(r) > 2 else None)
        uname = sql_val(r[3] if len(r) > 3 else 'Sistem')
        urole = sql_val(r[4] if len(r) > 4 else 'admin')
        ip = sql_val(r[5] if len(r) > 5 else '127.0.0.1')
        act = sql_val(r[6] if len(r) > 6 else 'UPDATE')
        mod = sql_val(r[7] if len(r) > 7 else 'ASET')
        tid = sql_val(r[8] if len(r) > 8 else None)
        tlabel = sql_val(r[9] if len(r) > 9 else None)
        details = sql_val(r[10] if len(r) > 10 else None)
        sql_lines.append(f"INSERT INTO system_audit_logs (id, created_at, user_id, user_name, user_role, ip_address, action, module, target_id, target_label, details) VALUES ({lid}, {c_at}, {uid}, {uname}, {urole}, {ip}, {act}, {mod}, {tid}, {tlabel}, {details});")
sql_lines.append("")

# 12. INVENTARIS_KARTU
ws = wb['inventaris_kartu']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 12. INVENTARIS_KARTU")
sql_lines.append("TRUNCATE TABLE inventaris_kartu;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        try:
            kid = int(float(r[0]))
        except (ValueError, TypeError):
            continue
        norek = sql_val(r[1])
        nama = sql_val(r[2])
        tgl = sql_val(r[3] if len(r) > 3 and r[3] is not None else datetime.date(2020, 1, 1))
        bc = sql_val(r[4] if len(r) > 4 else '')
        lok = sql_val(r[5] if len(r) > 5 else 'Kantor Pusat (KPO)')
        c_at = sql_val(r[6] if len(r) > 6 and r[6] is not None else datetime.datetime.now())
        sql_lines.append(f"INSERT INTO inventaris_kartu (id, nomor_rekening, nama_barang, tanggal_perolehan, barcode_data, lokasi, pengguna, created_at) VALUES ({kid}, {norek}, {nama}, {tgl}, {bc}, {lok}, 'Umum / Pool', {c_at});")
sql_lines.append("")

# 13. EMPLOYEE_COMPLAINTS
ws = wb['Employee_Complaints']
rows = list(ws.iter_rows(values_only=True))
sql_lines.append("-- 13. EMPLOYEE_COMPLAINTS")
sql_lines.append("TRUNCATE TABLE employee_complaints;")
for r in rows[1:]:
    if r[0] is not None and str(r[0]).strip() != '':
        try:
            cid = int(float(r[0]))
            aid = int(float(r[1]))
        except (ValueError, TypeError):
            continue
        emp_name = sql_val(r[2])
        kat = sql_val(r[3] if len(r) > 3 else 'Umum')
        comp_text = sql_val(r[4])
        contact = sql_val(r[5] if len(r) > 5 else None)
        stat = sql_val(r[6] if len(r) > 6 else 'Open')
        tech_note = sql_val(r[7] if len(r) > 7 else None)
        res_at = sql_val(r[9] if len(r) > 9 and r[9] is not None else None)
        c_at = sql_val(r[10] if len(r) > 10 and r[10] is not None else datetime.datetime.now())
        sql_lines.append(f"INSERT INTO employee_complaints (id, asset_id, employee_name, complaint_text, status, technician_notes, resolved_at, created_at) VALUES ({cid}, {aid}, {emp_name}, {comp_text}, {stat}, {tech_note}, {res_at}, {c_at});")
sql_lines.append("")

sql_lines.append("SET FOREIGN_KEY_CHECKS = 1;")

out_file = 'sql/05_full_database_migration.sql'
with open(out_file, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_lines) + '\n')

print(f"Generated {out_file} with {len(sql_lines)} lines of SQL successfully!")
