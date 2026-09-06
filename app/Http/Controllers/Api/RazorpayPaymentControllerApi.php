<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use App\Models\Payment;
use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\PaymentGateway;
use App\Services\RazorpayWebhookService;

class RazorpayPaymentControllerApi extends Controller
{
    protected $webhookService;

    public function __construct(RazorpayWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * --------------------------------
     * CREATE RAZORPAY ORDER
     * (CALLED BY FLUTTER ONLY)
     * --------------------------------
     */
    public function createEmiOrder(Request $request)
    {
        $request->validate([
            'loan_account_id' => 'required|integer',
            'payment_type'    => 'required|in:EMI,PREPAYMENT,FORECLOSURE,PARTIAL',
            'emi_id'          => 'required_if:payment_type,EMI',
            'amount'          => 'required_if:payment_type,PREPAYMENT,FORECLOSURE,PARTIAL|numeric|min:1',
        ]);

        if ($request->payment_type === 'PARTIAL' && floor($request->amount) != $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Partial payment amount must be a whole number.'
            ], 422);
        }

        $user   = Auth::user();
        $client = $user->client;

        $loan = LoanAccount::where('id', $request->loan_account_id)
            ->where('client_id', $client->id)
            ->firstOrFail();

        switch ($request->payment_type) {

            case 'EMI':
                $emi = Emi::where('id', $request->emi_id)
                    ->where('loan_account_id', $loan->id)
                    ->firstOrFail();

                $amount  = $emi->pending_amount;
                $receipt = 'emi_' . $emi->id;
                break;

            case 'PARTIAL':
                $amount  = (float) $request->amount;
                $receipt = 'partial_' . $loan->id . '_' . time();
                break;

            case 'PREPAYMENT':
            case 'FORECLOSURE':
                $amount  = (float) $request->amount;
                $receipt = strtolower($request->payment_type) . '_' . $loan->id;
                break;
        }

        $amountInPaise = (int) round($amount * 100);

        try {
            // Get credentials from database
            $credentials = $this->getRazorpayCredentials();
            $keyId = $credentials['key_id'];
            $keySecret = $credentials['key_secret'];
        } catch (\Exception $e) {
            Log::error('Failed to get Razorpay credentials', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }

        $api = new Api($keyId, $keySecret);

        try {
            $order = $api->order->create([
                'receipt'  => $receipt,
                'amount'   => $amountInPaise,
                'currency' => 'INR'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create Razorpay order', [
                'error' => $e->getMessage(),
                'amount' => $amountInPaise,
                'loan_account_id' => $loan->id
            ]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        Payment::create([
            'client_id'       => $client->id,
            'loan_account_id' => $loan->id,
            'emi_id'          => $request->payment_type === 'EMI' ? $request->emi_id : null,
            'order_id'        => $order['id'],
            'amount'          => $amount,
            'amount_paise'    => $amountInPaise,
            'payment_type'    => $request->payment_type,
            'status'          => 'pending',
        ]);

        Log::info('Razorpay order created', [
            'order_id' => $order['id'],
            'amount' => $amount,
            'amount_in_paise' => $amountInPaise,
            'payment_type' => $request->payment_type,
            'loan_account_id' => $loan->id,
        ]);

        return response()->json([
            'status'           => true,
            'order_id'         => $order['id'],
            'amount'           => $amountInPaise,
            'razorpay_key'     => $keyId,
            'loan_account_id'  => $loan->id,
        ]);
    }

    /**
     * --------------------------------
     * RAZORPAY WEBHOOK
     * (CALLED AUTOMATICALLY BY RAZORPAY)
     * --------------------------------
     */
    public function razorpayWebhook(Request $request)
    {
        // 1. Capture details
        $rawContent = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');
        $payload = $request->all();

        // 2. Delegate to Service
        $result = $this->webhookService->handleWebhook($payload, $signature, $rawContent);      

        return response()->json($result, $result['status']);
    }

    /**
     * Get Razorpay credentials from database
     */
    private function getRazorpayCredentials()
    {
        $razorpay = PaymentGateway::where('gateway', 'razorpay')
            ->where('enabled', true)
            ->first();

        if (!$razorpay) {
            throw new \Exception('Razorpay payment method is not configured or disabled');
        }

        $keyId = $razorpay->api_key;
        $keySecret = $razorpay->api_secret;

        if (!$keyId || !$keySecret) {
            throw new \Exception('Razorpay credentials are incomplete');
        }

        return [
            'key_id' => $keyId,
            'key_secret' => $keySecret
        ];
    }
}
