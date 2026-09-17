<?php
/**
 * Modul Kartu Inventaris (CR80) & Label QR
 * Disatukan langsung ke dalam Dashboard Utama Bank Mitra (Tab: Kartu Inventaris).
 */
if (!isset(['tab'])) {
    ['tab'] = 'kartu';
}
require __DIR__ . '/dashboard.php';
