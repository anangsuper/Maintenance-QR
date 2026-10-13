<?php
/**
 * AUTO RUN IMPORTER - DATA ASET INVENTARIS IT
 * Digunakan untuk memasukkan data CSV aset ke database MariaDB & membuat token QR otomatis
 * Bisa dijalankan via browser: https://192.168.88.163/run_import_assets.php
 * Atau via terminal: php /var/www/html/Server-Maintenance/api/run_import_assets.php
 */
require __DIR__ . '/bootstrap.php';

// Data CSV Mentah dari User
$csvData = <<<'CSV'
7,0150035418012023,HP,HP Victus 15-FB0012AX,SN5CD235GFRS,1,1,1,5,Aktif,Lokasi: LT.4/KPO/IT | Jabatan: Kepala Satker MIS & IT | Kondisi: Baik,192.168.88.200,Epson L3211,
8,0150049226082026,MSI,LAPTOP MSI THIN 15,9S716R8312413ZS1001160,1,1,1,6,Aktif,LAPTOP MSI THIN 15 I7 8GB 512 GB RTX2050  COSMOS GRAY,192.168.88.202,Epson L3211 Sharing,
9,0150018606012021,Asus,ASUS Vivobook,LBN0CX01L884451,1,1,13,7,Aktif,Lokasi: LT.4/KPO | Jabatan: Internal Control Unit | Kondisi: Baik,192.168.88.162,Epson L121 Sharing,
10,0150044624012025,HP,HP 14S,5CD4256MS3,1,1,4,74,Aktif,Lokasi: LT.4/KPI/KadivKredit | Jabatan: Kadiv Kredit | Kondisi: Baik,192.168.1.15,Epson L121,
11,011202061223001,HP,HP 14s-dq5xxx,5CD337BKRN,1,1,7,9,Aktif,Lokasi: LT.4/KPO/Hrd | Jabatan: Kabag Umum & SDM | Kondisi: Baik,192.168.1.4,Epson L3211,
12,0150031008082022,Lenovo,Lenovo IdeaPad 3,PF3NZNGN,1,1,6,10,Aktif,Lokasi: LT.4/KPO/SKAI | Jabatan: Staff SKAI | Kondisi: Baik,192.168.88.236,Epson L3210 Sharing,
13,0150024603112021,HP,HP 14s-cf2xxx,5CG13356NJ,1,1,6,11,Aktif,Lokasi: LT.4/KPO/SKAI | Jabatan: Kepala SKAI | Kondisi: Baik,192.168.88.32 Wi-Fi,Epson L3210 Sharing,
14,0150010418102018,Asus,Asus A442UF,J5N0CV03Y147196,1,1,1,12,Aktif,Lokasi: KPO/Laptop Meeting | Jabatan: Staff Satker MIS & IT | Kondisi: Baik,192.168.1.26 Wi-Fi,,
15,INV-KPO-015,Lenovo,Lenovo IdeaPad 3,5CG1181ZFC,1,1,4,75,Aktif,Lokasi: LT.3/KPO/KaCab | Jabatan: Kepala Cabang | Kondisi: Baik,192.169.50.146 Wifi,,
16,0150030012042022,HP,HP All-in-One 22-df1xxx,8CC2030GCK,2,1,12,76,Aktif,Lokasi: LT.3/KPO/AC | Jabatan: Kabag Analis Kredit | Kondisi: Baik,192.169.88.71,Epson L3211 sharing,
17,0150020709072021,Acer,Acer AIO,DQBG0SN00111402A163000,1,1,12,73,Aktif,Lokasi: LT.3/KPO/AC | Jabatan: Analis Kredit | Kondisi: Baik,192.168.88.56,Epson L3211 Sharing,
18,0150045370042025,Asus,Asus Mini PC,-,1,1,4,16,Aktif,Lokasi: LT.3/KPO/AO | Jabatan: Account Officer | Kondisi: Baik,192.168.88.64,Epson L3110,
19,0150045430042025,Asus,Asus Mini PC,-,1,1,4,17,Aktif,Lokasi: LT.3/KPO/AO | Jabatan: Account Officer | Kondisi: Baik,192.168.88.193,Epson L3110,
20,015026502122021,Lenovo,Lenovo IdeaCentre AIO 3-24ITL6,MP20W1QQ,2,1,4,77,Aktif,Lokasi: LT.3/KPO/DirUt | Jabatan: Direktur Utama | Kondisi: Baik,192.168.88.125,epsonL3110,
21,015025919112021,Dell,Dell AIO 5400,4QGJG92,2,1,4,78,Aktif,Lokasi: LT.3/KPO/DirBis | Jabatan: Direktur Bisnis | Kondisi: Baik,192.168.88.72,Epson L3210,
22,010802061223001,HP,HP Laptop 14s-dq5xxx,5CD336MQR4,1,1,4,79,Aktif,Lokasi: LT.3/KPO/DirDivKrd | Jabatan: Kadiv Kredit | Kondisi: Baik,192.168.50.240,Epson L3210,
23,0150031226092022,HP,HP All-in-One 22-df1xxx,8CC213298B,3,1,1,80,Aktif,Lokasi: LT.3/KPO/AdmColl | Jabatan: Admin Collection | Kondisi: Baik,192.168.88.95,Printer L3210 Sharing,
24,0150030801082022,HP,HP All-in-One 22-df1xxx,PF3P3SE9,1,1,11,22,Aktif,Lokasi: LT.3/KPO/KaBagColl | Jabatan: Kabag Collection | Kondisi: Baik,192.68.50.176,,
25,02002810122007-A,PC Custom,PC DESKTOP,-,2,1,11,23,Aktif,Lokasi: LT.3/KPO/KaBagRecov | Jabatan: Kabag Remedial & Recovery | Kondisi: Baik,192.168.88.66,,
26,02002810122007-B,PC Custom,PC DESKTOP,-,2,1,11,24,Aktif,Lokasi: LT.3/KPO/Coll | Jabatan: Collection Officer | Kondisi: Baik,192.168.88.188,Epson L3210,
27,02008104112016-B,PC Custom,PC DESKTOP,-,2,1,11,25,Aktif,Lokasi: LT.3/KPO/Coll | Jabatan: Collection Officer | Kondisi: Baik,192.168.88.123,Epson L3210,
28,0150017504092020,Asus,Asus VivoBook X409BA,L7N0CV15Y36730B,1,1,2,26,Aktif,Lokasi: LT.3/KPO/FO | Jabatan: Funding Officer | Kondisi: Baik,192.168.50.122,Epson L3210 Sharing,
29,0150016104022020,Asus,Asus VivoBook 14,KBN0GR03N553479,1,1,2,27,Aktif,Lokasi: LT.3/KPO/FO | Jabatan: Funding Officer | Kondisi: Baik,,Epson L3210,
30,010802061223001-B,HP,HP All-in-One 22-dd2xxx,8CC33514HS,3,1,4,81,Aktif,Lokasi: LT.3/KPO/KaBagAdmKrd | Jabatan: Kabag Admin Kredit | Kondisi: Baik,192.168.88.126,Epson L3210,
31,0150047417122025,HP,HP All-in-One 22-df1xxx,8CC2030GD0,2,1,4,82,Aktif,Lokasi: LT.3/KPO/Legal | Jabatan: Legal | Kondisi: Baik,192.162.88.67,Epson L3211,
32,0150029812042022,Asus,Asus Vivo AIO 22,M5PTCJ00W693215,2,1,4,83,Aktif,Lokasi: LT.3/KPO/AdmKrd | Jabatan: Admin Kredit | Kondisi: Baik,192.168.88.208,Epson  L3210 Sharing,
33,0150029912042022,HP,HP All-in-One 22-df1xxx,8CC2011ZSR,2,1,4,84,Aktif,Lokasi: LT.3/KPO/AdmKrd | Jabatan: Admin Kredit | Kondisi: Baik,192.168.88.240,Epson L3210 Sharing,
34,0150009518052018,Asus,Asus X441NA,HCN0CV15H64151G,1,1,4,32,Aktif,Lokasi: LT.3/KPO/Krd | Jabatan: Kredit | Kondisi: Baik,192.168.88.235,Epson L3210 sharing dan Kodak i940,
35,0150036217022023,HP,HP Victus 15,5CD225N6GR,1,1,8,85,Aktif,Lokasi: LT.2/KPO/Kepatuhan | Jabatan: Direktur Kepatuhan | Kondisi: Baik,192.162.88.205,,
36,0150019616042021,Asus,Asus Vivo AIO,M1PTCJ00T37704A,2,1,8,34,Aktif,Lokasi: LT.2/KPO/Kepatuhan | Jabatan: PE Kepatuhan | Kondisi: Baik,192.168.88.65,Epson L3110,
37,0150047110122025,Lenovo,LENOVO V15,PF50HRLB,1,1,8,35,Aktif,Lokasi: LT.2/KPO/Kepatuhan | Jabatan: Unit Kerja Khusus APU & PPT | Kondisi: Baik,192.168.88.171,,
38,0150044524012025,HP,HP Laptop 14s-dq5xxx,5CD433D2SR,1,1,8,36,Aktif,Lokasi: LT.2/KPO/Kepatuhan | Jabatan: PE Manajemen Risiko | Kondisi: Baik,192.168.88.120,,
39,0150045726062025,Microsoft,Microsoft Surface Pro 7,63683204153,1,1,2,37,Aktif,Lokasi: LT.1/KPO/CS | Jabatan: Customer Service | Kondisi: Baik,,,
40,0150047731122025,Lenovo,Lenovo IdeaPad 3,-,1,1,2,38,Aktif,Lokasi: LT.1/KPO/CS | Jabatan: Customer Service | Kondisi: Baik,192.168.1.177,Epson PLQ-20,
41,0150026402122021,HP,HP 14 S,SCD13542Q2,1,1,2,86,Aktif,Lokasi: LT.1/KPO/DirOPS | Jabatan: Kadiv Operasional | Kondisi: Baik,192.168.88.130,Brother DCP-t520w,
42,0150035322122022,Asus,Asus Vivo AIO,M1PTCJ00T438046,3,1,2,87,Aktif,Lokasi: LT.1/KPO/KaDivOPS | Jabatan: Dir Operasional | Kondisi: Baik,192.168.xxx.,,
43,0150044705022025,Lenovo,Lenovo V15 G3,-,1,1,3,41,Aktif,Lokasi: LT.1/KPO/BO | Jabatan: Staff Akunting | Kondisi: Baik,192.168.1.138 (Wi-fi),Epson L3211 sharing,
44,0150036527022023,Asus,Asus Vivobook 15,NAN0CV02E883417,1,1,3,42,Aktif,Lokasi: LT.1/KPO/BO | Jabatan: Kabag Akunting | Kondisi: Baik,192.168.1.168 ( WIFI),Epson L3211 Sharing,
45,0150046228072025,HP,HP Laptop 15-fd0xxx,5CD423GW3L,1,1,2,43,Aktif,Lokasi: LT.1/KPO/BO | Jabatan: Supervisor Operasional | Kondisi: Baik,192.168.1.163,Epson L3110,
46,0150020205052021,Asus,Asus Vivo AIO,M3PTCJ00602409A,3,1,2,88,Aktif,Lokasi: LT.1/KPO/Teller | Jabatan: Teller | Kondisi: Baik,192.168.1.124 (Wi-fi),PLQ - 20,
47,0150020105052021,Asus,Asus Vivo AIO,KBN0GR01Z40845C,3,1,2,89,Aktif,Lokasi: LT.1/KPO/Teller | Jabatan: Teller | Kondisi: Baik,192.168.1.115,PLQ-30,
51,450005310072024,ASUS AIO,AIO ASUS A3202WBAK,,2,4,4,50,Aktif,,,EPSON  L3210,2026-09-15 9:30:00
52,450007417122025,Lenovo,PC LENOVO AIO A100( 5CID) 13-N305 U/FUNDING,,2,4,2,49,Aktif,Intel Core  I3-N305 8 Core - Ram 8 GB,192.168.88.18,Epson L3100,2026-09-15 8:41:03
53,431022210240001,AIO LENOVO,AIO LENOVO THINKCENTRE NEO 30A 13-1215U,MP2AF5BS,2,4,2,51,Aktif,Intel i3- 1215u 8 CPU - RAM 8 GB 512 GB SSD,192.168.88.17,PLQ 35,2026-09-15 9:47:59
54,085/KC.TJG/BPR.MITRA/XII/2022,LENOVO,AIO Lenovo,,2,4,2,52,Aktif,,192.168.88.125,EPSON L3210,2026-09-15 10:16:11
55,0450005410072024,ASUS AIO,AIO A3202WBAK,,2,4,4,60,Aktif,Intel Core i3- 1215U - 8 GB - 500 GB,192.168.88.6,-,2026-09-15 10:26:01
56,0450004626052023,Pc DESKTOP Mini Vonom Rx,PC BUILD,-,2,4,4,54,Aktif,Intel Core i3 10105 - Ram 8 gb - SSD 256,192.168.88.15,Epson L3210,2026-09-15 13:35:04
57,045001608112021,HP,14 S,5CG133311K,1,4,4,55,Aktif,Intel Core i3 10110U - 4 gb ram - SSD 256 gb,WIFI,,2026-09-15 13:42:42
58,0450004518012023,HP,Hp 14 s,Tidak ada,1,4,4,56,Aktif,Intel Core i3 - Ram 4 GB - SSD 256,Wifi,,2026-09-15 13:51:45
59,117/KC.TJG/BPR.MITRA/XII/2021,PC Rakitan,PC OEM,-,2,4,4,57,Aktif,Intel Core i3 -10100F - NVDIA Geforce gt 730 - Ram 8 GB -500 GB SSD,192.168.88.201,Epson L3210,2026-09-15 14:02:03
60,142/KC.TJG/BPR.MITRA/XXI/2021,HP,HP 14S,5CG1545W4G,1,4,4,58,Aktif,Intel I3 -10110 - 4 GB Ram - 256 SSD,Wifi,,2026-09-15 14:11:40
61,114/KC.TJG/BPR.MITRA/XII/2021,PC,PC rakitan,-,1,4,4,59,Aktif,"Intel(R) Core(TM) i3-10100F CPU @ 3.60GHz (8 CPUs), ~3.6GHz / 20 GB Ram / 240 SSD",192.168.88.5,Epson L3210,2026-09-15 14:25:25
62,0250007229082024,Asus,Asus A3202 WBAK,-,2,2,4,54,Aktif,Intel Core I3 Gen 12 / 8 GB Ram /SSD 500 GB,192.168.88.245,Epson L3210 (sharing),2026-09-18 11:32:45
63,0250006916012024,Asus,Notebook A14006A,-,1,2,12,61,Aktif,Intel Core I3 Gen 11/ 4 GB Ram / SSD 256 GB,192.168.88.244 (wifi),Epson L3210 (sharing),2026-09-18 11:32:46
64,025005926092022,HP,HP 14 S,,1,2,10,67,Aktif,Intel Core I3 Gen 11/ 4 GB Ram / SSD 500 GB,192.168.88.233 (wifi),Epson L360 (usb),2026-09-18 11:32:47
65,0250006816012024,Acer,AIO ACER AC22-1700,-,2,2,11,63,Aktif,Inten Core I3 Gen 12/ 4 gb ram / 500 GB,192.168.88.237,Epsol L3210 (sharing),2026-09-18 11:32:49
66,0250008330072026,ASUS,Aio Asus P440VA,-,2,2,4,71,Aktif,Inten Core I3 Gen 13/ 8 gb ram / SSD 500 GB,192.168.88.246,Epson L3210,2026-09-18 11:32:50
67,0250006716012024,Acer,AIO ACER AC22-1700,-,2,2,9,69,Aktif,Inten Core I3 Gen 12/ 4 gb ram / SSD 500 GB,192.168.88.13,Epson PLQ-20 (2011),2026-09-18 11:32:52
68,0250008223062026,Asus,Aio Asus P440VA,-,2,2,2,70,Aktif,Inten Core I3 Gen 13/ 8 gb ram / SSD 500 GB,192.168.88.250,Epson L3110,2026-09-18 11:32:53
69,0250004410022021,Acer,ACER AC22-963,-,2,2,2,49,Aktif,Intel Core I3 - Windows 11 - Ram 4 - 500 GB SSD,192.168.88.235,Epson L3210 (sharing),2026-09-22 10:42:38
70,0150030012042022,HP,PC AIO,,2,1,12,91,Aktif,,,,2026-09-28 10:11:47
71,0350006628082025,Cubi,Pro ADL-N Cubi N (MS-B0A9),,2,3,14,92,Aktif,Intel N100 - RAM 8 GB - SSD  256 GB,192.168.88.19,PLQ-50,2026-10-06 10:45:09
72,0350005729112024,Minni PC,NUC11ATKC4,,2,3,15,93,Aktif,INTEL Celeron  -  8 GB Ram -  256 SSD,192.168.88.15,Epson L3210,2026-10-06 10:56:53
73,0350004702022023,HP,All in One 22-dd2xxx,8CC2321ZNP,2,3,11,94,Aktif,Intel Core I3 Gen-12 / Ram 4 GB / 128 GB SSD,192.168.88.13,Epson L3110,2026-10-06 11:39:32
74,085/KC.MTP/BPR.MITRA/2012,Rakitan,Custom PC,O.E.M,2,3,16,95,Aktif,,,,2026-10-06 12:14:26
75,0350004602022023,HP,All in One 22-dd2xxx,DVWBV-KNXF2-JNFGH-4XJ4P-PGY6Q,2,3,16,33,Aktif,,,Epson L3110,2026-10-06 12:33:22
76,0350004026092022,Hp,All in One 22,8CC2230KWQ,2,3,17,96,Aktif,,192.168.88.9,Epson L3210,2026-10-06 13:48:13
77,035/KC.MTP/BPR.MITRA/2012,Rakitan,Pc,,2,3,18,97,Aktif,,,,2026-10-06 14:38:44
78,0350005929112024,Asus,Aio A3202WBA,S9PTJ00814337B,2,3,12,98,Aktif,,,,2026-10-06 14:50:39
79,0350006029112024,Acer,Aspire AL14-51M,,1,3,10,99,Aktif,,,,2026-10-06 15:07:57
80,0150019716042021,Asus,Vivo AIO V241DAP_M241DA,M1PTCJ00T438046,2,6,18,100,Aktif,,,,2026-10-07 10:14:23
81,0150021203062121,ASUS,VIVO AIO 22,M3PTCJ005357098,2,6,14,101,Aktif,,,PLQ-30,2026-10-07 10:34:25
82,0150021103092021,ASUS,Vivo AIO 22,M4PTCJ007568152,2,6,19,102,Aktif,,,,2026-10-07 11:09:17
83,0550000629112023,ASUS,Vivobook X415EA,,1,5,11,55,Aktif,,,,2026-10-07 13:10:51
84,0550003810072024,ASUS,AIO A3202WBAK,S4PTCJOOF125168,2,5,16,106,Aktif,,,,2026-10-07 13:26:20
85,0550003710072024,Asus,AIO A3202WBAK,S4PTCJ00F24616A,2,5,16,105,Aktif,,,L3210 Epson,2026-10-07 13:50:56
86,0550003710072024,LENOVO,Idea Centre AIO 3 22OAP7,MP2DHGGQ,2,5,12,107,Aktif,,,Epson L120,2026-10-07 14:24:28
87,05500000929112023,Lenovo,IdeaCentre AIO 3,MPWDHKGA,2,5,14,108,Aktif,,,,2026-10-07 15:17:40
88,081/KC.MPT/BPR.MITRA/2020,Asus,X441B,,1,5,18,103,Aktif,,192.168.88.211,L3210,2026-10-07 15:31:27
89,0550001029112023,LENOVO,IdeaCentre  AIO 3,MP2DHKFH,2,5,17,109,Aktif,,192.168.88.201,Epson L3210,2026-10-07 15:38:54
90,0550000729112023,ASUS,Vivobook X415EA,R8NOCV028125312,1,5,10,110,Aktif,,,Epson L3210,2026-10-07 15:53:31
91,0550000829112023,Lenovo,IdeaCentre AIO 3,MP2DHJR8,2,5,15,111,Aktif,,,L3210,2026-10-07 16:28:30
CSV;

$pdo = db();

// Pastikan skema tabel cabang, divisi, kategori_aset, assets, asset_qr_tokens sudah ada
$lines = explode("\n", trim($csvData));
$inserted = 0;
$updated = 0;
$qrGenerated = 0;
$reportRows = [];

$pdo->beginTransaction();

try {
    // Siapkan statement insert/update assets
    $stmtAsset = $pdo->prepare("
        INSERT INTO assets (
            id, kode_inventaris, merk, model, serial_number, 
            id_kategori, id_cabang, id_divisi, id_karyawan, 
            status, keterangan, ip_address, printer, created_at
        ) VALUES (
            :id, :kode, :merk, :model, :sn, 
            :id_kat, :id_cab, :id_div, :id_kar, 
            :status, :ket, :ip, :printer, :created_at
        )
        ON DUPLICATE KEY UPDATE
            kode_inventaris = VALUES(kode_inventaris),
            merk = VALUES(merk),
            model = VALUES(model),
            serial_number = VALUES(serial_number),
            id_kategori = VALUES(id_kategori),
            id_cabang = VALUES(id_cabang),
            id_divisi = VALUES(id_divisi),
            id_karyawan = VALUES(id_karyawan),
            status = VALUES(status),
            keterangan = VALUES(keterangan),
            ip_address = VALUES(ip_address),
            printer = VALUES(printer),
            updated_at = NOW()
    ");

    $stmtQr = $pdo->prepare("
        INSERT INTO asset_qr_tokens (asset_id, token, placement_label, is_active)
        VALUES (:asset_id, :token, :placement_label, 1)
        ON DUPLICATE KEY UPDATE
            token = VALUES(token),
            is_active = 1
    ");

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $row = str_getcsv($line);
        $id = isset($row[0]) ? (int)trim($row[0]) : 0;
        if ($id <= 0) continue; // Lewati header atau baris kosong

        $kode = normalize_kode_inventaris(trim((string)($row[1] ?? '')));
        $merk = trim((string)($row[2] ?? ''));
        $model = trim((string)($row[3] ?? ''));
        $sn = trim((string)($row[4] ?? ''));
        if ($sn === '-' || $sn === 'Tidak ada') $sn = '';

        $idKat = !empty($row[5]) ? (int)$row[5] : 1;
        $idCab = !empty($row[6]) ? (int)$row[6] : 1;
        $idDiv = !empty($row[7]) ? (int)$row[7] : 1;
        $idKar = !empty($row[8]) ? (int)$row[8] : 0;
        $status = !empty(trim((string)($row[9] ?? ''))) ? trim((string)$row[9]) : 'Aktif';
        $ket = trim((string)($row[10] ?? ''));

        // IP Address & Printer (Kolom 11 & 12)
        $ip = trim((string)($row[11] ?? ''));
        $ip = str_replace(',', '.', $ip); // Perbaiki typo "192,168.88.15"

        $printer = trim((string)($row[12] ?? ''));

        // Created at timestamp
        $createdAt = !empty($row[13]) ? trim((string)$row[13]) : date('Y-m-d H:i:s');
        if (!strtotime($createdAt)) {
            $createdAt = date('Y-m-d H:i:s');
        }

        // 1. Eksekusi simpan ke tabel assets
        $stmtAsset->execute([
            ':id' => $id,
            ':kode' => $kode,
            ':merk' => $merk,
            ':model' => $model,
            ':sn' => $sn,
            ':id_kat' => $idKat,
            ':id_cab' => $idCab,
            ':id_div' => $idDiv,
            ':id_kar' => $idKar,
            ':status' => $status,
            ':ket' => $ket,
            ':ip' => $ip,
            ':printer' => $printer,
            ':created_at' => $createdAt
        ]);
        $inserted++;

        // 2. Generate token QR otomatis
        $token = substr(hash('sha256', 'STATIC_QR_MAINTENANCE_KEY_SALT_' . $id), 0, 32);
        $stmtQr->execute([
            ':asset_id' => $id,
            ':token' => $token,
            ':placement_label' => 'Bodi Casing'
        ]);
        $qrGenerated++;

        $reportRows[] = [
            'id' => $id,
            'kode' => $kode,
            'device' => trim($merk . ' ' . $model),
            'ip' => $ip ?: '-',
            'printer' => $printer ?: '-'
        ];
    }

    $pdo->commit();
    $isSuccess = true;
    $msg = "Berhasil mengimpor {$inserted} aset dan membuat {$qrGenerated} Token QR ke database MariaDB!";
} catch (Throwable $e) {
    $pdo->rollBack();
    $isSuccess = false;
    $msg = "Gagal impor database: " . $e->getMessage();
}

// Jika dijalankan lewat CLI terminal
if (php_sapi_name() === 'cli') {
    echo "===============================================\n";
    echo $msg . "\n";
    echo "===============================================\n";
    exit($isSuccess ? 0 : 1);
}

// Jika dijalankan lewat Browser (Web UI)
$tableHtml = '';
foreach ($reportRows as $i => $r) {
    $tableHtml .= '<tr>
        <td class="text-center font-monospace">'.($i+1).'</td>
        <td class="font-monospace fw-bold text-primary">'.e($r['kode']).'</td>
        <td class="fw-semibold">'.e($r['device']).'</td>
        <td class="font-monospace text-muted small">'.e($r['ip']).'</td>
        <td class="small">'.e($r['printer']).'</td>
        <td class="text-center"><span class="badge bg-success rounded-pill"><i class="bi bi-check-circle me-1"></i>Tersimpan &amp; QR Aktif</span></td>
    </tr>';
}

$body = '
<div class="card p-4 border-0 shadow-sm rounded-4 mb-4 bg-white">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 fs-3">
        <i class="bi bi-database-check"></i>
      </div>
      <div>
        <h4 class="fw-bold mb-1 text-dark">Hasil Impor Data Aset ke Database</h4>
        <p class="text-secondary small mb-0">'.e($msg).'</p>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="'.e(module_url('assets.php')).'" class="btn btn-primary fw-semibold rounded-pill px-3 shadow-xs">
        <i class="bi bi-pc-display me-1"></i> Buka Asset Registry
      </a>
      <a href="'.e(module_url('asset_gallery.php')).'" class="btn btn-outline-secondary fw-semibold rounded-pill px-3 shadow-xs">
        <i class="bi bi-images me-1"></i> Buka Galeri Foto
      </a>
    </div>
  </div>

  <div class="table-responsive rounded-3 border">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width: 50px;" class="text-center">No</th>
          <th>Kode Inventaris</th>
          <th>Nama Perangkat</th>
          <th>Alamat IP</th>
          <th>Printer Terhubung</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
        '.$tableHtml.'
      </tbody>
    </table>
  </div>
</div>
';

render_page('Hasil Impor Data Aset', $body);
