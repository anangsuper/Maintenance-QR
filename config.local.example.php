<?php
/**
 * Copy file ini menjadi config.local.php bila tidak memakai environment variable.
 * Jangan commit config.local.php ke Git.
 */
return [
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'rekap_it',
    'db_user' => 'root',
    'db_pass' => '',
    // Kosongkan app_url agar dideteksi otomatis dari browser.
    // Contoh produksi: https://rekapit.domainanda.com/modules/qr_maintenance
    'app_url' => '',
    // URL login project utama. Bisa relatif atau absolut.
    'login_url' => '/login.php',
];
