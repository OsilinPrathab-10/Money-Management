<?php

namespace App\Http\Controllers\Account;

use App\Services\Account\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeReport(callable $callback): array
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function index()
    {
        if (Auth::user()->can('manage-account-reports')) {
            $currentYear = date('Y');
            $financialYear = [
                'year_start_date' => "$currentYear-01-01",
                'year_end_date' => "$currentYear-12-31",
            ];

            $invoicePreview = $this->reportService->getInvoiceAging(['as_of_date' => date('Y-m-d')]);
            $erpSalesInvoicesAvailable = $this->reportService->hasSalesInvoiceTables();

            $creatorId = creatorId();

            // Fetch overall accounting and loan data for report cards
            $totalRevenue = \App\Models\Account\Revenue::where('created_by', $creatorId)->sum('amount');
            $totalExpense = \App\Models\Account\Expense::where('created_by', $creatorId)->sum('amount');
            $netProfit = $totalRevenue - $totalExpense;
            
            $totalBankBalance = \App\Models\Account\BankAccount::where('created_by', $creatorId)->sum('current_balance') ?? 0;
            $totalOutstandingLoans = \App\Models\LoanAccount::where('status', 'active')->sum('outstanding_amount') ?? 0;
            $totalLoanDisbursed = \App\Models\LoanAccount::sum('disbursed_amount') ?? 0;
            $totalAccountsCount = \App\Models\Account\ChartOfAccount::where('created_by', $creatorId)->count();

            return view('admin.account.reports.index', [
                'financialYear' => $financialYear,
                'invoicePreview' => $invoicePreview,
                'erpSalesInvoicesAvailable' => $erpSalesInvoicesAvailable,
                'reportStats' => [
                    'netProfit' => $netProfit,
                    'totalBankBalance' => $totalBankBalance,
                    'totalRevenue' => $totalRevenue,
                    'totalExpense' => $totalExpense,
                    'totalOutstandingLoans' => $totalOutstandingLoans,
                    'totalLoanDisbursed' => $totalLoanDisbursed,
                    'totalAccountsCount' => $totalAccountsCount,
                ]
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function invoiceAging(Request $request)
    {
        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
        ];

        $data = $this->reportService->getInvoiceAging($filters);
        return response()->json($data);
    }

    public function billAging(Request $request)
    {
        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
        ];

        $data = $this->reportService->getBillAging($filters);
        return response()->json($data);
    }

    public function taxSummary(Request $request)
    {
        $currentYear = date('Y');
        $filters = [
            'from_date' => $request->from_date ?: "$currentYear-01-01",
            'to_date' => $request->to_date ?: "$currentYear-12-31",
        ];

        $data = $this->reportService->getTaxSummary($filters);
        return response()->json($data);
    }

    public function customerBalance(Request $request)
    {
        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
            'show_zero_balances' => $request->show_zero_balances === 'true',
        ];

        $data = $this->reportService->getCustomerBalanceSummary($filters);
        return response()->json($data);
    }

    public function vendorBalance(Request $request)
    {
        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
            'show_zero_balances' => $request->show_zero_balances === 'true',
        ];

        $data = $this->reportService->getVendorBalanceSummary($filters);
        return response()->json($data);
    }

    public function printInvoiceAging(Request $request)
    {
        if (! Auth::user()->can('print-invoice-aging')) {
            return back()->with('error', __('Permission denied'));
        }

        $filters = ['as_of_date' => $request->as_of_date ?: date('Y-m-d')];
        $data = $this->safeReport(fn () => $this->reportService->getInvoiceAging($filters));
        if (isset($data['error'])) {
            $data['aging_summary'] = ['current' => 0, '1_30_days' => 0, '31_60_days' => 0, '61_90_days' => 0, 'over_90_days' => 0, 'total' => 0];
            $data['customers'] = [];
        }

        return view('admin.account.reports.print-invoice-aging', compact('data', 'filters'));
    }

    public function printBillAging(Request $request)
    {
        if (! Auth::user()->can('print-bill-aging')) {
            return back()->with('error', __('Permission denied'));
        }

        $filters = ['as_of_date' => $request->as_of_date ?: date('Y-m-d')];
        $data = $this->safeReport(fn () => $this->reportService->getBillAging($filters));
        if (isset($data['error'])) {
            $data['aging_summary'] = ['current' => 0, '1_30_days' => 0, '31_60_days' => 0, '61_90_days' => 0, 'over_90_days' => 0, 'total' => 0];
            $data['vendors'] = [];
        }

        return view('admin.account.reports.print-bill-aging', compact('data', 'filters'));
    }

    public function printTaxSummary(Request $request)
    {
        if (! Auth::user()->can('print-tax-summary')) {
            return back()->with('error', __('Permission denied'));
        }

        $currentYear = date('Y');
        $filters = [
            'from_date' => $request->from_date ?: "$currentYear-01-01",
            'to_date' => $request->to_date ?: "$currentYear-12-31",
        ];
        $data = $this->safeReport(fn () => $this->reportService->getTaxSummary($filters));
        if (isset($data['error'])) {
            $data['tax_collected'] = ['items' => [], 'total' => 0];
            $data['tax_paid'] = ['items' => [], 'total' => 0];
            $data['net_tax_liability'] = 0;
        }

        return view('admin.account.reports.print-tax-summary', compact('data', 'filters'));
    }

    public function printCustomerBalance(Request $request)
    {
        if (! Auth::user()->can('print-customer-balance')) {
            return back()->with('error', __('Permission denied'));
        }
        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
            'show_zero_balances' => $request->show_zero_balances === 'true',
        ];
        $data = $this->safeReport(fn () => $this->reportService->getCustomerBalanceSummary($filters));
        if (isset($data['error'])) {
            $data['customers'] = [];
            $data['total_balance'] = 0;
        }

        $pageTitle = __('Customer balance');
        $balanceType = 'customer';

        return view('admin.account.reports.print-balances', compact('data', 'filters', 'pageTitle', 'balanceType'));
    }

    public function printVendorBalance(Request $request)
    {
        if (! Auth::user()->can('print-vendor-balance')) {
            return back()->with('error', __('Permission denied'));
        }

        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
            'show_zero_balances' => $request->show_zero_balances === 'true',
        ];
        $data = $this->safeReport(fn () => $this->reportService->getVendorBalanceSummary($filters));
        if (isset($data['error'])) {
            $data['vendors'] = [];
            $data['total_balance'] = 0;
        }

        $pageTitle = __('Vendor balance');
        $balanceType = 'vendor';

        return view('admin.account.reports.print-balances', compact('data', 'filters', 'pageTitle', 'balanceType'));
    }

    public function customerDetail($customerId, Request $request)
    {
        if(Auth::user()->can('view-customer-detail-report')){
            $filters = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ];

            $data = $this->reportService->getCustomerDetail($customerId, $filters);

            if (!$data) {
                return back()->with('error', __('Customer not found'));
            }

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Customer detail report'),
                'payload' => ['customerData' => $data],
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function vendorDetail($vendorId, Request $request)
    {
        if(Auth::user()->can('view-vendor-detail-report')){
            $filters = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ];

            $data = $this->reportService->getVendorDetail($vendorId, $filters);

            if (!$data) {
                return back()->with('error', __('Vendor not found'));
            }

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Vendor detail report'),
                'payload' => ['vendorData' => $data],
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function printCustomerDetail($customerId, Request $request)
    {
        if(Auth::user()->can('print-customer-detail-report')){
            $filters = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ];

            $data = $this->reportService->getCustomerDetail($customerId, $filters);

            if (!$data) {
                return back()->with('error', __('Customer not found'));
            }

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Customer detail (print)'),
                'payload' => ['data' => $data, 'filters' => $filters],
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function printVendorDetail($vendorId, Request $request)
    {
        if(Auth::user()->can('print-vendor-detail-report')){
            $filters = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ];

            $data = $this->reportService->getVendorDetail($vendorId, $filters);

            if (!$data) {
                return back()->with('error', __('Vendor not found'));
            }

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Vendor detail (print)'),
                'payload' => ['data' => $data, 'filters' => $filters],
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function exportInvoiceAging(Request $request)
    {
        abort_unless(Auth::user()->can('print-invoice-aging'), 403);
        $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'as_of_date' => 'nullable|date',
        ]);

        $filters = ['as_of_date' => $request->as_of_date ?: date('Y-m-d')];
        $data = $this->safeReport(fn () => $this->reportService->getInvoiceAging($filters));
        if (isset($data['error'])) {
            $data = [
                'aging_summary' => ['current' => 0, '1_30_days' => 0, '31_60_days' => 0, '61_90_days' => 0, 'over_90_days' => 0, 'total' => 0],
                'customers' => [],
                'as_of_date' => $filters['as_of_date'],
            ];
        }

        $base = 'invoice-aging-' . ($filters['as_of_date'] ?? date('Y-m-d'));

        return $this->exportByFormat($request->get('format'), 'admin.account.reports.exports.invoice-aging', compact('data', 'filters'), $base);
    }

    public function exportBillAging(Request $request)
    {
        abort_unless(Auth::user()->can('print-bill-aging'), 403);
        $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'as_of_date' => 'nullable|date',
        ]);

        $filters = ['as_of_date' => $request->as_of_date ?: date('Y-m-d')];
        $data = $this->safeReport(fn () => $this->reportService->getBillAging($filters));
        if (isset($data['error'])) {
            $data = [
                'aging_summary' => ['current' => 0, '1_30_days' => 0, '31_60_days' => 0, '61_90_days' => 0, 'over_90_days' => 0, 'total' => 0],
                'vendors' => [],
                'as_of_date' => $filters['as_of_date'],
            ];
        }

        $base = 'bill-aging-' . ($filters['as_of_date'] ?? date('Y-m-d'));

        return $this->exportByFormat($request->get('format'), 'admin.account.reports.exports.bill-aging', compact('data', 'filters'), $base);
    }

    public function exportTaxSummary(Request $request)
    {
        abort_unless(Auth::user()->can('print-tax-summary'), 403);
        $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $currentYear = date('Y');
        $filters = [
            'from_date' => $request->from_date ?: "$currentYear-01-01",
            'to_date' => $request->to_date ?: "$currentYear-12-31",
        ];
        $data = $this->safeReport(fn () => $this->reportService->getTaxSummary($filters));
        if (isset($data['error'])) {
            $data = [
                'tax_collected' => ['items' => collect(), 'total' => 0],
                'tax_paid' => ['items' => collect(), 'total' => 0],
                'net_tax_liability' => 0,
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
            ];
        }

        $base = 'tax-summary-' . ($filters['from_date'] ?? '') . '-to-' . ($filters['to_date'] ?? '');

        return $this->exportByFormat($request->get('format'), 'admin.account.reports.exports.tax-summary', compact('data', 'filters'), $base);
    }

    public function exportCustomerBalance(Request $request)
    {
        abort_unless(Auth::user()->can('print-customer-balance'), 403);
        $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'as_of_date' => 'nullable|date',
        ]);

        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
            'show_zero_balances' => $request->show_zero_balances === 'true',
        ];
        $data = $this->safeReport(fn () => $this->reportService->getCustomerBalanceSummary($filters));
        if (isset($data['error'])) {
            $data = ['customers' => [], 'total_balance' => 0, 'as_of_date' => $filters['as_of_date']];
        }

        $base = 'customer-balance-' . ($filters['as_of_date'] ?? date('Y-m-d'));
        $pageTitle = __('Customer balance');
        $balanceType = 'customer';

        return $this->exportByFormat($request->get('format'), 'admin.account.reports.exports.balances', compact('data', 'filters', 'pageTitle', 'balanceType'), $base);
    }

    public function exportVendorBalance(Request $request)
    {
        abort_unless(Auth::user()->can('print-vendor-balance'), 403);
        $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'as_of_date' => 'nullable|date',
        ]);

        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
            'show_zero_balances' => $request->show_zero_balances === 'true',
        ];
        $data = $this->safeReport(fn () => $this->reportService->getVendorBalanceSummary($filters));
        if (isset($data['error'])) {
            $data = ['vendors' => [], 'total_balance' => 0, 'as_of_date' => $filters['as_of_date']];
        }

        $base = 'vendor-balance-' . ($filters['as_of_date'] ?? date('Y-m-d'));
        $pageTitle = __('Vendor balance');
        $balanceType = 'vendor';

        return $this->exportByFormat($request->get('format'), 'admin.account.reports.exports.balances', compact('data', 'filters', 'pageTitle', 'balanceType'), $base);
    }

    public function trialBalance(Request $request)
    {
        return view('admin.account.reports.trial-balance', ['pageTitle' => __('Trial Balance')]);
    }

    public function balanceSheet(Request $request)
    {
        return view('admin.account.reports.balance-sheet', ['pageTitle' => __('Balance Sheet')]);
    }

    public function cashFlow(Request $request)
    {
        return view('admin.account.reports.cash-flow', ['pageTitle' => __('Cash Flow Statement')]);
    }

    public function cashBook(Request $request)
    {
        return view('admin.account.reports.cash-book', ['pageTitle' => __('Cash Book')]);
    }

    public function accountSummary(Request $request)
    {
        $creatorId = creatorId();
        $accountTypeId = $request->get('account_type_id');

        // Fetch all Account Types for the filter dropdown
        $accountTypes = \App\Models\Account\AccountType::where(function($q) use ($creatorId) {
            $q->where('created_by', $creatorId)
              ->orWhere('is_system_type', true);
        })->get();

        $query = \App\Models\Account\ChartOfAccount::with('accountType')
            ->where('created_by', $creatorId);

        if ($accountTypeId) {
            $query->where('account_type_id', $accountTypeId);
        }

        $accounts = $query->orderBy('account_code', 'asc')->get();

        return view('admin.account.reports.account-summary', [
            'pageTitle' => __('Account Summary Report'),
            'accounts' => $accounts,
            'accountTypes' => $accountTypes,
            'accountTypeId' => $accountTypeId,
        ]);
    }

    public function profitLoss(Request $request)
    {
        $creatorId = creatorId();
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-t'));

        // Revenue grouped by category
        $revenueQuery = \App\Models\Account\Revenue::with('category')
            ->where('created_by', $creatorId);
        if ($startDate) {
            $revenueQuery->whereDate('revenue_date', '>=', $startDate);
        }
        if ($endDate) {
            $revenueQuery->whereDate('revenue_date', '<=', $endDate);
        }
        $revenues = $revenueQuery->get();
        $revenueCategories = $revenues->groupBy('category_id')->map(function ($items) {
            return [
                'name' => $items->first()->category->name ?? __('Uncategorized'),
                'total' => $items->sum('amount')
            ];
        });
        $totalRevenue = $revenues->sum('amount');

        // Expense grouped by category
        $expenseQuery = \App\Models\Account\Expense::with('category')
            ->where('created_by', $creatorId);
        if ($startDate) {
            $expenseQuery->whereDate('expense_date', '>=', $startDate);
        }
        if ($endDate) {
            $expenseQuery->whereDate('expense_date', '<=', $endDate);
        }
        $expenses = $expenseQuery->get();
        $expenseCategories = $expenses->groupBy('category_id')->map(function ($items) {
            return [
                'name' => $items->first()->category->name ?? __('Uncategorized'),
                'total' => $items->sum('amount')
            ];
        });
        $totalExpense = $expenses->sum('amount');

        $netProfit = $totalRevenue - $totalExpense;

        return view('admin.account.reports.profit-loss', [
            'pageTitle' => __('Profit & Loss Statement'),
            'revenueCategories' => $revenueCategories,
            'totalRevenue' => $totalRevenue,
            'expenseCategories' => $expenseCategories,
            'totalExpense' => $totalExpense,
            'netProfit' => $netProfit,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    public function generalLedger(Request $request)
    {
        $creatorId = creatorId();
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-t'));
        $accountId = $request->get('account_id');

        // Fetch accounts for filter dropdown
        $accountsList = \App\Models\Account\ChartOfAccount::where('created_by', $creatorId)
            ->orderBy('account_name', 'asc')
            ->get();

        $accountsData = [];

        $accountsQuery = \App\Models\Account\ChartOfAccount::where('created_by', $creatorId);
        if ($accountId) {
            $accountsQuery->where('id', $accountId);
        }
        $accounts = $accountsQuery->orderBy('account_name', 'asc')->get();

        foreach ($accounts as $account) {
            $openingDebit = 0.00;
            $openingCredit = 0.00;

            // Base opening balance
            $opBal = \App\Models\Account\OpeningBalance::where('account_id', $account->id)->first();
            if ($opBal) {
                if ($opBal->balance_type === 'debit') {
                    $openingDebit += (float)$opBal->opening_balance;
                } else {
                    $openingCredit += (float)$opBal->opening_balance;
                }
            }

            // Sum up transactions before start date
            $priorTransactionsQuery = \App\Models\Account\JournalEntryItem::where('account_id', $account->id)
                ->whereHas('journalEntry', function($q) use ($startDate) {
                    $q->where('status', 'posted');
                    if ($startDate) {
                        $q->whereDate('journal_date', '<', $startDate);
                    }
                });

            $priorDebit = $priorTransactionsQuery->sum('debit_amount') ?? 0;
            $priorCredit = $priorTransactionsQuery->sum('credit_amount') ?? 0;

            $openingBalance = ($openingDebit + $priorDebit) - ($openingCredit + $priorCredit);

            // Get transactions within date range
            $itemsQuery = \App\Models\Account\JournalEntryItem::with('journalEntry')
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                    $q->where('status', 'posted');
                    if ($startDate) {
                        $q->whereDate('journal_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('journal_date', '<=', $endDate);
                    }
                });

            $items = $itemsQuery->get()->sortBy(function($item) {
                return $item->journalEntry->journal_date->timestamp;
            });

            $runningBalance = $openingBalance;
            $formattedItems = [];
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($items as $item) {
                $runningBalance += ((float)$item->debit_amount - (float)$item->credit_amount);
                $totalDebit += (float)$item->debit_amount;
                $totalCredit += (float)$item->credit_amount;
                $formattedItems[] = [
                    'date' => $item->journalEntry->journal_date,
                    'reference' => $item->journalEntry->journal_number,
                    'description' => $item->description ?: $item->journalEntry->description,
                    'debit' => (float)$item->debit_amount,
                    'credit' => (float)$item->credit_amount,
                    'running_balance' => $runningBalance
                ];
            }

            if (count($formattedItems) > 0 || $openingBalance != 0 || $accountId) {
                $accountsData[] = [
                    'account' => $account,
                    'opening_balance' => $openingBalance,
                    'items' => $formattedItems,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'closing_balance' => $runningBalance
                ];
            }
        }

        return view('admin.account.reports.general-ledger', [
            'pageTitle' => __('General Ledger Report'),
            'accountsData' => $accountsData,
            'accountsList' => $accountsList,
            'accountId' => $accountId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function dayBook(Request $request)
    {
        $creatorId = creatorId();
        $date = $request->get('date', date('Y-m-d'));

        // Query revenues for this day
        $revenues = \App\Models\Account\Revenue::with(['category', 'bankAccount'])
            ->where('created_by', $creatorId)
            ->whereDate('revenue_date', $date)
            ->get();

        // Query expenses for this day
        $expenses = \App\Models\Account\Expense::with(['category', 'bankAccount'])
            ->where('created_by', $creatorId)
            ->whereDate('expense_date', $date)
            ->get();

        return view('admin.account.reports.day-book', [
            'pageTitle' => __('Day Book Report'),
            'revenues' => $revenues,
            'expenses' => $expenses,
            'date' => $date,
        ]);
    }

    public function bankBook(Request $request)
    {
        $creatorId = creatorId();
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-t'));
        $bankAccountId = $request->get('bank_account_id');

        // Fetch bank accounts for the dropdown
        $bankAccounts = \App\Models\Account\BankAccount::where('created_by', $creatorId)->get();

        $query = \App\Models\Account\BankTransaction::with('bankAccount')
            ->where('created_by', $creatorId);

        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }
        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->get();

        return view('admin.account.reports.bank-book', [
            'pageTitle' => __('Bank Book'),
            'transactions' => $transactions,
            'bankAccounts' => $bankAccounts,
            'bankAccountId' => $bankAccountId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function revenueReport(Request $request)
    {
        $creatorId = creatorId();
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-t'));

        $query = \App\Models\Account\Revenue::with(['category', 'bankAccount'])
            ->where('created_by', $creatorId);

        if ($startDate) {
            $query->whereDate('revenue_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('revenue_date', '<=', $endDate);
        }

        $revenues = $query->orderBy('revenue_date', 'desc')->get();

        return view('admin.account.reports.revenue-report', [
            'pageTitle' => __('Revenue Report'),
            'revenues' => $revenues,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function expenseReport(Request $request)
    {
        $creatorId = creatorId();
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-t'));

        $query = \App\Models\Account\Expense::with(['category', 'bankAccount'])
            ->where('created_by', $creatorId);

        if ($startDate) {
            $query->whereDate('expense_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('expense_date', '<=', $endDate);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->get();

        return view('admin.account.reports.expense-report', [
            'pageTitle' => __('Expense Report'),
            'expenses' => $expenses,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function revenueCategory(Request $request)
    {
        return view('admin.account.reports.revenue-category', ['pageTitle' => __('Revenue Category Summary')]);
    }

    public function expenseCategory(Request $request)
    {
        return view('admin.account.reports.expense-category', ['pageTitle' => __('Expense Category Summary')]);
    }

    public function outstandingLoans(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = \App\Models\LoanAccount::with('client')
            ->where('status', 'active');

        if ($startDate) {
            $query->whereDate('disbursed_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('disbursed_at', '<=', $endDate);
        }

        $loans = $query->orderBy('disbursed_at', 'desc')->get();

        return view('admin.account.reports.outstanding-loans', [
            'pageTitle' => __('Outstanding Loans Report'),
            'loans' => $loans,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loanDisbursement(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-t'));

        $query = \App\Models\LoanAccount::with('client')
            ->whereNotNull('disbursed_at');

        if ($startDate) {
            $query->whereDate('disbursed_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('disbursed_at', '<=', $endDate);
        }

        $loans = $query->orderBy('disbursed_at', 'desc')->get();

        return view('admin.account.reports.loan-disbursement', [
            'pageTitle' => __('Loan Disbursement Report'),
            'loans' => $loans,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * @param  array<string, mixed>  $viewData
     */
    protected function exportByFormat(string $format, string $bladeView, array $viewData, string $filenameBase)
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filenameBase) ?? 'report';

        if ($format === 'pdf') {
            $pdf = Pdf::loadView($bladeView, $viewData)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'sans-serif',
                ]);

            return $pdf->download($safeName . '.pdf');
        }

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($bladeView, $viewData) {
                $out = fopen('php://output', 'w');
                fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                $this->renderReportAsCsv($out, $bladeView, $viewData);
                fclose($out);
            }, $safeName . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $html = view($bladeView, array_merge($viewData, ['exportMode' => 'excel']))->render();

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '.xls"',
        ]);
    }

    /**
     * @param  resource  $out
     * @param  array<string, mixed>  $viewData
     */
    protected function renderReportAsCsv($out, string $bladeView, array $viewData): void
    {
        $html = view($bladeView, array_merge($viewData, ['exportMode' => 'csv']))->render();
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows)) {
            foreach ($rows[1] as $rowHtml) {
                if (! preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $rowHtml, $cells)) {
                    continue;
                }
                $line = [];
                foreach ($cells[1] as $cell) {
                    $line[] = trim(html_entity_decode(strip_tags(str_replace('<br>', ' ', $cell)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
                fputcsv($out, $line);
            }
        }
    }
}

