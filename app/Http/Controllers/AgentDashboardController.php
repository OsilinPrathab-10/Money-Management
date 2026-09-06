<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Emi;
use App\Models\EmiAgentAssignment;
use App\Models\LoanAccount;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AgentDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $agent = $user->agent;

        if (!$agent) {
            abort(403, 'Unauthorized access: No agent profile found.');
        }

        $agentId = $agent->id;

        $today = Carbon::now()->startOfDay();

        // Stats
        $stats = [
            'total_clients' => Client::where(function($q) use ($agentId) {
                $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
            })->count(),
            'active_loans' => LoanAccount::whereHas('client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })->where('status', 'active')->count(),
            'pending_followups' => EmiAgentAssignment::where('agent_id', $agentId)
                ->active()
                ->onActiveLoan()
                ->count(),
            'overdue_emis' => Emi::whereHas('loanAccount.client', function ($q) use ($agentId) {
                    $q->where('assigned_to', $agentId)
                        ->orWhere('added_by', $agentId);
                })
                ->where(function ($q) use ($today) {
                    // Overdue payments (status != paid and due date is in the past)
                    $q->where(function ($sq) use ($today) {
                        $sq->where('due_date', '<', $today)
                           ->where('status', '!=', 'paid');
                    })
                    // OR the upcoming 1 payment of the client (earliest unpaid/partial EMI due today or in the future)
                    ->orWhereIn('id', function ($subQuery) use ($today) {
                        $subQuery->select(DB::raw('MIN(e2.id)'))
                            ->from('emis as e2')
                            ->where('e2.status', '!=', 'paid')
                            ->where('e2.due_date', '>=', $today)
                            ->groupBy('e2.loan_account_id');
                    });
                })
                ->count(),
        ];

        // Upcoming Follow-ups (Next 7 days)
        $upcomingFollowups = EmiAgentAssignment::with(['emi.loanAccount.client'])
            ->where('agent_id', $agentId)
            ->active()
            ->onActiveLoan()
            ->whereHas('emi', function($q) {
                $q->where('status', 'pending')
                  ->whereDate('due_date', '>=', now())
                  ->whereDate('due_date', '<=', now()->addDays(7));
            })
            ->get()
            ->sortBy(function($assignment) {
                return $assignment->emi->due_date;
            });

        // Recently Added or Assigned Clients
        $recentClients = Client::where(function($q) use ($agentId) {
                $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
            })
            ->latest()
            ->limit(5)
            ->get();

        return view('agent.dashboard', compact('stats', 'upcomingFollowups', 'recentClients'));
    }
}
