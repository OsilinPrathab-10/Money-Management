<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Client;
use App\Models\User;
use App\Models\UserDevice;
use GuzzleHttp\Client as HttpClient;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    protected HttpClient $http;

    protected GoogleClient $gclient;

    protected string $projectId;

    protected bool $credentialsLoaded = false;

    public function __construct()
    {
        $this->http = new HttpClient(['http_errors' => false, 'timeout' => 5, 'connect_timeout' => 3]);
        $this->gclient = new GoogleClient();
        $this->projectId = (string) config('firebase.project_id', config('services.firebase.project_id', ''));

        $credentialsPath = $this->resolveCredentialsPath();
        if ($credentialsPath) {
            $this->gclient->setAuthConfig($credentialsPath);
            $this->gclient->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $this->credentialsLoaded = true;

            if ($this->projectId === '') {
                $json = json_decode((string) file_get_contents($credentialsPath), true);
                $this->projectId = (string) ($json['project_id'] ?? '');
            }
        }
    }

    public function isConfigured(): bool
    {
        return $this->credentialsLoaded && $this->projectId !== '';
    }

    /**
     * Send notifications via FCM HTTP v1.
     * $deviceToken: string token OR array of tokens.
     */
    public function sendPushNotification($deviceToken, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'status' => 0, 'error' => 'Firebase is not configured. Check FIREBASE_CREDENTIALS and FIREBASE_PROJECT_ID.'];
        }

        $tokens = $this->sanitizeTokens($deviceToken);
        if (empty($tokens)) {
            return ['success' => false, 'status' => 0, 'error' => 'No valid device tokens'];
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return ['success' => false, 'status' => 0, 'error' => 'Failed to acquire OAuth2 access token'];
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
                                'sound' => 'default',
                                'content-available' => 1,
                            ],
                        ],
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                    ],
                ],
            ];

            if (! empty($data)) {
                $payload['message']['data'] = $this->stringifyData($data);
            }

            $res = $this->http->post($url, ['headers' => $headers, 'json' => $payload]);
            $status = $res->getStatusCode();
            $json = json_decode((string) $res->getBody(), true);

            $ok = $status === 200 && isset($json['name']);
            if (! $ok) {
                $err = $json['error']['status'] ?? ($json['error']['message'] ?? '');
                if ($status === 404 || in_array($err, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
                    $invalid[] = $t;
                }
                $allOk = false;
                Log::warning('FCM send failed', [
                    'status' => $status,
                    'error' => $err,
                    'token_suffix' => substr($t, -8),
                ]);
            }

            $statuses[] = $status;
            $results[] = [
                'token' => $t,
                'ok' => $ok,
                'status' => $status,
                'response' => $json,
            ];
        }

        $this->forgetInvalidTokens($invalid);

        return [
            'success' => $allOk,
            'status' => end($statuses) ?: 0,
            'results' => $results,
            'invalid_tokens' => array_values(array_unique($invalid)),
        ];
    }

    public function sendDataMessage($deviceToken, array $data = []): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'status' => 0, 'error' => 'Firebase is not configured'];
        }

        $tokens = $this->sanitizeTokens($deviceToken);
        if (empty($tokens)) {
            return ['success' => false, 'status' => 0, 'error' => 'No valid device tokens'];
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
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
            if (! $ok) {
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

    public function sendToUser(int $userId, string $title, string $body, string $type = 'general', array $data = []): array
    {
        $user = User::with(['userDevice', 'client', 'agent'])->find($userId);
        if (! $user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $tokens = $this->tokensForUser($user);
        if (empty($tokens)) {
            Log::info('No FCM tokens for user push notification', ['user_id' => $userId, 'type' => $type]);

            return ['success' => false, 'error' => 'No devices registered'];
        }

        return $this->sendPushNotification($tokens, $title, $body, array_merge($data, [
            'type' => $type,
            'user_id' => (string) $userId,
        ]));
    }

    public function sendToCustomer(Client $client, string $title, string $body, string $type = 'general', array $data = []): array
    {
        $client->loadMissing('user.userDevice');
        $tokens = [(string) $client->fcm_token];

        if ($client->user) {
            $tokens = array_merge($tokens, $this->tokensForUser($client->user));
        }

        $tokens = $this->sanitizeTokens($tokens);
        if (empty($tokens)) {
            Log::info('No FCM tokens for customer push notification', [
                'client_id' => $client->id,
                'user_id' => $client->user_id,
                'type' => $type,
            ]);

            return ['success' => false, 'error' => 'No devices registered'];
        }

        return $this->sendPushNotification($tokens, $title, $body, array_merge($data, [
            'type' => $type,
            'audience' => 'customer',
            'client_id' => (string) $client->id,
        ]));
    }

    public static function isLikelyFcmToken(?string $token): bool
    {
        $token = trim((string) $token);
        if ($token === '' || preg_match('/\s/', $token) || strlen($token) < 40) {
            return false;
        }

        // Sanctum personal access tokens look like "12|abc..." — not FCM.
        if (preg_match('/^\d+\|/', $token)) {
            return false;
        }

        return true;
    }

    public function sendToAgent(Agent $agent, string $title, string $body, string $type = 'general', array $data = []): array
    {
        $user = $agent->user;
        if ($user) {
            return $this->sendToUser($user->id, $title, $body, $type, array_merge($data, [
                'audience' => 'agent',
                'agent_id' => (string) $agent->id,
            ]));
        }

        return ['success' => false, 'error' => 'Agent user not found'];
    }

    /**
     * @return list<string>
     */
    public function tokensForUser(User $user): array
    {
        $deviceTokens = UserDevice::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->whereNotNull('device_token')
            ->where('device_token', '!=', '')
            ->orderByDesc('updated_at')
            ->pluck('device_token')
            ->all();

        $tokens = [];
        if (! empty($deviceTokens)) {
            $tokens[] = $deviceTokens[0];
        }

        if (! empty($user->fcm_token)) {
            $tokens[] = $user->fcm_token;
        }

        if (! empty(optional($user->client)->fcm_token)) {
            $tokens[] = $user->client->fcm_token;
        }

        return $this->sanitizeTokens($tokens);
    }

    protected function resolveCredentialsPath(): ?string
    {
        $configured = (string) config('firebase.credentials', config('services.firebase.credentials', ''));
        $candidates = array_filter([
            $configured,
            storage_path('app/firebase/fintronixmicrofinance-c2656-firebase-adminsdk-fbsvc-7d456d1b6f.json'),
            base_path('fintronixmicrofinance-c2656-firebase-adminsdk-fbsvc-7d456d1b6f.json'),
            storage_path('app/firebase/codepluse-gen-pvt-ltd-firebase-adminsdk-fbsvc-0bc81559d6.json'),
        ]);

        foreach ($candidates as $path) {
            if ($path === '') {
                continue;
            }
            if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
                $path = base_path($path);
            }
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        Log::error('Firebase Admin SDK credentials file not found');

        return null;
    }

    protected function forgetInvalidTokens(array $tokens): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            return;
        }

        UserDevice::whereIn('device_token', $tokens)->update(['device_token' => null]);
        User::whereIn('fcm_token', $tokens)->update(['fcm_token' => null]);
        Client::whereIn('fcm_token', $tokens)->update(['fcm_token' => null]);
    }

    private function getAccessToken(): ?string
    {
        try {
            $this->gclient->fetchAccessTokenWithAssertion();
            $token = $this->gclient->getAccessToken();

            return $token['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Firebase OAuth token failed: ' . $e->getMessage());

            return null;
        }
    }

    private function sanitizeTokens($deviceToken): array
    {
        $arr = is_array($deviceToken) ? $deviceToken : [$deviceToken];
        $out = [];
        foreach ($arr as $t) {
            if (! is_string($t)) {
                continue;
            }
            $t = trim($t);
            if (! self::isLikelyFcmToken($t)) {
                continue;
            }
            $out[$t] = true;
        }

        return array_keys($out);
    }

    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v) || is_object($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            } elseif ($v === null) {
                $v = '';
            }
            $out[(string) $k] = (string) $v;
        }

        return $out;
    }
}
