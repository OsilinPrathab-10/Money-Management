<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Client;
use Carbon\Carbon;
use App\Models\KycDetail;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\DB;

class VerificationCurlService
{
    protected $apiKey;
    protected $apiSecret;
    protected $authUrl;
    protected $panVerifyUrl;
    protected $aadhaarOtpUrl;
    protected $aadhaarVerifyOtpUrl;
    protected $sandboxApiKey;
    protected $sandboxApiSecret;
    protected $bankUrl;
    protected $panAadhaarLinkStatus;
    protected $gridlinesApiKey;
    protected $apiHubKey;
    protected $apiHubSecret;
    protected $apihubApiKey;
    protected $apihubApiSecret;
    protected $apihubApiMode;
    protected $apihubBaseUrl;
    protected $mode;
    protected $bankVerifyUrl;
    protected $sandboxApiVersion;

    public function __construct()
    {
        $apihub = config('services.apihub', []);

        $this->apiHubKey = $apihub['client_id'] ?? env('APIHUB_CLIENT_API_ID', env('APIHUB_API_KEY', env('API_KEY')));
        $this->apiHubSecret = $apihub['client_secret'] ?? env('APIHUB_CLIENT_API_SECRET', env('APIHUB_API_SECRET', env('SECRET_KEY')));
        $this->apihubApiKey = $this->apiHubKey;
        $this->apihubApiSecret = $this->apiHubSecret;
        $this->apihubApiMode = $apihub['mode'] ?? env('APIHUB_API_MODE', env('MODE', 'production'));
        $this->apihubBaseUrl = rtrim($apihub['base_url'] ?? env('APIHUB_BASE_URL', 'http://apihub.services'), '/');
        $this->sandboxApiSecret = env('SANDBOX_SECRET_KEY');
        $this->sandboxApiKey = env('SANDBOX_API_KEY');
        $this->sandboxApiVersion = env('SANDBOX_API_VERSION', '1.0.0');
        $this->authUrl = 'https://api.sandbox.co.in/authenticate';
        $this->panVerifyUrl = 'https://api.sandbox.co.in/kyc/pan/verify';
        $this->aadhaarOtpUrl = 'https://api.sandbox.co.in/kyc/aadhaar/okyc/otp';
        $this->aadhaarVerifyOtpUrl = 'https://api.sandbox.co.in/kyc/aadhaar/okyc/otp/verify';
        $this->panAadhaarLinkStatus = 'https://api.sandbox.co.in/kyc/pan-aadhaar/status';
        $this->bankVerifyUrl = 'https://api.sandbox.co.in/bank/{ifsc}/accounts/{account_number}/verify';
        $this->apiKey = $this->apiHubKey;
        $this->apiSecret = $this->apiHubSecret;
        $this->mode = $this->apihubApiMode;
        $this->bankUrl = $this->apihubBaseUrl . '/' . ltrim($apihub['bank_path'] ?? 'v5/bank-verify', '/');
        $this->gridlinesApiKey = env('GRIDLINES_API_KEY');
    }

    private function hasApiHubCredentials(): bool
    {
        return !empty($this->apihubApiKey) && !empty($this->apihubApiSecret);
    }

    /**
     * API Hub returns status as "success" (string) or true (bool).
     */
    private function isApiHubSuccess(?array $resData, $response = null): bool
    {
        if (!is_array($resData)) {
            return false;
        }

        if ($response && method_exists($response, 'successful') && !$response->successful()) {
            return false;
        }

        if (!empty($resData['error'])) {
            return false;
        }

        $status = $resData['status'] ?? null;
        if ($status === true || $status === 1 || $status === 200 || $status === 'success' || $status === 'SUCCESS') {
            $isValid = $resData['result']['is_valid'] ?? $resData['data']['is_valid'] ?? null;
            if ($isValid === false) {
                return false;
            }
            // v4 API sometimes sets success code 1000 inside data.code
            $code = $resData['data']['code'] ?? null;
            if ($status === 200 && isset($code) && $code !== '1000') {
                return false;
            }
            return true;
        }

        if (($resData['success'] ?? false) === true || ($resData['success'] ?? null) === 'success') {
            $isValid = $resData['result']['is_valid'] ?? $resData['data']['is_valid'] ?? null;
            if ($isValid === false) {
                return false;
            }
            return true;
        } 

        // Nested success flags (aadhaar generate/verify payloads, or v4 bank-hybrid)
        $nestedStatus = $resData['data']['status'] ?? null;
        if ($nestedStatus === true || $nestedStatus === 1 || in_array($nestedStatus, ['generate_otp_success', 'success_aadhaar', 'success'], true)) {
            return true;
        }

        return false;
    }

    private function apihubPost(string $endpoint, array $data = [], ?string $service = null)
    {
        $url = $this->apihubBaseUrl . '/' . ltrim($endpoint, '/');

        $response = Http::withHeaders([
            'X-Client-Api-ID'     => $this->apihubApiKey,
            'X-Client-Api-Secret' => $this->apihubApiSecret,
            'Content-Type'        => 'application/json',
            'Accept'              => 'application/json',
        ])->timeout(40)->post($url, $data);

        if ($service) {
            $payload = $response->json();
            $this->recordApiHit(
                $service,
                $endpoint,
                $this->isApiHubSuccess(is_array($payload) ? $payload : null, $response),
                $response->status(),
                $data,
                is_array($payload) ? $payload : null
            );
        }

        return $response;
    }

    private function recordApiHit(
        string $service,
        string $endpoint,
        bool $success,
        int $statusCode,
        array $requestPayload = [],
        ?array $responsePayload = null
    ): void {
        try {
            $maskedRequest = $requestPayload;
            foreach (['aadhar_number', 'aadhaar_number', 'aadhaar_no', 'pan_number', 'pan', 'account_number'] as $key) {
                if (!empty($maskedRequest[$key]) && is_string($maskedRequest[$key])) {
                    $val = $maskedRequest[$key];
                    $maskedRequest[$key] = strlen($val) > 4
                        ? substr($val, 0, 2) . str_repeat('*', max(0, strlen($val) - 4)) . substr($val, -2)
                        : '****';
                }
            }

            \App\Models\VerificationApiHit::create([
                'service' => $service,
                'endpoint' => $endpoint,
                'success' => $success,
                'http_status' => $statusCode,
                'request_payload' => $maskedRequest,
                'response_message' => is_array($responsePayload)
                    ? ($responsePayload['message'] ?? $responsePayload['data']['message'] ?? null)
                    : null,
                'user_id' => Auth::id(),
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record verification API hit', ['error' => $e->getMessage()]);
        }
    }

    private function extractRequestId(?array $resData): ?string
    {
        if (!is_array($resData)) {
            return null;
        }

        return $resData['data']['sessionId']
            ?? $resData['data']['request_id']
            ?? $resData['data']['data']['request_id']
            ?? $resData['data']['reference_id']
            ?? $resData['request_id']
            ?? $resData['reference_id']
            ?? $resData['data']['client_id']
            ?? null;
    }

    private function maskAadhaar(string $aadhaarNumber): string
    {
        return substr($aadhaarNumber, 0, 4) . '********';
    }

    private function maskAccountNumber(string $accountNumber): string
    {
        $length = strlen($accountNumber);
        if ($length <= 4) {
            return '****';
        }

        return substr($accountNumber, 0, 2) . str_repeat('*', $length - 4) . substr($accountNumber, -2);
    }

    private function sanitizeAadhaarPayloadForLog(?array $data): ?array
    {
        if (!is_array($data)) {
            return $data;
        }

        $copy = json_decode(json_encode($data), true);
        $photoKeys = ['photo', 'profile_image', 'aadhaar_photo'];

        $stripPhoto = function (array &$node) use (&$stripPhoto, $photoKeys): void {
            foreach ($node as $key => &$value) {
                if (is_array($value)) {
                    $stripPhoto($value);
                    continue;
                }

                if (in_array($key, $photoKeys, true) && is_string($value) && strlen($value) > 80) {
                    $value = '[base64:' . strlen($value) . ' chars]';
                }
            }
        };

        $stripPhoto($copy);

        return $copy;
    }

    private function logAadhaarOtpResponse(
        string $provider,
        string $aadhaarNumber,
        int $statusCode,
        ?array $rawResponse,
        ?array $parsedResult = null,
        array $extra = []
    ): void {
        Log::info('[Aadhaar OTP Send] API response', array_merge([
            'provider' => $provider,
            'aadhaar' => $this->maskAadhaar($aadhaarNumber),
            'status_code' => $statusCode,
            'api_response' => $this->sanitizeAadhaarPayloadForLog($rawResponse),
            'parsed_result' => $parsedResult,
        ], $extra));
    }

    private function logAadhaarVerifiedDetails(
        string $provider,
        string $referenceId,
        array $verifiedDetails,
        ?array $rawResponse = null,
        ?string $aadhaarNumber = null
    ): void {
        Log::info('[Aadhaar OTP Verified] Details', [
            'provider' => $provider,
            'aadhaar' => $aadhaarNumber ? $this->maskAadhaar($aadhaarNumber) : null,
            'reference_id' => $referenceId,
            'verified_details' => $verifiedDetails,
            'api_response' => $this->sanitizeAadhaarPayloadForLog($rawResponse),
        ]);
    }

    private function logBankApiResponse(
        string $provider,
        string $endpoint,
        string $accountNumber,
        string $ifsc,
        int $statusCode,
        ?array $rawResponse,
        ?array $parsedResult = null
    ): void {
        Log::info('[Bank API] Response', [
            'provider' => $provider,
            'endpoint' => $endpoint,
            'account_number' => $this->maskAccountNumber($accountNumber),
            'ifsc' => $ifsc,
            'status_code' => $statusCode,
            'api_response' => $rawResponse,
            'parsed_result' => $parsedResult,
        ]);
    }

    private function isSandboxOtpCooldownMessage(?string $message): bool
    {
        if (!$message) {
            return false;
        }

        return str_contains(strtolower($message), 'try after')
            || str_contains(strtolower($message), 'please try after');
    }

    private function hasSandboxCredentials(): bool
    {
        return !empty($this->sandboxApiKey) && !empty($this->sandboxApiSecret);
    }

    private function shouldUseSandboxAadhaar(?string $referenceId = null): bool
    {
        // Prefer API Hub when credentials are configured (unless forced to sandbox).
        if ($this->hasApiHubCredentials() && !config('services.apihub.use_sandbox', false)) {
            return false;
        }

        if (!$this->hasSandboxCredentials()) {
            return false;
        }

        if ($referenceId === null) {
            return true;
        }

        return ctype_digit($referenceId);
    }

    private function shouldUseSandboxBank(): bool
    {
        if ($this->hasApiHubCredentials() && !config('services.apihub.use_sandbox', false)) {
            return false;
        }

        return $this->hasSandboxCredentials();
    }

    private function sandboxBankRequestHeaders(string $accessToken): array
    {
        return [
            'Authorization' => $accessToken,
            'x-api-key' => $this->sandboxApiKey,
            'x-api-version' => $this->sandboxApiVersion,
            'Accept' => 'application/json',
        ];
    }

    private function lookupIfscDetails(string $ifsc): array
    {
        $ifsc = strtoupper($ifsc);

        try {
            $response = Http::timeout(15)->get("https://ifsc.razorpay.com/{$ifsc}");
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'bank_name' => $data['BANK'] ?? null,
                    'branch' => $data['BRANCH'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Razorpay IFSC lookup failed', ['ifsc' => $ifsc, 'error' => $e->getMessage()]);
        }

        if (!$this->hasSandboxCredentials()) {
            return ['bank_name' => null, 'branch' => null];
        }

        $accessToken = $this->sandboxAuthenticate();
        if (!$accessToken) {
            return ['bank_name' => null, 'branch' => null];
        }

        try {
            $response = Http::withHeaders($this->sandboxBankRequestHeaders($accessToken))
                ->timeout(20)
                ->get('https://api.sandbox.co.in/bank/' . $ifsc);

            if ($response->successful()) {
                $data = $response->json('data') ?? $response->json();
                if (is_array($data)) {
                    return [
                        'bank_name' => $data['bank'] ?? $data['bank_name'] ?? null,
                        'branch' => $data['branch'] ?? $data['branch_name'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Sandbox IFSC lookup failed', ['ifsc' => $ifsc, 'error' => $e->getMessage()]);
        }

        return ['bank_name' => null, 'branch' => null];
    }

    private function isSandboxBankVerificationSuccess(?array $resData): bool
    {
        if (!is_array($resData)) {
            return false;
        }

        $data = $resData['data'] ?? [];

        return ($resData['code'] ?? null) === 200
            && ($data['account_exists'] ?? false) === true;
    }

    private function mapSandboxBankVerification(
        array $resData,
        string $accountNumber,
        string $ifsc,
        ?string $clientName,
        string $method
    ): array {
        $data = $resData['data'] ?? [];
        $ifscDetails = $this->lookupIfscDetails($ifsc);
        $nameAtBank = $data['name_at_bank'] ?? $clientName;

        return [
            'status' => true,
            'message' => $data['message'] ?? 'Bank account verified successfully.',
            'data' => [
                'full_name' => $nameAtBank,
                'bank_name' => $ifscDetails['bank_name'],
                'branch' => $ifscDetails['branch'],
                'account_number' => $accountNumber,
                'ifsc' => strtoupper($ifsc),
                'account_exists' => $data['account_exists'] ?? true,
                'verification_method' => $method,
                'utr' => $data['utr'] ?? null,
                'amount_deposited' => $data['amount_deposited'] ?? null,
            ],
        ];
    }

    private function sandboxVerifyBank(string $accountNumber, string $ifsc, ?string $clientName = null): array
    {
        $ifsc = strtoupper(preg_replace('/\s+/', '', $ifsc));
        $accountNumber = preg_replace('/\s+/', '', $accountNumber);

        $accessToken = $this->sandboxAuthenticate();
        if (!$accessToken) {
            return [
                'status' => false,
                'message' => 'Bank verification service authentication failed.',
            ];
        }

        $query = [];
        if (!empty($clientName)) {
            $query['name'] = $clientName;
        }

        $methods = [
            'penniless' => "https://api.sandbox.co.in/bank/{$ifsc}/accounts/{$accountNumber}/penniless-verify",
            'penny_drop' => "https://api.sandbox.co.in/bank/{$ifsc}/accounts/{$accountNumber}/verify",
        ];

        $headers = $this->sandboxBankRequestHeaders($accessToken);
        $lastFailure = [
            'status' => false,
            'message' => 'Bank account verification failed.',
        ];

        foreach ($methods as $method => $url) {
            try {
                $response = Http::withHeaders($headers)
                    ->timeout(45)
                    ->get($url, $query);

                $resData = $response->json();

                if ($this->isSandboxBankVerificationSuccess($resData)) {
                    $result = $this->mapSandboxBankVerification($resData, $accountNumber, $ifsc, $clientName, $method);
                    $this->logBankApiResponse('sandbox', "bank/{$method}", $accountNumber, $ifsc, $response->status(), $resData, $result);

                    return $result;
                }

                $apiMessage = $resData['data']['message'] ?? $resData['message'] ?? 'Bank account verification failed.';
                $lastFailure = [
                    'status' => false,
                    'message' => $apiMessage,
                    'data' => $resData,
                ];
                $this->logBankApiResponse('sandbox', "bank/{$method}", $accountNumber, $ifsc, $response->status(), $resData, $lastFailure);
            } catch (\Throwable $e) {
                Log::error('Sandbox bank verification exception', [
                    'method' => $method,
                    'error' => $e->getMessage(),
                ]);
                $lastFailure = [
                    'status' => false,
                    'message' => 'Bank verification failed: ' . $e->getMessage(),
                ];
            }
        }

        return $lastFailure;
    }

    private function sandboxAuthenticate(): ?string
    {
        $cacheKey = 'sandbox_api_access_token_' . md5($this->sandboxApiKey);

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->sandboxApiKey,
                'x-api-secret' => $this->sandboxApiSecret,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->authUrl);

            $authData = $response->json();
            $token = $authData['access_token'] ?? null;

            if (!$response->successful() || empty($token)) {
                Log::error('Sandbox authentication failed', [
                    'status_code' => $response->status(),
                    'response' => $authData,
                ]);
                return null;
            }

            Cache::put($cacheKey, $token, now()->addMinutes(50));

            return $token;
        } catch (\Throwable $e) {
            Log::error('Sandbox authentication exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function sandboxAadhaarErrorMessage(?array $resData, int $statusCode): string
    {
        $apiMessage = $resData['message'] ?? $resData['data']['message'] ?? null;

        if ($statusCode === 503 || $apiMessage === 'Source Unavailable') {
            return 'Aadhaar OTP service is temporarily unavailable from UIDAI. Please wait 30–60 seconds and use Resend OTP.';
        }

        if ($statusCode === 429) {
            return 'Too many OTP requests. Please wait a minute before trying again.';
        }

        if ($statusCode === 504) {
            return 'Aadhaar OTP request timed out. Please try again.';
        }

        if ($this->isSandboxOtpCooldownMessage($apiMessage)) {
            return $apiMessage;
        }

        return $apiMessage ?? 'Failed to send Aadhaar OTP.';
    }

    private function isSandboxRetryableStatus(int $statusCode): bool
    {
        return in_array($statusCode, [503, 504], true);
    }

    private function sandboxAadhaarOtpRequest(string $aadhaarNumber, bool $forceResend = false): array
    {
        $cacheKey = 'sandbox_aadhaar_otp_' . $aadhaarNumber;
        if (!$forceResend && ($cached = Cache::get($cacheKey))) {
            $this->logAadhaarOtpResponse('sandbox', $aadhaarNumber, 200, null, $cached, [
                'source' => 'cache',
            ]);

            return $cached;
        }

        $accessToken = $this->sandboxAuthenticate();
        if (!$accessToken) {
            return [
                'status' => false,
                'message' => 'Aadhaar verification service authentication failed.',
            ];
        }

        $maxAttempts = 3;
        $response = null;
        $resData = null;

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = Http::withHeaders([
                    'Authorization' => $accessToken,
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->sandboxApiKey,
                    'x-api-version' => $this->sandboxApiVersion,
                ])->timeout(40)->post($this->aadhaarOtpUrl, [
                    'aadhaar_number' => $aadhaarNumber,
                    'consent' => 'y',
                    'reason' => 'For Kyc',
                    '@entity' => 'in.co.sandbox.kyc.aadhaar.okyc.otp.request',
                ]);

                $resData = $response->json();
                $referenceId = $this->extractRequestId($resData);
                $apiMessage = $resData['data']['message'] ?? $resData['message'] ?? null;

                if ($response->successful() && $referenceId) {
                    $result = [
                        'status' => true,
                        'message' => $apiMessage ?: 'OTP sent successfully.',
                        'data' => [
                            'request_id' => (string) $referenceId,
                        ],
                    ];
                    Cache::put($cacheKey, $result, now()->addMinutes(3));
                    $this->logAadhaarOtpResponse('sandbox', $aadhaarNumber, $response->status(), $resData, $result, [
                        'attempt' => $attempt,
                        'transaction_id' => $resData['transaction_id'] ?? null,
                    ]);

                    return $result;
                }

                if (
                    $response->successful()
                    && !$referenceId
                    && $this->isSandboxOtpCooldownMessage($apiMessage)
                    && ($cached = Cache::get($cacheKey))
                ) {
                    $cooldownResult = [
                        'status' => true,
                        'message' => $apiMessage,
                        'data' => $cached['data'],
                    ];
                    $this->logAadhaarOtpResponse('sandbox', $aadhaarNumber, $response->status(), $resData, $cooldownResult, [
                        'attempt' => $attempt,
                        'cooldown_reused_reference' => true,
                        'transaction_id' => $resData['transaction_id'] ?? null,
                    ]);

                    return $cooldownResult;
                }

                $this->logAadhaarOtpResponse('sandbox', $aadhaarNumber, $response->status(), $resData, null, [
                    'attempt' => $attempt,
                    'transaction_id' => $resData['transaction_id'] ?? null,
                ]);

                if (!$this->isSandboxRetryableStatus($response->status()) || $attempt === $maxAttempts) {
                    break;
                }

                sleep(2);
            }

            $failure = [
                'status' => false,
                'message' => $this->sandboxAadhaarErrorMessage($resData, $response?->status() ?? 500),
                'data' => $resData,
            ];
            $this->logAadhaarOtpResponse('sandbox', $aadhaarNumber, $response?->status() ?? 500, $resData, $failure);

            return $failure;
        } catch (\Throwable $e) {
            Log::error('Sandbox Aadhaar OTP request exception', ['error' => $e->getMessage()]);

            return [
                'status' => false,
                'message' => 'Aadhaar OTP request failed: ' . $e->getMessage(),
            ];
        }
    }

    private function mapSandboxAadhaarVerifyResponse(array $verifyData): array
    {
        $payload = $verifyData['data'] ?? [];
        $address = $payload['address'] ?? [];

        if (is_array($address)) {
            $fullAddress = implode(', ', array_filter([
                $address['house'] ?? null,
                $address['street'] ?? null,
                $address['landmark'] ?? null,
                $address['vtc'] ?? null,
                $address['post_office'] ?? null,
                $address['subdistrict'] ?? null,
                $address['district'] ?? null,
                $address['state'] ?? null,
                $address['country'] ?? null,
            ]));
            $city = $address['vtc'] ?? $address['city'] ?? null;
            $state = $address['state'] ?? null;
            $pincode = $address['pincode'] ?? null;
        } else {
            $fullAddress = $payload['full_address'] ?? (string) $address;
            $city = $payload['city'] ?? null;
            $state = $payload['state'] ?? null;
            $pincode = $payload['pincode'] ?? null;
        }

        $dob = $payload['date_of_birth'] ?? $payload['dob'] ?? null;
        if ($dob) {
            try {
                $dob = Carbon::createFromFormat('d-m-Y', $dob)->format('d-m-Y');
            } catch (\Throwable $e) {
                try {
                    $dob = Carbon::parse($dob)->format('d-m-Y');
                } catch (\Throwable $e) {
                    // keep raw value
                }
            }
        }

        return [
            'status' => true,
            'message' => 'Aadhaar verified successfully.',
            'data' => [
                'status' => 'success',
                'data' => [
                    'status' => 'success_aadhaar',
                    'full_name' => $payload['name'] ?? $payload['full_name'] ?? null,
                    'gender' => strtolower($payload['gender'] ?? ''),
                    'dob' => $dob,
                    'address' => $fullAddress,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $pincode,
                    'pincode' => $pincode,
                    'profile_image' => $payload['photo'] ?? $payload['profile_image'] ?? null,
                ],
            ],
        ];
    }

    private function sandboxSubmitAadhaarOtp(string $otp, string $referenceId): array
    {
        $accessToken = $this->sandboxAuthenticate();
        if (!$accessToken) {
            return [
                'status' => false,
                'message' => 'Aadhaar verification service authentication failed.',
            ];
        }

        try {
            $maxAttempts = 3;
            $response = null;
            $verifyData = null;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = Http::withHeaders([
                    'Authorization' => $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'x-api-key' => $this->sandboxApiKey,
                    'x-api-version' => $this->sandboxApiVersion,
                ])->timeout(40)->post($this->aadhaarVerifyOtpUrl, [
                    'reference_id' => $referenceId,
                    'otp' => $otp,
                    '@entity' => 'in.co.sandbox.kyc.aadhaar.okyc.request',
                ]);

                $verifyData = $response->json();
                $message = $verifyData['data']['message'] ?? $verifyData['message'] ?? null;

                if (
                    $response->successful()
                    && ($message === 'Aadhaar Card Exists' || ($verifyData['data']['name'] ?? null))
                ) {
                    $mapped = $this->mapSandboxAadhaarVerifyResponse($verifyData);
                    $this->logAadhaarVerifiedDetails(
                        'sandbox',
                        $referenceId,
                        $mapped['data']['data'] ?? [],
                        $verifyData
                    );

                    return $mapped;
                }

                Log::info('[Aadhaar OTP Verify] API response', [
                    'provider' => 'sandbox',
                    'reference_id' => $referenceId,
                    'attempt' => $attempt,
                    'status_code' => $response->status(),
                    'api_response' => $this->sanitizeAadhaarPayloadForLog($verifyData),
                    'transaction_id' => $verifyData['transaction_id'] ?? null,
                ]);

                if (!$this->isSandboxRetryableStatus($response->status()) || $attempt === $maxAttempts) {
                    break;
                }

                sleep(2);
            }

            return [
                'status' => false,
                'message' => $this->sandboxAadhaarErrorMessage($verifyData, $response?->status() ?? 500),
                'data' => $verifyData,
            ];
        } catch (\Throwable $e) {
            Log::error('Sandbox Aadhaar OTP submit exception', ['error' => $e->getMessage()]);

            return [
                'status' => false,
                'message' => 'Aadhaar OTP submit exception: ' . $e->getMessage(),
            ];
        }
    }

    public function verifyAadhaarOtpRequest(string $aadhaarNumber, bool $forceResend = false): array
    {
        if ($this->shouldUseSandboxAadhaar()) {
            return $this->sandboxAadhaarOtpRequest($aadhaarNumber, $forceResend);
        }

        if (!$this->hasApiHubCredentials()) {
            return [
                'status' => false,
                'message' => 'Aadhaar verification service is not configured. Please set APIHUB credentials in .env.',
            ];
        }

        try {
            $endpoint = config('services.apihub.aadhaar_send', 'v5/aadhar/send');
            $response = $this->apihubPost($endpoint, [
                'aadhar_number' => $aadhaarNumber,
            ], 'aadhaar');

            $resData = $response->json();
            $requestId = $this->extractRequestId($resData);
            $isSuccess = $this->isApiHubSuccess($resData, $response) && !empty($requestId);

            if ($isSuccess) {
                Cache::put('aadhaar_req_' . $requestId, $aadhaarNumber, now()->addMinutes(15));
                $result = [
                    'status' => true,
                    'message' => $resData['message'] ?? 'OTP sent successfully.',
                    'data' => [
                        'request_id' => $requestId,
                    ],
                ];
                $this->logAadhaarOtpResponse('apihub', $aadhaarNumber, $response->status(), $resData, $result);

                return $result;
            }

            $failure = [
                'status' => false,
                'message' => $resData['message']
                    ?? $resData['data']['message']
                    ?? $resData['error']
                    ?? 'Failed to send Aadhaar OTP.',
                'data' => $resData,
            ];
            $this->logAadhaarOtpResponse('apihub', $aadhaarNumber, $response->status(), $resData, $failure);
            Log::error('Aadhaar OTP request failed', [
                'status_code' => $response->status(),
                'response' => $resData,
            ]);

            return $failure;
        } catch (\Throwable $e) {
            Log::error('Aadhaar OTP request exception', ['error' => $e->getMessage()]);

            return [
                'status' => false,
                'message' => 'Aadhaar OTP request failed: ' . $e->getMessage(),
            ];
        }
    }

    public function submitAadhaarOtp(string $otp, string $referenceId): array
    {
        if (str_starts_with($referenceId, 'mock_req_')) {
            $cachedOtp = Cache::get('aadhaar_otp_' . $referenceId);
            if ($cachedOtp && $otp !== $cachedOtp && $otp !== '123456') {
                return [
                    'status' => false,
                    'message' => 'Invalid OTP (Mock)',
                    'data' => ['status' => 'failed'],
                ];
            }

            $mockData = Cache::get('aadhaar_mock_data_' . $referenceId, []);
            return [
                'status' => true,
                'message' => 'Aadhaar verified successfully (Mock).',
                'data' => [
                    'status' => 'success',
                    'data' => array_merge([
                        'status' => 'success_aadhaar',
                        'full_name' => 'Mock User',
                        'gender' => 'male',
                        'dob' => '01-01-1990',
                        'address' => 'Mock Address',
                        'city' => 'Mock City',
                        'state' => 'Mock State',
                        'zip' => '110001',
                    ], $mockData),
                ],
            ];
        }

        if ($this->shouldUseSandboxAadhaar($referenceId)) {
            return $this->sandboxSubmitAadhaarOtp($otp, $referenceId);
        }

        try {
            $aadhaarNumber = Cache::get('aadhaar_req_' . $referenceId, '');
            $endpoint = config('services.apihub.aadhaar_verify', 'v5/okyc/verify');
            $response = $this->apihubPost($endpoint, [
                'session_id' => $referenceId,
                'otp' => $otp,
                'aadhar_number' => $aadhaarNumber,
            ], 'aadhaar');

            $resData = $response->json();
            $isSuccess = $this->isApiHubSuccess($resData, $response);

            if ($isSuccess) {
                // New API Hub: verified details live directly under data{}
                $verifiedDetails = [];
                if (isset($resData['data']['aadhaarData'])) {
                    $aadhaarData = $resData['data']['aadhaarData'];
                    $verifiedDetails = array_merge($resData['data'], $aadhaarData, [
                        'status' => 'success_aadhaar',
                        'full_name' => $aadhaarData['name'] ?? null,
                        'aadhaar_number' => $aadhaarData['aadhaarNumber'] ?? null,
                        'zip' => $aadhaarData['pincode'] ?? null,
                        'city' => $aadhaarData['district'] ?? $aadhaarData['locality'] ?? null,
                    ]);
                } elseif (isset($resData['data']['status']) && $resData['data']['status'] === 'success_aadhaar') {
                    $verifiedDetails = $resData['data'];
                } elseif (isset($resData['data']['data']) && is_array($resData['data']['data'])) {
                    $verifiedDetails = $resData['data']['data'];
                } elseif (isset($resData['data']) && is_array($resData['data'])) {
                    $verifiedDetails = $resData['data'];
                }

                if (is_array($verifiedDetails) && ($verifiedDetails['status'] ?? '') !== 'success_aadhaar') {
                    if (!empty($verifiedDetails['full_name']) || !empty($verifiedDetails['aadhaar_number']) || !empty($verifiedDetails['name']) || !empty($verifiedDetails['aadhaarNumber'])) {
                        $verifiedDetails['status'] = 'success_aadhaar';
                    }
                }

                $normalized = [
                    'status' => true,
                    'message' => $resData['message'] ?? 'Aadhaar verified successfully.',
                    'data' => [
                        'status' => 'success',
                        'data' => $verifiedDetails,
                    ],
                ];

                $this->logAadhaarVerifiedDetails('apihub', $referenceId, $verifiedDetails, $resData);

                return $normalized;
            }

            Log::info('[Aadhaar OTP Verify] API response', [
                'provider' => 'apihub',
                'reference_id' => $referenceId,
                'status_code' => $response->status(),
                'api_response' => $this->sanitizeAadhaarPayloadForLog($resData),
            ]);

            return [
                'status' => false,
                'message' => $resData['message'] ?? $resData['data']['message'] ?? 'Aadhaar OTP verification failed.',
                'data' => $resData,
            ];
        } catch (\Throwable $e) {
            Log::error('Aadhaar OTP submit exception', ['error' => $e->getMessage()]);

            return [
                'status' => false,
                'message' => 'Aadhaar OTP submit exception: ' . $e->getMessage(),
            ];
        }
    }

    public function verifyAadhaarOtpConfirm(string $aadhaarNumber, string $otp, string $referenceId): array
    {
        $result = $this->submitAadhaarOtp($otp, $referenceId);

        if (($result['status'] ?? false) !== true) {
            return $result;
        }

        $aadhaarData = $result['data']['data'] ?? [];
        if (($aadhaarData['status'] ?? '') !== 'success_aadhaar') {
            return [
                'status' => false,
                'message' => $result['data']['message'] ?? 'Aadhaar verification failed.',
                'data' => $result,
            ];
        }

        return [
            'status' => true,
            'message' => 'Aadhaar verified successfully.',
            'data' => $aadhaarData,
            'aadhaar_number' => $aadhaarNumber,
        ];
    }

    public function verifyBank(string $accountNumber, string $ifsc, ?string $clientName = null): array
    {
        if ($this->shouldUseSandboxBank()) {
            return $this->sandboxVerifyBank($accountNumber, $ifsc, $clientName);
        }

        if (!$this->hasApiHubCredentials()) {
            return [
                'status' => false,
                'message' => 'Bank verification service is not configured. Please set APIHUB credentials in .env.',
            ];
        }

        try {
            $endpoint = config('services.apihub.bank_path', 'v4/bank-hybrid');
            $response = $this->apihubPost($endpoint, [
                'account_number' => $accountNumber,
                'ifsc' => strtoupper($ifsc),
            ], 'bank');

            $resData = $response->json();
            $isSuccess = $this->isApiHubSuccess($resData, $response);

            if ($isSuccess) {
                // v4/bank-hybrid returns data inside data.bank_account_data
                $bankData = $resData['result'] 
                         ?? $resData['data']['bank_account_data']
                         ?? $resData['data']['data']['data'] 
                         ?? $resData['data']['data'] 
                         ?? $resData['data'] 
                         ?? [];

                $result = [
                    'status' => true,
                    'message' => $resData['message'] ?? $resData['data']['message'] ?? $bankData['message'] ?? 'Bank account verified successfully.',
                    'data' => [
                        'full_name' => $bankData['account_holder_name'] ?? $bankData['name'] ?? $bankData['full_name'] ?? $bankData['account_name'] ?? $clientName,
                        'bank_name' => $bankData['bank_name'] ?? $bankData['bank'] ?? null,
                        'branch' => $bankData['branch'] ?? $bankData['branch_name'] ?? null,
                        'account_number' => $bankData['account_number'] ?? $accountNumber,
                        'ifsc' => $bankData['account_ifsc'] ?? $bankData['ifsc'] ?? $ifsc,
                    ],
                ];
                $this->logBankApiResponse('apihub', $endpoint, $accountNumber, $ifsc, $response->status(), $resData, $result);

                return $result;
            }

            $failure = [
                'status' => false,
                'message' => $resData['message'] ?? $resData['data']['message'] ?? 'Bank account verification failed.',
                'data' => $resData,
            ];
            $this->logBankApiResponse('apihub', $endpoint, $accountNumber, $ifsc, $response->status(), $resData, $failure);

            return $failure;
        } catch (\Throwable $e) {
            Log::error('Bank verification exception', ['error' => $e->getMessage()]);

            return [
                'status' => false,
                'message' => 'Bank verification failed: ' . $e->getMessage(),
            ];
        }
    }

    public function verifyAgentBank(string $accountNumber, string $ifscCode, ?string $clientName = null): array
    {
        if ($this->shouldUseSandboxBank()) {
            $result = $this->sandboxVerifyBank($accountNumber, $ifscCode, $clientName);

            if (($result['status'] ?? false) === true) {
                $bankData = $result['data'] ?? [];

                return [
                    'success' => true,
                    'bank' => [
                        'bank_name' => $bankData['bank_name'] ?? null,
                        'branch' => $bankData['branch'] ?? null,
                        'full_name' => $bankData['full_name'] ?? $clientName,
                        'beneficiary_name' => $bankData['full_name'] ?? $clientName,
                        'account_number' => $bankData['account_number'] ?? $accountNumber,
                        'ifsc' => $bankData['ifsc'] ?? strtoupper($ifscCode),
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Bank account verification failed.',
                'data' => $result['data'] ?? null,
            ];
        }

        try {
            $result = $this->verifyBank($accountNumber, $ifscCode, $clientName);

            if (($result['status'] ?? false) === true) {
                $bankData = $result['data'] ?? [];

                return [
                    'success' => true,
                    'bank' => [
                        'bank_name' => $bankData['bank_name'] ?? null,
                        'branch' => $bankData['branch'] ?? null,
                        'full_name' => $bankData['full_name'] ?? $clientName,
                        'beneficiary_name' => $bankData['full_name'] ?? $clientName,
                        'account_number' => $bankData['account_number'] ?? $accountNumber,
                        'ifsc' => $bankData['ifsc'] ?? strtoupper($ifscCode),
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Bank account verification failed.',
                'data' => $result['data'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Agent bank verification exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Agent bank verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify aadhaar via cURL
     */
    public function verifyAadhaar($aadhaarNumber)
    {
        // Trim whitespace
        $aadhaarNumber = trim($aadhaarNumber);

        if (!preg_match('/^[2-9]{1}[0-9]{11}$/', $aadhaarNumber)) {
            return response()->json(['error' => 'Invalid Aadhaar number.'], 422);
        }

        // Check in both Client and KycDetail tables
        $existInClient = Client::where('aadhaar_number', $aadhaarNumber)
            ->whereNotNull('aadhaar_number')
            ->exists();
        
        $existInKyc = KycDetail::where('aadhaar_number', $aadhaarNumber)
            ->whereNotNull('aadhaar_number')
            ->exists();

        if ($existInClient || $existInKyc) {
            return response()->json([
                'success' => false,
                'message' => 'This Aadhaar number is already registered.',
            ], 409);
        }

        $headers = [
            "x-api-key: $this->sandboxApiKey",
            "x-api-secret: $this->sandboxApiSecret"
        ];

        $ch = curl_init($this->authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $authResponse = curl_exec($ch);
        $authStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $authData = json_decode($authResponse, true);

        if ($authStatus !== 200 || empty($authData['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'details' => $authData
            ], 401);
        }

        $accessToken = $authData['access_token'];

        $otpHeaders = [
            "Authorization: $accessToken",
            "Content-Type: application/json",
            "x-api-key: $this->sandboxApiKey",
            "x-api-version: " . env('SANDBOX_API_VERSION')
        ];

        $otpPayload = json_encode([
            'aadhaar_number' => $aadhaarNumber,
            'consent'        => 'y',
            'reason'         => 'For Kyc',
            '@entity'        => 'in.co.sandbox.kyc.aadhaar.okyc.otp.request'
        ]);

        $ch = curl_init($this->aadhaarOtpUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $otpPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $otpHeaders);

        $otpResponse = curl_exec($ch);
        $otpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $otpData = json_decode($otpResponse, true);

        return response()->json($otpData, $otpStatus);
    }

    public function resendAadhaarOtp($aadhaarNumber)
    {
        // Trim whitespace
        $aadhaarNumber = trim($aadhaarNumber);

        if (!preg_match('/^[2-9]{1}[0-9]{11}$/', $aadhaarNumber)) {
            return response()->json(['error' => 'Invalid Aadhaar number.'], 422);
        }

        // Check in both Client and KycDetail tables
        $existInClient = Client::where('aadhaar_number', $aadhaarNumber)
            ->whereNotNull('aadhaar_number')
            ->exists();
        
        $existInKyc = KycDetail::where('aadhaar_number', $aadhaarNumber)
            ->whereNotNull('aadhaar_number')
            ->exists();

        if ($existInClient || $existInKyc) {
            return response()->json([
                'success' => false,
                'message' => 'This Aadhaar number is already registered.',
            ], 409);
        }

        $headers = [
            "x-api-key: $this->sandboxApiKey",
            "x-api-secret: $this->sandboxApiSecret"
        ];

        $ch = curl_init($this->authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $authResponse = curl_exec($ch);
        $authStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $authData = json_decode($authResponse, true);

        if ($authStatus !== 200 || empty($authData['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'details' => $authData
            ], 401);
        }

        $accessToken = $authData['access_token'];

        $otpHeaders = [
            "Authorization: $accessToken",
            "Content-Type: application/json",
            "x-api-key: $this->sandboxApiKey",
            "x-api-version: " . env('API_VERSION')
        ];

        $otpPayload = json_encode([
            'aadhaar_number' => $aadhaarNumber,
            'consent'        => 'y',
            'reason'         => 'For Kyc',
            '@entity'        => 'in.co.sandbox.kyc.aadhaar.okyc.otp.request'
        ]);

        $ch = curl_init($this->aadhaarOtpUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $otpPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $otpHeaders);

        $otpResponse = curl_exec($ch);
        $otpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $otpData = json_decode($otpResponse, true);

        return response()->json($otpData, $otpStatus);
    }

    public function verifyAadhaarOtp($aadhaarNumber, $otp, $referenceId)
    {
        // Trim whitespace
        $aadhaarNumber = trim($aadhaarNumber);

        // Check in both Client and KycDetail tables
        $existInClient = Client::where('aadhaar_number', $aadhaarNumber)
            ->whereNotNull('aadhaar_number')
            ->exists();
        
        $existInKyc = KycDetail::where('aadhaar_number', $aadhaarNumber)
            ->whereNotNull('aadhaar_number')
            ->exists();

        if ($existInClient || $existInKyc) {
            return response()->json([
                'success' => false,
                'message' => 'This Aadhaar number is already registered.',
            ], 409);
        }

        $authHeaders = [
            "x-api-key: $this->sandboxApiKey",
            "x-api-secret: $this->sandboxApiSecret"
        ];

        $ch = curl_init($this->authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $authHeaders);

        $authResponse = curl_exec($ch);
        $authStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $authData = json_decode($authResponse, true);

        if ($authStatus !== 200 || empty($authData['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'details' => $authData
            ], 401);
        }

        $accessToken = $authData['access_token'];

        $verifyHeaders = [
            "Authorization: $accessToken",
            "Content-Type: application/json",
            "Accept: application/json",
            "x-api-key: $this->sandboxApiKey",
            "x-api-version: 2.0"
        ];

        $verifyPayload = json_encode([
            'reference_id' => $referenceId,
            'otp'          => $otp,
            '@entity'      => 'in.co.sandbox.kyc.aadhaar.okyc.request'
        ]);

        $ch = curl_init($this->aadhaarVerifyOtpUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $verifyPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $verifyHeaders);

        $verifyResponse = curl_exec($ch);
        $verifyStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $verifyData = json_decode($verifyResponse, true);

        if(isset($verifyData['data']['message']) && $verifyData['data']['message'] === 'Aadhaar Card Exists'){
            $user = Auth::user();

            $user->name = data_get($verifyData, 'data.name');

            $photoBase64 = data_get($verifyData, 'data.photo');
            $photoPath = null;

            if ($photoBase64) {
                $photoData = base64_decode($photoBase64);

                $folderPath = storage_path('app/public/aadhaarPhoto');
                if (!file_exists($folderPath)) {
                    mkdir($folderPath, 0777, true);
                }

                $fileName = $user->id.'_aadhaar.jpg';
                $filePath = $folderPath.'/'.$fileName;

                file_put_contents($filePath, $photoData);

                $photoPath = 'aadhaar/'.$fileName;
            }

            $aadhaarDob = data_get($verifyData, 'data.date_of_birth');

            $client = Client::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'gender'      => data_get($verifyData, 'data.gender'),
                    'care_of'     => data_get($verifyData, 'data.care_of'),
                    'flat'        => data_get($verifyData, 'data.address.house'),
                    'street'      => data_get($verifyData, 'data.address.street'),
                    'address'     => data_get($verifyData, 'data.full_address'),
                    'country'     => data_get($verifyData, 'data.address.country'),
                    'state'       => data_get($verifyData, 'data.address.state'),
                    'city'        => data_get($verifyData, 'data.address.city'),
                    'district'    => data_get($verifyData, 'data.address.district'),
                    'subdistrict' => data_get($verifyData, 'data.address.subdistrict'),
                    'pincode'     => data_get($verifyData, 'data.address.pincode'),
                    'landmark'    => data_get($verifyData, 'data.address.landmark'),
                    'post_office' => data_get($verifyData, 'data.address.post_office'),
                    'vtc'         => data_get($verifyData, 'data.address.vtc'),
                    'aadhaar_photo_path'    => $photoPath,
                    'aadhaar_number'    => $aadhaarNumber,
                    'date_of_birth'    => Carbon::createFromFormat('d-m-Y', $aadhaarDob)->format('Y-m-d'),
                ]
            );

            $client->kycDetail()->updateOrCreate(
                ['client_id' => $client->id],
                ['aadhaar_number' => $aadhaarNumber, 'aadhaar_name' => data_get($verifyData, 'data.name')]
            );
        }

        return response()->json([$verifyData, $verifyStatus, 'aadhaar_number' => $aadhaarNumber]);
    }

    public function verifyPan($panNumber, $aadhaarNumber = null)
    {
        $panNumber = strtoupper(trim($panNumber));

        if ($aadhaarNumber === null) {
            try {
                $endpoint = config('services.apihub.pan_path', 'v5/pan');
                $response = $this->apihubPost($endpoint, [
                    'pan_number' => $panNumber,
                ], 'pan');

                $resData = $response->json();

                Log::info('ApiHub verifyPan response', [
                    'pan' => substr($panNumber, 0, 3) . '****' . substr($panNumber, -1),
                    'status_code' => $response->status(),
                    'response' => $resData,
                ]);

                $isSuccess = $this->isApiHubSuccess($resData, $response);

                if ($isSuccess) {
                    $panData = $resData['data']['data'] ?? $resData['data'] ?? [];

                    return [
                        'status' => true,
                        'message' => $resData['message'] ?? 'PAN verified successfully.',
                        'data' => [
                            'full_name' => $panData['full_name'] ?? $panData['name'] ?? $panData['registered_name'] ?? null,
                            'pan' => $panData['pan'] ?? $panData['pan_number'] ?? $panNumber,
                            'status' => $panData['status'] ?? 'valid',
                        ],
                    ];
                }

                return [
                    'status' => false,
                    'message' => $resData['message'] ?? $resData['data']['message'] ?? 'PAN verification failed.',
                    'data' => $resData,
                ];
            } catch (\Throwable $e) {
                Log::error('PAN verification exception', ['error' => $e->getMessage()]);

                return [
                    'status' => false,
                    'message' => 'PAN verification failed: ' . $e->getMessage(),
                ];
            }
        }

        $authHeaders = [
            "x-api-key: {$this->sandboxApiKey}",
            "x-api-secret: {$this->sandboxApiSecret}",
            "x-api-version: 1.0",
            "Content-Type: application/json"
        ];

        // Step 1: Authenticate
        $ch = curl_init($this->authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $authHeaders);

        $authResponse = curl_exec($ch);
        $authStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $authData = json_decode($authResponse, true);

        if ($authStatus !== 200 || empty($authData['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'details' => $authData
            ], 401);
        }

        $accessToken = $authData['access_token'];

        // Step 2: Check PAN–Aadhaar Link Status
        $aadhaarLinkResult = $this->checkPanAadhaarLinkStatus(
            $accessToken,
            $panNumber,
            $aadhaarNumber
        );

        return response()->json([
            'success' => true,
            'message' => 'PAN–Aadhaar link status retrieved successfully',
            'pan_aadhaar_link_status' => $aadhaarLinkResult
        ], 200);
    }

    public function verifyBankAccount($accountNumber, $ifsc)
    {
        $payload = [
            'account_number' => $accountNumber,
            'ifsc' => strtoupper($ifsc),
        ];

        $ch = curl_init($this->bankUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Client-Api-ID: ' . $this->apiKey,
                'X-Client-Api-Secret: ' . $this->apiSecret,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            Log::error('cURL Error during Bank Verification', ['error' => $error, 'payload' => $payload]);
            return ['success' => false, 'error' => $error, 'status_code' => 500];
        }

        curl_close($ch);
        $decoded = json_decode($response, true);
        $success = $httpCode >= 200 && $httpCode < 300
            && (($decoded['status'] ?? false) === true || ($decoded['status'] ?? false) === 'success' || ($decoded['success'] ?? false) === true);

        $parsed = [
            'success' => $success,
            'status_code' => $httpCode,
            'data' => $decoded,
        ];
        $this->recordApiHit('bank', config('services.apihub.bank_path', 'v5/bank-verify'), $success, $httpCode, $payload, $decoded);
        $this->logBankApiResponse('apihub', 'v5/bank-verify (curl)', $accountNumber, $ifsc, $httpCode, $decoded, $parsed);

        return $parsed;
    }

    // Bank statement OCR via Gridlines
    public function verifyBankStatement($filePath, $consent = 'Y', $referenceId = null)
    {
        if (!file_exists($filePath)) {
            dd("FILE DOES NOT EXIST: " . $filePath);
        }

        $referenceId = $referenceId ?? Str::uuid()->toString();

        // MUST fix Windows path + add MIME type
        $file = curl_file_create(
            realpath($filePath),
            'application/pdf',
            basename($filePath)
        );

        $payload = [
            'file_front' => $file,
            'consent' => $consent,
        ];

        $ch = curl_init("https://api.gridlines.io/bank-api/statement/ocr");

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-API-Key: ' . $this->gridlinesApiKey,
                'X-Auth-Type: API-Key',
                'X-Reference-ID: ' . $referenceId,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            dd("CURL ERROR: " . curl_error($ch));
        }

        curl_close($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            Log::error('cURL Error during Bank Statement OCR', ['error' => $error, 'file' => $filePath]);
            return ['success' => false, 'error' => $error, 'status_code' => 500];
        }

        curl_close($ch);
        $decoded = json_decode($response, true);

        Log::info('Bank Statement OCR Response', ['status_code' => $httpCode, 'response' => $decoded]);

        return ['success' => $httpCode >= 200 && $httpCode < 300, 'status_code' => $httpCode, 'data' => $decoded];
    }

    public function checkPanAadhaarLinkStatus($accessToken, $panNumber, $aadhaarNumber)
    {
        $headers = [
            "Authorization: {$accessToken}",
            "Content-Type: application/json",
            "x-api-key: {$this->sandboxApiKey}"
        ];

        $payload = json_encode([
            "@entity" => "in.co.sandbox.kyc.pan_aadhaar.status",
            "pan" => $panNumber,
            "aadhaar_number" => $aadhaarNumber,
            "consent" => "Y",
            "reason" => "FOR KYC"
        ]);

        $ch = curl_init($this->panAadhaarLinkStatus);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status_code' => $status,
            'data' => json_decode($response, true)
        ];
    }

    public function verifyBankDetails($validated)
    {
        $accountNumber = $validated['account_number'];
        $ifscCode = $validated['ifsc'];

        $user = Auth::user();

        // Check if bank account already exists
        $existingAccount = KycDetail::where('account_number', $accountNumber)->first();

        if ($existingAccount && $existingAccount->client_id !== $user->client->id) {
            return response()->json([
                'ok' => false,
                'message' => 'This bank account number is already registered with another user.'
            ], 400);
        }

        $verifyHeaders = [
            "X-Client-Api-ID: {$this->apiHubKey}",
            "X-Client-Api-Secret: {$this->apiHubSecret}",
            "Content-Type: application/json",
            "Accept: */*"
        ];

        $payload = json_encode([
            "account_number" => $accountNumber,
            "ifsc_code" => strtoupper($ifscCode),
        ]);

        $ch = curl_init($this->bankUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $verifyHeaders
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $verifyData = json_decode($response, true);
        $this->recordApiHit(
            'bank',
            config('services.apihub.bank_path', 'v5/bank-verify'),
            $status >= 200 && $status < 300 && (($verifyData['status'] ?? false) === true || ($verifyData['success'] ?? false) === true),
            $status,
            ['account_number' => $accountNumber, 'ifsc_code' => $ifscCode],
            $verifyData
        );

        $bankData = $verifyData['data']['data']['bank_account_data']
            ?? $verifyData['data']['bank_account_data']
            ?? $verifyData['data']['data']
            ?? $verifyData['data']
            ?? null;

        if (
            $status !== 200
            || (empty($verifyData['status']) && empty($verifyData['success']))
            || empty($bankData)
        ) {
            $this->logBankApiResponse('apihub', 'v5/bank-verify (curl)', $accountNumber, $ifscCode, $status, $verifyData, [
                'ok' => false,
                'message' => 'Bank account verification failed',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Bank account verification failed',
                'response' => $verifyData
            ], 422);
        }

        $parsedBank = [
            'name_at_bank' => $bankData['name'] ?? $bankData['full_name'] ?? null,
            'bank_name' => $bankData['bank_name'] ?? $bankData['bank'] ?? null,
            'branch' => $bankData['branch'] ?? $bankData['branch_name'] ?? null,
            'account_number' => $bankData['account_number'] ?? $accountNumber,
            'ifsc' => $bankData['ifsc'] ?? $bankData['ifsc_code'] ?? $ifscCode,
            'account_status' => $bankData['account_status'] ?? null,
        ];
        $this->logBankApiResponse('apihub', 'bank-api/verify/hybrid (curl)', $accountNumber, $ifscCode, $status, $verifyData, $parsedBank);

        KycDetail::updateOrCreate(
            ['client_id' => $user->client->id],
            [
                'account_holder_name' => $bankData['name'] ?? $accountHolderName,
                'ifsc_code' => $bankData['ifsc'] ?? $ifscCode,
                'bank_name' => $bankData['bank_name'] ?? $bankName,
                'account_number' => $bankData['account_number'] ?? $accountNumber,
                'branch_name' => $bankData['branch'] ?? $branchName,
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Bank account verified & saved successfully',
            'bank' => [
                'name_at_bank' => $bankData['name'] ?? null,
                'bank_name' => $bankData['bank_name'] ?? null,
                'branch' => $bankData['branch'] ?? null,
                'account_number' => $bankData['account_number'] ?? null,
                'ifsc' => $bankData['ifsc'] ?? null,
                'account_status' => $bankData['account_status'] ?? null
            ]
        ]);
    }

}
