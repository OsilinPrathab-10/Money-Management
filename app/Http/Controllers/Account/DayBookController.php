<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\BankAccount;
use App\Models\Account\BankTransaction;
use App\Models\Account\Expense;
use App\Models\Account\ExpenseCategories;
use App\Models\Account\Revenue;
use App\Models\Account\RevenueCategories;
use App\Models\ChitDividendPoolEntry;
use App\Services\Account\AccountExportService;
use App\Services\Account\AccountingTags;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class DayBookController extends Controller
{
    public function __construct(protected AccountExportService $exportService)
    {
        //
    }

    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-day-book'), 403);

        $creatorId = creatorId();

        $validated = $request->validate([
            'day' => 'nullable|date',
            'date' => 'nullable|date',
            'status' => 'nullable|string|in:all,draft,approved,posted',
            'bank_account_id' => 'nullable|integer',
            'revenue_category_id' => 'nullable|integer',
            'expense_category_id' => 'nullable|integer',
            'module_tag' => 'nullable|string|in:all,CHIT,LOAN,FD,TRANSFER,OTHER',
            'entry_tag' => 'nullable|string|max:40',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:10|max:200',
        ]);

        $dayInput = $validated['day'] ?? $validated['date'] ?? null;
        $day = $dayInput ? Carbon::parse($dayInput)->toDateString() : now()->toDateString();
        $status = $validated['status'] ?? 'posted';
        $bankAccountId = $validated['bank_account_id'] ?? null;
        $revenueCategoryId = $validated['revenue_category_id'] ?? null;
        $expenseCategoryId = $validated['expense_category_id'] ?? null;
        $moduleTag = $validated['module_tag'] ?? 'all';
        $entryTag = $validated['entry_tag'] ?? null;
        $search = $validated['search'] ?? null;

        $bankAccounts = BankAccount::query()
            ->where('created_by', $creatorId)
            ->where('is_active', true)
            ->select('id', 'account_name')
            ->orderBy('account_name')
            ->get();

        $revenueCategories = RevenueCategories::query()
            ->where('created_by', $creatorId)
            ->where('is_active', true)
            ->select('id', 'category_name')
            ->orderBy('category_name')
            ->get();

        $expenseCategories = ExpenseCategories::query()
            ->where('created_by', $creatorId)
            ->where('is_active', true)
            ->select('id', 'category_name')
            ->orderBy('category_name')
            ->get();

        $revenuesQuery = Revenue::with([
                'category:id,category_name',
                'bankAccount:id,account_name',
                'chartOfAccount:id,account_code,account_name',
                'approvedBy:id,name',
            ])
            ->where('created_by', $creatorId)
            ->whereDate('revenue_date', $day);

        if ($status !== 'all') {
            $revenuesQuery->where('status', $status);
        }
        if ($revenueCategoryId) {
            $revenuesQuery->where('category_id', $revenueCategoryId);
        }
        if ($bankAccountId) {
            $revenuesQuery->where('bank_account_id', $bankAccountId);
        }
        $this->applyTagFilters($revenuesQuery, $moduleTag, $entryTag);
        if ($search) {
            $revenuesQuery->where(function ($q) use ($search) {
                $q->where('revenue_number', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $expensesQuery = Expense::with([
                'category:id,category_name',
                'bankAccount:id,account_name',
                'chartOfAccount:id,account_code,account_name',
                'approvedBy:id,name',
            ])
            ->where('created_by', $creatorId)
            ->whereDate('expense_date', $day);

        if ($status !== 'all') {
            $expensesQuery->where('status', $status);
        }
        if ($expenseCategoryId) {
            $expensesQuery->where('category_id', $expenseCategoryId);
        }
        if ($bankAccountId) {
            $expensesQuery->where('bank_account_id', $bankAccountId);
        }
        $this->applyTagFilters($expensesQuery, $moduleTag, $entryTag);
        if ($search) {
            $expensesQuery->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $revenues = $revenuesQuery->orderByDesc('id')->get();
        $expenses = $expensesQuery->orderByDesc('id')->get();

        $bankTxQuery = BankTransaction::with(['bankAccount:id,account_name'])
            ->whereDate('transaction_date', $day)
            ->where('transaction_status', '!=', 'cancelled')
            ->where('created_by', $creatorId);

        if ($bankAccountId) {
            $bankTxQuery->where('bank_account_id', $bankAccountId);
        }
        $this->applyTagFilters($bankTxQuery, $moduleTag, $entryTag);
        if ($search) {
            $bankTxQuery->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $bankTransactions = $bankTxQuery->orderByDesc('id')->get();
        $bankCredits = (float) $bankTransactions->where('transaction_type', 'credit')->sum('amount');
        $bankDebits = (float) $bankTransactions->where('transaction_type', 'debit')->sum('amount');

        $dividends = collect();
        if ($moduleTag === 'all' || $moduleTag === AccountingTags::MODULE_CHIT) {
            if (! $entryTag || $entryTag === AccountingTags::ENTRY_DIVIDEND) {
                $dividends = $this->dividendEntriesForDay($creatorId, $day, $search);
            }
        }

        $totalRevenue = (float) ($revenues->sum('amount') ?? 0);
        $totalExpense = (float) ($expenses->sum('amount') ?? 0);
        $netProfit = $totalRevenue - $totalExpense;

        return view('admin.account.day-book.index', [
            'day' => $day,
            'status' => $status,
            'bankAccountId' => $bankAccountId,
            'revenueCategoryId' => $revenueCategoryId,
            'expenseCategoryId' => $expenseCategoryId,
            'moduleTag' => $moduleTag,
            'entryTag' => $entryTag,
            'search' => $search,
            'bankAccounts' => $bankAccounts,
            'revenueCategories' => $revenueCategories,
            'expenseCategories' => $expenseCategories,
            'moduleOptions' => AccountingTags::modules(),
            'entryOptions' => AccountingTags::entries(),
            'revenues' => $revenues,
            'expenses' => $expenses,
            'bankTransactions' => $bankTransactions,
            'dividends' => $dividends,
            'totals' => [
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
                'net_profit' => $netProfit,
                'bank_credits' => $bankCredits,
                'bank_debits' => $bankDebits,
                'bank_net' => $bankCredits - $bankDebits,
                'dividend_credits' => (float) $dividends->where('entry_type', ChitDividendPoolEntry::TYPE_CREDIT)->sum('amount'),
                'dividend_debits' => (float) $dividends->where('entry_type', ChitDividendPoolEntry::TYPE_DEBIT)->sum('amount'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-day-book'), 403);

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'day' => 'nullable|date',
            'status' => 'nullable|string|in:all,draft,approved,posted',
            'bank_account_id' => 'nullable|integer',
            'revenue_category_id' => 'nullable|integer',
            'expense_category_id' => 'nullable|integer',
            'module_tag' => 'nullable|string|in:all,CHIT,LOAN,FD,TRANSFER,OTHER',
            'entry_tag' => 'nullable|string|max:40',
            'search' => 'nullable|string|max:255',
        ]);

        $creatorId = creatorId();
        $day = ! empty($validated['day'])
            ? Carbon::parse($validated['day'])->toDateString()
            : now()->toDateString();
        $status = $validated['status'] ?? 'posted';
        $bankAccountId = $validated['bank_account_id'] ?? null;
        $revenueCategoryId = $validated['revenue_category_id'] ?? null;
        $expenseCategoryId = $validated['expense_category_id'] ?? null;
        $moduleTag = $validated['module_tag'] ?? 'all';
        $entryTag = $validated['entry_tag'] ?? null;
        $search = $validated['search'] ?? null;

        $revenues = Revenue::with([
                'category:id,category_name',
                'bankAccount:id,account_name',
            ])
            ->where('created_by', $creatorId)
            ->whereDate('revenue_date', $day)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($revenueCategoryId, fn ($q) => $q->where('category_id', $revenueCategoryId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($moduleTag !== 'all', fn ($q) => $q->where('module_tag', $moduleTag))
            ->when($entryTag, fn ($q) => $q->where('entry_tag', $entryTag))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('revenue_number', 'like', '%' . $search . '%')
                        ->orWhere('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('id')
            ->get();

        $expenses = Expense::with([
                'category:id,category_name',
                'bankAccount:id,account_name',
            ])
            ->where('created_by', $creatorId)
            ->whereDate('expense_date', $day)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($expenseCategoryId, fn ($q) => $q->where('category_id', $expenseCategoryId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($moduleTag !== 'all', fn ($q) => $q->where('module_tag', $moduleTag))
            ->when($entryTag, fn ($q) => $q->where('entry_tag', $entryTag))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('expense_number', 'like', '%' . $search . '%')
                        ->orWhere('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('id')
            ->get();

        $bankTransactions = BankTransaction::with(['bankAccount:id,account_name'])
            ->where('created_by', $creatorId)
            ->whereDate('transaction_date', $day)
            ->where('transaction_status', '!=', 'cancelled')
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($moduleTag !== 'all', fn ($q) => $q->where('module_tag', $moduleTag))
            ->when($entryTag, fn ($q) => $q->where('entry_tag', $entryTag))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('id')
            ->get();

        $dividends = collect();
        if ($moduleTag === 'all' || $moduleTag === AccountingTags::MODULE_CHIT) {
            if (! $entryTag || $entryTag === AccountingTags::ENTRY_DIVIDEND) {
                $dividends = $this->dividendEntriesForDay($creatorId, $day, $search);
            }
        }

        $totalRevenue = (float) ($revenues->sum('amount') ?? 0);
        $totalExpense = (float) ($expenses->sum('amount') ?? 0);
        $bankCredits = (float) $bankTransactions->where('transaction_type', 'credit')->sum('amount');
        $bankDebits = (float) $bankTransactions->where('transaction_type', 'debit')->sum('amount');

        $rows = collect()
            ->concat($revenues->map(function ($r) {
                return [
                    'type' => 'Revenue',
                    'number' => $r->revenue_number,
                    'date' => $r->revenue_date?->format('Y-m-d'),
                    'module' => $r->module_tag ?? '—',
                    'entry' => $r->entry_tag ?? '—',
                    'category' => $r->category?->category_name ?? '—',
                    'bank' => $r->bankAccount?->account_name ?? '—',
                    'status' => $r->status,
                    'description' => $r->description ?? '',
                    'amount' => (float) $r->amount,
                ];
            }))
            ->concat($expenses->map(function ($e) {
                return [
                    'type' => 'Expense',
                    'number' => $e->expense_number,
                    'date' => $e->expense_date?->format('Y-m-d'),
                    'module' => $e->module_tag ?? '—',
                    'entry' => $e->entry_tag ?? '—',
                    'category' => $e->category?->category_name ?? '—',
                    'bank' => $e->bankAccount?->account_name ?? '—',
                    'status' => $e->status,
                    'description' => $e->description ?? '',
                    'amount' => (float) $e->amount,
                ];
            }))
            ->concat($bankTransactions->map(function ($t) {
                return [
                    'type' => 'Bank ' . ucfirst($t->transaction_type),
                    'number' => $t->reference_number ?? '—',
                    'date' => $t->transaction_date?->format('Y-m-d'),
                    'module' => $t->module_tag ?? '—',
                    'entry' => $t->entry_tag ?? '—',
                    'category' => '—',
                    'bank' => $t->bankAccount?->account_name ?? '—',
                    'status' => $t->transaction_status,
                    'description' => $t->description ?? '',
                    'amount' => (float) $t->amount,
                ];
            }))
            ->concat($dividends->map(function ($d) {
                $groupCode = $d->group?->group_code ?? ('GRP-' . $d->group_id);

                return [
                    'type' => 'Dividend ' . ucfirst($d->entry_type),
                    'number' => 'DIV-' . $d->id,
                    'date' => optional($d->created_at)->format('Y-m-d'),
                    'module' => AccountingTags::MODULE_CHIT,
                    'entry' => AccountingTags::ENTRY_DIVIDEND,
                    'category' => '—',
                    'bank' => '—',
                    'status' => 'memo',
                    'description' => ($d->remarks ?: $d->entry_type_label) . ' — ' . $groupCode,
                    'amount' => (float) $d->amount,
                ];
            }))
            ->all();

        return $this->exportService->exportByFormat(
            $validated['format'],
            'admin.account.day-book.exports.day-book',
            [
                'pageTitle' => __('Day Book'),
                'filters' => [
                    'day' => $day,
                    'status' => $status,
                    'module_tag' => $moduleTag,
                    'entry_tag' => $entryTag,
                    'bank_account_id' => $bankAccountId,
                    'search' => $search,
                ],
                'rows' => $rows,
                'totals' => [
                    'total_revenue' => $totalRevenue,
                    'total_expense' => $totalExpense,
                    'net_profit' => $totalRevenue - $totalExpense,
                    'bank_credits' => $bankCredits,
                    'bank_debits' => $bankDebits,
                ],
                'day' => $day,
            ],
            'day-book-' . $day
        );
    }

    protected function applyTagFilters($query, string $moduleTag, ?string $entryTag): void
    {
        if ($moduleTag !== 'all') {
            $query->where('module_tag', $moduleTag);
        }
        if ($entryTag) {
            $query->where('entry_tag', $entryTag);
        }
    }

    protected function dividendEntriesForDay(int $creatorId, string $day, ?string $search)
    {
        return ChitDividendPoolEntry::with(['group:id,group_code'])
            ->where('created_by', $creatorId)
            ->whereDate('created_at', $day)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('remarks', 'like', '%' . $search . '%')
                        ->orWhereHas('group', function ($gq) use ($search) {
                            $gq->where('group_code', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderByDesc('id')
            ->get();
    }
}
