<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AgentDailyLog;
use Illuminate\Support\Facades\Auth;

class EnsureAgentCheckedIn
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get authenticated user (agent)
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHENTICATED',
                'message' => 'Please login first'
            ], 401);
        }

        // COMMENTED OUT: Check-in validation temporarily disabled
        /*
        // Get agent ID
        $agentId = $user->id;
        
        // Check today's log
        $today = now()->startOfDay();
        $todayLog = AgentDailyLog::where('agent_id', $agentId)
            ->whereDate('check_in_at', $today)
            ->first();
        
        // If no log exists for today, agent hasn't checked in
        if (!$todayLog) {
            return response()->json([
                'success' => false,
                'error' => 'NOT_CHECKED_IN',
                'message' => 'Please check in to start your day',
                'action_required' => 'check_in'
            ], 403);
        }
        
        // If agent has checked out, block all actions
        if ($todayLog->status === 'checked_out') {
            return response()->json([
                'success' => false,
                'error' => 'ALREADY_CHECKED_OUT',
                'message' => 'You have checked out for today. Please check in again tomorrow',
                'check_out_time' => $todayLog->check_out_at ? $todayLog->check_out_at->format('Y-m-d H:i:s') : null
            ], 403);
        }
        */
        
        // Agent is checked in, allow request to proceed
        return $next($request);
    }
}
