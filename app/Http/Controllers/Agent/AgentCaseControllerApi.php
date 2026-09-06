<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmiAgentAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\AgentActivity;
use App\Models\Emi;
use App\Models\EmiCollection;

class AgentCaseControllerApi extends Controller
{
    /**
     * All Cases API - Shows all unresolved cases with time badges
     * Sorted by oldest first (2 days ago -> 1 day ago -> Just now)
     */
    public function index(Request $request)
    {
        $agent = Auth::user();
        
        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }
        
        $type = $request->query('type', 'all');
        $today = now()->toDateString();

        $query = EmiAgentAssignment::where('agent_id', $agent->id)
            ->active()
            ->onActiveLoan()
            ->whereHas('emi', function($q) {
                $q->where('status', '!=', 'paid')
                  ->whereDoesntHave('collections', function ($sq) {
                      $sq->whereIn('status', ['verified', 'in_progress']);
                  });
            })
            ->with([
                'emi.loanAccount.client',
                'emi.loanAccount.emis'
            ]);

        // Filter based on type
        if ($type === 'unresolved') {
            // Unresolved: Show only yesterday and previous cases (not today)
            $query->whereDate('assigned_at', '<', $today);
        } elseif ($type === 'all') {
            // All Cases: Show everything including today
            // No additional filter needed
        } elseif ($type === 'overdue') {
            $query->whereHas('emi', fn($q) => $q->where('status', 'overdue'));
        } elseif ($type === 'high_risk') {
            $query->whereHas('emi', function ($q) {
                $q->where('pending_amount', '>', 0)
                  ->whereDate('due_date', '<', now()->subDays(15));
            });
        } else {
            return response()->json(['message' => 'Invalid type'], 422);
        }

        $assignments = $query
            ->orderBy('assigned_at', 'asc') // Oldest first (2 days ago -> 1 day ago -> today)
            ->get();

        // Group by loan account and format the response
        $groupedCases = $assignments
            ->groupBy('emi.loan_account_id')
            ->map(function ($assignments) {
                return $this->formatCaseResponse($assignments, true); // true = include time badge
            })
            ->values();

        return response()->json([
            'success' => true,
            'type' => $type,
            'count' => $groupedCases->count(),
            'cases' => $groupedCases,
        ]);
    }

    /**
     * Today Cases API - Shows only today's assignments with 'Just now' badge
     */
    public function todayCases(Request $request)
    {
        $agent = Auth::user();
        
        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        $today = now()->toDateString();
        
        $assignments = EmiAgentAssignment::where('agent_id', $agent->id)
            ->whereDate('assigned_at', $today)
            ->whereIn('status', ['assigned', 'visited'])
            ->whereHas('emi', function($q) use ($today) {
                // Exclude paid EMIs
                $q->where('status', '!=', 'paid');
                
                // Exclude EMIs that have been actioned today (Followups)
                $q->whereDoesntHave('followups', function($sq) use ($today) {
                    $sq->whereDate('created_at', $today);
                });
                
                // Exclude EMIs that have been actioned today (Visits)
                $q->whereDoesntHave('visitLogs', function($sq) use ($today) {
                    $sq->whereDate('created_at', $today);
                });
                
                // Exclude EMIs that have been actioned today (Collections)
                $q->whereDoesntHave('collections', function($sq) use ($today) {
                    $sq->whereDate('created_at', $today);
                });
            })
            ->with([
                'emi.loanAccount.client',
                'emi.loanAccount.emis'
            ])
            ->orderByDesc('assigned_at')
            ->get();

        $groupedCases = $assignments
            ->groupBy('emi.loan_account_id')
            ->map(function ($assignments) {
                return $this->formatCaseResponse($assignments, false); // false = no time badge calculation (always 'Just now')
            })
            ->values();

        return response()->json([
            'success' => true,
            'count' => $groupedCases->count(),
            'cases' => $groupedCases,
        ]);
    }

    /**
     * Format case response with optional time badge
     * @param $assignments - Collection of assignments for the same loan account
     * @param $includeTimeBadge - Whether to include time badge (for All Cases)
     */
    private function formatCaseResponse($assignments, $includeTimeBadge = false)
    {
        $firstAssignment = $assignments->first();
        $emi = $firstAssignment->emi;
        $loanAccount = $emi->loanAccount;
        $client = $loanAccount->client;
        
        // Calculate total EMI amount for this loan (sum of all assigned EMIs) = Total Due (Previous + Current)
        $totalEmiAmount = $assignments->sum(function($assignment) {
            return $assignment->emi->total_due;
        });

        // Calculate Overdue EMIs count specifically for risk logic
        $overdueEmisCount = $assignments->filter(function($assignment) {
            return $assignment->emi->status === 'overdue' || $assignment->emi->pending_amount > 0; // consistent check
        })->count();
        
        // Determine Badge Status
        // Rule: High Risk if (2+ EMIs overdue) OR (1 overdue EMI with DPD > 15)
        $badgeStatus = 'Overdue';
        $maxDpd = $assignments->max(fn($a) => $a->emi->dpd_days);

        if ($overdueEmisCount >= 2 || $maxDpd > 15) {
            $badgeStatus = 'High Risk';
        }
        
        // Calculate paid EMIs vs total EMIs
        $totalEmis = $loanAccount->emis->count();
        $paidEmis = $loanAccount->emis->where('status', 'paid')->count();
        
        // Calculate time badge if needed
        $timeBadge = 'Just now';
        $daysAgo = 0;
        if ($includeTimeBadge) {
            $assignedDate = $firstAssignment->assigned_at;
            // Use absolute value and round to get whole days
            $daysAgo = abs((int) now()->diffInDays($assignedDate, false));
            
            if ($daysAgo == 0) {
                $timeBadge = 'Just now';
            } elseif ($daysAgo == 1) {
                $timeBadge = '1 day ago';
            } else {
                $timeBadge = $daysAgo . ' days ago';
            }
        }
        
        return [
            'emi_id' => $emi->id,
            'loan_account_id' => $loanAccount->id,
            'customer_name' => $client->client_name,
            'customer_phone' => $client->client_phone,
            'customer_profile_image' => $client->profile_image_url,
            'loan_account_number' => $loanAccount->account_number,
            'emi_status' => $badgeStatus, // Using calculated badge status
            'emi_paid_info' => $paidEmis . '/' . $totalEmis,
            'due_date' => $emi->due_date ? $emi->due_date->format('d-m-Y') : null,
            'dpd' => $emi->dpd_days, // showing DPD of the primary (first) EMI in the group
            'call_status' => 'call',
            'total_emi_amount' => (float) $totalEmiAmount,
            'time_badge' => $timeBadge,
            'days_ago' => $daysAgo,
        ];
    }

    /**
     * Update call status for a loan account
     */
    public function updateCallStatus(Request $request)
    {
        $agent = Auth::user();
        
        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent data not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'call_status' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $loanAccountId = $request->loan_account_id;
        $callStatus = $request->call_status;

        // Get all EMIs for this loan account that are assigned to the agent
        $assignments = EmiAgentAssignment::where('agent_id', $agent->id)
            ->whereHas('emi', function ($q) use ($loanAccountId) {
                $q->where('loan_account_id', $loanAccountId);
            })
            ->with('emi')
            ->get();

        if ($assignments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this loan account'
            ], 403);
        }

        // Store the call status for each EMI in the loan account
        $activitiesCreated = 0;
        foreach ($assignments as $assignment) {
            AgentActivity::create([
                'agent_id' => $agent->id,
                'emi_id' => $assignment->emi_id,
                'type' => 'call',
                'description' => 'Call status updated to: ' . $callStatus,
                'action_at' => now(),
            ]);
            $activitiesCreated++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Call status updated successfully',
            'data' => [
                'loan_account_id' => $loanAccountId,
                'call_status' => $callStatus,
                'emis_updated' => $activitiesCreated,
                'updated_at' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }



    public function riskCases(Request $request)
    {
        $agentId = Auth::user()->id;
        // User rule: At Risk/High Risk if (2+ EMIs Overdue) OR (Any EMI DPD > 15)
        
        // Fetch all active assignments with necessary relationships
        $assignments = EmiAgentAssignment::where('agent_id', $agentId)
            ->whereHas('emi', fn ($q) =>
                $q->where('pending_amount', '>', 0)
            )
            ->with([
                'emi.loanAccount.client.location',
                'emi.loanAccount.emis', // Need all EMIs to check count
                'emi.followups' => fn ($q) => $q->where('agent_id', $agentId)->latest()
            ])
            ->orderByDesc('assigned_at')
            ->get();

        // Filter based on risk logic
        $riskCases = $assignments->filter(function ($assignment) {
            $emi = $assignment->emi;
            $loanAccount = $emi->loanAccount;
            
            // Check 1: Any EMI in this loan DPD > 15
            $maxDpd = $loanAccount->emis->max('dpd_days');
            if ($maxDpd > 15) {
                return true;
            }

            // Check 2: 2+ EMIs Overdue
            $overdueCount = $loanAccount->emis->where('status', 'overdue')->count();
            if ($overdueCount >= 2) {
                return true;
            }

            return false;
        });

        // Group by loan_account_id to show one case per client
        $groupedCases = $riskCases->groupBy(function ($assignment) {
            return $assignment->emi->loan_account_id;
        })->map(function ($group) {
            // Use the first assignment as the base, but aggregate EMI data
            return $this->formatRiskCaseGrouped($group);
        });

        return response()->json([
            'count' => $groupedCases->count(),
            'cases' => $groupedCases->values(),
        ]);
    }

    /**
     * Recovered Screen
     */
    public function recoveredCases(Request $request)
    {
        $agentId = Auth::user()->id;
        $period = $request->get('period', 'all'); // 'all' or 'month'

        $query = EmiAgentAssignment::where('agent_id', $agentId)
            ->whereHas('emi', fn ($q) =>
                $q->where('pending_amount', '<=', 0) // Basic Check: Is it paid?
            )
            ->with([
                'emi.loanAccount.client.location',
                'emi.loanAccount.loanApplication.product.loanType', // Add loanType
                'emi.collections' => fn ($q) =>
                    $q->where('agent_id', $agentId)->latest()
            ]);

        if ($period === 'month') {
            $query->whereHas('emi', fn ($q) =>
                $q->where(function ($sub) {
                    $sub->whereMonth('paid_date', now()->month)
                        ->whereYear('paid_date', now()->year);
                })->orWhere(function ($sub) {
                    // Fallback for cases where paid_date might be missing but is paid (using updated_at)
                    $sub->whereNull('paid_date')
                        ->whereMonth('updated_at', now()->month)
                        ->whereYear('updated_at', now()->year);
                })
            );
        }

        $assignments = $query->orderByDesc('updated_at')->get();

        // Group by loan_account_id to show one case per client
        $groupedCases = $assignments->groupBy(function ($assignment) {
            return $assignment->emi->loan_account_id;
        })->map(function ($group) {
            return $this->formatRecoveredCaseGrouped($group);
        });

        return response()->json([
            'count' => $groupedCases->count(),
            'period' => $period,
            'cases' => $groupedCases->values(),
        ]);
    }



    // ... I will skip this implementation here and use the tool call.


    /**
     * Agent Collections Summary Screen
     */
    public function agentCollections(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        // Get all assignments for the agent to calculate summaries
        $assignments = EmiAgentAssignment::where('agent_id', $agentId)
            ->with(['emi.loanAccount.client.location'])
            ->get();

        $todayTotalDue = 0;
        $todayCollected = 0;
        $todayPending = 0;

        $monthTotalDue = 0;
        $monthCollected = 0;
        $monthPending = 0;

        $todayCases = [];

        foreach ($assignments as $row) {
            $emi = $row->emi;
            if (!$emi) continue;

            $dueDate = optional($emi->due_date)->toDateString();

            // Monthly stats (Target)
            if ($dueDate >= $startOfMonth && $dueDate <= $endOfMonth) {
                // Use total_due instead of total_amount
                $monthTotalDue += (float) $emi->total_due;
                // $monthCollected comes from actuals now
                $monthPending += (float) $emi->pending_amount;
            }

            // Today stats (Target)
            if ($dueDate === $today) {
                // Use total_due instead of total_amount
                $todayTotalDue += (float) $emi->total_due;
                // $todayCollected comes from actuals now
                $todayPending += (float) $emi->pending_amount;

                // Only add to cases list if pending_amount > 0
                if ($emi->pending_amount > 0) {
                    $client = $emi->loanAccount->client;
                    $todayCases[] = [
                        'emi_id'        => $emi->id,
                        'client_name'   => $client->client_name,
                        'loan_number'   => $emi->loanAccount->account_number,
                        'total_amount'  => (float) $emi->total_due, // Changed to total_due
                        'paid_amount'   => (float) $emi->paid_amount,
                        'pending_amount'=> (float) $emi->pending_amount,
                        'due_date'      => optional($emi->due_date)->format('d-m-Y'),
                        'location'      => optional($client->location)->name,
                        'risk_level'    => $emi->risk_level,
                        'dpd_days'      => $emi->dpd_days,
                    ];
                }
            }
        }

        // Calculate ACTUAL COLLECTIONS (regardless of due date)
        $todayCollected = EmiCollection::where('agent_id', $agentId)
            ->whereDate('collected_at', $today)
            ->sum('amount');

        $monthCollected = EmiCollection::where('agent_id', $agentId)
            ->whereBetween('collected_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $todaySummary = [
            'total_due' => $todayTotalDue,
            'collected' => (float) $todayCollected,
            'pending'   => $todayPending,
            'collected_percentage' => $todayTotalDue > 0 ? round(($todayCollected / $todayTotalDue) * 100, 2) : 0,
        ];

        $monthSummary = [
            'total_due'            => $monthTotalDue,
            'collected'            => (float) $monthCollected,
            'pending'              => $monthPending,
            'pending_percentage'   => $monthTotalDue > 0 ? round(($monthPending / $monthTotalDue) * 100, 2) : 0,
        ];

        return response()->json([
            'count_today'   => count($todayCases),
            'today_summary' => $todaySummary,
            'month_summary' => $monthSummary,
            'cases_today'   => $todayCases,
        ]);
    }

    /**
     * All Pending Collections List API for Agent
     */
    public function pendingCollections(Request $request)
    {
        $agentId = Auth::user()->id;

        $pendingCases = EmiAgentAssignment::with(['emi.loanAccount.client.location'])
            ->where('agent_id', $agentId)
            ->active()
            ->onActiveLoan()
            ->whereHas('emi', function ($q) {
                $q->where('pending_amount', '>', 0);
            })
            ->get();

        $data = $pendingCases->map(function ($row) {
            $emi = $row->emi;
            $client = $emi->loanAccount->client;

            return [
                'emi_id'         => $emi->id,
                'client_name'    => $client->client_name,
                'loan_number'    => $emi->loanAccount->account_number,
                'total_amount'   => (float) $emi->total_due, // Changed to total_due
                'paid_amount'    => (float) $emi->paid_amount,
                'pending_amount' => (float) $emi->pending_amount,
                'due_date'       => optional($emi->due_date)->format('d-m-Y'),
                'location'       => optional($client->location)->name,
                'risk_level'     => $emi->risk_level,
                'dpd_days'       => $emi->dpd_days,
                'status'         => $emi->status,
                'assigned_status'=> $row->status,
            ];
        });

        return response()->json([
            'count' => $data->count(),
            'total_pending_amount' => $data->sum('pending_amount'),
            'cases' => $data,
        ]);
    }

    /**
     * Today Collected List API
     */
    public function todayCollectedList(Request $request)
    {
        $agentId = Auth::user()->id;
        $today = now()->toDateString();

        $collections = EmiCollection::where('agent_id', $agentId)
            ->whereDate('collected_at', $today)
            ->with(['emi.loanAccount.client.location'])
            ->latest('collected_at')
            ->get();

        $data = $collections->map(function ($c) {
            $emi = $c->emi;
            $client = $emi?->loanAccount?->client;

            return [
                'collection_id'   => $c->id,
                'emi_id'          => $c->emi_id,
                'client_name'     => $client?->client_name,
                'loan_number'     => $emi?->loanAccount?->account_number,
                'amount'          => (float) $c->amount,
                'payment_method'  => ucfirst(str_replace('_', ' ', $c->payment_method)),
                'collected_at'    => optional($c->collected_at)->format('d-m-Y h:i A'),
                'remarks'         => $c->remarks,
            ];
        });

        return response()->json([
            'count'        => $data->count(),
            'total_amount' => $data->sum('amount'),
            'collections'  => $data,
        ]);
    }

    // Controller Method
    public function recoveredCaseDetail(Request $request, $emiId)
    {
        $agentId = Auth::user()->id;

        $case = EmiAgentAssignment::where('agent_id', $agentId)
            ->whereHas('emi', fn ($q) =>
                $q->where('id', $emiId)
                  ->where('pending_amount', '<=', 0)
                  ->whereNotNull('paid_date')
            )
            ->with([
                'emi.loanAccount.loanApplication.product.loanType',
                'emi.loanAccount.client.user.userDevice',
                'emi.loanAccount.client.location',
                'emi.collections' => fn ($q) =>
                    $q->where('agent_id', $agentId)->latest()
            ])
            ->first();

        if (!$case) {
            return response()->json([
                'message' => 'Recovered EMI not found or not assigned to this agent.'
            ], 404);
        }

        $emi = $case->emi;
        $loanAccount = $emi->loanAccount;
        $client = $loanAccount->client;
        $user = $client->user;
        $device = $user ? $user->userDevice()->latest()->first() : null;
        $collection = $emi->collections->first();

        // Get all EMIs for this client assigned to this agent (logic from customerDetails)
        $agentEmis = Emi::whereHas('assignments', function ($q) use ($agentId) {
                $q->where('agent_id', $agentId);
            })
            ->where('loan_account_id', $loanAccount->id)
            ->orderBy('due_date')
            ->get();

        $paymentHistory = $agentEmis->map(function ($e) {
            return [
                'emi_id'       => $e->id,
                'amount'       => (float) $e->total_due, // Changed to total_due
                'due_date'     => $e->due_date->format('d-m-Y'),
                'status'       => $e->total_paid_amount >= $e->total_due ? 'Paid' : 'Pending',
                'paid_amount'  => (float) $e->total_paid_amount,
                'payment_method' => $e->collections->first()?->payment_method ? ucfirst($e->collections->first()->payment_method) : null,
            ];
        });

        $data = [
            'emi_id' => $emi->id,
            'client_name' => $client->client_name,
            'client_email' => $client->client_email,
            'client_phone' => $client->client_phone,
            'loan_number' => $emi->loanAccount->account_number,
            'total_amount' => (float) $emi->total_due, // Changed to total_due
            'location' => optional($client->location)->name,
            'recovered_on' => optional($emi->paid_date)->format('d-m-Y'),
            'recovery_note' => sprintf(
                '%d days overdue, %s',
                $emi->dpd_days,
                $collection
                    ? ucfirst(str_replace('_', ' ', $collection->payment_method))
                    : 'Unknown'
            ),
            'status' => 'Recovered',

            // Added fields from customerDetails
            'customer' => [
                'id'        => $client->id,
                'name'      => $client->client_name,
                'longitude' => $device?->longitude,
                'latitude'  => $device?->latitude,
            ],

            'loan' => [
                'loan_type'       => optional(optional(optional($loanAccount->loanApplication)->product)->loanType)->name,
                'interest_rate'   => $loanAccount->interest_rate . '% p.a',
                'tenure'          => $loanAccount->tenure . ' months',
                'disbursed_on'    => optional($loanAccount->disbursed_at)->format('d-m-Y'),
                'total_amount'    => $loanAccount->loan_amount,
                'total_emi_paid'  => $emi->total_paid_amount,
                'overdue_amount'  => $emi->overdue_amount,
                'due_date'        => optional($emi->due_date)->format('d-m-Y'),
            ],

            'contact' => [
                'phone' => $client->client_phone,
                'email' => $client->client_email,
            ],

            'address' => [
                'full_address'  => $client->address,
                'location_name' => optional($client->location)->name,
            ],

            'last_actions' => AgentActivity::where('emi_id', $emiId)
                ->where('agent_id', $agentId)
                ->latest('action_at')
                ->limit(5)
                ->get()
                ->map(fn ($a) => [
                    'type' => $a->type,
                    'description' => $a->description,
                    'date' => optional($a->action_at)->format('d-m-Y h:i A'),
                ]),

            'notes' => AgentActivity::where('emi_id', $emiId)
                ->where('agent_id', $agentId)
                ->where('type', 'note')
                ->latest()
                ->value('description'),

            'payment_history' => $paymentHistory,
        ];

        return response()->json(['case' => $data]);
    }

    /**
     * Format Risk Case (High / At)
     */
    private function formatRiskCase($row)
    {
        $emi = $row->emi;
        $client = $emi->loanAccount->client;
        $followup = $emi->followups->first();

        $dpd = $emi->due_date->isPast()
            ? now()->diffInDays($emi->due_date)
            : 0;

        return [
            'emi_id' => $emi->id,
            'client_name' => $client->client_name,
            'profile_image_url' => $client->profile_image_url,
            'loan_number' => $emi->loanAccount->account_number,
            'due_amount' => (float) $emi->pending_amount,
            'due_date' => $emi->due_date->format('d-m-Y'),
            'dpd_days' => $emi->dpd_days,
            'location' => optional($client->location)->name,

            'risk_badge' => $emi->risk_level === 'high' ? 'High Risk' : 'At Risk',

            'visit_label' => $followup
                ? ucfirst($followup->type) . ' ' . optional($followup->followup_at)->format('H:i')
                : 'Pending Visit',
        ];
    }

    /**
     * Format Risk Case Grouped (multiple EMIs for same client)
     */
    private function formatRiskCaseGrouped($group)
    {
        // Get first assignment as base
        $firstAssignment = $group->first();
        $client = $firstAssignment->emi->loanAccount->client;
        $loanAccount = $firstAssignment->emi->loanAccount;
        
        // Get all EMIs for this group
        $emis = $group->pluck('emi');
        
        // Calculate total due amount
        $totalDueAmount = $emis->sum('pending_amount');
        
        // Get earliest due date
        $earliestDueDate = $emis->min('due_date');
        
        // Get max DPD
        $maxDpd = $emis->max('dpd_days');
        
        // Determine risk level (high if any EMI is high risk)
        $hasHighRisk = $emis->contains(fn($emi) => $emi->risk_level === 'high');
        
        // Get latest followup
        $followup = $emis->flatMap(fn($emi) => $emi->followups)->sortByDesc('created_at')->first();
        
        return [
            'loan_account_id' => $loanAccount->id,
            'client_name' => $client->client_name,
            'client_phone' => $client->client_phone,
            'profile_image_url' => $client->profile_image_url,
            'loan_number' => $loanAccount->account_number,
            'due_amount' => (float) $totalDueAmount,
            'due_date' => $earliestDueDate ? $earliestDueDate->format('d-m-Y') : null,
            'dpd_days' => $maxDpd,
            'location' => optional($client->location)->name,
            'emi_count' => $emis->count(),
            'emi_ids' => $emis->pluck('id')->toArray(),
            'risk_badge' => $hasHighRisk ? 'High Risk' : 'At Risk',
            'visit_label' => $followup
                ? ucfirst($followup->type) . ' ' . optional($followup->followup_at)->format('H:i')
                : 'Pending Visit',
        ];
    }

    /**
     * Format Recovered Case
     */
    private function formatRecoveredCase($row)
    {
        $emi = $row->emi;
        $client = $emi->loanAccount->client;
        $collection = $emi->collections->first();

        return [
            'emi_id' => $emi->id,
            'client_name' => $client->client_name,
            'loan_number' => $emi->loanAccount->account_number,

            'total_amount' => (float) $emi->total_due, // Changed to total_due

            'recovered_on' => optional($emi->paid_date)->format('d-m-Y'),

            'recovery_note' => sprintf(
                '%d days overdue, %s',
                $emi->dpd_days,
                $collection
                    ? ucfirst(str_replace('_', ' ', $collection->payment_method))
                    : 'Unknown'
            ),

            'status' => 'Recovered',
        ];
    }

    private function formatRecoveredCaseGrouped($group)
    {
        // Get first assignment as base
        $firstAssignment = $group->first();
        $client = $firstAssignment->emi->loanAccount->client;
        $loanAccount = $firstAssignment->emi->loanAccount;
        
        // Get all EMIs for this group
        $emis = $group->pluck('emi');
        
        // Calculate total recovered amount (Paid Now)
        $paidNow = $emis->sum('paid_amount');
        
        // Calculate Total Due Amount (Original Total Due)
        $totalDueAmount = $emis->sum('total_due'); // Changed from total_amount
        
        // Calculate Remaining Balance
        $remainingBalance = $emis->sum('pending_amount');
        
        // Get latest collection to determine payment mode, type, and ACTUAL TIME
        $latestCollection = $emis->flatMap(fn($emi) => $emi->collections)->sortByDesc('collected_at')->first();
        
        // Use collection's collected_at for accurate time (not EMI's paid_date which is DATE only)
        $recoveredDateTime = $latestCollection ? $latestCollection->collected_at : $emis->max('paid_date');
        
        // Determine Loan Type
        $loanType = optional(optional(optional($loanAccount->loanApplication)->product)->loanType)->name ?? 'Unknown Loan';

        return [
            'loan_account_id' => $loanAccount->id,
            'client_name' => $client->client_name,
            'loan_number' => $loanAccount->account_number,
            'loan_type' => $loanType,
            
            'recovered_amount' => (float) $paidNow,
            'recovered_on' => $recoveredDateTime ? $recoveredDateTime->format('d-m-Y') : null,
            'formatted_recovered_on' => $recoveredDateTime ? $recoveredDateTime->format('d-m-Y • h:i A') : null,
            
            'payment_details' => [
                'payment_type' => $latestCollection ? ucfirst($latestCollection->payment_type) : 'Full Payment',
                'payment_mode' => $latestCollection ? ucfirst(str_replace('_', ' ', $latestCollection->payment_method)) : 'Unknown',
            ],
            
            'breakdown' => [
                'total_due_amount' => (float) $totalDueAmount,
                'paid_now' => (float) $paidNow,
                'remaining_balance' => (float) $remainingBalance,
            ],
            
            'customer' => [
                'id' => $client->id,
                'name' => $client->client_name,
                'loan_id' => $loanAccount->account_number,
                'loan_type' => $loanType,
                'profile_image_url' => $client->profile_image_url,
            ],

            // Keep backward compatibility fields if needed
            'total_amount' => (float) $paidNow,
            'emi_count' => $emis->count(),
            'emi_ids' => $emis->pluck('id')->toArray(),
            'recovery_note' => sprintf(
                '%d EMIs recovered, %s',
                $emis->count(),
                $latestCollection
                    ? ucfirst(str_replace('_', ' ', $latestCollection->payment_method))
                    : 'Unknown'
            ),
            'status' => 'Recovered',
        ];
    }
}
