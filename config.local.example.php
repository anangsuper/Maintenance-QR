<?php
/**
 * Konfigurasi Database Lokal & Server Produksi Bank Mitra
 * Salin file ini menjadi 'config.local.php' dan sesuaikan isinya.
 */

return [
    // Pengaturan Database MariaDB / MySQL
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'rekap_it',
    'db_user' => 'maintenance_user',
    'db_pass' => 'password_kuat_123!',

    // Durasi Idle Timeout Sesi (1800 detik = 30 menit)
    'session_timeout' => '1800',

    // Kunci Rahasia Enkripsi Cookie Sesi (Opsional, jika kosong sistem menggunakan salt default)
    'app_key' => 'bank_mitra_secret_production_key_2026',

    // URL Aplikasi jika berada di subfolder (kosongkan jika di root domain)
    'app_url' => '',

    // Domain / URL resmi tujuan pemindaian Kode QR (Stiker Unit, Kartu CR80, QR Pintu Server)
    // Default: https://it.bankmitra.co.id
    'qr_base_url' => 'https://it.bankmitra.co.id',
];

