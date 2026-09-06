<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VerificationCurlService;
use App\Rules\KycRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmailOtp;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\KycDetail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class VerificationControllerApi extends Controller
{
    public function verifyAadhaar(Request $request, VerificationCurlService $curlService)
    {
        $validated = $request->validate([
            'aadhaar_number' => ['required', new KycRule('aadhaar')]
        ]);

        $result = $curlService->verifyAadhaar(
            $validated['aadhaar_number']
        );

        return response()->json($result);
    }

    public function resendAadhaarOtp(Request $request, VerificationCurlService $curlService)
    {
        $validated = $request->validate([
            'aadhaar_number' => ['required', new KycRule('aadhaar')]
        ]);

        $result = $curlService->resendAadhaarOtp(
            $validated['aadhaar_number']
        );

        return response()->json($result);
    }

    public function verifyAadhaarOtp(Request $request, VerificationCurlService $curlService)
    {
        $validated = $request->validate([
            'aadhaar_number' => 'required|string',
            'otp' => 'required|string',
            'reference_id' => 'required|string',
        ]);

        $result = $curlService->verifyAadhaarOtp(
            $validated['aadhaar_number'],
            $validated['otp'],
            $validated['reference_id']
        );

        return response()->json($result);
    }

    public function verifyPan(Request $request, VerificationCurlService $curlService)
    {
        $user = Auth::user();
        $client = Client::where('user_id', $user->id)->first();

        $validated = $request->validate([
            'pan_number' => ['required', new KycRule('pan')],
            'aadhaar_number' => ['required', new KycRule('aadhaar')]
        ]);

        // Check PAN already linked with another client
        $exists = KycDetail::where('pan_number', $validated['pan_number'])
            ->where('client_id', '!=', $client->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'This PAN number is already linked with another client.'
            ], 409);
        }

        // Hardcoded values
        $entity = "in.co.sandbox.kyc.pan_aadhaar.status";
        $consent = "Y";
        $reason = "FOR KYC";

        // Call verification service
        $result = $curlService->verifyPan(
            $validated['pan_number'],
            $validated['aadhaar_number']
        );

        $data = $result->getData(true);

        $aadhaarLinkData = $data['pan_aadhaar_link_status']['data']['data'] ?? [];

        $isAadhaarLinked = ($aadhaarLinkData['aadhaar_seeding_status'] ?? 'n') === 'y';

        if ($isAadhaarLinked) {

            KycDetail::updateOrCreate(
                ['client_id' => $client->id],
                [
                    'pan_number' => $validated['pan_number']
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'PAN verified and saved successfully.',
                'data' => $data
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => $aadhaarLinkData['message'] ?? 'PAN verification failed.',
            'data' => $data
        ], 422);
    }

    // public function verifyBank(Request $request, VerificationCurlService $curlService)
    // {
    //     $user = Auth::user();
    //     $client = Client::where('user_id', $user->id)->first();

    //     $validated = $request->validate([
    //         'account_number' => ['required', new KycRule('bank')],
    //         'ifsc' => ['required', new KycRule('ifsc')],
    //         'account_holder_name' => 'required|string',
    //         'bank_name' => 'required|string',
    //         'branch_name' => 'required|string',
    //         'account_type' => 'required|string',
    //         'file_front' => 'required|file|mimes:pdf|max:5120', // max 5MB
    //     ]);

    //     // --- Step 1: Bank account verification ---
    //     $bankResult = $curlService->verifyBankAccount(
    //         $validated['account_number'],
    //         $validated['ifsc']
    //     );

    //     $bankVerified =
    //         ($bankResult['success'] === true) &&
    //         ($bankResult['status_code'] == 200) &&
    //         isset($bankResult['data']['status']) &&
    //         $bankResult['data']['status'] === true &&
    //         ($bankResult['data']['data']['data']['code'] ?? null) === "1000" &&
    //         ($bankResult['data']['data']['data']['message'] ?? null) === "Bank Account details verified successfully.";

    //         // --- Step 2: Save PDF locally ---
    //     $file = $request->file('file_front');

    //     Storage::disk('public')->makeDirectory('bank_statements');

    //     $filename = 'bank_statement_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

    //     $storedPath = $file->storeAs('bank_statements', $filename, 'public');

    //     $absolutePath = Storage::disk('public')->path($storedPath);

    //     $statementResult = $curlService->verifyBankStatement(
    //         $absolutePath,
    //         'Y',
    //         $request->header('X-Reference-ID', Str::uuid()->toString())
    //     );

    //     // Verified?
    //     $statementVerified = ($statementResult['success'] === true && !empty($statementResult['data']));

    //     // --- Step 4: Save KYC details only if both succeed ---
    //     if ($bankVerified && $statementVerified) {
    //         KycDetail::updateOrCreate(
    //             ['client_id' => $client->id],
    //             [
    //                 'account_holder_name' => $validated['account_holder_name'],
    //                 'account_number' => $validated['account_number'],
    //                 'ifsc_code' => $validated['ifsc'],
    //                 'account_type' => $validated['account_type'],
    //                 'bank_name' => $validated['bank_name'],
    //                 'branch_name' => $validated['branch_name'],
    //                 'bank_statement' => $storedPath, // saved PDF
    //             ]
    //         );

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Bank account and statement verified successfully.',
    //             'bank_verification' => $bankResult['data']['data']['data'] ?? null,
    //             'statement_ocr' => $statementResult['data'] ?? null,
    //         ]);
    //     }

    //     // --- Step 5: Return error if any verification fails ---
    //     return response()->json([
    //         'status' => false,
    //         'message' => 'Verification failed.',
    //         'bank_verification' => $bankResult['data']['data']['data'] ?? null,
    //         'statement_ocr' => $statementResult['data'] ?? null,
    //     ], 422);
    // }

    public function verifyBank(Request $request, VerificationCurlService $curlService)
    {
        $validated = $request->validate([
            'account_number' => ['required', new KycRule('bank')],
            'ifsc' => ['required', new KycRule('ifsc')],
            'file_front' => 'required|file|mimes:pdf|max:5120',
        ]);

        $result = $curlService->verifyBankDetails($validated);

        return $result; // return directly
    }

    public function sendEmailOtp(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $otp = rand(100000, 999999);
        $ttl = 40; // seconds

        Cache::put('email_otp_' . $request->email, $otp, now()->addSeconds($ttl));

        // Pass both OTP and email to the mailable
        Mail::to($request->email)->send(new SendEmailOtp($otp, $request->email));

        return response()->json([
            'message' => 'OTP sent to your email address.',
            'expires_in' => $ttl . ' seconds'
        ]);
    }

    public function resendEmailOtp(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $otp = rand(100000, 999999);
        $ttl = 40; // seconds

        Cache::put('email_otp_' . $request->email, $otp, now()->addSeconds($ttl));

        // Pass both OTP and email to the mailable
        Mail::to($request->email)->send(new SendEmailOtp($otp, $request->email));

        return response()->json([
            'message' => 'OTP resent to your email address.',
            'expires_in' => $ttl . ' seconds'
        ]);
    }

    public function verifyEmailOtp(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'otp' => 'required|digits:6',
        ]);

        $cachedOtp = Cache::get('email_otp_' . $request->email);

        if (!$cachedOtp) {
            return response()->json(['error' => 'OTP expired or not found.'], 422);
        }

        if ($cachedOtp != $request->otp) {
            return response()->json(['error' => 'Invalid OTP.'], 422);
        }

        // Remove OTP after success
        Cache::forget('email_otp_' . $request->email);

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function userUpdate(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'address' => 'required|string',
            'location_id' => 'required|integer',
        ]);

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        $user->save();

        Client::updateOrCreate(
            ['user_id' => $user->id],
            [
                'client_name' => $request->name,
                'client_email' => $request->email,
                'address' => $request->address,
                'location_id' => $request->location_id,
            ]
        );

        return response()->json([
            'message' => 'User information updated successfully.',
            'user' => $user,
        ]);
    }

}
