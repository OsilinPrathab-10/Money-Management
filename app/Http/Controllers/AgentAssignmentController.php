<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\EmiAgentAssignment;
use Illuminate\Support\Facades\Auth;

class AgentAssignmentController extends Controller
{
    /**
     * Display a listing of the agent assignments.
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();
        $isAgent = $currentUser->hasRole('Agent');
        $agents = [];
        $myAssignments = collect();
        
        if ($currentUser->hasRole('Admin') || $currentUser->hasRole('Staff')) {
            $agents = Agent::where('status', 'active')->get();
        }

        if ($isAgent) {
            $agentId = $currentUser->agent?->id;
            if ($agentId) {
                $myAssignments = EmiAgentAssignment::with([
                    'emi.loanAccount.client',
                    'emi.loanAccount',
                ])
                ->where('agent_id', $agentId)
                ->whereIn('status', ['assigned', 'visited'])
                ->orderBy('assigned_at', 'desc')
                ->get();
            }
        }

        return view('admin.agents.assignments.index', compact('agents', 'isAgent', 'myAssignments'));
    }

    /**
     * Get paginated assignments data for datatables
     */
    public function list(Request $request)
    {
        $currentUser = Auth::user();
        $isAgent = $currentUser->hasRole('Agent');
        
        $query = EmiAgentAssignment::with(['agent', 'emi.loanAccount.client']);

        if ($isAgent) {
            $agentId = $currentUser->agent?->id;
            if (!$agentId) {
                return response()->json(['data' => []]);
            }
            $query->where('agent_id', $agentId);
        } else {
            if ($request->filled('agent_id')) {
                $query->where('agent_id', $request->agent_id);
            }
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'completed') {
                $query->whereIn('status', ['completed', 'resolved']);
            } else {
                $query->where('status', $status);
            }
        }

        $assignments = $query->orderBy('created_at', 'desc')->get()->map(function ($assignment) {
            $client = optional(optional(optional($assignment->emi)->loanAccount)->client);
            $loanAccount = optional(optional($assignment->emi)->loanAccount);
            $agent = optional($assignment->agent);
            $emi = $assignment->emi;
            
            return [
                'id' => $assignment->id,
                'real_emi_id' => $assignment->emi_id,
                'agent_name' => $agent->agent_name ?? 'N/A',
                'agent_code' => $agent->agent_code ?? 'N/A',
                'client_name' => $client->client_name ?? 'N/A',
                'account_number' => $loanAccount->account_number ?? 'N/A',
                'emi_number' => $emi ? ($emi->instalment_number ?? 'N/A') : 'N/A',
                'amount' => $emi ? ($emi->pending_amount ?? 0) : 0,
                'status' => $assignment->status,
                'emi_status' => $emi ? ($emi->status ?? 'N/A') : 'N/A',
                'assigned_at' => $assignment->assigned_at ? $assignment->assigned_at->format('d-m-Y h:i A') : 'N/A',
                'remarks' => $assignment->remarks,
            ];
        });

        return response()->json(['data' => $assignments]);
    }
}
