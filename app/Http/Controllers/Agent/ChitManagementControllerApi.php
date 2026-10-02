<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\ChitGroup;
use App\Models\Client;
use App\Models\GroupMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class ChitManagementControllerApi extends Controller
{
    /**
     * Scope to agent clients helper.
     */
    // protected function scopeToAgentClients(Builder $query, int $agentId): Builder
    // {
    //     $currentUser = auth()->user();
    //     $userId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;
    //     $userName = $currentUser instanceof \App\Models\Agent ? $currentUser->agent_name : $currentUser->name;
     
    //     return $query->where(function ($q) use ($userId, $agentId, $userName) {
    //         $q->where('referred_by_agent_id', $agentId)
    //             ->orWhere(function ($appliedBy) use ($userId, $userName) {
    //                 $appliedBy->where('applied_by', $userId);
     
    //                 // Legacy rows before applied_by existed
    //                 if ($userName) {
    //                     $appliedBy->orWhere(function ($legacy) use ($userName) {
    //                         $legacy->whereNull('applied_by')
    //                             ->where('remarks', 'like', 'Applied by ' . $userName . '%');
    //                     });
    //                 }
    //             });
    //     });
    // }
    protected function scopeToAgentClients(Builder $query, int $agentId): Builder
    {
        return $query->where(function ($q) use ($agentId) {
            $q->whereHas('client', function ($clientQuery) use ($agentId) {
                $clientQuery->where('assigned_to', $agentId);
            })
            ->orWhereHas('shares.client', function ($clientQuery) use ($agentId) {
                $clientQuery->where('assigned_to', $agentId);
            });
        });
    }
    // protected function scopeToAgentClients(Builder $query, int $agentId): Builder
    // {
    //     $currentUser = auth()->user();
    //     $userId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;
    //     $userName = $currentUser instanceof \App\Models\Agent ? $currentUser->agent_name : $currentUser->name;

    //     return $query->where(function ($q) use ($userId, $agentId, $userName) {
    //         $q->where('applied_by', $userId);

    //         // Legacy rows before applied_by existed
    //         if ($userName) {
    //             $q->orWhere(function ($legacy) use ($agentId, $userName) {
    //                 $legacy->whereNull('applied_by')
    //                     ->where('referred_by_agent_id', $agentId)
    //                     ->where('remarks', 'like', 'Applied by ' . $userName . '%');
    //             });
    //         }
    //     });
    // }

    /**
     * Get application counts.
     */
    protected function applicationCounts(int $agentId): array
    {
        $base = GroupMember::query()->whereIn('status', ['applied', 'approved', 'active', 'rejected']);

        $this->scopeToAgentClients($base, $agentId);

        return [
            'applied' => (clone $base)->where('status', 'applied')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'total' => (clone $base)->count(),
        ];
    }

    /**
     * Check if agent owns client.
     */
    protected function agentOwnsClient(Client $client): bool
    {
        $currentUser = auth()->user();
        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        return ((int) $client->added_by === (int) $agentId || (int) $client->assigned_to === (int) $agentId);
    }

    /**
     * Metadata endpoint for Chit Applications form.
     */
    public function metadata(): JsonResponse
    {
        $currentUser = auth()->user();
        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;

        $verifiedClients = Client::whereHas('kycDetail', function ($q) {
            $q->where('status', 'verified');
        })->where('assigned_to', $agentId)->orderBy('client_name')->get(['id', 'client_name', 'client_phone', 'client_email']);

        $availableGroups = ChitGroup::with('scheme')
            ->whereIn('status', ['forming', 'active'])
            ->withCount(['members' => fn ($q) => $q->whereNotIn('status', ['rejected', 'transferred', 'withdrawn', 'cancelled'])])
            ->orderBy('id', 'desc')
            ->get(['id', 'group_code', 'scheme_id', 'status', 'total_members', 'chit_value', 'installment_amount', 'total_months', 'current_month', 'start_date', 'end_date', 'installment_frequency'])
            ->filter(fn ($g) => $g->members_count < $g->total_members)
            ->each(function ($g) {
                $scheduleInstallment = $g->apiInstallmentAmount();
                $g->installment_amount = $scheduleInstallment;
                $g->settlement_amount = $g->resolvePayoutAmountForMonth((int) $g->current_month + 1);
                $fmtCurrency = function ($amount) {
                    return '₹' . preg_replace("/(\d+?)(?=(\d\d)+(\d)(?!\d))(\.\d+)?/i", "$1,", (string) round((float)$amount));
                };

                // Formatted fields for mobile app display
                $g->chit_value_formatted = $fmtCurrency($g->chit_value);
                $g->installment_formatted = $fmtCurrency($scheduleInstallment);
                $g->settlement_amount_formatted = $fmtCurrency($g->settlement_amount);
                $g->members_vacancy_label = $g->members_count . '/' . $g->total_members . ' · ' . $g->vacancy . ' open';
                $g->duration_label = 'Month ' . max(1, (int) $g->current_month) . ' / ' . $g->total_months;
                $g->start_date_formatted = $g->start_date ? \Carbon\Carbon::parse($g->start_date)->format('d-m-Y') : '—';
                $g->end_date_formatted = $g->end_date ? \Carbon\Carbon::parse($g->end_date)->format('d-m-Y') : '—';
                $g->collection_frequency_label = ucfirst($g->installment_frequency ?? 'Monthly');
            })->values();

        $availableSchemes = $availableGroups->groupBy('scheme_id')->map(function ($groups, $schemeId) {
            $scheme = $groups->first()->scheme;
            return [
                'id' => $scheme ? $scheme->id : $schemeId,
                'name' => $scheme ? $scheme->name : 'Unknown Scheme',
                'available_groups' => $groups->values()
            ];
        })->values();

        $counts = $this->applicationCounts($agentId);

        return response()->json([
            'success' => true,
            'data' => [
                'verified_clients' => $verifiedClients,
                'available_schemes' => $availableSchemes,
                'counts' => $counts
            ]
        ]);
    }

    /**
     * List Chit Applications for the authenticated agent.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;

        $query = GroupMember::with(['group.scheme:id,name,payout_schedule,installment_amount,foreman_commission_month', 'client:id,client_name,client_phone,client_email,alternate_phone', 'shares.client.agent', 'referrerAgent', 'referrerClient', 'installments'])
            ->latest();

        $this->scopeToAgentClients($query, $agentId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['applied', 'approved', 'active', 'rejected']);
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('client', fn ($c) => $c->where('client_name', 'like', "%$s%")->orWhere('client_phone', 'like', "%$s%"))
                    ->orWhereHas('group', fn ($g) => $g->where('group_code', 'like', "%$s%"));
            });
        }

        $applications = $query->paginate($request->input('per_page', 15));
        
        $applications->getCollection()->transform(function ($member) {
            $clientName = $member->client->client_name ?? '—';
            $clientPhone = $member->client->client_phone ?? '—';
            $group = $member->group;
            $scheme = $group->scheme ?? null;
            $clientId = (int) $member->client_id;

            $ownershipPct = (float) $member->ownershipPercentageFor($clientId);
            if ($ownershipPct <= 0) {
                $ownershipPct = (float) ($member->effective_share_percentage ?? $member->share_percentage ?? 100);
            }

            $fullChitValue = (float) ($group->chit_value ?? 0);
            $chitValueShare = $member->is_shared
                ? (float) $member->amountForClient($fullChitValue, $clientId)
                : round($fullChitValue * ($ownershipPct / 100.0), 2);

            $installment = (float) $member->apiInstallmentAmountForClient($clientId, $group);
            $settlementAmount = $member->is_shared
                ? (float) $member->amountForClient((float) $member->settlement_amount, $clientId)
                : round((float) $member->settlement_amount * ($ownershipPct / 100.0), 2);

            return [
                'id' => $member->id,
                'client_name' => $clientName,
                'client_phone' => $clientPhone,
                'client_email' => $member->client->client_email ?? '—',
                'alternate_phone' => $member->client->alternate_phone ?? '—',
                'group_code' => $group->group_code ?? '—',
                'group_status' => $group->status ?? '—',
                'scheme_name' => $scheme->name ?? '—',
                'chit_value' => $chitValueShare,
                'installment' => $installment,
                'is_shared' => (bool) $member->is_shared,
                'ownership_percentage' => $ownershipPct,
                'share_percentage' => $ownershipPct,
                'share_percentage_formatted' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
                'share_label' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
                'collection_frequency' => $member->collection_frequency ?? 'monthly',
                'collection_frequency_label' => $member->collection_frequency_label ?? 'Monthly',
                'collection_split_amount' => $member->collectionSplitAmount($installment),
                'total_months' => (int) ($group->total_months ?? 0),
                'settlement_amount' => $settlementAmount,
                'status' => $member->status,
                'applied_at' => $member->created_at ? $member->created_at->format('Y-m-d') : '—',
                'joined_date' => optional($member->joined_date)->format('Y-m-d') ?? '—',
                'member_number' => $member->member_number,
                'chit_need_month' => $member->chit_need_month_label,
                'chit_need_month_label' => $member->chit_need_month_label,
                'preferred_chit_need_period' => $member->preferredChitNeedPeriod(),
                'remarks' => $member->remarks ?? '',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $applications,
            'counts' => $this->applicationCounts($agentId)
        ]);
    }

    /**
     * Apply for a Chit Group.
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'group_id' => 'required|exists:chit_groups,id',
            'chit_need_month' => 'nullable|integer|min:1|max:12',
            'collection_frequency' => 'nullable|in:monthly,weekly,daily',
        ]);

        $client = Client::findOrFail((int) $request->client_id);

        if (!$this->agentOwnsClient($client)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only submit chit applications for your own clients.',
            ], 403);
        }

        $kycStatus = optional($client->kycDetail)->status;
        if ($kycStatus !== 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Client KYC must be verified before applying for chit.',
            ], 422);
        }

        $group = ChitGroup::findOrFail($request->group_id);

        $currentCount = $group->valid_members_count;
        if ($currentCount >= $group->total_members) {
            return response()->json(['success' => false, 'message' => 'Group is full. No slots available.'], 422);
        }

        $clientId = (int) $client->id;
        $collectionFrequency = strtolower((string) $request->input('collection_frequency', 'monthly'));
        if (! in_array($collectionFrequency, ['daily', 'weekly', 'monthly'], true)) {
            $collectionFrequency = 'monthly';
        }

        $existingMember = GroupMember::where('group_id', $group->id)
            ->where('client_id', $clientId)
            ->whereIn('status', ['applied', 'approved', 'active'])
            ->first();

        if ($existingMember) {
            return response()->json(['success' => false, 'message' => 'Client already has an active/pending application in this group.'], 422);
        }

        $currentUser = $request->user();
        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        $agentUserId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;
        $agentName = $currentUser instanceof \App\Models\Agent ? $currentUser->agent_name : $currentUser->name;

        $needFields = GroupMember::resolveChitNeedFields(
            $request->filled('chit_need_month') ? (int) $request->chit_need_month : null,
            $group
        );

        try {
            $member = DB::transaction(function () use (
                $group,
                $clientId,
                $agentId,
                $agentUserId,
                $agentName,
                $needFields,
                $collectionFrequency
            ) {
                $memberNumber = $group->getNextAvailableMemberNumber();

                return GroupMember::create([
                    'group_id' => $group->id,
                    'client_id' => $clientId,
                    'is_shared' => false,
                    'collection_frequency' => $collectionFrequency,
                    'referred_by_agent_id' => $agentId,
                    'referred_by_client_id' => null,
                    'member_number' => $memberNumber,
                    'status' => 'applied',
                    'joined_date' => today()->format('Y-m-d'),
                    'chit_need_month' => $needFields['chit_need_month'],
                    'chit_need_date' => $needFields['chit_need_date'],
                    'applied_by' => $agentUserId,
                    'remarks' => 'Applied by ' . $agentName . ' on ' . now()->format('d M Y'),
                ]);
            });

            event(new \App\Events\NewChitApplicationEvent($member->loadMissing(['client', 'group.scheme']), 'agent'));

            return response()->json([
                'success' => true,
                'message' => 'Chit application submitted successfully for ' . ($member->client->client_name ?? 'client') . '.',
                'data' => $member
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while applying: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific Chit Application.
     */
    public function show($id): JsonResponse
    {
        $currentUser = auth()->user();
        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        
        $member = GroupMember::with(['client.agent', 'group.scheme', 'referrerAgent', 'referrerClient', 'approvedBy', 'installments'])
            ->where('id', $id)
            ->first();

        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Application not found.'], 404);
        }

        // Verify ownership
        $userId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;
        $userName = $currentUser instanceof \App\Models\Agent ? $currentUser->agent_name : optional($currentUser)->name;
        // $canAccess = false;
         
        // if ((int) $member->referred_by_agent_id === (int) $agentId) {
        //     $canAccess = true;
        // } elseif ((int) $member->applied_by === (int) $userId) {
        //     $canAccess = true;
        // } elseif (
        //     !$member->applied_by &&
        //     str_starts_with((string) $member->remarks, 'Applied by ' . $userName)
        // ) {
        //     $canAccess = true;
        // }
         
        // if (!$canAccess) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'You can only view chit applications for your own clients.'
        //     ], 403);
        // }

        if (!$member->group) {
            return response()->json(['success' => false, 'message' => 'Application group no longer exists.'], 404);
        }

        $monthWise = $member->buildMonthWiseInstallments($member->client);
        $memberArr = $member->toArray();
        $memberArr['installments'] = $monthWise;
        $memberArr['month_wise_installments'] = $monthWise;
        $memberArr['collection_frequency'] = $member->collection_frequency ?? 'monthly';
        $memberArr['collection_frequency_label'] = $member->collection_frequency_label ?? ucfirst($member->collection_frequency ?? 'monthly');

        return response()->json([
            'success' => true,
            'data' => $memberArr
        ]);
    }
    
    /**
     * Chit installments for clients assigned/referred to this agent.
     */
    private function applyChitAgentScope(\Illuminate\Database\Eloquent\Builder $query, int $agentId): void
    {
        $query->whereHas('member', function ($mq) use ($agentId) {
            $mq->where(function ($memberQ) use ($agentId) {
                $memberQ->where('referred_by_agent_id', $agentId)
                    ->orWhereHas('client', function ($cq) use ($agentId) {
                        $cq->where('assigned_to', $agentId);
                    })
                    ->orWhereHas('shares', function ($sq) use ($agentId) {
                        $sq->whereHas('client', function ($cq) use ($agentId) {
                            $cq->where('assigned_to', $agentId);
                        });
                    });
            });
        });
    }

    /**
     * Exclude other agents' collections
     */
    private function excludeOtherAgentChitCollections(\Illuminate\Database\Eloquent\Builder $query, int $agentId, ?int $agentUserId = null): void
    {
        $ownCollectorIds = array_values(array_filter([$agentId, $agentUserId]));

        $otherCollectorIds = \App\Models\Agent::where('id', '!=', $agentId)
            ->get(['id', 'user_id'])
            ->flatMap(fn (\App\Models\Agent $agent) => [(int) $agent->id, $agent->user_id ? (int) $agent->user_id : null])
            ->filter()
            ->reject(fn (int $id) => in_array($id, $ownCollectorIds, true))
            ->unique()
            ->values()
            ->all();

        $query->where(function ($q) use ($otherCollectorIds) {
            $q->whereNull('collected_by');

            if (empty($otherCollectorIds)) {
                $q->orWhereNotNull('collected_by');
            } else {
                $q->orWhereNotIn('collected_by', $otherCollectorIds);
            }
        });
    }



    /**
     * GroupMember query scope for agent assigned clients
     */
    // private function scopeGroupMemberToAgentClients(\Illuminate\Database\Eloquent\Builder $query, int $agentId, int $userId): void
    // {
    //     $assignedClientIds = \App\Models\Client::where(function ($q) use ($agentId, $userId) {
    //         $q->where('assigned_to', $agentId)
    //           ->orWhere('user_id', $userId);
    //     })->pluck('id')->toArray();

    //     $query->where(function ($q) use ($agentId, $assignedClientIds) {
    //         $q->whereIn('client_id', $assignedClientIds)
    //           ->orWhere('referred_by_agent_id', $agentId)
    //           ->orWhereHas('shares', fn ($sq) => $sq->whereIn('client_id', $assignedClientIds));
    //     });
    // }
    private function scopeGroupMemberToAgentClients(
        \Illuminate\Database\Eloquent\Builder $query,
        int $agentId,
        int $userId
    ): void {
        $assignedClientIds = \App\Models\Client::where(function ($q) use ($agentId, $userId) {
            $q->where('assigned_to', $agentId)
              ->orWhere('user_id', $userId);
        })->pluck('id')->toArray();
    
        $query->where(function ($q) use ($assignedClientIds) {
            $q->whereIn('client_id', $assignedClientIds)
              ->orWhereHas('shares', fn ($sq) => $sq->whereIn('client_id', $assignedClientIds));
        });
    }

    /**
     * List chit accounts for the authenticated agent
     */
    public function chitAccounts(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        $userId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;

        $query = GroupMember::accounts()
            ->with(['client.location', 'group.scheme', 'installments']);

        $this->scopeGroupMemberToAgentClients($query, $agentId, $userId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('account_number')) {
            $search = $request->account_number;
            $query->where(function ($q) use ($search) {
                $q->whereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$search}%"))
                    ->orWhereHas('client', fn ($c) => $c->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_phone', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('joined_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('joined_date', '<=', $request->to_date);
        }
        
        if (!empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$search}%"))
                    ->orWhereHas('client', fn ($c) => $c->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_phone', 'like', "%{$search}%"))
                    ->orWhereHas('group.scheme', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        // Stats
        $baseQuery = GroupMember::accounts();
        $this->scopeGroupMemberToAgentClients($baseQuery, $agentId, $userId);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 'active')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
        ];

        $members = $query->latest('id')->paginate($request->input('per_page', 15));

        $members->getCollection()->transform(function (GroupMember $member) {
            $group = $member->group;
            $scheme = $group?->scheme;
            $clientId = (int) $member->client_id;

            $ownershipPct = (float) $member->ownershipPercentageFor($clientId);
            if ($ownershipPct <= 0) {
                $ownershipPct = (float) ($member->effective_share_percentage ?? $member->share_percentage ?? 100);
            }

            $fullChitValue = (float) ($group->chit_value ?? 0);
            $chitValueShare = $member->is_shared
                ? (float) $member->amountForClient($fullChitValue, $clientId)
                : round($fullChitValue * ($ownershipPct / 100.0), 2);

            $installmentAmount = (float) $member->apiInstallmentAmountForClient($clientId, $group);
            $outstanding = $member->is_shared
                ? (float) $member->amountForClient((float) $member->outstanding_balance, $clientId)
                : round((float) $member->outstanding_balance * ($ownershipPct / 100.0), 2);

            $totalInstallments = $member->installments->count();
            $paidCount = $member->paid_installments_count;

            return [
                'id' => $member->id,
                'account_number' => $member->account_number,
                'client_name' => $member->client->client_name ?? 'N/A',
                'client_phone' => $member->client->client_phone ?? 'N/A',
                'client_id' => $member->client_id,
                'zone' => $member->client->location->name ?? 'N/A',
                'group_code' => $group->group_code ?? 'N/A',
                'scheme_name' => $scheme->name ?? 'N/A',
                'chit_value' => $chitValueShare,
                'chit_value_formatted' => '₹' . number_format($chitValueShare, 0),
                'installment_amount' => $installmentAmount,
                'installment_amount_formatted' => '₹' . number_format($installmentAmount, 2),
                'collection_frequency' => $member->collection_frequency ?? 'monthly',
                'collection_frequency_label' => $member->collection_frequency_label ?? ucfirst($member->collection_frequency ?? 'monthly'),
                'tenure_formatted' => ($group->total_months ?? $totalInstallments) . ' months',
                'progress_formatted' => $paidCount . '/' . max($totalInstallments, 1),
                'outstanding_balance' => $outstanding,
                'outstanding_formatted' => '₹' . number_format($outstanding, 2),
                'status' => $member->status,
                'status_label' => $member->status_label,
                'status_badge' => $member->status_badge,
                'joined_date' => optional($member->joined_date)->format('Y-m-d') ?? 'N/A',
                'member_number' => $member->member_number,
                'is_shared' => (bool) $member->is_shared,
                'ownership_percentage' => $ownershipPct,
                'share_percentage' => $ownershipPct,
                'share_percentage_formatted' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
                'share_label' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $members,
            'stats' => $stats
        ]);
    }

    /**
     * View individual chit account details
     */
    public function viewChitAccount($id = null): JsonResponse
    {
        $id = $id ?? request()->input('id') ?? request()->input('chit_account_id') ?? request()->input('member_id');
        if (! $id) {
            return $this->chitAccounts(request());
        }

        $currentUser = auth()->user();
        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        $userId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;
        $isAdmin = $currentUser && ($currentUser instanceof \App\Models\User ? ($currentUser->hasRole(['admin', 'super_admin', 'super-admin', 'Super Admin']) || $currentUser->id === 1) : false);

        $member = GroupMember::with([
            'client',
            'group.scheme',
            'shares.client',
            'referrerAgent',
            'referrerClient',
        ])->find($id);

        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Chit account not found.'], 404);
        }

        if (!in_array($member->status, ['active', 'completed', 'defaulted'], true)) {
            return response()->json(['success' => false, 'message' => 'This enrollment is not an active chit account yet.'], 400);
        }

        if (!$member->group) {
            return response()->json(['success' => false, 'message' => 'Chit group for this account no longer exists.'], 404);
        }

        // Verify agent ownership
        $assignedClientIds = \App\Models\Client::where(function ($q) use ($agentId, $userId) {
            $q->where('assigned_to', $agentId)
              ->orWhere('assigned_to', $userId)
              ->orWhere('user_id', $userId);
        })->pluck('id')->toArray();

        $isAuthorized = $isAdmin;
        if (! $isAuthorized) {
            if (in_array($member->client_id, $assignedClientIds)) {
                $isAuthorized = true;
            } elseif ($member->referred_by_agent_id == $agentId || $member->referred_by_agent_id == $userId) {
                $isAuthorized = true;
            } else {
                foreach ($member->shares as $share) {
                    if (in_array($share->client_id, $assignedClientIds)) {
                        $isAuthorized = true;
                        break;
                    }
                }
            }
        }

        if (!$isAuthorized) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to view this chit account.'], 403);
        }

        \App\Models\Installment::applyAutomatedPenalties();
        
        // Reload installments as penalties might have changed
        $member->load(['installments' => fn ($q) => $q->with('collections')->orderBy('month_number')]);
        
        $installments = $member->installments->values();

        $clientId = (int) $member->client_id;

        // Statuses shown to the app fold in agent collections that an admin has
        // not verified yet (Installment::effectiveStatus), so the counts here are
        // derived the same way rather than from the raw column.
        $effectiveStatuses = $installments->map(
            fn ($installment) => $installment->effectiveStatus($clientId)
        );

        $totalPendingVerification = round((float) $installments->sum(fn ($i) => (float) $i->pendingCollectedAmount($clientId)), 2);
        $totalPaid = round((float) $installments->sum(fn ($i) => (float) $i->clientPaidShare($clientId)), 2);
        $totalDue = round((float) $installments->sum(fn ($i) => (float) $i->clientDueShare($clientId)), 2);
        $rawOutstanding = round((float) $member->outstanding_balance, 2);
        $collectibleOutstanding = round(max(0, $rawOutstanding - $totalPendingVerification), 2);

        $paidCount = $effectiveStatuses->filter(fn ($s) => $s === 'paid')->count();
        $inProgressCount = $effectiveStatuses->filter(fn ($s) => $s === 'in_progress')->count();
        $overdueCount = $effectiveStatuses->filter(fn ($s) => $s === 'overdue')->count();
        $pendingCount = $effectiveStatuses->filter(fn ($s) => in_array($s, ['pending', 'partial'], true))->count();

        $stats = [
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'outstanding' => $collectibleOutstanding,
            'collectible_outstanding' => $collectibleOutstanding,
            'collectible_balance' => $collectibleOutstanding,
            'total_in_progress' => $totalPendingVerification,
            'in_progress_amount' => $totalPendingVerification,
            'pending_verification_amount' => $totalPendingVerification,
            'raw_outstanding' => $rawOutstanding,
            'paid_count' => $paidCount,
            'in_progress_count' => $inProgressCount,
            'pending_count' => $pendingCount,
            'overdue_count' => $overdueCount,
        ];
        $ownershipPct = (float) $member->ownershipPercentageFor($clientId);
        if ($ownershipPct <= 0) {
            $ownershipPct = (float) ($member->effective_share_percentage ?? $member->share_percentage ?? 100);
        }
        $shareFormatted = rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%';

        $fullChitValue = (float) ($member->group?->chit_value ?? 0);
        $chitValueShare = $member->is_shared
            ? (float) $member->amountForClient($fullChitValue, $clientId)
            : round($fullChitValue * ($ownershipPct / 100.0), 2);

        $memberArr = $member->applyApiInstallmentToArray(
            $member->toArray(),
            (float) $member->apiInstallmentAmountForClient($clientId, $member->group)
        );
        unset($memberArr['installments']);
        $memberArr['is_shared'] = (bool) $member->is_shared;
        $memberArr['ownership_percentage'] = $ownershipPct;
        $memberArr['share_percentage'] = $ownershipPct;
        $memberArr['share_percentage_formatted'] = $shareFormatted;
        $memberArr['share_label'] = $shareFormatted;
        $memberArr['chit_value_share'] = $chitValueShare;
        
        $memberArr['joined_date'] = optional($member->joined_date)->format('Y-m-d');
        $memberArr['approved_at'] = optional($member->approved_at)->format('Y-m-d H:i:s');
        $memberArr['chit_need_date'] = optional($member->chit_need_date)->format('Y-m-d');
        $memberArr['collection_frequency'] = $member->collection_frequency ?? 'monthly';
        $memberArr['collection_frequency_label'] = $member->collection_frequency_label ?? ucfirst($member->collection_frequency ?? 'monthly');
        $memberArr['account_number'] = $member->account_number ?? $member->chit_account_number ?? $member->enrollment_number ?? ('CHIT-' . $member->id);
        $memberArr['chit_account_number'] = $memberArr['account_number'];

        // Month-wise installments with nested weekly/daily periods
        $monthWiseInstallments = $member->buildMonthWiseInstallments($member->client);

        return response()->json([
            'success' => true,
            'data' => [
                'member' => $memberArr,
                'installments' => $monthWiseInstallments,
                'stats' => $stats
            ]
        ]);
    }
}
