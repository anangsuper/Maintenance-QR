<?php
/**
 * Konfigurasi Lokal - Server Maintenance & Immich
 * Copy file ini menjadi config.local.php di server Anda.
 */
return [
    // 1. KONEKSI DATABASE MYSQL / MARIADB
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'db_maintenance_qr',
    'db_user' => 'qr_user',
    'db_pass' => 'QrPass123',

    // 2. URL APLIKASI
    // Kosongkan agar dideteksi otomatis oleh browser (misal: http://192.168.88.163)
    'app_url' => '',
    'login_url' => '',

    // 3. INTEGRASI SERVER IMMICH (PHOTO SERVER)
    // Masukkan alamat server Immich Anda dan API Key dari dashboard Immich
    'immich_url' => 'http://127.0.0.1:2283',
    'immich_api_key' => '', // Dapatkan dari: User Settings -> API Keys di Immich

    // 4. KUNCI ENKRIPSI SESI
    'app_key' => 'c89b21f3a9e245a4980d92e85a6e2945d7f1b2c3a4e5f60718293a4b5c6d7e8f',
];
