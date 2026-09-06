<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\LoanAccount;
use App\Models\DisbursementDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Emi;
use App\Services\EmiCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Exception;
use App\Events\GenerateDocument;
use App\Models\ClientLoanDocument;
use App\Models\LoanProduct;
use App\Services\LoanDocumentEmailService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\PaymentGateway;

class LoanApplicationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:loan-application.approve')->only(['approve']);
        $this->middleware('permission:loan-application.reject')->only(['reject']);
        $this->middleware('permission:loan-application.disbursement')->only(['disburse']);
    }

    public function index()
    {
        $baseQuery = LoanApplication::query();
        if (auth()->user()->hasRole('Agent')) {
            $agentId = optional(auth()->user()->agent)->id;
            if ($agentId) {
                $baseQuery->whereHas('client', function($q) use ($agentId) {
                    $q->where('added_by', $agentId)
                      ->orWhere('assigned_to', $agentId);
                });
            }
        }

        $statusCounts = (clone $baseQuery)->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'pending' => $statusCounts['pending'] ?? 0,
            'process' => ($statusCounts['process'] ?? 0) + ($statusCounts['in_progress'] ?? 0),
            'disbursed' => $statusCounts['disbursed'] ?? 0,
            'rejected' => $statusCounts['rejected'] ?? 0,
        ];

        $verifiedClientsQuery = Client::whereHas('kycDetail', function($q) {
            $q->where('status', 'verified');
        });

        if (auth()->user()->hasRole('Agent')) {
            $agentId = optional(auth()->user()->agent)->id;
            if ($agentId) {
                $verifiedClientsQuery->where(function($q) use ($agentId) {
                    $q->where('added_by', $agentId)
                      ->orWhere('assigned_to', $agentId);
                });
            }
        }
        
        $verifiedClients = $verifiedClientsQuery->get();
        
        $loanProducts = LoanProduct::all();
        
        $activePaymentMethods = PaymentMethod::where('is_enabled', true)->get();
        $activeGateways = PaymentGateway::where('enabled', true)->get();

        return view('admin.loan-management.loan-applications', [
            'stats' => $stats,
            'total_applications' => $stats['total'],
            'pendingApplications' => $stats['pending'],
            'processApplications' => $stats['process'],
            'disbursed_applications' => $stats['disbursed'],
            'rejected_applications' => $stats['rejected'],
            'verifiedClients' => $verifiedClients,
            'loanProducts' => $loanProducts,
            'activePaymentMethods' => $activePaymentMethods,
            'activeGateways' => $activeGateways
        ]);
    }

    public function view(LoanApplication $application)
    {
        $application->load(['client', 'product.loanType', 'loanAccount', 'disbursementDetail', 'applicationDetail']);

        // Agent guard: agents can only view applications for clients they added
        if (auth()->user()->hasRole('Agent')) {
            $agentId = optional(auth()->user()->agent)->id;
            $client = $application->client;
            if (!$agentId || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
                abort(403, 'You do not have permission to view this application.');
            }
        }

        return view('admin.loan-management.loan-application-view', compact('application'));
    }

    public function approve(Request $request, LoanApplication $application): JsonResponse
    {
        $application->load('product');
        $client = $application->client;

        $product = optional($application->product);
        $minAmount = $product->loan_amount_min ?? 0;
        $creditLimit = $product->loan_amount_max ?? 0;
        $productMinTenure = $product->min_tenture ?? 1;
        $productMaxTenure = $product->max_tenture ?? 1;
        $productInterestRate = $product->interest_rate ?? 0;

        $request->validate([
            'approved_amount' => [
                'required',
                'numeric',
                'min:' . $minAmount,
                'max:' . $creditLimit
            ],
            'approved_tenure_min' => [
                'required',
                'integer',
                'min:' . $productMinTenure,
                'max:' . $productMaxTenure,
            ],
            'approved_tenure_max' => [
                'required',
                'integer',
                'min:' . $productMinTenure,
                'max:' . $productMaxTenure,
                'gte:approved_tenure_min',
            ],
            'interest_rate' => [
                'required',
                'numeric',
                'min:' . $productInterestRate,
                'max:' . $productInterestRate,
            ],
        ], [
            'approved_amount.max' => 'Approved amount cannot exceed the credit limit of ₹' . number_format($creditLimit, 0),
            'approved_amount.min' => 'Approved amount must be at least ₹' . number_format($minAmount, 0),
            'approved_tenure_min.min' => 'Minimum tenure must be at least ' . $productMinTenure . ' ' . Str::plural($product->term_unit ?? 'month', $productMinTenure) . '.',
            'approved_tenure_max.max' => 'Maximum tenure cannot exceed ' . $productMaxTenure . ' ' . Str::plural($product->term_unit ?? 'month', $productMaxTenure) . '.',
            'approved_tenure_max.gte' => 'Maximum tenure must be greater than or equal to minimum tenure.',
            'interest_rate.min' => 'Interest rate must match the product rate of ' . number_format($productInterestRate, 2) . '% p.a.',
            'interest_rate.max' => 'Interest rate must match the product rate of ' . number_format($productInterestRate, 2) . '% p.a.',
        ]);

        DB::beginTransaction();

            try {
                $application->update([
                    'loan_amount' => $request->approved_amount,
                    'tenure' => $request->approved_tenure_max, // Set default tenure to max during approval
                    'tenure_min' => $request->approved_tenure_min,
                    'tenure_max' => $request->approved_tenure_max,
                    'interest_rate' => $request->interest_rate,
                    'term_unit' => optional($application->product)->term_unit ?? 'months',
                    'status' => 'approved',
                    'approved_at' => now(),
                ]);

                event(new \App\Events\LoanApplicationApproved($client, $application));

                // Send Approval SMS
                try {
                    if ($client && !empty($client->client_phone)) {
                        \App\Utils\SMSUtility::loanApproved(
                            $client->client_phone,
                            $client->client_name,
                            $application->application_number,
                            $application->loan_amount
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Loan approval SMS failed: ' . $e->getMessage());
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Loan application approved successfully'
                ]);

            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Approval failed: '.$e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong'
                ], 500);
            }
    }

    public function reject(Request $request, LoanApplication $application): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $client = $application->client;

        $application->update([
            'status' => 'rejected',
            'remarks' => $request->input('reason'),
        ]);

        event(new \App\Events\LoanApplicationRejected($client, $application));

        return response()->json([
            'success' => true,
            'message' => 'Loan application rejected'
        ]);
    }

    public function adminProceed(Request $request, LoanApplication $application): JsonResponse
    {
        // Guard: Prevent processing for deleted clients
        if (!$application->client || $application->client->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot process application: The client has been deleted.'
            ], 403);
        }

        $request->validate([
            'approved_amount' => 'required|numeric|min:1',
            'interest_rate' => 'required|numeric',
            'tenure' => 'required|integer',
            'emi_day' => 'required|integer|min:1|max:31',
            'emi_start_date' => 'nullable|date',
            'live_photo' => 'nullable|string', // Base64
            'cash_photo' => 'nullable|string', // Base64
            'terms_accepted' => 'required|accepted',
        ], [
            'terms_accepted.accepted' => 'You must accept the Terms and Conditions for safety.',
        ]);

        if (empty($request->live_photo) && empty($request->cash_photo)) {
            return response()->json([
                'success' => false,
                'message' => 'At least one verification photo (Live or Cash Proof) is required.'
            ], 422);
        }


        try {
            DB::beginTransaction();

            $updateData = [
                'loan_amount' => $request->approved_amount, // Finalize amount here if skipped Step 1
                'interest_rate' => $request->interest_rate, // Finalize rate here
                'tenure' => $request->tenure,
                'term_unit' => $application->term_unit ?: (optional($application->product)->term_unit ?? 'months'),
                'emi_day' => $request->emi_day,
                'payment_method' => 'manual',
                'status' => 'in_progress',
            ];

            if ($request->filled('emi_start_date')) {
                $emiStartDate = Carbon::parse($request->emi_start_date);
                $updateData['emi_start_year'] = $emiStartDate->year;
                $updateData['emi_start_month'] = $emiStartDate->month;
                $updateData['emi_start_day'] = $emiStartDate->day;
                
                // Recalculate emi_day from date to ensure sync
                $termUnit = strtolower($application->term_unit ?: optional($application->product)->term_unit ?: 'months');
                if (in_array($termUnit, ['weeks', 'week', 'weekly'])) {
                    $updateData['emi_day'] = $emiStartDate->dayOfWeekIso;
                } elseif (in_array($termUnit, ['months', 'month', 'monthly'])) {
                    $updateData['emi_day'] = $emiStartDate->day;
                }
            }

            // If it was pending, mark it as approved-at now as we are proceeding
            if ($application->status === 'pending') {
                $updateData['approved_at'] = now();
            }

            // Save live photo
            $liveImageData = $request->live_photo;
            if ($liveImageData && preg_match('/^data:image\/(\w+);base64,/', $liveImageData, $type)) {
                $liveImageData = substr($liveImageData, strpos($liveImageData, ',') + 1);
                $type = strtolower($type[1]);
                $liveImageData = base64_decode($liveImageData);
                $fileName = 'loan_live_photo_' . $application->id . '_' . time() . '.' . $type;
                $filePath = 'loan_applications/live_photos/' . $fileName;
                Storage::disk('public')->put($filePath, $liveImageData);
                $updateData['live_photo'] = $filePath;
            }

            // Save cash photo
            $cashImageData = $request->cash_photo;
            if ($cashImageData && preg_match('/^data:image\/(\w+);base64,/', $cashImageData, $type)) {
                $cashImageData = substr($cashImageData, strpos($cashImageData, ',') + 1);
                $type = strtolower($type[1]);
                $cashImageData = base64_decode($cashImageData);
                $fileName = 'loan_cash_photo_' . $application->id . '_' . time() . '.' . $type;
                $filePath = 'loan_applications/cash_photos/' . $fileName;
                Storage::disk('public')->put($filePath, $cashImageData);
                $updateData['cash_photo'] = $filePath;
            }

            $application->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Loan application proceeded with cash verification.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin Proceed failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to proceed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function disburse(Request $request, LoanApplication $application): JsonResponse
    {
        // Guard: Prevent disbursement for deleted clients
        if (!$application->client || $application->client->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot disburse loan: The client has been deleted.'
            ], 403);
        }

        try {
            // Validate grace period, penalty, and bank account data
            // For manual/cash loans, these are simplified
            $validated = $request->validate([
                'grace_period_days' => 'nullable|integer|min:0|max:30',
                'penalty' => 'nullable|numeric|min:0',
                'penalty_type' => 'nullable|in:percentage,rupees',
                'disbursement_reference' => 'nullable|string|max:150',
                'processing_fee' => 'nullable|numeric|min:0',
                'document_charges' => 'nullable|numeric|min:0',
                'other_charges' => 'nullable|numeric|min:0',
                'bank_name' => 'nullable|string|max:100',
                'account_number' => 'nullable|string|max:50',
                'holder_name' => 'nullable|string|max:100',
                'account_type' => 'nullable|string|max:50',
                'ifsc_code' => 'nullable|string|max:20',
                'utr_number' => 'nullable|string|max:100',
                'disbursed_at' => 'nullable|date',
                'emi_start_date' => 'nullable|date',
            ]);

            // Set default values if not provided
            $product = $application->product;
            $gracePeriodDays = $validated['grace_period_days'] ?? (optional($product)->grace_period_days ?? 0);
            $penalty = $validated['penalty'] ?? (optional($product)->penalty_rate ?? 0);
            $penaltyType = $validated['penalty_type'] ?? 'percentage';
            $transactionId = $validated['disbursement_reference'] ?? 'MANUAL-' . time();
            $utrNumber = $validated['utr_number'] ?? ($validated['disbursement_reference'] ?? 'CASH-' . time());
            $bankName = $validated['bank_name'] ?? 'CASH';
            $accountNumber = $validated['account_number'] ?? 'OFFLINE';
            $holderName = $validated['holder_name'] ?? $application->client->client_name;
            $accountType = $validated['account_type'] ?? 'savings';
            $ifscCode = $validated['ifsc_code'] ?? 'N/A';
            $disburseAt = $validated['disbursed_at'] ? Carbon::parse($validated['disbursed_at']) : now();

            DB::beginTransaction();

            $client = $application->client;

            // Update application status
            $application->update([
                'status' => 'disbursed',
                'disbursed_at' => $disburseAt,
            ]);

            $principal = $application->loan_amount;
            $tenure = (int)($application->tenure ?? 0);
            $annualRate = (float)($application->interest_rate ?? 0);
            $isInterestOnly = ($application->loan_mode ?? 'emi') === 'interest_only';

            if ($annualRate <= 0 || (!$isInterestOnly && $tenure <= 0)) {
                throw new \Exception('Invalid loan configuration: interest rate or tenure missing');
            }

            $emiService = new EmiCalculator();
            $product = $application->product;
            
            // Map term_unit to frequency
            $frequency = 'monthly';
            // Prioritize term_unit from application, fallback to product
            $rawTermUnit = $application->term_unit ?: ($product ? $product->term_unit : 'monthly');
            $termUnit = strtolower((string) $rawTermUnit);
            
            if (in_array($termUnit, ['week', 'weeks', 'weekly'], true)) {
                $frequency = 'weekly';
            } elseif (in_array($termUnit, ['day', 'days', 'daily'], true)) {
                $frequency = 'daily';
            }

            // Prioritize the EMI day from the application, fallback to client's preferred collection day
            $emiDay = $application->emi_day;
            if (!$emiDay && $frequency === 'weekly' && $client && $client->collection_day) {
                // Convert day name to 1-7 (Mon-Sun)
                $dayMap = [
                    'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4,
                    'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7
                ];
                $emiDay = $dayMap[$client->collection_day] ?? $emiDay;
            }

            // Update EMI Start Date if provided
            if ($request->filled('emi_start_date')) {
                $emiStartDate = Carbon::parse($request->emi_start_date);
                
                // Sync emi_day with the selected start date
                $newEmiDay = $application->emi_day;
                if ($frequency === 'weekly') {
                    $newEmiDay = $emiStartDate->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
                } elseif ($frequency === 'monthly') {
                    $newEmiDay = $emiStartDate->day; // 1 to 31
                }

                $application->update([
                    'emi_start_year' => $emiStartDate->year,
                    'emi_start_month' => $emiStartDate->month,
                    'emi_start_day' => $emiStartDate->day,
                    'emi_day' => $newEmiDay
                ]);

                // Also update local variable for schedule generation
                $emiDay = $newEmiDay;
            }

            // Calculate EMI Start Date based on configuration
            $startDateObj = null;
            if ($application->emi_start_year && $application->emi_start_month && $application->emi_start_day) {
                $startDateObj = Carbon::create($application->emi_start_year, $application->emi_start_month, $application->emi_start_day);
                
                // Subtract 1 period ONLY for standard EMI because generateSchedule adds 1 period for the first EMI.
                // For Kandhuvatti (interest_only), the loop uses the date directly without adding for i=1.
                if (!$isInterestOnly) {
                    if ($frequency === 'daily') {
                        $startDateObj->subDay();
                    } elseif ($frequency === 'weekly') {
                        $startDateObj->subWeek();
                    } else {
                        $startDateObj->subMonth();
                    }
                }
            }

            $scheduleData = [];
            $emi = 0;
            $totalPayable = $principal;

            if (!$isInterestOnly) {
                $scheduleData = $emiService->generateSchedule(
                    principal: $principal,
                    annualRate: $annualRate,
                    term: $tenure,
                    startDate: $startDateObj ? $startDateObj->format('Y-m-d') : ($application->disbursed_at ? $application->disbursed_at->format('Y-m-d') : null),
                    emiDay: $emiDay,
                    frequency: $frequency,
                    interestType: $product ? ($product->interest_type ?? 'flat') : 'flat'
                );

                $emi = $scheduleData['emi'];
                $totalPayable = $scheduleData['total_payment'];
            }

            // Calculate net disbursed amount (loan amount minus all charges)
            $product = $application->product;
            $processingFee = $request->has('processing_fee') ? (float)$request->processing_fee : (optional($product)->processing_fee ?? 0);
            $documentCharges = $request->has('document_charges') ? (float)$request->document_charges : (optional($product)->document_charges ?? 0);
            $otherCharges = $request->has('other_charges') ? (float)$request->other_charges : (optional($product)->other_charges ?? 0);
            $totalCharges = $processingFee + $documentCharges + $otherCharges;
            $netDisbursedAmount = max($principal - $totalCharges, 0);

            // Save applied charges to application details
            $applicationDetail = $application->applicationDetail ?? new \App\Models\LoanApplicationDetail(['loan_application_id' => $application->id]);
            $details = $applicationDetail->details ?? [];
            $details['applied_processing_fee'] = $processingFee;
            $details['applied_document_charges'] = $documentCharges;
            $details['applied_other_charges'] = $otherCharges;
            $applicationDetail->details = $details;
            $applicationDetail->save();


            // Create Loan Account
            
            // For Kandhuvatti, total payable is just the principal initially (interest is dynamic)
            $initialTotalPayable = $isInterestOnly ? $principal : $totalPayable;

            $account = LoanAccount::create([
                'loan_application_id' => $application->id,
                'client_id' => $application->client_id,
                'application_number' => $application->application_number,
                'loan_code' => $application->loan_code,
                'loan_mode' => $application->loan_mode ?? 'emi',
                'loan_amount' => $principal,
                'disbursed_amount' => $netDisbursedAmount,
                'interest_rate' => $application->interest_rate,
                'tenure' => $tenure,
                'emi_amount' => $isInterestOnly ? 0 : $emi,
                'emi_day' => $application->emi_day,
                'payment_method' => $application->payment_method,
                'total_payable' => $initialTotalPayable,
                'paid_amount' => 0,
                'outstanding_amount' => $initialTotalPayable,
                'grace_period_days' => $gracePeriodDays,
                'penalty' => $penalty,
                'penalty_type' => $penaltyType,
                'transaction_id' => $transactionId,
                'utr_number' => $utrNumber,
                'disbursed_at' => $application->disbursed_at,
                'status' => 'active',
            ]);


            // Create disbursement detail record
            DisbursementDetail::create([
                'loan_application_id' => $application->id,
                'transaction_id' => $transactionId,
                'utr_number' => $utrNumber,
                'bank_name' => $bankName,
                'bank_account_number' => $accountNumber,
                'holder_name' => $holderName,
                'account_type' => $accountType,
                'ifsc_code' => $ifscCode,
                'disbursement_amount' => $netDisbursedAmount,
                'disburse_at' => $disburseAt,
            ]);

            // Save user-uploaded loan agreement PDF to client_loan_documents
            if ($application->loan_agreement_pdf) {
                ClientLoanDocument::create([
                    'loan_account_id' => $account->id,
                    'client_id' => $application->client_id,
                    'document_type' => 'Loan_agreement',
                    'document_title' => 'Loan Agreement Document',
                    'file_path' => $application->loan_agreement_pdf,
                    'file_name' => basename($application->loan_agreement_pdf),
                    'file_size' => 0,
                    'generated_at' => now(),
                    'generated_by' => auth()->id(),
                ]);
            }

            // Note: Loan sanction letter and repayment schedule are auto-generated by GenerateDocument event

            if ($application->loan_mode === 'interest_only') {
                // For Kandhuvatti, we create 12 initial interest cycles (as requested)
                $exactInterest = $principal * ($application->interest_rate / 100);
                $interestPerCycle = round($exactInterest);
                $totalInterest = round($exactInterest * 12);
                
                // For open loans, use the selected EMI start date if set; fallback to 1 period after disbursement date
                $currentDate = null;
                if ($application->emi_start_year && $application->emi_start_month && $application->emi_start_day) {
                    $currentDate = Carbon::create($application->emi_start_year, $application->emi_start_month, $application->emi_start_day);
                } else {
                    $currentDate = $disburseAt->copy();
                    if ($frequency === 'daily') {
                        $currentDate->addDay();
                    } elseif ($frequency === 'weekly') {
                        $currentDate->addWeek();
                    } else {
                        $currentDate->addMonth();
                    }
                }
                
                for ($i = 1; $i <= 12; $i++) {
                    // Adjust date for each instalment (1st one is on start date, then add frequency)
                    if ($i > 1) {
                        if ($frequency === 'daily') {
                            $currentDate->addDay();
                        } elseif ($frequency === 'weekly') {
                            $currentDate->addWeek();
                        } else {
                            $currentDate->addMonth();
                        }
                    }

                    // Deduct cumulative difference from the 12th (last) EMI
                    $currentInterest = ($i === 12) ? ($totalInterest - ($interestPerCycle * 11)) : $interestPerCycle;

                    $emi = Emi::create([
                        'loan_account_id'    => $account->id,
                        'instalment_number'  => $i,
                        'principal_amount'   => 0,
                        'interest_amount'    => $currentInterest,
                        'total_amount'       => $currentInterest,
                        'due_date'           => $currentDate->format('Y-m-d'),
                        'previous_balance'   => 0,
                        'total_due'          => $currentInterest,
                        'pending_amount'     => $currentInterest,
                        'paid_amount'        => 0,
                        'status'             => 'pending',
                    ]);

                    if ($client->assigned_to) {
                        \App\Models\EmiAgentAssignment::updateOrCreate(
                            ['emi_id' => $emi->id],
                            [
                                'agent_id' => $client->assigned_to,
                                'status' => 'assigned',
                                'assigned_at' => now(),
                                'remarks' => 'Auto-assigned upon loan disbursement'
                            ]
                        );
                    }
                }
            } else {
                foreach ($scheduleData['schedule'] as $item) {

                    $totalAmount = $item['emi_amount'];

                    $emi = Emi::create([
                        'loan_account_id'    => $account->id,
                        'instalment_number'  => $item['month'],

                        'principal_amount'  => $item['principal'],
                        'interest_amount'   => $item['interest'],
                        'total_amount'      => $totalAmount,

                        'due_date'           => $item['due_date'],

                        'opening_balance'    => $item['opening_balance'] ?? 0,
                        'closing_balance'    => $item['closing_balance'] ?? 0,

                        'previous_balance'   => 0,
                        'total_due'          => $totalAmount,
                        'pending_amount'     => $totalAmount,
                        'paid_amount'        => 0,

                        'status'             => 'pending',
                        'is_partial_paid'    => false,
                    ]);

                    // Auto-assign to agent if client has one assigned
                    if ($client->assigned_to) {
                        \App\Models\EmiAgentAssignment::updateOrCreate(
                            ['emi_id' => $emi->id],
                            [
                                'agent_id' => $client->assigned_to,
                                'status' => 'assigned',
                                'assigned_at' => now(),
                                'remarks' => 'Auto-assigned upon loan disbursement'
                            ]
                        );
                    }
                }
            }

            event(new GenerateDocument($account));

            DB::commit();

            try {
                event(new \App\Events\LoanDisbursement($client, $application));
                
                // Send Activation SMS
                if ($client && !empty($client->client_phone)) {
                    \App\Utils\SMSUtility::loanActivated(
                        $client->client_phone,
                        $client->client_name,
                        $application->application_number, // or account number
                        $netDisbursedAmount
                    );
                }
            } catch (\Throwable $e) {
                Log::error('LoanDisbursement notifications failed: ' . $e->getMessage());
                // Don't fail the request since disbursement is already committed
            }

            // Auto-send loan documents email
            try {
                $emailService = app(LoanDocumentEmailService::class);
                $emailResult = $emailService->sendLoanDocumentsEmail($account->id);

                if ($emailResult['success']) {
                    Log::info('Loan documents email sent after disbursement', [
                        'loan_account_id' => $account->id,
                        'documents_sent' => $emailResult['documents_sent'] ?? 0
                    ]);
                } else {
                    Log::warning('Failed to send loan documents email after disbursement', [
                        'loan_account_id' => $account->id,
                        'reason' => $emailResult['message']
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Loan documents email sending failed: ' . $e->getMessage());
                // Don't fail the request since disbursement is already committed
            }

            return response()->json([
                'success' => true,
                'message' => 'Loan disbursed successfully with EMI schedule',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Disbursement failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Disbursement failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function data(Request $request): JsonResponse
    {
        $statusColors = [
            'pending' => 'warning',
            'approved' => 'success',
            'process' => 'primary',
            'in_progress' => 'primary',
            'rejected' => 'danger',
            'disbursed' => 'success',
        ];

        $columns = [
            0 => 'id',
            1 => 'application_number',
            2 => 'id', // Borrower name (sorting on ID as fallback or need join)
            3 => 'id', // Borrower phone
            4 => 'id', // Zone
            5 => 'loan_code',
            6 => 'loan_amount',
            7 => 'status',
        ];

        $query = LoanApplication::with(['client.location', 'product']);

        if (auth()->user()->hasRole('Agent')) {
            $agentId = optional(auth()->user()->agent)->id;
            if ($agentId) {
                $query->whereHas('client', function($q) use ($agentId) {
                    $q->where('added_by', $agentId)
                      ->orWhere('assigned_to', $agentId);
                });
            }
        }

        // Total records after role filtering
        $totalData = (clone $query)->count();

        // DataTables parameters
        $limit = $request->input('length', 10);
        $start = $request->input('start', 0);
        $orderIndex = $request->input('order.0.column', 1);
        $order = $columns[$orderIndex] ?? 'id';
        $dir = $request->input('order.0.dir', 'desc');

        // Prevent invalid column ordering
        if (!in_array($order, ['id', 'application_number', 'loan_code', 'loan_amount', 'status'])) {
            $order = 'id';
        }

        // Build query
        $query->with([
            'product:id,loan_code,loan_name,loan_amount_max', 
            'client:id,client_name,client_phone,location_id,added_by',
            'client.location:id,name',
            'loanAccount:id,loan_application_id'
        ]);

        // Date range filtering
        if ($request->has('from_date') && !empty($request->from_date)) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && !empty($request->to_date)) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->has('status') && !empty($request->status)) {
            if ($request->status === 'process' || $request->status === 'in_progress') {
                $query->where('status', 'process');
            } else {
                $query->where('status', $request->status);
            }
        }

        // Search handling
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('application_number', 'LIKE', "%{$search}%")
                  ->orWhere('loan_amount', 'LIKE', "%{$search}%")
                  ->orWhere('status', 'LIKE', "%{$search}%")
                  ->orWhereHas('client', function($q) use ($search) {
                      $q->where('client_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('product', function($q) use ($search) {
                      $q->where('loan_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $totalFiltered = $query->count();

        // Apply pagination and ordering
        $applications = $query->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get()
            ->map(function (LoanApplication $application) use ($statusColors) {
                // Determine which amount to show based on status
                // - disbursed: show approved/disbursed loan amount
                // - others: show requested loan amount (or finalized amount if available)
                $amountValue = $application->loan_amount;

                return [
                'id' => $application->getRouteKey(),
                    'application_number' => $application->application_number,
                    'loan_name' => optional($application->product)->loan_name ?? 'N/A',
                    'loan_amount' => $amountValue && $amountValue > 0
                        ? '₹' . number_format($amountValue, 0)
                        : 'N/A',
                    'loan_amount_formatted' => $amountValue && $amountValue > 0
                        ? '₹' . number_format($amountValue, 0)
                        : 'N/A',
                    'borrower_name' => optional($application->client)->client_name ?? 'N/A',
                    'borrower_phone' => optional($application->client)->client_phone ?? 'N/A',
                    'zone' => optional(optional($application->client)->location)->name ?? 'N/A',
                    'client_id' => $application->client_id,
                    'status' => $application->status,
                    'status_label' => match ($application->status) {
                        'process', 'in_progress' => 'In Progress',
                        'pending' => 'Pending Approval',
                        default => ucfirst($application->status),
                    },
                    'status_color' => $statusColors[$application->status] ?? 'secondary',
                    'loan_account_id' => optional($application->loanAccount)->id,
                ];
            });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $applications,
        ]);
    }

    /**
     * Preview EMI calculation based on inputs
     */
    public function previewEmi(Request $request): JsonResponse
    {
        $principal = (float) $request->input('amount', 0);
        $annualRate = (float) $request->input('rate', 0);
        $tenure = (int) $request->input('tenure', 0);
        $frequency = $request->input('frequency', 'monthly');
        $interestType = $request->input('interest_type', 'flat');

        if ($principal <= 0 || $annualRate <= 0 || $tenure <= 0) {
            return response()->json([
                'emi' => 0,
                'total_interest' => 0,
                'total_payable' => 0
            ]);
        }

        $emiService = new EmiCalculator();
        $result = $emiService->generateSchedule(
            principal: $principal, 
            annualRate: $annualRate, 
            term: $tenure, 
            frequency: $frequency,
            interestType: $interestType
        );

        return response()->json([
            'emi' => $result['emi'],
            'total_interest' => $result['total_interest'],
            'total_payable' => $result['total_payment']
        ]);
    }

    /**
     * Store a quick loan application from the client view
     */
    public function storeQuickApplication(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'loan_code' => 'required|exists:loan_products,loan_code',
            'loan_amount' => 'required|numeric|min:1',
            'loan_mode' => 'nullable|in:emi,interest_only',
            'tenure' => 'required_if:loan_mode,emi|nullable|integer|min:0',
            'repayment_frequency' => 'required|in:daily,weekly,monthly',
            'emi_day' => 'required|integer|min:1',
            'emi_start_date' => 'required|date',
            'payment_method' => 'required|in:manual,e-nach',
            'payment_gateway' => 'required_if:payment_method,e-nach|nullable|in:razorpay,cashfree,payu',
        ]);

        // Eligibility Check: Block if client has a pending loan
        $existingLoan = LoanApplication::where('client_id', $validated['client_id'])
            ->whereIn('status', ['pending', 'approved', 'process', 'in_progress'])
            ->exists();

        if ($existingLoan) {
            return response()->json([
                'success' => false,
                'message' => 'This client already has a pending loan application. Please resolve or close the existing application before applying for a new one.'
            ], 422);
        }

        // Agent guard: agents can only apply for clients they added
        if (auth()->user()->hasRole('Agent')) {
            $agentId = optional(auth()->user()->agent)->id;
            $client = Client::find($validated['client_id']);
            if (!$agentId || !$client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only apply for loans for clients you have added or are assigned to you.'
                ], 403);
            }
        }

        try {
            $product = LoanProduct::where('loan_code', $validated['loan_code'])->firstOrFail();
            
            DB::beginTransaction();

            // Frequency specific emi_day adjustment/default
            $emiDay = (int)$validated['emi_day'];
            if ($validated['repayment_frequency'] === 'daily') {
                $emiDay = 1; // Default for daily
            }

            $emiStartDate = Carbon::parse($validated['emi_start_date']);

            $loanMode = $validated['loan_mode'] ?? 'emi';
            if ($product->interest_type === 'reducing' || $product->interest_type === 'declining_balance') {
                $loanMode = 'emi';
            }

            $application = LoanApplication::create([
                'client_id' => $validated['client_id'],
                'loan_code' => $validated['loan_code'],
                'loan_mode' => $loanMode,
                'loan_amount' => $validated['loan_amount'],
                'tenure' => $loanMode === 'interest_only' ? 0 : $validated['tenure'],
                'term_unit' => $validated['repayment_frequency'],
                'interest_rate' => $product->interest_rate,
                'status' => 'pending',
                'emi_day' => $emiDay,
                'emi_start_year' => $emiStartDate->year,
                'emi_start_month' => $emiStartDate->month,
                'emi_start_day' => $emiStartDate->day,
                'payment_method' => $validated['payment_method'],
                'payment_gateway' => $validated['payment_method'] === 'e-nach' ? $validated['payment_gateway'] : null,
            ]);

            DB::commit();

            // Send SMS Notification
            try {
                $client = $application->client;
                if ($client && !empty($client->client_phone)) {
                    \App\Utils\SMSUtility::loanSubmitted(
                        $client->client_phone,
                        $client->client_name,
                        $application->application_number,
                        $application->loan_amount
                    );
                }
            } catch (\Exception $e) {
                Log::error('Loan submission SMS failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Loan application submitted successfully!',
                'redirect' => route('loan-application-view', $application->getRouteKey())
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Quick application failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check loan eligibility for a client (AJAX endpoint)
     */
    public function checkLoanEligibility(Request $request): JsonResponse
    {
        $request->validate(['client_id' => 'required|exists:clients,id']);

        $clientId = $request->input('client_id');

        $pendingApplication = LoanApplication::where('client_id', $clientId)
            ->whereIn('status', ['pending', 'approved', 'process', 'in_progress'])
            ->first();

        if ($pendingApplication) {
            $reason = "Client has a {$pendingApplication->status} loan application (#{$pendingApplication->application_number}).";

            return response()->json([
                'eligible' => false,
                'message' => $reason
            ]);
        }

        return response()->json([
            'eligible' => true,
            'message' => 'Client is eligible for a new loan.'
        ]);
    }

    /**
     * Delete a loan application (Admin only)
     */
    public function destroy(LoanApplication $application): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin') && !auth()->user()->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can delete loan applications.'
            ], 403);
        }



        try {
            DB::beginTransaction();

            // Delete associated details first
            $application->applicationDetail()->delete();
            $application->disbursementDetail()->delete();
            // Delete associated loan account if it exists
            if ($application->loanAccount()->exists()) {
                $application->loanAccount()->delete();
            }
            // Delete the application itself
            $application->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Loan application deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Loan application deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete loan application: ' . $e->getMessage()
            ], 500);
        }
    }
}
