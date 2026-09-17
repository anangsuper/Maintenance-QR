<?php
/**
 * GoogleSheetsBridge - Helper Komunikasi HTTP ke Google Apps Script Web App
 * 
 * Digunakan untuk integrasi auto-database Google Spreadsheet:
 * - Insert / Batch Insert
 * - Update
 * - Delete / Batch Delete
 * - Read All
 */
class GoogleSheetsBridge {
    private static string $webAppUrl = '';

    /**
     * Set URL Web App Google Apps Script
     */
    public static function setUrl(string $url): void {
        self::$webAppUrl = trim($url);
    }

    /**
     * Dapatkan URL Web App dari memory, config, session, atau env
     */
    public static function getUrl(): string {
        if (!empty(self::$webAppUrl)) {
            return self::$webAppUrl;
        }

        $fromSession = (string)($_SESSION['google_sheets_webapp_url'] ?? '');
        if ($fromSession !== '') {
            return $fromSession;
        }

        $fromEnv = (string)(getenv('GOOGLE_SHEETS_WEBAPP_URL') ?: (getenv('GAS_WEBAPP_URL') ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        if (function_exists('envv')) {
            $fromEnvv = envv('GOOGLE_SHEETS_WEBAPP_URL', envv('GAS_WEBAPP_URL', ''));
            if (!empty($fromEnvv)) {
                return (string)$fromEnvv;
            }
        }

        if (function_exists('cfg')) {
            $fromCfg = cfg('google_sheets_webapp_url', '');
            if (!empty($fromCfg)) {
                return (string)$fromCfg;
            }
        }

        return '';
    }

    /**
     * Kirim permintaan POST (insert, update, delete)
     */
    public static function post(string $action, array $data = [], mixed $id = null, string $table = 'inventaris_kartu'): array {
        $url = self::getUrl();
        if (empty($url)) {
            return [
                'success' => false,
                'error' => 'URL Google Apps Script Web App belum dikonfigurasi.'
            ];
        }

        $payload = json_encode([
            'action' => $action,
            'table'  => $table,
            'id'     => $id,
            'data'   => $data
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'error' => 'Ekstensi PHP cURL tidak aktif pada server ini.'
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== '') {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $curlError
            ];
        }

        $decoded = json_decode((string)$response, true);
        if ($decoded === null && !empty($response)) {
            // Cek jika response HTML redirect atau teks biasa
            return [
                'success' => false,
                'raw_response' => substr((string)$response, 0, 500),
                'error' => 'Respon Google Sheets tidak berformat JSON yang valid.'
            ];
        }

        return is_array($decoded) ? $decoded : ['success' => true, 'response' => $response];
    }

    /**
     * Kirim permintaan GET (read all baris data)
     */
    public static function get(string $table = 'inventaris_kartu'): array {
        $url = self::getUrl();
        if (empty($url)) {
            return [];
        }

        $sep = (strpos($url, '?') === false) ? '?' : '&';
        $requestUrl = $url . $sep . 'action=readAll&table=' . urlencode($table);

        if (!function_exists('curl_init')) {
            return [];
        }

        $ch = curl_init($requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '' || empty($response)) {
            return [];
        }

        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
