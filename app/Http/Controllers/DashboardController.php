<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
  public function index(Request $request)
  {
    if (Auth::check() && Auth::user()->hasRole('CreditVerifier')) {
      return redirect()->route('verification-credit-score-history');
    }

    $totalAgents = Agent::count();
    $totalStaff = \App\Models\Staff::count();

    // Global dashboard period (default: this month). Clients keep their own filter.
    $dashboardPeriod = $this->resolveDashboardPeriod(
      $request->get('period'),
      $request->get('period_from'),
      $request->get('period_to')
    );
    $periodKey = $dashboardPeriod['key'];
    $periodLabel = $dashboardPeriod['label'];
    $periodFrom = $dashboardPeriod['from'];
    $periodTo = $dashboardPeriod['to'];
    $periodFromInput = $dashboardPeriod['from_input'] ?? null;
    $periodToInput = $dashboardPeriod['to_input'] ?? null;

    $clientPeriod = $request->get('client_period', 'all');
    $clientPeriodLabel = 'All Time';
    $clientDateLimit = null;

    if ($clientPeriod === '28_days') {
      $clientDateLimit = Carbon::now()->subDays(28);
      $clientPeriodLabel = 'Last 28 Days';
    } elseif ($clientPeriod === 'month') {
      $clientDateLimit = Carbon::now()->startOfMonth();
      $clientPeriodLabel = 'This Month';
    } elseif ($clientPeriod === 'year') {
      $clientDateLimit = Carbon::now()->startOfYear();
      $clientPeriodLabel = 'This Year';
    }

    $currentUser = Auth::user();
    $isAgent = $currentUser->hasRole('Agent');
    $agentId = $isAgent ? optional($currentUser->agent)->id : null;

    $collectionTotals = $this->calculateCollectionTotals($agentId, $periodFrom, $periodTo);
    $adminCollections = $collectionTotals['admin'];
    $agentCollections = $collectionTotals['agent'];
    $totalCollections = $adminCollections + $agentCollections;

    $clientQuery = Client::query()
      ->where(function ($q) {
        $q->whereHas('loanAccounts')
          ->orWhereHas('loanApplications');
      });

    if ($isAgent && $agentId) {
      $clientQuery->where(function ($q) use ($agentId) {
        $q->where('assigned_to', $agentId)
          ->orWhere('added_by', $agentId);
      });
    }

    if ($clientDateLimit) {
      $clientQuery->where('created_at', '>=', $clientDateLimit);
    }

    $clientAggregates = $clientQuery->selectRaw("LOWER(TRIM(status)) as normalized_status")
      ->selectRaw('COUNT(*) as total')
      ->groupBy('normalized_status')
      ->pluck('total', 'normalized_status');

    $totalClients = $clientAggregates->sum();
    $activeClients = (int) (($clientAggregates['active'] ?? 0) + ($clientAggregates['verified'] ?? 0));
    $blacklistedClients = (int) (($clientAggregates['blacklist'] ?? 0) + ($clientAggregates['blacklisted'] ?? 0));
    $inactiveClients = (int) (($clientAggregates['inactive'] ?? 0) + ($clientAggregates['unverified'] ?? 0));
    $pendingClients = (int) ($clientAggregates['pending'] ?? 0);

    $loanQuery = LoanAccount::query();
    if ($isAgent && $agentId) {
      $loanQuery->whereHas('client', function ($q) use ($agentId) {
        $q->where('assigned_to', $agentId)
          ->orWhere('added_by', $agentId);
      });
    }
    $this->applyLoanPeriodFilter($loanQuery, $periodFrom, $periodTo);

    $loanAggregates = (clone $loanQuery)->selectRaw("LOWER(TRIM(status)) as normalized_status")
      ->selectRaw('COUNT(*) as total')
      ->groupBy('normalized_status')
      ->pluck('total', 'normalized_status');

    $totalLoans = $loanAggregates->sum();
    $activeLoans = (int) ($loanAggregates['active'] ?? 0);
    $closedLoans = (int) (($loanAggregates['closed'] ?? 0) + ($loanAggregates['completed'] ?? 0) + ($loanAggregates['foreclosed'] ?? 0));
    $loanActiveClientsCount = (clone $loanQuery)->where('status', 'active')->whereNotNull('client_id')->distinct('client_id')->count('client_id');

    $appQuery = LoanApplication::query();
    if ($isAgent && $agentId) {
      $appQuery->whereHas('client', function ($q) use ($agentId) {
        $q->where('assigned_to', $agentId)
          ->orWhere('added_by', $agentId);
      });
    }
    if ($periodFrom) {
      $appQuery->where('created_at', '>=', $periodFrom);
    }
    if ($periodTo) {
      $appQuery->where('created_at', '<=', $periodTo);
    }

    $pendingApplications = (clone $appQuery)->whereRaw("LOWER(TRIM(status)) = 'pending'")->count();
    $totalDisbursedAmount = (clone $loanQuery)->sum('loan_amount');
    $totalOutstandingAmount = (clone $loanQuery)->sum('outstanding_amount');

    $now = Carbon::now();
    $uncollectedInterest = $this->calculateMonthlyEmiUncollectedInterest(
      (clone $loanQuery)->where('status', 'active'),
      $now
    );

    $emiQuery = Emi::query();
    if ($isAgent && $agentId) {
      $emiQuery->whereHas('loanAccount.client', function ($q) use ($agentId) {
        $q->where('assigned_to', $agentId)
          ->orWhere('added_by', $agentId);
      });
    }

    $emiStatusCounts = $this->calculateEmiStatusCounts($emiQuery, $periodFrom, $periodTo);
    $overdueLoans = $emiStatusCounts['overdue'];
    $pendingLoans = $emiStatusCounts['pending'];

    $revenueEmiQuery = (clone $emiQuery)->whereRaw("LOWER(TRIM(status)) = 'paid'")
      ->whereNotNull('paid_date');
    if ($periodFrom) {
      $revenueEmiQuery->where('paid_date', '>=', $periodFrom);
    }
    if ($periodTo) {
      $revenueEmiQuery->where('paid_date', '<=', $periodTo);
    }

    $emiRevenue = max(0.00, (float) $revenueEmiQuery->sum(DB::raw('COALESCE(interest_amount, 0) + COALESCE(penalty_amount, 0)')));

    $revenueAppQuery = LoanApplication::query();
    if ($isAgent && $agentId) {
      $revenueAppQuery->whereHas('client', function ($q) use ($agentId) {
        $q->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
      });
    }
    $revenueAppQuery->whereRaw("LOWER(TRIM(status)) = 'disbursed'")->with('applicationDetail');
    if ($periodFrom) {
      $revenueAppQuery->where('disbursed_at', '>=', $periodFrom);
    }
    if ($periodTo) {
      $revenueAppQuery->where('disbursed_at', '<=', $periodTo);
    }

    $loansDisbursedInPeriod = $revenueAppQuery->get();

    $feeRevenue = max(0.00, (float) $loansDisbursedInPeriod->sum(function ($loan) {
      $details = $loan->applicationDetail->details ?? [];
      return max(0.00, (float) ($details['applied_processing_fee'] ?? 0))
        + max(0.00, (float) ($details['applied_document_charges'] ?? 0))
        + max(0.00, (float) ($details['applied_other_charges'] ?? 0));
    }));

    $revenueThisMonth = max(0.00, $emiRevenue + $feeRevenue);

    $allLoansForRevenue = (clone $loanQuery)->with(['loanApplication.applicationDetail', 'emis'])->get();

    $totalProcessingFees = 0;
    $totalDocumentCharges = 0;
    $totalOtherCharges = 0;
    $totalInterestCollected = 0;
    $totalForeclosureRevenue = 0;
    $totalPenaltyAmount = 0;

    foreach ($allLoansForRevenue as $loan) {
      $details = $loan->loanApplication->applicationDetail->details ?? [];
      $processingFee = max(0.00, (float) ($details['applied_processing_fee'] ?? 0));
      $documentCharges = max(0.00, (float) ($details['applied_document_charges'] ?? 0));
      $otherCharges = max(0.00, (float) ($details['applied_other_charges'] ?? 0));

      $interestCollected = max(0.00, (float) $loan->emis->sum(function ($emi) use ($loan, $periodFrom, $periodTo) {
        if ($periodFrom && $emi->paid_date && $emi->paid_date->lt($periodFrom)) {
          return 0;
        }
        if ($periodTo && $emi->paid_date && $emi->paid_date->gt($periodTo)) {
          return 0;
        }
        if ($loan->loan_mode === 'interest_only') {
          return max(0.00, (float) $emi->paid_amount - (float) $emi->principal_amount);
        }

        return max(0.00, min((float) $emi->paid_amount, (float) $emi->interest_amount));
      }));

      $foreclosureRevenue = 0;
      if ($loan->is_foreclosed) {
        if ($loan->foreclosure_charges_amount !== null) {
          $foreclosureRevenue = max(0.00, (float) $loan->foreclosure_charges_amount);
        } elseif ($loan->foreclosure_amount > 0) {
          $chargesPercentage = $loan->foreclosure_charges_percentage ?? $loan->getForeclosureChargesPercentage();
          $multiplier = 1 + ($chargesPercentage / 100);
          $outstanding = $loan->foreclosure_amount / $multiplier;
          $foreclosureRevenue = max(0.00, (float) $loan->foreclosure_amount - $outstanding);
        }
      }

      if ($loan->is_foreclosed && (float) ($loan->foreclosure_interest_amount ?? 0) > 0) {
        $emiAlreadyHasForeclosureInterest = $loan->emis->contains(function ($emi) use ($loan) {
          return $emi->paid_date
            && $loan->closed_at
            && $emi->paid_date->isSameDay($loan->closed_at)
            && (float) $emi->interest_amount > 0;
        });
        if (! $emiAlreadyHasForeclosureInterest) {
          $interestCollected += max(0.00, (float) $loan->foreclosure_interest_amount);
        }
      }

      $penaltyAmount = max(0.00, (float) $loan->emis->sum('penalty_amount'));

      $totalProcessingFees += $processingFee;
      $totalDocumentCharges += $documentCharges;
      $totalOtherCharges += $otherCharges;
      $totalInterestCollected += $interestCollected;
      $totalForeclosureRevenue += $foreclosureRevenue;
      $totalPenaltyAmount += $penaltyAmount;
    }

    $totalRevenue = max(0.00, $totalProcessingFees + $totalDocumentCharges + $totalOtherCharges + $totalInterestCollected + $totalForeclosureRevenue + $totalPenaltyAmount);

    $emiChart = $this->buildEmiChartData($emiQuery, $periodKey, $periodFrom, $periodTo);

    $loanPerformanceQuery = LoanAccount::query();
    if ($isAgent && $agentId) {
      $loanPerformanceQuery->whereHas('client', function ($q) use ($agentId) {
        $q->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
      });
    }
    $this->applyLoanPeriodFilter($loanPerformanceQuery, $periodFrom, $periodTo);

    $loanPerformanceRaw = (clone $loanPerformanceQuery)->selectRaw(
      'COALESCE(loan_products.loan_name, loan_accounts.loan_code, "Unknown") as loan_name'
    )
      ->selectRaw('COUNT(loan_accounts.id) as loan_count')
      ->selectRaw('SUM(COALESCE(loan_accounts.loan_amount, 0)) as total_disbursed')
      ->leftJoin('loan_products', 'loan_products.loan_code', '=', 'loan_accounts.loan_code')
      ->groupBy('loan_products.loan_name', 'loan_accounts.loan_code')
      ->orderByDesc(DB::raw('SUM(COALESCE(loan_accounts.loan_amount, 0))'))
      ->get();

    $loanPerformanceTotalAmount = (float) $loanPerformanceRaw->sum('total_disbursed');
    $loanPerformanceTotalCount = (int) $loanPerformanceRaw->sum('loan_count');

    $iconPresets = [
      'personal' => ['icon' => 'ri-user-heart-line', 'color' => 'primary'],
      'vehicle' => ['icon' => 'ri-car-line', 'color' => 'info'],
      'auto' => ['icon' => 'ri-car-line', 'color' => 'info'],
      'home' => ['icon' => 'ri-home-4-line', 'color' => 'success'],
      'business' => ['icon' => 'ri-briefcase-4-line', 'color' => 'warning'],
      'education' => ['icon' => 'ri-book-3-line', 'color' => 'danger'],
      'agri' => ['icon' => 'ri-plant-line', 'color' => 'success'],
      'gold' => ['icon' => 'ri-gift-line', 'color' => 'warning'],
      'micro' => ['icon' => 'ri-community-line', 'color' => 'primary'],
    ];

    $loanPerformanceList = $loanPerformanceRaw->map(function ($record) use ($loanPerformanceTotalAmount, $iconPresets) {
      $loanName = $record->loan_name;
      $preset = collect($iconPresets)->first(function ($config, $needle) use ($loanName) {
        return Str::contains(Str::lower($loanName), $needle);
      }, ['icon' => 'ri-bank-card-line', 'color' => 'secondary']);

      $sharePercent = $loanPerformanceTotalAmount > 0
        ? round(($record->total_disbursed / $loanPerformanceTotalAmount) * 100, 1)
        : 0;

      return [
        'name' => $loanName,
        'loan_count' => (int) $record->loan_count,
        'total_disbursed' => (float) $record->total_disbursed,
        'share_percent' => $sharePercent,
        'icon' => $preset['icon'],
        'color' => $preset['color'],
      ];
    })->sortByDesc('total_disbursed')->values();

    $loanPerformanceChart = ['hasData' => false];

    $loanDistributionChart = [
      'labels' => $loanPerformanceList->pluck('name')->values()->all(),
      'series' => $loanPerformanceList->pluck('loan_count')->map(fn ($value) => (int) $value)->values()->all(),
      'total' => $loanPerformanceTotalCount,
      'hasData' => $loanPerformanceList->isNotEmpty(),
    ];

    $recentApplications = (clone $appQuery)->with(['client.user:id,name', 'client.location', 'product:loan_code,loan_name,loan_amount_max'])
      ->orderByDesc('created_at')
      ->take(5)
      ->get()
      ->map(function ($application) {
        $creditLimit = optional($application->product)->loan_amount_max ?? null;
        $shouldShowCreditLimit = in_array($application->status, ['pending', 'approved']);
        $application->display_amount = $shouldShowCreditLimit ? $creditLimit : $application->loan_amount;
        return $application;
      });

    $clientActivityPercentage = $totalClients > 0
      ? round(($activeClients / $totalClients) * 100, 2)
      : 0;

    $activeDashboardTab = in_array($request->get('tab'), ['loan', 'chit', 'fd'], true)
      ? $request->get('tab')
      : 'loan';

    $agentFromDate = $request->get('agent_from_date');
    $agentToDate = $request->get('agent_to_date');
    $agentFilterId = $request->filled('agent_filter_id') ? (int) $request->get('agent_filter_id') : null;

    $agentCollectionSummary = $this->buildAgentCollectionSummary($agentFromDate, $agentToDate, $agentFilterId);
    $allAgents = Agent::orderBy('agent_name')->get(['id', 'agent_name', 'agent_code']);

    // Nest chit data so keys like totalCollections do not overwrite loan period filters.
    $chitDashboardData = app(ChitDashboardController::class)->getDashboardData($request);
    $fdDashboard = app(FixedDepositDashboardController::class)->getDashboardData($request);

    return view('admin.dashboard', array_merge(compact(
      'totalAgents',
      'totalStaff',
      'adminCollections',
      'agentCollections',
      'totalCollections',
      'totalClients',
      'totalLoans',
      'activeLoans',
      'closedLoans',
      'loanActiveClientsCount',
      'pendingApplications',
      'pendingLoans',
      'totalDisbursedAmount',
      'totalOutstandingAmount',
      'uncollectedInterest',
      'overdueLoans',
      'revenueThisMonth',
      'totalRevenue',
      'activeClients',
      'inactiveClients',
      'pendingClients',
      'blacklistedClients',
      'clientPeriodLabel',
      'periodKey',
      'periodLabel',
      'periodFromInput',
      'periodToInput',
      'emiChart',
      'loanPerformanceChart',
      'loanDistributionChart',
      'loanPerformanceList',
      'loanPerformanceTotalAmount',
      'loanPerformanceTotalCount',
      'recentApplications',
      'clientActivityPercentage',
      'agentCollectionSummary',
      'allAgents',
      'agentFromDate',
      'agentToDate',
      'agentFilterId',
      'activeDashboardTab',
      'chitDashboardData'
    ), $fdDashboard));
  }

  public function agentCollectionDetails(Agent $agent)
  {
    $today = Carbon::today();
    $monthEnd = $today->copy()->endOfMonth();

    $clients = Client::query()
      ->where('clients.assigned_to', $agent->id)
      ->get();

    // Today's EMI loan collections
    $loanCollections = EmiCollection::query()
      ->where('emi_collections.agent_id', $agent->id)
      ->whereIn('emi_collections.status', ['verified', 'in_progress'])
      ->where('emi_collections.payment_method', '!=', 'payment_link')
      ->whereDate('emi_collections.collected_at', $today)
      ->join('emis', 'emis.id', '=', 'emi_collections.emi_id')
      ->join('loan_accounts', 'loan_accounts.id', '=', 'emis.loan_account_id')
      ->join('clients', 'clients.id', '=', 'loan_accounts.client_id')
      ->select([
        'emi_collections.id',
        'emi_collections.amount',
        'emi_collections.payment_method',
        'emi_collections.status',
        'emi_collections.collected_at',
        'clients.id as client_id',
        'clients.client_name',
        'loan_accounts.account_number',
        'emis.instalment_number',
      ])
      ->get()
      ->map(function ($collection) {
        return [
          'id' => 'loan_' . $collection->id,
          'client_id' => $collection->client_id,
          'client_name' => $collection->client_name ?: 'Unnamed Client',
          'account_number' => $collection->account_number,
          'instalment_number' => 'EMI #' . $collection->instalment_number,
          'amount' => round((float) $collection->amount, 2),
          'payment_method' => Str::headline((string) $collection->payment_method),
          'status' => Str::headline((string) $collection->status),
          'collected_at' => Carbon::parse($collection->collected_at)->format('d M Y, h:i A'),
          'collected_at_raw' => Carbon::parse($collection->collected_at)->toIso8601String(),
        ];
      });

    // Today's Chit collections
    $chitCollections = \App\Models\ChitCollection::query()
      ->where('chit_collections.agent_id', $agent->id)
      ->whereIn('chit_collections.status', ['verified', 'in_progress'])
      ->whereDate('chit_collections.collected_at', $today)
      ->join('group_members', 'group_members.id', '=', 'chit_collections.member_id')
      ->join('clients', 'clients.id', '=', 'chit_collections.client_id')
      ->join('chit_groups', 'chit_groups.id', '=', 'chit_collections.group_id')
      ->join('installments', 'installments.id', '=', 'chit_collections.installment_id')
      ->select([
        'chit_collections.id',
        'chit_collections.amount',
        'chit_collections.payment_method',
        'chit_collections.status',
        'chit_collections.collected_at',
        'clients.id as client_id',
        'clients.client_name',
        'chit_groups.group_code',
        'installments.month_number as instalment_number',
      ])
      ->get()
      ->map(function ($collection) {
        return [
          'id' => 'chit_' . $collection->id,
          'client_id' => $collection->client_id,
          'client_name' => $collection->client_name ?: 'Unnamed Client',
          'account_number' => 'Chit: ' . ($collection->group_code ?: 'Group'),
          'instalment_number' => 'Inst #' . $collection->instalment_number,
          'amount' => round((float) $collection->amount, 2),
          'payment_method' => Str::headline((string) $collection->payment_method),
          'status' => Str::headline((string) $collection->status),
          'collected_at' => Carbon::parse($collection->collected_at)->format('d M Y, h:i A'),
          'collected_at_raw' => Carbon::parse($collection->collected_at)->toIso8601String(),
        ];
      });

    $allCollections = $loanCollections->concat($chitCollections)
      ->sortByDesc('collected_at_raw')
      ->values();

    $collectionTotalsByClient = $allCollections
      ->groupBy('client_id')
      ->map(fn ($items) => round((float) $items->sum('amount'), 2));

    $clientDetailsList = $clients->map(function ($client) use ($today, $monthEnd, $collectionTotalsByClient) {
      $loanOverdue = Emi::whereHas('loanAccount', fn ($q) => $q->where('client_id', $client->id)->where('status', 'active'))
        ->whereDate('due_date', '<', $today)
        ->whereIn('status', ['pending', 'overdue', 'partial'])
        ->where('pending_amount', '>', 0)
        ->count();

      $chitOverdue = Installment::whereHas('member', fn ($q) => $q->where('client_id', $client->id))
        ->whereDate('due_date', '<', $today)
        ->whereIn('status', ['pending', 'overdue', 'partial'])
        ->count();

      $loanPending = Emi::whereHas('loanAccount', fn ($q) => $q->where('client_id', $client->id)->where('status', 'active'))
        ->whereBetween('due_date', [$today, $monthEnd])
        ->whereIn('status', ['pending', 'overdue'])
        ->where('pending_amount', '>', 0)
        ->count();

      $chitPending = Installment::whereHas('member', fn ($q) => $q->where('client_id', $client->id))
        ->whereBetween('due_date', [$today, $monthEnd])
        ->whereIn('status', ['pending', 'overdue', 'partial'])
        ->count();

      $loanPaid = Emi::whereHas('loanAccount', fn ($q) => $q->where('client_id', $client->id))
        ->where('status', 'paid')
        ->count();

      $chitPaid = Installment::whereHas('member', fn ($q) => $q->where('client_id', $client->id))
        ->where('status', 'paid')
        ->count();

      $loanUpcoming = Emi::whereHas('loanAccount', fn ($q) => $q->where('client_id', $client->id)->where('status', 'active'))
        ->whereDate('due_date', '>', $monthEnd)
        ->whereIn('status', ['pending', 'overdue'])
        ->where('pending_amount', '>', 0)
        ->count();

      $chitUpcoming = Installment::whereHas('member', fn ($q) => $q->where('client_id', $client->id))
        ->whereDate('due_date', '>', $monthEnd)
        ->whereIn('status', ['pending', 'overdue', 'partial'])
        ->count();

      return [
        'id' => $client->id,
        'name' => $client->client_name ?: 'Unnamed Client',
        'phone' => $client->client_phone,
        'overdue_count' => $loanOverdue + $chitOverdue,
        'pending_count' => $loanPending + $chitPending,
        'paid_count' => $loanPaid + $chitPaid,
        'upcoming_count' => $loanUpcoming + $chitUpcoming,
        'collected_today' => (float) ($collectionTotalsByClient[$client->id] ?? 0),
      ];
    })->sortByDesc('overdue_count')->values();

    return response()->json([
      'agent' => [
        'name' => $agent->agent_name ?: optional($agent->user)->name ?: 'Unnamed Agent',
        'code' => $agent->agent_code,
        'phone' => $agent->agent_phone,
      ],
      'date' => $today->format('d M Y'),
      'clients' => $clientDetailsList,
      'collections' => $allCollections,
      'total_collected_today' => round((float) $allCollections->sum('amount'), 2),
    ]);
  }

  public function getStats(Request $request)
  {
    $dashboardPeriod = $this->resolveDashboardPeriod(
      $request->get('period'),
      $request->get('period_from'),
      $request->get('period_to')
    );
    $periodFrom = $dashboardPeriod['from'];
    $periodTo = $dashboardPeriod['to'];

    $totalAgents = Agent::count();
    $totalStaff = \App\Models\Staff::count();
    $collectionTotals = $this->calculateCollectionTotals(null, $periodFrom, $periodTo);
    $adminCollections = $collectionTotals['admin'];
    $agentCollections = $collectionTotals['agent'];
    $totalCollections = $adminCollections + $agentCollections;

    $clientAggregates = Client::query()
      ->where(function ($q) {
        $q->whereHas('loanAccounts')
          ->orWhereHas('loanApplications');
      })
      ->selectRaw("LOWER(TRIM(status)) as normalized_status")
      ->selectRaw('COUNT(*) as total')
      ->groupBy('normalized_status')
      ->pluck('total', 'normalized_status');

    $totalClients = $clientAggregates->sum();
    $activeClients = (int) (($clientAggregates['active'] ?? 0) + ($clientAggregates['verified'] ?? 0));
    $blacklistedClients = (int) (($clientAggregates['blacklist'] ?? 0) + ($clientAggregates['blacklisted'] ?? 0));
    $inactiveClients = (int) (($clientAggregates['inactive'] ?? 0) + ($clientAggregates['unverified'] ?? 0));
    $pendingClients = (int) ($clientAggregates['pending'] ?? 0);

    $loanQuery = LoanAccount::query();
    $this->applyLoanPeriodFilter($loanQuery, $periodFrom, $periodTo);

    $loanAggregates = (clone $loanQuery)->selectRaw("LOWER(TRIM(status)) as normalized_status")
      ->selectRaw('COUNT(*) as total')
      ->groupBy('normalized_status')
      ->pluck('total', 'normalized_status');

    $totalLoans = $loanAggregates->sum();
    $activeLoans = (int) ($loanAggregates['active'] ?? 0);

    $appQuery = LoanApplication::query();
    if ($periodFrom) {
      $appQuery->where('created_at', '>=', $periodFrom);
    }
    if ($periodTo) {
      $appQuery->where('created_at', '<=', $periodTo);
    }
    $pendingApplications = (clone $appQuery)->whereRaw("LOWER(TRIM(status)) = 'pending'")->count();
    $totalDisbursedAmount = (clone $loanQuery)->sum('loan_amount');
    $totalOutstandingAmount = (clone $loanQuery)->sum('outstanding_amount');

    $emiStatusCounts = $this->calculateEmiStatusCounts(Emi::query(), $periodFrom, $periodTo);
    $overdueLoans = $emiStatusCounts['overdue'];
    $pendingLoans = $emiStatusCounts['pending'];

    $now = Carbon::now();
    $uncollectedInterest = $this->calculateMonthlyEmiUncollectedInterest(
      (clone $loanQuery)->where('status', 'active'),
      $now
    );

    $emiRevenueQuery = Emi::whereRaw("LOWER(TRIM(status)) = 'paid'")->whereNotNull('paid_date');
    if ($periodFrom) {
      $emiRevenueQuery->where('paid_date', '>=', $periodFrom);
    }
    if ($periodTo) {
      $emiRevenueQuery->where('paid_date', '<=', $periodTo);
    }
    $emiRevenue = max(0.00, (float) $emiRevenueQuery->sum(DB::raw('COALESCE(interest_amount, 0) + COALESCE(penalty_amount, 0)')));

    $loansDisbursedQuery = LoanApplication::whereRaw("LOWER(TRIM(status)) = 'disbursed'")
      ->with('applicationDetail');
    if ($periodFrom) {
      $loansDisbursedQuery->where('disbursed_at', '>=', $periodFrom);
    }
    if ($periodTo) {
      $loansDisbursedQuery->where('disbursed_at', '<=', $periodTo);
    }
    $loansDisbursedInPeriod = $loansDisbursedQuery->get();

    $feeRevenue = max(0.00, (float) $loansDisbursedInPeriod->sum(function ($loan) {
      $details = $loan->applicationDetail->details ?? [];
      return max(0.00, (float) ($details['applied_processing_fee'] ?? 0))
        + max(0.00, (float) ($details['applied_document_charges'] ?? 0))
        + max(0.00, (float) ($details['applied_other_charges'] ?? 0));
    }));

    $revenueThisMonth = max(0.00, $emiRevenue + $feeRevenue);

    $emiQuery = Emi::query();
    $periodKey = $dashboardPeriod['key'] ?? null;
    $emiChart = $this->buildEmiChartData($emiQuery, $periodKey, $periodFrom, $periodTo);

    $distributionData = \App\Models\LoanProduct::withCount(['loanAccounts' => function ($q) {
      $q->whereRaw("LOWER(TRIM(status)) = 'active'");
    }])->get();

    $distLabels = $distributionData->pluck('loan_name')->toArray();
    $distSeries = $distributionData->pluck('loan_accounts_count')->toArray();

    return response()->json([
      'success' => true,
      'stats' => [
        'totalAgents' => number_format($totalAgents),
        'totalStaff' => number_format($totalStaff),
        'adminCollections' => number_format($adminCollections, 2),
        'agentCollections' => number_format($agentCollections, 2),
        'totalCollections' => number_format($totalCollections, 2),
        'totalClients' => number_format($totalClients),
        'activeClients' => number_format($activeClients),
        'inactiveClients' => number_format($inactiveClients),
        'pendingClients' => number_format($pendingClients),
        'blacklistedClients' => number_format($blacklistedClients),
        'totalLoans' => number_format($totalLoans),
        'activeLoans' => number_format($activeLoans),
        'pendingApplications' => number_format($pendingApplications),
        'pendingLoans' => number_format($pendingLoans),
        'totalDisbursedAmount' => number_format($totalDisbursedAmount, 2),
        'totalOutstandingAmount' => number_format($totalOutstandingAmount, 2),
        'uncollectedInterest' => number_format($uncollectedInterest, 2),
        'overdueLoans' => number_format($overdueLoans),
        'revenueThisMonth' => number_format($revenueThisMonth, 2),
      ],
      'charts' => [
        'emiChart' => $emiChart,
        'loanDistribution' => [
          'labels' => $distLabels,
          'series' => $distSeries,
          'hasData' => array_sum($distSeries) > 0,
          'total' => array_sum($distSeries),
        ],
      ],
    ]);
  }

  /**
   * @return array{key: string, label: string, from: ?Carbon, to: ?Carbon, from_input: ?string, to_input: ?string}
   */
  protected function resolveDashboardPeriod(?string $period, ?string $fromInput = null, ?string $toInput = null): array
  {
    $now = Carbon::now();
    $key = in_array($period, ['today', 'month', 'this_month', 'year', 'custom', 'all', '3_months', '6_months'], true)
      ? ($period === 'this_month' ? 'month' : $period)
      : 'month';

    if ($key === 'custom') {
      $from = $fromInput ? Carbon::parse($fromInput)->startOfDay() : $now->copy()->startOfMonth();
      $to = $toInput ? Carbon::parse($toInput)->endOfDay() : $now->copy()->endOfDay();
      if ($from->gt($to)) {
        [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
      }

      return [
        'key' => 'custom',
        'label' => $from->format('d M Y') . ' – ' . $to->format('d M Y'),
        'from' => $from,
        'to' => $to,
        'from_input' => $from->toDateString(),
        'to_input' => $to->toDateString(),
      ];
    }

    return match ($key) {
      'today' => [
        'key' => 'today',
        'label' => 'Today',
        'from' => $now->copy()->startOfDay(),
        'to' => $now->copy()->endOfDay(),
        'from_input' => null,
        'to_input' => null,
      ],
      '3_months' => [
        'key' => '3_months',
        'label' => 'Last 3 Months',
        'from' => $now->copy()->subMonthsNoOverflow(2)->startOfMonth(),
        'to' => $now->copy()->endOfMonth(),
        'from_input' => null,
        'to_input' => null,
      ],
      '6_months' => [
        'key' => '6_months',
        'label' => 'Last 6 Months',
        'from' => $now->copy()->subMonthsNoOverflow(5)->startOfMonth(),
        'to' => $now->copy()->endOfMonth(),
        'from_input' => null,
        'to_input' => null,
      ],
      'year' => [
        'key' => 'year',
        'label' => 'This Year',
        'from' => $now->copy()->startOfYear(),
        'to' => $now->copy()->endOfYear(),
        'from_input' => null,
        'to_input' => null,
      ],
      'all' => [
        'key' => 'all',
        'label' => 'All Time',
        'from' => null,
        'to' => null,
        'from_input' => null,
        'to_input' => null,
      ],
      default => [
        'key' => 'month',
        'label' => 'This Month',
        'from' => $now->copy()->startOfMonth(),
        'to' => $now->copy()->endOfMonth(),
        'from_input' => null,
        'to_input' => null,
      ],
    };
  }

  /**
   * Qualify columns with loan_accounts. to avoid join ambiguity with loan_products.
   */
  protected function applyLoanPeriodFilter($query, ?Carbon $from, ?Carbon $to)
  {
    if (!$from && !$to) {
      return $query;
    }

    return $query->where(function ($q) use ($from, $to) {
      $q->where(function ($dq) use ($from, $to) {
        $dq->whereNotNull('loan_accounts.disbursed_at');
        if ($from) {
          $dq->where('loan_accounts.disbursed_at', '>=', $from);
        }
        if ($to) {
          $dq->where('loan_accounts.disbursed_at', '<=', $to);
        }
      })->orWhere(function ($cq) use ($from, $to) {
        $cq->whereNull('loan_accounts.disbursed_at');
        if ($from) {
          $cq->where('loan_accounts.created_at', '>=', $from);
        }
        if ($to) {
          $cq->where('loan_accounts.created_at', '<=', $to);
        }
      });
    });
  }

  /**
   * @return array{overdue: int, pending: int}
   */
  protected function calculateEmiStatusCounts($emiQuery, ?Carbon $from, ?Carbon $to): array
  {
    $today = Carbon::now()->startOfDay();
    $periodEnd = $to ? $to->copy()->endOfDay() : $today->copy()->endOfMonth();
    $pendingFrom = $today;
    $pendingTo = $periodEnd->lt($today) ? $today->copy()->endOfDay() : $periodEnd;

    $overdueQuery = (clone $emiQuery)
      ->whereDate('due_date', '<', $today)
      ->whereIn('status', ['pending', 'overdue', 'partial'])
      ->where('pending_amount', '>', 0);
    if ($from) {
      $overdueQuery->whereDate('due_date', '>=', $from);
    }

    $pendingQuery = (clone $emiQuery)
      ->whereDate('due_date', '>=', $pendingFrom)
      ->whereDate('due_date', '<=', $pendingTo)
      ->whereIn('status', ['pending', 'overdue'])
      ->where('pending_amount', '>', 0);

    return [
      'overdue' => (int) $overdueQuery->distinct('loan_account_id')->count('loan_account_id'),
      'pending' => (int) $pendingQuery->distinct('loan_account_id')->count('loan_account_id'),
    ];
  }

  /**
   * @return array{admin: float, agent: float}
   */
  protected function calculateCollectionTotals(?int $agentId = null, ?Carbon $from = null, ?Carbon $to = null): array
  {
    $adminQuery = EmiCollection::whereNull('agent_id')
      ->whereIn('status', ['verified', 'in_progress'])
      ->where('payment_method', '!=', 'payment_link');

    $agentQuery = EmiCollection::whereNotNull('agent_id')
      ->whereIn('status', ['verified', 'in_progress'])
      ->where('payment_method', '!=', 'payment_link');

    if ($agentId) {
      $adminQuery->whereHas('emi.loanAccount.client', function ($q) use ($agentId) {
        $q->where('assigned_to', $agentId)
          ->orWhere('added_by', $agentId);
      });
      $agentQuery->where('agent_id', $agentId);
    }

    foreach ([$adminQuery, $agentQuery] as $q) {
      if ($from) {
        $q->where('collected_at', '>=', $from);
      }
      if ($to) {
        $q->where('collected_at', '<=', $to);
      }
    }

    return [
      'admin' => max(0.00, (float) $adminQuery->sum('amount')),
      'agent' => max(0.00, (float) $agentQuery->sum('amount')),
    ];
  }

  protected function buildAgentCollectionSummary()
  {
    $today = Carbon::today();
    $monthEnd = $today->copy()->endOfMonth();

    $loanStatusCounts = Client::query()
      ->whereNotNull('clients.assigned_to')
      ->leftJoin('loan_accounts', function ($join) {
        $join->on('loan_accounts.client_id', '=', 'clients.id')
          ->where('loan_accounts.status', '=', 'active');
      })
      ->leftJoin('emis', 'emis.loan_account_id', '=', 'loan_accounts.id')
      ->select('clients.assigned_to')
      ->selectRaw('COUNT(DISTINCT clients.id) as assigned_customers')
      ->selectRaw(
        'COUNT(DISTINCT CASE WHEN emis.due_date < ? AND emis.status IN ("pending", "overdue", "partial") AND emis.pending_amount > 0 THEN clients.id END) as overdue_customers',
        [$today->toDateString()]
      )
      ->selectRaw(
        'COUNT(DISTINCT CASE WHEN emis.due_date >= ? AND emis.due_date <= ? AND emis.status IN ("pending", "overdue") AND emis.pending_amount > 0 THEN clients.id END) as pending_customers',
        [$today->toDateString(), $monthEnd->toDateString()]
      )
      ->selectRaw(
        'COUNT(DISTINCT CASE WHEN emis.status = "paid" THEN clients.id END) as paid_customers'
      )
      ->selectRaw(
        'COUNT(DISTINCT CASE WHEN emis.due_date > ? AND emis.status IN ("pending", "overdue") AND emis.pending_amount > 0 THEN clients.id END) as upcoming_customers',
        [$monthEnd->toDateString()]
      )
      ->groupBy('clients.assigned_to')
      ->get()
      ->keyBy('assigned_to');

    $chitStatusCounts = Client::query()
      ->whereNotNull('clients.assigned_to')
      ->leftJoin('group_members', function ($j) {
        $j->on('group_members.client_id', '=', 'clients.id')
          ->whereNull('group_members.deleted_at')
          ->whereNotIn('group_members.status', ['rejected', 'transferred', 'withdrawn', 'cancelled']);
      })
      ->leftJoin('installments', function ($j) {
        $j->on('installments.member_id', '=', 'group_members.id')
          ->whereNull('installments.deleted_at')
          ->whereNotIn('installments.status', ['waived']);
      })
      ->select('clients.assigned_to')
      ->selectRaw('COUNT(DISTINCT clients.id) as assigned_customers')
      ->selectRaw(
        'COUNT(DISTINCT CASE WHEN installments.due_date < ? AND installments.status IN ("pending", "overdue", "partial") THEN clients.id END) as overdue_customers',
        [$today->toDateString()]
      )
      ->selectRaw(
        'COUNT(DISTINCT CASE WHEN installments.due_date >= ? AND installments.due_date <= ? AND installments.status IN ("pending", "overdue", "partial") THEN clients.id END) as pending_customers',
        [$today->toDateString(), $monthEnd->toDateString()]
      )
      ->selectRaw(
        'COUNT(DISTINCT CASE WHEN installments.status = "paid" THEN clients.id END) as paid_customers'
      )
      ->selectRaw(
        'COUNT(DISTINCT CASE WHEN installments.due_date > ? AND installments.status IN ("pending", "overdue", "partial") THEN clients.id END) as upcoming_customers',
        [$monthEnd->toDateString()]
      )
      ->groupBy('clients.assigned_to')
      ->get()
      ->keyBy('assigned_to');

    $dailyLoanCollections = EmiCollection::query()
      ->whereNotNull('agent_id')
      ->whereIn('status', ['verified', 'in_progress'])
      ->where('payment_method', '!=', 'payment_link')
      ->whereDate('collected_at', $today)
      ->select('agent_id')
      ->selectRaw('SUM(amount) as total')
      ->groupBy('agent_id')
      ->pluck('total', 'agent_id');

    $dailyChitCollections = \App\Models\ChitCollection::query()
      ->whereNotNull('agent_id')
      ->whereIn('status', ['verified', 'in_progress'])
      ->whereDate('collected_at', $today)
      ->select('agent_id')
      ->selectRaw('SUM(amount) as total')
      ->groupBy('agent_id')
      ->pluck('total', 'agent_id');

    return Agent::query()
      ->with('user:id,name')
      ->orderBy('agent_name')
      ->get()
      ->map(function (Agent $agent) use ($loanStatusCounts, $chitStatusCounts, $dailyLoanCollections, $dailyChitCollections) {
        $loanCounts = $loanStatusCounts->get($agent->id);
        $chitCounts = $chitStatusCounts->get($agent->id);

        $assignedCount = max((int) ($loanCounts->assigned_customers ?? 0), (int) ($chitCounts->assigned_customers ?? 0));
        if ($assignedCount === 0) {
          $assignedCount = Client::where('assigned_to', $agent->id)->count();
        }

        $overdueCount = max((int) ($loanCounts->overdue_customers ?? 0), (int) ($chitCounts->overdue_customers ?? 0));
        $pendingCount = max((int) ($loanCounts->pending_customers ?? 0), (int) ($chitCounts->pending_customers ?? 0));
        $paidCount = max((int) ($loanCounts->paid_customers ?? 0), (int) ($chitCounts->paid_customers ?? 0));
        $upcomingCount = max((int) ($loanCounts->upcoming_customers ?? 0), (int) ($chitCounts->upcoming_customers ?? 0));

        $loanTotal = (float) ($dailyLoanCollections[$agent->id] ?? 0);
        $chitTotal = (float) ($dailyChitCollections[$agent->id] ?? 0);

        return [
          'name' => $agent->agent_name ?: optional($agent->user)->name ?: 'Unnamed Agent',
          'code' => $agent->agent_code,
          'assigned_customers' => $assignedCount,
          'overdue_customers' => $overdueCount,
          'pending_customers' => $pendingCount,
          'paid_customers' => $paidCount,
          'upcoming_customers' => $upcomingCount,
          'collected_today' => round($loanTotal + $chitTotal, 2),
          'details_url' => route('dashboard.agent-collection-details', $agent),
        ];
      })
      ->sortByDesc('overdue_customers')
      ->values();
  }

  protected function calculateMonthlyEmiUncollectedInterest($loanQuery, Carbon $month): float
  {
    $monthStart = $month->copy()->startOfMonth();
    $monthEnd = $month->copy()->endOfMonth();

    $loans = $loanQuery
      ->where(function ($q) {
        $q->whereNull('loan_mode')->orWhere('loan_mode', '!=', 'interest_only');
      })
      ->whereHas('loanApplication', function ($q) {
        $q->where(function ($tq) {
          $tq->whereNull('term_unit')
            ->orWhereRaw("LOWER(TRIM(term_unit)) IN ('month', 'months', 'monthly')");
        });
      })
      ->with(['emis' => function ($q) use ($monthStart, $monthEnd) {
        $q->whereBetween('due_date', [$monthStart, $monthEnd])
          ->whereRaw("LOWER(TRIM(status)) != 'paid'");
      }])
      ->get();

    $uncollected = 0.0;
    foreach ($loans as $loan) {
      foreach ($loan->emis as $emi) {
        $interestCollected = max(0.00, min((float) $emi->paid_amount, (float) $emi->interest_amount));
        $uncollected += max(0.00, (float) $emi->interest_amount - $interestCollected);
      }
    }

    return round($uncollected, 2);
  }

  protected function buildEmiChartData($emiQuery, $periodKey, $periodFrom, $periodTo)
  {
    $now = Carbon::now();

    if ($periodFrom && $periodTo && $periodKey !== 'today') {
      $emiWindowStart = $periodFrom->copy()->startOfDay();
      $emiWindowEnd = $periodTo->copy()->endOfDay();
    } elseif ($periodKey === 'today') {
      $emiWindowStart = $now->copy()->startOfDay();
      $emiWindowEnd = $now->copy()->endOfDay();
    } else {
      $defaultWindowStart = $now->copy()->subMonths(11)->startOfMonth();
      $defaultWindowEnd = $now->copy()->endOfMonth();
      $firstDueDate = (clone $emiQuery)->whereNotNull('due_date')->orderBy('due_date')->value('due_date');
      $emiWindowStart = $defaultWindowStart;

      if ($firstDueDate) {
        $firstDueMonth = Carbon::parse($firstDueDate)->startOfMonth();
        if ($firstDueMonth->gt($defaultWindowEnd)) {
          $emiWindowStart = $firstDueMonth;
        }
      }
      $emiWindowEnd = $emiWindowStart->copy()->addMonthsNoOverflow(11)->endOfMonth();
    }

    $diffInDays = $emiWindowStart->diffInDays($emiWindowEnd);
    $isDaily = ($diffInDays <= 31);

    $buckets = [];
    if ($isDaily) {
      $curr = $emiWindowStart->copy();
      while ($curr->lte($emiWindowEnd)) {
        $buckets[] = [
          'key' => $curr->format('Y-m-d'),
          'label' => $curr->format('d M Y'),
        ];
        $curr->addDay();
      }
    } else {
      $startMonthIndex = ((int) $emiWindowStart->format('Y')) * 12 + ((int) $emiWindowStart->format('n'));
      $endMonthIndex = ((int) $emiWindowEnd->format('Y')) * 12 + ((int) $emiWindowEnd->format('n'));
      $chartMonthCount = max(1, $endMonthIndex - $startMonthIndex + 1);
      if ($chartMonthCount > 12) {
        $emiWindowStart = $emiWindowEnd->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $chartMonthCount = 12;
      }

      for ($i = 0; $i < $chartMonthCount; $i++) {
        $monthDate = $emiWindowStart->copy()->startOfMonth()->addMonthsNoOverflow($i);
        $buckets[] = [
          'key' => $monthDate->format('Y-m'),
          'label' => $monthDate->format('M Y'),
        ];
      }
    }

    $dateFormat = $isDaily ? '%Y-%m-%d' : '%Y-%m';

    $dueQuery = (clone $emiQuery)
      ->whereNotNull('due_date')
      ->whereBetween('due_date', [$emiWindowStart->toDateString(), $emiWindowEnd->toDateString()]);

    $dueRaw = $dueQuery->select(
      DB::raw("DATE_FORMAT(due_date, '{$dateFormat}') as bucket_key"),
      DB::raw('SUM(total_amount) as due_amount'),
      DB::raw('SUM(CASE WHEN LOWER(TRIM(status)) = "paid" THEN 0 WHEN LOWER(TRIM(status)) = "partial" THEN GREATEST(total_amount - COALESCE(partial_paid_amount, paid_amount, 0), 0) ELSE total_amount END) as pending_amount')
    )->groupBy('bucket_key')->get()->keyBy('bucket_key');

    $matchingEmiSubquery = (clone $emiQuery)->select('emis.id');

    $collectionQuery = EmiCollection::whereIn('emi_id', $matchingEmiSubquery)
      ->whereIn('status', ['verified', 'in_progress', 'approved', 'success', 'paid'])
      ->where(function ($q) use ($emiWindowStart, $emiWindowEnd) {
        $q->whereBetween('collected_at', [$emiWindowStart, $emiWindowEnd])
          ->orWhere(function ($q2) use ($emiWindowStart, $emiWindowEnd) {
            $q2->whereNull('collected_at')
              ->whereBetween('created_at', [$emiWindowStart, $emiWindowEnd]);
          });
      });

    $collectedRaw = $collectionQuery->select(
      DB::raw("DATE_FORMAT(COALESCE(collected_at, created_at), '{$dateFormat}') as bucket_key"),
      DB::raw('SUM(amount) as collected_amount')
    )->groupBy('bucket_key')->get()->keyBy('bucket_key');

    $labels = [];
    $collected = [];
    $pending = [];

    foreach ($buckets as $b) {
      $key = $b['key'];
      $dueItem = $dueRaw->get($key);
      $collectedItem = $collectedRaw->get($key);

      $labels[] = $b['label'];
      $colVal = $collectedItem ? (float) $collectedItem->collected_amount : 0.0;
      $collected[] = round($colVal, 2);

      $dueVal = $dueItem ? (float) $dueItem->due_amount : 0.0;
      $pendVal = $dueItem ? (float) $dueItem->pending_amount : max(0.0, $dueVal - $colVal);
      $pending[] = round($pendVal, 2);
    }

    return [
      'labels' => $labels,
      'collected' => $collected,
      'pending' => $pending,
      'hasData' => (array_sum($collected) + array_sum($pending)) > 0,
    ];
  }
}
