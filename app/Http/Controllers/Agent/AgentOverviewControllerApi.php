<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AgentOverviewControllerApi extends Controller
{
    public function outstandingOverview(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = Carbon::today()->toDateString();

        // Get today's followups for this agent
        $todayFollowups = DB::table('emi_followups')
            ->where('agent_id', $agentId)
            ->whereDate('followup_at', $today)
            ->get();

        $totalFollowups = $todayFollowups->count();
        
        // Completed followups are those that have been updated (followed up on)
        $completedFollowups = $todayFollowups->filter(function ($followup) {
            return $followup->updated_at > $followup->created_at;
        })->count();

        // Missed followups are those that haven't been completed yet
        $missedFollowups = $totalFollowups - $completedFollowups;

        // Calculate percentage
        $completionPercentage = $totalFollowups > 0 
            ? round(($completedFollowups / $totalFollowups) * 100) 
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'today_target' => [
                    'title' => "Finish today's follow-up calls",
                    'completed' => $completedFollowups,
                    'total' => $totalFollowups,
                    'progress_text' => "{$completedFollowups} of {$totalFollowups} follow-ups completed",
                    'progress_percentage' => $completionPercentage,
                    'progress_label' => "{$completionPercentage}% of today's target",
                ],
                'completed_followups' => $completedFollowups,
                'missed_followups' => $missedFollowups,
            ],
        ]);
    }
}
