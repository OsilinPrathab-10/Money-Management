<?php

namespace App\Http\Controllers;

use App\Models\ChitCollection;
use App\Models\ChitGroup;
use App\Models\ChitScheme;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\Payout;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ChitDashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.chit.dashboard', $this->getDashboardData($request));
    }

    /**
     * Shared chit dashboard metrics for standalone page and main dashboard tab.
     * Only non-deleted active groups contribute to totals and listings.
     */
    public function getDashboardData(?Request $request = null): array
    {
        $request = $request ?: request();
        $period = $this->resolveChitPeriod($request);
        $periodKey = $period['key'];
        $periodLabel = $period['label'];
        $periodFrom = $period['from'];
        $periodTo = $period['to'];
        $periodFromInput = $period['from_input'];
        $periodToInput = $period['to_input'];

        $today = Carbon::today();
        $pendingTo = $periodTo ? $periodTo->copy()->endOfDay() : $today->copy()->endOfMonth();
        if ($pendingTo->lt($today)) {
            $pendingTo = $today->copy()->endOfDay();
        }

        // Live groups = active (running) + forming (filling seats)
        $activeGroupIds = ChitGroup::where('status', 'active')->pluck('id');
        $liveGroupIds = ChitGroup::whereIn('status', ['active', 'forming'])->pluck('id');

        $collectionTotals = $this->calculateChitCollectionTotals($periodFrom, $periodTo, $activeGroupIds);
        $adminCollections = $collectionTotals['admin'];
        $agentCollections = $collectionTotals['agent'];
        $periodCollections = $collectionTotals['total'];
        $totalCollections = $periodCollections;

        $settlementsQuery = Payout::query()->where('status', 'paid');
        if ($activeGroupIds->isNotEmpty()) {
            $settlementsQuery->whereIn('group_id', $activeGroupIds);
        } else {
            $settlementsQuery->whereRaw('1 = 0');
        }
        $this->applyDateRange($settlementsQuery, 'paid_date', $periodFrom, $periodTo);
        $totalSettlements = (float) $settlementsQuery->sum('payout_amount');

        $foremanCommissionQuery = Payout::query()->where('status', 'paid');
        $allGroupIds = ChitGroup::whereIn('status', ['active', 'forming', 'completed', 'closed'])->pluck('id');
        if ($allGroupIds->isNotEmpty()) {
            $foremanCommissionQuery->whereIn('group_id', $allGroupIds);
        }
        $this->applyDateRange($foremanCommissionQuery, 'paid_date', $periodFrom, $periodTo);
        $payoutCommissionSum = (float) $foremanCommissionQuery->sum('commission_amount');

        $groupForemanSum = 0.0;
        $activeGroups = ChitGroup::with('scheme')->whereIn('status', ['active', 'completed'])->get();
        foreach ($activeGroups as $grp) {
            $groupForemanSum += $grp->recognizedForemanCommissionAmount();
        }

        $totalForemanCommission = max($payoutCommissionSum, $groupForemanSum);

        $totalDistributedDividends = $activeGroupIds->isNotEmpty()
            ? (float) \App\Models\Dividend::whereIn('group_id', $activeGroupIds)->sum('net_dividend')
            : 0.0;

        $groupBalance = $totalCollections - $totalSettlements - $totalForemanCommission;
        $dividendPoolTotal = $activeGroupIds->isNotEmpty()
            ? (float) ChitGroup::whereIn('id', $activeGroupIds)->sum('dividend_pool_balance')
            : 0.0;
        $availableGroupFunds = $groupBalance + $dividendPoolTotal;

        $activeGroupsCount = $activeGroupIds->count();
        $formingGroupsCount = ChitGroup::where('status', 'forming')->count();
        $completedGroupsCount = ChitGroup::whereIn('status', ['completed', 'closed'])->count();
        $totalGroupsCount = $activeGroupsCount + $formingGroupsCount + $completedGroupsCount;
        $deletedGroupsCount = ChitGroup::onlyTrashed()->count();
        $totalSchemesCount = ChitScheme::where('status', 'active')->count();

        $totalActiveChitSeats = GroupMember::whereHas('group', fn ($q) => $q->whereIn('status', ['active', 'forming']))
            ->whereNotIn('status', GroupMember::INACTIVE_STATUSES)
            ->count();

        $activeSystemClients = \App\Models\Client::whereIn('status', ['active', 'verified'])->count();

        $installmentStatusCounts = $this->calculateInstallmentStatusCounts(
            $liveGroupIds,
            $today,
            $pendingTo,
            $periodFrom,
            $periodTo
        );
        $pendingInstallments = $installmentStatusCounts['pending'];
        $overdueInstallments = $installmentStatusCounts['overdue'];

        $clientStats = $this->calculateChitClientStats($liveGroupIds);
        $totalChitClients = $clientStats['total'];
        $activeChitClients = $clientStats['active'];
        $totalMembersCount = $activeChitClients;

        $groups = ChitGroup::with([
            'scheme' => fn ($q) => $q->withTrashed(),
            'members',
            'payouts' => function ($q) use ($periodFrom, $periodTo) {
                $q->with(['winner.client', 'winner.shares.client'])
                    ->where('status', 'paid');
                $this->applyDateRange($q, 'paid_date', $periodFrom, $periodTo);
                $q->orderByDesc('paid_date')->orderByDesc('id');
            },
        ])
            ->whereIn('status', ['active', 'forming'])
            ->orderByRaw("FIELD(status, 'active', 'forming')")
            ->latest()
            ->get();

        $groupWiseSummary = $groups->map(function ($group) use ($periodFrom, $periodTo) {
            $collectionsQuery = $group->installments()
                ->whereIn('status', ['paid', 'partial'])
                ->whereHas('member', function ($mq) {
                    $mq->whereNotIn('status', GroupMember::INACTIVE_STATUSES)
                        ->whereNotNull('client_id');
                });
            $this->applyDateRange($collectionsQuery, 'paid_date', $periodFrom, $periodTo);
            $collections = (float) $collectionsQuery->sum('paid_amount');
            $settlements = (float) $group->payouts->sum('payout_amount');
            $foremanCommission = (float) $group->recognizedForemanCommissionAmount();
            $balance = $collections - $settlements - $foremanCommission;
            $dividendPool = (float) ($group->dividend_pool_balance ?? 0);
            $availableFunds = $balance + $dividendPool;
            $latestSettlement = $group->payouts->first();

            return [
                'id' => $group->id,
                'group_code' => $group->group_code,
                'scheme_name' => $group->scheme?->name ?? '—',
                'scheme_type' => $group->scheme_type_label,
                'members_count' => $group->occupiedSeats(),
                'members_enrolled' => $group->valid_members_count,
                'total_members' => $group->total_members,
                'collections' => $collections,
                'settlements' => $settlements,
                'balance' => $balance,
                'dividend_pool' => $dividendPool,
                'available_funds' => $availableFunds,
                'status' => $group->status,
                'status_badge' => $group->status_badge,
                'settlement_to' => $latestSettlement?->winner?->owners_display
                    ?? $latestSettlement?->winner?->client?->client_name
                    ?? null,
                'settlement_to_member' => $latestSettlement?->winner?->member_number,
                'settlement_month' => $latestSettlement?->month_number,
            ];
        });

        $recentSettlementsQuery = Payout::with([
            'group.scheme' => fn ($q) => $q->withTrashed(),
            'winner.client',
            'winner.shares.client',
        ])->where('status', 'paid');
        if ($activeGroupIds->isNotEmpty()) {
            $recentSettlementsQuery->whereIn('group_id', $activeGroupIds);
        } else {
            $recentSettlementsQuery->whereRaw('1 = 0');
        }
        $this->applyDateRange($recentSettlementsQuery, 'paid_date', $periodFrom, $periodTo);

        $recentSettlements = $recentSettlementsQuery
            ->orderByDesc('paid_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Payout $payout) {
                return [
                    'id' => $payout->id,
                    'payout_code' => $payout->payout_code,
                    'group_id' => $payout->group_id,
                    'group_code' => $payout->group->group_code ?? '—',
                    'scheme_name' => $payout->group->scheme?->name ?? '—',
                    'month_number' => $payout->month_number,
                    'settlement_to' => $payout->winner?->owners_display
                        ?? $payout->winner?->client?->client_name
                        ?? '—',
                    'member_number' => $payout->winner?->member_number,
                    'amount' => (float) $payout->payout_amount,
                    'paid_date' => $payout->paid_date?->format('d M Y') ?? '—',
                    'payment_mode' => $payout->payment_mode_label,
                ];
            });

        $trendYear = (int) ($periodTo?->year ?? $periodFrom?->year ?? date('Y'));

        $installmentsThisYearQuery = Installment::query()
            ->whereIn('status', ['paid', 'partial'])
            ->whereYear('paid_date', $trendYear);
        if ($activeGroupIds->isNotEmpty()) {
            $installmentsThisYearQuery->whereIn('group_id', $activeGroupIds);
        } else {
            $installmentsThisYearQuery->whereRaw('1 = 0');
        }
        $this->applyDateRange($installmentsThisYearQuery, 'paid_date', $periodFrom, $periodTo);
        $installmentsThisYear = $installmentsThisYearQuery->get();

        $payoutsThisYearQuery = Payout::query()
            ->where('status', 'paid')
            ->whereYear('paid_date', $trendYear);
        if ($activeGroupIds->isNotEmpty()) {
            $payoutsThisYearQuery->whereIn('group_id', $activeGroupIds);
        } else {
            $payoutsThisYearQuery->whereRaw('1 = 0');
        }
        $this->applyDateRange($payoutsThisYearQuery, 'paid_date', $periodFrom, $periodTo);
        $payoutsThisYear = $payoutsThisYearQuery->get();

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $collectionsTrend = array_fill(0, 12, 0.0);
        $settlementsTrend = array_fill(0, 12, 0.0);

        foreach ($installmentsThisYear as $inst) {
            if ($inst->paid_date) {
                $monthIndex = Carbon::parse($inst->paid_date)->month - 1;
                if ($monthIndex >= 0 && $monthIndex < 12) {
                    $collectionsTrend[$monthIndex] += (float) $inst->paid_amount;
                }
            }
        }

        foreach ($payoutsThisYear as $payout) {
            if ($payout->paid_date) {
                $monthIndex = Carbon::parse($payout->paid_date)->month - 1;
                if ($monthIndex >= 0 && $monthIndex < 12) {
                    $settlementsTrend[$monthIndex] += (float) $payout->payout_amount;
                }
            }
        }

        return compact(
            'totalCollections',
            'periodCollections',
            'adminCollections',
            'agentCollections',
            'totalSettlements',
            'totalForemanCommission',
            'totalDistributedDividends',
            'groupBalance',
            'dividendPoolTotal',
            'availableGroupFunds',
            'activeGroupsCount',
            'formingGroupsCount',
            'completedGroupsCount',
            'totalGroupsCount',
            'deletedGroupsCount',
            'totalSchemesCount',
            'totalMembersCount',
            'totalActiveChitSeats',
            'pendingInstallments',
            'overdueInstallments',
            'totalChitClients',
            'activeChitClients',
            'activeSystemClients',
            'groupWiseSummary',
            'recentSettlements',
            'months',
            'collectionsTrend',
            'settlementsTrend',
            'periodKey',
            'periodLabel',
            'periodFromInput',
            'periodToInput',
            'trendYear'
        );
    }

    /**
     * @return array{
     *   key: string,
     *   label: string,
     *   from: ?Carbon,
     *   to: ?Carbon,
     *   from_input: ?string,
     *   to_input: ?string
     * }
     */
    protected function resolveChitPeriod(Request $request): array
    {
        $now = Carbon::now();
        // Prefer chit_period. Do not fall back to loan `period` on the main dashboard
        // (that made chit stats track the loan filter when chit_period was absent).
        $key = $request->get('chit_period');
        if ($key === null || $key === '') {
            $key = $request->routeIs('dashboard')
                ? 'month'
                : $request->get('period', 'month');
        }
        if (! in_array($key, ['today', 'month', 'year', 'custom', 'all'], true)) {
            $key = 'month';
        }

        $fromInput = $request->get('chit_from', $request->get('period_from'));
        $toInput = $request->get('chit_to', $request->get('period_to'));

        // Date inputs were submitted without switching Period to Custom.
        if ($key !== 'custom' && ($fromInput || $toInput) && ! $request->filled('chit_period') && ! $request->filled('period')) {
            $key = 'custom';
        }

        if ($key === 'custom') {
            $from = $this->parseChitPeriodDate($fromInput)?->startOfDay() ?? $now->copy()->startOfMonth();
            $to = $this->parseChitPeriodDate($toInput)?->endOfDay() ?? $now->copy()->endOfDay();
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

    protected function parseChitPeriodDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function applyDateRange($query, string $column, ?Carbon $from, ?Carbon $to)
    {
        if ($from) {
            $query->whereDate($column, '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate($column, '<=', $to->toDateString());
        }

        return $query;
    }

    /**
     * @return array{admin: float, agent: float, total: float}
     */
    protected function calculateChitCollectionTotals(?Carbon $from = null, ?Carbon $to = null, $activeGroupIds = null): array
    {
        if ($activeGroupIds === null) {
            $activeGroupIds = ChitGroup::where('status', 'active')->pluck('id');
        }

        $agentIds = \App\Models\Agent::pluck('id')->filter()->all();
        $agentUserIds = \App\Models\Agent::whereNotNull('user_id')->pluck('user_id')->filter()->all();
        $allAgentIdentifiers = array_unique(array_merge($agentIds, $agentUserIds));

        // 1. Calculate from paid/partial installments
        $baseInstQuery = Installment::query()
            ->whereIn('status', ['paid', 'partial'])
            ->where('paid_amount', '>', 0)
            ->whereHas('member', function ($mq) {
                $mq->whereNotIn('status', GroupMember::INACTIVE_STATUSES)
                    ->whereNotNull('client_id');
            });

        if ($activeGroupIds->isNotEmpty()) {
            $baseInstQuery->whereIn('group_id', $activeGroupIds);
        } else {
            $baseInstQuery->whereRaw('1 = 0');
        }

        $this->applyDateRange($baseInstQuery, 'paid_date', $from, $to);

        $agentInstQuery = (clone $baseInstQuery)->where(function ($q) use ($allAgentIdentifiers) {
            if (!empty($allAgentIdentifiers)) {
                $q->whereIn('collected_by', $allAgentIdentifiers);
            }
            $q->orWhereHas('collections', function ($cq) {
                $cq->whereNotNull('agent_id');
            });
        });

        $totalInstSum = (float) (clone $baseInstQuery)->sum('paid_amount');
        $agentInstSum = (float) $agentInstQuery->sum('paid_amount');
        $adminInstSum = max(0.0, $totalInstSum - $agentInstSum);

        // 2. Add any pending in_progress chit collections from agents not yet recorded in installments
        $pendingAgentCollQuery = ChitCollection::query()
            ->whereNotNull('agent_id')
            ->where('status', 'in_progress');
        $this->applyDateRange($pendingAgentCollQuery, 'collected_at', $from, $to);
        $pendingAgentCollSum = (float) $pendingAgentCollQuery->sum('amount');

        $agentTotal = $agentInstSum + $pendingAgentCollSum;
        $adminTotal = $adminInstSum;

        // 3. Fallback to ChitCollection table if installment table has no paid sum but ChitCollection does
        if ($totalInstSum <= 0) {
            $adminQuery = ChitCollection::query()
                ->whereNull('agent_id')
                ->whereIn('status', ['verified', 'in_progress']);
            $this->applyDateRange($adminQuery, 'collected_at', $from, $to);

            $agentQuery = ChitCollection::query()
                ->whereNotNull('agent_id')
                ->whereIn('status', ['verified', 'in_progress']);
            $this->applyDateRange($agentQuery, 'collected_at', $from, $to);

            $adminTotal = max(0.0, (float) $adminQuery->sum('amount'));
            $agentTotal = max(0.0, (float) $agentQuery->sum('amount'));
        }

        return [
            'admin' => $adminTotal,
            'agent' => $agentTotal,
            'total' => $adminTotal + $agentTotal,
        ];
    }

    /**
     * Pending / overdue installment counts (distinct members), aligned with loan EMI logic.
     *
     * @return array{overdue: int, pending: int}
     */
    protected function calculateInstallmentStatusCounts(
        $liveGroupIds,
        Carbon $today,
        Carbon $pendingTo,
        ?Carbon $periodFrom = null,
        ?Carbon $periodTo = null
    ): array {
        if ($liveGroupIds->isEmpty()) {
            return ['overdue' => 0, 'pending' => 0];
        }

        $balanceSql = '(installments.amount + COALESCE(installments.penalty_amount, 0) - COALESCE(installments.paid_amount, 0))';

        $overdueQuery = Installment::query()
            ->whereIn('group_id', $liveGroupIds)
            ->whereDate('due_date', '<', $today)
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereRaw("{$balanceSql} > 0.009");
        if ($periodFrom) {
            $overdueQuery->whereDate('due_date', '>=', $periodFrom);
        }

        $pendingQuery = Installment::query()
            ->whereIn('group_id', $liveGroupIds)
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $pendingTo)
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereRaw("{$balanceSql} > 0.009");

        return [
            'overdue' => (int) $overdueQuery->distinct()->count('member_id'),
            'pending' => (int) $pendingQuery->distinct()->count('member_id'),
        ];
    }

    /**
     * Clients enrolled in live chit groups.
     *
     * @return array{total: int, active: int}
     */
    protected function calculateChitClientStats($liveGroupIds): array
    {
        $allMemberships = GroupMember::query()
            ->whereNotIn('status', GroupMember::INACTIVE_STATUSES)
            ->with('shares:id,group_member_id,client_id')
            ->get(['id', 'client_id', 'status', 'group_id']);

        $totalClientIds = [];
        $activeClientIds = [];
        $liveGroupIdArray = $liveGroupIds instanceof \Illuminate\Support\Collection ? $liveGroupIds->toArray() : (array) $liveGroupIds;

        foreach ($allMemberships as $member) {
            $ownerIds = $member->shares->isNotEmpty()
                ? $member->shares->pluck('client_id')->all()
                : [(int) $member->client_id];

            foreach ($ownerIds as $clientId) {
                $clientId = (int) $clientId;
                if ($clientId <= 0) {
                    continue;
                }
                $totalClientIds[$clientId] = true;

                if (in_array($member->group_id, $liveGroupIdArray, true) && in_array($member->status, ['active', 'approved'], true)) {
                    $activeClientIds[$clientId] = true;
                }
            }
        }

        return [
            'total' => count($totalClientIds),
            'active' => count($activeClientIds),
        ];
    }
}
