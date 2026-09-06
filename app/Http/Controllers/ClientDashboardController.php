<?php

namespace App\Http\Controllers;

use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\Emi;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientDashboardController extends Controller
{
    /**
     * Show the client dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $client = $user->client;

        if (!$client) {
            Auth::logout();
            return redirect()->route('client.login')->with('error', 'No client profile found for this user.');
        }

        // Get active loans
        $activeLoans = LoanAccount::where('client_id', $client->id)
            ->where('status', 'active')
            ->with(['loanApplication.product'])
            ->get();

        // Calculate summary stats
        $stats = [
            'total_loand_amount' => $activeLoans->sum('loan_amount'),
            'total_outstanding' => $activeLoans->sum('outstanding_amount'),
            'next_payment' => Emi::whereIn('loan_account_id', $activeLoans->pluck('id'))
                ->where('status', 'pending')
                ->where('due_date', '>=', now())
                ->orderBy('due_date', 'asc')
                ->first(),
            'overdue_count' => Emi::whereIn('loan_account_id', $activeLoans->pluck('id'))
                ->where('status', 'pending')
                ->where('due_date', '<', now())
                ->count()
        ];

        // Get loan applications
        $loanApplications = LoanApplication::where('client_id', $client->id)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('client.dashboard.index', compact('client', 'activeLoans', 'stats', 'loanApplications'));
    }

    /**
     * Show loan details and EMI schedule.
     */
    public function loanView($id)
    {
        $user = Auth::user();
        $client = $user->client;

        // Ensure the loan belongs to the client
        $loanAccount = LoanAccount::where('id', $id)
            ->where('client_id', $client->id)
            ->with(['loanApplication.product', 'loanApplication.client', 'emis' => function($q) {
                $q->with('collections')->orderBy('instalment_number', 'asc');
            }])
            ->firstOrFail();

        return view('client.dashboard.loan-view', compact('loanAccount'));
    }

    /**
     * Show the client's profile page.
     */
    public function profile()
    {
        $user = Auth::user();
        $client = $user->client;

        if (!$client) {
            return redirect()->route('client.dashboard')->with('error', 'Client profile not found.');
        }

        return view('client.profile.index', compact('client', 'user'));
    }
}
