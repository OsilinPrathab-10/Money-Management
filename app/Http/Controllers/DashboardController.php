<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Emi;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
  private function getDateBounds(Request $request)
  {
    $dateRange = $request->get('date_range', 'all');
    $startDate = null;
    $endDate = null;
    $label = 'All Time';

    switch ($dateRange) {
      case 'today':
        $startDate = Carbon::today()->startOfDay();
        $endDate = Carbon::today()->endOfDay();
        $label = 'Today (' . $startDate->format('d M Y') . ')';
        break;
      case 'week':
        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();
        $label = 'This Week (' . $startDate->format('d M') . ' - ' . $endDate->format('d M Y') . ')';
        break;
      case 'month':
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        $label = 'This Month (' . $startDate->format('M Y') . ')';
        break;
      case '3_months':
        $startDate = Carbon::now()->subMonths(3)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $label = 'Last 3 Months';
        break;
      case 'year':
        $startDate = Carbon::now()->startOfYear();
        $endDate = Carbon::now()->endOfYear();
        $label = 'This Year (' . $startDate->format('Y') . ')';
        break;
      case 'custom':
        $rawStart = $request->get('start_date');
        $rawEnd = $request->get('end_date');
        if ($rawStart && $rawEnd) {
          try {
            $startDate = Carbon::parse($rawStart)->startOfDay();
            $endDate = Carbon::parse($rawEnd)->endOfDay();
            $label = $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y');
          } catch (\Exception $e) {
            $dateRange = 'all';
            $label = 'All Time';
          }
        } else {
          $dateRange = 'all';
          $label = 'All Time';
        }
        break;
      case 'all':
      default:
        $dateRange = 'all';
        $label = 'All Time';
        break;
    }

    return [
      'dateRange' => $dateRange,
      'startDate' => $startDate,
      'endDate' => $endDate,
      'label' => $label,
      'rawStart' => $request->get('start_date', ''),
      'rawEnd' => $request->get('end_date', ''),
    ];
  }

  public function index(Request $request)
  {
    if (Auth::check() && Auth::user()->hasRole('CreditVerifier')) {
      return redirect()->route('verification-credit-score-history');
    }

    $data = $this->getDashboardData($request);

    return view('admin.dashboard', $data);
  }

  public function getStats(Request $request)
  {
    $data = $this->getDashboardData($request);

    return response()->json([
      'success' => true,
      'date_range' => $data['dateRange'],
      'date_label' => $data['dateFilterLabel'],
      'stats' => [
        'totalAgents' => number_format($data['totalAgents']),
        'totalStaff' => number_format($data['totalStaff']),
        'totalClients' => number_format($data['totalClients']),
        'activeClients' => number_format($data['activeClients']),
        'inactiveClients' => number_format($data['inactiveClients']),
        'pendingClients' => number_format($data['pendingClients']),
        'blacklistedClients' => number_format($data['blacklistedClients']),
        'totalLoans' => number_format($data['totalLoans']),
        'activeLoans' => number_format($data['activeLoans']),
        'pendingApplications' => number_format($data['pendingApplications']),
        'totalDisbursedAmount' => number_format($data['totalDisbursedAmount'], 2),
        'totalOutstandingAmount' => number_format($data['totalOutstandingAmount'], 2),
        'uncollectedInterest' => number_format($data['uncollectedInterest'], 2),
        'overdueLoans' => number_format($data['overdueLoans']),
        'revenueThisMonth' => number_format($data['revenueThisMonth'], 2),
        'totalRevenue' => number_format($data['totalRevenue'], 2),
      ],
      'charts' => [
        'emiChart' => $data['emiChart'],
        'loanDistribution' => $data['loanDistributionChart']
      ]
    ]);
  }

  private function getDashboardData(Request $request)
  {
    $bounds = $this->getDateBounds($request);
    $dateRange = $bounds['dateRange'];
    $startDate = $bounds['startDate'];
    $endDate = $bounds['endDate'];
    $dateFilterLabel = $bounds['label'];

    $totalAgents = Agent::count();
    $totalStaff = \App\Models\Staff::count();

    $currentUser = Auth::user();
    $isAgent = $currentUser->hasRole('Agent');
    $agentId = $isAgent ? optional($currentUser->agent)->id : null;

    $clientQuery = Client::query();
    if ($isAgent && $agentId) {
      $clientQuery->where(function($q) use ($agentId) {
        $q->where('assigned_to', $agentId)
          ->orWhere('added_by', $agentId);
      });
    }

    if ($startDate && $endDate) {
      $clientQuery->whereBetween('created_at', [$startDate, $endDate]);
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
      $loanQuery->whereHas('client', function($q) use ($agentId) {
        $q->where('assigned_to', $agentId)
          ->orWhere('added_by', $agentId);
      });
    }

    if ($startDate && $endDate) {
      $loanQuery->whereBetween('created_at', [$startDate, $endDate]);
    }

    $loanAggregates = (clone $loanQuery)->selectRaw("LOWER(TRIM(status)) as normalized_status")
      ->selectRaw('COUNT(*) as total')
      ->groupBy('normalized_status')
      ->pluck('total', 'normalized_status');

    $totalLoans = $loanAggregates->sum();
    $activeLoans = (int) ($loanAggregates['active'] ?? 0);

    $appQuery = LoanApplication::query();
    if ($isAgent && $agentId) {
      $appQuery->whereHas('client', function($q) use ($agentId) {
        $q->where('assigned_to', $agentId)
          ->orWhere('added_by', $agentId);
      });
    }

    if ($startDate && $endDate) {
      $appQuery->whereBetween('created_at', [$startDate, $endDate]);
    }

    $pendingApplications = (clone $appQuery)->whereRaw("LOWER(TRIM(status)) = 'pending'")->count();
    $totalDisbursedAmount = (clone $loanQuery)->sum('loan_amount');
    $totalOutstandingAmount = (clone $loanQuery)->sum('outstanding_amount');

    $uncollectedInterest = 0;
    $loansForUncollected = (clone $loanQuery)->where('status', 'active')->with('emis')->get();
    foreach ($loansForUncollected as $loan) {
      foreach ($loan->emis as $emi) {
        if ($startDate && $endDate && $emi->due_date) {
          $dueDate = Carbon::parse($emi->due_date);
          if ($dueDate->lt($startDate) || $dueDate->gt($endDate)) {
            continue;
          }
        }
        $interestCollected = 0;
        if ($loan->loan_mode === 'interest_only') {
          $interestCollected = max(0.00, (float)$emi->paid_amount - (float)$emi->principal_amount);
        } else {
          $interestCollected = min((float)$emi->paid_amount, (float)$emi->interest_amount);
        }
        $uncollectedInterest += max(0.00, (float)$emi->interest_amount - $interestCollected);
      }
    }

    $emiQuery = Emi::query();
    if ($isAgent && $agentId) {
      $emiQuery->whereHas('loanAccount.client', function($q) use ($agentId) {
        $q->where('assigned_to', $agentId)
          ->orWhere('added_by', $agentId);
      });
    }

    $overdueQuery = clone $emiQuery;
    if ($startDate && $endDate) {
      $overdueQuery->whereBetween('due_date', [$startDate, $endDate]);
    }
    $overdueLoans = $overdueQuery->whereRaw("LOWER(TRIM(status)) = 'overdue'")
      ->distinct('loan_account_id')
      ->count('loan_account_id');

    $revenueEmiQuery = (clone $emiQuery)->whereRaw("LOWER(TRIM(status)) = 'paid'")
      ->whereNotNull('paid_date');

    if ($startDate && $endDate) {
      $revenueEmiQuery->whereBetween('paid_date', [$startDate, $endDate]);
    } else {
      $revenueEmiQuery->whereMonth('paid_date', Carbon::now()->month)
        ->whereYear('paid_date', Carbon::now()->year);
    }
    $emiRevenue = $revenueEmiQuery->sum(DB::raw('COALESCE(interest_amount, 0) + COALESCE(penalty_amount, 0)'));

    $revenueAppQuery = (clone $appQuery)->whereRaw("LOWER(TRIM(status)) = 'disbursed'")
      ->with('applicationDetail');

    if ($startDate && $endDate) {
      $revenueAppQuery->whereBetween('disbursed_at', [$startDate, $endDate]);
    } else {
      $revenueAppQuery->whereMonth('disbursed_at', Carbon::now()->month)
        ->whereYear('disbursed_at', Carbon::now()->year);
    }
    $loansDisbursedInPeriod = $revenueAppQuery->get();

    $feeRevenue = $loansDisbursedInPeriod->sum(function($loan) {
        $details = $loan->applicationDetail->details ?? [];
        return (float)($details['applied_processing_fee'] ?? 0) 
             + (float)($details['applied_document_charges'] ?? 0) 
             + (float)($details['applied_other_charges'] ?? 0);
    });

    $revenueThisMonth = $emiRevenue + $feeRevenue;

    $revenueLoanQuery = LoanAccount::with(['loanApplication.applicationDetail', 'emis']);
    if ($startDate && $endDate) {
      $revenueLoanQuery->whereBetween('created_at', [$startDate, $endDate]);
    }
    $allLoansForRevenue = $revenueLoanQuery->get();
    
    $totalProcessingFees = 0;
    $totalDocumentCharges = 0;
    $totalOtherCharges = 0;
    $totalInterestCollected = 0;
    $totalForeclosureRevenue = 0;
    $totalPenaltyAmount = 0;

    foreach ($allLoansForRevenue as $loan) {
        $details = $loan->loanApplication->applicationDetail->details ?? [];
        $processingFee = (float)($details['applied_processing_fee'] ?? 0);
        $documentCharges = (float)($details['applied_document_charges'] ?? 0);
        $otherCharges = (float)($details['applied_other_charges'] ?? 0);

        $interestCollected = $loan->emis->filter(function($emi) use ($startDate, $endDate) {
          if ($startDate && $endDate && $emi->paid_date) {
            $paidDate = Carbon::parse($emi->paid_date);
            return $paidDate->gte($startDate) && $paidDate->lte($endDate);
          }
          return true;
        })->sum(function ($emi) use ($loan) {
            if ($loan->loan_mode === 'interest_only') {
                return max(0.00, (float)$emi->paid_amount - (float)$emi->principal_amount);
            } else {
                return min((float)$emi->paid_amount, (float)$emi->interest_amount);
            }
        });

        $foreclosureRevenue = 0;
        if ($loan->is_foreclosed && $loan->foreclosure_amount > 0) {
            $chargesPercentage = $loan->foreclosure_charges_percentage ?? $loan->getForeclosureChargesPercentage();
            $multiplier = 1 + ($chargesPercentage / 100);
            $outstanding = $loan->foreclosure_amount / $multiplier;
            $foreclosureRevenue = $loan->foreclosure_amount - $outstanding;
        }

        $penaltyAmount = $loan->emis->filter(function($emi) use ($startDate, $endDate) {
          if ($startDate && $endDate && $emi->paid_date) {
            $paidDate = Carbon::parse($emi->paid_date);
            return $paidDate->gte($startDate) && $paidDate->lte($endDate);
          }
          return true;
        })->sum('penalty_amount');

        $totalProcessingFees += $processingFee;
        $totalDocumentCharges += $documentCharges;
        $totalOtherCharges += $otherCharges;
        $totalInterestCollected += $interestCollected;
        $totalForeclosureRevenue += $foreclosureRevenue;
        $totalPenaltyAmount += $penaltyAmount;
    }

    $totalRevenue = $totalProcessingFees + $totalDocumentCharges + $totalOtherCharges + $totalInterestCollected + $totalForeclosureRevenue + $totalPenaltyAmount;

    $now = Carbon::now();
    if ($startDate && $endDate) {
      $winStart = $startDate->copy()->startOfMonth();
      $winEnd = $endDate->copy()->endOfMonth();
    } else {
      $winStart = $now->copy()->subMonths(5)->startOfMonth();
      $winEnd = $now->copy()->endOfMonth();
    }

    $chartEmiQuery = (clone $emiQuery)->select(
      DB::raw('DATE_FORMAT(due_date, "%Y-%m") as month_key'),
      DB::raw('SUM(total_amount) as due_amount'),
      DB::raw('SUM(CASE WHEN status = "paid" THEN COALESCE(paid_amount, total_amount) ELSE 0 END) as collected_amount')
    )
      ->whereNotNull('due_date')
      ->whereBetween('due_date', [$winStart, $winEnd])
      ->groupBy('month_key')
      ->orderBy('month_key');

    $emiCollectionRaw = $chartEmiQuery->get();

    $monthsCount = (int) max(1, ($winEnd->year - $winStart->year) * 12 + ($winEnd->month - $winStart->month) + 1);
    if ($monthsCount > 12) {
      $monthsCount = 12;
      $winStart = $winEnd->copy()->subMonths(11)->startOfMonth();
    }

    $emiMonthsWindow = collect(range(0, (int) $monthsCount - 1))->map(fn($i) => $winStart->copy()->addMonthsNoOverflow($i));

    $emiChartBuckets = $emiMonthsWindow->map(function (Carbon $date) use ($emiCollectionRaw) {
      $monthKey = $date->format('Y-m');
      $match = $emiCollectionRaw->firstWhere('month_key', $monthKey);

      $due = $match ? (float) $match->due_amount : 0.0;
      $collected = $match ? (float) $match->collected_amount : 0.0;

      return [
        'label' => $date->format('M Y'),
        'collected' => round($collected, 2),
        'pending' => round(max($due - $collected, 0), 2),
      ];
    });

    $emiChart = [
      'labels' => $emiChartBuckets->pluck('label')->values()->all(),
      'collected' => $emiChartBuckets->pluck('collected')->values()->all(),
      'pending' => $emiChartBuckets->pluck('pending')->values()->all(),
      'hasData' => ($emiChartBuckets->sum('collected') + $emiChartBuckets->sum('pending')) > 0,
    ];

    $loanPerfQuery = LoanAccount::selectRaw(
      'COALESCE(loan_products.loan_name, loan_accounts.loan_code, "Unknown") as loan_name'
    )
      ->selectRaw('COUNT(loan_accounts.id) as loan_count')
      ->selectRaw('SUM(COALESCE(loan_accounts.loan_amount, 0)) as total_disbursed')
      ->leftJoin('loan_products', 'loan_products.loan_code', '=', 'loan_accounts.loan_code');

    if ($startDate && $endDate) {
      $loanPerfQuery->whereBetween('loan_accounts.created_at', [$startDate, $endDate]);
    }

    $loanPerformanceRaw = $loanPerfQuery->groupBy('loan_products.loan_name', 'loan_accounts.loan_code')
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
      'series' => $loanPerformanceList->pluck('loan_count')->map(fn($value) => (int) $value)->values()->all(),
      'total' => $loanPerformanceTotalCount,
      'hasData' => $loanPerformanceList->isNotEmpty(),
    ];

    $recentAppQuery = LoanApplication::with(['client.user:id,name', 'client.location', 'product:loan_code,loan_name,loan_amount_max']);
    if ($startDate && $endDate) {
      $recentAppQuery->whereBetween('created_at', [$startDate, $endDate]);
    }
    $recentApplications = $recentAppQuery->orderByDesc('created_at')
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

    $clientPeriodLabel = $dateFilterLabel;

    return compact(
      'dateRange',
      'dateFilterLabel',
      'bounds',
      'totalAgents',
      'totalStaff',
      'totalClients',
      'totalLoans',
      'activeLoans',
      'pendingApplications',
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
      'emiChart',
      'loanPerformanceChart',
      'loanDistributionChart',
      'loanPerformanceList',
      'loanPerformanceTotalAmount',
      'loanPerformanceTotalCount',
      'recentApplications',
      'clientActivityPercentage'
    );
  }
}
