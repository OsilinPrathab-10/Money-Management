<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentDashboardResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

use App\Models\AgentDailyLog;
use Illuminate\Support\Carbon;
use App\Models\EmiFollowup;
use Illuminate\Support\Facades\DB;
use App\Models\EmiAgentAssignment;
use App\Models\AgentNotification;
use App\Services\PushNotificationService;

class AgentDashboardControllerApi extends Controller
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    public function checkIn(Request $request)
    {
        $agent = Auth::user();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        // Check if already checked in today (one check-in per day)
        $todayLog = AgentDailyLog::where('agent_id', $agent->id)
            ->whereDate('check_in_at', Carbon::today())
            ->first();

        if ($todayLog) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked in today.'
            ], 400);
        }

        $log = AgentDailyLog::create([
            'agent_id' => $agent->id,
            'check_in_at' => now(),
            'check_in_lat' => $request->latitude,
            'check_in_long' => $request->longitude,
            'status' => 'checked_in',
        ]);

        // Send push notification
        $this->sendAttendanceNotification($agent, 'check_in', $log->check_in_at->format('h:i A'));

        return response()->json([
            'success' => true,
            'message' => 'Checked in successfully.',
            'data' => [
                'id' => $log->id,
                'agent_id' => $log->agent_id,
                'check_in_at' => $log->check_in_at->format('d-m-Y h:i A'),
                'check_in_lat' => $log->check_in_lat,
                'check_in_long' => $log->check_in_long,
                'status' => $log->status,
                'created_at' => $log->created_at,
                'updated_at' => $log->updated_at,
            ]
        ]);
    }

    public function checkoutSummary(Request $request)
    {
        $agent = Auth::user();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        // 1. Get all predefined Status Options from config
        $configStatuses = config('followup.status_options', []);
        
        // Initialize summary with 0 for all config statuses
        $summary = collect($configStatuses)->mapWithKeys(function ($label, $key) {
            return [$label => 0];
        })->toArray();
        
        // 2. Get Actual Status Counts for Today
        $dbCounts = EmiFollowup::where('agent_id', $agent->id)
            ->whereDate('created_at', Carbon::today())
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();
            
        // 3. Update summary with actuals
        foreach ($dbCounts as $item) {
             // If status exists in config, use its label. Else                      the raw status key.
            $label = $configStatuses[$item->status] ?? ucwords(str_replace('_', ' ', $item->status));
            $summary[$label] = $item->total;
        }
        
        // 4. Explicitly count "Recovered" (Resolved assignments today)
        $recoveredCount = EmiAgentAssignment::where('agent_id', $agent->id)
            ->whereDate('resolved_at', Carbon::today())
            ->where('status', 'resolved')
            ->count();
            
        $summary['Recovered'] = $recoveredCount;

        return response()->json([
            'success' => true,
            'status_summary' => $summary
        ]);
    }

    public function checkOut(Request $request)
    {
        $agent = Auth::user();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        $request->validate([
            'notes' => 'required|string|max:1000',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        // Find today's check-in log
        $todayLog = AgentDailyLog::where('agent_id', $agent->id)
            ->whereDate('check_in_at', Carbon::today())
            ->where('status', 'checked_in')
            ->first();

        if (!$todayLog) {
            return response()->json([
                'success' => false,
                'message' => 'No check-in found for today. Please check in first.'
            ], 400);
        }

        $todayLog->update([
            'check_out_at' => now(),
            'check_out_lat' => $request->latitude,
            'check_out_long' => $request->longitude,
            'notes' => $request->notes,
            'status' => 'checked_out',
        ]);

        // Send push notification
        $this->sendAttendanceNotification($agent, 'check_out', $todayLog->check_out_at->format('h:i A'));

        return response()->json([
            'success' => true,
            'message' => 'Checked out successfully.',
            'data' => $todayLog,
        ]);
    }

    public function dailyLogs(Request $request)
    {
        $agent = Auth::user();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        $query = AgentDailyLog::where('agent_id', $agent->id);

        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'this_week':
                    $query->whereBetween('check_in_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'last_week':
                    $query->whereBetween('check_in_at', [
                        Carbon::now()->subWeek()->startOfWeek(),
                        Carbon::now()->subWeek()->endOfWeek()
                    ]);
                    break;
                case 'last_30_days':
                    $query->whereDate('check_in_at', '>=', Carbon::now()->subDays(30));
                    break;
                case 'last_90_days':
                    $query->whereDate('check_in_at', '>=', Carbon::now()->subDays(90));
                    break;
            }
        }

        $logs = $query->orderByDesc('check_in_at')
            ->paginate(15);

        $formattedLogs = collect($logs->items())->map(function ($log) use ($agent) {
            $workingHours = null;
            if ($log->check_in_at && $log->check_out_at) {
                $duration = $log->check_in_at->diff($log->check_out_at);
                $workingHours = sprintf('%dh %dm', $duration->h + ($duration->days * 24), $duration->i);
            }

            // Get status update details for this specific date (only if checked out)
            $statusSummary = [];
            
            if ($log->check_out_at) {
                $date = $log->check_in_at->toDateString();
                $configStatuses = config('followup.status_options', []);
                
                // Get followup counts for this date
                $statusCounts = EmiFollowup::where('agent_id', $agent->id)
                    ->whereDate('created_at', $date)
                    ->select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->get();
                
                // Only add statuses that have counts > 0
                foreach ($statusCounts as $item) {
                    if ($item->total > 0) {
                        $label = $configStatuses[$item->status] ?? ucwords(str_replace('_', ' ', $item->status));
                        $statusSummary[$label] = $item->total;
                    }
                }
            }

            return [

                'id' => $log->id,
                'date' => $log->check_in_at ? $log->check_in_at->format('d-m-Y') : null,
                'check_in' => $log->check_in_at ? $log->check_in_at->format('h:i A') : null,
                'check_out' => $log->check_out_at ? $log->check_out_at->format('h:i A') : null,
                'working_hours' => $workingHours,
                'notes' => $log->notes,
                'status' => ucfirst(str_replace('_', ' ', $log->status)),
                'reports' => $statusSummary,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedLogs,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ]
        ]);
    }

    public function showDailyLog(Request $request, $id)
    {
        $agentId = Auth::user()->id;
        $log = AgentDailyLog::where('agent_id', $agentId)->findOrFail($id);
        
        $date = $log->check_in_at->toDateString();
        $configStatuses = config('followup.status_options', []);

        // Initialize empty report
        $report = [];

        // Get status counts for that specific date
        $statusCounts = EmiFollowup::where('agent_id', $agentId)
            ->whereDate('created_at', $date)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();
        
        // Only add statuses with counts > 0
        foreach ($statusCounts as $item) {
            if ($item->total > 0) {
                $label = $configStatuses[$item->status] ?? ucwords(str_replace('_', ' ', $item->status));
                $report[$label] = $item->total;
            }
        }

        return response()->json([

            'success' => true,
            'data' => [
                'id' => $log->id,
                'date' => $log->check_in_at->format('l, d-m-Y'),
                'check_in' => $log->check_in_at->format('h:i A'),
                'check_out' => $log->check_out_at ? $log->check_out_at->format('h:i A') : '--:--',
                'total_hours' => $this->calculateDuration($log->check_in_at, $log->check_out_at),
                'notes' => $log->notes,
                'report' => $report
            ]
        ]);
    }

    private function calculateDuration($start, $end)
    {
        if (!$start || !$end) return '00:00 hrs';
        $duration = $start->diff($end);
        return sprintf('%02d:%02d hrs', $duration->h + ($duration->days * 24), $duration->i);
    }

    private function sendAttendanceNotification($agent, string $type, string $time): void
    {
        try {
            $deviceTokens = $agent->agentDevice()
                ->pluck('device_token')
                ->filter()->unique()->values()->toArray();

            if (empty($deviceTokens)) return;

            $isCheckIn = $type === 'check_in';
            $title   = $isCheckIn ? 'Checked In ✓' : 'Checked Out ✓';
            $message = $isCheckIn
                ? "You have successfully checked in at {$time}."
                : "You have successfully checked out at {$time}.";

            $actionData = ['type' => $type, 'time' => $time];

            $result = $this->pushService->sendPushNotification($deviceTokens, $title, $message, $actionData);
            $anySuccess = $result['success'] || collect($result['results'] ?? [])->contains('ok', true);

            if ($anySuccess) {
                AgentNotification::create([
                    'agent_id'               => $agent->id,
                    'notification_type'      => $type === 'check_in' ? 'check_in_success' : 'check_out_success',
                    'notification_id'        => $type . '_' . $agent->id . '_' . now()->format('YmdHis'),
                    'title'                  => $title,
                    'message'                => $message,
                    'notification_type_label' => 'attendance',
                    'icon'                   => $isCheckIn ? 'login' : 'logout',
                    'priority'               => 'low',
                    'action_data'            => $actionData,
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Attendance push notification failed', [
                'agent_id' => $agent->id,
                'type'     => $type,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function updateProfile(Request $request)
    {
        $agent = Auth::user();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'agent_name' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('agent_name')) {
            $agent->agent_name = $request->agent_name;
        }

        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($agent->profile_image) {
                Storage::disk('public')->delete($agent->profile_image);
            }

            $path = $request->file('profile_image')->store('agent_profiles', 'public');
            $agent->profile_image = $path;
        }

        $agent->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new AgentDashboardResource($agent)
        ]);
    }

    public function index(Request $request)
    {
        $agent = Auth::user();

        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new AgentDashboardResource($agent)
        ]);
    }

    public function profile()
    {
        $agent = Auth::user();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new AgentDashboardResource($agent)
        ]);
    }

    public function highRiskClients()
    {
        $agentId = Auth::user()->id;

        $clients = Client::where('risk_level', 'high')
            ->whereHas('loanAccounts.emis.assignments', function ($q) use ($agentId) {
                $q->where('agent_id', $agentId);
            })
            ->with([
                'location',
                'loanAccounts.emis' => function ($q) use ($agentId) {
                    $q->whereIn('status', ['pending', 'overdue'])
                      ->whereHas('assignments', function ($a) use ($agentId) {
                          $a->where('agent_id', $agentId);
                      });
                }
            ])
            ->get();

        $data = [];

        foreach ($clients as $client) {
            foreach ($client->loanAccounts as $loanAccount) {
                foreach ($loanAccount->emis as $emi) {

                    $data[] = [
                        'client_name' => $client->client_name,
                        'loan_id' => $loanAccount->loan_number,
                        'emi_id' => $emi->id,

                        'visit_time' => '09:30',
                        'visit_type' => 'visit at home',

                        'location' => optional($client->location)->name,
                        'due_amount' => (float) $emi->total_due, // Changed from pending_amount to total_due

                        'status' => strtoupper($client->risk_level),
                        'client_phone' => $client->client_phone,
                    ];
                }
            }
        }

        return response()->json([
            'count' => count($data),
            'data' => $data
        ]);
    }
}
