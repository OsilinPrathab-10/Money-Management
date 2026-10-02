<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CardCashBillPayment;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashReturn;
use App\Models\CardCash\CardCashSwipeTransaction;
use App\Models\CardCash\CreditCardCustomer;
use App\Models\CardCash\CreditCardWallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardCashDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();

        // 1. KPI Stats
        $totalLeads = CardCashLead::count();
        $todayLeads = CardCashLead::whereDate('lead_date', $today)->count();
        $processingCount = CardCashLead::whereIn('status', ['new', 'processing', 'approved', 'payment_processing'])->count();
        $returnPendingCount = CardCashLead::where('status', 'return_pending')->count();
        $returnPendingAmount = CardCashLead::where('status', 'return_pending')->sum('requested_amount');
        $completedCount = CardCashLead::where('status', 'completed')->count();
        $failedCount = CardCashLead::whereIn('status', ['failed', 'cancelled', 'rejected'])->count();

        // Bill payments stats
        $billPaymentCount = CardCashBillPayment::count();
        $billPaymentVolume = CardCashBillPayment::where('status', 'payment_success')->sum('amount');

        // Swipe transactions stats
        $swipeCount = CardCashSwipeTransaction::count();
        $swipeVolume = CardCashSwipeTransaction::where('status', 'success')->sum('swipe_amount');

        // Returns & Profits/Charges stats
        $totalReturnsAmount = CardCashReturn::where('status', 'success')->sum('return_amount');
        $totalChargesEarned = CardCashReturn::where('status', 'success')->sum('charges')
            + CardCashSwipeTransaction::where('status', 'success')->sum('charges');

        // Total Wallet Balance
        $totalWalletBalance = CreditCardWallet::active()->sum('current_balance');
        $wallets = CreditCardWallet::active()->get();

        // Total Customers
        $totalCustomers = CreditCardCustomer::count();

        // 2. Chart: Last 14 Days Leads Trend
        $dates = [];
        $leadCounts = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $dates[] = $d->format('d M');
            $leadCounts[] = CardCashLead::whereDate('lead_date', $d)->count();
        }

        // 3. Status breakdown
        $statusCounts = CardCashLead::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 4. Return Method breakdown
        $returnMethods = CardCashReturn::selectRaw('return_method, count(*) as count, sum(return_amount) as total')
            ->groupBy('return_method')
            ->get();

        // 5. Recent Active Leads
        $recentLeads = CardCashLead::with(['customer', 'assignedStaff'])
            ->latest('id')
            ->take(8)
            ->get();

        return view('admin.card-to-cash.dashboard', compact(
            'totalLeads',
            'todayLeads',
            'processingCount',
            'returnPendingCount',
            'returnPendingAmount',
            'completedCount',
            'failedCount',
            'billPaymentCount',
            'billPaymentVolume',
            'swipeCount',
            'swipeVolume',
            'totalReturnsAmount',
            'totalChargesEarned',
            'totalWalletBalance',
            'wallets',
            'totalCustomers',
            'dates',
            'leadCounts',
            'statusCounts',
            'returnMethods',
            'recentLeads'
        ));
    }
}
