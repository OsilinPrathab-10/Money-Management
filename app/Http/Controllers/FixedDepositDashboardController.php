<?php

namespace App\Http\Controllers;

use App\Models\FixedDeposit;
use App\Models\FixedDepositRenewal;
use App\Models\FixedDepositTransaction;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FixedDepositDashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.fd.dashboard', $this->getDashboardData($request));
    }

    public function getDashboardData(?Request $request = null): array
    {
        $request = $request ?? request();
        $period = $this->resolveFdPeriod($request);
        $from = $period['from'];
        $to = $period['to'];

        $today = Carbon::today();
        $upcomingEnd = $today->copy()->addDays(30);

        // Active portfolio metrics (represent current active commitments / portfolio state)
        $totalDeposits = FixedDeposit::count();
        $activeDeposits = FixedDeposit::where('status', 'active');
        $totalActiveDeposits = (clone $activeDeposits)->count();
        $fdClosedDeposits = FixedDeposit::whereIn('status', ['closed', 'matured', 'completed', 'withdrawn', 'premature_closed', 'renewed'])->count();
        $totalDepositAmount = (float) (clone $activeDeposits)->sum('deposit_amount');
        $totalInterestLiability = (float) (clone $activeDeposits)->sum('interest_amount');
        $fdActiveSystemClients = \App\Models\Client::whereIn('status', ['active', 'verified'])->count();

        // Deposits created within the selected period (flow metric)
        $periodDepositsQuery = FixedDeposit::query();
        $this->applyDateRange($periodDepositsQuery, 'deposit_date', $from, $to);
        $periodNewDepositsCount = (clone $periodDepositsQuery)->count();
        $periodNewDepositsAmount = (float) (clone $periodDepositsQuery)->sum('deposit_amount');

        // Maturity alerts stay operational (as of today), not period-scoped.
        $todaysMaturity = FixedDeposit::where('status', 'active')
            ->whereDate('maturity_date', $today)
            ->count();

        $upcomingMaturity = FixedDeposit::where('status', 'active')
            ->whereDate('maturity_date', '>', $today)
            ->whereDate('maturity_date', '<=', $upcomingEnd)
            ->count();

        $overdueMaturity = FixedDeposit::where('status', 'active')
            ->whereDate('maturity_date', '<', $today)
            ->count();

        $walletCreditsQuery = WalletTransaction::where('type', 'credit')
            ->where('reference_type', 'fixed_deposit');
        $this->applyDateRange($walletCreditsQuery, 'created_at', $from, $to);
        $totalWalletCredits = (float) $walletCreditsQuery->sum('amount');

        $chitAdjQuery = FixedDepositTransaction::where('transaction_type', 'chit_adjustment');
        $this->applyDateRange($chitAdjQuery, 'created_at', $from, $to);
        $totalChitAdjustments = (float) $chitAdjQuery->sum('amount');

        $renewalsQuery = FixedDepositRenewal::query();
        $this->applyDateRange($renewalsQuery, 'renewed_at', $from, $to);
        $totalRenewals = $renewalsQuery->count();

        $chartYear = $to ? (int) $to->year : (int) date('Y');
        if ($from && $to && $from->year === $to->year) {
            $chartYear = (int) $from->year;
        }
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyDeposits = array_fill(0, 12, 0.0);
        $monthlyMaturity = array_fill(0, 12, 0.0);
        $interestPaid = array_fill(0, 12, 0.0);
        $interestLiability = array_fill(0, 12, 0.0);
        $depositGrowth = array_fill(0, 12, 0.0);
        $renewalTrends = array_fill(0, 12, 0.0);

        // Yearly charts display full trends for the chart year
        FixedDeposit::whereYear('deposit_date', $chartYear)
            ->get(['deposit_date', 'deposit_amount', 'interest_amount'])
            ->each(function ($fd) use (&$monthlyDeposits, &$interestLiability, &$depositGrowth) {
                $idx = Carbon::parse($fd->deposit_date)->month - 1;
                $monthlyDeposits[$idx] += (float) $fd->deposit_amount;
                $interestLiability[$idx] += (float) $fd->interest_amount;
                $depositGrowth[$idx] += (float) $fd->deposit_amount;
            });

        FixedDeposit::whereYear('maturity_date', $chartYear)
            ->whereNotNull('maturity_processed_at')
            ->get(['maturity_date', 'maturity_amount', 'interest_amount'])
            ->each(function ($fd) use (&$monthlyMaturity, &$interestPaid) {
                $idx = Carbon::parse($fd->maturity_date)->month - 1;
                $monthlyMaturity[$idx] += (float) $fd->maturity_amount;
                $interestPaid[$idx] += (float) $fd->interest_amount;
            });

        FixedDepositRenewal::whereYear('renewed_at', $chartYear)
            ->get(['renewed_at'])
            ->each(function ($row) use (&$renewalTrends) {
                $idx = Carbon::parse($row->renewed_at)->month - 1;
                $renewalTrends[$idx] += 1;
            });

        $recentDeposits = FixedDeposit::with(['client', 'scheme'])
            ->latest('deposit_date')
            ->limit(10)
            ->get();

        $upcomingList = FixedDeposit::with(['client', 'scheme'])
            ->where('status', 'active')
            ->whereDate('maturity_date', '>=', $today)
            ->whereDate('maturity_date', '<=', $upcomingEnd)
            ->orderBy('maturity_date')
            ->limit(10)
            ->get();

        $totalFdClients = FixedDeposit::whereNotNull('client_id')->distinct('client_id')->count('client_id');
        $activeFdClients = FixedDeposit::where('status', 'active')->whereNotNull('client_id')->distinct('client_id')->count('client_id');

        return [
            'fdTotalDeposits' => $totalDeposits,
            'fdTotalActiveDeposits' => $totalActiveDeposits,
            'fdClosedDeposits' => $fdClosedDeposits,
            'fdTotalDepositAmount' => $totalDepositAmount,
            'fdTotalInterestLiability' => $totalInterestLiability,
            'fdPeriodNewDepositsCount' => $periodNewDepositsCount,
            'fdPeriodNewDepositsAmount' => $periodNewDepositsAmount,
            'fdTodaysMaturity' => $todaysMaturity,
            'fdUpcomingMaturity' => $upcomingMaturity,
            'fdOverdueMaturity' => $overdueMaturity,
            'fdTotalWalletCredits' => $totalWalletCredits,
            'fdTotalChitAdjustments' => $totalChitAdjustments,
            'fdTotalRenewals' => $totalRenewals,
            'fdTotalClients' => $totalFdClients,
            'fdActiveClients' => $activeFdClients,
            'fdActiveSystemClients' => $fdActiveSystemClients,
            'fdMonths' => $months,
            'fdMonthlyDeposits' => $monthlyDeposits,
            'fdMonthlyMaturity' => $monthlyMaturity,
            'fdInterestPaid' => $interestPaid,
            'fdInterestLiabilityChart' => $interestLiability,
            'fdDepositGrowth' => $depositGrowth,
            'fdRenewalTrends' => $renewalTrends,
            'fdRecentDeposits' => $recentDeposits,
            'fdUpcomingList' => $upcomingList,
            'fdPeriodKey' => $period['key'],
            'fdPeriodLabel' => $period['label'],
            'fdPeriodFromInput' => $period['from_input'],
            'fdPeriodToInput' => $period['to_input'],
        ];
    }

    /**
     * @return array{key: string, label: string, from: ?Carbon, to: ?Carbon, from_input: ?string, to_input: ?string}
     */
    protected function resolveFdPeriod(Request $request): array
    {
        $now = Carbon::now();
        // Prefer fd_period; on standalone FD dashboard also accept generic period.
        $key = $request->get('fd_period');
        if ($key === null || $key === '') {
            $key = $request->get('period', 'all');
        }
        if (! in_array($key, ['today', 'month', 'year', 'custom', 'all'], true)) {
            $key = 'all';
        }

        $fromInput = $request->get('fd_from', $request->get('period_from'));
        $toInput = $request->get('fd_to', $request->get('period_to'));

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

    protected function applyDateRange($query, string $column, ?Carbon $from, ?Carbon $to)
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<=', $to);
        }

        return $query;
    }
}
