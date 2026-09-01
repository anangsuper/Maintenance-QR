<?php
declare(strict_types=1);

/**
 * GOOGLE SHEETS API V4 CLIENT (DIRECT GOOGLE CLOUD API)
 * Menggunakan Google Cloud Service Account (OAuth2 JWT)
 */
class GoogleSheetsV4Client {
    private string $spreadsheetId;
    private string $clientEmail;
    private string $privateKey;
    private static ?string $cachedAccessToken = null;

    public function __construct(string $spreadsheetId, string $clientEmail, string $privateKey) {
        $this->spreadsheetId = trim($spreadsheetId);
        $this->clientEmail = trim($clientEmail);
        $this->privateKey = str_replace(['\\n', "\r"], ["\n", ''], trim($privateKey));
    }

    private function getAccessToken(): ?string {
        if (self::$cachedAccessToken !== null) {
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

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string)$response, true);
        if (!empty($data['access_token'])) {
            self::$cachedAccessToken = (string)$data['access_token'];
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

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string)$response, true);
        return $data['values'] ?? [];
    }

    public function appendValues(string $range, array $rows): bool {
        $token = $this->getAccessToken();
        if (!$token) return false;

        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED',
            urlencode($this->spreadsheetId),
            urlencode($range)
        );

        $payload = json_encode(['values' => $rows]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string)$response, true);
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

        $payload = json_encode(['values' => $rows]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string)$response, true);
        return isset($data['updatedCells']);
    }

    public function getSheetData(string $sheetName): array {
        $rows = $this->getValues($sheetName . '!A1:Z1000');
        if (count($rows) <= 1) return [];

        $headers = $rows[0];
        $result = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $obj = ['_row_num' => $i + 1];
            foreach ($headers as $idx => $header) {
                $obj[$header] = $row[$idx] ?? '';
            }
            $result[] = $obj;
        }
        return $result;
    }
}

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
