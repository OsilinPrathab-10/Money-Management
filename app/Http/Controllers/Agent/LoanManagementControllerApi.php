<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanType;
use App\Models\PaymentMethod;
use App\Models\PaymentGateway;
use App\Models\Client;
use App\Models\LoanAccount;
use App\Services\EmiCalculator;
use App\Services\LoanDocumentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanManagementControllerApi extends Controller
{
    /**
     * Get metadata for loan application form
     */
    public function metadata(): JsonResponse
    {
        $loanTypes = LoanType::with(['products'])->get();
        $loanProducts = LoanProduct::all(['id', 'loan_type_id', 'loan_name', 'loan_code', 'interest_rate', 'interest_type', 'loan_amount_min', 'loan_amount_max', 'min_tenture', 'max_tenture', 'term_unit', 'status']);
        $activePaymentMethods = PaymentMethod::where('is_enabled', true)->get(['id', 'name']);
        $activeGateways = PaymentGateway::where('enabled', true)->get(['id', 'name']);

        $agentId = auth()->user()->id;

        $verifiedClients = Client::whereHas('kycDetail', function($q) {
            $q->where('status', 'verified');
        })->where('assigned_to', $agentId)->get(['id', 'client_name', 'client_phone', 'client_email']);

        return response()->json([
            'success' => true,
            'data' => [
                'loan_types' => $loanTypes,
                'loan_products' => $loanProducts,
                'payment_methods' => $activePaymentMethods,
                'payment_gateways' => $activeGateways,
                'verified_clients' => $verifiedClients
            ]
        ]);
    }

    /**
     * List loan applications for the authenticated agent
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = auth()->user()->id;

        $query = LoanApplication::with(['client.location', 'product'])
            ->whereHas('client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            });

        // Date range filtering
        if ($request->has('from_date') && !empty($request->from_date)) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && !empty($request->to_date)) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->has('status') && !empty($request->status)) {
            if ($request->status === 'process' || $request->status === 'in_progress') {
                $query->whereIn('status', ['process', 'in_progress']);
            } else {
                $query->where('status', $request->status);
            }
        }

        // Search handling
        if (!empty($request->input('search'))) {
            $search = $request->input('search');

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

        // Status Statistics
        $baseQuery = LoanApplication::whereHas('client', function($q) use ($agentId) {
            $q->where('assigned_to', $agentId);
        });

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

        $applications = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $applications,
            'stats' => $stats
        ]);
    }

    /**
     * Show a specific loan application
     */
    public function show($id): JsonResponse
    {
        $agentId = auth()->user()->id;

        $application = LoanApplication::with(['client', 'product.loanType', 'loanAccount', 'disbursementDetail', 'applicationDetail'])
            ->where('id', $id)
            ->first();

        if (!$application) {
            return response()->json(['success' => false, 'message' => 'Loan application not found.'], 404);
        }

        $client = $application->client;
        if (!$client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to view this application.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * Store a quick loan application from the agent app
     */
    public function storeQuickApplication(Request $request): JsonResponse
    {
        if ($request->has('loan_mode')) {
            $rawMode = strtolower(str_replace([' ', '_', '-'], '', (string) $request->input('loan_mode')));
            if (in_array($rawMode, ['openloan', 'interestonly', 'open', 'kandhuvatti'], true)) {
                $request->merge(['loan_mode' => 'interest_only']);
            } elseif (in_array($rawMode, ['emi'], true)) {
                $request->merge(['loan_mode' => 'emi']);
            }
        }

        $emiDayMax = LoanApplication::maxEmiDayFor(
            $request->input('repayment_frequency'),
            $request->input('emi_start_date')
        );

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'loan_code' => 'required|exists:loan_products,loan_code',
            'loan_amount' => 'required|numeric|min:1',
            'loan_mode' => 'nullable|in:emi,interest_only,OpenLoan,open_loan',
            'tenure' => 'required_if:loan_mode,emi|nullable|integer|min:0',
            'repayment_frequency' => 'required|in:daily,weekly,monthly',
            'emi_day' => 'required|integer|min:1|max:' . $emiDayMax,
            'emi_start_date' => 'required|date',
            'applied_at' => 'nullable|date',
        ], [
            'emi_day.max' => 'EMI day cannot be greater than ' . $emiDayMax . ' for the selected start month.',
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
        $agentId = auth()->user()->id;
        $client = Client::with('kycDetail')->find($validated['client_id']);
        if (!$agentId || !$client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only apply for loans for clients you have added or are assigned to you.'
            ], 403);
        }

        // KYC check: client KYC must be approved/verified
        $kycStatus = optional($client->kycDetail)->status;
        if (!in_array($kycStatus, ['verified', 'approved'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Loan application failed: Customer KYC is not approved yet. Only KYC verified clients can apply for loans.'
            ], 422);
        }

            $emiDay = (int)$validated['emi_day'];
            if ($validated['repayment_frequency'] === 'daily') {
                $emiDay = 1; // Default for daily
            }

            $emiStartDate = Carbon::parse($validated['emi_start_date']);
            if ($validated['repayment_frequency'] === 'monthly' && $emiDay > $emiStartDate->daysInMonth) {
                return response()->json([
                    'success' => false,
                    'message' => 'EMI day cannot be greater than ' . $emiStartDate->daysInMonth . ' for ' . $emiStartDate->format('F Y') . '.',
                ], 422);
            }

        try {
            $product = LoanProduct::where('loan_code', $validated['loan_code'])->firstOrFail();
            
            DB::beginTransaction();

            $loanMode = $validated['loan_mode'] ?? 'emi';
            if ((float) $product->interest_rate === 0.0
                || $product->interest_type === 'reducing'
                || $product->interest_type === 'declining_balance') {
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
                'payment_method' => 'manual',
                'payment_gateway' => null,
                'applied_at' => !empty($validated['applied_at']) ? Carbon::parse($validated['applied_at']) : now(),
            ]);

            DB::commit();

            event(new \App\Events\NewLoanApplicationEvent($application, 'agent'));

            // Send SMS Notification
            try {
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
                'data' => $application
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
     * Check loan eligibility for a client
     */
    public function checkLoanEligibility(Request $request): JsonResponse
    {
        $request->validate(['client_id' => 'required|exists:clients,id']);

        $clientId = $request->input('client_id');
        
        // Ensure agent has permission for this client
        $agentId = auth()->user()->id;
        $client = Client::with('kycDetail')->find($clientId);
        
        if (!$client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission for this client.'], 403);
        }

        $kycStatus = optional($client->kycDetail)->status;
        $kycApproved = in_array($kycStatus, ['verified', 'approved'], true);

        $pendingApplication = LoanApplication::where('client_id', $clientId)
            ->whereIn('status', ['pending', 'approved', 'process', 'in_progress'])
            ->first();

        $eligible = true;
        $message = 'Client is eligible for a new loan.';

        if (!$kycApproved) {
            $eligible = false;
            $message = 'Customer KYC is not approved yet. Only KYC verified clients can apply for loans.';
        } elseif ($pendingApplication) {
            $eligible = false;
            $message = "Client has a {$pendingApplication->status} loan application (#{$pendingApplication->application_number}).";
        }

        $chitDetails = $this->getClientChitDetails($clientId);

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => $eligible,
                'message' => $message,
                'chit_details' => $chitDetails,
            ]
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

        if ($principal <= 0 || $annualRate < 0 || $tenure <= 0) {
            return response()->json([
                'success' => true,
                'data' => [
                    'emi' => 0,
                    'total_interest' => 0,
                    'total_payable' => 0
                ]
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
            'success' => true,
            'data' => [
                'emi' => $result['emi'],
                'total_interest' => $result['total_interest'],
                'total_payable' => $result['total_payment']
            ]
        ]);
    }

    /**
     * Get chit fund details for a client (used during loan application)
     */
    private function getClientChitDetails(int $clientId): array
    {
        $memberships = \App\Models\GroupMember::with(['group.scheme'])
            ->involvingClient($clientId)
            ->whereIn('status', ['active', 'approved', 'applied'])
            ->get();

        if ($memberships->isEmpty()) {
            return ['has_chit' => false];
        }

        $memberIds = $memberships->pluck('id');

        $installments = \App\Models\Installment::whereIn('member_id', $memberIds)->get();

        $today = Carbon::now()->startOfDay();

        $totalChitValue = 0;
        $totalMonthlyInstallment = 0;
        $groups = [];

        foreach ($memberships as $membership) {
            $group = $membership->group;
            if (! $group) {
                continue;
            }

            $groupInstallments = $installments->where('member_id', $membership->id);
            $overdueInstallments = $groupInstallments->filter(fn ($i) => ! in_array($i->status, ['paid', 'waived']) && $i->due_date && $i->due_date->lt($today));
            $overdueAmount = $overdueInstallments->sum(fn ($i) => max(0, (float) $i->amount + (float) $i->penalty_amount - (float) $i->paid_amount));

            $totalChitValue += (float) $group->chit_value;
            $totalMonthlyInstallment += (float) $group->installment_amount;

            $groups[] = [
                'group_code' => $group->group_code,
                'scheme_name' => $group->scheme->name ?? '—',
                'chit_value' => (float) $group->chit_value,
                'installment_amount' => (float) $group->installment_amount,
                'status' => $group->status,
                'total_installments' => $groupInstallments->count(),
                'paid_count' => $groupInstallments->where('status', 'paid')->count(),
                'overdue_count' => $overdueInstallments->count(),
                'overdue_amount' => round($overdueAmount, 2),
                'total_paid' => round($groupInstallments->sum('paid_amount'), 2),
                'total_balance' => round($groupInstallments->sum(fn ($i) => max(0, (float) $i->amount + (float) $i->penalty_amount - (float) $i->paid_amount)), 2),
            ];
        }

        $totalOverdue = collect($groups)->sum('overdue_amount');
        $totalBalance = collect($groups)->sum('total_balance');
        $totalPaid = collect($groups)->sum('total_paid');

        return [
            'has_chit' => true,
            'total_groups' => count($groups),
            'total_chit_value' => round($totalChitValue, 2),
            'total_monthly_installment' => round($totalMonthlyInstallment, 2),
            'total_overdue' => round($totalOverdue, 2),
            'total_balance' => round($totalBalance, 2),
            'total_paid' => round($totalPaid, 2),
            'groups' => $groups,
        ];
    }
    
    /**
     * Scope to agent's assigned/added clients or active assignments.
     */
    private function applyLoanAgentScope(\Illuminate\Database\Eloquent\Builder $query, int $agentId): void
    {
        $query->where(function ($q) use ($agentId) {
            $q->whereHas('emi.loanAccount.client', function ($cq) use ($agentId) {
                $cq->where('assigned_to', $agentId);
            })
            ->orWhereHas('emi.activeAssignment', function ($aq) use ($agentId) {
                $aq->where('agent_id', $agentId);
            });
        });
    }



    /**
     * List loan accounts for the authenticated agent
     */
    public function loanAccounts(Request $request): JsonResponse
    {
        $agentUser = auth()->user();
        $agentId = $agentUser instanceof \App\Models\Agent ? $agentUser->id : optional(optional($agentUser)->agent)->id;
        $userId = $agentUser instanceof \App\Models\Agent ? $agentUser->user_id : optional($agentUser)->id;
        $isAdmin = $agentUser && ($agentUser instanceof \App\Models\User ? ($agentUser->hasRole(['admin', 'super_admin', 'super-admin', 'Super Admin']) || $agentUser->id === 1) : false);

        $query = LoanAccount::with([
            'client.location',
            'loanApplication.product',
            'emis'
        ]);

        if (! $isAdmin) {
            $query->whereHas('client', function($q) use ($agentId, $userId) {
                $q->where('assigned_to', $agentId)
                  ->orWhere('assigned_to', $userId)
                  ->orWhere('added_by', $agentId)
                  ->orWhere('added_by', $userId);
            });
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        if ($request->filled('account_number')) {
            $query->where(function ($q) use ($request) {
                $q->where('account_number', 'LIKE', "%{$request->account_number}%")
                  ->orWhere('customer_loan_account_number', 'LIKE', "%{$request->account_number}%");
            });
        }

        if ($request->has('from_date') && !empty($request->from_date)) {
            $query->whereDate('disbursed_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && !empty($request->to_date)) {
            $query->whereDate('disbursed_at', '<=', $request->to_date);
        }

        if (!empty($request->input('search'))) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('account_number', 'LIKE', "%{$search}%")
                  ->orWhere('customer_loan_account_number', 'LIKE', "%{$search}%")
                  ->orWhere('application_number', 'LIKE', "%{$search}%")
                  ->orWhere('loan_amount', 'LIKE', "%{$search}%")
                  ->orWhere('status', 'LIKE', "%{$search}%")
                  ->orWhereHas('client', function($q) use ($search) {
                      $q->where('client_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('loanApplication.product', function($q) use ($search) {
                      $q->where('loan_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $baseQuery = LoanAccount::query();
        if (! $isAdmin) {
            $baseQuery->whereHas('client', function($q) use ($agentId, $userId) {
                $q->where('assigned_to', $agentId)
                  ->orWhere('assigned_to', $userId)
                  ->orWhere('added_by', $agentId)
                  ->orWhere('added_by', $userId);
            });
        }

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 'active')->count(),
            'closed' => (clone $baseQuery)->where('status', 'closed')->count(),
        ];

        $loanAccounts = $query->latest()->paginate($request->input('per_page', 15));
        
        $loanAccounts->getCollection()->transform(function ($loan) {
            $frequency = $loan->frequency;
            $frequencyLabel = $loan->frequency_label;
            $presentLoanAmount = $loan->present_loan_amount;
            $isOpenLoan = $loan->isOpenLoan();
            $remainingPrincipal = $isOpenLoan
                ? (float) $loan->remaining_principal_balance
                : (float) $loan->outstanding_amount;

            $emiCount = $loan->emis->count();
            if ($isOpenLoan) {
                $nextEmi = $loan->emis->whereNotIn('status', ['paid', 'closed'])->sortBy('instalment_number')->first();
                $emiAmount = $nextEmi
                    ? (float) ($nextEmi->interest_amount ?: $nextEmi->total_amount ?: $nextEmi->pending_amount)
                    : round($remainingPrincipal * ((float) $loan->interest_rate / 100), 2);
            } else {
                $emiAmount = $emiCount > 0 ? $loan->total_payable / $emiCount : 0;
            }

            if ($presentLoanAmount <= 0) {
                $presentLoanAmount = (float) ($emiAmount ?: $loan->emi_amount);
            }

            $isClosed = $loan->isEffectivelyClosed();
            $effectiveStatus = $isClosed ? 'closed' : (($loan->status === 'closed' && $isOpenLoan) ? 'active' : ($loan->status ?? 'active'));

            return [
                'id' => $loan->id,
                'account_number' => $loan->account_number,
                'customer_loan_account_number' => $loan->customer_loan_account_number,
                'application_number' => $loan->application_number ?? 'N/A',
                'client_name' => $loan->client->client_name ?? 'N/A',
                'zone' => $loan->client->location->name ?? 'N/A',
                'client_phone' => $loan->client->client_phone ?? 'N/A',
                'loan_name' => optional($loan->loanApplication->product)->loan_name ?? 'N/A',
                'loan_amount' => $loan->loan_amount,
                'total_payable' => $loan->total_payable,
                'outstanding_amount' => $isOpenLoan ? $remainingPrincipal : $loan->outstanding_amount,
                'frequency' => $frequency,
                'frequency_label' => $frequencyLabel,
                'present_loan_amount' => $presentLoanAmount,
                'present_loan_amount_formatted' => '₹' . number_format($presentLoanAmount, 2),
                'emi_amount' => $emiAmount ?: $presentLoanAmount,
                'tenure' => $loan->tenure,
                'tenure_formatted' => $isOpenLoan ? 'Open Loan' : ($loan->tenure . ' ' . (optional($loan->loanApplication)->term_unit ?? ($frequencyLabel . 's'))),
                'interest_rate' => $loan->interest_rate,
                'is_open_loan' => $isOpenLoan,
                'status' => $effectiveStatus,
                'status_label' => $isClosed ? 'Closed' : ucfirst($effectiveStatus),
                'status_badge' => match ($effectiveStatus) {
                    'active' => 'success',
                    'closed', 'completed' => 'info',
                    'defaulted' => 'danger',
                    default => 'secondary',
                },
                'disbursed_at' => $loan->disbursed_at ? $loan->disbursed_at->format('Y-m-d') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $loanAccounts,
            'stats' => $stats
        ]);
    }

    /**
     * Display individual loan account details
     */
    public function viewLoanAccount($id = null, ?LoanDocumentService $documentService = null): JsonResponse
    {
        $id = $id ?? request()->input('id') ?? request()->input('loan_account_id');
        if (! $id) {
            return $this->loanAccounts(request());
        }

        $documentService = $documentService ?? app(LoanDocumentService::class);
        $agentUser = auth()->user();
        $agentId = $agentUser instanceof \App\Models\Agent ? $agentUser->id : optional(optional($agentUser)->agent)->id;
        $userId = $agentUser instanceof \App\Models\Agent ? $agentUser->user_id : optional($agentUser)->id;
        $isAdmin = $agentUser && ($agentUser instanceof \App\Models\User ? ($agentUser->hasRole(['admin', 'super_admin', 'super-admin', 'Super Admin']) || $agentUser->id === 1) : false);

        $loanAccount = LoanAccount::with([
            'emis' => function($query) {
                $query->with('collections')->orderBy('instalment_number', 'asc');
            },
            'loanApplication.client',
            'client',
            'loanApplication.product',
            'clientLoanDocuments'
        ])->find($id);

        if (!$loanAccount) {
            return response()->json(['success' => false, 'message' => 'Loan account not found.'], 404);
        }
        
        $client = $loanAccount->client ?? ($loanAccount->loanApplication ? $loanAccount->loanApplication->client : null);
        
        if (! $client || (! $isAdmin && $client->added_by != $agentId && $client->assigned_to != $agentId && $client->added_by != $userId && $client->assigned_to != $userId)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to view this loan account.'], 403);
        }

        $emis = $loanAccount->emis->sortBy('instalment_number')->map(function ($emi) {
            $inProgressSum = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
            
            if ($emi->status !== 'paid' && $inProgressSum > 0) {
                $emi->status = 'in_progress';
            }
            
            $emiArray = $emi->toArray();
            $emiArray['due_date'] = $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->format('Y-m-d') : null;
            
            return $emiArray;
        })->values();

        $isClosed = $loanAccount->isEffectivelyClosed();
        $isOpenLoan = $loanAccount->isOpenLoan();
        $effectiveStatus = $isClosed ? 'closed' : (($loanAccount->status === 'closed' && $isOpenLoan) ? 'active' : ($loanAccount->status ?? 'active'));
        $remainingPrincipal = (float) $loanAccount->remaining_principal_balance;
        $principalAllocated = (float) $loanAccount->principal_allocated;
        $presentLoanAmount = (float) $loanAccount->present_loan_amount;
        if ($isOpenLoan && $presentLoanAmount <= 0) {
            $nextEmi = $loanAccount->emis->whereNotIn('status', ['paid', 'closed'])->sortBy('instalment_number')->first();
            $presentLoanAmount = $nextEmi
                ? (float) ($nextEmi->interest_amount ?: $nextEmi->total_amount ?: $nextEmi->pending_amount)
                : round($remainingPrincipal * ((float) $loanAccount->interest_rate / 100), 2);
        }

        $loanAccountArray = $loanAccount->toArray();
        $loanAccountArray['status'] = $effectiveStatus;
        $loanAccountArray['status_label'] = $isClosed ? 'Closed' : ucfirst($effectiveStatus);
        $loanAccountArray['status_badge'] = match ($effectiveStatus) {
            'active' => 'success',
            'closed', 'completed' => 'info',
            'defaulted' => 'danger',
            default => 'secondary',
        };
        $loanAccountArray['emis'] = $emis;
        $loanAccountArray['frequency'] = $loanAccount->frequency;
        $loanAccountArray['frequency_label'] = $loanAccount->frequency_label;
        $loanAccountArray['is_open_loan'] = $isOpenLoan;
        $loanAccountArray['loan_mode_label'] = $isOpenLoan ? 'Open Loan' : 'EMI';
        $loanAccountArray['remaining_principal_balance'] = $remainingPrincipal;
        $loanAccountArray['principal_allocated'] = $principalAllocated;
        $loanAccountArray['principal_pending'] = $remainingPrincipal;
        $loanAccountArray['present_loan_amount'] = $presentLoanAmount;
        $loanAccountArray['present_loan_amount_formatted'] = '₹' . number_format($presentLoanAmount, 2);
        if ($isOpenLoan) {
            $loanAccountArray['outstanding_amount'] = $remainingPrincipal;
            $loanAccountArray['emi_amount'] = $presentLoanAmount;
        }

        unset(
            $loanAccountArray['loan_application'],
            $loanAccountArray['client'],
            $loanAccountArray['client_loan_documents']
        );

        $availableTemplates = collect($documentService->getAvailableDocuments($loanAccount))
            ->map(fn ($template) => [
                'id' => $template->id ?? null,
                'type' => $template->type ?? null,
                'title' => $template->display_title ?? $template->title ?? null,
                'display_title' => $template->display_title ?? $template->title ?? null,
            ])
            ->values();

        $savedDocuments = $loanAccount->clientLoanDocuments->map(fn ($doc) => [
            'id' => $doc->id,
            'document_type' => $doc->document_type,
            'document_title' => $doc->document_title,
            'file_name' => $doc->file_name,
            'file_url' => $doc->file_url,
            'generated_at' => optional($doc->generated_at)->toIso8601String(),
        ])->values();

        $clientSummary = $client ? [
            'id' => $client->id,
            'customer_id' => $client->customer_id,
            'client_name' => $client->client_name,
            'client_email' => $client->client_email,
            'client_phone' => $client->client_phone,
            'address' => $client->address,
            'city' => $client->city,
            'state' => $client->state,
            'pincode' => $client->pincode,
            'status' => $client->status,
            'profile_image_url' => $client->profile_image_url,
        ] : null;

        return response()->json([
            'success' => true,
            'data' => [
                'loan_account' => $loanAccountArray,
                'client' => $clientSummary,
                'emis' => $emis,
                'availableTemplates' => $availableTemplates,
                'savedDocuments' => $savedDocuments,
            ]
        ]);
    }

    /**
     * Regenerate all documents for a loan account
     */
    public function regenerateDocuments($id, LoanDocumentService $documentService): JsonResponse
    {
        $agentId = auth()->user()->id;
        
        $loanAccount = LoanAccount::with('client')->find($id);
        
        if (!$loanAccount) {
            return response()->json(['success' => false, 'message' => 'Loan account not found.'], 404);
        }
        
        $client = $loanAccount->client;
        if (!$client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to regenerate documents for this loan account.'], 403);
        }

        try {
            $documentService->generateAndSaveAllDocuments($loanAccount);
            
            return response()->json([
                'success' => true,
                'message' => 'All documents regenerated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to regenerate documents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate documents: ' . $e->getMessage()
            ], 500);
        }
    }
}
