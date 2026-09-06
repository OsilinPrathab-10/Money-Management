<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmiAgentAssignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use App\Models\EmiCollection;
use App\Models\Emi;
use Illuminate\Database\Eloquent\Builder;
use App\Models\EmiFollowup;
use App\Models\AgentActivity;
use App\Services\PushNotificationService;
use App\Models\AgentNotification;

class EmiPaymentControllerApi extends Controller
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    public function generateLink(Request $request, $emiId)
    {
        try {
            $agentId = Auth::user()->agent->id;

            $assignment = EmiAgentAssignment::where('emi_id', $emiId)
                ->where('agent_id', $agentId)
                ->firstOrFail();

            $emi = $assignment->emi;
            $amount = $emi->total_amount;
            $amountPaise = (int) round($amount * 100);

            // Razorpay limits
            $maxAmountInr = 50000;
            $maxAmountPaise = 5000000;

            Log::info('Payment link request', [
                'emi_id' => $emi->id,
                'amount_inr' => $amount,
                'amount_paise' => $amountPaise,
                'max_allowed' => $maxAmountInr,
            ]);

            // FIRST: Validate amount BEFORE API call
            if ($amountPaise > $maxAmountPaise) {
                Log::warning('Amount exceeds Razorpay limit', [
                    'emi_id' => $emi->id,
                    'amount_inr' => $amount,
                    'max_allowed' => $maxAmountInr,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount ₹' . number_format($amount, 2) . ' exceeds maximum limit of ₹' . number_format($maxAmountInr, 2) . '. Please collect partial payment of maximum ₹' . number_format($maxAmountInr, 2) . ' each.',
                    'error' => 'amount_exceeds_maximum',
                    'max_allowed' => $maxAmountInr,
                    'current_amount' => $amount,
                ], 422);
            }

            if ($amountPaise < 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount must be at least ₹1',
                    'error' => 'amount_too_small',
                ], 422);
            }

            // API call with proper error handling
            $api = new \Razorpay\Api\Api(
                env('RAZORPAY_KEY_ID'),
                env('RAZORPAY_KEY_SECRET')
            );

            $paymentLink = $api->paymentLink->create([
                'amount' => $amountPaise,
                'currency' => 'INR',
                'accept_partial' => false,
                'description' => "EMI Payment - EMI #{$emi->id}",
                'customer' => [
                    'name' => $emi->loanAccount->client->client_name,
                    'contact' => $emi->loanAccount->client->client_phone,
                ],
                'notify' => [
                    'sms' => true,
                    'email' => false,
                ],
                'notes' => [
                    'emi_id' => $emi->id,
                    'agent_id' => $agentId,
                ],
                'callback_url' => route('agent.payment.callback'),
            ]);

            Log::info('Payment link generated', [
                'emi_id' => $emi->id,
                'amount_inr' => $amount,
            ]);

            return response()->json([
                'success' => true,
                'payment_link' => $paymentLink['short_url'],
                'expires_at' => $paymentLink['expire_by'] ?? null,
                'amount' => $amount,
            ]);

        } catch (\Razorpay\Api\Exceptions\BadRequestException $e) {
            $errorMsg = $e->getMessage();
            
            Log::error('Razorpay BadRequestException', [
                'emi_id' => $emi->id ?? $emiId,
                'amount_inr' => $amount ?? null,
                'error' => $errorMsg,
                'code' => $e->getCode(),
            ]);

            // Amount-related errors
            if (stripos($errorMsg, 'amount') !== false || 
                stripos($errorMsg, 'exceeds') !== false || 
                stripos($errorMsg, 'maximum') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount error: Maximum allowed is ₹50,000. Please collect partial payment.',
                    'error' => 'amount_error',
                    'max_allowed' => 50000,
                    'razorpay_error' => $errorMsg,
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment gateway error (BadRequest). Please try again or collect partial payment.',
                'error' => 'razorpay_bad_request',
            ], 422);

        } catch (\Exception $e) {
            Log::error('Payment link generation error', [
                'emi_id' => $emi->id ?? $emiId,
                'amount_inr' => $amount ?? null,
                'error_message' => $e->getMessage(),
                'exception_type' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate payment link. IMPORTANT: Maximum payment amount allowed is ₹50,000. If EMI amount exceeds this, collect partial payments.',
                'error' => 'payment_link_failed',
                'max_allowed' => 50000,
            ], 500);
        }
    }

    public function handle(Request $request)
    {
        Log::info('=== Payment Link Callback Received ===', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'all_params' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        try {
            // Check if this is a GET redirect callback (success page)
            if ($request->isMethod('GET')) {
                Log::info('Payment link GET callback (redirect)', [
                    'payment_id' => $request->input('razorpay_payment_id'),
                    'payment_link_id' => $request->input('razorpay_payment_link_id'),
                    'payment_link_reference_id' => $request->input('razorpay_payment_link_reference_id'),
                    'payment_link_status' => $request->input('razorpay_payment_link_status'),
                    'signature' => $request->input('razorpay_signature'),
                ]);

                // For GET callbacks, just return success page
                // The actual webhook will handle the payment processing
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment received. Processing...'
                ]);
            }

            // Handle POST webhook
            Log::info('Payment link POST webhook received', [
                'event' => $request->input('event'),
                'payload_keys' => array_keys($request->all()),
            ]);

            // Verify signature for POST webhooks
            $api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

            try {
                $api->utility->verifyPaymentSignature($request->all());
                Log::info('Payment signature verified successfully');
            } catch (\Exception $e) {
                Log::error('Payment signature verification failed', [
                    'error' => $e->getMessage(),
                    'request_data' => $request->all(),
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }

            $entity = $request->payload['payment']['entity'] ?? null;

            if (!$entity) {
                Log::error('Payment entity not found in webhook payload', [
                    'payload' => $request->all(),
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
            }

            $notes = $entity['notes'] ?? [];
            $collectionId = $notes['collection_id'] ?? null;
            $emiId = $notes['emi_id'] ?? null;
            $agentId = $notes['agent_id'] ?? null;
            $amount = ($entity['amount'] ?? 0) / 100;
            $paymentId = $entity['id'] ?? null;

            Log::info('Processing payment link webhook', [
                'collection_id' => $collectionId,
                'emi_id' => $emiId,
                'agent_id' => $agentId,
                'amount' => $amount,
                'payment_id' => $paymentId,
                'payment_method' => $entity['method'] ?? null,
            ]);

            // Find and update existing collection
            if ($collectionId) {
                $collection = EmiCollection::find($collectionId);

                if ($collection) {
                    Log::info('Updating existing collection', [
                        'collection_id' => $collection->id,
                        'old_status' => $collection->status,
                    ]);

                    $collection->update([
                        'status' => 'completed',
                        'remarks' => trim(($collection->remarks ?? '') . " | Payment ID: {$paymentId} | Method: " . ($entity['method'] ?? 'unknown')),
                    ]);

                    Log::info('Collection updated to completed', [
                        'collection_id' => $collection->id,
                    ]);
                } else {
                    Log::warning('Collection not found, creating new one', [
                        'collection_id' => $collectionId,
                    ]);

                    // Fallback: create new collection if not found
                    $collection = EmiCollection::create([
                        'emi_id' => $emiId,
                        'agent_id' => $agentId,
                        'amount' => $amount,
                        'payment_method' => 'payment_link',
                        'status' => 'completed',
                        'collected_at' => now(),
                        'remarks' => "Paid via payment link | Payment ID: {$paymentId}",
                    ]);
                }
            } else {
                Log::warning('No collection_id in notes, creating new collection', [
                    'notes' => $notes,
                ]);

                // Create new collection if no collection_id
                $collection = EmiCollection::create([
                    'emi_id' => $emiId,
                    'agent_id' => $agentId,
                    'amount' => $amount,
                    'payment_method' => 'payment_link',
                    'status' => 'completed',
                    'collected_at' => now(),
                    'remarks' => "Paid via payment link | Payment ID: {$paymentId}",
                ]);
            }

            // Update EMI status and amounts
            $emi = Emi::findOrFail($emiId);

            // Determine payment type from notes
            $paymentType = $entity['notes']['payment_type'] ?? 'overdue';
            $isPartialPayment = ($paymentType === 'partial');

            Log::info('Processing payment based on type', [
                'payment_type' => $paymentType,
                'is_partial' => $isPartialPayment,
                'amount' => $amount,
            ]);

            if ($isPartialPayment) {
                // Use LoanPaymentService for partial payments
                $paymentService = app(\App\Services\LoanPaymentService::class);

                $result = $paymentService->processPartialPayment(
                    $emi->loan_account_id,
                    $amount,
                    now()->format('Y-m-d'),
                    'payment_link',
                    $paymentId,
                    "Payment link payment"
                );

                if (!$result['success']) {
                    throw new \Exception($result['message']);
                }

                Log::info('Partial payment processed via LoanPaymentService', [
                    'collection_id' => $collection->id,
                    'result' => $result,
                ]);

            } else {
                // For overdue/full payments, use direct EMI update
                $newPaidAmount = ($emi->paid_amount ?? 0) + $amount;
                $newPendingAmount = max(0, ($emi->total_due ?? $emi->total_amount) - $newPaidAmount);

                $emi->update([
                    'paid_amount' => $newPaidAmount,
                    'pending_amount' => $newPendingAmount,
                    'status' => $newPendingAmount <= 0.01 ? 'paid' : 'partial',
                    'paid_date' => $newPendingAmount <= 0.01 ? now() : $emi->paid_date,
                    'is_partial_paid' => $newPendingAmount > 0.01,
                    'partial_paid_amount' => $newPendingAmount > 0.01 ? $newPaidAmount : $emi->partial_paid_amount,
                    'partial_paid_date' => $newPendingAmount > 0.01 ? now() : $emi->partial_paid_date,
                    'payment_reference' => $paymentId,
                    'payment_method' => $entity['method'] ?? 'razorpay',
                ]);

                Log::info('Overdue/full payment processed directly', [
                    'emi_id' => $emi->id,
                    'new_paid_amount' => $newPaidAmount,
                    'new_pending_amount' => $newPendingAmount,
                    'new_status' => $emi->status,
                ]);
            }

            // Refresh EMI to get latest status
            $emi->refresh();

            // Update agent assignment if EMI is fully paid
            if ($emi->status === 'paid' && $agentId) {
                $assignment = EmiAgentAssignment::where('emi_id', $emi->id)
                    ->where('agent_id', $agentId)
                    ->first();

                if ($assignment) {
                    $assignment->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'remarks' => trim(($assignment->remarks ?? '') . ' [Full payment received via payment link]'),
                    ]);

                    Log::info('Agent assignment resolved', [
                        'assignment_id' => $assignment->id,
                        'emi_id' => $emi->id,
                        'agent_id' => $agentId,
                    ]);
                }
            }

            Log::info('=== Payment Link Callback Processed Successfully ===', [
                'collection_id' => $collection->id,
                'emi_id' => $emi->id,
                'emi_status' => $emi->status,
            ]);

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            Log::error('=== Payment Link Callback Failed ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history for a loan account
     * Shows all EMIs with their payment status and collection details
     * GET /api/agent/payment-history?loan_account_id=123
     */
    public function paymentHistory(Request $request)
    {
        $request->validate([
            'loan_account_id' => 'required|integer|exists:loan_accounts,id',
        ]);

        $agentId = Auth::user()->id;
        $loanAccountId = $request->loan_account_id;

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

        // Get loan account details
        $loanAccount = \App\Models\LoanAccount::with('client')->findOrFail($loanAccountId);

        // Get all EMIs for this loan account
        $emis = Emi::where('loan_account_id', $loanAccountId)
            ->with([
                'collections' => function ($q) {
                    $q->whereIn('status', ['completed', 'verified'])
                        ->orderBy('created_at', 'desc');
                }
            ])
            ->orderBy('due_date', 'asc')
            ->get();

        $paymentHistory = [];

        foreach ($emis as $emi) {
            // Determine status badge
            $statusBadge = null;
            $statusColor = null;
            $paidDate = null;

            if ($emi->status === 'paid') {
                $statusBadge = 'Paid';
                $statusColor = 'success';
                $paidDate = $emi->paid_date ? \Carbon\Carbon::parse($emi->paid_date)->format('d-m-Y') : null;
            } elseif ($emi->status === 'partial') {
                $statusBadge = 'Partial';
                $statusColor = 'warning';
            } elseif ($emi->status === 'overdue') {
                $statusBadge = 'Overdue';
                $statusColor = 'danger';
            } else {
                $statusBadge = 'Pending';
                $statusColor = 'info';
            }

            // Get latest collection for this EMI
            $latestCollection = $emi->collections->first();
            $paymentMethod = null;

            if ($latestCollection) {
                $paymentMethod = match ($latestCollection->payment_method) {
                    'in_hand' => 'In-Hand',
                    'direct' => 'Online Payment',
                    'payment_link' => 'Payment Link',
                    default => ucfirst($latestCollection->payment_method),
                };
            }

            $paymentHistory[] = [
                'emi_id' => $emi->id,
                'loan_id' => $loanAccount->account_number,
                'emi_number' => $emi->emi_number,

                // Status Badge
                'status_badge' => $statusBadge,
                'status_badge_text' => $paidDate ? "Paid on {$paidDate}" : $statusBadge,
                'status_color' => $statusColor,

                // Amount Details
                'emi_amount' => (float) $emi->total_amount,
                'emi_amount_formatted' => '₹ ' . number_format($emi->total_amount, 0),
                'paid_amount' => (float) $emi->paid_amount,
                'pending_amount' => (float) $emi->pending_amount,

                // Date Details
                'emi_date' => $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->format('d-m-Y') : null,
                'paid_date' => $paidDate,

                // Payment Method
                'payment_mode' => $paymentMethod,

                // Status
                'status' => $emi->status,
                'is_paid' => $emi->status === 'paid',
                'is_partial' => $emi->status === 'partial',
                'is_overdue' => $emi->status === 'overdue',
            ];
        }

        return response()->json([
            'success' => true,
            'loan_account' => [
                'id' => $loanAccount->id,
                'account_number' => $loanAccount->account_number,
                'client_name' => $loanAccount->client->client_name,
            ],
            'count' => count($paymentHistory),
            'data' => $paymentHistory,
        ]);
    }



    public function followups(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = now()->toDateString();

        $type = $request->get('type', 'all'); // 'all', 'today', 'yesterday', 'upcoming', 'past'
        $date = $request->get('date'); // Optional: specific date filter (Y-m-d format)
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        // Get only the latest followup per EMI to avoid duplicate cards
        $query = EmiFollowup::with([
            'emi.loanAccount.client.location',
            'emi' => function ($q) {
                $q->select('id', 'loan_account_id', 'total_amount', 'pending_amount', 'due_date', 'status');
            }
        ])
            ->where('agent_id', $agentId)
            ->whereHas('emi', function ($q) {
                $q->where('status', '!=', 'paid');
            })
            ->whereHas('emi.loanAccount', function ($q) {
                $q->activeForCollection();
            })
            ->whereIn('id', function ($subquery) use ($agentId) {
                $subquery->selectRaw('MAX(id)')
                    ->from('emi_followups')
                    ->where('agent_id', $agentId)
                    ->groupBy('emi_id');
            });

        // Date-based filtering
        if ($fromDate && $toDate) {
            $query->whereDate('followup_at', '>=', $fromDate)
                ->whereDate('followup_at', '<=', $toDate)
                ->orderBy('followup_at', 'asc');
        } elseif ($date) {
            // Filter by specific date
            $query->whereDate('followup_at', $date)
                ->orderBy('followup_at', 'asc');
        } elseif ($type === 'today') {
            // Today's followups
            $query->whereDate('followup_at', $today)
                ->orderBy('followup_at', 'asc');
        } elseif ($type === 'yesterday') {
            // Yesterday's followups
            $query->whereDate('followup_at', \Carbon\Carbon::yesterday()->toDateString())
                ->orderBy('followup_at', 'desc'); // Most recent yesterday first
        } elseif ($type === 'upcoming') {
            // Future followups
            $query->whereDate('followup_at', '>', $today)
                ->orderBy('followup_at', 'asc');
        } elseif ($type === 'past') {
            // Past followups
            $query->whereDate('followup_at', '<', $today)
                ->orderBy('followup_at', 'desc');
        } else {
            // Default 'all'
            // Let's use the original sorting: ASC (Overdue first)
            $query->orderByRaw('followup_at IS NULL, followup_at ASC');
        }


        $followups = $query->get();

        $mappedData = $followups->map(function ($f) use ($today) {
            $emi = $f->emi;
            $client = optional(optional($emi)->loanAccount)->client;
            $loanAccount = optional($emi)->loanAccount;

            // Determine time display
            $followupDate = $f->followup_at ? \Carbon\Carbon::parse($f->followup_at) : null;
            $isToday = $followupDate && $followupDate->toDateString() === $today;
            $isPast = $followupDate && $followupDate->isPast();

            // Calculate risk status based on EMI status and DPD
            $riskStatus = 'Pending';
            if ($emi) {
                if ($emi->status === 'overdue') {
                    // Calculate Days Past Due (DPD)
                    $dueDate = $emi->due_date ? \Carbon\Carbon::parse($emi->due_date) : null;
                    $dpd = $dueDate ? now()->diffInDays($dueDate, false) : 0;

                    if ($dpd > 15) {
                        $riskStatus = 'High Risk';
                    } else {
                        $riskStatus = 'Overdue';
                    }
                } elseif (in_array($emi->status, ['pending', 'partial'])) {
                    $riskStatus = 'Pending';
                }
            }

            // Count paid EMIs for this loan account
            $emiPaidCount = 0;
            $totalEmis = 0;
            if ($loanAccount) {
                $totalEmis = \App\Models\Emi::where('loan_account_id', $loanAccount->id)->count();
                $paidEmis = \App\Models\Emi::where('loan_account_id', $loanAccount->id)
                    ->where('status', 'paid')
                    ->count();
                $emiPaidCount = "{$paidEmis}/{$totalEmis}";
            }

            return [
                // All table columns
                'id' => $f->id,
                'emi_id' => $f->emi_id,
                'agent_id' => $f->agent_id,
                'status' => $f->status,
                'followup_at' => $followupDate ? $followupDate->toIso8601String() : null,
                'remarks' => $f->remarks,
                'created_at' => $f->created_at ? $f->created_at->toIso8601String() : null,
                'updated_at' => $f->updated_at ? $f->updated_at->toIso8601String() : null,

                // Derived/Extra fields
                'followup_id' => $f->id,
                'loan_account_id' => optional($emi)->loan_account_id,

                // Customer Info
                'customer_name' => $client->client_name ?? 'N/A',
                'customer_phone' => $client->client_phone ?? 'N/A',
                'profile_image_url' => $client->profile_image_url ?? null,

                // Status Info
                'status_label' => config('followup.status_options')[$f->status] ?? ucwords(str_replace('_', ' ', $f->status)),
                'risk_status' => $riskStatus,

                // Time Info
                'followup_at_formatted' => $followupDate ? $followupDate->format('d-m-Y h:i A') : null,
                'followup_date' => $followupDate ? $followupDate->format('d-m-Y') : null,
                'followup_time' => $followupDate ? $followupDate->format('h:i A') : null,
                'time_badge' => $isToday ? 'Today' : ($isPast ? 'Overdue' : 'Upcoming'),
                'is_today' => $isToday,
                'is_past' => $isPast,

                // Amount Info
                'emi_amount' => $emi ? (float) ($emi->pending_amount + $emi->paid_amount) : 0, // Calculated Total Due
                'pending_amount' => $emi ? (float) $emi->pending_amount : 0,
                'due_date' => $emi && $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->format('d-m-Y') : null,

                // EMI Payment Info
                'emi_paid_count' => $emiPaidCount,

                // Additional Info
                'loan_number' => optional(optional($emi)->loanAccount)->account_number,
                'location' => optional($client->location)->name,

                // For UI actions
                'can_reschedule' => true,
                'can_call' => true,
                'can_collect' => $emi && $emi->pending_amount > 0,
            ];
        });

        return response()->json([
            'success' => true,
            'date' => now()->format('d-m-Y'),
            'filter_type' => $type,
            'count' => $mappedData->count(),
            'data' => $mappedData,
        ]);
    }

    /**
     * Update EMI status with conditional field requirements
     * POST /api/agent/emis/{id}/update-status
     */
    public function updateStatus(Request $request, $emiId)
    {
        $agentId = Auth::user()->id;

        // Get allowed statuses and requirements from config
        $allowedStatuses = array_keys(config('followup.status_options', []));
        $requiresDateTime = config('followup.statuses_requiring_datetime', []);

        // Base validation
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'status' => 'required|in:' . implode(',', $allowedStatuses),
            'remarks' => 'required|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Add conditional validation for followup_at
        $validator->sometimes('followup_at', 'required|date_format:Y-m-d H:i', function ($input) use ($requiresDateTime) {
            return in_array($input->status, $requiresDateTime);
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify agent has access to this EMI
        $emi = Emi::with('loanAccount')
            ->where('id', $emiId)
            ->whereHas('assignments', fn($q) => $q->where('agent_id', $agentId))
            ->firstOrFail();

        /* 
        // Logic Reverted: User wants visits to be handled via followups table for scheduling
        // as agent_visit_logs doesn't support 'visit_type' column.

        // This will now fall through to the default followup creation below
        if ($request->status === 'appointment_to_visit') {
           // ... logic removed ...
        }
        */

        // All other statuses - create follow-up record
        $followup = $emi->followups()->create([
            'agent_id' => $agentId,
            'status' => $request->status,
            'followup_at' => $request->followup_at,
            'remarks' => $request->remarks,
        ]);

        // Calculate paid EMIs for response
        $totalEmis = Emi::where('loan_account_id', $emi->loan_account_id)->count();
        $paidEmis = Emi::where('loan_account_id', $emi->loan_account_id)
            ->where('status', 'paid')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'EMI status updated successfully',
            'data' => [
                'followup_id' => $followup->id,
                'type' => 'followup',
                'loan_account_number' => $emi->loanAccount->account_number,
                'paid_emis' => "{$paidEmis}/{$totalEmis}",
                'status' => config('followup.status_options')[$request->status] ?? $request->status,
                'followup_at' => $followup->followup_at ? $followup->followup_at->format('d-m-Y h:i A') : null,
            ]
        ]);
    }

    private function sendFollowupScheduledNotification($followup, $emi, $agentId): void
    {
        try {
            $agent = \App\Models\Agent::with('user')->find($agentId);
            if (!$agent || !$agent->user)
                return;

            $deviceToken = $agent->user->userDevice()
                ->where('user_type', 'Agent')
                ->latest()
                ->value('device_token');

            if (!$deviceToken)
                return;

            $client = $emi->loanAccount->client ?? null;
            $customerName = $client ? $client->client_name : 'Customer';
            $loanNumber = $emi->loanAccount->account_number ?? 'N/A';
            $statusLabel = config("followup.status_options.{$followup->status}") ?? $followup->status;
            $scheduledTime = $followup->followup_at->format('d-m-Y h:i A');

            $title = 'Followup Scheduled';
            $body = "Followup for {$customerName} (Loan: {$loanNumber}) scheduled at {$scheduledTime}";

            $actionData = [
                'type' => 'followup',
                'followup_id' => (string) $followup->id,
                'emi_id' => (string) $followup->emi_id,
                'loan_account_id' => (string) $emi->loan_account_id,
                'status' => $followup->status,
            ];

            $result = $this->pushService->sendPushNotification($deviceToken, $title, $body, $actionData);

            if ($result['success']) {
                AgentNotification::create([
                    'agent_id' => $agentId,
                    'notification_type' => 'followup_scheduled',
                    'notification_id' => (string) $followup->id,
                    'title' => $title,
                    'message' => $body,
                    'notification_type_label' => 'followup',
                    'icon' => 'schedule',
                    'priority' => 'medium',
                    'action_data' => $actionData,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send followup scheduled notification', [
                'followup_id' => $followup->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @deprecated Use updateStatus() instead
     * Kept for backward compatibility
     */
    public function updateEmiStatus(Request $request, $emiId)
    {
        return $this->updateStatus($request, $emiId);
    }

    /**
     * Get predefined followup status options with field requirements
     */
    public function getFollowupOptions(Request $request)
    {
        $statusOptions = config('followup.status_options');
        $requiresDateTime = config('followup.statuses_requiring_datetime', []);
        $requiresNotesOnly = config('followup.statuses_requiring_notes_only', []);

        // Build metadata for each status
        $statusesWithMetadata = [];
        foreach ($statusOptions as $key => $label) {
            $statusesWithMetadata[] = [
                'key' => $key,
                'label' => $label,
                'requires_datetime' => in_array($key, $requiresDateTime),
                'requires_notes' => true, // All statuses require notes
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'followup_status_options' => $statusOptions,
                'statuses_metadata' => $statusesWithMetadata,
            ]
        ]);
    }

    /**
     * Reschedule an existing followup
     * PUT /api/agent/followups/{id}/reschedule
     */
    public function rescheduleFollowup(Request $request, $followupId)
    {
        $agentId = Auth::user()->id;

        // Get allowed statuses and requirements from config
        $allowedStatuses = array_keys(config('followup.status_options', []));
        $requiresDateTime = config('followup.statuses_requiring_datetime', []);

        // Base validation
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'status' => 'nullable|in:' . implode(',', $allowedStatuses),
            'remarks' => 'nullable|string|max:1000',
        ]);

        // Add conditional validation for followup_at
        $validator->sometimes('followup_at', 'required|date_format:Y-m-d H:i', function ($input) use ($requiresDateTime) {
            return $input->status && in_array($input->status, $requiresDateTime);
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find the followup and verify agent ownership
        $followup = EmiFollowup::where('id', $followupId)
            ->where('agent_id', $agentId)
            ->with('emi.loanAccount')
            ->firstOrFail();

        // Update the followup
        $updateData = [];

        if ($request->has('status')) {
            $updateData['status'] = $request->status;
        }

        if ($request->has('followup_at')) {
            $updateData['followup_at'] = $request->followup_at;
        }

        if ($request->has('remarks')) {
            $updateData['remarks'] = $request->remarks;
        }

        $followup->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Followup rescheduled successfully',
            'data' => [
                'followup_id' => $followup->id,
                'emi_id' => $followup->emi_id,
                'status' => config('followup.status_options')[$followup->status] ?? $followup->status,
                'followup_at' => $followup->followup_at ? $followup->followup_at->format('d-m-Y h:i A') : null,
                'remarks' => $followup->remarks,
            ]
        ]);
    }

    /**
     * Comprehensive Notifications API
     * Shows: Broadcast notifications, Unactioned cases, Scheduled visits, Scheduled followups, Unresolved cases, Attendance confirmations
     * GET /api/agent/notifications
     */
    public function notifications(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = now()->toDateString();
        $now = now();
        $time = now();

        $filter = $request->get('filter', 'all');

        $notifications = [];

        // 0. BROADCAST NOTIFICATIONS - From admin panel
        $broadcastNotifications = \App\Models\AgentNotification::where('agent_id', $agentId)
            ->where('notification_type', 'broadcast')
            ->latest('created_at')
            ->get();

        foreach ($broadcastNotifications as $broadcast) {
            $notifications[] = [
                'id' => $broadcast->notification_id, // Use the notification_id from database
                'type' => 'broadcast',
                'priority' => $broadcast->priority,
                'icon' => $broadcast->icon,
                'title' => $broadcast->title,
                'message' => $broadcast->message,
                'time' => $broadcast->created_at->format('h:i A'),
                'timestamp' => $broadcast->created_at->toIso8601String(),
                'action_data' => $broadcast->action_data,
                'action_type' => $broadcast->action_data['screen'] ?? 'none',
                'is_read' => $broadcast->read_at !== null,
            ];
        }

        // 0.5. STORED PUSH NOTIFICATIONS - followup reminders, visit reminders, unresolved alerts, case assignments
        $storedNotifications = \App\Models\AgentNotification::where('agent_id', $agentId)
            ->whereIn('notification_type', ['followup_reminder', 'appointment_visit_reminder', 'unresolved_cases_alert', 'case_assigned'])
            ->latest('created_at')
            ->get();

        // Pre-load EMI data for stored notifications that have an emi_id in action_data
        $storedEmiIds = $storedNotifications
            ->map(fn($n) => $n->action_data['emi_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $storedEmiMap = \App\Models\Emi::with('loanAccount.client')
            ->whereIn('id', $storedEmiIds)
            ->get()
            ->keyBy('id');

        foreach ($storedNotifications as $stored) {
            $emiId = $stored->action_data['emi_id'] ?? null;
            $storedEmi = $emiId ? ($storedEmiMap[$emiId] ?? null) : null;
            $storedClient = $storedEmi?->loanAccount?->client;

            $notifications[] = [
                'id' => $stored->notification_id,
                'type' => $stored->notification_type,
                'priority' => $stored->priority,
                'icon' => $stored->icon,
                'title' => $stored->title,
                'message' => $stored->message,
                'customer_name' => $storedClient?->client_name,
                'loan_account_number' => $storedEmi?->loanAccount?->account_number,
                'time' => $stored->created_at->format('h:i A'),
                'timestamp' => $stored->created_at->toIso8601String(),
                'action_data' => $stored->action_data,
                'action_type' => match ($stored->notification_type) {
                    'appointment_visit_reminder' => 'start_visit',
                    'unresolved_cases_alert' => 'view_unactioned_cases',
                    default => 'open_followup',
                },
                'is_read' => $stored->read_at !== null,
            ];
        }

        // 1. TODAY CASES NOT UPDATED - Remind about unactioned cases (CONSOLIDATED)
        $unactionedCases = EmiAgentAssignment::where('agent_id', $agentId)
            ->active()
            ->onActiveLoan()
            ->whereDate('assigned_at', $today)
            ->whereHas('emi', function ($q) use ($today) {
                $q->where('status', '!=', 'paid')
                    ->whereDoesntHave('followups', function ($sq) use ($today) {
                        $sq->whereDate('created_at', $today);
                    })
                    ->whereDoesntHave('visitLogs', function ($sq) use ($today) {
                        $sq->whereDate('created_at', $today);
                    })
                    ->whereDoesntHave('collections', function ($sq) use ($today) {
                        $sq->whereDate('created_at', $today);
                    });
            })
            ->with('emi.loanAccount.client')
            ->get();

        if ($unactionedCases->count() > 0) {
            $caseCount = $unactionedCases->count();
            $totalPending = $unactionedCases->sum(function ($assignment) {
                return $assignment->emi->pending_amount ?? 0;
            });

            $firstUnactioned = $unactionedCases->first();
            $firstUnactionedClient = $firstUnactioned?->emi?->loanAccount?->client;

            $notifications[] = [
                'id' => 'unactioned_cases_today',
                'type' => 'unactioned_cases_group',
                'priority' => 'high',
                'icon' => 'alert',
                'title' => 'Cases Pending Action',
                'message' => "{$caseCount} cases assigned today require action",
                'customer_name' => $caseCount === 1 ? $firstUnactionedClient?->client_name : null,
                'loan_account_number' => $caseCount === 1 ? $firstUnactioned?->emi?->loanAccount?->account_number : null,
                'time' => $firstUnactioned?->assigned_at?->format('h:i A') ?? $time->format('h:i A'),
                'timestamp' => $firstUnactioned?->assigned_at?->toIso8601String() ?? $time->toIso8601String(),
                'notification_sent_at' => $time->format('h:i A'),
                'notification_sent_timestamp' => $time->toIso8601String(),
                'action_data' => [
                    'case_count' => $caseCount,
                    'total_pending_amount' => (float) $totalPending,
                    'notification_sent_at' => $time->toIso8601String(),
                    'cases' => $unactionedCases->map(function ($assignment) {
                        $emi = $assignment->emi;
                        $client = $emi->loanAccount->client;
                        return [
                            'emi_id' => $emi->id,
                            'loan_account_id' => $emi->loan_account_id,
                            'loan_id' => optional($emi->loanAccount)->account_number,
                            'customer_name' => $client->client_name,
                            'pending_amount' => (float) $emi->pending_amount,
                        ];
                    })->values()->all(),
                ],
                'action_type' => 'view_unactioned_cases',
            ];
        }

        // 2. SCHEDULED VISITS - Time-based reminders (within next 2 hours)
        $upcomingVisits = \App\Models\EmiFollowup::where('agent_id', $agentId)
            ->where('status', 'appointment_to_visit')
            ->whereNotNull('followup_at')
            ->whereBetween('followup_at', [$now, $now->copy()->addHours(2)])
            ->whereHas('emi.loanAccount', function ($q) {
                $q->activeForCollection();
            })
            ->with('emi.loanAccount.client')
            ->get();

        foreach ($upcomingVisits as $visit) {
            $emi = $visit->emi;
            $client = $emi->loanAccount->client;
            $timeUntil = $now->diffInMinutes($visit->followup_at);

            $notifications[] = [
                'id' => 'visit_' . $visit->id,
                'type' => 'scheduled_visit',
                'priority' => $timeUntil <= 30 ? 'high' : 'medium',
                'icon' => 'location',
                'title' => 'Upcoming Visit',
                'message' => "Visit scheduled with {$client->client_name} in {$timeUntil} minutes",
                'customer_name' => $client->client_name,
                'loan_account_number' => $emi->loanAccount->account_number,
                'time' => $visit->created_at->format('h:i A'),
                'timestamp' => $visit->created_at->toIso8601String(),
                'action_data' => [
                    'emi_id' => $emi->id,
                    'loan_account_id' => $emi->loan_account_id,
                    'loan_id' => $emi->loanAccount->account_number,
                    'customer_name' => $client->client_name,
                    'customer_phone' => $client->client_phone,
                    'location' => optional($client->location)->name,
                    'scheduled_at' => $visit->followup_at->format('h:i A'),
                ],
                'action_type' => 'start_visit',
            ];
        }

        // 3. SCHEDULED FOLLOWUPS - Time-based reminders (within next 2 hours)
        $upcomingFollowups = EmiFollowup::where('agent_id', $agentId)
            ->where('status', '!=', 'appointment_to_visit')
            ->whereNotNull('followup_at')
            ->whereBetween('followup_at', [$now, $now->copy()->addHours(2)])
            ->whereHas('emi.loanAccount', function ($q) {
                $q->activeForCollection();
            })
            ->with('emi.loanAccount.client')
            ->get();

        foreach ($upcomingFollowups as $followup) {
            $emi = $followup->emi;
            $client = $emi->loanAccount->client;
            $timeUntil = $now->diffInMinutes($followup->followup_at);

            $notifications[] = [
                'id' => 'followup_' . $followup->id,
                'type' => 'scheduled_followup',
                'priority' => $timeUntil <= 30 ? 'high' : 'medium',
                'icon' => 'calendar',
                'title' => config('followup.status_options')[$followup->status] ?? 'Followup Reminder',
                'message' => "Followup with {$client->client_name} in {$timeUntil} minutes",
                'customer_name' => $client->client_name,
                'loan_account_number' => $emi->loanAccount->account_number,
                'time' => $followup->created_at->format('h:i A'),
                'timestamp' => $followup->created_at->toIso8601String(),
                'action_data' => [
                    'followup_id' => $followup->id,
                    'emi_id' => $emi->id,
                    'loan_account_id' => $emi->loan_account_id,
                    'loan_id' => $emi->loanAccount->account_number,
                    'customer_name' => $client->client_name,
                    'customer_phone' => $client->client_phone,
                    'status' => $followup->status,
                    'remarks' => $followup->remarks,
                    'scheduled_at' => $followup->followup_at->format('h:i A'),
                ],
                'action_type' => 'call_customer',
            ];
        }

        // 4. UNRESOLVED CASES - Cases assigned before today with no followup/collection/visit by this agent
        $unresolvedCases = EmiAgentAssignment::where('agent_id', $agentId)
            ->active()
            ->onActiveLoan()
            ->whereDate('assigned_at', '<', $today) // before today
            ->whereHas('emi', function ($q) use ($agentId) {
                $q->where('status', '!=', 'paid')
                    ->whereDoesntHave('followups', fn($fq) => $fq->where('agent_id', $agentId))
                    ->whereDoesntHave('collections', fn($cq) => $cq->where('agent_id', $agentId))
                    ->whereDoesntHave('visitLogs', fn($vq) => $vq->where('agent_id', $agentId));
            })
            ->with('emi.loanAccount.client')
            ->limit(5)
            ->get();


        foreach ($unresolvedCases as $assignment) {
            $emi = $assignment->emi;
            $client = $emi->loanAccount->client;
            $daysOld = now()->diffInDays($assignment->assigned_at);

            $notifications[] = [
                'id' => 'unresolved_' . $assignment->id,
                'type' => 'unresolved_case',
                'priority' => $daysOld >= 5 ? 'high' : 'medium',
                'icon' => 'warning',
                'title' => 'Unresolved Case',
                'message' => "{$client->client_name} - Pending for {$daysOld} days",
                'customer_name' => $client->client_name,
                'loan_account_number' => $emi->loanAccount->account_number,
                'time' => $assignment->assigned_at->format('h:i A'),
                'timestamp' => $assignment->assigned_at->toIso8601String(),
                'notification_sent_at' => $time->format('h:i A'),
                'notification_sent_timestamp' => $time->toIso8601String(),
                'action_data' => [
                    'emi_id' => $emi->id,
                    'loan_account_id' => $emi->loan_account_id,
                    'loan_id' => $emi->loanAccount->account_number,
                    'customer_name' => $client->client_name,
                    'pending_amount' => (float) $emi->pending_amount,
                    'days_pending' => $daysOld,
                    'notification_sent_at' => $time->toIso8601String(),
                ],
                'action_type' => 'open_case',
            ];
        }

        // 5. CHECK-IN/CHECK-OUT SUCCESS - Recent attendance confirmations (last 12 hours)
        $recentAttendance = \App\Models\AgentActivity::where('agent_id', $agentId)
            ->whereIn('type', ['check_in', 'check_out'])
            ->where('action_at', '>=', now()->subHours(12))
            ->latest('action_at')
            ->limit(2)
            ->get();

        foreach ($recentAttendance as $attendance) {
            $notifications[] = [
                'id' => 'attendance_' . $attendance->id,
                'type' => $attendance->type === 'check_in' ? 'check_in_success' : 'check_out_success',
                'priority' => 'low',
                'icon' => $attendance->type === 'check_in' ? 'login' : 'logout',
                'title' => $attendance->type === 'check_in' ? 'Checked In' : 'Checked Out',
                'message' => $attendance->description ?? ($attendance->type === 'check_in' ? 'You have successfully checked in' : 'You have successfully checked out'),
                'customer_name' => null,
                'loan_account_number' => null,
                'time' => $attendance->action_at->format('h:i A'),
                'timestamp' => $attendance->action_at->toIso8601String(),
                'action_data' => null,
                'action_type' => 'none',
                'is_read' => false,
            ];
        }

        // Get read notifications for this agent
        $readNotifications = \App\Models\AgentNotification::where('agent_id', $agentId)
            ->whereNotNull('read_at')
            ->get()
            ->groupBy('notification_type')
            ->map(function ($group) {
                return $group->pluck('notification_id')->toArray();
            })
            ->toArray();

        // Mark each notification with is_read status
        $notifications = array_map(function ($notification) use ($readNotifications) {
            $type = $notification['type'];
            $id = $notification['id'];

            // Check if this notification has been marked as read
            $isRead = isset($readNotifications[$type]) && in_array($id, $readNotifications[$type]);
            $notification['is_read'] = $isRead;

            return $notification;
        }, $notifications);

        // Filter based on filter parameter
        if ($filter === 'unread') {
            $notifications = array_filter($notifications, fn($n) => !$n['is_read']);
            $notifications = array_values($notifications);
        }

        // Sort by priority and timestamp (high priority first, then by time)
        $priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
        usort($notifications, function ($a, $b) use ($priorityOrder) {
            $priorityCompare = $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }
            return strtotime($b['timestamp']) <=> strtotime($a['timestamp']);
        });

        $unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));
        $totalCount = count($notifications);

        return response()->json([
            'success' => true,
            'filter' => $filter,
            'count' => count($notifications),
            'unread_count' => $unreadCount,
            'total_count' => $totalCount,
            'notifications' => $notifications,
        ]);
    }


    private function emiPaidText($emi)
    {
        $totalEmis = Emi::where('loan_account_id', $emi->loan_account_id)->count();

        $paidEmis = Emi::where('loan_account_id', $emi->loan_account_id)
            ->where('status', 'paid')
            ->count();

        return "{$paidEmis}/{$totalEmis} EMI Paid";
    }

    private function uiStatus($emi)
    {
        if ($emi->status === 'paid') {
            return 'complete';
        }

        if ($emi->status === 'overdue') {
            return 'high_risk';
        }

        return 'pending'; // pending or partial
    }

    /**
     * Mark a single notification as read
     * POST /api/agent/notifications/mark-read
     */
    public function markAsRead(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|string',
            'notification_type' => 'required|string',
        ]);

        $agentId = Auth::user()->id;

        // Handle broadcast notifications
        if ($request->notification_type === 'broadcast') {
            // For broadcast notifications, use notification_id to find and mark as read
            \App\Models\AgentNotification::where('agent_id', $agentId)
                ->where('notification_type', 'broadcast')
                ->where('notification_id', $request->notification_id)
                ->update(['read_at' => now()]);
        } else {
            // For system notifications, create tracking record
            \App\Models\AgentNotification::updateOrCreate(
                [
                    'agent_id' => $agentId,
                    'notification_type' => $request->notification_type,
                    'notification_id' => $request->notification_id,
                ],
                [
                    'read_at' => now(),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     * POST /api/agent/notifications/mark-all-read
     */
    public function markAllAsRead(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = now()->toDateString();
        $now = now();

        $notificationIds = [];

        // Get all current notification IDs and types
        // 1. Unactioned cases
        $unactionedCases = EmiAgentAssignment::where('agent_id', $agentId)
            ->active()
            ->onActiveLoan()
            ->whereDate('assigned_at', $today)
            ->whereHas('emi', function ($q) use ($today) {
                $q->where('status', '!=', 'paid')
                    ->whereDoesntHave('followups', function ($sq) use ($today) {
                        $sq->whereDate('created_at', $today);
                    })
                    ->whereDoesntHave('visitLogs', function ($sq) use ($today) {
                        $sq->whereDate('created_at', $today);
                    })
                    ->whereDoesntHave('collections', function ($sq) use ($today) {
                        $sq->whereDate('created_at', $today);
                    });
            })
            ->exists();

        if ($unactionedCases) {
            $notificationIds[] = [
                'notification_type' => 'unactioned_cases_group',
                'notification_id' => 'unactioned_cases_today',
            ];
        }

        // 2. Scheduled visits
        $upcomingVisits = \App\Models\EmiFollowup::where('agent_id', $agentId)
            ->where('status', 'appointment_to_visit')
            ->whereNotNull('followup_at')
            ->whereBetween('followup_at', [$now, $now->copy()->addHours(2)])
            ->whereHas('emi.loanAccount', function ($q) {
                $q->activeForCollection();
            })
            ->get();

        foreach ($upcomingVisits as $visit) {
            $notificationIds[] = [
                'notification_type' => 'scheduled_visit',
                'notification_id' => 'visit_' . $visit->id,
            ];
        }

        // 3. Scheduled followups
        $upcomingFollowups = EmiFollowup::where('agent_id', $agentId)
            ->where('status', '!=', 'appointment_to_visit')
            ->whereNotNull('followup_at')
            ->whereBetween('followup_at', [$now, $now->copy()->addHours(2)])
            ->whereHas('emi.loanAccount', function ($q) {
                $q->activeForCollection();
            })
            ->get();

        foreach ($upcomingFollowups as $followup) {
            $notificationIds[] = [
                'notification_type' => 'scheduled_followup',
                'notification_id' => 'followup_' . $followup->id,
            ];
        }

        // 4. Unresolved cases
        $unresolvedCases = EmiAgentAssignment::where('agent_id', $agentId)
            ->active()
            ->onActiveLoan()
            ->whereDate('assigned_at', '<=', now()->subDays(2)->toDateString())
            ->whereHas('emi', fn($q) => $q->where('status', '!=', 'paid'))
            ->limit(5)
            ->get();

        foreach ($unresolvedCases as $assignment) {
            $notificationIds[] = [
                'notification_type' => 'unresolved_case',
                'notification_id' => 'unresolved_' . $assignment->id,
            ];
        }

        // 5. Recent attendance
        $recentAttendance = \App\Models\AgentActivity::where('agent_id', $agentId)
            ->whereIn('type', ['check_in', 'check_out'])
            ->where('action_at', '>=', now()->subHours(12))
            ->latest('action_at')
            ->limit(2)
            ->get();

        foreach ($recentAttendance as $attendance) {
            $notificationIds[] = [
                'notification_type' => $attendance->type === 'check_in' ? 'check_in_success' : 'check_out_success',
                'notification_id' => 'attendance_' . $attendance->id,
            ];
        }

        // Mark all as read
        foreach ($notificationIds as $notification) {
            \App\Models\AgentNotification::updateOrCreate(
                [
                    'agent_id' => $agentId,
                    'notification_type' => $notification['notification_type'],
                    'notification_id' => $notification['notification_id'],
                ],
                [
                    'read_at' => now(),
                ]
            );
        }

        // Mark ALL stored notification types as read (broadcast + push notifications)
        \App\Models\AgentNotification::where('agent_id', $agentId)
            ->whereIn('notification_type', [
                'broadcast',
                'followup_reminder',
                'appointment_visit_reminder',
                'unresolved_cases_alert',
                'case_assigned',
            ])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'count' => count($notificationIds),
        ]);
    }

    /**
     * Clear a specific notification by ID
     * - Deletes a single notification by notification_id and type
     * POST /api/agent/notifications/clear/{notification_id}
     */
    public function clearNotification(Request $request, $notificationId)
    {
        $request->validate([
            'notification_type' => 'required|string',
        ]);

        $agentId = Auth::user()->id;
        $notificationType = $request->notification_type;

        // Delete the specific notification
        $deleted = \App\Models\AgentNotification::where('agent_id', $agentId)
            ->where('notification_type', $notificationType)
            ->where('notification_id', $notificationId)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification cleared successfully',
            'notification_id' => $notificationId,
            'notification_type' => $notificationType,
        ]);
    }

    /**
     * Clear all READ notifications
     * - Deletes read broadcast notifications (they won't come back)
     * - Deletes tracking records for system notifications (they'll show as unread again)
     * POST /api/agent/notifications/clear-all
     */
    public function clearAllNotifications(Request $request)
    {
        $agentId = Auth::user()->id;

        // Delete all read notifications (broadcast + system tracking records)
        $deletedCount = \App\Models\AgentNotification::where('agent_id', $agentId)
            ->whereNotNull('read_at') // Only delete read notifications
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'All read notifications cleared successfully',
            'deleted_count' => $deletedCount,
        ]);
    }

}
