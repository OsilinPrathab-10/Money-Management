<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\PaymentMethod;
use App\Models\PaymentGateway;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\DB;

class PaymentControllerApi extends Controller
{
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
                $amount = $emi->pending_amount;
                $receipt = 'emi_' . $emi->id;
                break;

            case 'PARTIAL':
                $amount  = (float) $request->amount;
                $receipt = 'partial_' . $loan->id . '_' . time();
                break;

            case 'PREPAYMENT':
            case 'FORECLOSURE':
                $amount = (float) $request->amount;
                $receipt = strtolower($request->payment_type) . '_' . $loan->id;
                break;
        }

        $amountInPaise = (int) round($amount * 100);

        $api = new Api(
            env('RAZORPAY_KEY_ID'),
            env('RAZORPAY_KEY_SECRET')
        );

        $order = $api->order->create([
            'receipt'  => $receipt,
            'amount'   => $amountInPaise,
            'currency' => 'INR'
        ]);

        $payment = Payment::create([
            'client_id'        => $client->id,
            'loan_account_id'  => $loan->id,
            'emi_id'           => $request->payment_type === 'EMI' ? $request->emi_id : null,
            'order_id'         => $order['id'],
            'amount'           => $amount,
            'amount_paise'    => $amountInPaise,
            'payment_type'     => $request->payment_type,
            'status'           => 'pending',
        ]);

        return response()->json([
            'status' => true,
            'order_id' => $order['id'],
            'amount' => $amountInPaise,
            'razorpay_key' => env('RAZORPAY_KEY_ID'),
            'payment_id' => $payment->id,
        ]);
    }

    public function verifyEmiPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id'    => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature'  => 'required',
            'notes'                => 'nullable|string|max:1000',
        ]);

        $generatedSignature = hash_hmac(
            'sha256',
            $request->razorpay_order_id . '|' . $request->razorpay_payment_id,
            env('RAZORPAY_KEY_SECRET')
        );

        if ($generatedSignature !== $request->razorpay_signature) {
            return response()->json(['status' => false, 'message' => 'Signature mismatch'], 400);
        }

        $api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));
        $paymentInfo = $api->payment->fetch($request->razorpay_payment_id);

        $payment = Payment::where('order_id', $request->razorpay_order_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $payment->amount_paise !== (int) $paymentInfo['amount']) {
            return response()->json([
                'status' => false,
                'message' => 'Amount mismatch'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $payment->update([
                'payment_id' => $request->razorpay_payment_id,
                'signature'  => $request->razorpay_signature,
                'status'     => 'success',
                'payload'    => $paymentInfo,
            ]);

            switch ($payment->payment_type) {

                case 'EMI':

                    $emi = Emi::findOrFail($payment->emi_id);

                    // Check for unpaid prior EMIs
                    $isKandhuvatti = $emi->loanAccount && ($emi->loanAccount->loan_mode === 'interest_only');
                    if ($isKandhuvatti) {
                        $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                            ->where('instalment_number', '<', $emi->instalment_number)
                            ->whereIn('status', ['pending', 'overdue', 'partial'])
                            ->where(function($q) {
                                $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                            })
                            ->exists();
                    } else {
                        $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                            ->where('instalment_number', '<', $emi->instalment_number)
                            ->whereIn('status', ['pending', 'overdue', 'partial'])
                            ->exists();
                    }

                    if ($unpaidPrior) {
                        Log::warning('Blocked out-of-order EMI payment via Razorpay', [
                            'emi_id' => $emi->id,
                            'loan_account_id' => $emi->loan_account_id
                        ]);
                        throw new \Exception('Please pay your previous pending EMIs first.');
                    }

                    $amount = round($paymentInfo['amount'] / 100, 2);

                    $emi->update([
                        'payment_reference' => $payment->payment_id,
                        'payment_method'    => $paymentInfo['method'],
                        'paid_amount'       => $amount,
                        'pending_amount'    => 0,
                        'status'            => 'paid',
                        'paid_date'         => now(),
                    ]);

                    app(\App\Services\LoanPaymentService::class)
                        ->syncLoanTotals($emi->loan_account_id);

                    $loanAccountId = $emi->loan_account_id;
                    $loan = LoanAccount::findOrFail($emi->loan_account_id);

                    $message = 'EMI payment successful';
                    break;

                case 'PARTIAL':

                    $paymentAmount = $payment->amount;

                    $result = app(\App\Services\LoanPaymentService::class)
                        ->processPartialPayment(
                            $payment->loan_account_id,  // loanAccountId
                            $payment->amount,           // paymentAmount (RUPEES)
                            now(),                       // paymentDate
                            $paymentInfo['method'],      // paymentMethod
                            $payment->payment_id,        // paymentReference
                            'Partial payment via Razorpay'
                        );

                    if (!$result['success']) {
                        throw new \Exception($result['message']);
                    }

                    Log::info('Partial payment applied successfully', [
                        'loan_account_id' => $payment->loan_account_id,
                        'result' => $result['data'] ?? []
                    ]);

                    app(\App\Services\LoanPaymentService::class)
                        ->syncLoanTotals($payment->loan_account_id);

                    $loanAccountId = $payment->loan_account_id;

                    $message = 'Partial payment successful';

                    break;

                  case 'PREPAYMENT':

                      $result = app(\App\Services\LoanPaymentService::class)->processPrepayment(
                          $payment->loan_account_id,     // loanAccountId
                          $payment->amount,              // prepaymentAmount (RUPEES)
                          now(),                          // paymentDate
                          $paymentInfo['method'],         // paymentMethod
                          $payment->payment_id,           // paymentReference
                          'Prepayment via Razorpay'       // remarks
                      );

                      // ❌ If service failed → rollback payment
                      if (!$result['success']) {
                          Log::error('Prepayment failed in service', [
                              'loan_account_id' => $payment->loan_account_id,
                              'payment_id'      => $payment->id,
                              'reason'          => $result['message'] ?? 'Unknown error'
                          ]);

                          throw new \Exception($result['message'] ?? 'Prepayment failed');
                      }

                      // ✅ Log success
                      Log::info('Prepayment successful', [
                          'loan_account_id' => $payment->loan_account_id,
                          'payment_id'      => $payment->id,
                          'data'            => $result['data'] ?? []
                      ]);

                      $loanAccountId = $payment->loan_account_id;
                      $message = 'Prepayment successful';
                      break;

                case 'FORECLOSURE':

                    $loanAccount = LoanAccount::findOrFail($payment->loan_account_id);
                    if ($request->filled('notes')) {
                        $loanAccount->foreclose_notes = $request->notes;
                        $loanAccount->save();
                    }

                    $result = app(\App\Services\LoanPaymentService::class)
                        ->foreclose($payment->loan_account_id);

                    if (!$result['success']) {
                        throw new \Exception($result['message']);
                    }

                    $loanAccountId = $payment->loan_account_id;
                    $message = 'Loan foreclosed successfully';
                    break;

                default:
                    throw new \Exception('Invalid payment type');
            }

            DB::commit();

            return response()->json([
                'status'          => true,
                'message'         => $message,
                'loan_account_id' => $loanAccountId,
                'payment_type'    => $payment->payment_type,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment verification failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function cashFreeEmiOrder(Request $request)
    {
        $request->validate([
            "emi_id" => "required|integer",
            "loan_account_id" => "required|integer",
        ]);

        $user = Auth::user();
        $client = $user->client;

        // Fetch EMI
        $emi = Emi::where("id", $request->emi_id)
                  ->where("loan_account_id", $request->loan_account_id)
                  ->firstOrFail();

        // Generate unique order_id
        $orderId = "emi_" . $emi->id . "_" . time();

        // Cashfree order payload
        $payload = [
            "order_id" => $orderId,
            "order_amount" => (float) $emi->total_amount,
            "order_currency" => "INR",
            "customer_details" => [
                "customer_id" => (string)$client->id,
                "customer_email" => $client->client_email ?? "noemail@test.com",
                "customer_phone" => $client->client_phone ?? "9999999999",
            ],
            "order_meta" => [
                "return_url" => url('/cashfree/return?order_id={order_id}')
            ]
        ];

        $response = Http::withHeaders([
            "x-client-id"     => env("CASHFREE_APP_ID"),
            "x-client-secret" => env("CASHFREE_SECRET_KEY"),
            "x-api-version"   => env("CASHFREE_API_VERSION"),
        ])->post(env("CASHFREE_BASE_URL") . "/orders", $payload);

        if (!$response->successful()) {
            return response()->json([
                "status" => false,
                "message" => "Cashfree order creation failed",
                "error" => $response->json()
            ], 500);
        }

        $data = $response->json();

        // Save payment record
        Payment::create([
            "client_id" => $client->id,
            "loan_account_id" => $emi->loan_account_id,
            "emi_id" => $emi->id,
            "order_id" => $orderId,
            "amount" => $emi->total_amount,
            "status" => "PENDING"
        ]);

        return response()->json([
            "status" => true,
            "order_id" => $orderId,
            "payment_session_id" => $data["payment_session_id"],
            "amount" => $emi->total_amount,
            "cashfree_app_id" => env("CASHFREE_APP_ID")
        ]);
    }

    /**
     * Verify Cashfree EMI payment via API
     */
    public function verifyCashFreeEmiPayment(Request $request)
    {
        $request->validate([
            "order_id" => "required|string"
        ]);

        $orderId = $request->order_id;

        $response = Http::withHeaders([
            "x-client-id"     => env("CASHFREE_APP_ID"),
            "x-client-secret" => env("CASHFREE_SECRET_KEY"),
            "x-api-version"   => env("CASHFREE_API_VERSION")
        ])->get(env("CASHFREE_BASE_URL") . "/orders/{$orderId}/payments");

        if (!$response->successful()) {
            return response()->json([
                "status" => false,
                "message" => "Payment verification failed",
                "error" => $response->json()
            ], 500);
        }

        $data = $response->json();

        if (!empty($data["data"])) {
            $paymentInfo = $data["data"][0];

            Payment::where("order_id", $orderId)->update([
                "status" => $paymentInfo["payment_status"], // SUCCESS / FAILED
                "payment_id" => $paymentInfo["cf_payment_id"] ?? null,
            ]);

            if ($paymentInfo["payment_status"] === "SUCCESS") {
                $payRec = Payment::where("order_id", $orderId)->first();
                if ($payRec && $payRec->emi_id) {
                    $emi = Emi::find($payRec->emi_id);
                    if ($emi) {
                        // Check for unpaid prior EMIs
                        $isKandhuvatti = $emi->loanAccount && ($emi->loanAccount->loan_mode === 'interest_only');
                        if ($isKandhuvatti) {
                            $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                                ->where('instalment_number', '<', $emi->instalment_number)
                                ->whereIn('status', ['pending', 'overdue', 'partial'])
                                ->where(function($q) {
                                    $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                                })
                                ->exists();
                        } else {
                            $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                                ->where('instalment_number', '<', $emi->instalment_number)
                                ->whereIn('status', ['pending', 'overdue', 'partial'])
                                ->exists();
                        }

                        if (!$unpaidPrior) {
                            $emi->update([
                                "status" => "paid",
                                "paid_amount" => $payRec->amount,
                                "pending_amount" => 0,
                                "paid_date" => now(),
                            ]);
                            
                            app(\App\Services\LoanPaymentService::class)
                                ->syncLoanTotals($emi->loan_account_id);
                        } else {
                            Log::warning('Cashfree payment received for out-of-order EMI', [
                                'emi_id' => $emi->id,
                                'loan_account_id' => $emi->loan_account_id
                            ]);
                            // Note: We don't throw exception here as it's a callback, 
                            // but we don't mark the EMI as paid. The user should contact support.
                            // Better: process it as partial or keep it in payments table for manual reconciliation.
                        }
                    }
                }
            }
        }

        return response()->json([
            "status" => true,
            "data" => $data["data"] ?? []
        ]);
    }


    public function enabledMethods()
    {
        $paymentTypes = PaymentMethod::where('is_enabled', true)->get();
        $paymentGateways = PaymentGateway::where('enabled', true)->get();

        return response()->json([
            'status' => true,
            'payment_types' => $paymentTypes,
            'payment_gateways' => $paymentGateways
        ]);
    }

}
