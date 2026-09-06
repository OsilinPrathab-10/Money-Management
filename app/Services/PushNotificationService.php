<?php

namespace App\Services;

use GuzzleHttp\Client;
use Google\Client as GoogleClient;

class PushNotificationService
{
    protected Client $http;
    protected GoogleClient $gclient;
    protected string $projectId;
 
    
    public function __construct()
    {
        $this->http = new Client(['http_errors' => false, 'timeout' => 15]);

        // 1. Get the path
        $credentialsPath = storage_path('app/firebase/companynameop-firebase-adminsdk-fbsvc-0bc81559d6.json');
        
        $this->gclient = new GoogleClient();

        // 2. ONLY attempt to set AuthConfig if the file actually exists
        if (file_exists($credentialsPath) && is_readable($credentialsPath)) {
            $this->gclient->setAuthConfig($credentialsPath);
            $this->gclient->setScopes(['https://www.googleapis.com/auth/firebase.messaging']);
        }

        // 3. Use config() instead of env() for better performance/caching
        $this->projectId = (string) env('FIREBASE_PROJECT_ID', '');
    }

    /**
     * Send notifications via FCM HTTP v1.
     * $deviceToken: string token OR array of tokens. v1 requires one token per request (we loop).
     */
    public function sendPushNotification($deviceToken, string $title, string $body, array $data = []): array
    {
        if ($this->projectId === '') {
            return ['success' => false, 'status' => 0, 'error' => 'Missing FIREBASE_PROJECT_ID'];
        }

        $tokens = $this->sanitizeTokens($deviceToken);
        if (empty($tokens)) {
            return ['success' => false, 'status' => 0, 'error' => 'No valid device tokens'];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'status' => 0, 'error' => 'Failed to acquire OAuth2 access token (check FIREBASE_CREDENTIALS path and JSON)'];
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        $results = [];
        $invalid = [];
        $statuses = [];
        $allOk = true;

        foreach ($tokens as $t) {
            $payload = [
                'message' => [
                    'token' => $t,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'android' => [
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'content-available' => 1,
                            ],
                        ],
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                    ],
                ],
            ];

            if (!empty($data)) {
                $payload['message']['data'] = $this->stringifyData($data);
            }

            $res = $this->http->post($url, ['headers' => $headers, 'json' => $payload]);
            $status = $res->getStatusCode();
            $json = json_decode((string) $res->getBody(), true);

            $ok = $status === 200 && isset($json['name']);
            if (!$ok) {
                $err = $json['error']['status'] ?? ($json['error']['message'] ?? '');
                // UNREGISTERED/INVALID_ARGUMENT/NOT_FOUND => stale/bad token (prune)
                if ($status === 404 || in_array($err, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
                    $invalid[] = $t;
                }
                $allOk = false;
            }

            $statuses[] = $status;
            $results[] = [
                'token' => $t,
                'ok' => $ok,
                'status' => $status,
                'response' => $json,
            ];
        }

        return [
            'success' => $allOk,
            'status' => end($statuses) ?: 0,
            'results' => $results,
            'invalid_tokens' => array_values(array_unique($invalid)),
        ];
    }

    /**
     * Send high-priority data messages via FCM HTTP v1.
     * $deviceToken: string token OR array of tokens.
     */
    public function sendDataMessage($deviceToken, array $data = []): array
    {
        if ($this->projectId === '') {
            return ['success' => false, 'status' => 0, 'error' => 'Missing FIREBASE_PROJECT_ID'];
        }

        $tokens = $this->sanitizeTokens($deviceToken);
        if (empty($tokens)) {
            return ['success' => false, 'status' => 0, 'error' => 'No valid device tokens'];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'status' => 0, 'error' => 'Failed to acquire OAuth2 access token'];
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        $results = [];
        $allOk = true;

        foreach ($tokens as $t) {
            $payload = [
                'message' => [
                    'token' => $t,
                    'data' => $this->stringifyData($data),
                    'android' => [
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'content-available' => 1,
                            ],
                        ],
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                    ],
                ],
            ];

            $res = $this->http->post($url, ['headers' => $headers, 'json' => $payload]);
            $status = $res->getStatusCode();
            $json = json_decode((string) $res->getBody(), true);

            $ok = $status === 200 && isset($json['name']);
            if (!$ok) {
                $allOk = false;
            }

            $results[] = [
                'token' => $t,
                'ok' => $ok,
                'status' => $status,
                'response' => $json,
            ];
        }

        return [
            'success' => $allOk,
            'results' => $results,
        ];
    }

    private function getAccessToken(): ?string
    {
        try {
            $this->gclient->fetchAccessTokenWithAssertion();
            $token = $this->gclient->getAccessToken();
            return $token['access_token'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function sanitizeTokens($deviceToken): array
    {
        $arr = is_array($deviceToken) ? $deviceToken : [$deviceToken];
        $out = [];
        foreach ($arr as $t) {
            if (!is_string($t))
                continue;
            $t = trim($t);
            if ($t === '')
                continue;
            if (preg_match('/\s/', $t))
                continue;  // no spaces
            if (strlen($t) < 80)
                continue;         // simple length heuristic
            $out[$t] = true;                       // dedup
        }
        return array_keys($out);
    }
    // In PushNotificationService

    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v) || is_object($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            } elseif ($v === null) {
                $v = ''; // or omit the key instead of empty string
            }
            $out[(string) $k] = (string) $v;
        }
        return $out;
    }

}
