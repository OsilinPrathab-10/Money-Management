<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\Expense;
use App\Models\Account\Revenue;
use App\Services\Account\AccountExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ProfitLossController extends Controller
{
    public function __construct(protected AccountExportService $exportService)
    {
        //
    }

    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-profit-loss'), 403);

        $creatorId = creatorId();

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'status_mode' => 'nullable|string|in:posted,all',
            'search' => 'nullable|string|max:255',
        ]);

        $currentYear = (int) date('Y');
        $fromInput = $validated['from_date'] ?? null;
        $toInput = $validated['to_date'] ?? null;
        $fromDate = $fromInput ? Carbon::parse($fromInput)->toDateString() : ($currentYear . '-01-01');
        $toDate = $toInput ? Carbon::parse($toInput)->toDateString() : ($currentYear . '-12-31');
        $statusMode = $validated['status_mode'] ?? 'posted';
        $search = $validated['search'] ?? null;

        $revenuesQuery = Revenue::query()
            ->where('created_by', $creatorId)
            ->whereBetween('revenue_date', [$fromDate, $toDate]);

        $expensesQuery = Expense::query()
            ->where('created_by', $creatorId)
            ->whereBetween('expense_date', [$fromDate, $toDate]);

        if ($statusMode === 'posted') {
            $revenuesQuery->where('status', 'posted');
            $expensesQuery->where('status', 'posted');
        }

        if ($search) {
            $revenuesQuery->where(function ($q) use ($search) {
                $q->where('revenue_number', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });

            $expensesQuery->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $totalRevenue = (float) ($revenuesQuery->sum('amount') ?? 0);
        $totalExpense = (float) ($expensesQuery->sum('amount') ?? 0);
        $netProfit = $totalRevenue - $totalExpense;

        return view('admin.account.profit-loss.index', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'statusMode' => $statusMode,
            'search' => $search,
            'totals' => [
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
                'net_profit' => $netProfit,
            ],
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-profit-loss'), 403);

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'status_mode' => 'nullable|string|in:posted,all',
            'search' => 'nullable|string|max:255',
        ]);

        $creatorId = creatorId();
        $currentYear = (int) date('Y');
        $fromInput = $validated['from_date'] ?? null;
        $toInput = $validated['to_date'] ?? null;
        $fromDate = $fromInput ? Carbon::parse($fromInput)->toDateString() : ($currentYear . '-01-01');
        $toDate = $toInput ? Carbon::parse($toInput)->toDateString() : ($currentYear . '-12-31');
        $statusMode = $validated['status_mode'] ?? 'posted';
        $search = $validated['search'] ?? null;

        $revenuesQuery = Revenue::query()
            ->where('created_by', $creatorId)
            ->whereBetween('revenue_date', [$fromDate, $toDate]);

        $expensesQuery = Expense::query()
            ->where('created_by', $creatorId)
            ->whereBetween('expense_date', [$fromDate, $toDate]);

        if ($statusMode === 'posted') {
            $revenuesQuery->where('status', 'posted');
            $expensesQuery->where('status', 'posted');
        }

        if ($search) {
            $revenuesQuery->where(function ($q) use ($search) {
                $q->where('revenue_number', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });

            $expensesQuery->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $totalRevenue = (float) ($revenuesQuery->sum('amount') ?? 0);
        $totalExpense = (float) ($expensesQuery->sum('amount') ?? 0);
        $netProfit = $totalRevenue - $totalExpense;

        $rows = [
            [
                'metric' => __('Revenue'),
                'amount' => $totalRevenue,
            ],
            [
                'metric' => __('Expense'),
                'amount' => $totalExpense,
            ],
            [
                'metric' => __('Net profit (Revenue - Expense)'),
                'amount' => $netProfit,
            ],
        ];

        return $this->exportService->exportByFormat(
            $validated['format'],
            'admin.account.profit-loss.exports.profit-loss',
            [
                'pageTitle' => __('Profit & Loss'),
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'statusMode' => $statusMode,
                'search' => $search,
                'filters' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'status_mode' => $statusMode,
                    'search' => $search,
                ],
                'rows' => $rows,
                'totals' => [
                    'total_revenue' => $totalRevenue,
                    'total_expense' => $totalExpense,
                    'net_profit' => $netProfit,
                ],
            ],
            'profit-loss-' . $fromDate . '-to-' . $toDate
        );
    }
}

