<?php

namespace App\Http\Controllers;

use App\Models\ChitDividendPoolEntry;
use App\Models\ChitGroup;
use App\Models\Dividend;
use Illuminate\Http\Request;

class ChitDividendController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->input('view', 'pool'); // pool | auction

        $groups = ChitGroup::with('scheme')
            ->whereIn('status', ['active', 'completed', 'forming'])
            ->orderByDesc('id')
            ->get();

        $activeGroupIds = $groups->pluck('id');

        if ($view === 'auction') {
            $query = Dividend::whereHas('group', function ($q) {
                $q->whereNull('deleted_at')
                  ->whereIn('status', ['active', 'completed', 'forming']);
            })->with(['group.scheme', 'auction'])->latest();

            if ($request->filled('group_id')) {
                $query->where('group_id', $request->group_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $dividends = $query->paginate(15)->withQueryString();
            $mode = 'index';

            return view('admin.chit.dividends.index', compact('dividends', 'groups', 'mode', 'view'));
        }

        $poolQuery = ChitGroup::with(['scheme', 'dividends', 'payouts'])
            ->withSum(['dividendPoolEntries as pool_credit_total' => fn ($q) => $q->where('entry_type', ChitDividendPoolEntry::TYPE_CREDIT)], 'amount')
            ->withSum(['dividendPoolEntries as pool_debit_total' => fn ($q) => $q->where('entry_type', ChitDividendPoolEntry::TYPE_DEBIT)], 'amount')
            ->withSum(['payouts as total_payout_sum' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'payout_amount')
            ->withSum(['dividends as total_dividend_sum'], 'net_dividend')
            ->withCount('dividendPoolEntries')
            ->whereIn('status', ['active', 'completed', 'forming']);

        if ($request->filled('group_id')) {
            $poolQuery->where('id', $request->group_id);
        }

        if ($request->filled('has_balance') && $request->has_balance === '1') {
            $poolQuery->where('dividend_pool_balance', '>', 0);
        }

        $poolGroups = $poolQuery
            ->orderByDesc('dividend_pool_balance')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Calculate fallback dividend sums for groups that rely on scheduled scheme dividends
        $poolGroups->getCollection()->transform(function ($g) {
            $divSum = (float) ($g->total_dividend_sum ?? 0);
            if ($divSum <= 0 && $g->total_months > 0) {
                $calcDiv = 0.0;
                for ($m = 1; $m <= (int) $g->total_months; $m++) {
                    $calcDiv += $g->getMonthlyDividendAmount($m);
                }
                $divSum = round($calcDiv * (int) ($g->total_members ?: 1), 2);
            }
            $g->calculated_total_dividend_sum = $divSum;
            return $g;
        });

        if ($activeGroupIds->isEmpty()) {
            $poolTotals = [
                'balance'         => 0.0,
                'credits'         => 0.0,
                'debits'          => 0.0,
                'total_dividends' => 0.0,
                'total_payouts'   => 0.0,
            ];
        } else {
            $poolTotals = [
                'balance'         => (float) ChitGroup::whereIn('id', $activeGroupIds)->sum('dividend_pool_balance'),
                'credits'         => (float) ChitDividendPoolEntry::whereIn('group_id', $activeGroupIds)->where('entry_type', ChitDividendPoolEntry::TYPE_CREDIT)->sum('amount'),
                'debits'          => (float) ChitDividendPoolEntry::whereIn('group_id', $activeGroupIds)->where('entry_type', ChitDividendPoolEntry::TYPE_DEBIT)->sum('amount'),
                'total_dividends' => (float) Dividend::whereIn('group_id', $activeGroupIds)->sum('net_dividend'),
                'total_payouts'   => (float) \App\Models\Payout::whereIn('group_id', $activeGroupIds)->where('status', '!=', 'cancelled')->sum('payout_amount'),
            ];
        }

        $mode = 'index';

        return view('admin.chit.dividends.index', compact('poolGroups', 'groups', 'mode', 'view', 'poolTotals'));
    }

    public function showPool(ChitGroup $group)
    {
        $group->load([
            'scheme',
            'dividends.auction',
            'payouts.winner.client',
            'auctions.winner.client',
        ]);

        $entries = $group->dividendPoolEntries()
            ->with(['payout.winner.client', 'createdBy'])
            ->paginate(30)
            ->withQueryString();

        $totalPayoutSum = (float) $group->payouts()
            ->whereIn('status', ['paid', 'completed', 'disbursed'])
            ->sum('payout_amount');

        $allPayoutSum = (float) $group->payouts()
            ->where('status', '!=', 'cancelled')
            ->sum('payout_amount');

        $totalDividendSum = (float) $group->dividends()
            ->sum('net_dividend');

        $totalMonths = (int) ($group->total_months ?: 12);
        if ($totalDividendSum <= 0) {
            $calcDiv = 0.0;
            for ($m = 1; $m <= $totalMonths; $m++) {
                $calcDiv += $group->getMonthlyDividendAmount($m);
            }
            $totalDividendSum = round($calcDiv * (int) ($group->total_members ?: 1), 2);
        }

        $monthlyBreakdown = collect();
        for ($m = 1; $m <= $totalMonths; $m++) {
            $payout = $group->payouts->firstWhere('month_number', $m);
            $auction = $group->auctions->firstWhere('month_number', $m);
            $dividendRec = $group->dividends->firstWhere('month_number', $m);

            $perMemberDiv = $group->getMonthlyDividendAmount($m);
            $fullNetDiv = $dividendRec ? (float) $dividendRec->net_dividend : round($perMemberDiv * (int) ($group->total_members ?: 1), 2);
            $payoutAmt = $payout ? (float) $payout->payout_amount : ($auction ? (float) $auction->winning_bid : $group->resolvePayoutAmountForMonth($m));

            $winnerClient = $payout?->winner?->client?->client_name ?? $auction?->winner?->client?->client_name ?? null;

            $monthlyBreakdown->push([
                'month_number' => $m,
                'month_label' => $group->periodCalendarLabel($m),
                'installment_amount' => $group->getInstallmentAmountForMonth($m),
                'per_member_dividend' => $perMemberDiv,
                'net_dividend' => $fullNetDiv,
                'payout_amount' => $payoutAmt,
                'payout' => $payout,
                'auction' => $auction,
                'dividend' => $dividendRec,
                'winner_name' => $winnerClient,
                'status' => $payout ? ucfirst($payout->status) : ($auction ? 'Auction Completed' : ($group->isForemanCommissionMonth($m) ? 'Foreman Month' : 'Scheduled')),
            ]);
        }

        $mode = 'pool';
        $view = 'pool';

        return view('admin.chit.dividends.index', compact(
            'group',
            'entries',
            'mode',
            'view',
            'totalPayoutSum',
            'allPayoutSum',
            'totalDividendSum',
            'monthlyBreakdown'
        ));
    }

    public function show(Dividend $dividend)
    {
        $dividend->load(['group.scheme', 'auction', 'distributions.member.client']);
        $mode = 'show';
        $view = 'auction';

        return view('admin.chit.dividends.index', compact('dividend', 'mode', 'view'));
    }

    public function distribute(Dividend $dividend)
    {
        if ($dividend->status === 'distributed') {
            return back()->with('error', 'Dividend already distributed.');
        }

        $dividend->distributions()->update(['status' => 'paid', 'paid_at' => now()]);
        $dividend->update(['status' => 'distributed', 'processed_at' => now()]);

        return back()->with('success', 'Dividends distributed to all members!');
    }
}
