<?php
/**
 * Router fallback untuk direktori /assets/
 * Menjembatani request URL /assets atau /assets/ agar diarahkan langsung
 * ke halaman modul Data Komputer & PC (assets.php) tanpa terkena 403 Forbidden.
 */

declare(strict_types=1);

// Ubah working directory ke direktori induk (api/)
chdir(dirname(__DIR__));

// Set script name seolah-olah assets.php di root agar helper URL (module_url) bekerja sempurna
$_SERVER['SCRIPT_NAME'] = '/assets.php';
$_SERVER['PHP_SELF'] = '/assets.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/assets.php';

require __DIR__ . '/../assets.php';
