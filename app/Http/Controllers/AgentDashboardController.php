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
    public function index(Request $request)
    {
        $user = auth()->user();
        $agent = $user->agent;

        if (!$agent) {
            abort(403, 'Unauthorized access: No agent profile found.');
        }

        $agentId = $agent->id;
        $today = Carbon::now()->startOfDay();
        $activeTab = 'loan';

        $loanStats = $this->getLoanStats($agentId, $today);

        $upcomingFollowups = EmiAgentAssignment::with(['emi.loanAccount.client'])
            ->where('agent_id', $agentId)
            ->active()
            ->onActiveLoan()
            ->whereHas('emi', function ($q) {
                $q->whereIn('status', ['pending', 'overdue', 'partial'])
                    ->whereDate('due_date', '=', now())
                    ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
            })
            ->get()
            ->sortBy(fn ($assignment) => $assignment->emi->due_date);

        $recentClients = Client::where(function ($q) use ($agentId) {
                $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
            })
            ->latest()
            ->limit(8)
            ->get();

        return view('agent.dashboard', [
            'activeTab' => $activeTab,
            'loanStats' => $loanStats,
            'stats' => $loanStats,
            'upcomingFollowups' => $upcomingFollowups,
            'recentClients' => $recentClients,
        ]);
    }

    private function getLoanStats(int $agentId, Carbon $today): array
    {
        return [
            'total_clients' => Client::where(function ($q) use ($agentId) {
                    $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
                })->count(),
            'active_loans' => LoanAccount::whereHas('client', function ($q) use ($agentId) {
                    $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
                })->where('status', 'active')->count(),
            'today_emis' => Emi::whereHas('loanAccount.client', function ($q) use ($agentId) {
                    $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
                })
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->whereDate('due_date', '=', now())
                ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")')
                ->whereHas('loanAccount', fn ($lq) => $lq->where('status', 'active'))
                ->count(),
            'overdue_emis' => Emi::whereHas('loanAccount.client', function ($q) use ($agentId) {
                    $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
                })
                ->where(function ($q) use ($today) {
                    $q->where('status', 'overdue')
                        ->orWhere(function ($sq) use ($today) {
                            $sq->whereIn('status', ['pending', 'partial'])
                                ->where('due_date', '<', $today);
                        });
                })
                ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")')
                ->whereHas('loanAccount', fn ($lq) => $lq->where('status', 'active'))
                ->count(),
        ];
    }
}
