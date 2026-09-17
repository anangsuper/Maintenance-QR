<?php
/**
 * Modul Kartu Inventaris (CR80) & Label QR
 * Disatukan langsung ke dalam Dashboard Utama Bank Mitra (Tab: Kartu Inventaris).
 */
if (!isset($_GET['tab'])) {
    $_GET['tab'] = 'kartu';
}
require __DIR__ . '/dashboard.php';
