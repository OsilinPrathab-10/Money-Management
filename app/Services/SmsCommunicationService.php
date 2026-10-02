<?php

namespace App\Services;

use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsCommunicationService
{
    protected string $baseUrl;
    protected string $authKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.msg91.base_url', 'https://control.msg91.com/api/v5'), '/');
        $this->authKey = (string) config('services.msg91.auth_key', '');
    }

    /**
     * Send OTP via MSG91 Flow API using template identifier `basic_otp`.
     * Template body: ##var1## is your verification code for ##var2##.
     */
    public function sendOtp(string $phone, string|int $otp, ?string $appName = null): array
    {
        try {
            if ($this->authKey === '') {
                return ['status' => false, 'message' => 'MSG91 auth key is not configured'];
            }

            $template = SmsTemplate::where('identifier', 'basic_otp')
                ->where('status', true)
                ->first();

            if (! $template) {
                $message = 'SMS template [basic_otp] not found or inactive';
                Log::error($message);

                return ['status' => false, 'message' => $message];
            }

            if (empty($template->template_id)) {
                $message = 'SMS template_id is missing for OTP template [basic_otp]';
                Log::error($message);

                return ['status' => false, 'message' => $message];
            }

            $mobileWithCountryCode = $this->formatMobile($phone);
            $var2 = $appName ?: (config('app.name') ?: 'App');

            $payload = [
                'template_id' => $template->template_id,
                'short_url' => '0',
                'realTimeResponse' => '1',
                'recipients' => [[
                    'mobiles' => $mobileWithCountryCode,
                    'var1' => (string) $otp,
                    'var2' => (string) $var2,
                ]],
            ];

            Log::debug('MSG91 Flow Payload', ['payload' => $payload]);

            $response = Http::withHeaders([
                'accept' => 'application/json',
                'authkey' => $this->authKey,
                'content-type' => 'application/json',
            ])
                ->timeout(30)
                ->post($this->baseUrl . '/flow', $payload);

            $data = $response->json() ?? [];

            Log::info('OTP sent via MSG91', [
                'phone' => $mobileWithCountryCode,
                'template_id' => $template->template_id,
                'http_status' => $response->status(),
                'response' => $data,
            ]);

            $isSuccess = ($data['type'] ?? null) === 'success' || $response->successful();

            return [
                'status' => $isSuccess,
                'message' => $isSuccess
                    ? 'OTP sent successfully'
                    : (string) ($data['message'] ?? 'Failed to send OTP'),
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Exception in SmsCommunicationService::sendOtp', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    protected function formatMobile(string $mobile): string
    {
        $mobile = preg_replace('/\D/', '', $mobile) ?? '';

        if (str_starts_with($mobile, '91') && strlen($mobile) >= 12) {
            return $mobile;
        }

        if (strlen($mobile) === 10) {
            return '91' . $mobile;
        }

        return $mobile;
    }
}
