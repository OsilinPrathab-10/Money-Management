<?php

namespace App\Http\Controllers;

use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Support\HashId;
use App\Support\BulkPaymentGroup;
use App\Services\PaymentReceiptService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class EmiController extends Controller
{
    /**
     * Display repayments index with stats
     */
    public function index(): View
    {
        $today = Carbon::now()->startOfDay();
        
        $currentUser = auth()->user();
        $isAgent = $currentUser->hasRole('Agent');
        $agentId = $isAgent ? optional($currentUser->agent)->id : null;
 
        $baseEmiQuery = Emi::whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery());

        if ($isAgent && $agentId) {
            $baseEmiQuery->whereHas('loanAccount.loanApplication.client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })->whereHas('activeAssignment');
        }

        // Calculate statistics using due-date buckets
        $paidCount = (clone $baseEmiQuery)->where('status', 'paid')->count();
        $overdueCount = (clone $baseEmiQuery)->overdue()->count();
        $pendingCount = (clone $baseEmiQuery)->pendingCurrentMonth()->count();
        $upcomingCount = (clone $baseEmiQuery)->upcoming()->count();
        $partialCount = (clone $baseEmiQuery)->where('status', 'partial')
            ->whereDate('due_date', '>=', $today)
            ->count();

        $stats = [
            'total_emis' => $overdueCount + $pendingCount + $upcomingCount + $partialCount + $paidCount,
            'paid_emis' => $paidCount,
            'pending_emis' => $pendingCount,
            'upcoming_emis' => $upcomingCount,
            'partial_emis' => $partialCount,
            'overdue_emis' => $overdueCount,
            'total_collected' => (clone $baseEmiQuery)->where('status', 'paid')->sum('paid_amount'),
            'total_pending' => (clone $baseEmiQuery)->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where('pending_amount', '>', 0)
                ->sum('pending_amount'),
            'reminders_2days' => (clone $baseEmiQuery)
                ->whereIn('status', ['pending', 'partial'])
                ->whereDate('due_date', Carbon::now()->addDays(2)->toDateString())
                ->count(),
        ];

        $locations = Location::orderBy('name')->get();
        $agents = \App\Models\Agent::where('status', 'active')->orderBy('agent_name')->get();
        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)->orderBy('account_name')->get();
        $loanTypes = \App\Models\LoanType::orderBy('name')->get();

        return view('admin.emi-repayments.repayments', compact('stats', 'locations', 'agents', 'bankAccounts', 'loanTypes'));
    }

    /**
     * Bulk assign EMIs to an agent
     */
    public function bulkAssignAgent(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'emi_ids' => 'required|array',
            'emi_ids.*' => 'required',
            'agent_id' => 'required|exists:agents,id',
            'remarks' => 'nullable|string'
        ]);

        $agent = \App\Models\Agent::findOrFail($request->agent_id);
        $emiIds = $request->emi_ids;
        $count = 0;
        $notifiedClients = [];

        DB::beginTransaction();
        $dedupeKey = null;

        try {
            foreach ($emiIds as $hashedId) {
                $emiId = HashId::decode($hashedId);
                $emiId = is_array($emiId) ? ($emiId[0] ?? $hashedId) : ($emiId ?? $hashedId);
                
                $emi = Emi::findOrFail($emiId);

                // Create or update assignment
                \App\Models\EmiAgentAssignment::updateOrCreate(
                    ['emi_id' => $emi->id],
                    [
                        'agent_id' => $agent->id,
                        'status' => 'assigned',
                        'assigned_at' => now(),
                        'remarks' => $request->remarks ?: 'Bulk assigned via Repayments list'
                    ]
                );

                // Also update Client assigned_to if needed (optional but helpful)
                if ($emi->loanAccount && $emi->loanAccount->client) {
                    $client = $emi->loanAccount->client;
                    $previousAgentId = $client->assigned_to;
                    $client->update(['assigned_to' => $agent->id]);
                    if ((int) $previousAgentId !== (int) $agent->id && ! isset($notifiedClients[$client->id])) {
                        $notifiedClients[$client->id] = true;
                        event(new \App\Events\ClientAssignedToAgentEvent($client->fresh(), $agent));
                    }
                }

                $count++;
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Successfully assigned {$count} EMIs to {$agent->agent_name}"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk assignment failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Assignment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get EMI data for DataTable
     */
    /**
     * Repayments query with every filter except status and search applied.
     */
    private function buildRepaymentsBaseQuery(Request $request)
    {
        $query = Emi::with(['loanAccount.loanApplication.client.location', 'activeAssignment.agent' => function($q) {
            $q->withTrashed();
        }])
            ->whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->select('emis.*');

        // Apply agent filter if current user is an agent
        $currentUser = auth()->user();
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if ($agentId) {
                $query->where(function ($q) use ($agentId) {
                    $q->whereHas('loanAccount.client', function ($cq) use ($agentId) {
                        $cq->where('assigned_to', $agentId)
                          ->orWhere('added_by', $agentId);
                    })
                    ->orWhereHas('loanAccount.loanApplication.client', function ($cq) use ($agentId) {
                        $cq->where('assigned_to', $agentId)
                          ->orWhere('added_by', $agentId);
                    })
                    ->orWhereHas('activeAssignment', function ($aq) use ($agentId) {
                        $aq->where('agent_id', $agentId);
                    });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply location filter
        if ($request->has('location_id') && $request->location_id != '') {
            $query->whereHas('loanAccount.loanApplication.client', function($q) use ($request) {
                $q->where('location_id', $request->location_id);
            });
        }

        // Apply account number filter
        if ($request->filled('account_number')) {
            $query->whereHas('loanAccount', function($q) use ($request) {
                $q->where('account_number', 'LIKE', "%{$request->account_number}%")
                  ->orWhere('customer_loan_account_number', 'LIKE', "%{$request->account_number}%");
            });
        }

        // Optional date filtering
        if ($request->filled('from_date') || $request->filled('to_date')) {
            if ($request->filled('from_date')) {
                $from = Carbon::parse($request->input('from_date'))->startOfDay();
                $query->where('due_date', '>=', $from);
            }

            if ($request->filled('to_date')) {
                $to = Carbon::parse($request->input('to_date'))->endOfDay();
                $query->where('due_date', '<=', $to);
            }
        }

        // Filtering by loan_mode or term_unit
        if ($request->input('loan_mode') === 'emi') {
            $query->whereHas('loanAccount', function ($q) {
                $q->where(function ($mq) {
                    $mq->whereNull('loan_mode')->orWhere('loan_mode', '!=', 'interest_only');
                });
            });
        } elseif ($request->input('loan_mode') === 'interest_only') {
            $query->whereHas('loanAccount', function ($q) {
                $q->where('loan_mode', 'interest_only');
            });
        } elseif ($request->input('term_unit') === 'monthly') {
            $query->whereHas('loanAccount.loanApplication', function ($q) {
                $q->where(function ($tq) {
                    $tq->whereNull('term_unit')
                        ->orWhereRaw("LOWER(TRIM(term_unit)) IN ('month', 'months', 'monthly')");
                });
            });
        }

        // Filtering by loan_type_id
        if ($request->filled('loan_type_id')) {
            $query->whereHas('loanAccount.product', function ($q) use ($request) {
                $q->where('loan_type_id', $request->loan_type_id);
            });
        }

        return $query;
    }

    private function applyRepaymentSearch($query, $search): void
    {
        if (empty($search)) {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->whereHas('loanAccount', function($q) use ($search) {
                  $q->where('account_number', 'LIKE', "%{$search}%")
                    ->orWhere('customer_loan_account_number', 'LIKE', "%{$search}%")
                    ->orWhere('application_number', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('loanAccount.client', function($q) use ($search) {
                  $q->where('client_name', 'LIKE', "%{$search}%")
                    ->orWhere('client_phone', 'LIKE', "%{$search}%");
              });
        });
    }

    /**
     * Every EMI matching the current filters, so Bulk Pay can select beyond the visible page.
     */
    public function allIds(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin') && !auth()->user()->hasRole('Staff')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $status = $request->input('status', 'overdue');
        $today = Carbon::now()->startOfDay();

        $query = $this->buildRepaymentsBaseQuery($request);
        $this->applyRepaymentSearch($query, $request->input('search.value') ?: $request->input('search'));
        $this->applyRepaymentStatusFilter($query, $status, $today);

        // Only rows Bulk Pay can act on: unpaid for payment, paid for undo.
        if ($status === 'paid') {
            $query->where('status', 'paid');
        } else {
            $query->whereIn('status', ['pending', 'overdue', 'partial']);
        }

        $emis = $query->orderBy('loan_account_id')
            ->orderBy('instalment_number')
            ->get();

        $data = $emis->map(function (Emi $emi) use ($status) {
            $client = $emi->loanAccount?->loanApplication?->client ?? $emi->loanAccount?->client;

            $amount = $status === 'paid'
                ? (float) ($emi->paid_amount ?: $emi->total_amount)
                : (float) ($emi->pending_amount ?: max(0, (float) $emi->total_amount - (float) $emi->paid_amount));

            return [
                'id' => $emi->getRouteKey(),
                'amount' => round($amount, 2),
                'client' => $client->client_name ?? 'N/A',
                'account' => $emi->loanAccount->account_number ?? 'N/A',
            ];
        })->filter(fn ($row) => $row['amount'] > 0.01)->values();

        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'data' => $data,
        ]);
    }

    public function getData(Request $request): JsonResponse
    {
        $columns = [
            0 => 'id',
            1 => 'account_number',
            2 => 'due_date',
            3 => 'total_amount',
            4 => 'principal_amount',
            5 => 'status',
        ];

        // Fetch company mobile for WhatsApp message
        $companyMobile = \App\Models\CompanyDetail::first()?->company_mobile ?? '[Phone Number]';

        $today = Carbon::now()->startOfDay();
        $status = $request->input('status', 'overdue'); // Default to overdue if not specified

        // Build base query
        $query = $this->buildRepaymentsBaseQuery($request);

        $queryBeforeSearch = clone $query;

        // Search handling
        $this->applyRepaymentSearch($query, $request->input('search.value'));

        // Calculate dynamic stats
        $baseCountQuery = clone $query;

        $paidCount = (clone $baseCountQuery)->where('status', 'paid')->count();
        $overdueCount = (clone $baseCountQuery)->overdue()->count();
        $pendingCount = (clone $baseCountQuery)->pendingCurrentMonth()->count();
        $upcomingCount = (clone $baseCountQuery)->upcoming()->count();
        $partialCount = (clone $baseCountQuery)->where('status', 'partial')
            ->whereDate('due_date', '>=', $today)
            ->count();

        // Now apply status filter to $query
        $this->applyRepaymentStatusFilter($query, $status, $today);

        $totalDataQuery = clone $queryBeforeSearch;
        $this->applyRepaymentStatusFilter($totalDataQuery, $status, $today);
        $totalData = DB::query()
            ->fromSub(
                $totalDataQuery->select('loan_account_id')->groupBy('loan_account_id'),
                'client_totals'
            )
            ->count();

        // Calculate dynamic stats array
        $dynamicStats = [
            'total_emis' => $overdueCount + $pendingCount + $upcomingCount + $partialCount + $paidCount,
            'paid_emis' => $paidCount,
            'pending_emis' => $pendingCount,
            'upcoming_emis' => $upcomingCount,
            'partial_emis' => $partialCount,
            'overdue_emis' => $overdueCount,
            'total_collected' => (clone $baseCountQuery)->where('status', 'paid')->sum('paid_amount'),
            'total_pending' => (clone $baseCountQuery)->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where('pending_amount', '>', 0)
                ->sum('pending_amount'),
        ];

        // Client-level pagination: one row per loan account
        $clientGroupsQuery = (clone $query)
            ->select(
                'loan_account_id',
                DB::raw('MIN(due_date) as earliest_due'),
                DB::raw('SUM(pending_amount) as total_pending_amount')
            )
            ->groupBy('loan_account_id');

        $totalFiltered = DB::query()
            ->fromSub($clientGroupsQuery, 'client_groups')
            ->count();

        $limit = (int) $request->input('length', 20);
        $start = (int) $request->input('start', 0);

        $columnsMap = [
            0 => 'earliest_due',
            1 => 'earliest_due',
            2 => 'earliest_due',
            3 => 'earliest_due',
            4 => 'earliest_due',
            5 => 'earliest_due',
            6 => 'earliest_due',
            7 => 'earliest_due',
            8 => 'total_pending_amount',
            9 => 'earliest_due',
        ];
        $orderColIndex = (int) $request->input('order.0.column', 7);
        $order = $columnsMap[$orderColIndex] ?? 'earliest_due';
        $dir = $request->input('order.0.dir') ?? 'asc';

        $pagedClientGroups = (clone $clientGroupsQuery)
            ->orderBy($order, $dir)
            ->offset($start)
            ->limit($limit)
            ->get();

        $loanAccountIds = $pagedClientGroups->pluck('loan_account_id')->filter()->values();

        $loanAccounts = LoanAccount::with([
            'loanApplication.client.location',
            'loanApplication.client.agent',
            'loanApplication.client.creator',
            'loanApplication.product',
            'client.location',
            'client.agent',
            'client.creator',
            'emis.activeAssignment.agent' => fn ($q) => $q->withTrashed(),
            'emis.assignments.agent' => fn ($q) => $q->withTrashed(),
        ])
            ->whereIn('id', $loanAccountIds)
            ->get()
            ->keyBy('id');

        $viewEmisQuery = Emi::with([
            'loanAccount',
            'collections',
            'assignments.agent' => fn ($q) => $q->withTrashed(),
            'activeAssignment.agent' => fn ($q) => $q->withTrashed(),
        ])->whereIn('loan_account_id', $loanAccountIds);

        $this->applyRepaymentStatusFilter($viewEmisQuery, $status, $today);

        $emisByAccount = $viewEmisQuery
            ->orderBy('instalment_number')
            ->get()
            ->groupBy('loan_account_id');

        $companySlogan = \App\Models\CompanyDetail::first()->company_slogan
            ?? \App\Models\CompanyDetail::first()->company_name
            ?? 'Codepluse Gen PVT Ltd';

        $data = $pagedClientGroups->values()->map(function ($group, $index) use (
            $start,
            $loanAccounts,
            $emisByAccount,
            $companyMobile,
            $companySlogan,
            $today
        ) {
            $loanAccount = $loanAccounts->get($group->loan_account_id);
            $loanApplication = optional($loanAccount)->loanApplication;
            $client = optional($loanAccount)->client ?? optional($loanApplication)->client;
            $loanAmount = $loanApplication->loan_amount
                ?? optional($loanAccount)->loan_amount
                ?? 0;
            $applicationNumber = optional($loanAccount)->application_number
                ?? optional($loanApplication)->application_number;

            $accountEmis = $emisByAccount->get($group->loan_account_id, collect());
            $formattedEmis = $accountEmis->map(
                fn ($emi) => $this->formatEmiForRepaymentsTable($emi, $companyMobile, $companySlogan, $today)
            );

            $groupedEmis = [
                'overdue' => $formattedEmis->where('status', 'overdue')->values()->all(),
                'pending' => $formattedEmis->where('status', 'pending')->values()->all(),
                'upcoming' => $formattedEmis->where('status', 'upcoming')->values()->all(),
                'partial' => $formattedEmis->where('status', 'partial')->values()->all(),
                'paid' => $formattedEmis->where('status', 'paid')->values()->all(),
            ];

            $overdueCount = count($groupedEmis['overdue']);
            $pendingCount = count($groupedEmis['pending']);
            $upcomingCount = count($groupedEmis['upcoming']);
            $partialCount = count($groupedEmis['partial']);
            $paidEmiCount = count($groupedEmis['paid']);

            $agentName = 'Unassigned';

            // 1. Check active assignment on filtered EMIs for this tab
            $firstEmiWithAgent = $accountEmis->first(fn ($emi) => $emi->activeAssignment?->agent);
            if ($firstEmiWithAgent && $firstEmiWithAgent->activeAssignment?->agent) {
                $agentName = $firstEmiWithAgent->activeAssignment->agent->agent_name;
            }

            // 2. Check any assignment on filtered EMIs (including collected/completed)
            if ($agentName === 'Unassigned') {
                $anyEmiWithAgent = $accountEmis->first(fn ($emi) => $emi->assignments?->agent);
                if ($anyEmiWithAgent && $anyEmiWithAgent->assignments?->agent) {
                    $agentName = $anyEmiWithAgent->assignments->agent->agent_name;
                }
            }

            // 3. Check all EMIs belonging to the LoanAccount for any agent assignment
            if ($agentName === 'Unassigned' && $loanAccount && $loanAccount->emis) {
                $allAccountEmis = $loanAccount->emis;
                $allEmiWithAgent = $allAccountEmis->first(fn ($emi) => $emi->activeAssignment?->agent ?? $emi->assignments?->agent);
                if ($allEmiWithAgent) {
                    $agent = $allEmiWithAgent->activeAssignment?->agent ?? $allEmiWithAgent->assignments?->agent;
                    if ($agent && $agent->agent_name) {
                        $agentName = $agent->agent_name;
                    }
                }
            }

            // 4. Fallback to client's assigned agent ($client->agent / assigned_to)
            if ($agentName === 'Unassigned') {
                $assignedAgent = $client?->agent ?? $loanAccount?->client?->agent ?? $loanApplication?->client?->agent;
                if ($assignedAgent && $assignedAgent->agent_name) {
                    $agentName = $assignedAgent->agent_name;
                }
            }

            // 5. Fallback to client's creator agent ($client->creator / added_by)
            if ($agentName === 'Unassigned') {
                $creatorAgent = $client?->creator ?? $loanAccount?->client?->creator ?? $loanApplication?->client?->creator;
                if ($creatorAgent && $creatorAgent->agent_name) {
                    $agentName = $creatorAgent->agent_name;
                }
            }

            $totalDue = $accountEmis->sum('pending_amount');

            return [
                'id' => $loanAccount ? $loanAccount->getRouteKey() : null,
                'sno' => $start + $index + 1,
                'loan_account_id' => $loanAccount ? $loanAccount->getRouteKey() : null,
                'account_number' => $loanAccount->account_number ?? 'N/A',
                'customer_loan_account_number' => $loanAccount->customer_loan_account_number ?? null,
                'application_number' => $applicationNumber,
                'client_name' => $client->client_name ?? 'N/A',
                'client_phone' => $client->client_phone ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'agent_name' => $agentName,
                'loan_amount' => $loanAmount,
                'loan_amount_formatted' => '₹' . number_format($loanAmount, 0),
                'overdue_count' => $overdueCount,
                'pending_count' => $pendingCount,
                'upcoming_count' => $upcomingCount,
                'partial_count' => $partialCount,
                'paid_count' => $paidEmiCount,
                'total_due' => $totalDue,
                'total_due_formatted' => '₹' . number_format($totalDue, 2),
                'status_summary' => $this->buildClientStatusSummary($overdueCount, $pendingCount, $upcomingCount, $partialCount, $paidEmiCount),
                'company_phone' => $companyMobile,
                'company_slogan' => $companySlogan,
                'emis' => $formattedEmis->values()->all(),
                'emis_grouped' => $groupedEmis,
            ];
        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
            'stats' => $dynamicStats,
        ]);
    }

    /**
     * View detailed EMI schedule for a loan application
     */
    public function view($applicationNumber): View
    {
        $loanAccount = LoanAccount::with(['loanApplication.client', 'loanApplication.product'])
            ->where('application_number', $applicationNumber)
            ->firstOrFail();

        // Security Check: Agents can only view their assigned clients
        $currentUser = auth()->user();
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            $client = $loanAccount->client ?? $loanAccount->loanApplication?->client;
            if (!$agentId || !$client || ((int)$client->assigned_to !== (int)$agentId && (int)$client->added_by !== (int)$agentId)) {
                abort(403, 'Unauthorized access to this loan account.');
            }
        }

        $loanApplication = $loanAccount->loanApplication;

        // Open loans gain a cycle only once its due date arrives, so top up here
        // rather than waiting for the nightly loans:sync-open-cycles run.
        app(\App\Services\OpenLoanCycleService::class)->syncDueCycles($loanAccount);

        // Ensure EMI balances are synchronized with the new non-cumulative logic
        $paymentService = app(\App\Services\LoanPaymentService::class);
        $paymentService->syncEmiBalances($loanAccount->id);
        $paymentService->syncLoanTotals($loanAccount->id);
        $loanAccount->refresh();

        $emis = $loanAccount->emis()
            ->with('collections')
            ->orderBy('instalment_number')
            ->get();

        // Get the global first unpaid instalment number
        $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
        if ($isKandhuvatti) {
            $firstUnpaid = $emis->filter(function($emi) {
                if (in_array($emi->status, ['paid', 'closed'], true)) return false;
                $inProgressSum = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
                $netPending = max(0, $emi->pending_amount - $inProgressSum);
                return $netPending > 0;
            })->sortBy('instalment_number')->first();
        } else {
            // Skip EMIs fully covered by in_progress (pending approval) collections
            $firstUnpaid = $emis->whereIn('status', ['pending', 'overdue', 'partial'])->filter(function($e) {
                $inProg = $e->collections ? $e->collections->where('status', 'in_progress')->sum('amount') : 0;
                return ($e->pending_amount - $inProg) > 0;
            })->sortBy('instalment_number')->first();
        }
        $firstUnpaidInstalment = $firstUnpaid ? $firstUnpaid->instalment_number : 999999;

        // Calculate summary
        $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
        if ($isKandhuvatti) {
            $principalPaid = $emis->sum('principal_amount');
            $interestPaid = max(0, $loanAccount->paid_amount - $principalPaid);
            $outstanding = $loanAccount->isSettled()
                ? 0
                : max(0, (float)$loanAccount->loan_amount - $principalPaid);
            
            $summary = [
                'account_number' => $loanAccount->customer_loan_account_number ?? $loanAccount->account_number ?? $loanAccount->application_number,
                'loan_amount' => $loanApplication->loan_amount,
                'interest_rate' => $loanApplication->interest_rate,
                'tenure' => 0,
                'total_payable' => 0,
                'paid_amount' => $loanAccount->paid_amount,
                'outstanding' => $outstanding,
                'status' => $loanAccount->status,
                'total_emis' => $emis->count(),
                'paid_emis' => $emis->where('status', 'paid')->count(),
                'pending_emis' => $emis->where('status', 'pending')->count(),
                'overdue_emis' => $emis->filter(fn ($e) => $e->due_date
                    && $e->due_date->lt(now()->startOfDay())
                    && in_array($e->status, ['pending', 'overdue', 'partial'], true)
                    && (float) $e->pending_amount > 0)->count(),
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,
            ];
        } else {
            $totalPayable = (float)($loanAccount->total_payable ?? $emis->sum('total_amount'));
            $paidAmount = (float)($emis->sum('paid_amount'));
            
            $principalPaid = $emis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return (float)($emi->principal_amount ?? 0);
                return max(0, $alreadyPaid - $interestPart);
            });
            
            $interestPaid = $emis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return $interestPart;
                return min($alreadyPaid, $interestPart);
            });

            $isReducing = $loanApplication->product && in_array($loanApplication->product->interest_type, ['reducing', 'declining_balance']);
            if ($isReducing) {
                $outstanding = max(0, (float)$loanApplication->loan_amount - $principalPaid);
            } else {
                $outstanding = max(0, $totalPayable - $paidAmount);
            }

            $summary = [
                'account_number' => $loanAccount->customer_loan_account_number ?? $loanAccount->account_number ?? $loanAccount->application_number,
                'loan_amount' => $loanApplication->loan_amount,
                'interest_rate' => $loanApplication->interest_rate,
                'tenure' => $loanApplication->tenure_months,
                'total_payable' => $totalPayable,
                'paid_amount' => $paidAmount,
                'outstanding' => $outstanding,
                'status' => $loanAccount->status,
                'total_emis' => $emis->count(),
                'paid_emis' => $emis->where('status', 'paid')->count(),
                'pending_emis' => $emis->where('status', 'pending')->count(),
                'overdue_emis' => $emis->filter(fn ($e) => $e->due_date
                    && $e->due_date->lt(now()->startOfDay())
                    && in_array($e->status, ['pending', 'overdue', 'partial'], true)
                    && (float) $e->pending_amount > 0)->count(),
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,
                'principal_outstanding' => max(0, (float)$loanApplication->loan_amount - $principalPaid),
                'next_emi_due_date' => ($loanAccount->status === 'closed') ? 'Closed' : ($firstUnpaid ? $firstUnpaid->due_date->format('d-m-Y') : 'N/A'),
                'is_reducing' => $isReducing,
            ];
        }

        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)
            ->orderBy('account_name')
            ->get();

        $partialPaymentConfig = \App\Models\LoanConfiguration::getPartialPaymentConfig();

        $walletBalance = 0.0;
        if ($loanAccount?->client_id) {
            $walletBalance = app(\App\Services\FixedDeposit\WalletService::class)
                ->balanceForClient((int) $loanAccount->client_id);
        }

        $latestPaidInstalment = $emis
            ->filter(fn ($e) => (float) $e->paid_amount > 0.001 || in_array($e->status, ['paid', 'partial']))
            ->max('instalment_number');

        return view('admin.emi-repayments.emi-details', compact(
            'loanAccount',
            'loanApplication',
            'emis',
            'summary',
            'firstUnpaidInstalment',
            'bankAccounts',
            'partialPaymentConfig',
            'latestPaidInstalment',
            'walletBalance'
        ));
    }

    /**
     * Get a specific EMI detail record
     */
    public function show($emiId): JsonResponse
    {
        $emi = Emi::with([
            'loanAccount.loanApplication.client',
            'loanAccount.loanApplication.product',
            'loanAccount.client',
        ])
            ->findOrFail($emiId);

        $statusMeta = $this->getStatusMeta($emi->status);

        $loanAccount = $emi->loanAccount;
        $loanApplication = optional($loanAccount)->loanApplication;
        $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
        $product = optional($loanApplication)->product;
        $loanAmount = $loanApplication->loan_amount
            ?? optional($loanAccount)->loan_amount
            ?? null;
        $applicationNumber = $emi->application_number
            ?? optional($loanAccount)->application_number
            ?? optional($loanApplication)->application_number;
        $penaltyAmount = $emi->penalty_amount ? number_format($emi->penalty_amount, 2) : null;
        $penaltyDate = $emi->last_penalty_date ? Carbon::parse($emi->last_penalty_date)->format('d-m-Y') : null;

        return response()->json([
            'success' => true,
            'emi' => [
                'id' => $emi->getRouteKey(),
                'application_number' => $applicationNumber,
                'instalment_number' => $emi->instalment_number,
                'due_date' => $emi->due_date ? $emi->due_date->format('d-m-Y') : null,
                'principal_amount' => number_format($emi->principal_amount, 2),
                'interest_amount' => number_format($emi->interest_amount, 2),
                'total_amount' => number_format($emi->total_amount, 2),
                'status' => $emi->status,
                'status_label' => $statusMeta['label'],
                'status_color' => $statusMeta['color'],
                'paid_amount' => $emi->paid_amount ? number_format($emi->paid_amount, 2) : null,
                'paid_date' => $emi->paid_date ? $emi->paid_date->format('d-m-Y') : null,
                'payment_method' => $emi->payment_method ? ucfirst(str_replace('_', ' ', $emi->payment_method)) : null,
                'payment_reference' => $emi->payment_reference ?: null,
                'remarks' => $emi->remarks ?: null,
                'penalty_amount' => $penaltyAmount,
                'penalty_date' => $penaltyDate,
            ],
            'loan' => [
                'account_number' => $loanAccount->customer_loan_account_number ?? $loanAccount->account_number ?? null,
                'product' => $product->loan_name ?? null,
                'client_name' => $client->client_name ?? null,
                'loan_amount' => $loanAmount ? number_format($loanAmount, 0) : null,
            ],
            'is_admin' => auth()->user()->hasRole('Admin'),
        ]);
    }

    /**
     * Display payment receipts index
     */
    public function receiptsIndex(Request $request): RedirectResponse
    {
        return redirect()->route('loan-payment-receipts', $request->query());
    }

    /**
     * Get receipts data for DataTable
     */
    public function getReceiptsData(Request $request): JsonResponse
    {
        return response()->json([
            'data' => app(PaymentReceiptService::class)->listRows('loan', $request),
        ]);
    }

    /**
     * Get pending EMIs for receipt creation
     */
    public function getPendingEmis(): JsonResponse
    {
        $query = Emi::with(['loanAccount.loanApplication.client'])
            ->whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->whereIn('status', ['pending', 'partial', 'overdue']);

        // Filter by agent if current user is an agent
        $currentUser = auth()->user();
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if ($agentId) {
                $query->whereHas('loanAccount.loanApplication.client', function($q) use ($agentId) {
                    $q->where('assigned_to', $agentId);
                });
            }
        }

        $pendingEmis = $query->orderBy('due_date')
            ->get()
            ->map(function ($emi) {
                $loanAccount = $emi->loanAccount;
                $loanApplication = optional($loanAccount)->loanApplication;
                $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
                $applicationNumber = $emi->application_number
                    ?? optional($loanAccount)->application_number
                    ?? optional($loanApplication)->application_number;

                return [
                    'id' => $emi->getRouteKey(),
                    'text' => ($applicationNumber ?? 'N/A') . ' - EMI #' . $emi->instalment_number . ' (₹' . number_format($emi->total_amount, 2) . ')',
                    'client_name' => $client->client_name ?? 'N/A',
                    'application_number' => $applicationNumber,
                    'pending_amount_display' => ($emi->status === 'partial' || ($emi->status === 'paid' && $emi->pending_amount > 0)) ? '<span class="text-danger fw-bold">₹' . number_format($emi->pending_amount, 2) . '</span>' : '-',
                    'emi_amount' => $emi->total_amount,
                    'emi_amount_formatted' => '₹' . number_format($emi->total_amount, 2),
                ];
            });

        return response()->json($pendingEmis);
    }

    /**
     * Create payment receipt
     */
    public function createReceipt(Request $request): JsonResponse
    {
        $request->validate([
            'emi_id' => 'required', // exists check handled by service or manual check
            'paid_amount' => 'required|numeric|min:0',
            'principal_amount' => 'nullable|numeric|min:0',
            'paid_date' => 'required|date',
            'payment_method' => 'required',
            'payment_reference' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
            'internal_bank_account_id' => 'required_if:payment_method,upi,bank_transfer|nullable|exists:bank_accounts,id',
        ]);

        $emiId = $request->emi_id;
        $decodedEmiId = HashId::decode($emiId);
        $decodedEmiId = is_array($decodedEmiId) ? ($decodedEmiId[0] ?? $emiId) : ($decodedEmiId ?? $emiId);
        $emi = \App\Models\Emi::findOrFail($decodedEmiId);

        DB::beginTransaction();
        try {
            // Use LoanPaymentService to process the payment
            $paymentService = app(\App\Services\LoanPaymentService::class);
            $result = $paymentService->processPayment(
                $decodedEmiId,
                $request->paid_amount,
                $request->paid_date,
                $request->payment_method,
                $request->payment_reference,
                $request->remarks ?: 'Created via Receipt',
                false, // skipHistory
                $request->principal_amount ?? 0,
                false, // bypassPriorCheck
                $request->internal_bank_account_id
            );

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            DB::commit();

            $emi = Emi::find($decodedEmiId);
            $appliedEmis = $result['data']['applied_emis'] ?? [];
            $isFullyPaid = ($emi->status === 'paid');
            $successMessage = count($appliedEmis) > 1
                ? 'Payment recorded and applied across multiple EMIs successfully.'
                : ($isFullyPaid ? 'EMI fully paid successfully.' : 'Partial payment recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CreateReceipt error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create receipt: ' . $e->getMessage()
            ], 500);
        }

        // Fire notification event
        event(new \App\Events\PaymentReceivedEvent($emi, $request->paid_amount));

        $loanAccount = $emi->loanAccount->fresh();
        $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
        $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
        $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
        if (strlen($cleanMobile) === 10) {
            $cleanMobile = '91' . $cleanMobile;
        }
        $remainingBalance = $loanAccount->outstanding_amount;

        $smsData = [
            'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
            'mobile_no' => $cleanMobile,
            'account_no' => $loanAccount->account_number,
            'amount_paid' => ($loanAccount->loan_mode === 'interest_only' && $request->principal_amount > 0.001 && $request->paid_amount <= 0.001) ? $request->principal_amount : $request->paid_amount,
            'remaining_balance' => $remainingBalance,
            'loan_mode' => $loanAccount->loan_mode,
            'payment_type' => ($loanAccount->loan_mode === 'interest_only' && $request->principal_amount > 0.001) ? 'principal' : (($loanAccount->loan_mode === 'interest_only') ? 'interest' : 'emi'),
            'application_number' => $loanAccount->application_number,
            'is_partial' => !$isFullyPaid,
            'emi_balance' => $emi->pending_amount,
        ];
        $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

        return response()->json([
            'success' => true,
            'message' => $successMessage ?? 'Payment recorded successfully',
            'receipt_id' => $emi->getRouteKey(),
            'sms_data' => $smsData
        ]);
    }

    /**
     * Pay multiple selected EMIs with an editable total amount.
     * Extra amount cascades to upcoming EMIs automatically.
     */
    public function paySelectedEmis(Request $request): JsonResponse
    {
        $request->validate([
            'emi_ids' => 'required|array|min:1',
            'emi_ids.*' => 'required',
            'paid_amount' => 'required|numeric|min:0.01',
            'principal_amount' => 'nullable|numeric|min:0',
            'paid_date' => 'required|date',
            'payment_method' => 'required',
            'payment_reference' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
            'internal_bank_account_id' => 'required_if:payment_method,upi,bank_transfer|nullable|exists:bank_accounts,id',
        ]);

        $decodedIds = collect($request->emi_ids)->map(function ($id) {
            $decoded = HashId::decode($id);
            return is_array($decoded) ? ($decoded[0] ?? $id) : ($decoded ?? $id);
        })->unique()->values();

        $emis = Emi::whereIn('id', $decodedIds)->get();
        if ($emis->isEmpty() || $emis->count() !== $decodedIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'One or more selected EMIs could not be found.',
            ], 422);
        }

        $loanAccountIds = $emis->pluck('loan_account_id')->unique();
        if ($loanAccountIds->count() !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'All selected EMIs must belong to the same loan account.',
            ], 422);
        }

        $invalidEmi = $emis->first(function ($emi) {
            return !in_array($emi->status, ['pending', 'overdue', 'partial'], true)
                || (float) ($emi->pending_amount ?? 0) <= 0.01;
        });
        if ($invalidEmi) {
            return response()->json([
                'success' => false,
                'message' => 'EMI #' . $invalidEmi->instalment_number . ' is not eligible for payment.',
            ], 422);
        }

        $sortedEmis = $emis->sortBy('instalment_number');
        $anchorEmi = $sortedEmis->first();
        $loanAccount = $anchorEmi->loanAccount;
        $isOpenLoan = ($loanAccount && $loanAccount->loan_mode === 'interest_only');

        DB::beginTransaction();
        try {
            $paymentService = app(\App\Services\LoanPaymentService::class);
            $batchKey = BulkPaymentGroup::generateKey();
            $customRemarks = $request->remarks ?: 'Pay selected EMIs';
            $remarksText = BulkPaymentGroup::appendRemarks(
                $customRemarks,
                $batchKey,
                'Bulk payment for selected EMIs'
            );

            if ($isOpenLoan) {
                $remainingPool = (float) $request->paid_amount;
                $explicitPrincipal = (float) ($request->principal_amount ?? 0);
                $appliedEmis = [];

                foreach ($sortedEmis as $selectedEmi) {
                    if ($remainingPool <= 0.009) {
                        break;
                    }

                    $selectedEmi = $selectedEmi->fresh();
                    if (!$selectedEmi || !in_array($selectedEmi->status, ['pending', 'overdue', 'partial'], true)) {
                        continue;
                    }

                    $pendingAmount = (float) ($selectedEmi->pending_amount ?? 0);
                    if ($pendingAmount <= 0.01) {
                        $pendingAmount = max(0, (float) $selectedEmi->interest_amount - (float) $selectedEmi->paid_amount);
                    }
                    if ($pendingAmount <= 0.01) {
                        continue;
                    }

                    $payThisEmi = min($remainingPool, $pendingAmount);
                    if ($payThisEmi <= 0.009) {
                        continue;
                    }

                    $result = $paymentService->processPayment(
                        $selectedEmi->id,
                        $payThisEmi,
                        $request->paid_date,
                        $request->payment_method,
                        $request->payment_reference ?: $batchKey,
                        $remarksText,
                        false,
                        0, // Interest collections never reduce principal!
                        true,
                        $request->internal_bank_account_id
                    );

                    if (!$result['success']) {
                        throw new \Exception($result['message']);
                    }

                    $remainingPool -= $payThisEmi;
                    $appliedEmis[] = $selectedEmi->id;
                }

                if ($explicitPrincipal > 0.01) {
                    $targetCycle = $sortedEmis->last() ?? $anchorEmi;
                    $result = $paymentService->processPayment(
                        $targetCycle->id,
                        0,
                        $request->paid_date,
                        $request->payment_method,
                        $request->payment_reference ?: $batchKey,
                        $remarksText,
                        false,
                        $explicitPrincipal,
                        true,
                        $request->internal_bank_account_id
                    );
                    if (!$result['success']) {
                        throw new \Exception($result['message']);
                    }
                }
            } else {
                $result = $paymentService->processPayment(
                    $anchorEmi->id,
                    $request->paid_amount,
                    $request->paid_date,
                    $request->payment_method,
                    $request->payment_reference ?: $batchKey,
                    $remarksText,
                    false,
                    $request->principal_amount ?? 0,
                    true,
                    $request->internal_bank_account_id
                );

                if (!$result['success']) {
                    throw new \Exception($result['message']);
                }

                $appliedEmis = $result['data']['applied_emis'] ?? [$anchorEmi->id];
            }

            DB::commit();

            $anchorEmi->refresh();
            $isFullyPaid = ($anchorEmi->status === 'paid');
            $successMessage = count($appliedEmis) > 1
                ? 'Payment recorded and applied across multiple EMIs successfully.'
                : ($isFullyPaid ? 'EMI fully paid successfully.' : 'Partial payment recorded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PaySelectedEmis error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ], 500);
        }

        event(new \App\Events\PaymentReceivedEvent($anchorEmi, $request->paid_amount));

        $loanAccount = $anchorEmi->loanAccount->fresh();
        $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
        $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
        $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
        if (strlen($cleanMobile) === 10) {
            $cleanMobile = '91' . $cleanMobile;
        }

        $smsData = [
            'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
            'mobile_no' => $cleanMobile,
            'account_no' => $loanAccount->account_number,
            'amount_paid' => ($loanAccount->loan_mode === 'interest_only' && $request->principal_amount > 0.001 && $request->paid_amount <= 0.001)
                ? $request->principal_amount
                : $request->paid_amount,
            'remaining_balance' => $loanAccount->outstanding_amount,
            'loan_mode' => $loanAccount->loan_mode,
            'payment_type' => ($loanAccount->loan_mode === 'interest_only' && $request->principal_amount > 0.001) ? 'principal' : (($loanAccount->loan_mode === 'interest_only') ? 'interest' : 'emi'),
            'application_number' => $loanAccount->application_number,
            'is_partial' => !$isFullyPaid,
            'emi_balance' => $anchorEmi->pending_amount,
        ];
        $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'receipt_id' => $anchorEmi->getRouteKey(),
            'sms_data' => $smsData,
        ]);
    }

    /**
     * Build receipt payload for a single EMI or its bulk payment group.
     */
    public function buildReceiptPayload(Emi $emi, ?string $bulkKey = null): array
    {
        $emi->loadMissing([
            'loanAccount.loanApplication.client',
            'loanAccount.loanApplication.product',
            'loanAccount.client',
            'collections.emi',
        ]);

        $loanAccount = $emi->loanAccount;
        $loanApplication = optional($loanAccount)->loanApplication;
        $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
        $product = optional($loanApplication)->product;

        $bulkCollection = BulkPaymentGroup::latestPostedBulkLoanCollection($emi, $bulkKey);
        $siblings = $bulkCollection ? BulkPaymentGroup::findSiblings($bulkCollection) : collect();
        $siblings = $siblings->filter(
            fn (EmiCollection $row) => in_array($row->status, BulkPaymentGroup::postedStatuses(), true)
        );
        $forceSingle = request()->query('single') || request()->query('bulk') === '0';
        $isBulk = $forceSingle ? false : ($siblings->count() > 1);

        $latestCollection = $emi->collections
            ? $emi->collections->filter(fn ($c) => in_array($c->status, BulkPaymentGroup::postedStatuses(), true))->sortByDesc('id')->first()
            : null;
        if (!$latestCollection && $emi->relationLoaded('collections')) {
            $latestCollection = $emi->collections->sortByDesc('id')->first();
        }

        $collTime = null;
        $activeCollection = $isBulk ? ($bulkCollection ?: $latestCollection) : $latestCollection;
        if ($activeCollection) {
            $collAt = $activeCollection->collected_at ? Carbon::parse($activeCollection->collected_at)->timezone('Asia/Kolkata') : null;
            if ($collAt && ($collAt->hour !== 0 || $collAt->minute !== 0 || $collAt->second !== 0)) {
                $collTime = $collAt;
            } elseif ($activeCollection->created_at) {
                $created = Carbon::parse($activeCollection->created_at)->timezone('Asia/Kolkata');
                $dateBase = $collAt ? $collAt->toDateString() : ($emi->paid_date ? $emi->paid_date->toDateString() : $created->toDateString());
                $collTime = Carbon::parse($dateBase . ' ' . $created->format('H:i:s'), 'Asia/Kolkata');
            } elseif ($collAt) {
                $collTime = $collAt;
            }
        }

        if (!$collTime && $emi->paid_date) {
            $timeSource = ($emi->updated_at && $emi->paid_date->isSameDay($emi->updated_at)) ? $emi->updated_at : ($emi->created_at ?? now());
            $collTime = Carbon::parse($emi->paid_date->toDateString() . ' ' . $timeSource->timezone('Asia/Kolkata')->format('H:i:s'), 'Asia/Kolkata');
        }

        $resolvedPaidAt = $collTime ?: now()->timezone('Asia/Kolkata');

        $paymentDate = $resolvedPaidAt ? $resolvedPaidAt->timezone('Asia/Kolkata')->format('d-m-Y h:i A') : 'N/A';
        $paymentDateOnly = $resolvedPaidAt ? $resolvedPaidAt->timezone('Asia/Kolkata')->format('d-m-Y') : 'N/A';
        $paymentTimeOnly = $resolvedPaidAt ? $resolvedPaidAt->timezone('Asia/Kolkata')->format('h:i:s A') : '';

        $disbursedDate = $loanAccount && $loanAccount->disbursed_at
            ? $loanAccount->disbursed_at->timezone('Asia/Kolkata')->format('d-m-Y h:i A')
            : ($loanApplication && $loanApplication->disbursed_at
                ? $loanApplication->disbursed_at->timezone('Asia/Kolkata')->format('d-m-Y h:i A')
                : 'N/A');

        $items = [];
        if ($isBulk) {
            $paidAmount = round((float) $siblings->sum('amount'), 2);
            $principalAmount = round((float) $siblings->sum(fn (EmiCollection $row) => (float) ($row->emi?->principal_amount ?? 0)), 2);
            $interestAmount = round((float) $siblings->sum(fn (EmiCollection $row) => (float) ($row->emi?->interest_amount ?? 0)), 2);
            $penaltyAmount = round((float) $siblings->sum(fn (EmiCollection $row) => (float) ($row->emi?->penalty_amount ?? 0)), 2);
            $emiAmount = round($principalAmount + $interestAmount, 2);
            $overdueAmount = $penaltyAmount > 0 ? $penaltyAmount : 0;
            $instalmentLabel = BulkPaymentGroup::emiSplitLabel($siblings);
            $lead = $siblings->sortBy(fn (EmiCollection $row) => (int) ($row->emi?->instalment_number ?? 0))->first()?->emi ?? $emi;
            $receiptNumber = 'RCP-B-' . str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT);
            $paymentReference = $bulkCollection->payment_reference ?: ($lead->payment_reference ?: 'N/A');
            $paymentMethod = $bulkCollection->payment_method ?: $emi->payment_method;
            $status = BulkPaymentGroup::combinedPaymentType($siblings) === 'partial' ? 'partial' : 'paid';

            $sortedSiblings = $siblings->sortBy(fn (EmiCollection $row) => (int) ($row->emi?->instalment_number ?? $row->emi_id ?? 0))->values();
            foreach ($sortedSiblings as $row) {
                $itemEmi = $row->emi;
                $itemLoan = $itemEmi?->loanAccount;
                $itemApp = $itemLoan?->loanApplication;
                $itemClient = $itemLoan?->client ?? $itemApp?->client ?? $client;
                $appNo = $itemEmi?->application_number
                    ?? $itemLoan?->application_number
                    ?? $itemLoan?->account_number
                    ?? $itemApp?->application_number
                    ?? 'N/A';
                $due = (float) ($itemEmi?->total_amount ?? (($itemEmi?->principal_amount ?? 0) + ($itemEmi?->interest_amount ?? 0)) ?: $row->amount);

                $items[] = [
                    'type' => 'loan',
                    'account_number' => $appNo,
                    'client_name' => $itemClient?->client_name ?? ($client->client_name ?? 'N/A'),
                    'instalment_no' => 'EMI #' . ($itemEmi?->instalment_number ?? 1),
                    'due_amount' => round($due, 2),
                    'paid_amount' => round((float) $row->amount, 2),
                ];
            }
        } else {
            $principalAmount = $emi->principal_amount ?? 0;
            $interestAmount = $emi->interest_amount ?? 0;
            $emiAmount = $emi->total_amount ?? ($principalAmount + $interestAmount);
            $paidAmount = $emi->paid_amount ?? 0;
            $penaltyAmount = $emi->penalty_amount ?? 0;
            $overdueAmount = ($emi->status === 'overdue' || ($penaltyAmount > 0 && $emi->paid_date && $emi->paid_date->gt($emi->due_date)))
                ? $penaltyAmount
                : 0;
            $instalmentLabel = $emi->instalment_number ? ('EMI #' . $emi->instalment_number) : 'N/A';
            $receiptNumber = 'RCP-' . str_pad($emi->id, 6, '0', STR_PAD_LEFT);
            $paymentReference = $emi->payment_reference ?: 'N/A';
            $paymentMethod = $emi->payment_method;
            $status = $emi->status;
        }

        $outstandingAmount = $isBulk ? 0 : max($emiAmount - $paidAmount, 0);
        $isOverduePayment = $overdueAmount > 0;
        $refUpper = strtoupper(trim((string) $paymentReference));
        if ($refUpper === '' || $refUpper === 'BULK PAYMENT' || str_starts_with($refUpper, 'BULK-') || str_starts_with($refUpper, 'FAM-')) {
            $paymentReference = 'N/A';
        }

        return [
            'id' => $emi->getRouteKey(),
            'receipt_number' => $receiptNumber,
            'client_name' => $client->client_name ?? 'N/A',
            'application_number' => $emi->application_number
                ?? optional($loanAccount)->application_number
                ?? optional($loanApplication)->application_number,
            'account_number' => optional($loanAccount)->account_number
                ?? optional($loanAccount)->customer_loan_account_number,
            'loan_product' => $product->loan_name ?? 'N/A',
            'instalment_number' => $emi->instalment_number,
            'instalment_label' => $instalmentLabel,
            'principal_amount' => $principalAmount,
            'interest_amount' => $interestAmount,
            'emi_amount' => $isBulk ? $paidAmount : $emiAmount,
            'overdue_amount' => $overdueAmount,
            'show_overdue' => $isOverduePayment,
            'total_amount_display' => $isBulk ? $paidAmount : ($emiAmount + $overdueAmount),
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'payment_method' => ucfirst(str_replace('_', ' ', $paymentMethod ?? 'N/A')),
            'payment_reference' => $paymentReference,
            'paid_date' => $paymentDate,
            'paid_date_only' => $paymentDateOnly,
            'paid_time' => $paymentTimeOnly,
            'paid_time_only' => $paymentTimeOnly,
            'disbursed_date' => $disbursedDate,
            'status' => $status,
            'status_label' => $this->getStatusMeta($status)['label'] ?? ucfirst((string) $status),
            'status_color' => $this->getStatusMeta($status)['color'] ?? 'secondary',
            'remarks' => $emi->remarks ?? '-',
            'is_bulk' => $isBulk,
            'items' => $items,
            'emi_splits' => $isBulk ? BulkPaymentGroup::emiSplits($siblings) : [],
            'split_item_label' => 'EMI',
            'account_label' => 'Loan ID',
            'start_date_label' => 'Disbursement Date',
            'receipt_title' => $isBulk ? 'LOAN BULK PAYMENT RECEIPT' : 'LOAN PAYMENT RECEIPT',
        ];
    }

    /**
     * Get receipt details for viewing
     */
    public function getReceiptDetails($id): JsonResponse
    {
        $decodedId = \App\Support\HashId::decode($id) ?? $id;
        $emi = Emi::with([
            'loanAccount.loanApplication.client',
            'loanAccount.loanApplication.product',
            'loanAccount.client',
            'collections.emi',
        ])
            ->findOrFail($decodedId);

        return response()->json($this->buildReceiptPayload($emi));
    }

    /**
     * Print receipt
     */
    public function printReceipt($id)
    {
        $decodedId = \App\Support\HashId::decode($id);
        
        if (!$decodedId) {
            if (is_numeric($id)) {
                $decodedId = $id;
            } else {
                Log::error("Failed to decode EMI ID for receipt print: " . $id);
                abort(404, 'Invalid EMI Receipt ID');
            }
        }

        $emi = Emi::with([
            'loanAccount.loanApplication.client',
            'loanAccount.loanApplication.product',
            'loanAccount.client',
            'collections.emi',
        ])
            ->findOrFail($decodedId);

        $receiptData = $this->buildReceiptPayload($emi);

        return view('pdf.payment_receipt', compact('receiptData'));
    }

    public function printStatement($id)
    {
        $decodedId = \App\Support\HashId::decode($id);
        
        if (!$decodedId) {
            if (is_numeric($id)) {
                $decodedId = $id;
            } else {
                Log::error("Failed to decode ID for statement print: " . $id);
                abort(404, 'Invalid Statement ID');
            }
        }

        $loanAccount = LoanAccount::with([
            'loanApplication.client',
            'loanApplication.product',
            'client',
            'emis' => function($q) {
                $q->orderBy('instalment_number', 'asc');
            }
        ])->find($decodedId);

        if (!$loanAccount) {
            $emi = Emi::find($decodedId);
            if ($emi) {
                $loanAccount = LoanAccount::with([
                    'loanApplication.client',
                    'loanApplication.product',
                    'client',
                    'emis' => function($q) {
                        $q->orderBy('instalment_number', 'asc');
                    }
                ])->findOrFail($emi->loan_account_id);
            } else {
                abort(404, 'Loan Account not found');
            }
        }

        $loanApplication = optional($loanAccount)->loanApplication;
        $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
        $product = optional($loanApplication)->product;

        // Calculate portions
        $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
        if ($isKandhuvatti) {
            $principalPaid = $loanAccount->emis->sum('principal_amount');
            $interestPaid = max(0, $loanAccount->paid_amount - $principalPaid);
        } else {
            $principalPaid = $loanAccount->emis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return (float)($emi->principal_amount ?? 0);
                return max(0, $alreadyPaid - $interestPart);
            });
            $interestPaid = $loanAccount->emis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return $interestPart;
                return min($alreadyPaid, $interestPart);
            });
        }

        $statementData = [
            'statement_number' => 'STMT-' . str_pad($loanAccount->id, 6, '0', STR_PAD_LEFT),
            'client_name' => $client->client_name ?? 'N/A',
            'application_number' => $loanAccount->application_number,
            'loan_product' => $product->loan_name ?? 'N/A',
            'loan_amount' => $loanAccount->loan_amount,
            'interest_rate' => $loanAccount->interest_rate,
            'tenure' => $loanAccount->tenure,
            'loan_mode' => $loanAccount->loan_mode,
            'paid_amount' => $loanAccount->paid_amount,
            'outstanding_amount' => $loanAccount->outstanding_amount,
            'status' => $loanAccount->status,
            'principal_paid' => $principalPaid,
            'interest_paid' => $interestPaid,
            'disbursed_date' => $loanAccount->disbursed_at ? $loanAccount->disbursed_at->format('d-m-Y') : 'N/A',
            'emis' => $loanAccount->emis
        ];

        return view('pdf.payment_statement', compact('statementData'));
    }

    /**
     * Filter EMIs by repayment tab status.
     * Overdue = past due | Pending = current month (not past) | Upcoming = after this month.
     */
    private function applyRepaymentStatusFilter($query, string $status, Carbon $today): void
    {
        $monthEnd = $today->copy()->endOfMonth();

        if ($status === 'paid') {
            $query->where('status', 'paid');
            return;
        }

        if ($status === 'overdue') {
            $query->overdue();
            return;
        }

        if ($status === 'pending') {
            $query->pendingCurrentMonth();
            return;
        }

        if ($status === 'upcoming') {
            $query->upcoming();
            return;
        }

        if ($status === 'partial') {
            $query->where('status', 'partial')
                ->whereDate('due_date', '>=', $today);
            return;
        }

        // Fallback: overdue + current-month pending window
        $query->where(function ($q) use ($today, $monthEnd) {
            $q->where(function ($sq) use ($today) {
                $sq->whereDate('due_date', '<', $today)
                    ->whereIn('status', ['pending', 'overdue', 'partial'])
                    ->where('pending_amount', '>', 0);
            })->orWhere(function ($sq) use ($today, $monthEnd) {
                $sq->whereDate('due_date', '>=', $today)
                    ->whereDate('due_date', '<=', $monthEnd)
                    ->whereIn('status', ['pending', 'overdue', 'partial'])
                    ->where('pending_amount', '>', 0);
            });
        });
    }

    /**
     * Restrict query to overdue EMIs plus the earliest upcoming unpaid EMI per account.
     * @deprecated Prefer applyRepaymentStatusFilter()
     */
    private function applyActiveEmiWindow($query, Carbon $today): void
    {
        $query->where(function ($q) use ($today) {
            $q->where(function ($sq) use ($today) {
                $sq->where('due_date', '<', $today)
                    ->where('status', '!=', 'paid');
            })
            ->orWhereIn('emis.id', function ($subQuery) use ($today) {
                $subQuery->select(DB::raw('MIN(e2.id)'))
                    ->from('emis as e2')
                    ->whereColumn('e2.loan_account_id', 'emis.loan_account_id')
                    ->where('e2.status', '!=', 'paid')
                    ->where('e2.due_date', '>=', $today);
            });
        });
    }

    private function formatEmiForRepaymentsTable(Emi $emi, string $companyMobile, string $companySlogan, Carbon $today): array
    {
        $loanAccount = $emi->loanAccount;
        $loanApplication = optional($loanAccount)->loanApplication;
        $applicationNumber = $emi->application_number
            ?? optional($loanAccount)->application_number
            ?? optional($loanApplication)->application_number;

        $displayStatus = $this->resolveDisplayStatus($emi, $today);

        return [
            'id' => $emi->getRouteKey(),
            'loan_account_id' => $loanAccount ? $loanAccount->getRouteKey() : null,
            'account_number' => $loanAccount->account_number ?? 'N/A',
            'customer_loan_account_number' => $loanAccount->customer_loan_account_number ?? null,
            'application_number' => $applicationNumber,
            'instalment_number' => $emi->instalment_number,
            'principal_amount' => $emi->principal_amount,
            'principal_amount_formatted' => '₹' . number_format($emi->principal_amount, 2),
            'interest_amount' => $emi->interest_amount,
            'interest_amount_formatted' => '₹' . number_format($emi->interest_amount, 2),
            'total_amount' => $emi->total_amount,
            'total_amount_formatted' => '₹' . number_format($emi->total_amount, 2),
            'pending_amount' => $emi->pending_amount,
            'pending_amount_formatted' => '₹' . number_format($emi->pending_amount, 2),
            'due_date' => $emi->due_date ? $emi->due_date->format('d-m-Y') : '-',
            'paid_amount' => '<span class="fw-bold">' . ($emi->paid_amount > 0 ? '₹' . number_format($emi->paid_amount, 2) : '-') . '</span>' . ($emi->collections && $emi->collections->count() > 0 ? ' <i class="fa fa-info-circle emi-history-trigger" style="cursor:pointer;"></i>' : ''),
            'paid_amount_raw' => $emi->paid_amount,
            'paid_date_formatted' => $emi->paid_date ? $emi->paid_date->format('d-m-Y') : null,
            'company_phone' => $companyMobile,
            'company_slogan' => $companySlogan,
            'status' => $displayStatus,
            'status_badge' => $this->getStatusBadge($displayStatus),
            'status_meta' => $this->getStatusMeta($displayStatus),
        ];
    }

    /**
     * Map EMI to UI bucket: overdue (past) | pending (current month) | upcoming (later months).
     */
    private function resolveDisplayStatus(Emi $emi, Carbon $today): string
    {
        if ($emi->status === 'paid') {
            return 'paid';
        }

        $dueDate = $emi->due_date ? $emi->due_date->copy()->startOfDay() : null;
        if (!$dueDate) {
            return $emi->status ?: 'pending';
        }

        if ($dueDate->lt($today)) {
            return 'overdue';
        }

        // Partial stays partial when not past due
        if ($emi->status === 'partial') {
            return 'partial';
        }

        $monthEnd = $today->copy()->endOfMonth()->startOfDay();
        if ($dueDate->lte($monthEnd)) {
            return 'pending';
        }

        return 'upcoming';
    }

    private function buildClientStatusSummary(int $overdue, int $pending, int $upcoming, int $partial, int $paid): string
    {
        $parts = [];
        if ($overdue > 0) {
            $parts[] = '<span class="badge bg-label-danger me-1">' . $overdue . ' Overdue</span>';
        }
        if ($pending > 0) {
            $parts[] = '<span class="badge bg-label-warning me-1">' . $pending . ' Pending</span>';
        }
        if ($upcoming > 0) {
            $parts[] = '<span class="badge bg-label-secondary me-1">' . $upcoming . ' Upcoming</span>';
        }
        if ($partial > 0) {
            $parts[] = '<span class="badge bg-label-info me-1">' . $partial . ' Partial</span>';
        }
        if ($paid > 0) {
            $parts[] = '<span class="badge bg-label-success me-1">' . $paid . ' Paid</span>';
        }

        return $parts ? implode('', $parts) : '<span class="text-muted">No EMIs</span>';
    }

    /**
     * Get status badge HTML
     */
    private function getStatusBadge($status): string
    {
        $meta = $this->getStatusMeta($status);
        return sprintf('<span class="badge bg-label-%s">%s</span>', $meta['color'], $meta['label']);
    }

    private function getStatusMeta($status): array
    {
        $status = strtolower($status);
        $map = [
            'paid' => ['label' => 'Paid', 'color' => 'success'],
            'partial' => ['label' => 'Partial', 'color' => 'info'],
            'pending' => ['label' => 'Pending', 'color' => 'warning'],
            'upcoming' => ['label' => 'Upcoming', 'color' => 'secondary'],
            'overdue' => ['label' => 'Overdue', 'color' => 'danger'],
        ];

        return $map[$status] ?? ['label' => 'Unknown', 'color' => 'secondary'];
    }

    private function resolveRouteId(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = HashId::decode((string) $value);
        if ($decoded !== null) {
            return $decoded;
        }

        if (ctype_digit((string) $value)) {
            return (int) $value;
        }

        return $value;
    }

    private function primaryLoanAccountIdsSubquery()
    {
        return LoanAccount::selectRaw('MAX(id)')
            ->groupBy('loan_application_id');
    }

    /**
     * Process partial payment for an EMI
     */
    public function processPartialPayment(Request $request): JsonResponse
    {
        $request->merge([
            'loan_account_id' => $this->resolveRouteId($request->input('loan_account_id')),
            'emi_id' => $this->resolveRouteId($request->input('emi_id')),
            'partial_amount' => $request->filled('partial_amount') ? $request->input('partial_amount') : null,
            'principal_amount' => $request->filled('principal_amount') ? $request->input('principal_amount') : null,
            'internal_bank_account_id' => $request->filled('internal_bank_account_id') ? $request->input('internal_bank_account_id') : null,
        ]);

        $validated = $request->validate([
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'emi_id'          => 'nullable|exists:emis,id',
            'partial_amount'  => 'nullable|numeric|min:0',
            'principal_amount'=> 'nullable|numeric|min:0',
            'payment_date'    => 'required|date',
            'payment_method'  => 'required|string',
            'payment_reference'=> 'nullable|string',
            'internal_bank_account_id' => 'required_if:payment_method,upi,bank_transfer|nullable|exists:bank_accounts,id',
        ]);

        $interestAmount = (float)($validated['partial_amount'] ?? 0);
        $principalAmount = (float)($validated['principal_amount'] ?? 0);

        if ($interestAmount <= 0.001 && $principalAmount <= 0.001) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter either a partial interest amount or a principal repayment amount.'
            ], 422);
        }

        if ($interestAmount > 0.001 && $principalAmount > 0.001) {
            return response()->json([
                'success' => false,
                'message' => 'Only one payment type (Interest OR Principal) is allowed in a single transaction. Please pay only one.'
            ], 422);
        }

        try {
            $partialService = app(\App\Services\PartialPaymentConfigService::class);
            if (!$partialService->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partial payments are disabled. Enable them in Loan Configuration.',
                ], 422);
            }

            $loanAccount = LoanAccount::findOrFail($validated['loan_account_id']);
            
            // If specific EMI ID is provided, check for prior unpaid EMIs
            if (!empty($validated['emi_id'])) {
                $emi = Emi::findOrFail($validated['emi_id']);
                if ((int) $emi->loan_account_id !== (int) $loanAccount->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected EMI ID is invalid for the given loan account.'
                    ], 422);
                }
                
                $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
                $partialService = app(\App\Services\PartialPaymentConfigService::class);
                $payAmount = $interestAmount > 0.001 ? $interestAmount : $principalAmount;

                if ($isKandhuvatti) {
                    if ($interestAmount > 0.001) {
                        return response()->json([
                            'success' => false,
                            'message' => 'For Open Loans (Kandhuvatti), use the standard "Pay" button for interest. The "Partial Pay" modal is only for paying down the Principal.'
                        ], 422);
                    }
                    
                    $pendingAmount = $partialService->getOutstandingDueAmount($emi, $loanAccount);
                    if ($principalAmount > 0.001) {
                        // Principal payment for Kandhuvatti
                        if ($principalAmount > ($loanAccount->outstanding_amount + 0.01)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Principal repayment amount cannot exceed the remaining outstanding loan principal (₹' . number_format($loanAccount->outstanding_amount, 2) . ').'
                            ], 422);
                        }
                    }
                } else {
                    if ($validationError = $partialService->validatePartialAmount($emi, $payAmount, $loanAccount)) {
                        return response()->json([
                            'success' => false,
                            'message' => $validationError,
                        ], 422);
                    }
                }
                
                $lastEmi = Emi::where('loan_account_id', $emi->loan_account_id)
                    ->orderByDesc('instalment_number')
                    ->first();
                $isLoanMatured = ($lastEmi && $lastEmi->due_date && $lastEmi->due_date->lt(now()));

                $unpaidPrior = false;
                if (!$isLoanMatured) {
                    if ($isKandhuvatti) {
                        $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                            ->where('instalment_number', '<', $emi->instalment_number)
                            ->whereIn('status', ['pending', 'overdue', 'partial'])
                            ->where(function($q) {
                                $q->whereRaw('pending_amount - 0 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                            })
                            ->exists();
                    } else {
                        // Also ignore EMIs fully covered by pending (in_progress) agent collections
                        $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                            ->where('instalment_number', '<', $emi->instalment_number)
                            ->whereIn('status', ['pending', 'overdue', 'partial'])
                            ->where(function($q) {
                                $q->whereRaw('pending_amount - 0 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                            })
                            ->exists();
                    }
                }

                if ($unpaidPrior) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please clear previous pending EMIs before paying for this instalment.'
                    ], 400);
                }

     // 3-Day Lock Logic (only applies to 'pending' EMIs; 'partial' or 'overdue' are always unlocked; never applies to open loans/Kandhuvatti)
                // if ($emi->status === 'pending' && !$isKandhuvatti) {
                //     $threeDaysBefore = now()->addDays(3);
                //     $isWithinWindow = $emi->due_date && $emi->due_date <= $threeDaysBefore;
                //     $isPreviousPaid = ($emi->instalment_number > 1) && !$unpaidPrior;

                //     if (!$isWithinWindow && !$isPreviousPaid) {
                //         return response()->json([
                //             'success' => false,
                //             'message' => 'This EMI is currently locked. It will be available 3 days before the due date (' . $emi->due_date->format('d-m-Y') . ').'
                //         ], 400);
                //     }
                // }
                // 3-Day Lock Logic removed: all pending EMIs are now always payable.
            }

            $dedupeFingerprint = implode('|', [
                auth()->id(),
                $validated['loan_account_id'],
                $validated['emi_id'] ?? 'cascade',
                number_format($interestAmount, 2, '.', ''),
                number_format($principalAmount, 2, '.', ''),
                $validated['payment_date'],
                $validated['payment_method'],
            ]);
            $dedupeKey = 'loan-partial-payment:' . hash('sha256', $dedupeFingerprint);

            if (!Cache::add($dedupeKey, true, now()->addSeconds(30))) {
                return response()->json([
                    'success' => false,
                    'message' => 'This payment is already being processed. Please refresh before trying again.',
                ], 409);
            }

            $paymentService = new \App\Services\LoanPaymentService();
            $currentUser    = auth()->user();
            $isAgent        = $currentUser->hasRole('Agent');

            // ── AGENT PATH: auto-verify and process payment immediately ───────────────
            if ($isAgent) {
                $agentId   = optional($currentUser->agent)->id;
                $targetEmi = !empty($validated['emi_id']) ? Emi::findOrFail($validated['emi_id']) : null;
                $payAmount = $interestAmount > 0 ? $interestAmount : $principalAmount;

                DB::beginTransaction();
                try {
                    $emiForCollection = $targetEmi ?? $loanAccount->emis()
                        ->whereIn('status', ['pending','overdue','partial'])
                        ->orderBy('instalment_number')
                        ->first();

                    if (!$emiForCollection) {
                        throw new \Exception('No pending EMI found for this loan.');
                    }

                    $pendingAmt  = max(0, $emiForCollection->pending_amount);
                    $paymentType = ($payAmount >= ($pendingAmt - 0)) ? 'full' : 'partial';

                    $existing = \App\Models\EmiCollection::where('emi_id', $emiForCollection->id)
                        ->where('status', 'in_progress')
                        ->first();

                    if ($existing) {
                        $newAmt    = $existing->amount + $payAmount;
                        $isNowFull = ($newAmt >= ($pendingAmt - 0));
                        $existing->update([
                            'amount'         => $newAmt,
                            'payment_type'   => $isNowFull ? 'full' : 'partial',
                            'payment_method' => $validated['payment_method'],
                            'collected_at'   => $validated['payment_date'],
                            'status'         => 'verified',
                            'verified_by'    => auth()->id(),
                            'verified_at'    => now(),
                            'remarks'        => trim(($existing->remarks ?? '') . "\n[Agent Updated via Partial Modal]"),
                            'bank_account_id'=> $validated['internal_bank_account_id'] ?? $existing->bank_account_id,
                        ]);
                        $collection = $existing;
                    } else {
                        $collection = \App\Models\EmiCollection::create([
                            'agent_id'          => $agentId,
                            'emi_id'            => $emiForCollection->id,
                            'amount'            => $payAmount,
                            'payment_method'    => $validated['payment_method'],
                            'payment_type'      => $paymentType,
                            'payment_reference' => $validated['payment_reference'] ?? null,
                            'status'            => 'verified',
                            'collected_at'      => $validated['payment_date'],
                            'verified_by'       => auth()->id(),
                            'verified_at'       => now(),
                            'remarks'           => '[Agent Collected via Partial Modal]',
                            'bank_account_id'   => $validated['internal_bank_account_id'] ?? null,
                        ]);
                    }

                    if ($agentId) {
                        \App\Models\AgentActivity::create([
                            'emi_id'      => $emiForCollection->id,
                            'agent_id'    => $agentId,
                            'type'        => 'payment',
                            'description' => '₹' . number_format($payAmount, 2),
                            'method'      => strtoupper(str_replace('_', ' ', $validated['payment_method'])),
                            'reference'   => $validated['payment_reference'] ?? null,
                            'remarks'     => null,
                            'action_at'   => $validated['payment_date'],
                        ]);
                    }

                    \App\Models\EmiAgentAssignment::where('emi_id', $emiForCollection->id)
                        ->whereIn('status', ['assigned', 'visited'])
                        ->update(['status' => 'resolved', 'resolved_at' => now()]);

                    $result = $paymentService->processPayment(
                        $emiForCollection->id,
                        $interestAmount,
                        $validated['payment_date'],
                        $validated['payment_method'],
                        $validated['payment_reference'] ?? null,
                        $collection->remarks,
                        true,
                        $principalAmount,
                        false,
                        $validated['internal_bank_account_id'] ?? null
                    );

                    if (!$result['success']) {
                        throw new \Exception($result['message']);
                    }

                    DB::commit();
                } catch (\Exception $ex) {
                    DB::rollBack();
                    throw $ex;
                }

                $loanAccount->refresh();
                $emiForCollection->refresh();
                $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
                $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
                $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
                if (strlen($cleanMobile) === 10) {
                    $cleanMobile = '91' . $cleanMobile;
                }

                $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
                $isFullyPaid = ($emiForCollection->status === 'paid');
                $emiBalance = max(0, $emiForCollection->pending_amount);

                $smsData = [
                    'client_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) ?: ($client->client_name ?? 'Client'),
                    'mobile_no' => $cleanMobile,
                    'account_no' => $loanAccount->account_number,
                    'amount_paid' => $payAmount,
                    'remaining_balance' => $loanAccount->outstanding_amount,
                    'loan_mode' => $loanAccount->loan_mode,
                    'payment_type' => ($isKandhuvatti && $request->payment_type === 'principal') ? 'principal' : (($isKandhuvatti) ? 'interest' : 'emi'),
                    'application_number' => $loanAccount->application_number,
                    'is_partial' => !$isFullyPaid,
                    'emi_balance' => $emiBalance,
                ];
                $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

                $msg = 'Payment processed successfully';
                if ($isKandhuvatti) {
                    $msg = $principalAmount > 0.001 ? 'Principal payment processed successfully' : 'Interest payment processed successfully';
                }

                return response()->json([
                    'success'  => true,
                    'message'  => $msg,
                    'sms_data' => $smsData
                ]);
            }

            // ── ADMIN / STAFF PATH: process immediately ───────────────────────────────
            if (!empty($validated['emi_id'])) {
                $result = $paymentService->processPayment(
                    $validated['emi_id'],
                    $interestAmount,
                    $validated['payment_date'],
                    $validated['payment_method'],
                    $validated['payment_reference'] ?? null,
                    'Partial payment processed via targeted modal',
                    false,
                    $principalAmount,
                    false, // bypassPriorCheck
                    $validated['internal_bank_account_id'] ?? null
                );
            } else {
                $result = $paymentService->processPartialPayment(
                    $loanAccount->id,
                    $interestAmount,
                    $validated['payment_date'],
                    $validated['payment_method'],
                    $validated['payment_reference'] ?? null,
                    'Partial payment processed via cascading logic',
                    false,
                    $validated['internal_bank_account_id'] ?? null
                );
            }
            
            if ($result['success']) {
                $actualPaid = $isKandhuvatti ? ($interestAmount + $principalAmount) : ($interestAmount > 0.001 ? $interestAmount : $principalAmount);
                $this->sendPartialPaymentEmail($loanAccount, $actualPaid, $validated['payment_date'], $validated['payment_method']);
                
                $msg = 'Partial payment processed successfully';
                if ($isKandhuvatti) {
                    if ($principalAmount > 0.001) {
                        $msg = 'Principal payment processed successfully';
                    } else {
                        $msg = 'Interest payment processed successfully';
                    }
                }

                $loanAccount->refresh();
                $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
                $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
                $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
                if (strlen($cleanMobile) === 10) {
                    $cleanMobile = '91' . $cleanMobile;
                }
                $remainingBalance = $loanAccount->outstanding_amount;

                $paidEmi = !empty($validated['emi_id']) 
                    ? Emi::find($validated['emi_id']) 
                    : $loanAccount->emis()->whereIn('status', ['pending','overdue','partial'])->orderBy('instalment_number')->first();
                $emiBalance = $paidEmi ? $paidEmi->fresh()->pending_amount : 0;
                $isFullyPaid = $paidEmi ? ($paidEmi->fresh()->status === 'paid') : false;

                $smsData = [
                    'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
                    'mobile_no' => $cleanMobile,
                    'account_no' => $loanAccount->account_number,
                    'amount_paid' => $actualPaid,
                    'remaining_balance' => $remainingBalance,
                    'loan_mode' => $loanAccount->loan_mode,
                    'payment_type' => ($isKandhuvatti && $principalAmount > 0.001) ? 'principal' : (($isKandhuvatti) ? 'interest' : 'emi'),
                    'application_number' => $loanAccount->application_number,
                    'is_partial' => !$isFullyPaid,
                    'emi_balance' => $emiBalance,
                ];
                $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));
                if (!empty($cleanMobile)) {
                    $smsData['whatsapp_url'] = 'https://wa.me/' . $cleanMobile . '?text=' . rawurlencode($smsData['whatsapp_message'] ?? '');
                    $smsData['sms_url'] = 'sms:+' . $cleanMobile . '?body=' . rawurlencode($smsData['sms_message'] ?? '');
                }

                return response()->json([
                    'success'  => true,
                    'message'  => $msg,
                    'data'     => $result['data'] ?? null,
                    'sms_data' => $smsData
                ]);
            }
            
            Cache::forget($dedupeKey);

            return response()->json(['success' => false, 'message' => $result['message']], 400);
            
        } catch (\Throwable $e) {
            if ($dedupeKey) {
                Cache::forget($dedupeKey);
            }
            Log::error('Partial payment error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Send partial payment confirmation email
     */
    protected function sendPartialPaymentEmail($loanAccount, $paymentAmount, $paymentDate, $paymentMethod)
    {
        try {
            $template = \App\Models\EmailTemplate::where('identifier', 'partial_payment_confirmation')->first();
            
            if (!$template || !$template->status) {
                Log::warning('Partial payment email template not found or inactive');
                return;
            }

            $client = $loanAccount->loanApplication->client;
            
            // Get the most recently updated EMI (the one that was just paid)
            $emi = \App\Models\Emi::where('loan_account_id', $loanAccount->id)
                ->orderBy('updated_at', 'desc')
                ->first();
            
            if (!$emi) {
                Log::warning('No EMI found for loan account: ' . $loanAccount->id);
                return;
            }
            
            // Get next EMI
            $nextEmi = \App\Models\Emi::where('loan_account_id', $loanAccount->id)
                ->where('instalment_number', $emi->instalment_number + 1)
                ->first();
            
            // Replace placeholders
            $subject = str_replace('{{account_number}}', $loanAccount->account_number, $template->subject);
            
            $emailContent = str_replace([
                '{{client_name}}',
                '{{account_number}}',
                '{{emi_number}}',
                '{{payment_amount}}',
                '{{payment_date}}',
                '{{payment_method}}',
                '{{total_emi_amount}}',
                '{{total_paid_amount}}',
                '{{balance_remaining}}',
                '{{emi_status}}',
                '{{next_emi_due_date}}',
                '{{next_emi_amount}}',
                '{{support_email}}',
                '{{company_name}}'
            ], [
                $client->client_name,
                $loanAccount->account_number,
                $emi->instalment_number,
                number_format($paymentAmount, 2),
                \Carbon\Carbon::parse($paymentDate)->format('d-m-Y'),
                ucfirst(str_replace('_', ' ', $paymentMethod)),
                number_format($emi->total_amount, 2),
                number_format($emi->paid_amount ?? 0, 2),
                number_format($emi->balance_forward ?? 0, 2),
                ucfirst($emi->status),
                $nextEmi ? $nextEmi->due_date->format('d-m-Y') : 'N/A',
                $nextEmi ? number_format($nextEmi->total_due ?? $nextEmi->total_amount, 2) : 'N/A',
                config('mail.from.address'),
                config('app.name')
            ], $template->email_body);
            
            // Send email using default template
            Mail::send('emails.default-email-template', [
                'emailContent' => $emailContent
            ], function($message) use ($client, $subject) {
                $message->to($client->email)
                        ->subject($subject);
            });
            
            Log::info('Partial payment email sent to: ' . $client->email);
            
        } catch (\Exception $e) {
            Log::error('Failed to send partial payment email: ' . $e->getMessage());
        }
    }

    public function getCollectionHistory($emiId): JsonResponse
    {
        $emi = Emi::findOrFail($emiId);
        
        $rawCollections = EmiCollection::where('emi_id', $emi->id)
            ->with(['agent:id,agent_name', 'verifiedBy:id,name'])
            ->orderBy('created_at', 'asc')
            ->get();
            
        $interestLimit = (float)$emi->interest_amount;
        $interestRemaining = $interestLimit;
        
        $collections = $rawCollections->map(function ($collection) use (&$interestRemaining) {
            $amount = round((float) $collection->amount, 2);
            
            // Interest portion is cleared first up to the interest limit of this EMI
            $interestPaid = round(min($amount, $interestRemaining), 2);
            $interestRemaining = max(0.00, round($interestRemaining - $interestPaid, 2));
            
            $principalPaid = max(0.00, round($amount - $interestPaid, 2));
            
            $approverName = $collection->getCollectedByLabel();

            return [
                'id' => $collection->id,
                'amount' => '₹' . number_format($amount, 2),
                'principal_paid' => number_format($principalPaid, 2),
                'interest_paid' => number_format($interestPaid, 2),
                'raw_principal_paid' => $principalPaid,
                'raw_interest_paid' => $interestPaid,
                'method' => ucfirst(str_replace('_', ' ', $collection->payment_method)),
                'reference' => $collection->payment_reference ?: 'N/A',
                'type' => ucfirst($collection->payment_type ?? 'N/A'),
                'date' => $collection->collected_at ? $collection->collected_at->format('d-m-Y h:i A') : 'N/A',
                'agent' => $approverName,
                'remarks' => $collection->remarks ?? '-',
                'status' => ucfirst($collection->status)
            ];
        })->reverse()->values();

        $originalPaid = round((float) $rawCollections->where('status', 'verified')->sum('amount'), 2);
        if ($originalPaid <= 0) {
            $originalPaid = round((float) ($emi->paid_amount ?? 0), 2);
        }

        $isKandhu = ($emi->loanAccount?->loanApplication?->loan_mode ?? 'emi') === 'interest_only';
        $fullInterest = (float)$emi->interest_amount;
        $totalDisplay = $isKandhu ? $fullInterest : (float)$emi->total_amount;
        $interestPaidTotal = $isKandhu ? max(0, (float)$emi->paid_amount - (float)($emi->principal_amount ?? 0)) : (float)$emi->paid_amount;
        $originalPaidDisplay = $isKandhu ? $interestPaidTotal : $originalPaid;

        return response()->json([
            'success' => true,
            'emi_number' => $emi->instalment_number,
            'total_amount' => '₹' . number_format($totalDisplay, 2),
            'paid_amount' => '₹' . number_format($interestPaidTotal, 2),
            'original_paid_amount' => '₹' . number_format($originalPaidDisplay, 2),
            'collections' => $collections,
            'is_admin' => auth()->user()->hasRole('Admin'),
        ]);
    }

    /**
     * Send prepayment confirmation email
     */
    public function sendPrepaymentEmail($loanAccount, $prepaymentData)
    {
        try {
            $template = \App\Models\EmailTemplate::where('identifier', 'prepayment_confirmation')->first();
            
            if (!$template || !$template->status) {
                Log::warning('Prepayment email template not found or inactive');
                return;
            }

            $client = $loanAccount->loanApplication->client;
            
            // Replace placeholders
            $subject = str_replace('{{account_number}}', $loanAccount->account_number, $template->subject);
            


            $emailContent = str_replace([
                '{{client_name}}',
                '{{account_number}}',
                '{{prepayment_amount}}',
                '{{prepayment_charge}}',
                '{{total_paid}}',
                '{{payment_date}}',
                '{{previous_outstanding}}',
                '{{new_outstanding}}',
                '{{previous_tenure}}',
                '{{new_tenure}}',
                '{{emi_amount}}',
                '{{principal_reduced}}',
                '{{tenure_reduced}}',
                '{{interest_saved}}',
                '{{support_email}}',
                '{{company_name}}'
            ], [
                $client->first_name . ' ' . $client->last_name,
                $loanAccount->account_number,
                number_format($prepaymentData['amount'], 2),
                number_format($prepaymentData['charge'], 2),
                number_format($prepaymentData['total'], 2),
                \Carbon\Carbon::parse($prepaymentData['date'])->format('d-m-Y'),
                number_format($prepaymentData['previous_outstanding'], 2),
                number_format($loanAccount->outstanding_amount, 2),
                $prepaymentData['previous_tenure'],
                $loanAccount->tenure,
                number_format($loanAccount->emi_amount, 2),
                number_format($prepaymentData['amount'], 2),
                $prepaymentData['previous_tenure'] - $loanAccount->tenure,
                number_format($prepaymentData['interest_saved'] ?? 0, 2),
                config('mail.from.address'),
                config('app.name')
            ], $template->email_body);
            
            // Send email
            Mail::send('emails.default-email-template', [
                'emailContent' => $emailContent
            ], function($message) use ($client, $subject) {
                $message->to($client->email)
                        ->subject($subject);
            });
            
            Log::info('Prepayment email sent to: ' . $client->email);
            
        } catch (\Exception $e) {
            Log::error('Failed to send prepayment email: ' . $e->getMessage());
        }
    }

    /**
     * Get EMIs due in the next 2 days for admin reminders
     */
    public function upcomingReminders(): JsonResponse
    {
        $targetDate = Carbon::now()->addDays(2)->toDateString();
        
        $reminders = Emi::with(['loanAccount.loanApplication.client'])
            ->whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->whereDate('due_date', $targetDate)
            ->where('status', 'pending')
            ->get()
            ->map(function($emi) {
                return [
                    'id' => $emi->id,
                    'client' => optional($emi->loanAccount->loanApplication->client)->client_name,
                    'amount' => '₹' . number_format($emi->total_amount, 2),
                    'due_date' => $emi->due_date ? $emi->due_date->format('d-m-Y') : 'N/A',
                    'account' => $emi->loanAccount->account_number
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $reminders->count(),
            'data' => $reminders
        ]);
    }

    /**
     * Process bulk full payment for multiple selected EMIs
     */
    public function bulkPay(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin') && !auth()->user()->hasRole('Staff')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'emi_ids' => 'required|array',
            'emi_ids.*' => 'required',
            'payment_method' => 'nullable|string',
            'internal_bank_account_id' => 'nullable',
            'paid_amount' => 'nullable|numeric|min:0.01',
            'paid_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $paymentMethod = $request->input('payment_method', 'in_hand');
        $bankAccountId = $request->input('internal_bank_account_id');

        if (in_array($paymentMethod, ['upi', 'bank_transfer'], true) && !$bankAccountId) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a collection bank account for UPI / Bank Transfer payments.'
            ], 422);
        }

        $paidDate = $request->input('paid_date') ?: Carbon::now()->toDateString();
        $customRemarks = $request->input('remarks');
        $userRole = auth()->user()->hasRole('Admin') ? 'Admin' : 'Staff';
        $batchKey = BulkPaymentGroup::generateKey();
        $remarksText = BulkPaymentGroup::appendRemarks(
            $customRemarks,
            $batchKey,
            'Bulk payment processed by ' . $userRole
        );

        $hasCustomAmount = $request->filled('paid_amount');
        $remainingPool = $hasCustomAmount ? (float) $request->input('paid_amount') : null;

        $emiIds = $request->emi_ids;
        $count = 0;
        $totalPaid = 0;
        $cashbookLines = [];

        DB::beginTransaction();
        try {
            $paymentService = app(\App\Services\LoanPaymentService::class);

            $decodedIds = [];
            foreach ($emiIds as $hashedId) {
                $emiId = HashId::decode($hashedId);
                $emiId = is_array($emiId) ? ($emiId[0] ?? $hashedId) : ($emiId ?? $hashedId);
                if ($emiId) {
                    $decodedIds[] = $emiId;
                }
            }

            // Fetch selected EMIs ordered by loan_account_id and instalment_number
            $emis = Emi::with(['loanAccount.client'])->whereIn('id', $decodedIds)
                ->orderBy('loan_account_id')
                ->orderBy('instalment_number')
                ->get();

            foreach ($emis as $emi) {
                if ($hasCustomAmount && $remainingPool <= 0.009) {
                    break;
                }

                $emi = $emi->fresh(['loanAccount.client']);
                if (!$emi || !in_array($emi->status, ['pending', 'overdue', 'partial'], true)) {
                    continue;
                }

                $pendingAmount = (float) ($emi->pending_amount ?? 0);
                if ($pendingAmount <= 0.01) {
                    $pendingAmount = max(0, (float) $emi->total_amount - (float) $emi->paid_amount);
                }
                if ($pendingAmount <= 0.01) {
                    continue;
                }

                $payForThisEmi = $pendingAmount;
                if ($hasCustomAmount) {
                    $payForThisEmi = min($remainingPool, $pendingAmount);
                }

                if ($payForThisEmi <= 0.009) {
                    continue;
                }

                $result = $paymentService->processPayment(
                    $emi->id,
                    $payForThisEmi,
                    $paidDate,
                    $paymentMethod,
                    $batchKey,
                    $remarksText,
                    false,
                    0,
                    true,
                    $bankAccountId,
                    null,
                    true // skipCashbook — one bank tx for the whole bulk pay
                );

                if (!$result['success']) {
                    throw new \Exception("Failed to pay EMI #{$emi->instalment_number} for Loan Account: " . ($emi->loanAccount->account_number ?? 'N/A') . ". Reason: " . $result['message']);
                }

                // Fire notification event
                event(new \App\Events\PaymentReceivedEvent($emi, $payForThisEmi));

                $accountNumber = $emi->loanAccount->customer_loan_account_number
                    ?? $emi->loanAccount->account_number
                    ?? 'N/A';
                $clientName = $emi->loanAccount->client->client_name ?? 'Client';
                $loanKey = (string) $emi->loan_account_id;
                if (! isset($cashbookLines[$loanKey])) {
                    $cashbookLines[$loanKey] = [
                        'account_number' => (string) $accountNumber,
                        'client_name' => (string) $clientName,
                        'emis' => [],
                        'amount' => 0.0,
                    ];
                }
                $cashbookLines[$loanKey]['emis'][] = (int) $emi->instalment_number;
                $cashbookLines[$loanKey]['amount'] = round($cashbookLines[$loanKey]['amount'] + $payForThisEmi, 2);

                $count++;
                $totalPaid += $payForThisEmi;
                if ($hasCustomAmount) {
                    $remainingPool -= $payForThisEmi;
                }
            }

            if ($totalPaid > 0.009 && ! in_array($paymentMethod, ['wallet'], true)) {
                $paymentService->recordLoanCollectionInCashbook(
                    $bankAccountId,
                    (string) $paymentMethod,
                    (float) $totalPaid,
                    $batchKey,
                    \App\Services\Account\AccountingTags::loanIcBulkDescription(
                        array_values($cashbookLines),
                        Auth::id()
                    ),
                    $paidDate
                );
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Successfully processed payments for {$count} EMI(s). Total paid: ₹" . number_format($totalPaid, 2)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk payment failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Bulk payment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process bulk undo payments for multiple selected EMIs
     */
    public function bulkUndo(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin') && !auth()->user()->hasRole('Staff')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'emi_ids' => 'required|array',
            'emi_ids.*' => 'required'
        ]);

        $emiIds = $request->emi_ids;
        $count = 0;

        DB::beginTransaction();
        try {
            $paymentService = app(\App\Services\LoanPaymentService::class);

            $decodedIds = [];
            foreach ($emiIds as $hashedId) {
                $emiId = HashId::decode($hashedId);
                $emiId = is_array($emiId) ? ($emiId[0] ?? $hashedId) : ($emiId ?? $hashedId);
                if ($emiId) {
                    $decodedIds[] = $emiId;
                }
            }

            $selectedEmis = Emi::with('loanAccount')->whereIn('id', $decodedIds)->get();

            if ($selectedEmis->isEmpty()) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'No valid EMIs selected for undo.'], 422);
            }

            // Group selected EMIs by loan account to process each loan's paid/partial EMIs in descending order
            $loanGrouped = $selectedEmis->groupBy('loan_account_id');

            foreach ($loanGrouped as $loanAccountId => $groupEmis) {
                $minInstalment = $groupEmis->min('instalment_number');

                // Get all paid/partially-paid EMIs for this loan account >= minInstalment in descending order
                $emisToUndo = Emi::where('loan_account_id', $loanAccountId)
                    ->where(function ($q) {
                        $q->where('paid_amount', '>', 0.001)
                          ->orWhereIn('status', ['paid', 'partial', 'partially_paid']);
                    })
                    ->where('instalment_number', '>=', $minInstalment)
                    ->orderByDesc('instalment_number')
                    ->get();

                foreach ($emisToUndo as $emi) {
                    $result = $paymentService->undoEmiPayment($emi->id, 'Bulk undo processed by ' . (auth()->user()->hasRole('Admin') ? 'Admin' : 'Staff'));

                    if (! $result['success']) {
                        $accountNo = $emi->loanAccount->customer_loan_account_number ?? $emi->loanAccount->account_number ?? 'N/A';
                        throw new \Exception("Failed to undo EMI #{$emi->instalment_number} for Loan Account: {$accountNo}. Reason: " . $result['message']);
                    }

                    $count++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully undid payments for {$count} EMI(s)."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk undo failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Bulk undo failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin-only: Undo a fully paid EMI payment
     */
    public function undoPayment(Request $request, $emiId): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. Admin access only.'], 403);
        }

        $decodedEmiId = \App\Support\HashId::decode($emiId);
        $decodedEmiId = is_array($decodedEmiId) ? ($decodedEmiId[0] ?? $emiId) : ($decodedEmiId ?? $emiId);

        $reason = $request->input('reason', 'Payment undone by Admin');

        $paymentService = app(\App\Services\LoanPaymentService::class);
        $result = $paymentService->undoEmiPayment($decodedEmiId, $reason);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => $result['message']]);
        }

        return response()->json(['success' => false, 'message' => $result['message']], 500);
    }

    /**
     * Admin-only: Delete a payment/collection entry
     */
    public function deleteCollection(Request $request, $collectionId): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. Admin access only.'], 403);
        }

        $decodedCollectionId = \App\Support\HashId::decode($collectionId);
        $decodedCollectionId = is_array($decodedCollectionId) ? ($decodedCollectionId[0] ?? $collectionId) : ($decodedCollectionId ?? $collectionId);

        $reason = $request->input('reason', 'Payment collection entry deleted by Admin');

        $paymentService = app(\App\Services\LoanPaymentService::class);
        $result = $paymentService->deleteEmiCollection($decodedCollectionId, $reason);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => $result['message']]);
        }

        return response()->json(['success' => false, 'message' => $result['message']], 500);
    }

    /**
     * Get loan closing calculation details (total collected vs loan amount + interest)
     */
    public function getLoanClosingDetails($loanAccountId): JsonResponse
    {
        $decodedId = \App\Support\HashId::decode($loanAccountId);
        $decodedId = is_array($decodedId) ? ($decodedId[0] ?? $loanAccountId) : ($decodedId ?? $loanAccountId);

        $loanAccount = LoanAccount::with('emis')->findOrFail($decodedId);

        $paymentService = app(\App\Services\LoanPaymentService::class);
        $summary = $paymentService->calculateLoanClosingSummary($loanAccount);

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * Settle and close loan account after interest & collected checks
     */
    public function settleAndCloseLoan(Request $request, $loanAccountId): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin') && !auth()->user()->hasRole('Staff')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $decodedId = \App\Support\HashId::decode($loanAccountId);
        $decodedId = is_array($decodedId) ? ($decodedId[0] ?? $loanAccountId) : ($decodedId ?? $loanAccountId);

        $loanAccount = LoanAccount::with('emis')->findOrFail($decodedId);

        $paymentService = app(\App\Services\LoanPaymentService::class);
        $result = $paymentService->closeLoanAccount($loanAccount, [
            'remarks' => $request->input('remarks', 'Manual final settlement & loan closing')
        ]);

        return response()->json($result);
    }
}
