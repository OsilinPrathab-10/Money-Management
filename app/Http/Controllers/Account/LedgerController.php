<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\ChartOfAccount;
use App\Services\Account\AccountExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LedgerController extends Controller
{
    public function __construct(protected AccountExportService $exportService)
    {
        //
    }

    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-ledger'), 403);

        $creatorId = creatorId();

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'status' => 'nullable|string|in:all,draft,posted,reversed',
            'account_id' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:10|max:200',
        ]);

        $fromDate = !empty($validated['from_date'] ?? null)
            ? Carbon::parse($validated['from_date'])->toDateString()
            : now()->startOfMonth()->toDateString();
        $toDate = !empty($validated['to_date'] ?? null)
            ? Carbon::parse($validated['to_date'])->toDateString()
            : now()->endOfMonth()->toDateString();
        $status = $validated['status'] ?? 'posted';
        $accountId = $validated['account_id'] ?? null;
        $search = $validated['search'] ?? null;

        $chartOfAccounts = ChartOfAccount::query()
            ->where('created_by', $creatorId)
            ->where('is_active', true)
            ->select('id', 'account_code', 'account_name')
            ->orderBy('account_code')
            ->get();

        $perPage = (int) ($validated['per_page'] ?? 20);

        $baseQuery = $this->ledgerBaseQuery($creatorId, $fromDate, $toDate, $status, $accountId, $search);

        $ledger = $baseQuery
            ->orderByDesc('journal_entries.journal_date')
            ->orderByDesc('journal_entries.id')
            ->paginate($perPage)
            ->withQueryString();

        $totals = $this->ledgerTotals($creatorId, $fromDate, $toDate, $status, $accountId, $search);

        return view('admin.account.ledger.index', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'status' => $status,
            'accountId' => $accountId,
            'search' => $search,
            'chartOfAccounts' => $chartOfAccounts,
            'ledger' => $ledger,
            'totals' => $totals,
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-ledger'), 403);

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'status' => 'nullable|string|in:all,draft,posted,reversed',
            'account_id' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
        ]);

        $creatorId = creatorId();
        $fromDate = !empty($validated['from_date'] ?? null)
            ? Carbon::parse($validated['from_date'])->toDateString()
            : now()->startOfMonth()->toDateString();
        $toDate = !empty($validated['to_date'] ?? null)
            ? Carbon::parse($validated['to_date'])->toDateString()
            : now()->endOfMonth()->toDateString();
        $status = $validated['status'] ?? 'posted';
        $accountId = $validated['account_id'] ?? null;
        $search = $validated['search'] ?? null;

        $rows = $this->ledgerBaseQuery($creatorId, $fromDate, $toDate, $status, $accountId, $search)
            ->orderByDesc('journal_entries.journal_date')
            ->orderByDesc('journal_entries.id')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->journal_date,
                    'journal' => $row->journal_number,
                    'account' => $row->account_code . ' — ' . $row->account_name,
                    'debit' => (float) $row->debit_amount,
                    'credit' => (float) $row->credit_amount,
                    'description' => $row->line_description ?? $row->journal_description ?? '',
                    'status' => $row->journal_status,
                ];
            })
            ->all();

        $totals = $this->ledgerTotals($creatorId, $fromDate, $toDate, $status, $accountId, $search);

        return $this->exportService->exportByFormat(
            $validated['format'],
            'admin.account.ledger.exports.ledger',
            [
                'pageTitle' => __('Ledger'),
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'status' => $status,
                'accountId' => $accountId,
                'search' => $search,
                'filters' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'status' => $status,
                    'account_id' => $accountId,
                    'search' => $search,
                ],
                'rows' => $rows,
                'totals' => $totals,
            ],
            'ledger-' . $fromDate . '-to-' . $toDate
        );
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function ledgerBaseQuery($creatorId, string $fromDate, string $toDate, string $status, $accountId = null, ?string $search = null): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('journal_entries')
            ->join('journal_entry_items', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->join('chart_of_accounts', 'journal_entry_items.account_id', '=', 'chart_of_accounts.id')
            ->select(
                'journal_entries.id',
                'journal_entries.journal_date',
                'journal_entries.journal_number',
                'journal_entries.description as journal_description',
                'journal_entries.status as journal_status',
                'journal_entry_items.description as line_description',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'journal_entry_items.debit_amount',
                'journal_entry_items.credit_amount'
            )
            ->where('journal_entries.created_by', $creatorId)
            ->whereBetween('journal_entries.journal_date', [$fromDate, $toDate]);

        if ($status !== 'all') {
            $query->where('journal_entries.status', $status);
        }

        if ($accountId) {
            $query->where('journal_entry_items.account_id', $accountId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('journal_entries.journal_number', 'like', '%' . $search . '%')
                    ->orWhere('journal_entries.description', 'like', '%' . $search . '%')
                    ->orWhere('journal_entry_items.description', 'like', '%' . $search . '%')
                    ->orWhere('chart_of_accounts.account_code', 'like', '%' . $search . '%')
                    ->orWhere('chart_of_accounts.account_name', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function ledgerTotals($creatorId, string $fromDate, string $toDate, string $status, $accountId = null, ?string $search = null): array
    {
        $totalsQuery = DB::table('journal_entries')
            ->join('journal_entry_items', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->join('chart_of_accounts', 'journal_entry_items.account_id', '=', 'chart_of_accounts.id')
            ->selectRaw('COALESCE(SUM(journal_entry_items.debit_amount),0) as total_debit, COALESCE(SUM(journal_entry_items.credit_amount),0) as total_credit')
            ->where('journal_entries.created_by', $creatorId)
            ->whereBetween('journal_entries.journal_date', [$fromDate, $toDate]);

        if ($status !== 'all') {
            $totalsQuery->where('journal_entries.status', $status);
        }
        if ($accountId) {
            $totalsQuery->where('journal_entry_items.account_id', $accountId);
        }
        if ($search) {
            $totalsQuery->where(function ($q) use ($search) {
                $q->where('journal_entries.journal_number', 'like', '%' . $search . '%')
                    ->orWhere('journal_entries.description', 'like', '%' . $search . '%')
                    ->orWhere('journal_entry_items.description', 'like', '%' . $search . '%')
                    ->orWhere('chart_of_accounts.account_code', 'like', '%' . $search . '%')
                    ->orWhere('chart_of_accounts.account_name', 'like', '%' . $search . '%');
            });
        }

        $totals = $totalsQuery->first();

        return [
            'total_debit' => (float) ($totals?->total_debit ?? 0),
            'total_credit' => (float) ($totals?->total_credit ?? 0),
        ];
    }
}

