<?php

namespace App\Services;

use App\Models\CreditScoreHistory;
use App\Models\VerificationApiHit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ApiHubCibilService
{
    /**
     * @param  array{pan: string, name: string, mobile: string, gender: string}  $input
     * @return array{
     *     ok: bool,
     *     status: string,
     *     http_status: int,
     *     response: ?array,
     *     error_message: ?string,
     *     is_mock: bool,
     *     score: ?int,
     *     rating: ?string,
     *     doc_id: ?string,
     *     credit_report_link: ?string,
     *     link_expires_at: ?\Carbon\Carbon,
     *     consolidated_pdf_url: ?string,
     *     email: ?string
     * }
     */
    public function fetchReport(array $input): array
    {
        $path = config('cibil.path', config('services.apihub.cibil_path', 'v3/cibil'));
        $isMock = (bool) config('cibil.mock', false);

        $payload = [
            'pan' => $input['pan'],
            'name' => $input['name'],
            'mobile' => $input['mobile'],
            'gender' => $input['gender'],
        ];

        if ($isMock) {
            $json = $this->loadMockResponse();
            $parsed = $this->parseBureauResponse($json, true);

            $this->recordHit($path, $parsed['ok'], 200, $payload, $json);

            return $parsed;
        }

        $baseUrl = $this->httpsBaseUrl((string) config('cibil.base_url'));
        $url = $baseUrl . '/' . ltrim($path, '/');

        try {
            $response = Http::timeout(90)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-Client-Api-ID' => (string) config('cibil.client_id'),
                    'X-Client-Api-Secret' => (string) config('cibil.client_secret'),
                ])
                ->post($url, $payload);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['success' => false, 'message' => $response->body()];
            }

            $parsed = $this->parseBureauResponse($json, false, $response->successful() ? null : 'HTTP ' . $response->status());

            if (! $parsed['ok'] && $parsed['error_message'] === null) {
                $parsed['error_message'] = is_string($json['message'] ?? null)
                    ? $json['message']
                    : 'CIBIL bureau request failed.';
            }

            $parsed['http_status'] = $response->status();
            $this->recordHit($path, $parsed['ok'], $response->status(), $payload, $json);

            return $parsed;
        } catch (\Throwable $e) {
            Log::error('ApiHub CIBIL exception', ['message' => $e->getMessage()]);
            $this->recordHit($path, false, 0, $payload, ['message' => $e->getMessage()]);

            return [
                'ok' => false,
                'status' => 'failed',
                'http_status' => 0,
                'response' => null,
                'error_message' => $e->getMessage(),
                'is_mock' => false,
                'score' => null,
                'rating' => null,
                'doc_id' => null,
                'credit_report_link' => null,
                'link_expires_at' => null,
                'consolidated_pdf_url' => null,
                'email' => null,
            ];
        }
    }

    public function downloadTransUnionPdf(CreditScoreHistory $history): array
    {
        if ($history->is_mock || empty($history->credit_report_link)) {
            return ['saved' => false, 'error' => 'No bureau PDF link available.'];
        }

        if ($history->link_expires_at && $history->link_expires_at->isPast()) {
            return ['saved' => false, 'error' => 'Signed PDF link has expired.'];
        }

        try {
            $response = Http::timeout(120)->get($history->credit_report_link);
            if (! $response->successful()) {
                return ['saved' => false, 'error' => 'Download failed (HTTP ' . $response->status() . ').'];
            }

            $body = $response->body();
            if (! str_starts_with($body, '%PDF')) {
                return ['saved' => false, 'error' => 'Response is not a valid PDF.'];
            }

            $relative = $this->pdfRelativePath($history);
            Storage::disk('local')->put($relative, $body);

            return [
                'saved' => true,
                'path' => $relative,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('CIBIL PDF download failed', ['id' => $history->id, 'error' => $e->getMessage()]);

            return ['saved' => false, 'error' => $e->getMessage()];
        }
    }

    public function pdfRelativePath(CreditScoreHistory $history): string
    {
        $folder = $history->client_id
            ? 'cibil-reports/client_' . $history->client_id
            : 'cibil-reports/unlinked';

        return $folder . '/cibil_' . $history->id . '_' . now()->format('Ymd_His') . '.pdf';
    }

    public function ratingFromScore(?int $score): ?string
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

        return 'Below Average';
    }

    public function scoreColor(?int $score): string
    {
        if ($score === null) {
            return 'secondary';
        }
        if ($score >= 750) {
            return 'success';
        }
        if ($score >= 700) {
            return 'info';
        }
        if ($score >= 650) {
            return 'warning';
        }

        return 'danger';
    }

    protected function parseBureauResponse(array $json, bool $isMock, ?string $transportError = null): array
    {
        $cibilOk = ($json['success'] ?? false) === true
            && Arr::get($json, 'data.results.cibil.success') === true;

        $ok = $transportError === null && $cibilOk;

        $score = $this->extractScore($json);
        $rating = Arr::get($json, 'data.analysis.scores.cibil.rating');
        if (! is_string($rating) || $rating === '') {
            $rating = $this->ratingFromScore($score);
        }

        $creditReportLink = Arr::get($json, 'data.results.cibil.credit_report_link')
            ?: Arr::get($json, 'data.results.cibil.data.credit_report_link');

        $linkExpires = is_string($creditReportLink)
            ? $this->parseSignedLinkExpiry($creditReportLink)
            : null;

        $error = null;
        if (! $ok) {
            $error = $transportError
                ?: Arr::get($json, 'data.results.cibil.message')
                ?: ($json['message'] ?? 'CIBIL report failed.');
        }

        return [
            'ok' => $ok,
            'status' => $ok ? 'success' : 'failed',
            'http_status' => (int) ($json['status_code'] ?? ($ok ? 200 : 422)),
            'response' => $json,
            'error_message' => $error,
            'is_mock' => $isMock,
            'score' => $score,
            'rating' => $rating,
            'doc_id' => Arr::get($json, 'data.doc_id'),
            'credit_report_link' => is_string($creditReportLink) ? $creditReportLink : null,
            'link_expires_at' => $linkExpires,
            'consolidated_pdf_url' => Arr::get($json, 'data.pdf_url'),
            'email' => Arr::get($json, 'data.results.cibil.data.user_email'),
        ];
    }

    protected function extractScore(array $json): ?int
    {
        $candidates = [
            Arr::get($json, 'data.results.cibil.data.credit_score'),
            Arr::get($json, 'data.analysis.scores.cibil.value'),
        ];

        foreach ($candidates as $raw) {
            if (is_numeric($raw) && (float) $raw > 0) {
                return (int) round((float) $raw);
            }
        }

        return null;
    }

    protected function parseSignedLinkExpiry(string $url): \Carbon\Carbon
    {
        $parts = parse_url($url);
        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if (! empty($query['X-Amz-Date']) && ! empty($query['X-Amz-Expires'])) {
            try {
                $start = \Carbon\Carbon::createFromFormat('Ymd\THis\Z', (string) $query['X-Amz-Date']);

                return $start->copy()->addSeconds((int) $query['X-Amz-Expires']);
            } catch (\Throwable) {
                // fall through
            }
        }

        return now()->addSeconds((int) config('cibil.link_ttl', 600));
    }

    protected function loadMockResponse(): array
    {
        $file = config('cibil.mock_file', 'resources/cibil/sample-response.json');
        $path = base_path($file);
        if (! is_file($path)) {
            return [
                'success' => false,
                'message' => 'Mock file missing: ' . $file,
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'Invalid mock JSON'];
    }

    protected function httpsBaseUrl(string $base): string
    {
        $base = rtrim($base, '/');
        if (str_starts_with($base, 'http://')) {
            return 'https://' . substr($base, 7);
        }

        return $base;
    }

    protected function recordHit(string $endpoint, bool $success, int $httpStatus, array $payload, ?array $response): void
    {
        try {
            $masked = $payload;
            foreach (['pan', 'mobile'] as $key) {
                if (empty($masked[$key]) || ! is_string($masked[$key])) {
                    continue;
                }
                $val = $masked[$key];
                $masked[$key] = strlen($val) > 4
                    ? substr($val, 0, 2) . str_repeat('*', max(0, strlen($val) - 4)) . substr($val, -2)
                    : '****';
            }

            VerificationApiHit::create([
                'service' => 'cibil',
                'endpoint' => $endpoint,
                'success' => $success,
                'http_status' => $httpStatus,
                'request_payload' => $masked,
                'response_message' => is_array($response)
                    ? ($response['message'] ?? Arr::get($response, 'data.results.cibil.message'))
                    : null,
                'user_id' => Auth::id(),
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record CIBIL API hit', ['error' => $e->getMessage()]);
        }
    }
}
