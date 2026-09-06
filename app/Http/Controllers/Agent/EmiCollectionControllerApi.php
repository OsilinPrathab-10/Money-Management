<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\EmiCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Emi;
use App\Models\EmiAgentAssignment;
use App\Models\AgentActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\LoanAccount;
use App\Services\AgentClientOtpService;
use App\Models\PaymentGateway;
use App\Services\RazorpayWebhookService;
use Illuminate\Support\Facades\Crypt;

class EmiCollectionControllerApi extends Controller
{
    protected RazorpayWebhookService $webhookService;

    public function __construct(RazorpayWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
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

    public function index(Request $request, $emiId)
    {
        $agentId = Auth::user()->id;

        $collections = EmiCollection::where('emi_id', $emiId)
            ->where('agent_id', $agentId)
            ->orderByDesc('collected_at')
            ->get()
            ->map(function ($collection) {
                return [
                    'id' => $collection->id,
                    'amount' => $collection->amount,
                    'payment_method' => $collection->payment_method,
                    'collected_at' => $collection->collected_at->toDateTimeString(),
                    'remarks' => $collection->remarks,
                    'proof_image_url' => $collection->proof_image_path ? Storage::url($collection->proof_image_path) : null,
                ];
            });

        return response()->json([
            'emi_id' => $emiId,
            'collections' => $collections,
        ]);
    }

    /**
     * GET: Collection details for loan account (amounts, charges, EMI list)
     * Shows actual EMI overdue data on collection page
     */
    public function getCollectionDetails(Request $request)
    {
        $agentId = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'loan_account_id' => 'required|exists:loan_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $loanAccount = LoanAccount::with([
            'client',
            'emis' => function ($q) use ($agentId) {
                $q->whereHas('assignments', function ($a) use ($agentId) {
                    $a->where('agent_id', $agentId)
                        ->whereIn('status', ['assigned', 'visited']);
                })->orderBy('due_date');
            }
        ])->findOrFail($request->loan_account_id);

        // IMPORTANT: pending_amount ALREADY includes penalty!
        // pending_amount = total_amount + previous_balance + penalty_amount
        // So we need to subtract penalty to get EMI-only amount
        $totalEmiOnly = 0;
        $totalPenalty = 0;
        $totalDue = 0;

        foreach ($loanAccount->emis as $emi) {
            $projected = $this->getProjectedEmiData($emi);
            $pending = $projected['pending_amount'];
            $penalty = $emi->penalty_amount ?? 0;

            // EMI only = pending - penalty
            $emiOnly = max(0, $pending - $penalty);

            $totalEmiOnly += $emiOnly;
            $totalPenalty += $penalty;
            $totalDue += $pending;  // projected pending_amount is the actual total due
        }

        $partialService = app(\App\Services\PartialPaymentConfigService::class);
        $minimumPartial = $partialService->isActive()
            ? (int) ceil($totalDue * ($partialService->getMinimumPercentage() / 100))
            : 0;

        return response()->json([
            'success' => true,
            'loan_account_id' => $loanAccount->id,
            'loan_account_number' => $loanAccount->account_number,
            'client_name' => $loanAccount->client->client_name,
            'client_phone' => $loanAccount->client->client_phone,
            'overdue_days' => $loanAccount->emis->max('dpd_days'),
            'remaining_principal_balance' => (float)$loanAccount->remaining_principal_balance,
            'principal_allocated' => (float)$loanAccount->principal_allocated,
            'principal_pending' => (float)$loanAccount->principal_pending,
            'amounts' => [
                'emi_pending' => (float) $totalEmiOnly,   // EMI amount only (without penalty)
                'penalty' => (float) $totalPenalty,        // Penalty amount
                'total_due' => (float) $totalDue,          // Total to pay (pending_amount already includes penalty)
                'minimum_partial' => (float) $minimumPartial,
            ],
            'emis' => $loanAccount->emis->map(function ($emi) {
                $projected = $this->getProjectedEmiData($emi);
                $penalty = $emi->penalty_amount ?? 0;
                $pending = $projected['pending_amount'];
                $emiOnly = max(0, $pending - $penalty);  // EMI only = pending - penalty
    
                return [
                    'id' => $emi->id,
                    'due_date' => $emi->due_date,
                    'emi_amount' => (float) $emi->total_amount,      // Original EMI amount
                    'previous_balance' => (float) $emi->previous_balance, // Balance from previous month
                    'pending' => (float) $emiOnly,                    // Pending EMI (without penalty)
                    'penalty' => (float) $penalty,                    // Penalty
                    'total_due' => (float) $pending,                  // Total to pay (already includes penalty)
                    'status' => match ($projected['status']) {
                        'paid' => 'Recovered',
                        'partial' => 'Partially Recovered',
                        'overdue' => 'Overdue',
                        default => ucfirst($projected['status'])
                    },
                    'dpd_days' => $emi->dpd_days,
                ];
            }),
            'currency' => 'INR'
        ]);
    }

    /**
     * POST: Collection action endpoint (dropdown-based actions)
     * Handles: send-otp, verify-otp, initiate-payment (based on payment_method)
     */
    public function collectionAction(Request $request)
    {
        $agentId = Auth::user()->id;
        $type = $request->input('type');

        switch ($type) {
            case 'calculate-partial':
                return $this->calculatePartial($request);

            case 'send-otp':
                // Only for in_hand payment method
                if ($request->payment_method !== 'in_hand') {
                    return response()->json([
                        'success' => false,
                        'message' => 'OTP is only required for in_hand payment method'
                    ], 400);
                }
                return $this->sendOtp($request);

            case 'verify-otp':
                return $this->verifyOtp($request);

            case 'initiate-payment':
                // Route based on payment_method
                if ($request->payment_method === 'direct') {
                    return $this->initiateDirect($request);
                } elseif ($request->payment_method === 'payment_link') {
                    return $this->generatePaymentLink($request);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid payment method for initiate-payment'
                    ], 400);
                }

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid type. Allowed: calculate-partial, send-otp, verify-otp, initiate-payment'
                ], 400);
        }
    }

    /**
     * Generate payment link via Razorpay
     * Supports both single EMI and multi-EMI payments
     */
    private function generatePaymentLink(Request $request)
    {
        $agentId = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'emi_id' => 'nullable|exists:emis,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:overdue,partial',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->payment_type === 'partial' && floor($request->amount) != $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Partial payment amount must be a whole number.'
            ], 422);
        }

        // Unique group ID
        $groupId = 'GRP_' . time() . '_' . $agentId . '_' . rand(1000, 9999);

        /** -------------------------------
         * SINGLE / MULTI EMI RESOLUTION
         * ------------------------------- */
        if ($request->emi_id) {

            $emi = Emi::with(['loanAccount.client'])
                ->where('id', $request->emi_id)
                ->where('loan_account_id', $request->loan_account_id)
                ->firstOrFail();

            $emis = collect([$emi]);
            $loanAccount = $emi->loanAccount;

        } else {

            $overdueEmis = $this->getOverdueEmisForLoanAccount(
                $request->loan_account_id,
                $agentId
            );

            if ($overdueEmis->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No overdue EMIs found for this loan account'
                ], 404);
            }

            $emis = $overdueEmis;
            $loanAccount = $overdueEmis->first()->loanAccount;
        }

        /** -------------------------------
         * AMOUNT VALIDATION
         * ------------------------------- */
        $totalDueWithPenalty = $emis->sum(function ($emi) {
            return $emi->pending_amount;
        });

        if ($request->amount > $totalDueWithPenalty) {
            return response()->json([
                'success' => false,
                'message' => "Maximum payable amount is ₹{$totalDueWithPenalty}"
            ], 422);
        }

        /** -------------------------------
         * CREATE COLLECTION RECORDS
         * ------------------------------- */
        $collectionIds = [];
        $remainingAmount = $request->amount;

        foreach ($emis as $emi) {

            if ($remainingAmount <= 0) {
                break;
            }

            $emiDue = $emi->pending_amount + ($emi->penalty_amount ?? 0);
            $amountForThisEmi = min($remainingAmount, $emiDue);

            $collection = EmiCollection::create([
                'emi_id' => $emi->id,
                'agent_id' => $agentId,
                'amount' => $amountForThisEmi,
                'payment_method' => 'payment_link',
                'payment_type' => $request->payment_type,
                'status' => 'in_progress',
                'collected_at' => now(),
                'remarks' => $emis->count() > 1
                    ? "Multi-EMI payment link | Group: {$groupId}"
                    : 'Payment link generated',
            ]);

            $collectionIds[] = $collection->id;
            $remainingAmount -= $amountForThisEmi;
        }

        /** -------------------------------
         * RAZORPAY PAYMENT LINK
         * ------------------------------- */
        try {
            // VALIDATE AMOUNT LIMIT FIRST
            $amountPaise = (int) round($request->amount * 100);
            $maxAmountPaise = 5000000; // ₹50,000
            $maxAmountInr = 50000;

            if ($amountPaise > $maxAmountPaise) {
                Log::warning('Payment link amount exceeds Razorpay maximum', [
                    'requested_amount' => $request->amount,
                    'max_allowed' => $maxAmountInr,
                    'loan_account_id' => $loanAccount->id,
                ]);

                EmiCollection::whereIn('id', $collectionIds)->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount ₹' . number_format($request->amount, 2) . ' exceeds Razorpay maximum limit of ₹' . number_format($maxAmountInr, 2) . '. Please collect partial payment of maximum ₹' . number_format($maxAmountInr, 2) . ' each.',
                    'error' => 'amount_exceeds_maximum',
                    'max_allowed' => $maxAmountInr,
                    'current_amount' => $request->amount,
                ], 422);
            }

            if ($amountPaise < 100) {
                EmiCollection::whereIn('id', $collectionIds)->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount must be at least ₹1',
                    'error' => 'amount_too_small',
                ], 422);
            }

            $credentials = $this->getRazorpayCredentials();

            $razorpay = new \Razorpay\Api\Api(
                $credentials['key_id'],
                $credentials['key_secret']
            );

            $paymentLinkData = [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'description' => $emis->count() > 1
                    ? "Payment for {$emis->count()} EMIs - {$loanAccount->account_number}"
                    : "EMI Payment - {$loanAccount->account_number}",

                'customer' => [
                    'name' => $loanAccount->client->client_name,
                    'email' => $loanAccount->client->client_email ?? 'noemail@example.com',
                    'contact' => $loanAccount->client->client_phone,
                ],

                'notify' => [
                    'sms' => true,
                    'email' => false,
                ],

                'reminder_enable' => true,

                // 🔑 IMPORTANT FOR WEBHOOK
                'notes' => [
                    'loan_account_id' => $loanAccount->id,
                    'collection_id' => $collectionIds[0], // First collection ID (backward compatibility)
                    'collection_ids' => implode(',', $collectionIds), // All collection IDs for multi-EMI
                    'emi_ids' => implode(',', $emis->pluck('id')->toArray()),
                    'group_id' => $groupId,
                    'emi_count' => $emis->count(),
                    'payment_type' => $request->payment_type,
                ],
            ];

            $paymentLink = $razorpay->paymentLink->create($paymentLinkData);

            /** -------------------------------
             * ✅ GENERATE LOG 
             * ------------------------------- */
            Log::info('Razorpay payment link generated', [
                'payment_link_id' => $paymentLink->id,
                'short_url' => $paymentLink->short_url,
                'amount' => $request->amount,
                'loan_account_id' => $loanAccount->id,
                'collection_ids' => $collectionIds,
                'emi_ids' => $emis->pluck('id')->toArray(),
                'group_id' => $groupId,
            ]);

            // Store reference
            foreach ($emis as $emi) {
                $emi->payment_reference = $paymentLink->id;
                $emi->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment link generated successfully',
                'payment_link' => $paymentLink->short_url,
                'payment_link_id' => $paymentLink->id,
                'collection_ids' => $collectionIds,
                'group_id' => $groupId,
                'emi_count' => $emis->count(),
            ]);

        } catch (\Razorpay\Api\Exceptions\BadRequestException $e) {
            Log::error('Razorpay BadRequestException', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'amount' => $request->amount,
            ]);

            EmiCollection::whereIn('id', $collectionIds)->delete();

            $errorMsg = $e->getMessage();
            if (stripos($errorMsg, 'amount') !== false || stripos($errorMsg, 'exceeds') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount error: Maximum allowed is ₹50,000. Please collect partial payment.',
                    'error' => 'amount_error',
                    'max_allowed' => 50000,
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment gateway error: ' . $errorMsg,
                'error' => 'razorpay_bad_request',
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to generate payment link', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'amount' => $request->amount,
            ]);

            EmiCollection::whereIn('id', $collectionIds)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate payment link. IMPORTANT: Maximum payment amount allowed is ₹50,000. Please collect partial payments.',
                'error' => 'payment_link_failed',
                'max_allowed' => 50000,
            ], 500);
        }
    }


    /**
     * Calculate partial payment amount based on admin configuration
     */
    private function calculatePartial(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'emi_id' => 'required|exists:emis,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $emi = Emi::where('id', $request->emi_id)
            ->where('loan_account_id', $request->loan_account_id)
            ->firstOrFail();

        $emi->loadMissing('loanAccount');
        $partialService = app(\App\Services\PartialPaymentConfigService::class);
        $rules = $partialService->rulesForEmi($emi, $emi->loanAccount);

        return response()->json([
            'success' => true,
            'loan_account_id' => $emi->loan_account_id,
            'emi_id' => $emi->id,
            'emi_amount' => (float) $rules['outstanding_due'],
            'penalty' => (float) ($emi->penalty_amount ?? 0),
            'total_due' => (float) $rules['outstanding_due'],
            'minimum_partial' => (float) $rules['minimum_partial_amount'],
            'currency' => 'INR',
            'partial_payment' => $rules,
        ]);
    }

    /**
     * Helper: Get all overdue EMIs for a loan account assigned to agent
     * Returns EMIs ordered by due_date (oldest first)
     */
    private function getOverdueEmisForLoanAccount($loanAccountId, $agentId)
    {
        return Emi::whereHas('assignments', function ($q) use ($agentId) {
            $q->where('agent_id', $agentId);
        })
            ->where('loan_account_id', $loanAccountId)
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->orderBy('due_date', 'asc') // Oldest first
            ->get();
    }

    /**
     * Helper: Distribute payment across multipl    e EMIs (oldest first)
     * Creates EmiCollection records and updates EMI statuses
     */
    private function distributePaymentAcrossEmis($emis, $paymentAmount, $agentId, $paymentMethod, $paymentType, $proofImagePath = null)
    {
        $remainingAmount = $paymentAmount;
        $collectionIds = [];
        $updatedEmiIds = [];

        // Generate unique group ID for this multi-EMI payment
        $groupId = 'GRP_' . time() . '_' . $agentId . '_' . rand(1000, 9999);

        foreach ($emis as $emi) {
            if ($remainingAmount <= 0) {
                break;
            }

            $emiPendingAmount = $emi->pending_amount;
            $penaltyAmount = $emi->penalty_amount ?? 0;
            // pending_amount already includes penalty
            $totalEmiDue = $emiPendingAmount;

            $amountForThisEmi = min($remainingAmount, $totalEmiDue);

            // Create collection record
            $collection = EmiCollection::create([
                'emi_id' => $emi->id,
                'agent_id' => $agentId,
                'amount' => $amountForThisEmi,
                'payment_method' => $paymentMethod,
                'payment_type' => $paymentType,
                'status' => $paymentMethod === 'in_hand' ? 'in_progress' : 'completed',
                'proof_image_path' => $proofImagePath,
                'collected_at' => now(),
                'remarks' => "Auto-distributed from multi-EMI payment | Group: {$groupId}",
            ]);

            $collectionIds[] = $collection->id;

            // Update EMI status (only for non-in_hand or when approved)
            if ($paymentMethod !== 'in_hand') {
                $emi->paid_amount = ($emi->paid_amount ?? 0) + $amountForThisEmi;
                $emi->pending_amount = max(0, $emi->pending_amount - $amountForThisEmi);

                if ($emi->pending_amount <= 0) {
                    $emi->status = 'paid';
                    $emi->paid_date = now();
                } elseif ($emi->paid_amount > 0) {
                    $emi->status = 'partial';
                    $emi->is_partial_paid = true;
                    $emi->partial_paid_date = now();
                    $emi->partial_paid_amount = $emi->paid_amount;
                }

                $emi->save();
                $updatedEmiIds[] = $emi->id;
            }

            $remainingAmount -= $amountForThisEmi;
        }

        return [
            'collection_ids' => $collectionIds,
            'updated_emi_ids' => $updatedEmiIds,
            'distributed_amount' => $paymentAmount - $remainingAmount,
            'remaining_amount' => $remainingAmount,
            'group_id' => $groupId,
        ];
    }

    /**
     * Send OTP to client for in-hand collection verification
     * Validates all required fields before sending OTP
     * Works with specific EMI or all overdue EMIs for loan account
     */
    private function sendOtp(Request $request)
    {
        $agentId = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'emi_id' => 'nullable|exists:emis,id', // Made optional
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:overdue,partial',
            'payment_method' => 'required|in:in_hand',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->payment_type === 'partial' && floor($request->amount) != $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Partial payment amount must be a whole number.'
            ], 422);
        }

        // Determine if single EMI or multiple EMIs
        if ($request->emi_id) {
            // Single EMI mode (backward compatible)
            $emi = Emi::with(['loanAccount.client'])
                ->where('id', $request->emi_id)
                ->where('loan_account_id', $request->loan_account_id)
                ->firstOrFail();

            $projected = $this->getProjectedEmiData($emi);
            $emiPendingAmount = $projected['pending_amount'];
            $penaltyAmount = $emi->penalty_amount ?? 0;
            // pending_amount already includes penalty
            $totalDueWithPenalty = $emiPendingAmount;
            $clientPhone = $emi->loanAccount->client->client_phone;
            $emiIdForOtp = $emi->id;

        } else {
            // Multiple EMIs mode (new feature)
            $overdueEmis = $this->getOverdueEmisForLoanAccount($request->loan_account_id, $agentId);

            if ($overdueEmis->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No overdue EMIs found for this loan account'
                ], 404);
            }

            // Calculate total overdue across all EMIs
            $totalDueWithPenalty = $overdueEmis->sum(function ($emi) {
                return $emi->pending_amount;
            });

            // Get client phone from first EMI
            $firstEmi = $overdueEmis->first()->load('loanAccount.client');
            $clientPhone = $firstEmi->loanAccount->client->client_phone;

            // Use first EMI ID for OTP service (OTP is for client, not specific EMI)
            $emiIdForOtp = $firstEmi->id;
        }

        $loanAccount = isset($emi) ? $emi->loanAccount : (isset($firstEmi) ? $firstEmi->loanAccount : null);
        $isKandhuvatti = $loanAccount && ($loanAccount->loan_mode === 'interest_only');

        if ($isKandhuvatti) {
            // For Kandhuvatti loans, allow any amount up to pending interest + outstanding principal
            $pendingInterest = $totalDueWithPenalty;
            $maxAllowable = $pendingInterest + $loanAccount->outstanding_amount;
            
            if ($request->amount < ($pendingInterest - 0.01)) {
                // If it's less than pending interest, it is a partial interest payment
                $partialService = app(\App\Services\PartialPaymentConfigService::class);
                if ($request->emi_id && isset($emi)) {
                    if ($validationError = $partialService->validatePartialAmount($emi, (float) $request->amount, $emi->loanAccount)) {
                        return response()->json([
                            'success' => false,
                            'message' => $validationError,
                        ], 422);
                    }
                } else {
                    if (!$partialService->isActive()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Partial payments are disabled in loan configuration.',
                        ], 422);
                    }
                    $minimumPercentage = $partialService->getMinimumPercentage();
                    $minimumPartial = (int) ceil($totalDueWithPenalty * ($minimumPercentage / 100));

                    if ($request->amount < $minimumPartial) {
                        return response()->json([
                            'success' => false,
                            'message' => "Minimum partial payment is ₹{$minimumPartial} ({$minimumPercentage}% of total due)",
                        ], 422);
                    }
                }
            } elseif ($request->amount > ($maxAllowable + 0.01)) {
                return response()->json([
                    'success' => false,
                    'message' => "Maximum payable amount is ₹" . number_format($maxAllowable, 2) . " (Interest: ₹" . number_format($pendingInterest, 2) . " + Principal: ₹" . number_format($loanAccount->outstanding_amount, 2) . ")",
                ], 422);
            }
        } else {
            // Standard EMI validation
            if ($request->payment_type === 'overdue') {
                // For overdue, amount must equal total (EMI + penalty)
                if ($request->amount != $totalDueWithPenalty) {
                    return response()->json([
                        'success' => false,
                        'message' => "For overdue payment, amount must be ₹{$totalDueWithPenalty}",
                        'total_due' => (float) $totalDueWithPenalty,
                    ], 422);
                }
            } else {
                $partialService = app(\App\Services\PartialPaymentConfigService::class);

                if ($request->emi_id && isset($emi)) {
                    if ($validationError = $partialService->validatePartialAmount($emi, (float) $request->amount, $emi->loanAccount)) {
                        return response()->json([
                            'success' => false,
                            'message' => $validationError,
                        ], 422);
                    }
                } else {
                    if (!$partialService->isActive()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Partial payments are disabled in loan configuration.',
                        ], 422);
                    }

                    $minimumPercentage = $partialService->getMinimumPercentage();
                    $minimumPartial = (int) ceil($totalDueWithPenalty * ($minimumPercentage / 100));

                    if ($request->amount < $minimumPartial) {
                        return response()->json([
                            'success' => false,
                            'message' => "Minimum partial payment is ₹{$minimumPartial} ({$minimumPercentage}% of total due)",
                        ], 422);
                    }
                }
            }

            // Validate amount doesn't exceed total
            if ($request->amount > $totalDueWithPenalty) {
                return response()->json([
                    'success' => false,
                    'message' => "Maximum payable amount is ₹{$totalDueWithPenalty}"
                ], 422);
            }
        }

        $otpService = new AgentClientOtpService();
        $result = $otpService->sendOtp($clientPhone, $emiIdForOtp, $agentId);

        return response()->json($result);
    }

    /**
     * Verify OTP entered by client
     */
    private function verifyOtp(Request $request)
    {
        $agentId = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'emi_id' => 'nullable|exists:emis,id', // Made optional
            'otp' => 'required|string|size:6',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:in_hand',
            'payment_type' => 'required|in:overdue,partial',
            'proof_image' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->payment_type === 'partial' && floor($request->amount) != $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Partial payment amount must be a whole number.'
            ], 422);
        }

        // Determine EMI ID for OTP verification (use provided or first overdue EMI)
        $emiIdForOtp = $request->emi_id;
        if (!$emiIdForOtp) {
            $firstEmi = $this->getOverdueEmisForLoanAccount($request->loan_account_id, $agentId)->first();
            if (!$firstEmi) {
                return response()->json([
                    'success' => false,
                    'message' => 'No overdue EMIs found for this loan account'
                ], 404);
            }
            $emiIdForOtp = $firstEmi->id;
        }

        // Verify OTP first
        $otpService = new AgentClientOtpService();
        $otpResult = $otpService->verifyOtp($request->otp, $emiIdForOtp, $agentId);

        // If OTP verification failed, return error without saving
        if (!$otpResult['success']) {
            return response()->json($otpResult, 400);
        }

        // OTP verified successfully - now save the collection
        // Handle proof image upload
        $proofImagePath = null;
        if ($request->hasFile('proof_image')) {
            $proofImagePath = $request->file('proof_image')->store('collection_proofs', 'public');
        }

        if ($request->emi_id) {
            // Single EMI mode (backward compatible)
            $emi = Emi::with(['loanAccount.client'])
                ->where('id', $request->emi_id)
                ->where('loan_account_id', $request->loan_account_id)
                ->firstOrFail();

            $projected = $this->getProjectedEmiData($emi);
            $totalPending = $projected['pending_amount'];

            // Validate amount
            if ($request->amount > $totalPending) {
                return response()->json([
                    'success' => false,
                    'message' => "Maximum payable amount is ₹{$totalPending}"
                ], 422);
            }

            // Create collection record
            $collection = EmiCollection::create([
                'emi_id' => $request->emi_id,
                'agent_id' => $agentId,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_type' => $request->payment_type,
                'status' => 'in_progress', // Pending admin verification
                'proof_image_path' => $proofImagePath,
                'collected_at' => now(),
            ]);

            // Log activity
            AgentActivity::create([
                'emi_id' => $request->emi_id,
                'agent_id' => $agentId,
                'type' => 'payment',
                'description' => "Recorded in-hand collection of ₹{$request->amount} for EMI #{$request->emi_id}",
                'action_at' => now(),
            ]);

        } else {
            // Multiple EMIs mode (new feature)
            $overdueEmis = $this->getOverdueEmisForLoanAccount($request->loan_account_id, $agentId);

            // Use helper to distribute payment across EMIs
            $result = $this->distributePaymentAcrossEmis(
                $overdueEmis,
                $request->amount,
                $agentId,
                $request->payment_method,
                $request->payment_type,
                $proofImagePath
            );

            // Log activity for first EMI
            $firstEmi = $overdueEmis->first();
            AgentActivity::create([
                'emi_id' => $firstEmi->id,
                'agent_id' => $agentId,
                'type' => 'payment',
                'description' => "Recorded in-hand collection of ₹{$request->amount} for " . count($result['collection_ids']) . " EMIs (pending admin verification)",
                'action_at' => now(),
            ]);

            // Return first collection ID for response
            $collection = (object) ['id' => $result['collection_ids'][0]];
        }

        // Get projected status for the EMI (first one if multiple)
        $projected = $this->getProjectedEmiData(Emi::find($emiIdForOtp));

        return response()->json([
            'success' => true,
            'message' => 'OTP verified and collection recorded successfully. Pending admin verification.',
            'collection_id' => $collection->id,
            'status' => 'in_progress',
            'emi_remaining_amount' => (float) $projected['pending_amount'],
            'emi_projected_status' => $projected['status'],
            'is_partial' => $projected['status'] === 'partial',
            'note' => 'EMI will be updated after admin verification'
        ]);
    }

    /**
     * Initiate direct payment via Razorpay
     * Supports both single EMI and multi-EMI payments
     */
    private function initiateDirect(Request $request)
    {
        $agentId = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'emi_id' => 'nullable|exists:emis,id', // Optional for multi-EMI
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:overdue,partial',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->payment_type === 'partial' && floor($request->amount) != $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Partial payment amount must be a whole number.'
            ], 422);
        }

        // Generate unique group ID for this payment
        $groupId = 'GRP_' . time() . '_' . $agentId . '_' . rand(1000, 9999);

        // Determine if single EMI or multiple EMIs
        if ($request->emi_id) {
            // Single EMI mode
            $emi = Emi::with(['loanAccount.client'])
                ->where('id', $request->emi_id)
                ->where('loan_account_id', $request->loan_account_id)
                ->firstOrFail();

            $emis = collect([$emi]);
            $loanAccount = $emi->loanAccount;

        } else {
            // Multiple EMIs mode
            $overdueEmis = $this->getOverdueEmisForLoanAccount($request->loan_account_id, $agentId);

            if ($overdueEmis->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No overdue EMIs found for this loan account'
                ], 404);
            }

            $emis = $overdueEmis;
            $loanAccount = $overdueEmis->first()->loanAccount;
        }

        // Calculate total amount
        $totalDueWithPenalty = $emis->sum(function ($emi) {
            return $emi->pending_amount;
        });

        // Validate amount
        if ($request->amount > $totalDueWithPenalty) {
            return response()->json([
                'success' => false,
                'message' => "Maximum payable amount is ₹{$totalDueWithPenalty}"
            ], 422);
        }

        // Create collection records for all EMIs
        $collectionIds = [];
        $remainingAmount = $request->amount;

        foreach ($emis as $emi) {
            if ($remainingAmount <= 0)
                break;

            $emiDue = $emi->pending_amount;
            $amountForThisEmi = min($remainingAmount, $emiDue);

            $collection = EmiCollection::create([
                'emi_id' => $emi->id,
                'agent_id' => $agentId,
                'amount' => $amountForThisEmi,
                'payment_method' => 'direct',
                'payment_type' => $request->payment_type,
                'status' => 'in_progress',
                'collected_at' => now(),
                'remarks' => $emis->count() > 1
                    ? "Multi-EMI direct payment | Group: {$groupId}"
                    : 'Direct payment initiated via App',
            ]);

            $collectionIds[] = $collection->id;
            $remainingAmount -= $amountForThisEmi;
        }

        $amountPaise = (int) round($request->amount * 100);

        try {
            $credentials = $this->getRazorpayCredentials();

            $api = new \Razorpay\Api\Api(
                $credentials['key_id'],
                $credentials['key_secret']
            );

            $order = $api->order->create([
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => 'grp_' . $groupId . '_' . time(),
                'notes' => [
                    'loan_account_id' => $loanAccount->id,
                    'agent_id' => $agentId,
                    'collection_ids' => implode(',', $collectionIds),
                    'group_id' => $groupId,
                    'emi_count' => $emis->count(),
                    'payment_type' => $request->payment_type,
                ]
            ]);

            // Update EMIs with Razorpay Order ID in payment_reference
            foreach ($emis as $emi) {
                $emi->payment_reference = $order['id'];
                $emi->save();
            }

            Log::info('Razorpay order created successfully', [
                'agent_id' => $agentId,
                'collection_ids' => $collectionIds,
                'order_id' => $order['id'],
                'group_id' => $groupId,
                'emi_count' => $emis->count(),
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order['id'],
                'amount' => $request->amount,
                'currency' => 'INR',
                'razorpay_key' => $credentials['key_id'],
                'client_name' => $loanAccount->client->client_name,
                'client_phone' => $loanAccount->client->client_phone,
                'client_email' => $loanAccount->client->client_email,
                'collection_ids' => $collectionIds,
                'group_id' => $groupId,
                'emi_count' => $emis->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to initiate direct payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Delete collections if order creation fails
            EmiCollection::whereIn('id', $collectionIds)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST: Resend OTP for collection verification
     * Allows agent to request a new OTP if the previous one expired or wasn't received
     * Supports both single EMI and multi-EMI payments
     */
    public function resendOtp(Request $request)
    {
        $agentId = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'emi_id' => 'nullable|exists:emis,id', // Optional for multi-EMI
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Determine if single EMI or multiple EMIs
        if ($request->emi_id) {
            // Single EMI mode
            $emi = Emi::with(['loanAccount.client'])
                ->where('id', $request->emi_id)
                ->where('loan_account_id', $request->loan_account_id)
                ->firstOrFail();

            $clientPhone = $emi->loanAccount->client->client_phone;
            $emiIdForOtp = $emi->id;

        } else {
            // Multiple EMIs mode - get first overdue EMI
            $overdueEmis = $this->getOverdueEmisForLoanAccount($request->loan_account_id, $agentId);

            if ($overdueEmis->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No overdue EMIs found for this loan account'
                ], 404);
            }

            // Get client phone from first EMI
            $firstEmi = $overdueEmis->first()->load('loanAccount.client');
            $clientPhone = $firstEmi->loanAccount->client->client_phone;

            // Use first EMI ID for OTP service (OTP is for client, not specific EMI)
            $emiIdForOtp = $firstEmi->id;
        }

        if (!$clientPhone) {
            return response()->json([
                'success' => false,
                'message' => 'Client phone number not found'
            ], 422);
        }

        // Send OTP using the same service
        $otpService = new AgentClientOtpService();
        $result = $otpService->sendOtp($clientPhone, $emiIdForOtp, $agentId);

        return response()->json($result);
    }

    /**
     * POST: Submit collection (final step)
     * Handles all payment methods with correct status flow
     */
    public function store(Request $request)
    {
        $agentId = Auth::user()->id;

        Log::info('Collection submission started', [
            'agent_id' => $agentId,
            'emi_id' => $request->emi_id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_type' => $request->payment_type,
        ]);

        $validator = Validator::make($request->all(), [
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'emi_id' => 'required|exists:emis,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:direct,in_hand,payment_link',
            'payment_type' => 'required|in:overdue,partial',
            'remarks' => 'nullable|string|max:1000',
            'otp_verified' => 'nullable|boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Conditional validation for proof_image (required for in_hand only)
        $validator->sometimes('proof_image', 'required|image|max:5120', function ($input) {
            return $input->payment_method === 'in_hand';
        });

        // Conditional validation for OTP verification (required for in_hand)
        $validator->sometimes('otp_verified', 'required|accepted', function ($input) {
            return $input->payment_method === 'in_hand';
        });

        if ($validator->fails()) {
            Log::warning('Collection submission validation failed', [
                'agent_id' => $agentId,
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->payment_type === 'partial' && floor($request->amount) != $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Partial payment amount must be a whole number.'
            ], 422);
        }

        $emi = Emi::with('loanAccount.client')
            ->where('id', $request->emi_id)
            ->where('loan_account_id', $request->loan_account_id)
            ->firstOrFail();

        // Validate agent has access
        $assignment = EmiAgentAssignment::where('emi_id', $request->emi_id)
            ->where('agent_id', $agentId)
            ->whereIn('status', ['assigned', 'visited'])
            ->firstOrFail();

        $isKandhuvatti = ($emi->loanAccount->loan_mode ?? 'emi') === 'interest_only';
        $loanAccount = $emi->loanAccount;

        if ($isKandhuvatti) {
            $pendingInterest = app(\App\Services\PartialPaymentConfigService::class)
                ->getOutstandingDueAmount($emi, $loanAccount);
            $maxAllowable = $pendingInterest + $loanAccount->outstanding_amount;
            
            if ($request->amount < ($pendingInterest - 0.01)) {
                $partialService = app(\App\Services\PartialPaymentConfigService::class);
                if ($validationError = $partialService->validatePartialAmount($emi, (float) $request->amount, $loanAccount)) {
                    return response()->json([
                        'success' => false,
                        'message' => $validationError,
                    ], 422);
                }
            } elseif ($request->amount > ($maxAllowable + 0.01)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum payable amount is ₹' . number_format($maxAllowable, 2),
                ], 422);
            }
        } else {
            if ($request->payment_type === 'partial') {
                $partialService = app(\App\Services\PartialPaymentConfigService::class);
                if ($validationError = $partialService->validatePartialAmount($emi, (float) $request->amount, $emi->loanAccount)) {
                    return response()->json([
                        'success' => false,
                        'message' => $validationError,
                    ], 422);
                }
            } else {
                $outstanding = app(\App\Services\PartialPaymentConfigService::class)
                    ->getOutstandingDueAmount($emi, $emi->loanAccount);

                if ($request->amount > ($outstanding + 0.01)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Maximum payable amount is ₹' . number_format($outstanding, 0),
                    ], 422);
                }
            }
        }

        $collection = null;

        DB::transaction(function () use ($request, $agentId, $emi, $assignment, &$collection) {

            // Upload proof image (for in_hand only)
            $imagePath = null;
            if ($request->hasFile('proof_image')) {
                $imagePath = $request->file('proof_image')
                    ->store('emi_collections', 'public');
            }

            // Determine collection status based on payment method
            $collectionStatus = ($request->payment_method === 'direct') ? 'completed' : 'in_progress';

            // Create collection record
            $collection = EmiCollection::create([
                'emi_id' => $emi->id,
                'agent_id' => $agentId,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_type' => $request->payment_type,
                'status' => $collectionStatus,
                'proof_image_path' => $imagePath,
                'collected_at' => now(),
                'remarks' => $request->remarks,
            ]);

            // Update EMI based on payment method
            if ($request->payment_method === 'in_hand') {
                // In-hand: Don't update EMI amounts yet, wait for admin verification
                // Just mark assignment as visited
                $assignment->update([
                    'status' => 'visited',
                    'remarks' => trim(($assignment->remarks ?? '') . ' [In-hand collection pending verification]')
                ]);

            } elseif ($request->payment_method === 'payment_link') {
                // Payment Link: Don't update EMI yet, wait for webhook confirmation
                $assignment->update([
                    'status' => 'visited',
                    'remarks' => trim(($assignment->remarks ?? '') . ' [Payment link sent, awaiting customer payment]')
                ]);

            } else {
                // Direct: Update EMI immediately (will be confirmed by webhook)
                $emi->paid_amount += $request->amount;
                $emi->pending_amount = $emi->total_due - $emi->paid_amount;

                if ($emi->pending_amount <= 1) { // 1 rupee tolerance
                    $emi->status = 'paid';
                    $emi->paid_date = now();
                    $emi->pending_amount = 0;

                    // Close the assignment
                    $assignment->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'remarks' => trim(($assignment->remarks ?? '') . ' [Full Payment Received]')
                    ]);

                } else {
                    $emi->status = 'partial';
                    $emi->is_partial_paid = true;
                    $emi->partial_paid_amount = $request->amount;
                    $emi->partial_paid_date = now();
                }

                $emi->save();
            }
        });

        // Automatically stop any active visit for this EMI
        \App\Http\Controllers\Agent\AgentVisitControllerApi::autoStopVisit($agentId, $request->emi_id, $request->latitude, $request->longitude);

        // Response based on payment method
        $responseMessage = 'Collection recorded successfully';
        $projected = $this->getProjectedEmiData($emi);

        $responseData = [
            'success' => true,
            'message' => $request->payment_method === 'payment_link' ? 'Payment link sent successfully' : 'Collection recorded successfully',
            'data' => [
                'collection_id' => $collection->id,
                'status' => $collection->status,
                'amount' => (float) $collection->amount,
                'emi_remaining_amount' => (float) $projected['pending_amount'],
                'emi_projected_status' => $projected['status'],
                'is_partial' => $projected['status'] === 'partial',
                'payment_method' => $collection->payment_method,
                'payment_type' => $collection->payment_type,
                'collected_at' => $collection->collected_at->toDateTimeString(),
                'remarks' => $collection->remarks,
                'proof_image_url' => $collection->proof_image_path
                    ? Storage::url($collection->proof_image_path)
                    : null,
            ],
        ];

        Log::info('Collection recorded successfully', [
            'agent_id' => $agentId,
            'collection_id' => $collection->id,
            'emi_id' => $collection->emi_id,
            'amount' => $collection->amount,
            'payment_method' => $collection->payment_method,
            'emi_status' => $emi->fresh()->status,
        ]);

        // Generate Razorpay payment link for payment_link method
        $paymentLinkUrl = null;

        // DEBUG LOG: Check if we are entering the payment link block
        Log::info('Checking payment method for link generation', [
            'method' => $request->payment_method,
            'is_link' => $request->payment_method === 'payment_link'
        ]);

        if ($request->payment_method === 'payment_link') {
            Log::info('Entering payment link generation block');
            try {
                $amountPaise = (int) round($request->amount * 100);
                $maxAmountPaise = 5000000; // ₹50,000
                $maxAmountInr = 50000;

                // VALIDATE AMOUNT BEFORE API CALL
                if ($amountPaise > $maxAmountPaise) {
                    Log::warning('Payment link amount exceeds maximum', [
                        'amount_inr' => $request->amount,
                        'max_allowed' => $maxAmountInr,
                        'collection_id' => $collection->id,
                    ]);
                    throw new \Exception('Payment amount ₹' . number_format($request->amount, 2) . ' exceeds Razorpay maximum limit of ₹' . number_format($maxAmountInr, 2) . '. Maximum allowed is ₹50,000 per transaction.');
                }

                $credentials = $this->getRazorpayCredentials();
                Log::info('Fetched Razorpay credentials', ['key_id' => $credentials['key_id']]);

                $api = new \Razorpay\Api\Api(
                    $credentials['key_id'],
                    $credentials['key_secret']
                );

                Log::info('Creating Razorpay payment link', [
                    'amount' => $amountPaise,
                    'reference_id' => "emi_{$emi->id}_col_{$collection->id}"
                ]);

                $paymentLink = $api->paymentLink->create([
                    'amount' => $amountPaise,
                    'currency' => 'INR',
                    'accept_partial' => false,
                    'description' => "EMI Payment - Loan Account #{$emi->loanAccount->loan_account_number}",
                    'customer' => [
                        'name' => $emi->loanAccount->client->client_name,
                        'contact' => $emi->loanAccount->client->client_phone,
                        'email' => $emi->loanAccount->client->client_email ?? '',
                    ],
                    'notify' => [
                        'sms' => true,
                        'email' => false,
                    ],
                    'reminder_enable' => true,
                    'notes' => [
                        'emi_id' => $emi->id,
                        'agent_id' => $agentId,
                        'collection_id' => $collection->id,
                        'loan_account_id' => $emi->loan_account_id,
                    ],
                    'callback_url' => url('/api/agent/payments/callback'),
                    'callback_method' => 'get',
                ]);

                $paymentLinkUrl = $paymentLink['short_url'];

                // Update collection with payment link
                $collection->update([
                    'remarks' => trim(($collection->remarks ?? '') . " | Payment Link: {$paymentLinkUrl}")
                ]);

                Log::info('Razorpay payment link created successfully', [
                    'agent_id' => $agentId,
                    'collection_id' => $collection->id,
                    'payment_link' => $paymentLinkUrl,
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to create payment link', [
                    'agent_id' => $agentId,
                    'collection_id' => $collection->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't fail the entire request, just log the error
            }
        }

        if ($request->payment_method === 'in_hand') {
            $responseData['message'] = 'Collection submitted. Pending admin verification.';
            $responseData['status_info'] = 'Your collection is under review. It will be marked as recovered after admin verification.';
        } elseif ($request->payment_method === 'payment_link') {
            $responseData['message'] = 'Payment link generated successfully';
            $responseData['status_info'] = 'Share this link with the customer to complete payment.';
            $responseData['payment_link'] = $paymentLinkUrl;
            // Additional debug info
            if (!$paymentLinkUrl) {
                Log::warning('Payment link URL is null in response', ['collection_id' => $collection->id]);
            }
        } else {
            $responseData['message'] = 'Payment initiated successfully';
            $responseData['status_info'] = 'Payment will be confirmed automatically after successful transaction.';
        }

        return response()->json($responseData, 201);
    }

    public function show($loanAccountId)
    {
        $agentId = Auth::user()->id;

        // Verify agent has access to this loan account
        $hasAccess = EmiAgentAssignment::where('agent_id', $agentId)
            ->whereHas('emi', function ($q) use ($loanAccountId) {
                $q->where('loan_account_id', $loanAccountId);
            })
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this loan account'
            ], 403);
        }

        // Get all overdue EMIs for this loan account assigned to the agent
        $overdueEmis = Emi::where('loan_account_id', $loanAccountId)
            ->whereIn('status', ['overdue', 'pending'])
            ->whereHas('assignments', function ($q) use ($agentId) {
                $q->where('agent_id', $agentId);
            })
            ->with('loanAccount.client')
            ->get();

        if ($overdueEmis->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No overdue EMIs found for this loan account'
            ], 404);
        }

        // Calculate totals
        $totalEmiAmount = $overdueEmis->sum('total_amount');
        $totalPenalty = $overdueEmis->sum('penalty_amount');
        $totalDue = $overdueEmis->sum('total_due');
        $totalPending = $overdueEmis->sum('pending_amount');

        // Get loan account and client details from first EMI
        $firstEmi = $overdueEmis->first();
        $loanAccount = $firstEmi->loanAccount;
        $client = $loanAccount->client;

        // Calculate paid vs total EMIs
        $totalEmis = Emi::where('loan_account_id', $loanAccountId)->count();
        $paidEmis = Emi::where('loan_account_id', $loanAccountId)
            ->where('status', 'paid')
            ->count();

        return response()->json([
            'success' => true,
            'loan_account_id' => $loanAccountId,
            'loan_account_number' => $loanAccount->account_number,
            'client' => [
                'name' => $client->client_name,
                'phone' => $client->client_phone,
                'profile_image_url' => $client->profile_image_url,
                'location' => $client->city . ' - ' . $client->state,
            ],
            'loan' => [
                'type' => $loanAccount->loan_code,
                'interest_rate' => $loanAccount->interest_rate,
                'tenure' => $loanAccount->tenure,
                'disbursed_on' => optional($loanAccount->disbursed_at)->format('d-m-Y'),
                'total_amount' => (float) $loanAccount->loan_amount,
            ],
            'emi_summary' => [
                'total_emis' => $totalEmis,
                'paid_emis' => $paidEmis,
                'overdue_emis' => $overdueEmis->count(),
                'emi_paid_info' => $paidEmis . '/' . $totalEmis,
            ],
            'overdue_totals' => [
                'total_emi_amount' => (float) $totalEmiAmount,
                'total_penalty' => (float) $totalPenalty,
                'total_due' => (float) $totalDue,
                'total_pending' => (float) $totalPending,
            ],
            'overdue_emis' => $overdueEmis->map(function ($emi) {
                $projected = $this->getProjectedEmiData($emi);
                return [
                    'emi_id' => $emi->id,
                    'instalment_number' => $emi->instalment_number,
                    'due_date' => optional($emi->due_date)->format('d-m-Y'),
                    'emi_amount' => (float) $emi->total_amount,
                    'penalty' => (float) $emi->penalty_amount,
                    'total_due' => (float) $emi->total_due,
                    'pending_amount' => (float) $projected['pending_amount'],
                    'dpd_days' => $emi->dpd_days,
                    'status' => $projected['status'],
                ];
            }),
        ]);
    }

    public function showEmi(Request $request, $emiId)
    {
        $agentId = Auth::user()->id;

        $emi = Emi::with(['loanAccount.client.location'])
            ->where('id', $emiId)
            ->whereHas('assignments', function ($q) use ($agentId) {
                $q->where('agent_id', $agentId);
            })
            ->firstOrFail();

        $loanAccount = $emi->loanAccount;
        $client = $loanAccount->client;

        // Calculate paid vs total EMIs
        $totalEmis = Emi::where('loan_account_id', $loanAccount->id)->count();
        $paidEmis = Emi::where('loan_account_id', $loanAccount->id)
            ->where('status', 'paid')
            ->count();

        $projected = $this->getProjectedEmiData($emi);

        return response()->json([
            'success' => true,
            'data' => [
                'emi_id' => $emi->id,
                'loan_account_number' => $loanAccount->account_number,
                'client_name' => $client->client_name,
                'dpd_days' => $emi->dpd_days,
                'emi_paid_count' => "{$paidEmis}/{$totalEmis}",
                'due_amount' => (float) $emi->total_amount,
                'bounce_charge' => 0,
                'late_charge' => (float) $emi->penalty_amount,
                'interest_after_due_date' => 0,
                'total_overdue_amount' => (float) $projected['pending_amount'],
            ]
        ]);
    }

    public function customerDetails($loanAccountId)
    {
        $agentId = Auth::user()->id;

        // Verify agent has access to this loan account
        $hasAccess = EmiAgentAssignment::where('agent_id', $agentId)
            ->whereHas('emi', function ($q) use ($loanAccountId) {
                $q->where('loan_account_id', $loanAccountId);
            })
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this loan account'
            ], 403);
        }

        // Load loan account with relationships
        $loanAccount = LoanAccount::with([
            'loanApplication.product.loanType',
            'client.location',
            'client.user.userDevice',
        ])->findOrFail($loanAccountId);

        $client = $loanAccount->client;
        $user = $client->user;
        $device = $user?->userDevice()->latest()->first();

        // Get all EMIs for this loan account assigned to this agent
        $agentEmis = Emi::whereHas('assignments', function ($q) use ($agentId) {
            $q->where('agent_id', $agentId);
        })
            ->where('loan_account_id', $loanAccountId)
            ->orderBy('due_date')
            ->get();

        // Get overall EMI counts for the entire loan account (not just agent-assigned)
        $totalEmisInLoan = Emi::where('loan_account_id', $loanAccountId)->count();
        $totalPaidEmisInLoan = Emi::where('loan_account_id', $loanAccountId)
            ->where('status', 'paid')
            ->count();

        // Calculate aggregated loan-level data
        $totalEmisPaid = $agentEmis->where('status', 'paid')->count();
        // Get overdue EMIs - use pending_amount (system-tracked total including penalties)
        // Calculate total overdue (projected)
        $totalOverdueAmount = $agentEmis->whereIn('status', ['overdue', 'pending', 'partial'])->reduce(function ($carry, $emi) {
            $projected = $this->getProjectedEmiData($emi);
            return $carry + $projected['pending_amount'];
        }, 0);

        $nextDueDate = $agentEmis->whereIn('status', ['overdue', 'pending', 'partial'])->min('due_date')?->format('d-m-Y') ?? 'No Pending EMI';
        $maxDpdDays = $agentEmis->whereIn('status', ['overdue', 'pending', 'partial'])->max('dpd_days') ?? 0;
        $totalAmountPaid = $agentEmis->sum(function ($emi) {
            $projected = $this->getProjectedEmiData($emi);
            return (float) $emi->paid_amount + $projected['in_progress_amount'];
        });

        // Payment history
        $paymentHistory = $agentEmis->map(function ($e) {
            $projected = $this->getProjectedEmiData($e);
            return [
                'emi_id' => $e->id,
                'amount' => (float) $e->total_due, // System total due (total_amount + previous_balance + penalty)
                'due_date' => $e->due_date->format('d-m-Y'),
                'status' => $projected['status'] === 'paid' ? 'Paid' : ucfirst($projected['status']),
                'paid_amount' => (float) ($e->paid_amount + $projected['in_progress_amount']),
                'pending_amount' => (float) $projected['pending_amount'],
                'payment_method' => $e->collections->first()?->payment_method ? ucfirst(str_replace('_', ' ', $e->collections->first()->payment_method)) : null,
            ];
        });

        // Get last actions (Followups + Visits + Collections)
        $emiIds = $agentEmis->pluck('id');

        // 1. Followups
        $followups = \App\Models\EmiFollowup::whereIn('emi_id', $emiIds)
            ->where('agent_id', $agentId)
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->toBase()
            ->map(fn($f) => [
                'type' => ucwords(str_replace('_', ' ', $f->status)),
                'description' => $f->remarks,
                'date' => $f->created_at, // Keep as object for sorting
                'formatted_date' => $f->created_at->format('d-m-Y h:i A'),
                'icon' => 'phone', // UI icon hint
                'highlight' => str_contains($f->status, 'promise') || str_contains($f->status, 'paid'),
            ]);

        // 2. Visits
        $visits = \App\Models\AgentVisitLog::whereIn('emi_id', $emiIds)
            ->where('agent_id', $agentId)
            ->latest('started_at')
            ->limit(10)
            ->get()
            ->toBase()
            ->map(fn($v) => [
                'type' => 'Agent Visit',
                'description' => $v->ended_at
                    ? "Visit completed (" . $v->started_at->diffInMinutes($v->ended_at) . " mins)"
                    : "Visit started at " . $v->started_at->format('h:i A'),
                'date' => $v->started_at,
                'formatted_date' => $v->started_at->format('d-m-Y h:i A'),
                'icon' => 'location',
                'highlight' => false,
            ]);

        // 3. Collections
        $collections = EmiCollection::whereIn('emi_id', $emiIds)
            ->where('agent_id', $agentId)
            ->latest('collected_at')
            ->limit(10)
            ->get()
            ->toBase()
            ->map(fn($c) => [
                'type' => 'Payment Collected',
                'description' => "Received ₹{$c->amount} via " . ucwords(str_replace('_', ' ', $c->payment_method)),
                'date' => $c->collected_at,
                'formatted_date' => $c->collected_at->format('d-m-Y h:i A'),
                'icon' => 'currency-rupee',
                'highlight' => true,
            ]);

        // Merge, Sort, and Slice
        $lastActions = $followups->merge($visits)->merge($collections)
            ->sortByDesc('date')
            ->take(3)
            ->values()
            ->map(function ($action) {
                // Flatten date for response
                $action['date'] = $action['formatted_date'];
                unset($action['formatted_date']);
                return $action;
            });

        // Get notes for this loan account
        $notes = AgentActivity::whereIn('emi_id', $emiIds)
            ->where('agent_id', $agentId)
            ->where('type', 'note')
            ->latest()
            ->value('description');

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $client->id,
                'name' => $client->client_name,
                'phone' => $client->client_phone,
                'email' => $client->client_email,
                'profile_image_url' => $client->profile_image_url,
                'longitude' => $device?->longitude,
                'latitude' => $device?->latitude,
                'emi_paid_count' => "{$totalPaidEmisInLoan}/{$totalEmisInLoan}",
                'emi_progress_text' => "{$totalPaidEmisInLoan}/{$totalEmisInLoan} EMI Paid",
            ],

            'loan' => [
                'loan_account_id' => $loanAccountId,
                'loan_account_number' => $loanAccount->account_number,
                'loan_type' => optional($loanAccount->loanApplication?->product?->loanType)->name,
                'interest_rate' => $loanAccount->interest_rate . '% p.a',
                'tenure' => $loanAccount->tenure . ' months',
                'disbursed_on' => optional($loanAccount->disbursed_at)->format('d-m-Y'),
                'total_loan_amount' => (float) $loanAccount->loan_amount,
                'total_emi_paid' => (float) $totalAmountPaid,
                'total_overdue_amount' => (float) $totalOverdueAmount,
                'next_due_date' => $nextDueDate,
                'dpd_days' => $maxDpdDays,
                'risk_badge' => $maxDpdDays > 15 || $agentEmis->whereIn('status', ['overdue', 'pending', 'partial'])->count() >= 2 ? 'High Risk' : ($maxDpdDays > 3 ? 'At Risk' : 'Normal'),
            ],

            'contact' => [
                'phone' => $client->client_phone,
                'email' => $client->client_email,
            ],

            'address' => [
                'full_address' => $client->address . ', ' . ($client->location ? $client->location->name : ''),
                'location_name' => optional($client->location)->name,
                'coordinates' => [
                    'lat' => $client->latitude ?? $device?->latitude,
                    'lng' => $client->longitude ?? $device?->longitude,
                ]
            ],

            'last_actions' => $lastActions,

            'notes' => $notes,

            'payment_history' => $paymentHistory,
        ]);
    }

    /**
     * GET: Overdue Amount Breakdown
     * Shows detailed per-EMI breakdown for modal display
     * 
     * @param int $loanAccountId
     * @return JsonResponse
     */
    public function getOverdueBreakdown($loanAccountId)
    {
        $agentId = Auth::user()->id;

        // Verify agent has access to this loan account
        $hasAccess = EmiAgentAssignment::where('agent_id', $agentId)
            ->whereHas('emi', function ($q) use ($loanAccountId) {
                $q->where('loan_account_id', $loanAccountId);
            })
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this loan account'
            ], 403);
        }

        // Get all EMIs assigned to this agent for the loan account
        // Only include pending/overdue EMIs (not paid)
        $agentEmis = Emi::whereHas('assignments', function ($q) use ($agentId) {
            $q->where('agent_id', $agentId);
        })
            ->where('loan_account_id', $loanAccountId)
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->orderBy('due_date')
            ->get();

        // Initialize grand total
        $grandTotal = [
            'due_amount' => 0,
            'interest_after_due' => 0,
            'late_charge' => 0,
            'gst' => 0,
            'previous_pending' => 0,
            'total_payable' => 0,
        ];

        // Build breakdown for each EMI
        $emiBreakdowns = $agentEmis->map(function ($emi) use (&$grandTotal) {
            $projected = $this->getProjectedEmiData($emi);
            // Source values from table columns
            $totalPayable = (float) $projected['pending_amount']; // The actual total to pay (projected)
            $lateCharge = (float) ($emi->penalty_amount ?? 0); // Mapped from penalty_amount as requested
            $previousPending = (float) ($emi->previous_balance ?? 0);

            // To ensure "total is total of showing calculation", we derive dueAmount
            // dueAmount + lateCharge + previousPending = totalPayable
            $dueAmount = max(0, $totalPayable - $lateCharge - $previousPending);

            // Add to grand total
            $grandTotal['due_amount'] += $dueAmount;
            $grandTotal['late_charge'] += $lateCharge;
            $grandTotal['previous_pending'] += $previousPending;
            $grandTotal['total_payable'] += $totalPayable;

            return [
                'emi_id' => $emi->id,
                'emi_number' => $emi->instalment_number,
                'due_date' => $emi->due_date->format('d-m-Y'),
                'dpd_days' => $emi->dpd_days,
                'status' => match ($projected['status']) {
                    'paid' => 'Recovered',
                    'partial' => 'Partially Recovered',
                    'overdue' => 'Overdue',
                    default => ucfirst($projected['status'])
                },
                'breakdown' => [
                    'due_amount' => round($dueAmount, 2),
                    'interest_after_due' => 0,
                    'late_charge' => round($lateCharge, 2),
                    'gst' => 0,
                    'previous_pending' => round($previousPending, 2),
                    'total_payable' => round($totalPayable, 2),
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'loan_account_id' => (int) $loanAccountId,
            'emis' => $emiBreakdowns,
            'grand_total' => [
                'due_amount' => round($grandTotal['due_amount'], 2),
                'interest_after_due' => 0,
                'late_charge' => round($grandTotal['late_charge'], 2),
                'gst' => 0,
                'previous_pending' => round($grandTotal['previous_pending'], 2),
                'total_payable' => round($grandTotal['total_payable'], 2),
            ]
        ]);
    }

    /**
     * Get complete action history for a customer (loan account)
     * Shows all activities: followups, visits, and collections in chronological order
     */
    public function customerActionsHistory($loanAccountId)
    {
        $agentId = Auth::user()->id;

        // Verify agent has access to this loan account
        $hasAccess = EmiAgentAssignment::where('agent_id', $agentId)
            ->whereHas('emi', function ($q) use ($loanAccountId) {
                $q->where('loan_account_id', $loanAccountId);
            })
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this loan account'
            ], 403);
        }

        // Get all EMIs for this loan account assigned to this agent
        $emiIds = Emi::whereHas('assignments', function ($q) use ($agentId) {
            $q->where('agent_id', $agentId);
        })
            ->where('loan_account_id', $loanAccountId)
            ->pluck('id');

        $actions = collect();

        // 1. Get all followups (status updates)
        $followups = \App\Models\EmiFollowup::whereIn('emi_id', $emiIds)
            ->where('agent_id', $agentId)
            ->with('emi')
            ->get()
            ->map(function ($f) {
                $statusConfig = config('followup.status_options', []);
                $statusLabel = $statusConfig[$f->status] ?? ucwords(str_replace('_', ' ', $f->status));

                // Determine icon based on status
                $icon = match ($f->status) {
                    'appointment_to_visit' => 'location',
                    'call_back' => 'phone',
                    'promise_to_pay' => 'calendar',
                    'unable_to_reach' => 'phone_missed',
                    'visit_rescheduled' => 'calendar_clock',
                    'request_extension' => 'clock',
                    'financial_difficulty' => 'alert',
                    'other_reasons' => 'info',
                    default => 'note',
                };

                return [
                    'id' => 'followup_' . $f->id,
                    'activity_type' => 'followup',
                    'icon' => $icon,
                    'title' => $statusLabel,
                    'description' => $f->remarks,
                    'date' => $f->created_at->format('d-m-Y'),
                    'time' => $f->created_at->format('h:i A'),
                    'datetime' => $f->created_at->format('d-m-Y h:i A'),
                    'timestamp' => $f->created_at->toIso8601String(),
                    'followup_scheduled_at' => $f->followup_at ? $f->followup_at->format('d-m-Y h:i A') : null,
                    'emi_id' => $f->emi_id,
                    'emi_amount' => (float) $f->emi->total_amount,
                    'status_key' => $f->status,
                ];
            });

        $actions = $actions->merge($followups);

        // 2. Get all visits
        $visits = \App\Models\AgentVisitLog::whereIn('emi_id', $emiIds)
            ->where('agent_id', $agentId)
            ->with('emi')
            ->get()
            ->map(function ($v) {
                $duration = null;
                if ($v->started_at && $v->ended_at) {
                    $diff = $v->started_at->diff($v->ended_at);
                    $duration = sprintf('%dh %dm', $diff->h, $diff->i);
                }

                $status = 'Field Visit';
                $description = 'Visited the registered address';

                if ($v->ended_at) {
                    $description .= '. Visit completed';
                    if ($duration) {
                        $description .= " (Duration: {$duration})";
                    }
                } else {
                    $description = 'Visit in progress';
                }

                if ($v->notes) {
                    $description .= '. ' . $v->notes;
                }

                return [
                    'id' => 'visit_' . $v->id,
                    'activity_type' => 'visit',
                    'icon' => 'location',
                    'title' => $status,
                    'description' => $description,
                    'date' => $v->created_at->format('d-m-Y'),
                    'time' => $v->created_at->format('h:i A'),
                    'datetime' => $v->created_at->format('d-m-Y h:i A'),
                    'timestamp' => $v->created_at->toIso8601String(),
                    'visit_duration' => $duration,
                    'started_at' => $v->started_at ? $v->started_at->format('h:i A') : null,
                    'ended_at' => $v->ended_at ? $v->ended_at->format('h:i A') : null,
                    'emi_id' => $v->emi_id,
                ];
            });

        $actions = $actions->merge($visits);

        // 3. Get all collections
        $collections = \App\Models\EmiCollection::whereIn('emi_id', $emiIds)
            ->where('agent_id', $agentId)
            ->with('emi')
            ->get()
            ->map(function ($c) {
                $methodLabel = match ($c->payment_method) {
                    'in_hand' => 'Cash Collection',
                    'direct' => 'Online Payment',
                    'payment_link' => 'Payment Link',
                    default => 'Payment Collected',
                };

                $statusLabel = match ($c->status) {
                    'completed' => 'Completed',
                    'in_progress' => 'Pending Verification',
                    'verified' => 'Verified',
                    'rejected' => 'Rejected',
                    default => ucfirst($c->status),
                };

                $description = "₹" . number_format($c->amount, 2) . " collected via {$c->payment_method}";
                $description .= ". Status: {$statusLabel}";

                if ($c->notes) {
                    $description .= '. ' . $c->notes;
                }

                return [
                    'id' => 'collection_' . $c->id,
                    'activity_type' => 'collection',
                    'icon' => 'payment',
                    'title' => $methodLabel,
                    'description' => $description,
                    'date' => $c->created_at->format('d-m-Y'),
                    'time' => $c->created_at->format('h:i A'),
                    'datetime' => $c->created_at->format('d-m-Y h:i A'),
                    'timestamp' => $c->created_at->toIso8601String(),
                    'amount' => (float) $c->amount,
                    'payment_method' => $c->payment_method,
                    'payment_status' => match ($c->status) {
                        'completed' => 'Recovered',
                        'verified' => 'Recovered',
                        default => ucfirst($c->status)
                    },
                    'payment_status_label' => match ($c->status) {
                        'completed' => 'Recovered',
                        'verified' => 'Recovered',
                        'in_progress' => 'Processing',
                        default => ucfirst($c->status)
                    },
                    'emi_id' => $c->emi_id,
                ];
            });

        $actions = $actions->merge($collections);

        // Sort all actions by timestamp (newest first)
        $actions = $actions->sortByDesc('timestamp')->values();

        return response()->json([
            'success' => true,
            'count' => $actions->count(),
            'actions' => $actions,
        ]);
    }

    /**
     * GET: Collection Dashboard Statistics
     * Shows cash collected, upcoming, pending, and today collections
     */
    public function collectionDashboard(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = now()->toDateString();

        // 1. Cash Collected (In-Hand) - Only show cash in-hand (pending admin verification)
        $cashCollected = EmiCollection::where('agent_id', $agentId)
            ->where('payment_method', 'in_hand')
            ->where('status', 'in_progress')
            ->sum('amount');

        // 2. Upcoming Collections - Distinct EMIs with scheduled followups (future dates)
        // Filtered by 'appointment_to_visit' status to match upcomingCollections() list
        $latestUpcoming = \App\Models\EmiFollowup::selectRaw('MAX(id) as id')
            ->where('agent_id', $agentId)
            ->groupBy('emi_id');

        $upcomingCount = \App\Models\EmiFollowup::whereIn('id', $latestUpcoming)
            ->whereNotIn('status', ['appointment_to_visit', 'visit_rescheduled'])
            ->whereNotNull('followup_at')
            ->whereDate('followup_at', '>', $today)
            ->whereHas('emi', function ($q) use ($agentId) {
                $q->whereIn('status', ['overdue', 'pending', 'partial'])
                    ->whereHas('loanAccount', function ($lq) {
                        $lq->activeForCollection();
                    })
                    ->whereHas('assignments', function ($sq) use ($agentId) {
                        $sq->where('agent_id', $agentId)
                            ->whereIn('status', ['assigned', 'visited']);
                    })
                    ->whereDoesntHave('collections', function ($sq) use ($agentId) {
                        $sq->where('agent_id', $agentId)
                            ->where('status', 'in_progress');
                    });
            })
            ->distinct('emi_id')
            ->count('emi_id');

        // 3. In Progress Collections - Collections pending verification/payment
        // Note: Direct payments are processed immediately at gateway, so only payment_link and in_hand have in_progress status
        $inProgressCount = EmiCollection::where('agent_id', $agentId)
            ->where(function ($q) {
                $q->where(function ($sub) {
                    // In-hand collections pending verification
                    $sub->where('payment_method', 'in_hand')
                        ->where('status', 'in_progress');
                })
                    ->orWhere(function ($sub) {
                        // Payment links not yet paid
                        $sub->where('payment_method', 'payment_link')
                            ->where('status', 'in_progress');
                    });
            })
            ->count();

        // 4. Today Visits - All followups scheduled for today (all statuses)
        // Filtered by 'appointment_to_visit' status to match todayCollections() list
        $latestToday = \App\Models\EmiFollowup::selectRaw('MAX(id) as id')
            ->where('agent_id', $agentId)
            ->groupBy('emi_id');

        $todayCount = \App\Models\EmiFollowup::whereIn('id', $latestToday)
            ->whereNotIn('status', ['appointment_to_visit', 'visit_rescheduled'])
            ->whereNotNull('followup_at')
            ->whereDate('followup_at', $today)
            ->whereHas('emi', function ($q) use ($agentId) {
                $q->whereIn('status', ['overdue', 'pending', 'partial'])
                    ->whereHas('loanAccount', function ($lq) {
                        $lq->activeForCollection();
                    })
                    ->whereHas('assignments', function ($sq) use ($agentId) {
                        $sq->where('agent_id', $agentId)
                            ->whereIn('status', ['assigned', 'visited']);
                    })
                    ->whereDoesntHave('collections', function ($sq) use ($agentId) {
                        $sq->where('agent_id', $agentId)
                            ->where('status', 'in_progress');
                    });
            })
            ->distinct('emi_id')
            ->count('emi_id');


        return response()->json([
            'success' => true,
            'cash_collected' => [
                'amount' => (float) $cashCollected,
                'currency' => 'INR',
                'formatted' => '₹ ' . number_format($cashCollected, 2)
            ],
            'upcoming_collections' => [
                'count' => $upcomingCount,
                'label' => 'Customers expected to pay'
            ],
            'in_progress_collections' => [
                'count' => $inProgressCount,
                'label' => 'Pending admin verification'
            ],
            'today_collections' => [
                'count' => $todayCount,
                'label' => 'Today\'s Visits'
            ]
        ]);
    }

    /**
     * GET: Today Collections List
     * Shows today's pending collections (like today visits)
     */
    public function todayCollections(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = now()->toDateString();

        // Fetch "Appointment to Visit" from Followups scheduled for today
        // We only show if this is the LATEST followup for the EMI
        $latestFollowups = \App\Models\EmiFollowup::selectRaw('MAX(id) as id')
            ->where('agent_id', $agentId)
            ->groupBy('emi_id');

        $followups = \App\Models\EmiFollowup::whereIn('id', $latestFollowups)
            ->whereNotIn('status', ['appointment_to_visit', 'visit_rescheduled'])
            ->whereNotNull('followup_at')
            ->whereDate('followup_at', $today)
            ->whereHas('emi', function ($q) use ($agentId) {
                $q->whereIn('status', ['overdue', 'pending', 'partial'])
                    ->whereHas('loanAccount', function ($lq) {
                        $lq->activeForCollection();
                    })
                    ->whereHas('assignments', function ($sq) use ($agentId) {
                        $sq->where('agent_id', $agentId)
                            ->whereIn('status', ['assigned', 'visited']);
                    })
                    ->whereDoesntHave('collections', function ($sq) use ($agentId) {
                        $sq->where('agent_id', $agentId)
                            ->where('status', 'in_progress');
                    });
            })
            ->with([
                'emi.loanAccount.client',
                'emi.loanAccount.emis',
            ])
            ->orderBy('followup_at')
            ->get();

        $collections = $followups->map(function ($followup) {
            $emi = $followup->emi;
            $client = $emi->loanAccount->client;

            $loanAccount = $emi->loanAccount;
            $totalEmis = $loanAccount->emis->count();
            $paidEmis = $loanAccount->emis->where('status', 'paid')->count();

            $dueDate = \Carbon\Carbon::parse($emi->due_date);
            $daysPastDue = (int) floor(max(0, $dueDate->diffInDays(now(), false)));
            $projected = $this->getProjectedEmiData($emi);

            // Unified Risk Level Logic: High Risk if (2+ EMIs overdue) OR (Max DPD > 15)
            $overdueEmisCount = $loanAccount->emis->where('pending_amount', '>', 0)->count();
            $maxDpdAcrossLoan = $loanAccount->emis->max('dpd_days');
            $riskLevel = ($overdueEmisCount >= 2 || $maxDpdAcrossLoan > 15) ? 'High Risk' : 'Overdue';

            return [
                'followup_id' => $followup->id,
                'emi_id' => $emi->id,
                'loan_account_id' => $emi->loan_account_id,
                'loan_account_number' => $loanAccount->account_number,
                'emi_paid_count' => "{$paidEmis}/{$totalEmis}",
                'customer_name' => $client->client_name,
                'customer_phone' => $client->client_phone,
                'profile_image_url' => $client->profile_image_url,
                'days_past_due' => abs($daysPastDue),
                'days_past_due_label' => $daysPastDue . ' days',
                'risk_level' => $riskLevel,
                'over_due_amount' => (float) $projected['pending_amount'],  // Shows remaining amount
                'due_date' => $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->format('d-m-Y') : null,
                'visit_time' => $followup->followup_at ? $followup->followup_at->format('h:i A') : '--:--',
                'status' => $projected['status'] === 'partial' ? 'Partially Recovered' : ($projected['status'] === 'paid' ? 'Recovered' : $followup->status),
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $collections->count(),
            'collections' => $collections
        ]);
    }

    /**
     * GET: Pending Collections List
     * Shows pending collections (payment_link, direct, in_hand awaiting completion)
     */
    public function inProgressCollections(Request $request)
    {
        $agentId = Auth::user()->id;

        $query = EmiCollection::where('agent_id', $agentId)
            ->where('status', 'in_progress')
            ->whereIn('payment_method', ['payment_link', 'in_hand']);

        if ($request->has('loan_account_id')) {
            $query->whereHas('emi', function ($q) use ($request) {
                $q->where('loan_account_id', $request->loan_account_id);
            });
        }

        $collections = $query->with(['emi.loanAccount.client'])
            ->latest('created_at')
            ->get()
            ->map(function ($collection) {
                $emi = $collection->emi;
                $client = $emi->loanAccount->client;

                // Determine payment mode display text
                $paymentModeText = match ($collection->payment_method) {
                    'payment_link' => 'Link Sent',
                    'direct' => 'Direct',
                    'in_hand' => 'In Hand',
                    default => ucfirst($collection->payment_method)
                };

                return [
                    'collection_id' => $collection->id,
                    'emi_id' => $emi->id,
                    'loan_id' => $emi->loanAccount->account_number,
                    'loan_account_id' => $emi->loan_account_id,
                    'customer_name' => $client->client_name,
                    'customer_phone' => $client->client_phone,
                    'profile_image_url' => $client->profile_image_url,
                    'emi_amount' => (float) $collection->amount,
                    'due_date' => $emi->due_date->format('d-m-Y'),
                    'payment_mode' => $paymentModeText,
                    'payment_method' => $collection->payment_method,
                    'payment_type' => $collection->payment_type,
                    'status' => $collection->status,
                    'submitted_at' => $collection->collected_at->format('d-m-Y h:i A'),
                    'submitted_ago' => $collection->collected_at->diffForHumans(),
                    'proof_image_url' => $collection->proof_image_path
                        ? Storage::url($collection->proof_image_path)
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $collections->count(),
            'collections' => $collections
        ]);
    }

    /**
     * GET: Upcoming Collections List
     * Shows followups with scheduled dates
     */
    public function upcomingCollections(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = now()->toDateString();

        // Fetch "Appointment to Visit" from Followups scheduled for today onwards
        // We only show if this is the LATEST followup for the EMI
        $latestFollowups = \App\Models\EmiFollowup::selectRaw('MAX(id) as id')
            ->where('agent_id', $agentId)
            ->groupBy('emi_id');

        $followups = \App\Models\EmiFollowup::whereIn('id', $latestFollowups)
            ->whereNotIn('status', ['appointment_to_visit', 'visit_rescheduled'])
            ->whereNotNull('followup_at')
            ->whereDate('followup_at', '>', $today)
            ->whereHas('emi', function ($q) use ($agentId) {
                $q->whereIn('status', ['overdue', 'pending', 'partial'])
                    ->whereHas('loanAccount', function ($lq) {
                        $lq->activeForCollection();
                    })
                    ->whereHas('assignments', function ($sq) use ($agentId) {
                        $sq->where('agent_id', $agentId)
                            ->whereIn('status', ['assigned', 'visited']);
                    })
                    ->whereDoesntHave('collections', function ($sq) use ($agentId) {
                        $sq->where('agent_id', $agentId)
                            ->where('status', 'in_progress');
                    });
            })
            ->with(['emi.loanAccount.client', 'emi.loanAccount.emis'])
            ->orderBy('followup_at')
            ->get()
            ->map(function ($followup) {
                $emi = $followup->emi;
                $client = $emi->loanAccount->client;
                $loanAccount = $emi->loanAccount;

                // Calculate risk status based on EMI status and DPD
                $riskStatus = 'Pending';
                if ($emi->status === 'overdue') {
                    // Calculate Days Past Due (DPD)
                    $dueDate = $emi->due_date ? \Carbon\Carbon::parse($emi->due_date) : null;
                    $dpd = $dueDate ? (int) floor($dueDate->diffInDays(now(), false)) : 0;

                    if ($dpd > 15) {
                        $riskStatus = 'High Risk';
                    } else {
                        $riskStatus = 'Overdue';
                    }
                } elseif (in_array($emi->status, ['pending', 'partial'])) {
                    $riskStatus = 'Pending';
                }

                // Count paid EMIs for this loan account
                $totalEmis = $loanAccount->emis->count();
                $paidEmis = $loanAccount->emis->where('status', 'paid')->count();
                $emiPaidCount = "{$paidEmis}/{$totalEmis}";

                $projected = $this->getProjectedEmiData($emi);

                return [
                    'followup_id' => $followup->id,
                    'emi_id' => $emi->id,
                    'loan_account_id' => $emi->loan_account_id,
                    'loan_number' => $loanAccount->account_number,
                    'customer_name' => $client->client_name,
                    'customer_phone' => $client->client_phone,
                    'profile_image_url' => $client->profile_image_url,
                    'followup_date' => $followup->followup_at->format('d-m-Y'),
                    'followup_time' => $followup->followup_at->format('h:i A'),
                    'followup_datetime' => $followup->followup_at->format('d-m-Y h:i A'),
                    'status' => $projected['status'] === 'partial' ? 'Partially Recovered' : ($projected['status'] === 'paid' ? 'Recovered' : $followup->status),
                    'status_label' => config('followup.status_options')[$followup->status] ?? $followup->status,
                    'risk_status' => $riskStatus,
                    'emi_paid_count' => $emiPaidCount,
                    'remarks' => $followup->remarks,
                    'over_due_amount' => (float) $projected['pending_amount'], // Changed from total_due to projected pending_amount
                    'due_date' => $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->format('d-m-Y') : null,
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $followups->count(),
            'collections' => $followups
        ]);
    }

    /**
     * Get payment status/history for all clients attended by agent
     */
    public function paymentStatus(Request $request)
    {
        try {
            $agent = Auth::user();

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent not authenticated'
                ], 401);
            }

            $agentId = $agent->id;

            // Get all EMIs assigned to this agent with their collections
            $assignments = EmiAgentAssignment::with([
                'emi.loanAccount.client',
                'emi.collections' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ])
                ->where('agent_id', $agentId)
                ->whereIn('status', ['assigned', 'visited', 'resolved'])
                ->orderBy('created_at', 'desc')
                ->get();

            $paymentHistory = [];

            foreach ($assignments as $assignment) {
                $emi = $assignment->emi;
                $loanAccount = $emi->loanAccount ?? null;
                $client = $loanAccount->client ?? null;

                if (!$emi || !$loanAccount || !$client) {
                    continue;
                }

                // Get all collections for this EMI
                foreach ($emi->collections as $collection) {
                    $paymentHistory[] = [
                        'collection_id' => $collection->id,
                        'loan_id' => $loanAccount->account_number,
                        'client_name' => $client->client_name,
                        'client_phone' => $client->client_phone,
                        'emi_id' => $emi->id,
                        'emi_amount' => (float) $collection->amount,
                        'emi_due_date' => $emi->due_date ? $emi->due_date->format('d-m-Y') : null,
                        'payment_mode' => ucfirst(str_replace('_', ' ', $collection->payment_method)),
                        'payment_type' => ucfirst($collection->payment_type),
                        'status' => $this->getCollectionStatusLabel($collection->status),
                        'status_badge' => $this->getCollectionStatusBadge($collection->status),
                        'collected_at' => $collection->collected_at ? $collection->collected_at->format('d-m-Y') : null,
                        'payment_link' => $collection->payment_link_url ?? null,
                        'proof_image' => $collection->proof_image_path
                            ? Storage::url($collection->proof_image_path)
                            : null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $paymentHistory,
                'total_count' => count($paymentHistory)
            ]);

        } catch (\Exception $e) {
            Log::error('Payment status fetch failed', [
                'agent_id' => $agentId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to get projected EMI data (pending amount and status)
     * by accounting for in-progress collections.
     */
    private function getProjectedEmiData(Emi $emi)
    {
        $actualPending = (float) $emi->pending_amount;
        $actualStatus = $emi->status;

        // Sum up all in-progress collections for this EMI
        $inProgressAmount = EmiCollection::where('emi_id', $emi->id)
            ->where('status', 'in_progress')
            ->sum('amount');

        $projectedPending = max(0, $actualPending - $inProgressAmount);

        $projectedStatus = $actualStatus;
        if ($actualStatus !== 'paid' && $inProgressAmount > 0) {
            if ($projectedPending <= 0.01) {
                $projectedStatus = 'paid';
            } else {
                $projectedStatus = 'partial';
            }
        }

        return [
            'pending_amount' => $projectedPending,
            'status' => $projectedStatus,
            'in_progress_amount' => (float) $inProgressAmount
        ];
    }

    /**
     * Get human-readable status label
     */
    private function getCollectionStatusLabel($status)
    {
        $statusMap = [
            'in_progress' => 'Pending',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'pending' => 'Pending'
        ];

        return $statusMap[$status] ?? ucfirst($status);
    }

    /**
     * Get status badge color
     */
    private function getCollectionStatusBadge($status)
    {
        $badgeMap = [
            'in_progress' => 'warning',
            'verified' => 'success',
            'rejected' => 'danger',
            'completed' => 'success',
            'pending' => 'warning'
        ];

        return $badgeMap[$status] ?? 'secondary';
    }
}
