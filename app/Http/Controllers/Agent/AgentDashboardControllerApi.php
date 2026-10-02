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
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\ChitCollection;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\FixedDeposit;
use App\Models\FixedDepositApplication;
use App\Models\Agent;

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
            $isCheckIn = $type === 'check_in';
            $title   = $isCheckIn ? 'Checked In ✓' : 'Checked Out ✓';
            $message = $isCheckIn
                ? "You have successfully checked in at {$time}."
                : "You have successfully checked out at {$time}.";

            $actionData = ['type' => $type, 'time' => $time];

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

            $deviceTokens = $agent->agentDevice()
                ->pluck('device_token')
                ->filter()->unique()->values()->toArray();

            if (!empty($deviceTokens)) {
                $this->pushService->sendPushNotification($deviceTokens, $title, $message, $actionData);
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
        $agentId = $this->resolveAgentId($agent);

        if (! $agent || ! $agentId) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        $today = Carbon::now()->startOfDay();

        $loanStats = $this->getLoanStats($agentId, $today);
        $chitStats = $this->getChitStats($agentId, $today);
        $fdStats = $this->getFdStats($agentId, $today);

        $todayFollowups = EmiAgentAssignment::with(['emi.loanAccount.client'])
            ->where('agent_id', $agentId)
            ->active()
            ->onActiveLoan()
            ->whereHas('emi', function ($q) {
                $q->whereIn('status', ['pending', 'overdue', 'partial'])
                    ->whereDate('due_date', '=', now());
            })
            ->get()
            ->sortBy(fn ($assignment) => $assignment->emi->due_date)
            ->map(function ($followup) {
                $client = $followup->emi?->loanAccount?->client;
                $loanAccount = $followup->emi?->loanAccount;
                $dueDate = $followup->emi?->due_date ? Carbon::parse($followup->emi->due_date) : null;
                
                $partialService = app(\App\Services\PartialPaymentConfigService::class);
                $amount = 0;
                if ($followup->emi && $loanAccount) {
                    $amount = max(0, (float) $partialService->getOutstandingDueAmount($followup->emi, $loanAccount));
                }
                
                return [
                    'client_name' => $client?->client_name,
                    'client_phone' => $client?->client_phone,
                    'loan_account' => $loanAccount?->account_number ?? $loanAccount?->customer_loan_account_number,
                    'emi_due_date' => $dueDate ? $dueDate->format('d-m-Y') : null,
                    'amount' => round($amount, 2),
                    'status' => $followup->emi?->status,
                ];
            })
            ->filter(fn ($row) => $row['amount'] > 0)
            ->take(3)
            ->values();

        $todayChitInstallments = Installment::with(['member.client', 'group'])
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereDate('due_date', '=', now())
            ->where(function ($q) use ($agentId) {
                $this->applyAgentMemberScope($q, $agentId);
            })
            ->orderBy('due_date')
            ->get()
            ->map(function ($inst) {
                $client = $inst->member?->client;
                $dueDate = $inst->due_date ? Carbon::parse($inst->due_date) : null;
                return [
                    'client_name' => $client?->client_name,
                    'client_phone' => $client?->client_phone,
                    'group' => $inst->group?->group_code,
                    'installment' => 'Inst #' . $inst->month_number,
                    'due_date' => $dueDate ? $dueDate->format('d-m-Y') : null,
                    'balance' => (float) $inst->balance,
                    'status' => $inst->status,
                ];
            })
            ->filter(fn ($row) => $row['balance'] > 0)
            ->take(3)
            ->values();

        $recentClients = Client::where(function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($client) {
                return [
                    'client_name' => $client->client_name,
                    'phone' => $client->client_phone,
                    'status' => $client->status,
                    'added' => $client->created_at ? $client->created_at->format('d-m-Y') : null,
                ];
            });

        $recentChitMembers = GroupMember::with(['client', 'group'])
            ->where(function ($q) use ($agentId) {
                $q->whereHas('client', function ($cq) use ($agentId) {
                    $cq->where('assigned_to', $agentId);
                })
                ->orWhereHas('shares.client', function ($cq) use ($agentId) {
                    $cq->where('assigned_to', $agentId);
                });
            })
            ->whereHas('group', function ($gq) {
                $gq->whereIn('status', ['active', 'completed']);
            })
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($member) {
                $client = $member->client;
        
                return [
                    'client_name' => $client?->client_name,
                    'client_phone' => $client?->client_phone,
                    'group' => $member->group?->group_code,
                    'member_no' => $member->member_number,
                    'status' => $member->status,
                    'joined' => $member->joined_date
                        ? Carbon::parse($member->joined_date)->format('d-m-Y')
                        : ($member->created_at
                            ? $member->created_at->format('d-m-Y')
                            : null),
                ];
            });

        $upcomingFdMaturities = FixedDeposit::with(['client'])
            ->whereHas('client', function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })
            ->where('status', 'active')
            ->whereDate('maturity_date', '>=', now())
            ->whereDate('maturity_date', '<=', now()->addDays(7))
            ->orderBy('maturity_date')
            ->limit(3)
            ->get()
            ->map(function ($fd) {
                $client = $fd->client;
                $maturityDate = $fd->maturity_date ? Carbon::parse($fd->maturity_date) : null;
                return [
                    'client_name' => $client?->client_name,
                    'client_phone' => $client?->client_phone,
                    'fd_number' => $fd->fd_number,
                    'maturity_date' => $maturityDate ? $maturityDate->format('d-m-Y') : null,
                    'maturity_amount' => (float) $fd->maturity_amount,
                    'status' => $fd->status,
                ];
            });

        $recentFdApplications = FixedDepositApplication::with(['client', 'scheme'])
            ->whereHas('client', function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($app) {
                $client = $app->client;
                return [
                    'client_name' => $client?->client_name,
                    'client_phone' => $client?->client_phone,
                    'application_number' => $app->application_number,
                    'scheme_name' => $app->scheme?->name,
                    'deposit_amount' => (float) $app->deposit_amount,
                    'status' => $app->status,
                    'applied_date' => $app->applied_at ? Carbon::parse($app->applied_at)->format('d-m-Y') : null,
                ];
            });

        $dailyPerformance = $this->getPerformanceStats($agentId, Carbon::now()->startOfDay(), Carbon::now()->endOfDay());
        $weeklyPerformance = $this->getPerformanceStats($agentId, Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
        $monthlyPerformance = $this->getPerformanceStats($agentId, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
        $overduePerformance = $this->getOverduePerformanceStats($agentId);

        return response()->json([
            'success' => true,
            'data' => [
                'collected_today' => $dailyPerformance['collected_amount'],
                'collected_this_month' => $monthlyPerformance['collected_amount'],
                'performance' => [
                    'daily' => $dailyPerformance,
                    'weekly' => $weeklyPerformance,
                    'monthly' => $monthlyPerformance,
                ],
                'overdue_performance' => $overduePerformance,
                'loans' => [
                    'stats' => $loanStats,
                    'todayFollowups' => $todayFollowups,
                    'recentClients' => $recentClients,
                ],
                'chits' => [
                    'stats' => $chitStats,
                    'todayChitInstallments' => $todayChitInstallments,
                    'recentChitMembers' => $recentChitMembers,
                ],
                'fixed_deposits' => [
                    'stats' => $fdStats,
                    'upcomingMaturities' => $upcomingFdMaturities,
                    'recentApplications' => $recentFdApplications,
                ]
            ]
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

    public function bankInfo()
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
            'data' => [
                'account_holder_name' => $agent->account_holder_name,
                'bank_name' => $agent->bank_name,
                'account_number' => $agent->account_number,
                'ifsc_code' => $agent->ifsc_code,
                'branch_name' => $agent->branch_name,
                'upi_id' => $agent->upi_id,
            ]
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

    private function getLoanStats(int $agentId, Carbon $today): array
    {
        $todayCollected = $this->sumAgentCollections($agentId, $today->copy()->startOfDay(), $today->copy()->endOfDay());
        $monthCollected = $this->sumAgentCollections($agentId, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        return [
            'total_clients' => Client::where(function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })->count(),
            'active_loans' => LoanAccount::whereHas('client', function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
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
                    $q->where('assigned_to', $agentId);
                })
                ->overdue()
                ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")')
                ->count(),
            'collected_today' => $todayCollected['loan_amount'],
            'collected_this_month' => $monthCollected['loan_amount'],
        ];
    }

    private function getChitStats(int $agentId, Carbon $today): array
    {
        $memberScope = function ($q) use ($agentId) {
            $q->whereHas('client', function ($cq) use ($agentId) {
                $cq->where('assigned_to', $agentId);
            })
            ->orWhereHas('shares.client', function ($cq) use ($agentId) {
                $cq->where('assigned_to', $agentId);
            });
        };
        
        $activeMembers = GroupMember::where($memberScope)
            ->whereIn('status', ['active', 'approved'])
            ->whereHas('group', fn ($gq) => $gq->where('status', 'active'))
            ->count();
        
        $activeGroups = GroupMember::where($memberScope)
            ->whereIn('status', ['active', 'approved'])
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

        $todayCollected = $this->sumAgentCollections($agentId, $today->copy()->startOfDay(), $today->copy()->endOfDay());
        $monthCollected = $this->sumAgentCollections($agentId, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        return [
            'active_members' => $activeMembers,
            'active_groups' => $activeGroups,
            'today_installments' => $todayInstallments,
            'overdue_installments' => $overdueInstallments,
            'collected_today' => $todayCollected['chit_amount'],
            'collected_this_month' => $monthCollected['chit_amount'],
        ];
    }

    private function getFdStats(int $agentId, Carbon $today): array
    {
        return [
            'active_deposits' => FixedDeposit::whereHas('client', function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })->where('status', 'active')->count(),
            
            'total_applications' => FixedDepositApplication::whereHas('client', function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })->count(),

            'pending_applications' => FixedDepositApplication::whereHas('client', function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })->where('status', 'pending')->count(),
            
            'upcoming_maturities' => FixedDeposit::whereHas('client', function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })->where('status', 'active')
              ->whereDate('maturity_date', '>=', $today)
              ->whereDate('maturity_date', '<=', $today->copy()->addDays(7))
              ->count(),
        ];
    }

    private function resolveAgentId($user): ?int
    {
        if ($user instanceof Agent) {
            return (int) $user->id;
        }

        $agentId = optional(optional($user)->agent)->id;

        return $agentId ? (int) $agentId : null;
    }

    /**
     * Sum loan + chit collections recorded by this agent in the period.
     * Counts cash/direct collections as soon as they are submitted (in_progress),
     * plus verified/completed. Excludes rejected and unpaid payment links.
     */
    private function sumAgentCollections(int $agentId, Carbon $start, Carbon $end): array
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->endOfDay();

        $applyScope = function ($query) use ($agentId, $from, $to) {
            return $query->where('agent_id', $agentId)
                ->whereBetween('collected_at', [$from, $to])
                ->whereIn('status', ['in_progress', 'verified', 'completed'])
                ->where(function ($q) {
                    $q->where('status', '!=', 'in_progress')
                        ->orWhere('payment_method', '!=', 'payment_link');
                });
        };

        $emiQuery = $applyScope(EmiCollection::query());
        $chitQuery = $applyScope(ChitCollection::query());

        $loanAmount = (float) (clone $emiQuery)->sum('amount');
        $chitAmount = (float) (clone $chitQuery)->sum('amount');
        $loanCount = (int) (clone $emiQuery)->count();
        $chitCount = (int) (clone $chitQuery)->count();

        return [
            'loan_count' => $loanCount,
            'chit_count' => $chitCount,
            'collected_count' => $loanCount + $chitCount,
            'loan_amount' => round($loanAmount, 2),
            'chit_amount' => round($chitAmount, 2),
            'collection_amount' => round($loanAmount + $chitAmount, 2),
        ];
    }

    private function getPerformanceStats(int $agentId, Carbon $start, Carbon $end): array
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->endOfDay();

        $collected = $this->sumAgentCollections($agentId, $from, $to);

        $pendingLoanEmis = Emi::where(function ($q) use ($agentId) {
                $q->whereHas('loanAccount.client', function ($cq) use ($agentId) {
                    $cq->where('assigned_to', $agentId);
                })
                ->orWhereHas('assignments', function ($aq) use ($agentId) {
                    $aq->where('agent_id', $agentId)->where('status', 'active');
                });
            })
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereBetween('due_date', [$from, $to])
            ->get();

        $pendingLoanCount = $pendingLoanEmis->count();
        $pendingLoanAmount = (float) $pendingLoanEmis->sum(function ($emi) {
            $inProgress = (float) $emi->collections()->where('status', 'in_progress')->sum('amount');
            return max(0, (float) $emi->pending_amount - $inProgress);
        });

        $pendingChitInstallments = Installment::whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereBetween('due_date', [$from, $to])
            ->where(function ($q) use ($agentId) {
                $this->applyAgentMemberScope($q, $agentId);
            })
            ->whereHas('group', fn ($gq) => $gq->whereIn('status', ['active', 'completed']))
            ->get();

        $pendingChitCount = $pendingChitInstallments->count();
        $pendingChitAmount = (float) $pendingChitInstallments->sum(function ($inst) {
            $inProgress = (float) $inst->collections()->where('status', 'in_progress')->sum('amount');
            $bal = (float) ($inst->amount + $inst->penalty_amount - $inst->paid_amount);
            return max(0, $bal - $inProgress);
        });

        $collectedCount = (int) $collected['collected_count'];
        $collectedAmount = (float) $collected['collection_amount'];

        $pendingCount = $pendingLoanCount + $pendingChitCount;
        $pendingAmount = round($pendingLoanAmount + $pendingChitAmount, 2);

        $collectableCount = $collectedCount + $pendingCount;
        $collectableAmount = round($collectedAmount + $pendingAmount, 2);

        // Client collection and pending overlap calculation
        $collectedLoanClients = EmiCollection::where('agent_id', $agentId)
            ->whereBetween('collected_at', [$from, $to])
            ->whereIn('status', ['in_progress', 'verified', 'completed'])
            ->whereHas('emi.loanAccount', function ($q) {
                $q->whereNotNull('client_id');
            })
            ->with('emi.loanAccount')
            ->get()
            ->pluck('emi.loanAccount.client_id')
            ->filter();

        $collectedChitClients = ChitCollection::where('agent_id', $agentId)
            ->whereBetween('collected_at', [$from, $to])
            ->whereIn('status', ['in_progress', 'verified', 'completed'])
            ->pluck('client_id')
            ->filter();

        $pendingLoanClients = $pendingLoanEmis->pluck('loanAccount.client_id')->filter();

        $pendingChitClients = $pendingChitInstallments->map(function ($inst) {
            return $inst->member?->client_id;
        })->filter();

        $totalCollectableClientCount = $collectedLoanClients
            ->merge($collectedChitClients)
            ->merge($pendingLoanClients)
            ->merge($pendingChitClients)
            ->unique()
            ->count();

        $progress = $collectableCount > 0 ? round(($collectedCount / $collectableCount) * 100, 2) : 0;

        return [
            'total_collectable_client_count' => $totalCollectableClientCount,
            'collectable_count' => $collectableCount,
            'collected_count' => $collectedCount,
            'collection_pending_count' => $pendingCount,
            'collectable_amount' => $collectableAmount,
            'collected_amount' => $collectedAmount,
            'collection_amount' => $collectedAmount,
            'collection_pending_amount' => $pendingAmount,
            'collection_progress' => $progress,
            'progress_ratio' => $progress,
        ];
    }

    private function getOverduePerformanceStats(int $agentId): array
    {
        $today = Carbon::now()->startOfDay();

        // 1. Current Active Pending Overdue Loan EMIs
        $pendingOverdueEmis = Emi::where(function ($q) use ($agentId) {
                $q->whereHas('loanAccount.client', function ($cq) use ($agentId) {
                    $cq->where('assigned_to', $agentId);
                })
                ->orWhereHas('assignments', function ($aq) use ($agentId) {
                    $aq->where('agent_id', $agentId)->where('status', 'active');
                });
            })
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereDate('due_date', '<', $today)
            ->get();

        $overduePendingLoanCount = $pendingOverdueEmis->count();
        $overduePendingLoanAmount = (float) $pendingOverdueEmis->sum(function ($emi) {
            $inProgress = (float) $emi->collections()->where('status', 'in_progress')->sum('amount');
            return max(0, (float) $emi->pending_amount - $inProgress);
        });

        // 2. Current Active Pending Overdue Chit Installments
        $pendingOverdueInstallments = Installment::whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereDate('due_date', '<', $today)
            ->where(function ($q) use ($agentId) {
                $this->applyAgentMemberScope($q, $agentId);
            })
            ->whereHas('group', fn ($gq) => $gq->whereIn('status', ['active', 'completed']))
            ->get();

        $overduePendingChitCount = $pendingOverdueInstallments->count();
        $overduePendingChitAmount = (float) $pendingOverdueInstallments->sum(function ($inst) {
            $inProgress = (float) $inst->collections()->where('status', 'in_progress')->sum('amount');
            $bal = (float) ($inst->amount + $inst->penalty_amount - $inst->paid_amount);
            return max(0, $bal - $inProgress);
        });

        $overduePendingCount = $overduePendingLoanCount + $overduePendingChitCount;
        $overduePendingRemaining = round($overduePendingLoanAmount + $overduePendingChitAmount, 2);

        // 3. Overdue Collections made by this Agent
        $loanOverdueCollections = EmiCollection::where('agent_id', $agentId)
            ->whereIn('status', ['in_progress', 'verified', 'completed'])
            ->where(function ($q) {
                $q->where('payment_type', 'overdue')
                  ->orWhereHas('emi', function ($eq) {
                      $eq->whereDate('due_date', '<', DB::raw('emi_collections.collected_at'))
                        ->orWhere('status', 'overdue');
                  });
            })
            ->get();

        $overdueRecoveredLoanTotal = (float) $loanOverdueCollections->sum('amount');
        $overdueClearedLoanCount = $loanOverdueCollections->pluck('emi_id')->unique()->count();

        $chitOverdueCollections = ChitCollection::where('agent_id', $agentId)
            ->whereIn('status', ['in_progress', 'verified', 'completed'])
            ->where(function ($q) {
                $q->where('payment_type', 'overdue')
                  ->orWhereHas('installment', function ($iq) {
                      $iq->whereDate('due_date', '<', DB::raw('chit_collections.collected_at'))
                        ->orWhere('status', 'overdue');
                  });
            })
            ->get();

        $overdueRecoveredChitTotal = (float) $chitOverdueCollections->sum('amount');
        $overdueClearedChitCount = $chitOverdueCollections->pluck('installment_id')->unique()->count();

        $overdueRecoveredTotal = round($overdueRecoveredLoanTotal + $overdueRecoveredChitTotal, 2);
        $overdueClearedCount = $overdueClearedLoanCount + $overdueClearedChitCount;

        $totalOverduePool = round($overdueRecoveredTotal + $overduePendingRemaining, 2);
        $totalOverdueItemsCount = $overdueClearedCount + $overduePendingCount;
        $overdueRecoveryPercentage = $totalOverduePool > 0 ? round(($overdueRecoveredTotal / $totalOverduePool) * 100, 2) : 0;

        return [
            'total_overdue_pool' => $totalOverduePool,
            'overdue_recovered_total' => $overdueRecoveredTotal,
            'overdue_pending_remaining' => $overduePendingRemaining,
            'overdue_recovery_percentage' => $overdueRecoveryPercentage,
            'total_overdue_items_count' => $totalOverdueItemsCount,
            'overdue_cleared_count' => $overdueClearedCount,
            'overdue_pending_count' => $overduePendingCount,
        ];
    }

    private function applyAgentMemberScope($query, int $agentId): void
    {
        $query->whereHas('member', function ($mq) use ($agentId) {
            $mq->where(function ($memberQ) use ($agentId) {
                $memberQ->whereHas('client', function ($cq) use ($agentId) {
                    $cq->where('assigned_to', $agentId);
                })
                ->orWhereHas('shares.client', function ($cq) use ($agentId) {
                    $cq->where('assigned_to', $agentId);
                });
            });
        });
    }
}
