<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Emi;
use App\Models\EmiAgentAssignment;
use App\Models\GroupMember;
use App\Models\Installment;
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
        $activeTab = in_array($request->get('tab'), ['loan', 'chit'], true)
            ? $request->get('tab')
            : 'loan';

        $loanStats = $this->getLoanStats($agentId, $today);
        $chitStats = $this->getChitStats($agentId, $today);

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

        $upcomingChitInstallments = Installment::with(['member.client', 'group'])
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereDate('due_date', '=', now())
            ->whereRaw('(amount + penalty_amount - paid_amount) - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM chit_collections WHERE chit_collections.installment_id = installments.id AND chit_collections.status = "in_progress")')
            ->where(function ($q) use ($agentId) {
                $this->applyAgentMemberScope($q, $agentId);
            })
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        $recentClients = Client::where(function ($q) use ($agentId) {
                $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
            })
            ->latest()
            ->limit(8)
            ->get();

        $recentChitMembers = GroupMember::with(['client', 'group'])
            ->where(function ($q) use ($agentId) {
                $q->where('referred_by_agent_id', $agentId)
                    ->orWhereHas('client', function ($cq) use ($agentId) {
                        $cq->where('assigned_to', $agentId)
                            ->orWhere('added_by', $agentId);
                    })
                    ->orWhereHas('shares.client', function ($cq) use ($agentId) {
                        $cq->where('assigned_to', $agentId)
                            ->orWhere('added_by', $agentId);
                    });
            })
            ->whereHas('group', function ($gq) {
                $gq->whereIn('status', ['active', 'completed']);
            })
            ->latest()
            ->limit(8)
            ->get();

        return view('agent.dashboard', [
            'activeTab' => $activeTab,
            'loanStats' => $loanStats,
            'chitStats' => $chitStats,
            'stats' => $loanStats,
            'upcomingFollowups' => $upcomingFollowups,
            'upcomingChitInstallments' => $upcomingChitInstallments,
            'recentClients' => $recentClients,
            'recentChitMembers' => $recentChitMembers,
        ]);
    }

    private function getLoanStats(int $agentId, Carbon $today): array
    {
        return [
            'total_clients' => Client::where(function ($q) use ($agentId) {
                $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
            })->where(function ($q) {
                $q->whereHas('loanAccounts')->orWhereHas('loanApplications');
            })->count(),
            'active_loans' => LoanAccount::whereHas('client', function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
            })->where('status', 'active')->count(),
            'today_followups' => EmiAgentAssignment::where('agent_id', $agentId)
                ->active()
                ->onActiveLoan()
                ->whereHas('emi', function ($q) {
                    $q->whereIn('status', ['pending', 'overdue', 'partial'])
                        ->whereDate('due_date', '=', now())
                        ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                })
                ->count(),
            'overdue_emis' => Emi::whereHas('loanAccount.client', function ($q) use ($agentId) {
                    $q->where('assigned_to', $agentId)
                        ->orWhere('added_by', $agentId);
                })
                ->overdue()
                ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")')
                ->count(),
        ];
    }

    private function getChitStats(int $agentId, Carbon $today): array
    {
        $memberScope = function ($q) use ($agentId) {
            $q->where('referred_by_agent_id', $agentId)
                ->orWhereHas('client', function ($cq) use ($agentId) {
                    $cq->where('assigned_to', $agentId)
                        ->orWhere('added_by', $agentId);
                })
                ->orWhereHas('shares.client', function ($cq) use ($agentId) {
                    $cq->where('assigned_to', $agentId)
                        ->orWhere('added_by', $agentId);
                });
        };

        $activeMembers = GroupMember::where($memberScope)
            ->whereIn('status', ['active', 'approved'])
            ->whereHas('group', fn ($gq) => $gq->where('status', 'active'))
            ->count();

        $activeGroups = GroupMember::where($memberScope)
            ->whereHas('group', fn ($gq) => $gq->where('status', 'active'))
            ->distinct('group_id')
            ->count('group_id');

        $todayInstallments = Installment::whereIn('status', ['pending', 'overdue', 'partial'])
            ->where(function ($q) use ($agentId) {
                $this->applyAgentMemberScope($q, $agentId);
            })
            ->whereDate('due_date', '=', now())
            ->whereRaw('(amount + penalty_amount - paid_amount) - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM chit_collections WHERE chit_collections.installment_id = installments.id AND chit_collections.status = "in_progress")')
            ->whereHas('group', fn ($gq) => $gq->whereIn('status', ['active', 'completed']))
            ->count();

        $overdueInstallments = Installment::where(function ($q) use ($today) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($sq) use ($today) {
                        $sq->whereIn('status', ['pending', 'partial'])
                            ->where('due_date', '<', $today);
                    });
            })
            ->where(function ($q) use ($agentId) {
                $this->applyAgentMemberScope($q, $agentId);
            })
            ->whereRaw('(amount + penalty_amount - paid_amount) - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM chit_collections WHERE chit_collections.installment_id = installments.id AND chit_collections.status = "in_progress")')
            ->whereHas('group', fn ($gq) => $gq->whereIn('status', ['active', 'completed']))
            ->count();

        return [
            'active_members' => $activeMembers,
            'active_groups' => $activeGroups,
            'today_installments' => $todayInstallments,
            'overdue_installments' => $overdueInstallments,
        ];
    }

    private function applyAgentMemberScope($query, int $agentId): void
    {
        $query->whereHas('member', function ($mq) use ($agentId) {
            $mq->where(function ($memberQ) use ($agentId) {
                $memberQ->where('referred_by_agent_id', $agentId)
                    ->orWhereHas('client', function ($cq) use ($agentId) {
                        $cq->where('assigned_to', $agentId)
                            ->orWhere('added_by', $agentId);
                    })
                    ->orWhereHas('shares.client', function ($cq) use ($agentId) {
                        $cq->where('assigned_to', $agentId)
                            ->orWhere('added_by', $agentId);
                    });
            });
        });
    }
}
