<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\Customer;
use App\Models\Account\CustomerPayment;
use App\Models\Account\Expense;
use App\Models\Account\Revenue;
use App\Models\Account\Vendor;
use App\Models\Account\VendorPayment;
use App\Models\Emi;
use App\Models\LoanAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Loan app: admins use the ERP-style company accounting dashboard.
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-dashboard'), 403);

        return $this->companyDashboard();
    }

    private function companyDashboard()
    {
        $creatorId = creatorId();

        $totalClients = Customer::where('created_by', $creatorId)->count();
        $totalVendors = Vendor::where('created_by', $creatorId)->count();
        $totalRevenue = Revenue::where('created_by', $creatorId)->sum('amount');
        $totalExpense = Expense::where('created_by', $creatorId)->sum('amount');
        $totalRevenuePosted = Revenue::where('created_by', $creatorId)->where('status', 'posted')->sum('amount');
        $totalExpensePosted = Expense::where('created_by', $creatorId)->where('status', 'posted')->sum('amount');
        $totalCustomerPayments = CustomerPayment::whereHas('customer', function ($q) use ($creatorId) {
            $q->where('created_by', $creatorId);
        })->sum('payment_amount');
        $totalVendorPayments = VendorPayment::whereHas('vendor', function ($q) use ($creatorId) {
            $q->where('created_by', $creatorId);
        })->sum('payment_amount');

        $netProfit = $totalRevenue - $totalExpense;
        $netProfitPosted = $totalRevenuePosted - $totalExpensePosted;

        $loanAccountsTotal = LoanAccount::query()->count();
        $loanAccountsActive = LoanAccount::query()->where('status', 'active')->count();
        $emisPending = Emi::query()->where('status', 'pending')->count();

        // Count rows marked overdue OR still pending with due date in the past (matches app behaviour).
        $emisOverdue = Emi::query()
            ->where(function ($q) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'pending')
                            ->whereNotNull('due_date')
                            ->whereDate('due_date', '<', today());
                    });
            })
            ->count();

        $recentRevenues = Revenue::where('created_by', $creatorId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->revenue_number,
                    'description' => $item->description ?? 'Revenue transaction',
                    'amount' => $item->amount,
                    'date' => $item->created_at,
                ];
            });

        $recentExpenses = Expense::where('created_by', $creatorId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->expense_number,
                    'description' => $item->description ?? 'Expense transaction',
                    'amount' => $item->amount,
                    'date' => $item->created_at,
                ];
            });

        $isDemo = config('app.is_demo');
        $monthlyCustomerPayments = [];
        $monthlyVendorPayments = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');

            if ($isDemo) {
                $customerPayments = rand(15000, 45000) + rand(0, 99) / 100;
                $vendorPayments = rand(5000, 25000) + rand(0, 99) / 100;
            } else {
                $customerPayments = CustomerPayment::whereHas('customer', function ($q) use ($creatorId) {
                    $q->where('created_by', $creatorId);
                })
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->sum('payment_amount');

                $vendorPayments = VendorPayment::whereHas('vendor', function ($q) use ($creatorId) {
                    $q->where('created_by', $creatorId);
                })
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->sum('payment_amount');
            }

            $monthlyCustomerPayments[] = [
                'month' => $monthName,
                'customer_payments' => $customerPayments,
            ];

            $monthlyVendorPayments[] = [
                'month' => $monthName,
                'vendor_payments' => $vendorPayments,
            ];
        }

        return view('admin.account.dashboard.company', [
            'stats' => [
                'total_clients' => $totalClients,
                'total_vendors' => $totalVendors,
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
                'total_revenue_posted' => $totalRevenuePosted,
                'total_expense_posted' => $totalExpensePosted,
                'total_customer_payment' => $totalCustomerPayments,
                'total_vendor_payment' => $totalVendorPayments,
                'net_profit' => $netProfit,
                'net_profit_posted' => $netProfitPosted,
            ],
            'monthlyCustomerPayments' => $monthlyCustomerPayments,
            'monthlyVendorPayments' => $monthlyVendorPayments,
            'recentRevenues' => $recentRevenues,
            'recentExpenses' => $recentExpenses,
            'loanPortfolio' => [
                'loan_accounts_total' => $loanAccountsTotal,
                'loan_accounts_active' => $loanAccountsActive,
                'emis_pending' => $emisPending,
                'emis_overdue' => $emisOverdue,
            ],
        ]);
    }
}

