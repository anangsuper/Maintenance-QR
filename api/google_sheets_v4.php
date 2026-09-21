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
    private ?string $lastError = null;
    private static ?string $cachedAccessToken = null;
    private static array $runtimeCache = [];

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function getSpreadsheetId(): string {
        return $this->spreadsheetId;
    }

    public function getClientEmail(): string {
        return $this->clientEmail;
    }

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
            CURLOPT_ENCODING => '', // Enable GZIP/deflate compression from Google Cloud
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
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

        // 1. Cek disk cache di /tmp (bertahan antar request serverless di container yang sama)
        $tokenFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gs_oauth_' . md5($this->clientEmail) . '.json';
        if (file_exists($tokenFile)) {
            $raw = @file_get_contents($tokenFile);
            if ($raw) {
                $tokData = @json_decode($raw, true);
                if (!empty($tokData['access_token']) && !empty($tokData['exp']) && $tokData['exp'] > time() + 120) {
                    self::$cachedAccessToken = (string)$tokData['access_token'];
                    return self::$cachedAccessToken;
                }
            }
        }

        // 2. Cek cache session agar tidak request token berulang-ulang
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
            $exp = $now + 3500;
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['_gs_access_token'] = self::$cachedAccessToken;
                $_SESSION['_gs_token_exp'] = $exp;
            }
            @file_put_contents($tokenFile, json_encode(['access_token' => self::$cachedAccessToken, 'exp' => $exp]));
            return self::$cachedAccessToken;
        }

        error_log('Google Cloud API OAuth error: ' . $response);
        $this->lastError = 'OAuth error: ' . (json_decode($response, true)['error_description'] ?? substr($response, 0, 200));
        return null;
    }

    public function getSpreadsheetMeta(): ?array {
        $token = $this->getAccessToken();
        if (!$token) return null;

        $metaUrl = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s',
            urlencode($this->spreadsheetId)
        );
        $resp = $this->curlExec($metaUrl, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        ]);
        $data = json_decode($resp, true);
        if (isset($data['properties'])) {
            return $data;
        }
        $this->lastError = (string)($data['error']['message'] ?? $resp);
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
            } else {
                // Auto-retry jika range spesifik kolom ditolak karena grid limits (misal Sheet!A1:Z saat sheet hanya ada kolom A..K)
                $errMsg = (string)($data['error']['message'] ?? $response);
                $this->lastError = $errMsg;
                if (str_contains($range, '!') && (stripos($errMsg, 'grid limits') !== false || stripos($errMsg, 'exceeds') !== false || stripos($errMsg, 'Unable to parse range') !== false || stripos($errMsg, 'column') !== false)) {
                    $sheetOnly = explode('!', $range)[0];
                    $fallbackUrl = sprintf(
                        'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
                        urlencode($this->spreadsheetId),
                        urlencode($sheetOnly)
                    );
                    $fallbackResp = $this->curlExec($fallbackUrl, [
                        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
                    ]);
                    $fallbackData = json_decode($fallbackResp, true);
                    if (isset($fallbackData['values'])) {
                        return $fallbackData['values'];
                    }
                }
                error_log("Google Sheets getValues failed on '{$range}': " . substr($response, 0, 300));
                break;
            }
        }

        return [];
    }

    public function batchGetValues(array $ranges): array {
        if (empty($ranges)) return [];
        $token = $this->getAccessToken();
        if (!$token) return [];

        $queryParams = [];
        foreach ($ranges as $r) {
            $queryParams[] = 'ranges=' . urlencode($r);
        }
        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values:batchGet?%s',
            urlencode($this->spreadsheetId),
            implode('&', $queryParams)
        );

        $attempt = 0;
        $maxAttempts = 2;
        while ($attempt < $maxAttempts) {
            $attempt++;
            $response = $this->curlExec($url, [
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            ]);

            $data = json_decode($response, true);
            if (isset($data['valueRanges'])) {
                $result = [];
                foreach ($data['valueRanges'] as $vr) {
                    $rangeStr = (string)($vr['range'] ?? '');
                    $rawName = explode('!', $rangeStr)[0];
                    $sheetName = trim($rawName, "' ");
                    $result[$sheetName] = $vr['values'] ?? [];
                }
                return $result;
            }

            if (!empty($data['error']['code']) && $data['error']['code'] == 401) {
                self::$cachedAccessToken = null;
                $token = $this->getAccessToken();
                if (!$token) break;
            } else {
                error_log("Google Sheets batchGetValues failed: " . substr($response, 0, 300));
                break;
            }
        }

        return [];
    }

    public function appendValues(string $range, array $rows, string $valueInputOption = 'USER_ENTERED'): bool {
        $token = $this->getAccessToken();
        if (!$token) return false;

        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=%s',
            urlencode($this->spreadsheetId),
            urlencode($range),
            urlencode($valueInputOption)
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

        if (isset($data['updates'])) {
            return true;
        }

        // Auto-fix 1: Jika gagal karena batas kolom sheet (misal sheet hanya punya kolom A..K),
        // otomatis perluas kolom sheet menjadi minimal 26 kolom lalu coba append lagi
        $errMsg = (string)($data['error']['message'] ?? $response);
        if (stripos($errMsg, 'grid limits') !== false || stripos($errMsg, 'exceeds') !== false || stripos($errMsg, 'column') !== false) {
            $this->ensureMinColumns($sheetName, 26);
            $retryResp = $this->curlExec($url, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['values' => $rows]),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                ],
            ]);
            $retryData = json_decode($retryResp, true);
            if (isset($retryData['updates'])) {
                return true;
            }
        }

        // Fallback: Jika range spesifik kolom ditolak karena grid limit (misal Maintenance_Scan!A:R pada sheet kolom A..K),
        // coba append langsung ke nama sheet tanpa batas kolom
        if (str_contains($range, '!')) {
            $fallbackUrl = sprintf(
                'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED',
                urlencode($this->spreadsheetId),
                urlencode($sheetName)
            );
            $fallbackResp = $this->curlExec($fallbackUrl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['values' => $rows]),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                ],
            ]);
            $fallbackData = json_decode($fallbackResp, true);
            if (isset($fallbackData['updates'])) {
                return true;
            }
            error_log("Google Sheets appendValues fallback failed on '{$sheetName}': " . substr($fallbackResp, 0, 500));
        }

        $errMsg = (string)($data['error']['message'] ?? $response);
        $this->lastError = $errMsg;
        error_log("Google Sheets appendValues failed on '{$range}': " . substr($response, 0, 500));
        return false;
    }

    public function updateValues(string $range, array $rows, string $valueInputOption = 'USER_ENTERED'): bool {
        $token = $this->getAccessToken();
        if (!$token) return false;

        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?valueInputOption=%s',
            urlencode($this->spreadsheetId),
            urlencode($range),
            urlencode($valueInputOption)
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

        if (isset($data['updatedCells'])) {
            return true;
        }

        // Auto-fix: Jika gagal karena batas kolom sheet (misal Users!I2:K2 melebihi batas kolom sheet yang baru ada A..H),
        // otomatis perluas sheet minimal 26 kolom lalu coba update lagi
        $errMsg = (string)($data['error']['message'] ?? $response);
        $this->lastError = $errMsg;
        if (stripos($errMsg, 'grid limits') !== false || stripos($errMsg, 'exceeds') !== false || stripos($errMsg, 'column') !== false) {
            $this->ensureMinColumns($sheetName, 26);
            $retryResp = $this->curlExec($url, [
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => json_encode(['values' => $rows]),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                ],
            ]);
            $retryData = json_decode($retryResp, true);
            if (isset($retryData['updatedCells'])) {
                $this->clearCache($sheetName);
                return true;
            }
            $this->lastError = (string)($retryData['error']['message'] ?? $retryResp);
            error_log("Google Sheets updateValues retry failed on '{$range}': " . substr($retryResp, 0, 500));
        }

        error_log("Google Sheets updateValues failed on '{$range}': " . substr($response, 0, 500));
        return false;
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

    public function deleteRow(string $sheetName, int $rowNumber): bool {
        if ($rowNumber <= 1) return false;
        $token = $this->getAccessToken();
        if (!$token) return false;

        $metaUrl = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s?fields=sheets(properties(sheetId,title))',
            urlencode($this->spreadsheetId)
        );
        $resp = $this->curlExec($metaUrl, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        ]);
        $data = json_decode($resp, true);
        if (empty($data['sheets'])) return false;

        $sheetId = null;
        foreach ($data['sheets'] as $s) {
            $props = $s['properties'] ?? [];
            if (strcasecmp((string)($props['title'] ?? ''), $sheetName) === 0) {
                $sheetId = (int)($props['sheetId'] ?? 0);
                break;
            }
        }

        if ($sheetId === null) return false;

        $updateUrl = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s:batchUpdate',
            urlencode($this->spreadsheetId)
        );

        $body = [
            'requests' => [
                [
                    'deleteDimension' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'ROWS',
                            'startIndex' => $rowNumber - 1,
                            'endIndex' => $rowNumber
                        ]
                    ]
                ]
            ]
        ];

        $resp = $this->curlExec($updateUrl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);

        $resData = json_decode($resp, true);
        if (isset($resData['replies'])) {
            $this->clearCache($sheetName);
            return true;
        }

        return false;
    }

    private function getCacheFilePath(string $sheetName): string {
        $hash = md5($this->spreadsheetId) . '_' . md5($sheetName);
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gs_' . $hash . '.json';
    }

    public function clearCache(?string $sheetName = null): void {
        if ($sheetName) {
            unset(self::$runtimeCache[$sheetName]);
            if (session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION['_gs_cache_' . $sheetName], $_SESSION['_gs_time_' . $sheetName]);
            }
            $filePath = $this->getCacheFilePath($sheetName);
            if (file_exists($filePath)) {
                @unlink($filePath);
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
            $pattern = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gs_' . md5($this->spreadsheetId) . '_*.json';
            $files = @glob($pattern);
            if ($files) {
                foreach ($files as $f) @unlink($f);
            }
        }
    }

    public function parseSheetRows(string $sheetName, array $rows): array {
        if (empty($rows)) return [];

        $defaultHeaders = [
            'Cabang' => ['id', 'nama_cabang', 'alamat', 'telepon', 'penanggung_jawab'],
            'Divisi' => ['id', 'nama_divisi', 'keterangan'],
            'Karyawan' => ['id', 'nama_karyawan', 'cabang_id', 'divisi_id'],
            'Kategori_Aset' => ['id', 'nama_kategori'],
            'Assets' => ['id', 'kode_inventaris', 'merk', 'model', 'serial_number', 'id_kategori', 'id_cabang', 'id_divisi', 'id_karyawan', 'status', 'keterangan', 'ip_address', 'printer', 'created_at'],
            'Asset_QR_Tokens' => ['id', 'asset_id', 'token', 'label', 'is_active', 'created_at'],
            'Maintenance_Scan' => ['id', 'asset_id', 'technician_user_id', 'technician_name', 'maintenance_date', 'maintenance_time', 'maintenance_month', 'maintenance_year', 'status', 'source', 'created_at', 'findings', 'recommendation', 'biometric_verified', 'biometric_confidence', 'biometric_photo', 'latitude', 'longitude'],
            'Maintenance_Findings' => ['id', 'maintenance_scan_id', 'asset_id', 'kategori_temuan', 'deskripsi_temuan', 'tindakan_diperlukan', 'status', 'reported_by', 'reported_at', 'resolved_by', 'resolved_at', 'catatan_penyelesaian'],
            'Maintenance_Checklists' => ['id', 'maintenance_id', 'asset_id', 'checklist_number', 'checklist_name', 'checked', 'notes', 'created_at'],
            'Users' => ['id', 'username', 'password', 'nama', 'role', 'telepon', 'status', 'created_at', 'face_descriptor', 'face_photo', 'face_status', 'passkey_credential', 'nama_panggilan'],
            'inventaris_kartu' => ['id', 'nomor_rekening', 'nama_barang', 'tanggal_perolehan', 'barcode_data', 'lokasi', 'created_at'],
            'Inventaris_Kartu' => ['id', 'nomor_rekening', 'nama_barang', 'tanggal_perolehan', 'barcode_data', 'lokasi', 'created_at'],
        ];

        $firstRow = $rows[0];
        $firstCell = strtolower(trim((string)($firstRow[0] ?? '')));
        // Deteksi apakah baris 0 adalah header teks atau data langsung (misal Maintenance_Checklists yang tidak memiliki header)
        $isHeader = !is_numeric($firstCell) && ($firstCell === 'id' || $firstCell === 'no' || $firstCell === 'kode' || !empty($firstRow[0]));
        if ($sheetName === 'Maintenance_Checklists' && (is_numeric($firstCell) || empty($firstCell))) {
            $isHeader = false;
        }

        if ($isHeader) {
            $headers = $firstRow;
            // Lengkapi header jika baris 1 di sheet Google Sheet lebih sedikit dari defaultHeaders (misal sheet lama)
            if (!empty($defaultHeaders[$sheetName]) && count($headers) < count($defaultHeaders[$sheetName])) {
                $defH = $defaultHeaders[$sheetName];
                for ($hIdx = count($headers); $hIdx < count($defH); $hIdx++) {
                    $headers[] = $defH[$hIdx];
                }
            }
            $startIdx = 1;
        } else {
            $headers = $defaultHeaders[$sheetName] ?? [];
            $startIdx = 0;
        }

        $result = [];
        for ($i = $startIdx; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Abaikan jika seluruh sel dalam baris kosong
            $hasAnyValue = false;
            foreach ($row as $cellVal) {
                if (trim((string)$cellVal) !== '') {
                    $hasAnyValue = true;
                    break;
                }
            }
            if (!$hasAnyValue) {
                continue;
            }

            // Abaikan jika baris ini merupakan duplikat baris header
            $c0 = strtolower(trim((string)($row[0] ?? '')));
            if ($c0 === 'id' || $c0 === 'no') {
                continue;
            }
            if ($sheetName === 'Users') {
                $valNama = strtolower(trim((string)($row[3] ?? '')));
                $valRole = strtolower(trim((string)($row[4] ?? '')));
                if ($valNama === 'nama' || $valRole === 'role') {
                    continue;
                }
            }

            $obj = ['_row_num' => $i + 1];

            // Deteksi offset kolom jika baris bergeser ke kanan pada sheet manapun
            $colOffset = 0;
            if (empty($row[0])) {
                foreach ($row as $ci => $cv) {
                    if ($cv !== '' && $cv !== null) {
                        $colOffset = $ci;
                        break;
                    }
                }
            }

            // Simpan indeks kolom numerik (col_0, col_1, ...) dinormalisasi terhadap offset
            foreach ($row as $colIdx => $colVal) {
                $obj['col_' . $colIdx] = $colVal;
                if ($colOffset > 0 && $colIdx >= $colOffset) {
                    $normColIdx = $colIdx - $colOffset;
                    $obj['col_' . $normColIdx] = $colVal;
                }
            }

            foreach ($headers as $idx => $header) {
                $val = $row[$idx + $colOffset] ?? '';
                $normKey = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim((string)$header)));
                if ($sheetName === 'Assets' && in_array($normKey, ['kode_inventaris', 'kode'], true)) {
                    if (function_exists('normalize_kode_inventaris')) {
                        $val = normalize_kode_inventaris((string)$val);
                    }
                }
                // Simpan key asli
                $obj[$header] = $val;
                // Simpan key ternormalisasi (huruf kecil & tanpa spasi)
                if ($normKey !== '') {
                    $obj[$normKey] = $val;
                }
            }
            $result[] = $obj;
        }

        return $result;
    }

    /**
     * Batch Preload multiple sheets dalam 1 single HTTP request ke Google Cloud API
     */
    public function preloadSheets(array $sheetNames, bool $forceRefresh = false): array {
        $needed = [];
        $results = [];

        foreach ($sheetNames as $sheetName) {
            // 1. Cek runtime memory cache
            if (!$forceRefresh && isset(self::$runtimeCache[$sheetName])) {
                $results[$sheetName] = self::$runtimeCache[$sheetName];
                continue;
            }

            // Tiered cache TTL: 120s scan/checklist, 300s assets, 900s master data
            $ttl = in_array($sheetName, ['Maintenance_Scan', 'Maintenance_Checklists'], true) ? 120 : (in_array($sheetName, ['Assets'], true) ? 300 : 900);

            // 2. Cek warm disk cache di /tmp
            if (!$forceRefresh) {
                $filePath = $this->getCacheFilePath($sheetName);
                if (file_exists($filePath) && (time() - filemtime($filePath) < $ttl)) {
                    $cachedContent = @file_get_contents($filePath);
                    if ($cachedContent) {
                        $cachedJson = json_decode($cachedContent, true);
                        if (is_array($cachedJson)) {
                            self::$runtimeCache[$sheetName] = $cachedJson;
                            $results[$sheetName] = $cachedJson;
                            continue;
                        }
                    }
                }
            }

            $needed[] = $sheetName;
        }

        if (!empty($needed)) {
            // Fetch all missing sheets in ONE single batchGet call!
            $batchData = count($needed) > 1 ? $this->batchGetValues($needed) : [];

            foreach ($needed as $name) {
                $rows = [];
                // Search in batchData case-insensitively
                foreach ($batchData as $bKey => $bRows) {
                    if (strcasecmp($bKey, $name) === 0) {
                        $rows = $bRows;
                        break;
                    }
                }

                // Fallback jika batch kosong atau pemanggilan tunggal
                if (empty($rows)) {
                    $rows = $this->getValues($name);
                    if (empty($rows)) {
                        $rows = $this->getValues($name . '!A1:Z');
                    }
                }

                $parsed = $this->parseSheetRows($name, $rows);
                // Jika API gagal (rate limit), fallback ke disk cache yang ada jika tersedia
                if (empty($parsed)) {
                    $filePath = $this->getCacheFilePath($name);
                    if (file_exists($filePath)) {
                        $cached = json_decode((string)@file_get_contents($filePath), true);
                        if (is_array($cached)) $parsed = $cached;
                    }
                }

                self::$runtimeCache[$name] = $parsed;
                @file_put_contents($this->getCacheFilePath($name), json_encode($parsed));
                $results[$name] = $parsed;
            }
        }

        return $results;
    }

    public function getSheetData(string $sheetName, bool $forceRefresh = false): array {
        $data = $this->preloadSheets([$sheetName], $forceRefresh);
        return $data[$sheetName] ?? [];
    }

    /**
     * Pastikan tab sheet memiliki minimal N kolom (default 26: A..Z)
     */
    public function ensureMinColumns(string $sheetName, int $minColumns = 26): bool {
        static $checkedColumns = [];
        if (!empty($checkedColumns[$sheetName])) return true;

        $token = $this->getAccessToken();
        if (!$token) return false;

        $metaUrl = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s?fields=sheets(properties(sheetId,title,gridProperties))',
            urlencode($this->spreadsheetId)
        );
        $resp = $this->curlExec($metaUrl, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        ]);
        $data = json_decode($resp, true);
        if (empty($data['sheets'])) return false;

        foreach ($data['sheets'] as $sheet) {
            $props = $sheet['properties'] ?? [];
            if (strcasecmp((string)($props['title'] ?? ''), $sheetName) === 0) {
                $curCols = (int)($props['gridProperties']['columnCount'] ?? 0);
                $sheetId = (int)($props['sheetId'] ?? 0);
                if ($curCols < $minColumns) {
                    $updateUrl = sprintf(
                        'https://sheets.googleapis.com/v4/spreadsheets/%s:batchUpdate',
                        urlencode($this->spreadsheetId)
                    );
                    $this->curlExec($updateUrl, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode([
                            'requests' => [[
                                'updateSheetProperties' => [
                                    'properties' => [
                                        'sheetId' => $sheetId,
                                        'gridProperties' => [
                                            'columnCount' => $minColumns
                                        ]
                                    ],
                                    'fields' => 'gridProperties.columnCount'
                                ]
                            ]]
                        ]),
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $token,
                            'Content-Type: application/json',
                        ],
                    ]);
                }
                $checkedColumns[$sheetName] = true;
                return true;
            }
        }
        return false;
    }

    /**
     * Buat sheet/tab baru jika belum ada
     */
    public function createSheetIfNotExists(string $sheetName): bool {
        static $checkedSheets = [];
        if (!empty($checkedSheets[$sheetName])) return true;
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['_gs_sheet_exists_' . $sheetName])) {
            $checkedSheets[$sheetName] = true;
            return true;
        }

        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gs_exist_' . md5($this->spreadsheetId . '_' . $sheetName);
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $checkedSheets[$sheetName] = true;
            return true;
        }

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
                        'properties' => [
                            'title' => $sheetName,
                            'gridProperties' => [
                                'rowCount' => 1000,
                                'columnCount' => 26
                            ]
                        ]
                    ]
                ]]
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);

        $checkedSheets[$sheetName] = true;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_gs_sheet_exists_' . $sheetName] = true;
        }
        @file_put_contents($cacheFile, '1');
        return true;
    }
}

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $data): string {
        return (string)base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
