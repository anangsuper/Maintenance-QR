<?php
declare(strict_types=1);

/**
 * Client HTTP untuk berkomunikasi dengan Google Apps Script Web App API
 */
class SpreadsheetApiClient {
    private string $apiUrl;

    public function __construct(string $apiUrl) {
        $this->apiUrl = $apiUrl;
    }

    public function get(string $action, array $params = []): array {
        $params['action'] = $action;
        $url = $this->apiUrl . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => 'Curl Error: ' . $err];
        }

        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'Invalid JSON from Apps Script: ' . $response];
    }

    public function post(string $action, array $data = []): array {
        $data['action'] = $action;
        $payload = json_encode($data);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => 'Curl Error: ' . $err];
        }

        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'Invalid JSON response: ' . $response];
    }
}
