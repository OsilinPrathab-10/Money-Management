<?php

namespace App\Services;

use App\Models\ApiConfiguration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generic CIBIL / credit-bureau HTTP integration.
 * Point base_url + endpoint to your partner API; map score with score_json_path (dot notation).
 * When disabled or on failure, returns structured demo data so the UI still works.
 */
class CibilApiService
{
    /**
     * @param  array{client_id?: int|null, applicant_name: string, pan_number?: string|null, phone?: string|null, date_of_birth?: string|null}  $input
     * @return array{score: int|null, rating: string|null, report: array, raw: mixed, status: string, error_message: ?string, is_demo: bool}
     */
    public function fetchReport(array $input): array
    {
        $config = ApiConfiguration::where('service', 'cibil')->where('is_enabled', true)->first();

        if (! $config || empty($config->credentials['base_url'])) {
            return $this->demoPayload($input, 'API not configured — showing sample data.');
        }

        $c = $config->credentials;
        $base = rtrim((string) $c['base_url'], '/');
        $endpoint = (string) ($c['endpoint'] ?? '/credit-report');
        $url = str_starts_with($endpoint, 'http') ? $endpoint : $base . (str_starts_with($endpoint, '/') ? $endpoint : '/' . $endpoint);

        $payload = [
            'name' => $input['applicant_name'],
            'aadhar' => $input['aadhar_number'] ?? null,
            'email' => $input['email'] ?? null,
            'pan' => $input['pan_number'] ?? null,
            'phone' => $input['phone'] ?? null,
            'dob' => $input['date_of_birth'] ?? null,
            'client_id' => $input['client_id'] ?? null,
        ];

        try {
            $request = Http::timeout(60)
                ->acceptJson()
                ->asJson();

            $auth = $c['auth_type'] ?? 'bearer';
            if ($auth === 'bearer' && ! empty($c['api_key'])) {
                $request = $request->withToken((string) $c['api_key']);
            } elseif ($auth === 'basic') {
                $request = $request->withBasicAuth((string) ($c['api_key'] ?? ''), (string) ($c['api_secret'] ?? ''));
            } else {
                $request = $request->withHeaders(array_filter([
                    'X-API-Key' => $c['api_key'] ?? null,
                    'X-API-Secret' => $c['api_secret'] ?? null,
                ]));
            }

            $method = strtoupper((string) ($c['http_method'] ?? 'POST'));
            $response = $method === 'GET'
                ? $request->get($url, $payload)
                : $request->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('CIBIL API HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->demoPayload($input, 'API error (' . $response->status() . '). Using sample data.');
            }

            $json = $response->json();
            $path = (string) ($c['score_json_path'] ?? 'score');
            $scoreRaw = Arr::get($json, $path);
            $score = is_numeric($scoreRaw) ? (int) round((float) $scoreRaw) : null;
            $rating = $this->ratingFromScore($score);

            return [
                'score' => $score,
                'rating' => $rating,
                'report' => is_array($json) ? $json : ['raw' => $json],
                'raw' => $json,
                'status' => 'success',
                'error_message' => null,
                'is_demo' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('CIBIL API exception', ['message' => $e->getMessage()]);

            return $this->demoPayload($input, $e->getMessage());
        }
    }

    /**
     * @return array{score: int|null, rating: string|null, report: array, raw: array, status: string, error_message: ?string, is_demo: bool}
     */
    protected function demoPayload(array $input, ?string $note = null): array
    {
        $hash = crc32(($input['pan_number'] ?? '') . ($input['applicant_name'] ?? '') . ($input['phone'] ?? ''));
        $score = 650 + ($hash % 150);

        return [
            'score' => $score,
            'rating' => $this->ratingFromScore($score),
            'report' => [
                'demo' => true,
                'message' => $note ?? 'Demo data',
                'applicant' => $input['applicant_name'],
                'generated_at' => now()->toIso8601String(),
            ],
            'raw' => ['demo' => true],
            'status' => 'demo',
            'error_message' => $note,
            'is_demo' => true,
        ];
    }

    protected function ratingFromScore(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }
        if ($score >= 750) {
            return 'Excellent';
        }
        if ($score >= 700) {
            return 'Good';
        }
        if ($score >= 650) {
            return 'Fair';
        }

        return 'Needs improvement';
    }
}
