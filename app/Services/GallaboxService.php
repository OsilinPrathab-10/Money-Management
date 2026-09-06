<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GallaboxService
{
    /**
     * Fetch approved WhatsApp templates from Gallabox.
     *
     * Gallabox endpoint: GET https://server.gallabox.com/devapi/accounts/{accountId}/whatsappTemplates
     * Headers: apikey, apiSecret
     */
    public function fetchTemplates(): array
    {
        $accountId = (string) config('services.gallabox.account_id');
        $apiKey = (string) config('services.gallabox.api_key');
        $apiSecret = (string) config('services.gallabox.api_secret');

        if ($accountId === '') {
            throw new \RuntimeException('Gallabox account/workspace ID is not configured.');
        }
        if ($apiKey === '' || $apiSecret === '') {
            throw new \RuntimeException('Gallabox API key/secret is not configured.');
        }

        $url = "https://server.gallabox.com/devapi/accounts/{$accountId}/whatsappTemplates";

        $response = Http::withHeaders([
            'apikey' => $apiKey,
            'apiSecret' => $apiSecret,
            'Accept' => 'application/json',
        ])->get($url);

        Log::info('Gallabox fetchTemplates response', [
            'url' => $url,
            'status_code' => $response->status(),
            'body' => $response->body(),
        ]);

        if (!$response->successful()) {
            $msg = $response->json('message') ?? $response->body();
            throw new \RuntimeException("Gallabox API error ({$response->status()}): {$msg}");
        }

        $templates = $response->json();
        if (!is_array($templates)) {
            throw new \RuntimeException('Unexpected Gallabox templates response.');
        }

        // Normalize to lightweight list the UI expects
        return collect($templates)
            ->filter(fn ($t) => is_array($t))
            ->map(function (array $t) {
                return [
                    'name' => $t['name'] ?? null,
                    'language' => $t['language'] ?? 'en',
                    'status' => $t['status'] ?? null,
                ];
            })
            ->filter(fn ($t) => !empty($t['name']))
            ->values()
            ->all();
    }

    /**
     * Send WhatsApp template message via Gallabox
     */
    public function sendTemplateMessage($phone, $agentName, $agentCode)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.gallabox.api_key'),
                'Accept' => 'application/json',
            ])->post(config('services.gallabox.api_url'), [
                'phone' => $phone,
                'channel_id' => config('services.gallabox.channel_id'),
                'template' => [
                    'name' => 'kyc_rejected', // your approved template name
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $agentName],
                                ['type' => 'text', 'text' => $agentCode],
                            ]
                        ]
                    ]
                ]
            ]);

            Log::info('Gallabox WhatsApp Response', [
                'phone' => $phone,
                'status_code' => $response->status(),
                'body' => $response->body(),
                'json' => $response->json()
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Gallabox WhatsApp Error', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);

            return null;
        }
    }

    protected static function sendMessage($data)
    {
        try {
            $response = Http::withHeaders([
                'apiKey' => env('GALLABOX_API_KEY'),
                'apiSecret' => env('GALLABOX_API_SECRET'),
                'Content-Type' => 'application/json',
            ])->post('https://server.gallabox.com/devapi/messages/whatsapp', $data);
//dd($response);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Gallabox WhatsApp Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Send a plain-text WhatsApp session message (requires Gallabox channel + approved session / policy).
     */
    public function sendTextMessage(string $phone, string $recipientName, string $body): array
    {
        $channelId = (string) config('services.gallabox.channel_id');
        $apiKey = (string) config('services.gallabox.api_key');
        $apiSecret = (string) config('services.gallabox.api_secret');

        if ($channelId === '' || $apiKey === '' || $apiSecret === '') {
            return ['error' => 'Gallabox WhatsApp (channel / api key / secret) is not configured in .env or services.'];
        }

        $digits = preg_replace('/\D/', '', $phone);
        $digits = substr($digits, -10);
        if (strlen($digits) !== 10) {
            return ['error' => 'Invalid phone number (need 10 digits).'];
        }

        $data = [
            'channelId' => $channelId,
            'channelType' => 'whatsapp',
            'recipient' => [
                'name' => $recipientName !== '' ? $recipientName : 'Customer',
                'phone' => '91' . $digits,
            ],
            'whatsapp' => [
                'type' => 'text',
                'text' => ['body' => $body],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'apiKey' => $apiKey,
                'apiSecret' => $apiSecret,
                'Content-Type' => 'application/json',
            ])->post('https://server.gallabox.com/devapi/messages/whatsapp', $data);

            Log::info('Gallabox text message', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (! $response->successful()) {
                return [
                    'error' => $response->json('message') ?? $response->body(),
                    'status' => $response->status(),
                ];
            }

            return $response->json() ?? ['success' => true];
        } catch (\Throwable $e) {
            Log::error('Gallabox text message failed', ['message' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }
}
