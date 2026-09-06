<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
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

    public function __construct()
    {
        $this->apiHubKey = env('API_KEY');
        $this->apiHubSecret = env('SECRET_KEY');
        $this->sandboxApiSecret = env('SANDBOX_SECRET_KEY');
        $this->sandboxApiKey = env('SANDBOX_API_KEY');
        $this->authUrl = 'https://api.sandbox.co.in/authenticate';
        $this->panVerifyUrl = 'https://api.sandbox.co.in/kyc/pan/verify';
        $this->aadhaarOtpUrl = 'https://api.sandbox.co.in/kyc/aadhaar/okyc/otp';
        $this->aadhaarVerifyOtpUrl = 'https://api.sandbox.co.in/kyc/aadhaar/okyc/otp/verify';
        $this->panAadhaarLinkStatus = 'https://api.sandbox.co.in/kyc/pan-aadhaar/status';
        $this->bankVerifyUrl = 'https://api.sandbox.co.in/bank/{ifsc}/accounts/{account_number}/verify';
        $this->apiKey = env('API_KEY');
        $this->apiSecret = env('SECRET_KEY');
        $this->mode = env('MODE');
        $this->bankUrl = 'https://apihub.solutions/bank-api/verify/hybrid';
        $this->gridlinesApiKey = env('GRIDLINES_API_KEY');
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

    public function verifyPan($panNumber, $aadhaarNumber)
    {
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
            'ifsc' => $ifsc,
            'consent' => 'Y'
        ];

        $ch = curl_init($this->bankUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apiKey,
                'X-API-Secret: ' . $this->apiSecret,
                'X-API-Mode: ' . $this->mode,
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

        Log::info('Bank Verification Response', ['status_code' => $httpCode, 'response' => $decoded]);

        return ['success' => $httpCode >= 200 && $httpCode < 300, 'status_code' => $httpCode, 'data' => $decoded];
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
            "X-API-Key: {$this->apiHubKey}",
            "X-API-Secret: {$this->apiHubSecret}",
            "X-API-Mode: production",
            "Content-Type: application/json",
            "Accept: */*"
        ];

        $payload = json_encode([
            "account_number" => $accountNumber,
            "ifsc" => $ifscCode,
            "consent" => "Y"
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

        if (
            $status !== 200 ||
            empty($verifyData['status']) ||
            empty($verifyData['data']['data']['bank_account_data'])
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Bank account verification failed',
                'response' => $verifyData
            ], 422);
        }

        $bankData = $verifyData['data']['data']['bank_account_data'];

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
