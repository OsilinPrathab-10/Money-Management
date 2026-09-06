<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AgentDashboardResource extends JsonResource
{
    public function toArray($request): array
    {
        $agent = $this;

        // Get today's check-in log
        $todayLog = \App\Models\AgentDailyLog::where('agent_id', $agent->id)
            ->whereDate('check_in_at', now()->toDateString())
            ->first();

        $isCheckedIn = $todayLog && $todayLog->status === 'checked_in';

        // Get all EMI assignments for the agent
        $allAssignments = $agent->emiAssignments()
            ->active()
            ->onActiveLoan()
            ->with('emi')
            ->get();

        // Recovery details
        $totalRecovered = $agent->recoveries()->sum('amount');
        $totalAssigned = $agent->emiAssignments()
            ->join('emis', 'emi_agent_assignments.emi_id', '=', 'emis.id')
            ->sum('emis.total_amount');
        $recoveryRate = $totalAssigned > 0 ? ($totalRecovered / $totalAssigned) * 100 : 0;

        // Visits details
        $visitsComplete = $agent->emiAssignments()
            ->where(function ($q) {
                $q->whereNotNull('visited_at')
                    ->orWhereIn('status', ['visited', 'resolved']);
            })
            ->count();

        // Promise details (PTP kept)
        $promiseKept = $agent->followups()
            ->whereHas('emi', function ($q) {
                $q->where('status', 'paid');
            })
            ->where(function ($q) {
                $q->where('status', 'like', '%promise%')
                    ->orWhere('status', 'like', '%ptp%');
            })
            ->count();

        // NEW METRICS - This Month
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Total followups this month (based on followup_at date)
        $totalFollowupsThisMonth = $agent->followups()
            ->whereMonth('followup_at', $currentMonth)
            ->whereYear('followup_at', $currentYear)
            ->count();

        // Recovered count this month (count of EMIs paid that are assigned to agent)
        $recoveredThisMonth = $agent->emiAssignments()
            ->whereHas('emi', function ($q) use ($currentMonth, $currentYear) {
                $q->where('status', 'paid')
                    ->whereMonth('paid_date', $currentMonth)
                    ->whereYear('paid_date', $currentYear);
            })
            ->count();

        // At risk count this month (EMIs with high risk status assigned to agent)
        $atRiskCountThisMonth = $agent->emiAssignments()
            ->whereHas('emi', function ($q) {
                $q->whereIn('status', ['pending', 'overdue', 'partial'])
                    ->where(function ($sq) {
                        // DPD > 15 (calculate days past due in database)
                        $sq->whereRaw('DATEDIFF(NOW(), due_date) > 15')
                            // OR 2+ overdue EMIs in same loan account
                            ->orWhereRaw('(SELECT COUNT(*) FROM emis e2 WHERE e2.loan_account_id = emis.loan_account_id AND e2.status = "overdue") >= 2');
                    });
            })
            ->distinct('emi_id')
            ->count('emi_id');

        // NEW METRICS - Today
        $today = now()->toDateString();

        // Today cases count (unique loan accounts assigned to agent with pending EMIs)
        $todayCasesCount = $agent->emiAssignments()
            ->onActiveLoan()
            ->whereDate('emi_agent_assignments.assigned_at', $today)
            ->whereIn('emi_agent_assignments.status', ['assigned', 'visited'])
            ->whereHas('emi', function ($q) {
                $q->where('status', '!=', 'paid')
                    ->whereDoesntHave('collections', function ($sq) {
                        $sq->whereIn('status', ['verified', 'in_progress']);
                    });
            })
            ->join('emis', 'emi_agent_assignments.emi_id', '=', 'emis.id')
            ->distinct('emis.loan_account_id')
            ->count('emis.loan_account_id');

        // Today visit count (only appointments to visit)
        // We only count if this is the LATEST followup for the EMI and it's still pending
        $latestToday = \App\Models\EmiFollowup::selectRaw('MAX(id) as id')
            ->where('agent_id', $agent->id)
            ->groupBy('emi_id');

        $todayVisitCount = $agent->followups()
            ->whereIn('id', $latestToday)
            ->where('status', 'appointment_to_visit')
            ->whereDate('followup_at', $today)
            ->whereHas('emi', function ($q) use ($agent) {
                // Only count if EMI is still overdue (not paid)
                $q->whereIn('status', ['overdue', 'pending', 'partial'])
                    ->whereHas('loanAccount', function ($lq) {
                        $lq->activeForCollection();
                    })
                    ->whereHas('assignments', function ($sq) use ($agent) {
                    // Include both 'assigned' and 'visited'
                    $sq->where('agent_id', $agent->id)
                        ->whereIn('status', ['assigned', 'visited']);
                })
                    ->whereDoesntHave('collections', function ($sq) use ($agent) {
                    // EXCLUDE if there's already an active collection (means it moved to 'In Progress')
                    $sq->where('agent_id', $agent->id)
                        ->where('status', 'in_progress');
                });
            })
            ->count();

        // All cases count (unique loan accounts with active assignments)
        $allCasesCount = $agent->emiAssignments()
            ->onActiveLoan()
            ->whereIn('emi_agent_assignments.status', ['assigned', 'visited'])
            ->whereHas('emi', function ($q) {
                $q->where('status', '!=', 'paid')
                    ->whereDoesntHave('collections', function ($sq) {
                        $sq->whereIn('status', ['verified', 'in_progress']);
                    });
            })
            ->join('emis', 'emi_agent_assignments.emi_id', '=', 'emis.id')
            ->distinct('emis.loan_account_id')
            ->count('emis.loan_account_id');

        // Today at risk count (unique loan accounts with high risk EMIs)
        $todayAtRiskCount = $agent->emiAssignments()
            ->onActiveLoan()
            ->whereIn('emi_agent_assignments.status', ['assigned', 'visited'])
            ->whereHas('emi', function ($q) {
                $q->whereIn('status', ['pending', 'overdue', 'partial'])
                    ->where(function ($sq) {
                        // DPD > 15 (calculate days past due in database)
                        $sq->whereRaw('DATEDIFF(NOW(), due_date) > 15')
                            // OR 2+ overdue EMIs in same loan account
                            ->orWhereRaw('(SELECT COUNT(*) FROM emis e2 WHERE e2.loan_account_id = emis.loan_account_id AND e2.status = "overdue") >= 2');
                    });
            })
            ->join('emis', 'emi_agent_assignments.emi_id', '=', 'emis.id')
            ->distinct('emis.loan_account_id')
            ->count('emis.loan_account_id');

        return [
            'agent_name' => $agent->agent_name,
            'agent_phone' => $agent->agent_phone,
            'profile_image_url' => $agent->profile_image ? Storage::disk('public')->url($agent->profile_image) : null,
            'agent_role' => 'Recovery Agent',
            'check_in_status' => $isCheckedIn ? 'active' : 'inactive',
            'check_in_time' => $todayLog && $todayLog->check_in_at ? $todayLog->check_in_at->format('d-m-Y h:i A') : null,
            'check_out_time' => $todayLog && $todayLog->check_out_at ? $todayLog->check_out_at->format('d-m-Y h:i A') : null,

            // requested statistics
            'total_follow_ups' => $totalFollowupsThisMonth,
            'all_cases' => $allCasesCount,
            'today_cases' => $todayCasesCount,
            'today_visit' => $todayVisitCount,
            'recovered_this_month' => $recoveredThisMonth,
            'today_at_risk' => $todayAtRiskCount,
        ];
    }
}
