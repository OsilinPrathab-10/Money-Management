<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\BankAccount;
use App\Models\Account\Expense;
use App\Models\Account\ExpenseCategories;
use App\Models\Account\Revenue;
use App\Models\Account\RevenueCategories;
use App\Services\Account\AccountExportService;
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
            // Backward/forward compatibility: some clients may send `date` instead of `day`.
            'date' => 'nullable|date',

            'status' => 'nullable|string|in:all,draft,approved,posted',
            'bank_account_id' => 'nullable|integer',
            'revenue_category_id' => 'nullable|integer',
            'expense_category_id' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:10|max:200',
        ]);

        $dayInput = $validated['day'] ?? $validated['date'] ?? null;
        $day = $dayInput ? Carbon::parse($dayInput)->toDateString() : now()->toDateString();
        $status = $validated['status'] ?? 'posted';
        $bankAccountId = $validated['bank_account_id'] ?? null;
        $revenueCategoryId = $validated['revenue_category_id'] ?? null;
        $expenseCategoryId = $validated['expense_category_id'] ?? null;
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
        if ($search) {
            $expensesQuery->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Day book for a single date is usually small; pull all to keep UI simple.
        $revenues = $revenuesQuery->orderByDesc('id')->get();
        $expenses = $expensesQuery->orderByDesc('id')->get();

        $totalRevenue = (float) ($revenues->sum('amount') ?? 0);
        $totalExpense = (float) ($expenses->sum('amount') ?? 0);
        $netProfit = $totalRevenue - $totalExpense;

        return view('admin.account.day-book.index', [
            'day' => $day,
            'status' => $status,
            'bankAccountId' => $bankAccountId,
            'revenueCategoryId' => $revenueCategoryId,
            'expenseCategoryId' => $expenseCategoryId,
            'search' => $search,
            'bankAccounts' => $bankAccounts,
            'revenueCategories' => $revenueCategories,
            'expenseCategories' => $expenseCategories,
            'revenues' => $revenues,
            'expenses' => $expenses,
            'totals' => [
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
                'net_profit' => $netProfit,
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
            'search' => 'nullable|string|max:255',
        ]);

        $creatorId = creatorId();
        $day = $validated['day'] ? Carbon::parse($validated['day'])->toDateString() : now()->toDateString();
        $status = $validated['status'] ?? 'posted';
        $bankAccountId = $validated['bank_account_id'] ?? null;
        $revenueCategoryId = $validated['revenue_category_id'] ?? null;
        $expenseCategoryId = $validated['expense_category_id'] ?? null;
        $search = $validated['search'] ?? null;

        $revenues = Revenue::with([
                'category:id,category_name',
                'bankAccount:id,account_name',
                'chartOfAccount:id,account_code,account_name',
            ])
            ->where('created_by', $creatorId)
            ->whereDate('revenue_date', $day)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($revenueCategoryId, fn ($q) => $q->where('category_id', $revenueCategoryId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
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
                'chartOfAccount:id,account_code,account_name',
            ])
            ->where('created_by', $creatorId)
            ->whereDate('expense_date', $day)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($expenseCategoryId, fn ($q) => $q->where('category_id', $expenseCategoryId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('expense_number', 'like', '%' . $search . '%')
                        ->orWhere('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('id')
            ->get();

        $totalRevenue = (float) ($revenues->sum('amount') ?? 0);
        $totalExpense = (float) ($expenses->sum('amount') ?? 0);
        $netProfit = $totalRevenue - $totalExpense;

        $rows = collect()
            ->concat($revenues->map(function ($r) {
                return [
                    'type' => 'Revenue',
                    'number' => $r->revenue_number,
                    'date' => $r->revenue_date?->format('Y-m-d') ?? $day,
                    'category' => $r->category?->category_name ?? '—',
                    'bank' => $r->bankAccount?->account_name ?? '—',
                    'gl' => $r->chartOfAccount?->account_code ? ($r->chartOfAccount->account_code . ' — ' . $r->chartOfAccount->account_name) : '—',
                    'status' => $r->status,
                    'description' => $r->description ?? '',
                    'amount' => (float) $r->amount,
                ];
            }))
            ->concat($expenses->map(function ($e) {
                return [
                    'type' => 'Expense',
                    'number' => $e->expense_number,
                    'date' => $e->expense_date?->format('Y-m-d') ?? $day,
                    'category' => $e->category?->category_name ?? '—',
                    'bank' => $e->bankAccount?->account_name ?? '—',
                    'gl' => $e->chartOfAccount?->account_code ? ($e->chartOfAccount->account_code . ' — ' . $e->chartOfAccount->account_name) : '—',
                    'status' => $e->status,
                    'description' => $e->description ?? '',
                    'amount' => (float) $e->amount,
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
                    'bank_account_id' => $bankAccountId,
                    'revenue_category_id' => $revenueCategoryId,
                    'expense_category_id' => $expenseCategoryId,
                    'search' => $search,
                ],
                'rows' => $rows,
                'totals' => [
                    'total_revenue' => $totalRevenue,
                    'total_expense' => $totalExpense,
                    'net_profit' => $netProfit,
                ],
                'day' => $day,
            ],
            'day-book-' . $day
        );
    }
}

