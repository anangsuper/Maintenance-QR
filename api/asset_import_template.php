<?php
/**
 * GENERATOR TEMPLATE IMPORT ASET IT (EXCEL / CSV COMPATIBLE)
 * Format standar perbankan untuk mempermudah migrasi & pengisian massal
 */
require __DIR__ . '/bootstrap.php';
require_login();

$filename = 'Template_Import_Aset_IT_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// Write UTF-8 BOM agar Microsoft Excel langsung membaca aksen & karakter secara benar
fwrite($out, "\xEF\xBB\xBF");

// Header Kolom Standar
$headers = [
    'Kode Inventaris',
    'Kategori',
    'Merk',
    'Model / Tipe',
    'Serial Number',
    'Kantor Cabang',
    'Divisi / Unit Kerja',
    'Pengguna / PIC',
    'Alamat IP',
    'Printer Terhubung',
    'Status Unit',
    'Keterangan'
];

fputcsv($out, $headers, ';');

// Data Contoh 1: PC Teller KPO
fputcsv($out, [
    'INV-KPO-001',
    'PC Desktop',
    'Lenovo',
    'ThinkCentre M70q Gen 3',
    'SN-LNV-992140',
    'Kantor Pusat',
    'Operasional',
    'Teller 1',
    '192.168.1.101',
    'Epson L3211',
    'Aktif',
    'PC Teller Layanan Utama'
], ';');

// Data Contoh 2: Laptop IT Staff
fputcsv($out, [
    'INV-KPO-002',
    'Laptop',
    'MSI',
    'Thin 15 B12UCX',
    'SN-MSI-883192',
    'Kantor Pusat',
    'IT / MIS',
    'Staff IT',
    '192.168.1.55',
    'Network Printer',
    'Aktif',
    'Laptop Operasional IT'
], ';');

// Data Contoh 3: Printer Kasir Batulicin
fputcsv($out, [
    'INV-BLC-001',
    'Printer',
    'Epson',
    'EcoTank L3211',
    'SN-EPS-771920',
    'Batulicin',
    'Operasional',
    'Kasir Kas Batulicin',
    '192.168.2.20',
    '-',
    'Aktif',
    'Printer Kas Kantor Kas Batulicin'
], ';');

// Data Contoh 4: PC Customer Service Martapura
fputcsv($out, [
    'INV-MTP-001',
    'PC Desktop',
    'Dell',
    'OptiPlex 3090 Micro',
    'SN-DLL-551029',
    'Martapura',
    'Customer Service',
    'CS 1 Martapura',
    '192.168.3.15',
    'HP LaserJet Pro',
    'Aktif',
    'PC Layanan Nasabah'
], ';');

// Data Contoh 5: PC Operasional Tanjung
fputcsv($out, [
    'INV-TJG-001',
    'PC Desktop',
    'HP',
    'ProDesk 400 G6',
    'SN-HP-339182',
    'Tanjung',
    'Operasional',
    'Staff Operasional',
    '192.168.4.12',
    'Canon G2010',
    'Aktif',
    'PC Operasional Tanjung'
], ';');

// Data Contoh 6: PC Handil Bakti
fputcsv($out, [
    'INV-HND-001',
    'PC Desktop',
    'Lenovo',
    'ThinkCentre Neo 50s',
    'SN-LNV-449102',
    'Handil Bakti',
    'Operasional',
    'Staff Kas Handil Bakti',
    '192.168.5.10',
    '-',
    'Aktif',
    'PC Kantor Kas Handil Bakti'
], ';');

fclose($out);
exit;
