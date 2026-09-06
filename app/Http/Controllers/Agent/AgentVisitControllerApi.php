<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\EmiAgentAssignment;

class AgentVisitControllerApi extends Controller
{
    public function index(Request $request)
    {
        $agentId = Auth::user()->id;
        $type = $request->get('type', 'all');

        $query = EmiAgentAssignment::with(['emi.loanAccount.client.location'])
            ->where('agent_id', $agentId)
            ->onActiveLoan();
        // ->whereDate('assigned_at', now());

        if ($type === 'pending') {
            $query->whereNull('visited_at')
                ->whereHas(
                    'emi',
                    fn($q) =>
                    $q->where('status', '!=', 'paid')
                );
        }

        if ($type === 'overdue') {
            $query->whereHas(
                'emi',
                fn($q) =>
                $q->where('status', '!=', 'paid')
                    ->whereDate('due_date', '<', now())
            );
        }

        if ($type === 'recovered') {
            $query->whereHas(
                'emi',
                fn($q) =>
                $q->where('status', 'paid')
            );
        }

        $assignments = $query->get();

        return response()->json([
            'type' => $type,
            'count' => $assignments->count(),
            'visits' => $assignments->map(fn($a) => $this->mapVisit($a))
        ]);
    }

    private function mapVisit($assignment)
    {
        $emi = $assignment->emi;
        $loan = $emi->loanAccount;

        return [
            'emi_id' => $emi->id,
            'customer_name' => $loan->client->client_name,
            'client_phone' => $loan->client->client_phone,
            'profile_image_url' => $loan->client->profile_image_url,
            'loan_account_number' => $loan->account_number,
            'visit_time' => optional($assignment->assigned_at)->format('h:i A'),
            'location' => optional(optional($loan->client)->location)->name,
            'status' => $this->resolveStatus($assignment, $emi),
            'dpd' => max(now()->diffInDays($emi->due_date, false), 0),
            'due_amount' => $emi->total_due, // Changed from pending_amount to total_due
        ];
    }

    public function todayVisits(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = now()->toDateString();

        // Fetch "Appointment to Visit" from Followups scheduled for today
        // We only show if this is the LATEST followup for the EMI
        $latestFollowups = \App\Models\EmiFollowup::selectRaw('MAX(id) as id')
            ->where('agent_id', $agentId)
            ->groupBy('emi_id');

        $visits = \App\Models\EmiFollowup::whereIn('id', $latestFollowups)
            ->whereIn('status', ['appointment_to_visit', 'visit_rescheduled'])
            ->whereDate('followup_at', $today)
            ->whereHas('emi', function ($q) use ($agentId) {
                // Only show if EMI is still overdue/pending (not paid)
                $q->whereIn('status', ['overdue', 'pending', 'partial'])
                    ->whereHas('loanAccount', function ($lq) {
                        $lq->activeForCollection();
                    })
                    ->whereHas('assignments', function ($sq) use ($agentId) {
                    // Include both 'assigned' and 'visited' (so it stays in the list during visit)
                    $sq->where('agent_id', $agentId)
                        ->whereIn('status', ['assigned', 'visited']);
                })
                    ->whereDoesntHave('collections', function ($sq) use ($agentId) {
                    // EXCLUDE if there's already an active collection (means it moved to 'In Progress')
                    $sq->where('agent_id', $agentId)
                        ->where('status', 'in_progress');
                });
            })
            ->with(['emi.loanAccount.client.location'])
            ->orderBy('followup_at')
            ->get();

        // Pre-fetch active assignments to check "In Progress" status
        $emiIds = $visits->pluck('emi_id')->toArray();
        $assignmentsToday = EmiAgentAssignment::where('agent_id', $agentId)
            ->whereIn('emi_id', $emiIds)
            // ->whereDate('updated_at', '>=', $today) // Check if valid for today
            ->get()
            ->keyBy('emi_id');

        $data = $visits->map(function ($followup) use ($assignmentsToday) {
            $emi = $followup->emi;
            $loan = $emi->loanAccount;
            $client = $loan->client;

            // Determine Status
            $assignment = $assignmentsToday->get($emi->id);
            $status = 'Pending';

            // 1. Recovered Check
            if ($emi->status === 'paid' || $emi->pending_amount <= 0) {
                $status = 'Recovered';
            }
            // 2. In Progress Check
            elseif ($assignment && $assignment->visited_at && !$assignment->resolved_at) {
                $status = 'In Progress';
            }
            // 3. Completed Check (if resolved)
            elseif ($assignment && $assignment->resolved_at) {
                $status = 'Completed';
            }

            return [
                'visit_id' => null, // No visit log ID yet, created on start
                'emi_id' => $emi->id,
                'loan_account_id' => $loan->id,
                'customer_name' => $client->client_name,
                'client_phone' => $client->client_phone,
                'profile_image_url' => $client->profile_image_url,
                'loan_account_number' => $loan->account_number,
                'visit_time' => $followup->followup_at ? $followup->followup_at->format('h:i A') : '--:--',
                'location' => optional(optional($client)->location)->name,
                'status' => $status,
                'dpd' => (int) floor(max($emi->due_date->diffInDays(now(), false), 0)),
                'due_amount' => (float) $emi->total_due,
                'source' => $followup->status
            ];
        });

        return response()->json([
            'date' => now()->format('D, d-m-Y'),
            'count' => $data->count(),
            'visits' => $data
        ]);
    }

    public function startVisit(Request $request)
    {
        $request->validate([
            'emi_id' => 'required|exists:emis,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $agentId = Auth::user()->id;
        $emiId = $request->emi_id;

        // 1. Create or Update Assignment (State Management)
        $assignment = EmiAgentAssignment::where('agent_id', $agentId)
            ->where('emi_id', $emiId)
            ->latest()
            ->first();

        $shouldCreateAssignment = true;
        if ($assignment) {
            if ($assignment->updated_at->diffInDays(now()) <= 3) {
                $shouldCreateAssignment = false;
                if (!$assignment->visited_at) {
                    $assignment->update([
                        'status' => 'visited',
                        'visited_at' => now(),
                    ]);
                }
            }
        }
        if ($shouldCreateAssignment) {
            EmiAgentAssignment::create([
                'agent_id' => $agentId,
                'emi_id' => $emiId,
                'status' => 'visited',
                'assigned_at' => now(),
                'visited_at' => now(),
            ]);
        }

        // 2. Create Visit Log (Location Tracking)
        $visitLog = \App\Models\AgentVisitLog::create([
            'agent_id' => $agentId,
            'emi_id' => $emiId,
            'start_latitude' => $request->latitude,
            'start_longitude' => $request->longitude,
            'started_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visit started successfully',
            'status' => 'In Progress',
            'visit_id' => $visitLog->id // Return ID for stop-visit usage
        ]);
    }

    public function stopVisit(Request $request)
    {
        $request->validate([
            'visit_id' => 'nullable|exists:agent_visit_logs,id', // Can optional if we infer last active
            'emi_id' => 'required_without:visit_id|exists:emis,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $agentId = Auth::user()->id;

        $visitLog = null;

        if ($request->visit_id) {
            $visitLog = \App\Models\AgentVisitLog::where('agent_id', $agentId)
                ->find($request->visit_id);
        } elseif ($request->emi_id) {
            // Find the latest active visit log for this EMI
            $visitLog = \App\Models\AgentVisitLog::where('agent_id', $agentId)
                ->where('emi_id', $request->emi_id)
                ->whereNull('ended_at') // Still open
                ->latest()
                ->first();
        }

        if (!$visitLog) {
            return response()->json([
                'success' => false,
                'message' => 'Active visit not found'
            ], 404);
        }

        $visitLog->update([
            'end_latitude' => $request->latitude,
            'end_longitude' => $request->longitude,
            'ended_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visit ended successfully',
        ]);
    }

    private function resolveStatus($assignment, $emi)
    {
        if ($emi->status === 'paid') {
            return 'Recovered';
        }

        if ($assignment->visited_at && !$assignment->resolved_at) {
            return 'In Progress';
        }

        if ($emi->due_date < now()) {
            return 'Overdue';
        }

        return 'Pending';
    }

    /**
     * Helper method to automatically stop active visit for an EMI
     * Called after status update or payment collection
     */
    public static function autoStopVisit($agentId, $emiId, $latitude = null, $longitude = null)
    {
        // Find the latest active visit log for this EMI
        $visitLog = \App\Models\AgentVisitLog::where('agent_id', $agentId)
            ->where('emi_id', $emiId)
            ->whereNull('ended_at') // Still open
            ->latest()
            ->first();

        if ($visitLog) {
            $visitLog->update([
                'end_latitude' => $latitude,
                'end_longitude' => $longitude,
                'ended_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Get all recovered visits for the agent
     * Shows EMIs that have been fully paid
     */
    public function recoveredVisits(Request $request)
    {
        $agentId = Auth::user()->id;

        // Get all assignments where EMI is paid
        $recoveredAssignments = EmiAgentAssignment::where('agent_id', $agentId)
            ->whereHas('emi', function ($q) {
                $q->where('status', 'paid')
                    ->orWhere('pending_amount', '<=', 0);
            })
            ->with([
                'emi.loanAccount.client.location',
                'emi.collections' => function ($q) use ($agentId) {
                    $q->where('agent_id', $agentId)->latest();
                }
            ])
            ->latest('resolved_at')
            ->get();

        $data = $recoveredAssignments->map(function ($assignment) {
            $emi = $assignment->emi;
            $loan = $emi->loanAccount;
            $client = $loan->client;
            $lastCollection = $emi->collections->first();

            return [
                'emi_id' => $emi->id,
                'loan_account_id' => $loan->id,
                'customer_name' => $client->client_name,
                'client_phone' => $client->client_phone,
                'profile_image_url' => $client->profile_image_url,
                'loan_account_number' => $loan->account_number,
                'total_amount' => (float) $emi->total_due, // Changed from total_amount to total_due
                'recovered_date' => $emi->paid_date ? $emi->paid_date->format('d-m-Y') : null,
                'payment_mode' => $lastCollection ? ucfirst($lastCollection->payment_method) : 'Online',
                'status' => 'Recovered',
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'recovered_visits' => $data,
        ]);
    }
}
