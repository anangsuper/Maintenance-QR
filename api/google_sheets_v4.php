<?php
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * GOOGLE SHEETS API V4 CLIENT (DIRECT GOOGLE CLOUD API)
 * Dilengkapi Smart Caching, Retry Handler, dan Header Normalization
 */
class GoogleSheetsV4Client {
    private string $spreadsheetId;
    private string $clientEmail;
    private string $privateKey;
    private static ?string $cachedAccessToken = null;
    private static array $runtimeCache = [];

    public function __construct(string $spreadsheetId, string $clientEmail, string $privateKey) {
        $this->spreadsheetId = trim($spreadsheetId);
        $this->clientEmail = trim($clientEmail);
        $this->privateKey = str_replace(['\\n', "\r"], ["\n", ''], trim($privateKey));
    }

    private function curlExec(string $url, array $opts = []): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, $opts + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            // Fallback jika sertifikat lokal Windows tidak lengkap
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
        }
        return (string)$response;
    }

    public function getAccessToken(): ?string {
        if (self::$cachedAccessToken !== null) {
            return self::$cachedAccessToken;
        }

        // Cek cache session agar tidak request token berulang-ulang
        if (!empty($_SESSION['_gs_access_token']) && !empty($_SESSION['_gs_token_exp']) && $_SESSION['_gs_token_exp'] > time() + 120) {
            self::$cachedAccessToken = (string)$_SESSION['_gs_access_token'];
            return self::$cachedAccessToken;
        }

        $now = time();
        $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64url_encode(json_encode([
            'iss' => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $toSign = $header . '.' . $payload;
        $signature = '';
        $success = @openssl_sign($toSign, $signature, $this->privateKey, 'SHA256');

        if (!$success) {
            error_log('Google Cloud API: Failed to sign JWT with private key');
            return null;
        }

        $jwt = $toSign . '.' . base64url_encode($signature);

        $response = $this->curlExec('https://oauth2.googleapis.com/token', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
        ]);

        $data = json_decode($response, true);
        if (!empty($data['access_token'])) {
            self::$cachedAccessToken = (string)$data['access_token'];
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['_gs_access_token'] = self::$cachedAccessToken;
                $_SESSION['_gs_token_exp'] = $now + 3500;
            }
            return self::$cachedAccessToken;
        }

        error_log('Google Cloud API OAuth error: ' . $response);
        return null;
    }

    public function getValues(string $range): array {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
            urlencode($this->spreadsheetId),
            urlencode($range)
        );

        $attempt = 0;
        $maxAttempts = 2;
        while ($attempt < $maxAttempts) {
            $attempt++;
            $response = $this->curlExec($url, [
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            ]);

            $data = json_decode($response, true);
            if (isset($data['values'])) {
                return $data['values'];
            }

            // Jika token kedaluwarsa, hapus cache dan coba lagi
            if (!empty($data['error']['code']) && $data['error']['code'] == 401) {
                self::$cachedAccessToken = null;
                if (session_status() === PHP_SESSION_ACTIVE) {
                    unset($_SESSION['_gs_access_token'], $_SESSION['_gs_token_exp']);
                }
                $token = $this->getAccessToken();
                if (!$token) break;
            }
        }

        return [];
    }

    public function appendValues(string $range, array $rows): bool {
        $token = $this->getAccessToken();
        if (!$token) return false;

        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED',
            urlencode($this->spreadsheetId),
            urlencode($range)
        );

        $response = $this->curlExec($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['values' => $rows]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);

        $data = json_decode($response, true);
        $sheetName = explode('!', $range)[0];
        $this->clearCache($sheetName);
        return isset($data['updates']);
    }

    public function updateValues(string $range, array $rows): bool {
        $token = $this->getAccessToken();
        if (!$token) return false;

        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?valueInputOption=USER_ENTERED',
            urlencode($this->spreadsheetId),
            urlencode($range)
        );

        $response = $this->curlExec($url, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode(['values' => $rows]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);

        $data = json_decode($response, true);
        $sheetName = explode('!', $range)[0];
        $this->clearCache($sheetName);
        return isset($data['updatedCells']);
    }

    public function clearValues(string $range): bool {
        $token = $this->getAccessToken();
        if (!$token) return false;

        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:clear',
            urlencode($this->spreadsheetId),
            urlencode($range)
        );

        $this->curlExec($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);

        $sheetName = explode('!', $range)[0];
        $this->clearCache($sheetName);
        return true;
    }

    public function clearCache(?string $sheetName = null): void {
        if ($sheetName) {
            unset(self::$runtimeCache[$sheetName]);
            if (session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION['_gs_cache_' . $sheetName], $_SESSION['_gs_time_' . $sheetName]);
            }
        } else {
            self::$runtimeCache = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                foreach (array_keys($_SESSION) as $k) {
                    if (str_starts_with($k, '_gs_cache_') || str_starts_with($k, '_gs_time_')) {
                        unset($_SESSION[$k]);
                    }
                }
            }
        }
    }

    public function getSheetData(string $sheetName, bool $forceRefresh = false): array {
        // 1. Cek runtime memory cache di request PHP saat ini
        if (!$forceRefresh && isset(self::$runtimeCache[$sheetName])) {
            return self::$runtimeCache[$sheetName];
        }

        // 2. Cek warm session cache (TTL: 120 detik) untuk pergantian halaman secepat kilat (0.01 detik)
        if (!$forceRefresh && session_status() === PHP_SESSION_ACTIVE) {
            $cacheKey = '_gs_cache_' . $sheetName;
            $timeKey = '_gs_time_' . $sheetName;
            if (!empty($_SESSION[$cacheKey]) && !empty($_SESSION[$timeKey]) && (time() - (int)$_SESSION[$timeKey] < 120)) {
                self::$runtimeCache[$sheetName] = $_SESSION[$cacheKey];
                return self::$runtimeCache[$sheetName];
            }
        }

        // 3. Ambil data dari Google Sheets API jika cache kedaluwarsa / force refresh
        $rows = $this->getValues($sheetName . '!A1:Z1000');

        // 4. Jika API gagal (misal rate limit/timeout), gunakan session cache sebelumnya agar data tidak hilang
        if (empty($rows)) {
            if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['_gs_cache_' . $sheetName])) {
                self::$runtimeCache[$sheetName] = $_SESSION['_gs_cache_' . $sheetName];
                return self::$runtimeCache[$sheetName];
            }
            return [];
        }

        if (count($rows) <= 1) {
            self::$runtimeCache[$sheetName] = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['_gs_cache_' . $sheetName] = [];
                $_SESSION['_gs_time_' . $sheetName] = time();
            }
            return [];
        }

        $headers = $rows[0];
        $result = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $obj = ['_row_num' => $i + 1];
            foreach ($headers as $idx => $header) {
                $val = $row[$idx] ?? '';
                // Simpan key asli
                $obj[$header] = $val;
                // Simpan key ternormalisasi (huruf kecil & tanpa spasi)
                $normKey = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim((string)$header)));
                if ($normKey !== '') {
                    $obj[$normKey] = $val;
                }
            }
            $result[] = $obj;
        }

        // Simpan ke cache runtime & session dengan timestamp
        self::$runtimeCache[$sheetName] = $result;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_gs_cache_' . $sheetName] = $result;
            $_SESSION['_gs_time_' . $sheetName] = time();
        }

        return $result;
    }

    /**
     * Buat sheet/tab baru jika belum ada
     */
    public function createSheetIfNotExists(string $sheetName): bool {
        $token = $this->getAccessToken();
        if (!$token) return false;

        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s:batchUpdate',
            urlencode($this->spreadsheetId)
        );

        $response = $this->curlExec($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'requests' => [[
                    'addSheet' => [
                        'properties' => ['title' => $sheetName]
                    ]
                ]]
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);

        return true;
    }
}

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
