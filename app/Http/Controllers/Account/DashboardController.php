<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\BankAccount;
use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 75, 100, 250, 500];

    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-dashboard'), 403);

        return $this->companyDashboard($request);
    }

    private function companyDashboard(Request $request)
    {
        $creatorId = creatorId();
        $perPage = $this->resolvePerPage($request);

        $bankQuery = BankAccount::query()
            ->where('is_active', true)
            ->where(function ($q) use ($creatorId) {
                $q->where('created_by', $creatorId)->orWhere('creator_id', Auth::id());
            });

        $cashBalance = (clone $bankQuery)
            ->where(function ($q) {
                $q->where('account_type', 'cash')
                    ->orWhere('account_name', 'like', '%Cash in Hand%')
                    ->orWhere('account_name', 'like', '%Cash In Hand%');
            })
            ->sum('current_balance');

        $bankBalance = (clone $bankQuery)
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('account_type')->orWhere('account_type', '!=', 'cash');
                })->where('account_name', 'not like', '%Cash in Hand%')
                    ->where('account_name', 'not like', '%Cash In Hand%');
            })
            ->sum('current_balance');

        $loanAll = $this->loanClientFlow();
        $chitAll = $this->chitClientFlow();
        $fdAll = $this->fdClientFlow();

        return view('admin.account.dashboard.company', [
            'cashBook' => [
                'cash_balance' => (float) $cashBalance,
                'bank_balance' => (float) $bankBalance,
                'accounts_count' => (clone $bankQuery)->count(),
                'total_liquidity' => (float) $cashBalance + (float) $bankBalance,
            ],
            'loanByClient' => $this->paginateFlow($loanAll, $request, 'loan_page', $perPage, 'loan'),
            'chitByClient' => $this->paginateFlow($chitAll, $request, 'chit_page', $perPage, 'chit'),
            'fdByClient' => $this->paginateFlow($fdAll, $request, 'fd_page', $perPage, 'fd'),
            'loanTotals' => $this->flowTotals($loanAll),
            'chitTotals' => $this->flowTotals($chitAll),
            'fdTotals' => $this->flowTotals($fdAll),
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 25);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;
    }

    private function paginateFlow(
        Collection $rows,
        Request $request,
        string $pageName,
        int $perPage,
        string $tab
    ): LengthAwarePaginator {
        $page = max(1, (int) $request->input($pageName, 1));
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $query = $request->except([$pageName]);
        $query['tab'] = $tab;
        $query['per_page'] = $perPage;

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
                'query' => $query,
            ]
        );
    }

    /**
     * Client-wise loan credit (collections) / debit (disbursements).
     */
    private function loanClientFlow(): Collection
    {
        $rows = LoanAccount::query()
            ->selectRaw('client_id, SUM(COALESCE(paid_amount, 0)) as credit, SUM(COALESCE(disbursed_amount, 0)) as debit')
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->havingRaw('SUM(COALESCE(paid_amount, 0)) > 0 OR SUM(COALESCE(disbursed_amount, 0)) > 0')
            ->get();

        return $this->attachClientNames($rows)->sortBy('client_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Client-wise chit credit (installments) / debit (payouts).
     */
    private function chitClientFlow(): Collection
    {
        $credits = Installment::query()
            ->join('group_members', 'installments.member_id', '=', 'group_members.id')
            ->whereNotNull('group_members.client_id')
            ->selectRaw('group_members.client_id, SUM(COALESCE(installments.paid_amount, 0)) as credit')
            ->groupBy('group_members.client_id')
            ->pluck('credit', 'client_id');

        $debits = Payout::query()
            ->join('group_members', 'payouts.winner_member_id', '=', 'group_members.id')
            ->where('payouts.status', 'paid')
            ->whereNotNull('group_members.client_id')
            ->selectRaw('group_members.client_id, SUM(COALESCE(payouts.net_payout_amount, 0)) as debit')
            ->groupBy('group_members.client_id')
            ->pluck('debit', 'client_id');

        $clientIds = $credits->keys()->merge($debits->keys())->unique()->filter()->values();

        $rows = $clientIds->map(function ($clientId) use ($credits, $debits) {
            return (object) [
                'client_id' => (int) $clientId,
                'credit' => (float) ($credits[$clientId] ?? 0),
                'debit' => (float) ($debits[$clientId] ?? 0),
            ];
        })->filter(fn ($row) => $row->credit > 0 || $row->debit > 0);

        return $this->attachClientNames($rows)->sortBy('client_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Client-wise FD credit (deposits) / debit (closures / payouts).
     */
    private function fdClientFlow(): Collection
    {
        $credits = FixedDeposit::query()
            ->selectRaw('client_id, SUM(COALESCE(deposit_amount, 0)) as credit')
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->pluck('credit', 'client_id');

        $debits = FixedDeposit::query()
            ->selectRaw('client_id, SUM(COALESCE(closure_amount, 0)) as debit')
            ->whereNotNull('client_id')
            ->whereIn('status', ['matured', 'closed', 'premature_closed', 'cancelled', 'renewed'])
            ->groupBy('client_id')
            ->pluck('debit', 'client_id');

        $clientIds = $credits->keys()->merge($debits->keys())->unique()->filter()->values();

        $rows = $clientIds->map(function ($clientId) use ($credits, $debits) {
            return (object) [
                'client_id' => (int) $clientId,
                'credit' => (float) ($credits[$clientId] ?? 0),
                'debit' => (float) ($debits[$clientId] ?? 0),
            ];
        })->filter(fn ($row) => $row->credit > 0 || $row->debit > 0);

        return $this->attachClientNames($rows)->sortBy('client_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    private function attachClientNames(Collection $rows): Collection
    {
        $names = Client::query()
            ->whereIn('id', $rows->pluck('client_id')->filter()->unique())
            ->pluck('client_name', 'id');

        return $rows->map(function ($row) use ($names) {
            $row->client_name = $names[$row->client_id] ?? ('#' . $row->client_id);
            $row->credit = (float) ($row->credit ?? 0);
            $row->debit = (float) ($row->debit ?? 0);

            return $row;
        });
    }

    private function flowTotals(Collection $rows): array
    {
        return [
            'credit' => (float) $rows->sum('credit'),
            'debit' => (float) $rows->sum('debit'),
            'clients' => $rows->count(),
        ];
    }
}
