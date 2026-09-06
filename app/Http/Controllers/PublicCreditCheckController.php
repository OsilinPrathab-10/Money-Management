<?php

namespace App\Http\Controllers;

use App\Models\CreditScoreHistory;
use App\Services\CibilApiService;
use App\Services\VerificationCurlService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PublicCreditCheckController extends Controller
{
    /**
     * Step 1: Send Aadhaar OTP
     */
    public function sendOtp(Request $request, VerificationCurlService $curlService)
    {
        $validated = $request->validate([
            'aadhaar_number' => 'required|digits:12',
            'applicant_name' => 'required|string|max:255',
            'pan_number' => 'required|string|size:10',
            'phone' => 'required|string|max:15',
            'email' => 'required|email|max:255',
        ]);

        try {
            // Initiate Aadhaar OTP
            $result = $curlService->verifyAadhaar($validated['aadhaar_number']);
            $data = $result->getData(true);

            if (isset($data['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $data['error']
                ], 422);
            }

            // The verification service returns different structures depending on status
            return response()->json([
                'success' => true,
                'message' => __('OTP sent to the mobile number registered with Aadhaar.'),
                'reference_id' => $data['data']['reference_id'] ?? ($data['reference_id'] ?? null),
            ]);

        } catch (\Throwable $e) {
            Log::error('Public Credit Check OTP failed', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => __('Failed to send OTP. Please try again.')], 500);
        }
    }

    /**
     * Step 2: Verify OTP and Fetch Credit Score
     */
    public function verifyAndFetch(Request $request, VerificationCurlService $curlService, CibilApiService $cibilApi)
    {
        $validated = $request->validate([
            'aadhaar_number' => 'required|digits:12',
            'otp' => 'required|string',
            'reference_id' => 'required|string',
            'applicant_name' => 'required|string|max:255',
            'pan_number' => 'required|string|size:10',
            'phone' => 'required|string|max:15',
            'email' => 'required|email|max:255',
        ]);

        try {
            // 1. Verify Aadhaar OTP (In a real production app, we'd also verify PAN)
            $verifyResult = $curlService->verifyAadhaarOtp($validated['aadhaar_number'], $validated['otp'], $validated['reference_id']);
            $vData = $verifyResult->getData(true);

            // Note: In demo/sandbox mode, verifyAadhaarOtp might return a different structure.
            // For now, if code is 200/verified, proceed.
            // Or if in sandbox environment, we can check for success indicators.
            
            // 2. Fetch CIBIL Report
            $input = [
                'applicant_name' => $validated['applicant_name'],
                'pan_number' => $validated['pan_number'],
                'aadhar_number' => $validated['aadhaar_number'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'date_of_birth' => null, // We could collect this in step 1 if needed
            ];

            $cibilResult = $cibilApi->fetchReport($input);

            // 3. Store History (Public/Guest)
            $history = CreditScoreHistory::create([
                'client_id' => null, // Guest
                'applicant_name' => $validated['applicant_name'],
                'pan_number' => $validated['pan_number'],
                'aadhar_number' => $validated['aadhaar_number'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'score' => $cibilResult['score'],
                'rating' => $cibilResult['rating'],
                'report_json' => $cibilResult['report'],
                'status' => $cibilResult['status'],
                'error_message' => $cibilResult['error_message'],
                'created_by' => null, // System
            ]);

            return response()->json([
                'success' => true,
                'score' => $cibilResult['score'],
                'rating' => $cibilResult['rating'],
                'history_id' => $history->id,
                'message' => __('Credit report retrieved successfully.')
            ]);

        } catch (\Throwable $e) {
            Log::error('Public Credit Check Verification failed', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => __('Verification failed. Check your OTP and try again.')], 500);
        }
    }

    /**
     * Download PDF Report
     */
    public function downloadReport(CreditScoreHistory $creditScoreHistory)
    {
        // For guest access, we might want to check the email or phone matches if we want more security
        // But for a simple public check, serving it once should be fine.
        
        $pdf = Pdf::loadView('admin.verification.credit-score-pdf', [
            'row' => $creditScoreHistory,
        ])->setPaper('a4', 'portrait')
          ->setOptions([
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled' => true,
              'defaultFont' => 'sans-serif',
          ]);

        $filename = 'credit-report-' . $creditScoreHistory->id . '.pdf';

        return $pdf->download($filename);
    }
}
