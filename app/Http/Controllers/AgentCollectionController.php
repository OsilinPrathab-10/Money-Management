<?php

namespace App\Http\Controllers;

use App\Models\EmiCollection;
use App\Models\ChitCollection;
use App\Models\Agent;
use App\Models\Emi;
use App\Models\Installment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\PartialPaymentConfigService;
use App\Support\BulkPaymentGroup;

class AgentCollectionController extends Controller
{
    private function collectionCollectorPayload(EmiCollection $collection): array
    {
        if ($collection->agent_id) {
            $agent = $collection->relationLoaded('agent') ? $collection->agent : Agent::find($collection->agent_id);
            if (!$agent) {
                $agent = Agent::where('user_id', $collection->agent_id)->first();
            }
            if ($agent) {
                return [
                    'agent_name' => $agent->agent_name,
                    'collector_type' => 'agent',
                ];
            }
        }

        if ($collection->verified_by) {
            $user = $collection->relationLoaded('verifiedBy') ? $collection->verifiedBy : User::find($collection->verified_by);
            if ($user?->agent && $user->hasRole('Agent')) {
                return [
                    'agent_name' => $user->agent->agent_name,
                    'collector_type' => 'agent',
                ];
            }
        }

        return [
            'agent_name' => 'Admin',
            'collector_type' => 'admin',
        ];
    }

    private function chitCollectorPayload(Installment $installment): array
    {
        $collectedBy = $installment->collected_by;

        if ($collectedBy) {
            $agent = Agent::where('id', $collectedBy)->orWhere('user_id', $collectedBy)->first();
            if ($agent) {
                $user = User::find($agent->user_id);
                if (!$user || $user->hasRole('Agent') || $user->agent) {
                    return [
                        'agent_name' => $agent->agent_name,
                        'collector_type' => 'agent',
                    ];
                }
            }

            $user = $installment->relationLoaded('collectedBy')
                ? $installment->collectedBy
                : User::find($collectedBy);

            if ($user?->agent && $user->hasRole('Agent')) {
                return [
                    'agent_name' => $user->agent->agent_name,
                    'collector_type' => 'agent',
                ];
            }
        }

        return [
            'agent_name' => 'Admin',
            'collector_type' => 'admin',
        ];
    }

    /**
     * Chit installments for clients assigned/referred to this agent (same rule as agent dashboard).
     */
    private function applyChitAgentScope($query, int $agentId): void
    {
        $query->whereHas('member', function ($mq) use ($agentId) {
            $mq->where(function ($memberQ) use ($agentId) {
                $memberQ->where('referred_by_agent_id', $agentId)
                    ->orWhereHas('client', function ($cq) use ($agentId) {
                        $cq->where('assigned_to', $agentId);
                            // ->orWhere('added_by', $agentId);
                    })
                    ->orWhereHas('shares', function ($sq) use ($agentId) {
                        $sq->whereHas('client', function ($cq) use ($agentId) {
                            $cq->where('assigned_to', $agentId);
                                // ->orWhere('added_by', $agentId);    
                        });
                    });
            });
        });
    }

    /**
     * Loan collections for clients assigned/added to this agent or with an active EMI assignment.
     */
    private function applyLoanAgentScope($query, int $agentId): void
    {
        $query->where(function ($q) use ($agentId) {
            $q->whereHas('emi.loanAccount.client', function ($cq) use ($agentId) {
                $cq->where('assigned_to', $agentId);
                    // ->orWhere('added_by', $agentId);
            })
            ->orWhereHas('emi.activeAssignment', function ($aq) use ($agentId) {
                $aq->where('agent_id', $agentId);
            });
        });
    }

    private function agentCanAccessLoanEmi(Emi $emi, int $agentId): bool
    {
        $client = $emi->loanAccount?->client;
        if ($client && ((int) $client->assigned_to === $agentId || (int) $client->added_by === $agentId)) {
            return true;
        }

        return $emi->activeAssignment()
            ->where('agent_id', $agentId)
            ->exists();
    }

    private function agentCanAccessChitInstallment(Installment $installment, int $agentId): bool
    {
        return Installment::query()
            ->whereKey($installment->id)
            ->where(function ($q) use ($agentId) {
                $this->applyChitAgentScope($q, $agentId);
            })
            ->exists();
    }

    /**
     * Resolves an Installment model and optional period index from various ID formats
     * that might be sent by mobile clients or web (e.g. "chit_1848_p_1", "chit_18481", 18481, "1848").
     *
     * @param string|int|array $target
     * @param int|null $explicitPeriod
     * @return array{0: Installment|null, 1: ?int}
     */
    private function resolveChitInstallmentAndPeriod(string|int|array $target, ?int $explicitPeriod = null): array
    {
        $rawId = '';
        $explicitInstId = null;

        if (is_array($target)) {
            $rawId = (string) ($target['id'] ?? $target['chit_installment_id'] ?? $target['due_id'] ?? '');
            $explicitInstId = $target['chit_installment_id'] ?? $target['installment_id'] ?? $target['due_id'] ?? null;
            if ($explicitPeriod === null) {
                $explicitPeriod = isset($target['period_index']) && $target['period_index'] !== ''
                    ? (int) $target['period_index']
                    : (isset($target['period_number']) && $target['period_number'] !== '' ? (int) $target['period_number'] : null);
            }
        } else {
            $rawId = (string) $target;
        }

        // 1. Check if rawId matches pattern "chit_{id}_p_{period}" or "{id}_p_{period}"
        if (preg_match('/^(?:chit_)?(\d+)_p_?(\d+)$/', $rawId, $m)) {
            $instId = (int) $m[1];
            $period = (int) $m[2];
            $inst = Installment::with(['member.client', 'group'])->find($instId);
            if ($inst) {
                return [$inst, $period];
            }
        }

        // 2. Direct lookup: check if clean numeric ID exists directly in database
        $cleanId = preg_replace('/^chit_/', '', $rawId);
        if (is_numeric($cleanId)) {
            $inst = Installment::with(['member.client', 'group'])->find((int) $cleanId);
            if ($inst) {
                return [$inst, $explicitPeriod];
            }
        }

        // 3. Check explicit installment ID if different from cleanId
        if ($explicitInstId && is_numeric($explicitInstId) && (int) $explicitInstId !== (int) $cleanId) {
            $inst = Installment::with(['member.client', 'group'])->find((int) $explicitInstId);
            if ($inst) {
                return [$inst, $explicitPeriod];
            }
        }

        // 4. Handle concatenated digits from mobile clients (e.g. "chit_18481" or "18481" where 1848 is installment and 1 is period)
        $digitsOnly = preg_replace('/\D/', '', $rawId);
        if (strlen($digitsOnly) >= 2) {
            // Check 1-digit period suffix (periods 1 to 9): e.g. 18481 -> id 1848, period 1
            $candidateId1 = (int) substr($digitsOnly, 0, -1);
            $candidatePeriod1 = (int) substr($digitsOnly, -1);
            $inst1 = Installment::with(['member.client', 'group'])->find($candidateId1);
            if ($inst1 && $candidatePeriod1 >= 1) {
                $freq = $inst1->member?->collection_frequency ?? 'monthly';
                if ($freq === 'weekly' && $candidatePeriod1 <= 5) {
                    return [$inst1, $candidatePeriod1];
                }
                if ($freq === 'daily' && $candidatePeriod1 <= 9) {
                    return [$inst1, $candidatePeriod1];
                }
                return [$inst1, $candidatePeriod1];
            }

            // Check 2-digit period suffix (periods 10 to 31 for daily): e.g. 184825 -> id 1848, period 25
            if (strlen($digitsOnly) >= 3) {
                $candidateId2 = (int) substr($digitsOnly, 0, -2);
                $candidatePeriod2 = (int) substr($digitsOnly, -2);
                if ($candidatePeriod2 >= 10 && $candidatePeriod2 <= 31) {
                    $inst2 = Installment::with(['member.client', 'group'])->find($candidateId2);
                    if ($inst2 && ($inst2->member?->collection_frequency ?? '') === 'daily') {
                        return [$inst2, $candidatePeriod2];
                    }
                }
            }
        }

        // 5. Also check if $explicitInstId had concatenated digits
        if ($explicitInstId && is_numeric($explicitInstId)) {
            $digitsOnlyExp = (string) $explicitInstId;
            if (strlen($digitsOnlyExp) >= 2 && $digitsOnlyExp !== $digitsOnly) {
                $candidateId1 = (int) substr($digitsOnlyExp, 0, -1);
                $candidatePeriod1 = (int) substr($digitsOnlyExp, -1);
                $inst1 = Installment::with(['member.client', 'group'])->find($candidateId1);
                if ($inst1 && $candidatePeriod1 >= 1) {
                    return [$inst1, $candidatePeriod1];
                }
                if (strlen($digitsOnlyExp) >= 3) {
                    $candidateId2 = (int) substr($digitsOnlyExp, 0, -2);
                    $candidatePeriod2 = (int) substr($digitsOnlyExp, -2);
                    if ($candidatePeriod2 >= 10 && $candidatePeriod2 <= 31) {
                        $inst2 = Installment::with(['member.client', 'group'])->find($candidateId2);
                        if ($inst2) {
                            return [$inst2, $candidatePeriod2];
                        }
                    }
                }
            }
        }

        return [null, null];
    }


    /**
     * Pick the customer who still owes on this installment seat.
     * Prefers an explicit client_id when that owner still has a balance share.
     */
    private function resolveChitCollectionClientId(Installment $installment, ?int $preferredClientId = null): ?int
    {
        $installment->loadMissing(['member.shares', 'sharePayments']);
        $member = $installment->member;
        if (!$member) {
            return $preferredClientId ?: null;
        }

        $ownerIds = $member->resolveOwnershipShares()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($preferredClientId && $preferredClientId > 0) {
            array_unshift($ownerIds, $preferredClientId);
            $ownerIds = array_values(array_unique($ownerIds));
        } elseif ($member->client_id) {
            array_unshift($ownerIds, (int) $member->client_id);
            $ownerIds = array_values(array_unique($ownerIds));
        }

        foreach ($ownerIds as $clientId) {
            if ($installment->clientBalanceShare($clientId) > 0.009) {
                return $clientId;
            }
        }

        return $preferredClientId ?: ((int) ($member->client_id ?? 0) ?: ($ownerIds[0] ?? null));
    }

    /**
     * Apply a verified agent chit collection onto the installment seat.
     * Agent collections may cover the full seat balance (all co-owners), so the
     * amount is split across owners who still have an outstanding share.
     */
    private function applyVerifiedChitCollection(\App\Models\ChitCollection $collection, ?int $bankAccountId = null): void
    {
        $installment = Installment::with(['member.shares', 'sharePayments'])
            ->find($collection->installment_id);

        if (!$installment || !$installment->member) {
            throw ValidationException::withMessages([
                'paid_amount' => 'The related chit installment no longer exists.',
            ]);
        }

        $remaining = round((float) $collection->amount, 2);
        if ($remaining <= 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Collection amount must be greater than zero.',
            ]);
        }

        $member = $installment->member;
        $installment->refresh();
        $installment->load(['member.shares', 'sharePayments']);

        $ownBalance = round((float) $installment->balance, 2);
        if ($ownBalance <= 0.01 || in_array($installment->status, ['paid', 'waived'], true)) {
            Log::info("Chit collection #{$collection->id} verified: installment month {$installment->month_number} is already settled.");
            return;
        }

        $collectedBy = $collection->agent?->user_id ?: Auth::id();
        $collecredByUser = User::find($collectedBy);
        $collecredByUserName = $collecredByUser?->name ?: 'Admin';

        $paymentService = app(\App\Services\ChitPaymentService::class);

        $ownerIds = $member->resolveOwnershipShares()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $preferred = (int) ($collection->client_id ?: $member->client_id ?: 0);
        if ($preferred > 0) {
            array_unshift($ownerIds, $preferred);
            $ownerIds = array_values(array_unique($ownerIds));
        }

        if (empty($ownerIds)) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Unable to resolve customer for payment.',
            ]);
        }

        $bankId = $bankAccountId;
        if (in_array($collection->payment_method, ['upi', 'bank_transfer'], true) && !$bankId) {
            $bankId = \App\Models\Account\BankAccount::where('is_active', true)->value('id');
        }

        $basePayload = [
            'payment_mode' => $collection->payment_method,
            'paid_date' => optional($collection->collected_at)->toDateString() ?? now()->toDateString(),
            'reference_no' => $collection->payment_reference,
            'remarks' => trim(($collection->remarks ?? '') . ' [Verified by admin]'),
            'single_seat_only' => true,
            'bypass_min_validation' => true,
            'internal_bank_account_id' => $bankId,
        ];

        $appliedTotal = 0.0;

        foreach ($ownerIds as $clientId) {
            if ($remaining <= 0.009) {
                break;
            }

            $openInstallments = Installment::with(['member.shares', 'sharePayments'])
                    ->where('member_id', $installment->member_id)
                    ->where('group_id', $installment->group_id)
                    ->whereNotIn('status', ['paid', 'waived'])
                ->orderBy('month_number', 'asc')
                ->get();

            if ($openInstallments->isEmpty()) {
                break;
            }

            $totalMemberOpen = round(
                $openInstallments->sum(fn (Installment $item) => $item->clientBalanceShare($clientId)),
                2
            );

            if ($totalMemberOpen <= 0.009) {
                continue;
            }

            $targetInstallment = $openInstallments->first(
                fn (Installment $item) => $item->clientBalanceShare($clientId) > 0.009
            ) ?? $openInstallments->first();

            $portion = round(min($remaining, $totalMemberOpen), 2);
            $outcome = $paymentService->collectInstallment($targetInstallment, array_merge($basePayload, [
                'paid_amount' => $portion,
                'payment_type' => $portion + 0.01 < $totalMemberOpen
                    ? 'partial'
                    : ($collection->payment_type ?: 'full'),
                'client_id' => $clientId,
            ]), $collectedBy);

            $posted = round((float) ($outcome['applied'] ?? $portion), 2);
            $remaining = round($remaining - $posted, 2);
            $appliedTotal = round($appliedTotal + $posted, 2);
        }

        if ($appliedTotal <= 0.009) {
            $freshInstallment = $installment->fresh();
            $seatSettled = $freshInstallment && (
                in_array($freshInstallment->status, ['paid', 'waived'], true)
                || round((float) $freshInstallment->balance, 2) <= 0.01
            );

            $hasAnyOpen = Installment::where('member_id', $installment->member_id)
                ->where('group_id', $installment->group_id)
                ->whereNotIn('status', ['paid', 'waived'])
                ->exists();

            if ($seatSettled || ! $hasAnyOpen) {
                Log::info("Chit collection #{$collection->id} verified: membership/installment is already settled.");
                return;
            }

            throw ValidationException::withMessages([
                'paid_amount' => 'No outstanding balance for this customer on this membership.',
            ]);
        }

        if ($remaining > 0.01) {
            Log::info("Chit collection #{$collection->id} verified with unused surplus ₹" . number_format($remaining, 2) . ' beyond current outstanding.');
        }
    }

    private function formatLoanEmiLabel(?EmiCollection $collection): string
    {
        $instalmentNo = $collection?->emi?->instalment_number;
        if ($instalmentNo) {
            return 'EMI #' . $instalmentNo;
        }

        return $collection?->emi_id ? ('#' . $collection->emi_id) : 'N/A';
    }

    private function applyChitCollectorFilter($query, string $collector): void
    {
        $agentIds = Agent::pluck('id');
        $agentUserIds = Agent::whereNotNull('user_id')->pluck('user_id');

        if ($collector === 'agent') {
            $query->where(function ($q) use ($agentIds, $agentUserIds) {
                $q->whereIn('collected_by', $agentIds)
                    ->orWhereIn('collected_by', $agentUserIds)
                    ->orWhereHas('pendingCollections');
            });
        } elseif ($collector === 'admin') {
            $query->whereDoesntHave('pendingCollections')
                ->where(function ($q) use ($agentIds, $agentUserIds) {
                    $q->whereNull('collected_by')
                        ->orWhere(function ($inner) use ($agentIds, $agentUserIds) {
                            $inner->whereNotIn('collected_by', $agentIds)
                                ->whereNotIn('collected_by', $agentUserIds);
                        });
                });
        }
    }

    /**
     * Chit payments an agent may see: everything on their own book except payments
     * another agent collected. Admin-collected payments stay visible, which is how
     * the loan collections tab already behaves.
     */
    private function excludeOtherAgentChitCollections($query, int $agentId, ?int $agentUserId = null): void
    {
        $ownCollectorIds = array_values(array_filter([$agentId, $agentUserId]));

        $otherCollectorIds = Agent::where('id', '!=', $agentId)
            ->get(['id', 'user_id'])
            ->flatMap(fn (Agent $agent) => [(int) $agent->id, $agent->user_id ? (int) $agent->user_id : null])
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

    private function getStatsData(Request $request = null)
    {
        $currentUser = Auth::user();
        $isAgent = $currentUser->hasRole('Agent');
        $currentAgentId = $isAgent ? optional($currentUser->agent)->id : null;

        $applyAgentCollectionScope = function ($query) use ($isAgent, $currentAgentId, $request) {
            if ($isAgent && $currentAgentId) {
                $this->applyLoanAgentScope($query, $currentAgentId);
                $query->where(function ($q) use ($currentAgentId) {
                    $q->whereNull('agent_id')->orWhere('agent_id', $currentAgentId);
                });
            } elseif ($request && $request->filled('agent_id')) {
                $this->applyLoanAgentScope($query, (int) $request->agent_id);
            }

            if ($request) {
                if ($request->has('start_date') && !empty($request->start_date)) {
                    $query->whereDate('collected_at', '>=', $request->start_date);
                }
                if ($request->has('end_date') && !empty($request->end_date)) {
                    $query->whereDate('collected_at', '<=', $request->end_date);
                }
            }
        };

        $agentCollectedQuery = EmiCollection::whereNotNull('agent_id')
            ->whereIn('status', ['verified', 'in_progress'])
            ->where('payment_method', '!=', 'payment_link');
        if ($isAgent && $currentAgentId) {
            $agentCollectedQuery->where('agent_id', $currentAgentId);
            $this->applyLoanAgentScope($agentCollectedQuery, $currentAgentId);
            if ($request) {
                if ($request->has('start_date') && !empty($request->start_date)) {
                    $agentCollectedQuery->whereDate('collected_at', '>=', $request->start_date);
                }
                if ($request->has('end_date') && !empty($request->end_date)) {
                    $agentCollectedQuery->whereDate('collected_at', '<=', $request->end_date);
                }
            }
        } else {
            $applyAgentCollectionScope($agentCollectedQuery);
        }

        $adminCollectedQuery = EmiCollection::whereNull('agent_id')
            ->whereIn('status', ['verified', 'in_progress'])
            ->where('payment_method', '!=', 'payment_link');
        if ($isAgent && $currentAgentId) {
            $this->applyLoanAgentScope($adminCollectedQuery, $currentAgentId);
            if ($request) {
                if ($request->has('start_date') && !empty($request->start_date)) {
                    $adminCollectedQuery->whereDate('collected_at', '>=', $request->start_date);
                }
                if ($request->has('end_date') && !empty($request->end_date)) {
                    $adminCollectedQuery->whereDate('collected_at', '<=', $request->end_date);
                }
            }
        } else {
            $applyAgentCollectionScope($adminCollectedQuery);
        }

        $paymentLinkQuery = EmiCollection::where('payment_method', 'payment_link')
            ->whereIn('status', ['verified', 'in_progress']);
        if ($isAgent && $currentAgentId) {
            $this->applyLoanAgentScope($paymentLinkQuery, $currentAgentId);
            if ($request) {
                if ($request->has('start_date') && !empty($request->start_date)) {
                    $paymentLinkQuery->whereDate('collected_at', '>=', $request->start_date);
                }
                if ($request->has('end_date') && !empty($request->end_date)) {
                    $paymentLinkQuery->whereDate('collected_at', '<=', $request->end_date);
                }
            }
        } else {
            $applyAgentCollectionScope($paymentLinkQuery);
        }

        $agentCollectedCount = $agentCollectedQuery->count();
        $agentCollectedAmount = $agentCollectedQuery->sum('amount');

        $adminCollectedCount = $adminCollectedQuery->count();
        $adminCollectedAmount = $adminCollectedQuery->sum('amount');

        $paymentLinkCount = $paymentLinkQuery->count();
        $paymentLinkAmount = $paymentLinkQuery->sum('amount');

        $chitCollectedQuery = Installment::where('paid_amount', '>', 0);
        if ($isAgent && $currentAgentId) {
            $this->applyChitAgentScope($chitCollectedQuery, $currentAgentId);
            $this->excludeOtherAgentChitCollections($chitCollectedQuery, $currentAgentId, (int) $currentUser->id);
            if ($request) {
                if ($request->has('start_date') && !empty($request->start_date)) {
                    $chitCollectedQuery->whereDate('paid_date', '>=', $request->start_date);
                }
                if ($request->has('end_date') && !empty($request->end_date)) {
                    $chitCollectedQuery->whereDate('paid_date', '<=', $request->end_date);
                }
            }
        } else {
            $chitCollectedQuery->whereHas('group', function ($gq) {
                $gq->whereIn('status', ['active', 'completed']);
            });

            if ($request && $request->filled('agent_id')) {
                $this->applyChitAgentScope($chitCollectedQuery, (int) $request->agent_id);
            }
            if ($request) {
                if ($request->has('start_date') && !empty($request->start_date)) {
                    $chitCollectedQuery->whereDate('paid_date', '>=', $request->start_date);
                }
                if ($request->has('end_date') && !empty($request->end_date)) {
                    $chitCollectedQuery->whereDate('paid_date', '<=', $request->end_date);
                }
            }
        }

        $chitCollectedCount = $chitCollectedQuery->count();
        $chitCollectedAmount = (float) $chitCollectedQuery->sum('paid_amount');

        // Include agent collections still awaiting verification so the collector sees their own entries.
        $pendingChitQuery = \App\Models\ChitCollection::where('status', 'in_progress');
        if ($isAgent && $currentAgentId) {
            $pendingChitQuery->where('agent_id', $currentAgentId);
        } elseif ($request && $request->filled('agent_id')) {
            $pendingChitQuery->where('agent_id', (int) $request->agent_id);
        }
        if ($request) {
            if ($request->filled('start_date')) {
                $pendingChitQuery->whereDate('collected_at', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $pendingChitQuery->whereDate('collected_at', '<=', $request->end_date);
            }
        }

        $chitCollectedCount += $pendingChitQuery->count();
        $chitCollectedAmount += (float) $pendingChitQuery->sum('amount');
        
        $pendingTasksCount = 0;
        if ($isAgent && $currentAgentId) {
            $pendingTasksCount = \App\Models\EmiAgentAssignment::where('agent_id', $currentAgentId)
                ->active()
                ->onActiveLoan()
                ->count();
        }

        return [
            'agentCollectedCount' => $agentCollectedCount,
            'agentCollectedAmount' => (float)$agentCollectedAmount,
            'adminCollectedCount' => $adminCollectedCount,
            'adminCollectedAmount' => (float)$adminCollectedAmount,
            'paymentLinkCount' => $paymentLinkCount,
            'paymentLinkAmount' => (float)$paymentLinkAmount,
            'chitCollectedCount' => $chitCollectedCount,
            'chitCollectedAmount' => $chitCollectedAmount,
            'pendingTasksCount' => $pendingTasksCount,
        ];
    }

    public function stats(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->getStatsData($request)
        ]);
    }

    public function index(Request $request)
    {
        $currentUser = Auth::user();
        $isAgent = $currentUser->hasRole('Agent');
        $currentAgentId = $isAgent ? optional($currentUser->agent)->id : null;

        $stats = $this->getStatsData($request);
        $agentCollectedCount = $stats['agentCollectedCount'];
        $agentCollectedAmount = $stats['agentCollectedAmount'];
        $adminCollectedCount = $stats['adminCollectedCount'];
        $adminCollectedAmount = $stats['adminCollectedAmount'];
        $paymentLinkCount = $stats['paymentLinkCount'];
        $paymentLinkAmount = $stats['paymentLinkAmount'];
        $chitCollectedCount = $stats['chitCollectedCount'];
        $chitCollectedAmount = $stats['chitCollectedAmount'];
        $pendingTasksCount = $stats['pendingTasksCount'];

        $agents = $isAgent && $currentAgentId
            ? Agent::where('id', $currentAgentId)->get()
            : Agent::where('status', 'active')->orderBy('agent_name')->get();

        $currentAgent = $isAgent && $currentAgentId
            ? $agents->first()
            : null;
        
        $myAssignments = collect();
        if ($isAgent && $currentAgentId) {
            $myAssignments = \App\Models\EmiAgentAssignment::with([
                'emi.loanAccount.client',
                'emi.loanAccount',
            ])
                ->where('agent_id', $currentAgentId)
                ->active()
                ->onActiveLoan()
                ->orderBy('assigned_at', 'desc')
                ->get();
        }

        $partialPaymentGlobal = app(PartialPaymentConfigService::class)->getGlobalSettings();
        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)->orderBy('account_name')->get();

        return view('admin.agents.agent-collections.agent-collection', compact(
            'agentCollectedCount',
            'agentCollectedAmount',
            'adminCollectedCount',
            'adminCollectedAmount',
            'paymentLinkCount',
            'paymentLinkAmount',
            'chitCollectedCount',
            'chitCollectedAmount',
            'agents',
            'currentAgent',
            'isAgent',
            'currentAgentId',
            'pendingTasksCount',
            'myAssignments',
            'partialPaymentGlobal',
            'bankAccounts'
        ));
    }

    public function list(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $columns = [
            0 => 'id',
            1 => 'client_name',
            2 => 'agent_id',
            3 => 'emi_id',
            4 => 'amount',
            5 => 'payment_method',
            6 => 'payment_type',
            7 => 'status',
            8 => 'collected_at',
        ];

        $query = EmiCollection::with(['agent', 'emi.loanAccount.client', 'verifiedBy']);
        
        // Filter by agent if current user is an agent
        $currentUser = Auth::user();
        $agentId = null;
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if ($agentId) {
                $this->applyLoanAgentScope($query, $agentId);
                $query->where(function ($q) use ($agentId) {
                    $q->whereNull('agent_id')->orWhere('agent_id', $agentId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            // Admin/Staff: all loan collections; optional agent filter
            if ($request->has('agent_id') && !empty($request->agent_id)) {
                $this->applyLoanAgentScope($query, (int) $request->agent_id);
            }
        }

        // Filter by date range (collected_at)
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('collected_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('collected_at', '<=', $request->end_date);
        }

        // Initialize total records based on visibility (agent filter applied)
        $totalData = $query->count();
        $totalFiltered = $totalData;

        // Handle status filter
        if ($request->has('status') && !empty($request->status)) {
            $statusVal = $request->status;
            if ($statusVal === 'pending') {
                $query->where('status', 'in_progress');
            } else {
                $query->where('status', $statusVal);
            }
        }

        // Handle collector filter
        if ($request->has('collector') && !empty($request->collector)) {
            $collectorVal = $request->collector;
            if ($collectorVal === 'agent') {
                $query->whereNotNull('agent_id');
            } elseif ($collectorVal === 'admin') {
                $query->whereNull('agent_id');
            }
        }

        // Handle method filter
        if ($request->has('method') && !empty($request->method)) {
            $methodVal = $request->method;
            if ($methodVal === 'payment_link') {
                $query->where('payment_method', 'payment_link');
            } elseif (str_starts_with($methodVal, 'agent_')) {
                $actualMethod = str_replace('agent_', '', $methodVal);
                $query->whereNotNull('agent_id')->where('payment_method', $actualMethod);
            } elseif (str_starts_with($methodVal, 'admin_')) {
                $actualMethod = str_replace('admin_', '', $methodVal);
                $query->whereNull('agent_id')->where('payment_method', $actualMethod);
            }
        }

        // Log agent filter application
        Log::info('Agent collections filtered by assigned client', ['agent_id' => $agentId ?? null]);
        $limit = $request->input('length', 20);
        $start = $request->input('start', 0);
        
        $orderIndex = $request->input('order.0.column', 0);
        $dir = $request->input('order.0.dir', 'desc');

        // Update totalFiltered after status filter but before search
        $totalFiltered = $query->count();

        // Search handling
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                  ->orWhere('amount', 'LIKE', "%{$search}%")
                  ->orWhereHas('agent', function ($q) use ($search) {
                      $q->where('agent_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('emi.loanAccount.client', function ($q) use ($search) {
                      $q->where('client_name', 'LIKE', "%{$search}%");
                  });
            });

            $totalFiltered = $query->count();
            // Log filtered count
            Log::info('Agent collections search applied', ['search' => $search, 'filtered_count' => $totalFiltered]);
        }

        // Apply ordering to collections query
        $collectionsQuery = clone $query;
        if ($orderIndex == 1) {
            // Join clients to sort by client name
            $collectionsQuery->join('emis', 'emi_collections.emi_id', '=', 'emis.id')
                ->join('loan_accounts', 'emis.loan_account_id', '=', 'loan_accounts.id')
                ->join('clients', 'loan_accounts.client_id', '=', 'clients.id')
                ->orderBy('clients.client_name', $dir)
                ->select('emi_collections.*');
        } elseif ($orderIndex == 2) {
            // Left join agents to sort by agent name
            $collectionsQuery->leftJoin('agents', 'emi_collections.agent_id', '=', 'agents.id')
                ->orderBy('agents.agent_name', $dir)
                ->select('emi_collections.*');
        } else {
            $orderColumn = $columns[$orderIndex] ?? 'id';
            $collectionsQuery->orderBy($orderColumn, $dir);
        }

        $allCollections = $collectionsQuery->get();
        $groupedRows = BulkPaymentGroup::groupCollections($allCollections)->map(function (array $group) {
            $collection = $group['lead'];
            $items = $group['items'];
            $isBulk = $group['is_bulk'];

                $clientName = $collection->emi && $collection->emi->loanAccount && $collection->emi->loanAccount->client
                    ? $collection->emi->loanAccount->client->client_name
                    : 'N/A';
                
            return array_merge([
                'id' => $collection->getRouteKey(),
                'record_id' => $collection->id,
                    'client_name' => $clientName,
                'emi_id' => $isBulk
                    ? BulkPaymentGroup::emiSplitLabel($items)
                    : $this->formatLoanEmiLabel($collection),
                'emi_numbers' => $isBulk
                    ? BulkPaymentGroup::emiNumbers($items)
                    : array_values(array_filter([(int) ($collection->emi?->instalment_number ?? 0)])),
                    'real_emi_id' => $collection->emi_id,
                'amount' => round((float) $items->sum('amount'), 2),
                    'payment_method' => $collection->payment_method,
                'payment_type' => $isBulk ? BulkPaymentGroup::combinedPaymentType($items) : $collection->payment_type,
                'status' => $isBulk ? BulkPaymentGroup::combinedStatus($items) : $collection->status,
                    'collected_at' => $collection->collected_at ? $collection->collected_at->toIso8601String() : '',
                    'created_at' => $collection->created_at ? $collection->created_at->toIso8601String() : '',
                'is_grouped' => $isBulk,
                'is_bulk' => $isBulk,
                'emi_count' => $items->count(),
                'emi_split' => $isBulk
                    ? BulkPaymentGroup::emiSplitLabel($items)
                    : $this->formatLoanEmiLabel($collection),
                'emi_splits' => $isBulk ? BulkPaymentGroup::emiSplits($items) : [],
                'group_id' => BulkPaymentGroup::extractExplicitKey($collection->remarks, $collection->payment_reference),
                    'action' => '',
                ], $this->collectionCollectorPayload($collection));
        })->values();

        $totalGrouped = $groupedRows->count();
        $limit = (int) $limit;
        $start = (int) $start;
        if ($limit < 0) {
            $paged = $groupedRows;
            } else {
            $paged = $groupedRows->slice($start, $limit)->values();
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalGrouped),
            'data' => $paged,
        ]);
    }

    private function formatChitCollectionListRow(Installment $installment, $currentUser): array
    {
        $groupCode = $installment->group?->group_code ?? 'N/A';
        $paymentMode = $installment->payment_mode === 'cash' ? 'in_hand' : ($installment->payment_mode ?? 'cash');
        $pending = $installment->pendingCollections->sortByDesc('collected_at')->first();

        $groupUrl = $installment->group_id
            ? route('chit.groups.show', $installment->group_id)
            : route('chit.installments.index');

        $row = array_merge([
            'id' => $installment->id,
            'client_name' => $installment->member?->client?->client_name ?? 'N/A',
            'emi_id' => "{$groupCode} - Inst #{$installment->month_number}",
            'real_emi_id' => $installment->id,
            'group_id' => $installment->group_id,
            'amount' => BulkPaymentGroup::installmentBulkAmount($installment),
            'payment_method' => $paymentMode,
            'payment_type' => $installment->status === 'partial' ? 'partial' : 'full',
            'status' => $installment->status === 'paid' ? 'verified' : $installment->status,
            'collected_at' => $installment->paid_date ? $installment->paid_date->toIso8601String() : '',
            'created_at' => $installment->updated_at ? $installment->updated_at->toIso8601String() : '',
            'collection_type' => 'chit',
            'view_url' => $groupUrl,
            'chit_collection_id' => null,
            'is_bulk' => false,
                    'is_grouped' => false,
                    'emi_count' => 1,
            'emi_split' => "{$groupCode} - Inst #{$installment->month_number}",
                    'action' => '',
        ], $this->chitCollectorPayload($installment));

        if ($pending) {
            $mode = $pending->payment_method === 'cash' ? 'in_hand' : $pending->payment_method;

            $row['amount'] = (float) $pending->amount;
            $row['payment_method'] = $mode;
            $row['payment_type'] = $pending->payment_type ?: 'full';
            $row['status'] = 'in_progress';
            $row['collected_at'] = $pending->collected_at ? $pending->collected_at->toIso8601String() : '';
            $row['chit_collection_id'] = 'chit_' . $pending->id;

            if ($pending->client_id && $pending->relationLoaded('client') === false) {
                $pending->load('client');
            }
            if ($pending->client?->client_name) {
                $row['client_name'] = $pending->client->client_name;
            }

            $collectorAgent = $pending->relationLoaded('agent') ? $pending->agent : Agent::find($pending->agent_id);
            if (!$collectorAgent && $pending->agent_id) {
                $collectorAgent = Agent::where('user_id', $pending->agent_id)->first();
            }

            if ($collectorAgent) {
                $row['agent_name'] = $collectorAgent->agent_name;
                $row['collector_type'] = 'agent';
            } elseif ($pending->agent_id || $currentUser->hasRole('Agent')) {
                $agentName = optional($currentUser->agent)->agent_name ?? 'Agent';
                $row['agent_name'] = $agentName;
                $row['collector_type'] = 'agent';
            } else {
                $row['agent_name'] = 'Admin';
                $row['collector_type'] = 'admin';
            }
        }

        return $row;
    }

    public function chitList(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $columns = [
            0 => 'id',
            1 => 'client_name',
            2 => 'collected_by',
            3 => 'group_code',
            4 => 'paid_amount',
            5 => 'payment_mode',
            6 => 'payment_type',
            7 => 'status',
            8 => 'paid_date',
        ];

        $query = Installment::with([
                'member.client',
                'member.shares.client',
                'group',
                'collectedBy.agent',
                'pendingCollections.agent',
                'pendingCollections.client',
                'collections',
            ]);

        $currentUser = Auth::user();
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if ($agentId) {
                $this->applyChitAgentScope($query, $agentId);
                $this->excludeOtherAgentChitCollections($query, $agentId, (int) $currentUser->id);

                // Only the submitting agent should see their own unverified entries.
                $query->where(function ($q) use ($agentId) {
                    $q->where('paid_amount', '>', 0)
                        ->orWhereHas('pendingCollections', function ($pq) use ($agentId) {
                            $pq->where('agent_id', $agentId);
                        });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query->where(function ($q) {
                $q->where('paid_amount', '>', 0)
                    ->orWhereHas('pendingCollections');
            });

            // Admin/Staff: all collections from active (activated) chit groups
            $query->whereHas('group', function ($gq) {
                $gq->whereIn('status', ['active', 'completed']);
            });

            if ($request->filled('agent_id')) {
                $this->applyChitAgentScope($query, (int) $request->agent_id);
            }
        }

        // A pending collection has not been applied to the installment yet, so it has no
        // paid_date. Match those rows on the date the agent recorded them instead.
        if ($request->filled('start_date') || $request->filled('end_date')) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            $query->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($paid) use ($startDate, $endDate) {
                    if ($startDate) {
                        $paid->whereDate('paid_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $paid->whereDate('paid_date', '<=', $endDate);
                    }
                })->orWhereHas('pendingCollections', function ($pq) use ($startDate, $endDate) {
                    if ($startDate) {
                        $pq->whereDate('collected_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $pq->whereDate('collected_at', '<=', $endDate);
                    }
                });
            });
        }

        if ($request->filled('collector')) {
            $this->applyChitCollectorFilter($query, $request->collector);
        }

        if ($request->filled('status')) {
            $statusVal = $request->status;
            if ($statusVal === 'pending') {
                $query->where(function ($q) {
                    $q->whereHas('pendingCollections')
                        ->orWhere('status', 'partial');
                });
            } elseif ($statusVal === 'verified') {
                $query->where('status', 'paid')->whereDoesntHave('pendingCollections');
            } elseif ($statusVal === 'rejected') {
                $query->whereHas('collections', fn ($cq) => $cq->where('status', 'rejected'));
            }
        }

        if ($request->filled('method')) {
            $methodVal = $request->method;
            if ($methodVal === 'payment_link') {
                $query->whereRaw('1 = 0');
            } elseif (str_starts_with($methodVal, 'agent_') || str_starts_with($methodVal, 'admin_')) {
                $actualMethod = str_replace(['agent_', 'admin_'], '', $methodVal);
                $map = ['in_hand' => 'cash'];
                $actualMethod = $map[$actualMethod] ?? $actualMethod;
                $query->where('payment_mode', $actualMethod);
                if (str_starts_with($methodVal, 'agent_')) {
                    $this->applyChitCollectorFilter($query, 'agent');
                } else {
                    $this->applyChitCollectorFilter($query, 'admin');
                }
            }
        }

        $totalData = (clone $query)->count();
        $totalFiltered = $totalData;

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('paid_amount', 'LIKE', "%{$search}%")
                    ->orWhere('reference_no', 'LIKE', "%{$search}%")
                    ->orWhereHas('member.client', function ($cq) use ($search) {
                        $cq->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('group', function ($gq) use ($search) {
                        $gq->where('group_code', 'LIKE', "%{$search}%");
                    });
            });
            $totalFiltered = $query->count();
        }

        $orderIndex = (int) $request->input('order.0.column', 8);
        $dir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderIndex] ?? 'paid_date';

        if ($orderColumn === 'client_name') {
            $query->join('group_members', 'installments.member_id', '=', 'group_members.id')
                ->join('clients', 'group_members.client_id', '=', 'clients.id')
                ->orderBy('clients.client_name', $dir)
                ->select('installments.*');
        } elseif ($orderColumn === 'group_code') {
            $query->join('chit_groups', 'installments.group_id', '=', 'chit_groups.id')
                ->orderBy('chit_groups.group_code', $dir)
                ->select('installments.*');
        } else {
            $query->orderBy($orderColumn, $dir);
        }

        $limit = (int) $request->input('length', 20);
        $start = (int) $request->input('start', 0);
        $allInstallments = $query->get();

        $groupedRows = BulkPaymentGroup::groupInstallments($allInstallments)->map(function (array $group) use ($currentUser) {
            $lead = $group['lead'];
            $items = $group['items'];
            $row = $this->formatChitCollectionListRow($lead, $currentUser);

            if (!$group['is_bulk']) {
                return $row;
            }

            $itemRows = $items->map(fn (Installment $installment) => $this->formatChitCollectionListRow($installment, $currentUser));
            $statuses = $itemRows->pluck('status');
            $types = $itemRows->pluck('payment_type');

            $row['amount'] = BulkPaymentGroup::chitInstallmentTotal($items);
            $row['is_bulk'] = true;
            $row['is_grouped'] = true;
            $row['emi_count'] = $items->count();
            $row['emi_id'] = BulkPaymentGroup::chitInstallmentSplitLabel($items);
            $row['emi_split'] = $row['emi_id'];
            $row['emi_splits'] = BulkPaymentGroup::chitInstallmentSplits($items);
            $row['payment_type'] = $types->contains('partial') ? 'partial' : ($types->first() ?? 'full');
            if ($statuses->contains('in_progress') || $statuses->contains('pending')) {
                $row['status'] = 'in_progress';
            } elseif ($statuses->contains('rejected')) {
                $row['status'] = 'rejected';
            }

            $pendingIds = $itemRows->pluck('chit_collection_id')->filter()->values();
            if ($pendingIds->isNotEmpty()) {
                $row['chit_collection_id'] = $pendingIds->first();
            }

            return $row;
        })->values();

        $totalGrouped = $groupedRows->count();
        if ($limit < 0) {
            $paged = $groupedRows;
                } else {
            $paged = $groupedRows->slice($start, $limit)->values();
            }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalGrouped),
            'data' => $paged,
        ]);
    }

    public function show($id)
    {
        $rawId = (string) $id;
        $isChit = str_starts_with($rawId, 'chit_');
        $cleanId = str_replace('chit_', '', $rawId);

        // Chit verify/view IDs must never be resolved as loan EMI collections
        // (numeric overlap would open the wrong loan or bounce to the group page).
        if ($isChit) {
            $chitCollection = \App\Models\ChitCollection::with(['agent', 'client', 'member.group', 'installment'])->find($cleanId);
            if ($chitCollection && $chitCollection->member?->group_id) {
                return redirect()->route('chit.groups.show', $chitCollection->member->group_id);
            }

            return redirect()->route('agent-collections')
                ->with('error', 'Chit collection not found.');
        }

        $realId = \App\Support\HashId::decode($cleanId);
        $realId = is_array($realId) ? ($realId[0] ?? $cleanId) : ($realId ?? $cleanId);

        $collection = EmiCollection::with(['agent', 'emi.loanAccount.client', 'verifiedBy'])->find($realId);

        if (!$collection) {
            return redirect()->route('agent-collections')
                ->with('error', 'Collection not found.');
        }
        
        $relatedCollections = BulkPaymentGroup::findSiblings($collection);
        $isMultiEmi = $relatedCollections->count() > 1;
        $bulkTotalAmount = round((float) $relatedCollections->sum('amount'), 2);
        $emiSplitLabel = $isMultiEmi
            ? BulkPaymentGroup::emiSplitLabel($relatedCollections)
            : $this->formatLoanEmiLabel($collection);
        $emiNumbers = $isMultiEmi
            ? BulkPaymentGroup::emiNumbers($relatedCollections)
            : array_values(array_filter([(int) ($collection->emi?->instalment_number ?? 0)]));
        
        $bankAccounts = \App\Models\Account\BankAccount::where('created_by', creatorId())
            ->where('is_active', true)
            ->get();
        
        return view('admin.agents.agent-collections.view', compact(
            'collection',
            'relatedCollections',
            'isMultiEmi',
            'bulkTotalAmount',
            'emiSplitLabel',
            'emiNumbers',
            'bankAccounts'
        ));
    }

    /**
     * Approve or reject an agent-collected chit installment payment.
     * The payment is only applied to the installment once an admin verifies it.
     */
    private function verifyChitCollection(Request $request, int $collectionId)
    {
        if (Auth::user()->hasRole('Agent')) {
            return response()->json([
                'success' => false,
                'message' => 'Agents cannot verify collections.',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:verified,rejected',
            'remarks' => 'nullable|string',
            'internal_bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        DB::beginTransaction();
        try {
            $collection = \App\Models\ChitCollection::with(['installment.member.shares', 'installment.sharePayments', 'agent'])
                ->lockForUpdate()
                ->findOrFail($collectionId);

            if (! in_array($collection->status, ['in_progress', 'pending'], true)) {
                throw new \Exception('This collection has already been finalized with status: ' . ucfirst($collection->status) . '.');
            }

            $bankAccountId = $request->filled('internal_bank_account_id')
                ? (int) $request->internal_bank_account_id
                : null;

            if ($request->status === 'rejected') {
                $rejectedCount = $this->rejectChitCollectionGroup($collection, $request->remarks);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => $rejectedCount > 1
                        ? "Chit collection rejected successfully ({$rejectedCount} installments)."
                        : 'Chit collection rejected successfully',
                ]);
            }

            $installment = $collection->installment;
            if (!$installment) {
                throw new \Exception('The related chit installment no longer exists.');
            }

            $processed = [];
            $groupResult = $this->verifyChitCollectionGroup($collection, $request->remarks, $bankAccountId, $processed);
            $count = (int) ($groupResult['count'] ?? 0);
            if ($count === 0) {
                throw new \Exception('This collection has already been finalized or has no pending installments.');
            }

            DB::commit();

            $this->queueChitApprovalNotifications($groupResult['applied'] ?? collect());

            return response()->json([
                'success' => true,
                'message' => $count > 1
                    ? "Chit collection verified successfully ({$count} installments)."
                    : 'Chit collection verified successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Chit collection verification failed', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function verify(Request $request, $id = null)
    {
        if (Auth::user()->hasRole('Agent') && ! Auth::user()->hasAnyRole(['Admin', 'Staff', 'admin', 'staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Agents cannot verify collections.',
            ], 403);
        }

        $status = strtolower(trim((string) $request->input('status')));
        $status = match ($status) {
            'approve', 'approved', 'verify', 'verified' => 'verified',
            'reject', 'rejected' => 'rejected',
            default => $status,
        };
        $request->merge(['status' => $status]);

        try {
            $request->validate([
                'status' => 'required|in:verified,rejected',
                'remarks' => 'nullable|string',
                'internal_bank_account_id' => 'nullable|exists:bank_accounts,id',
                'collection_id' => 'nullable',
                'collection_type' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
            ], 422);
        }

        $rawId = $request->filled('collection_id') ? $request->input('collection_id') : $id;
        $isChit = str_starts_with((string) $request->input('collection_type'), 'chit')
            || str_starts_with((string) $rawId, 'chit_')
            || str_starts_with((string) $id, 'chit_');

        if ($isChit) {
            $chitRaw = str_replace('chit_', '', (string) $rawId);
            $chitId = $this->resolveCollectionRecordId($chitRaw)
                ?? (ctype_digit((string) $chitRaw) ? (int) $chitRaw : null);

            if (! $chitId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Collection not found.',
                ], 404);
            }

            return $this->verifyChitCollection($request, (int) $chitId);
        }

        $realId = $this->resolveCollectionRecordId($rawId)
            ?? $this->resolveCollectionRecordId($id);

        $collection = $realId ? EmiCollection::with('emi.loanAccount')->find($realId) : null;
        if (! $collection) {
            $chitCollection = $realId ? \App\Models\ChitCollection::find($realId) : null;
            if ($chitCollection) {
                return $this->verifyChitCollection($request, (int) $chitCollection->id);
            }

            return response()->json([
                'success' => false,
                'message' => 'Collection not found.',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $collection = EmiCollection::with('emi.loanAccount')
                ->lockForUpdate()
                ->findOrFail($realId);

            if (! in_array($collection->status, ['in_progress', 'pending'], true) && $request->status === 'rejected') {
                throw new \Exception(
                    'This collection has already been finalized with status: ' . ucfirst($collection->status) . '.'
                );
            }

            $creditBankAccountId = $request->internal_bank_account_id ?: $collection->bank_account_id;
            $newRemarks = $request->remarks ?? '';
            
            if ($request->status === 'verified') {
                $processed = [];
                $groupResult = $this->verifyLoanCollectionGroup(
                    $collection,
                    $newRemarks,
                    $creditBankAccountId ? (int) $creditBankAccountId : null,
                    $processed
                );
                $verifiedCount = (int) ($groupResult['count'] ?? 0);
                $this->queueEmiApprovalNotifications($groupResult['applied'] ?? collect());
                $collection->refresh();

                if ($verifiedCount === 0 && in_array($collection->status, ['verified', 'paid'], true)) {
                    DB::commit();

                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Collection already verified',
                        ]);
                    }

                    return redirect()->route('agent-collections.show', $collection->id)
                        ->with('success', 'Collection already verified.');
                }

                if ($verifiedCount === 0) {
                    throw new \Exception(
                        'This collection has already been finalized with status: ' . ucfirst((string) $collection->status) . '.'
                    );
                }
            } else {
                Log::info('In-hand collection rejected by admin', [
                    'collection_id' => $collection->id,
                    'emi_id' => $collection->emi_id,
                    'remarks' => $request->remarks,
                ]);

                $this->rejectLoanCollectionGroup($collection, $newRemarks);
            }

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Collection ' . ($request->status === 'verified' ? 'verified' : 'rejected') . ' successfully'
                ]);
            }

            return redirect()->route('agent-collections.show', $collection->id)
                ->with('success', 'Collection ' . ($request->status === 'verified' ? 'approved' : 'rejected') . ' successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Admin verification failed', [
                'collection_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification failed: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('agent-collections.show', $id)
                ->with('error', 'Verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Repay a rejected collection — Admin only
     * Re-processes the rejected EmiCollection via LoanPaymentService and marks it verified.
     * The reprocess amount is capped to the EMI's actual pending balance to prevent overpayment.
     */
    public function repay(Request $request, $id)
    {
        if (!Auth::user()->hasRole('Admin') && !Auth::user()->hasRole('Staff') && !Auth::user()->hasRole('Agent')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $realId = \App\Support\HashId::decode((string) $id);
        $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);

        $collection = EmiCollection::with('emi.loanAccount')->findOrFail($realId);

        if (Auth::user()->hasRole('Agent')) {
            $agent = Auth::user()->agent;
            if (!$agent || $collection->agent_id !== $agent->id) {
                return response()->json(['success' => false, 'message' => 'You can only re-pay your own collections.'], 403);
            }
        }

        if ($collection->status !== 'rejected') {
            return response()->json(['success' => false, 'message' => 'Only rejected collections can be repaid.'], 400);
        }

        DB::beginTransaction();
        try {
            $emi         = $collection->emi;
            $loanAccount = $emi->loanAccount;

            // Calculate the actual EMI pending amount dynamically
            // Sum only verified/paid collections (exclude rejected and the current rejected one)
            $verifiedPaid = EmiCollection::where('emi_id', $emi->id)
                ->where('status', 'verified')
                ->sum('amount');

            $emiTotalDue = (float) $emi->total_amount + (float) $emi->penalty_amount;
            $emiPending  = max(0, $emiTotalDue - $verifiedPaid);

            // Cap the reprocess amount to the EMI's actual pending balance
            $rejectedAmount    = (float) $collection->amount;
            $reprocessAmount   = min($rejectedAmount, $emiPending);

            if ($reprocessAmount <= 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This EMI has no remaining balance to reprocess. The EMI is already fully paid.'
                ], 400);
            }

            $newRemarks = $request->input('remarks');
            $existingRemarks = $collection->remarks ?? '';
            $combinedRemarks = trim($existingRemarks . ($newRemarks ? "\n" . $newRemarks : ''));
            $systemRemarks = "[Re-verified after rejection. Original rejected: ₹" . number_format($rejectedAmount, 2) . ", Reprocessed: ₹" . number_format($reprocessAmount, 2) . "]";
            $finalRemarks = trim($combinedRemarks . "\n" . $systemRemarks);

            // Re-mark the collection as verified but update the amount to the capped value
            $collection->update([
                'status'      => 'verified',
                'amount'      => $reprocessAmount,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'remarks'     => $finalRemarks,
            ]);

            // Process the payment via LoanPaymentService with the capped amount
            $paymentService = app(\App\Services\LoanPaymentService::class);
            $result = $paymentService->processPayment(
                $emi->id,
                ($collection->payment_type === 'principal') ? 0 : $reprocessAmount,
                $collection->collected_at->format('Y-m-d'),
                $collection->payment_method,
                $collection->payment_reference,
                'Admin repaid after rejection (capped to EMI balance)',
                true, // skipHistory — collection record already exists
                ($collection->payment_type === 'principal') ? $reprocessAmount : 0
            );

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            // Sync Kandhuvatti details into remarks if applicable
            $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
            if ($isKandhuvatti && isset($result['remarks'])) {
                $calcRemarks = $result['remarks'];
                $finalRemarks = trim($finalRemarks . ' | ' . $calcRemarks);
                $collection->update(['remarks' => $finalRemarks]);
            }

            // Resolve agent assignment
            \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                ->where('agent_id', $collection->agent_id)
                ->update(['status' => 'resolved', 'resolved_at' => now()]);

            // Sync totals
            $paymentService->syncEmiBalances($loanAccount->id);
            $paymentService->syncLoanTotals($loanAccount->id);

            DB::commit();

            $message = 'Collection re-processed and verified for ₹' . number_format($reprocessAmount, 2) . '.';
            if ($reprocessAmount < $rejectedAmount) {
                $message .= ' (Original rejected amount was ₹' . number_format($rejectedAmount, 2) . ', only ₹' . number_format($reprocessAmount, 2) . ' applied to EMI balance.)';
            }

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->route('agent-collections.show', $collection->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Collection repay failed', ['collection_id' => $id, 'error' => $e->getMessage()]);

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Repay failed: ' . $e->getMessage()], 500);
            }

            return redirect()->route('agent-collections.show', $id)
                ->with('error', 'Repay failed: ' . $e->getMessage());
        }
    }

    private function resolveCollectionRecordId(mixed $rawId): ?int
    {
        $value = trim((string) $rawId);
        if ($value === '' || strcasecmp($value, 'undefined') === 0 || strcasecmp($value, 'null') === 0) {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $decoded = \App\Support\HashId::decode($value);
        if ($decoded !== null && $decoded > 0) {
            return $decoded;
        }

        return null;
    }

    /**
     * Apply an in-progress loan collection onto its EMI, then mark the collection verified.
     *
     * @return string verified|already_verified
     */
    private function applyVerifiedLoanCollection(
        EmiCollection $collection,
        ?string $extraRemarks = null,
        ?int $bankAccountId = null,
        bool $skipCashbook = false,
        bool $skipSync = false
    ): string {
        $collection->loadMissing(['emi.loanAccount', 'agent']);

        $alreadyVerified = in_array($collection->status, ['verified', 'paid'], true);
        if ($alreadyVerified) {
            $emi = $collection->emi;
            $stillDue = $emi
                && in_array($emi->status, ['pending', 'overdue', 'partial'], true)
                && (float) ($emi->pending_amount ?? 0) > 0.01;
            if (! $stillDue) {
                return 'already_verified';
            }
        } elseif (! in_array($collection->status, ['in_progress', 'pending'], true)) {
            throw new \Exception('This collection has already been finalized with status: ' . ucfirst((string) $collection->status) . '.');
        }

        $emi = $collection->emi;
        $loanAccount = $emi?->loanAccount;
        if (! $emi || ! $loanAccount) {
            throw new \Exception('The related EMI or loan account no longer exists.');
        }

        $isPrincipalCollection = ($collection->payment_type === 'principal');
        $emiAlreadyPaid = in_array($emi->status, ['paid', 'closed'], true)
            || (
                (float) ($emi->pending_amount ?? 0) <= 0.01
                && (float) ($emi->paid_amount ?? 0) > 0.01
            );
        if ($emiAlreadyPaid && ! $isPrincipalCollection && in_array($collection->status, ['in_progress', 'pending'], true)) {
            $collection->update([
                'status' => 'verified',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'remarks' => trim(($collection->remarks ?? '') . ($extraRemarks ? "\n" . $extraRemarks : '')),
                'bank_account_id' => ($bankAccountId ?: $collection->bank_account_id) ?: $collection->bank_account_id,
            ]);

            return 'verified';
        }

        $combinedRemarks = trim(($collection->remarks ?? '') . ($extraRemarks ? "\n" . $extraRemarks : ''));
        $creditBankAccountId = $bankAccountId ?: $collection->bank_account_id;

        $paymentService = app(\App\Services\LoanPaymentService::class);
        $previousSync = \App\Services\LoanPaymentService::$suppressBalanceSync;
        \App\Services\LoanPaymentService::$suppressBalanceSync = $skipSync || $previousSync;
        \App\Services\LoanPaymentService::$suppressPaymentNotifications = true;

        try {
            $result = $paymentService->processPayment(
                $emi->id,
                $isPrincipalCollection ? 0 : $collection->amount,
                $collection->collected_at ? $collection->collected_at->format('Y-m-d') : now()->toDateString(),
                $collection->payment_method,
                $collection->payment_reference ?: ('Collection ID: ' . $collection->id),
                'Verified by admin' . ($extraRemarks ? ': ' . $extraRemarks : ''),
                true, // skipHistory — collection row already exists
                $isPrincipalCollection ? (float) $collection->amount : 0,
                true, // bypassPriorCheck — bulk groups are verified together
                $creditBankAccountId,
                $collection->agent_id,
                $skipCashbook
            );
        } finally {
            \App\Services\LoanPaymentService::$suppressBalanceSync = $previousSync;
        }

        if (empty($result['success'])) {
            throw new \Exception($result['message'] ?? 'Payment could not be applied.');
        }

        if (($loanAccount->loan_mode === 'interest_only') && ! empty($result['remarks'])) {
            $combinedRemarks = trim($combinedRemarks . ($combinedRemarks ? ' | ' : '') . $result['remarks']);
        }

        $collection->update([
            'status' => 'verified',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'remarks' => $combinedRemarks,
            'bank_account_id' => $creditBankAccountId ?: $collection->bank_account_id,
        ]);

        \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
            ->where('agent_id', $collection->agent_id)
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'remarks' => trim(($emi->remarks ?? '') . ' [Resolved via admin verification]'),
            ]);

        if (! $skipSync) {
            $paymentService->syncEmiBalances($loanAccount->id);
            $paymentService->syncLoanTotals($loanAccount->id);
        }

        return 'verified';
    }

    /**
     * Verify a loan collection and all bulk siblings.
     *
     * @param  array<int, bool>  $processedIds
     * @return array{count: int, applied: \Illuminate\Support\Collection}
     */
    private function verifyLoanCollectionGroup(
        EmiCollection $collection,
        ?string $remarks,
        ?int $bankAccountId,
        array &$processedIds
    ): array {
        @set_time_limit(180);

        $siblings = BulkPaymentGroup::findSiblings($collection)
            ->sortBy(function (EmiCollection $row) {
                $instalment = (int) ($row->emi?->instalment_number ?? $row->id);
                $principalLast = $row->payment_type === 'principal' ? 1 : 0;
                return sprintf('%010d-%d-%010d', $instalment, $principalLast, (int) $row->id);
            })
            ->values();

        $toApply = $siblings->filter(function (EmiCollection $item) use ($processedIds) {
            return ! isset($processedIds[$item->id]) && $item->status !== 'rejected';
        })->values();

        if ($toApply->isEmpty()) {
            return ['count' => 0, 'applied' => collect()];
        }

        $isBulk = $toApply->count() > 1;
        $paymentService = app(\App\Services\LoanPaymentService::class);
        $previousNotify = \App\Services\LoanPaymentService::$suppressPaymentNotifications;
        \App\Services\LoanPaymentService::$suppressPaymentNotifications = true;

        $applied = collect();
        $verifiedCount = 0;

        try {
            foreach ($toApply as $item) {
                $processedIds[$item->id] = true;
                $outcome = $this->applyVerifiedLoanCollection(
                    $item,
                    $remarks,
                    $bankAccountId,
                    $isBulk,
                    $isBulk
                );
                if ($outcome === 'verified') {
                    $verifiedCount++;
                    $applied->push($item->fresh(['emi.loanAccount.client', 'agent']));
                }
            }

            if ($isBulk && $applied->isNotEmpty()) {
                $lead = $applied->first();
                $loanAccount = $lead?->emi?->loanAccount;
                $total = round((float) $applied->sum('amount'), 2);
                $emiNumbers = $applied
                    ->map(fn (EmiCollection $row) => (int) ($row->emi?->instalment_number ?? 0))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if ($loanAccount && $total > 0.009) {
                    $loanAccount->loadMissing('client');
                    $collectorName = $lead->agent?->user?->name
                        ?? $lead->agent?->agent_name
                        ?? null;
                    $paymentService->recordLoanCollectionInCashbook(
                        $lead->bank_account_id,
                        (string) $lead->payment_method,
                        $total,
                        $lead->payment_reference ?: ('Collection ID: ' . $lead->id),
                        \App\Services\Account\AccountingTags::loanIcDescription(
                            (string) ($loanAccount->customer_loan_account_number ?? $loanAccount->account_number ?? 'N/A'),
                            (string) ($loanAccount->client?->client_name ?? 'Client'),
                            $emiNumbers,
                            Auth::id(),
                            $collectorName
                        ),
                        $lead->collected_at ? $lead->collected_at->format('Y-m-d') : now()->toDateString()
                    );
                    $paymentService->syncEmiBalances($loanAccount->id);
                    $paymentService->syncLoanTotals($loanAccount->id);
                }
            }
        } finally {
            \App\Services\LoanPaymentService::$suppressPaymentNotifications = $previousNotify;
        }

        return ['count' => $verifiedCount, 'applied' => $applied];
    }

    /**
     * Reject a loan collection and pending bulk siblings so Reject matches Approve.
     */
    private function rejectLoanCollectionGroup(EmiCollection $collection, ?string $remarks): int
    {
        $siblings = BulkPaymentGroup::findSiblings($collection);
        $count = 0;

        foreach ($siblings as $item) {
            if (! in_array($item->status, ['in_progress', 'pending'], true)) {
                continue;
            }

            $item->update([
                'status' => 'rejected',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'remarks' => trim(($item->remarks ?? '') . ($remarks ? "\n" . $remarks : '')),
                'rejected_reason' => $remarks,
            ]);

            \App\Models\EmiAgentAssignment::where('emi_id', $item->emi_id)
                ->where('agent_id', $item->agent_id)
                ->update([
                    'status' => 'assigned',
                    'resolved_at' => null,
                ]);

            $count++;
        }

        return $count;
    }

    /**
     * Reject a chit collection and pending bulk siblings.
     */
    private function rejectChitCollectionGroup(ChitCollection $collection, ?string $remarks): int
    {
        $siblings = BulkPaymentGroup::findChitSiblings($collection);
        $count = 0;

        foreach ($siblings as $item) {
            if (! in_array($item->status, ['in_progress', 'pending'], true)) {
                continue;
            }

            $item->update([
                'status' => 'rejected',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'rejected_reason' => $remarks,
                'remarks' => trim(($item->remarks ?? '') . ($remarks ? "\n" . $remarks : '')),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * One agent+customer notification per customer after the HTTP response (keeps Approve fast).
     *
     * @param  \Illuminate\Support\Collection<int, EmiCollection>  $applied
     */
    private function queueEmiApprovalNotifications($applied): void
    {
        $applied = collect($applied)->filter();
        if ($applied->isEmpty()) {
            return;
        }

        $payloads = $applied
            ->groupBy(function (EmiCollection $row) {
                return (int) ($row->emi?->loanAccount?->client_id ?? $row->emi?->loan_account_id ?? $row->id);
            })
            ->map(function ($rows) {
                $lead = $rows->first();

                return [
                    'emi_ids' => $rows->pluck('emi_id')->filter()->unique()->values()->all(),
                    'amount' => round((float) $rows->sum('amount'), 2),
                    'agent_id' => $lead?->agent_id,
                ];
            })
            ->values()
            ->all();

        if ($payloads === []) {
            return;
        }

        dispatch(function () use ($payloads) {
            $notifications = app(\App\Services\AppNotificationService::class);
            foreach ($payloads as $payload) {
                if (empty($payload['emi_ids'])) {
                    continue;
                }
                $emis = \App\Models\Emi::with(['loanAccount.client', 'loanAccount.loanApplication.client'])
                    ->whereIn('id', $payload['emi_ids'])
                    ->get();
                $agent = ! empty($payload['agent_id'])
                    ? \App\Models\Agent::find($payload['agent_id'])
                    : null;
                $notifications->emiBulkCollected($emis, (float) $payload['amount'], $agent, 'admin');
            }
        })->afterResponse();
    }

    /**
     * Verify every pending sibling in a bulk chit group (or the single row).
     *
     * @param  array<string, bool>  $processedIds
     * @return array{count: int, applied: \Illuminate\Support\Collection}
     */
    private function verifyChitCollectionGroup(
        ChitCollection $collection,
        ?string $remarks,
        ?int $bankAccountId,
        array &$processedIds
    ): array {
        @set_time_limit(180);

        $siblings = BulkPaymentGroup::findChitSiblings($collection)
            ->sortBy(fn (ChitCollection $row) => (int) ($row->installment?->month_number ?? $row->id))
            ->values();

        $toApply = $siblings->filter(function (ChitCollection $item) use ($processedIds) {
            $key = 'chit_' . $item->id;

            return ! isset($processedIds[$key]) && in_array($item->status, ['in_progress', 'pending'], true);
        })->values();

        if ($toApply->isEmpty()) {
            return ['count' => 0, 'applied' => collect()];
        }

        $previousNotify = \App\Services\ChitPaymentService::$suppressPaymentNotifications;
        \App\Services\ChitPaymentService::$suppressPaymentNotifications = true;

        $applied = collect();
        $verifiedCount = 0;

        try {
            foreach ($toApply as $item) {
                $item->loadMissing(['installment.member.shares', 'installment.sharePayments', 'agent', 'client']);
                if (! $item->installment) {
                    continue;
                }

                $combinedRemarks = trim(($item->remarks ?? '') . ($remarks ? "\n" . $remarks : ''));
                $item->remarks = $combinedRemarks;
                $this->applyVerifiedChitCollection($item, $bankAccountId);
                $item->update([
                    'status' => 'verified',
                    'verified_by' => Auth::id(),
                    'verified_at' => now(),
                    'remarks' => $combinedRemarks,
                ]);

                $processedIds['chit_' . $item->id] = true;
                $verifiedCount++;
                $applied->push($item->fresh(['installment.member.client', 'installment.group', 'agent', 'client']));
            }
        } finally {
            \App\Services\ChitPaymentService::$suppressPaymentNotifications = $previousNotify;
        }

        return ['count' => $verifiedCount, 'applied' => $applied];
    }

    /**
     * One agent+customer notification per customer after the HTTP response.
     *
     * @param  \Illuminate\Support\Collection<int, ChitCollection>  $applied
     */
    private function queueChitApprovalNotifications($applied): void
    {
        $applied = collect($applied)->filter();
        if ($applied->isEmpty()) {
            return;
        }

        $payloads = $applied
            ->groupBy(function (ChitCollection $row) {
                return (int) ($row->client_id ?? $row->installment?->member?->client_id ?? $row->id);
            })
            ->map(function ($rows) {
                $lead = $rows->first();

                return [
                    'installment_ids' => $rows->pluck('installment_id')->filter()->unique()->values()->all(),
                    'amount' => round((float) $rows->sum('amount'), 2),
                    'agent_id' => $lead?->agent_id,
                    'client_id' => $lead?->client_id,
                ];
            })
            ->values()
            ->all();

        if ($payloads === []) {
            return;
        }

        dispatch(function () use ($payloads) {
            $notifications = app(\App\Services\AppNotificationService::class);
            foreach ($payloads as $payload) {
                if (empty($payload['installment_ids'])) {
                    continue;
                }
                $installments = Installment::with(['member.client', 'group'])
                    ->whereIn('id', $payload['installment_ids'])
                    ->get();
                $agent = ! empty($payload['agent_id'])
                    ? Agent::find($payload['agent_id'])
                    : null;
                $notifications->chitBulkCollected($installments, (float) $payload['amount'], $agent, 'admin');
            }
        })->afterResponse();
    }

    public function bulkVerify(Request $request)
    {
        if (!Auth::user()->hasRole('Admin') && !Auth::user()->hasRole('Staff')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'collection_ids'   => 'required|array|min:1',
            'collection_ids.*' => 'required',
            'remarks'          => 'nullable|string|max:500',
        ]);

        $ids     = $request->collection_ids;
        $remarks = $request->remarks ?? '';

        @set_time_limit(180);

        $verified = 0;
        $skipped  = 0;
        $errors   = [];
        $processedIds = [];
        $allApplied = collect();
        $allChitApplied = collect();

            foreach ($ids as $rawId) {
                $isChit = str_starts_with((string) $rawId, 'chit_');
                $cleanId = $isChit ? str_replace('chit_', '', (string) $rawId) : $rawId;
            $realId = $this->resolveCollectionRecordId($cleanId);

            if (! $realId) {
                        $skipped++;
                $errors[] = "Collection {$rawId}: invalid ID";
                        continue;
                    }

            try {
                if ($isChit) {
                    if (isset($processedIds['chit_' . $realId])) {
                        continue;
                    }

                    $chitColl = ChitCollection::with(['installment.member.shares', 'installment.sharePayments', 'agent'])->find($realId);

                    if (! $chitColl || ! in_array($chitColl->status, ['in_progress', 'pending'], true)) {
                        $skipped++;
                        $errors[] = "Chit collection #{$realId} is not pending verification";
                    continue;
                }

                    if (! $chitColl->installment) {
                    $skipped++;
                        $errors[] = "Chit collection #{$realId}: installment missing";
                    continue;
                }

                    $groupResult = DB::transaction(function () use ($chitColl, $remarks, &$processedIds) {
                        return $this->verifyChitCollectionGroup($chitColl, $remarks, null, $processedIds);
                    });
                    $groupVerified = (int) ($groupResult['count'] ?? 0);
                    $verified += $groupVerified;
                    $allChitApplied = $allChitApplied->concat($groupResult['applied'] ?? collect());

                    if ($groupVerified === 0) {
                    $skipped++;
                        $errors[] = "Chit collection #{$realId} had no pending installments to verify";
                    }
                    continue;
                }

                $collection = EmiCollection::with(['emi.loanAccount', 'agent'])->find($realId);
                if (! $collection) {
                    $skipped++;
                    $errors[] = "Loan collection #{$realId} was not found";
                    continue;
                }

                $groupResult = $this->verifyLoanCollectionGroup($collection, $remarks, null, $processedIds);
                $groupVerified = (int) ($groupResult['count'] ?? 0);
                $verified += $groupVerified;
                $allApplied = $allApplied->concat($groupResult['applied'] ?? collect());

                if ($groupVerified === 0) {
                    $skipped++;
                    $errors[] = "Collection #{$realId} had no pending EMIs to verify";
                }
            } catch (\Throwable $itemErr) {
                Log::error('Bulk verify item error', ['id' => $rawId, 'error' => $itemErr->getMessage()]);
                $errors[] = "Collection #{$rawId}: " . $itemErr->getMessage();
            }
        }

        $this->queueEmiApprovalNotifications($allApplied);
        $this->queueChitApprovalNotifications($allChitApplied);

        $success = $verified > 0;
            $message = "Bulk verify complete. Verified: {$verified}";
        if ($skipped > 0) {
            $message .= ", Skipped: {$skipped}";
        }
        if (! empty($errors)) {
            $message .= '. ' . implode('; ', array_slice($errors, 0, 3));
        }

            return response()->json([
            'success'  => $success,
                'message'  => $message,
                'verified' => $verified,
                'skipped'  => $skipped,
                'errors'   => $errors,
        ], $success ? 200 : 422);
    }

    /**
     * Search for pending EMIs for manual collection creation
     */
    public function searchEmis(Request $request)
    {
        $search = $request->input('q');
        $agentIdFilter = $request->input('agent_id');
        $currentUser = Auth::user();
        $isAgent = $currentUser->hasRole('Agent');
        $currentAgentId = $isAgent ? optional($currentUser->agent)->id : $agentIdFilter;
        
        $action = $request->input('action', 'collection');
        $collectionType = $request->input('collection_type', 'all');

        if ($isAgent && !$currentAgentId && $action !== 'assign') {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
            ]);
        }
        
        $formattedResults = collect();
        if ($collectionType !== 'chit') {
        $query = Emi::with(['loanAccount.client'])
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->where(function($q) {
                // Hide EMIs that are already fully covered by pending (in_progress) collections
                $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
            });

        // Filter by agent if provided or if user is an agent (skip for assignment action)
        if ($currentAgentId && $action !== 'assign') {
            $query->where(function($q) use ($currentAgentId) {
                $q->whereHas('loanAccount.client', function($query) use ($currentAgentId) {
                    $query->where('assigned_to', $currentAgentId)
                          ->orWhere('added_by', $currentAgentId);
                })
                ->orWhereHas('activeAssignment', function($query) use ($currentAgentId) {
                    $query->where('agent_id', $currentAgentId);
                });
            });
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->whereHas('loanAccount.client', function($query) use ($search) {
                    $query->where('client_name', 'LIKE', "%{$search}%")
                          ->orWhere('client_phone', 'LIKE', "%{$search}%")
                          ->orWhere('alternate_phone', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('loanAccount', function($query) use ($search) {
                    $query->where('account_number', 'LIKE', "%{$search}%");
                })
                ->orWhere('emis.id', 'LIKE', "%{$search}%");
            });
        }

        $page = $request->input('page', 1);
        $perPage = 50;

        $emis = $query->orderByRaw("CASE WHEN status = 'partial' THEN 1 ELSE 2 END ASC")
            ->orderBy('instalment_number')
            ->paginate($perPage, ['*'], 'page', $page);
            
        $formattedResults = $emis->getCollection()->map(function($emi) {
            $clientName = $emi->loanAccount?->client?->client_name ?? 'N/A';
            $accNo = $emi->loanAccount?->account_number ?? 'N/A';
            $status = strtoupper($emi->status);
            
            // Sequential lock: Check if any previous EMI is unpaid AND not fully covered by pending (in_progress) collections
            $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                ->where('instalment_number', '<', $emi->instalment_number)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where(function($q) {
                    $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                })
                ->exists();

            $isDisabled = ($emi->status === 'paid' || $unpaidPrior);
            $inProgressSum = $emi->collections()->where('status', 'in_progress')->sum('amount');
            $netPending = max(0, $emi->pending_amount - $inProgressSum);
            
            $label = ($emi->status === 'partial') ? 'Balance' : 'Pending';
            $displayText = "[Loan: #{$accNo}] {$clientName} - EMI #{$emi->instalment_number} ({$status}) - {$label}: ₹" . number_format($netPending, 2);
            if ($unpaidPrior) {
                $displayText .= " (PREVIOUS EMI PENDING)";
            }

            return [
                'id' => 'loan_' . $emi->id,
                'text' => $displayText,
                'amount' => $netPending,
                'disabled' => $isDisabled
            ];
        });
        }

        // Search Chit Installments when action is collection
        $chitResults = collect();
        $chitHasMore = false;
        if ($action === 'collection' && $collectionType !== 'loan') {
            $chitQuery = Installment::with(['member.client', 'group'])
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->whereDoesntHave('pendingCollections');

            if ($isAgent && $currentAgentId) {
                $this->applyChitAgentScope($chitQuery, $currentAgentId);
            } elseif (!$isAgent) {
                // Admin: installments from activated/active groups
                $chitQuery->whereHas('group', function ($gq) {
                    $gq->whereIn('status', ['active', 'completed']);
                });

                if ($currentAgentId) {
                    $this->applyChitAgentScope($chitQuery, (int) $currentAgentId);
                }
            }

            if (!empty($search)) {
                $chitQuery->where(function ($q) use ($search) {
                    $q->whereHas('member.client', function ($cq) use ($search) {
                        $cq->where('client_name', 'LIKE', "%{$search}%")
                           ->orWhere('client_phone', 'LIKE', "%{$search}%")
                           ->orWhere('alternate_phone', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('group', function ($gq) use ($search) {
                        $gq->where('group_code', 'LIKE', "%{$search}%");
                    });
                });
            }

            $chits = $chitQuery->orderBy('month_number')->take(30)->get();
            $chitHasMore = $chitQuery->count() > 30;
            $chitResults = $chits->flatMap(function ($inst) {
                $member = $inst->member;
                $clientName = $member?->client?->client_name ?? 'N/A';
                $groupCode = $inst->group?->group_code ?? 'N/A';
                $status = strtoupper($inst->status);
                $isCollectible = $inst->isCollectible();
                $memberFreq = $member?->collection_frequency ?? 'monthly';
                $clientId = (int) ($member?->client_id ?? 0);

                if (in_array($memberFreq, ['weekly', 'daily'], true)) {
                    $instAmount = (float) $member->displayAmountForInstallment($inst, $clientId);
                    $paidAmt = (float) $inst->clientPaidShare($clientId);
                    $periods = $member->collectionPeriodSchedule($inst->due_date, $instAmount, $paidAmt);

                    $unpaidPeriods = collect($periods)->filter(fn ($p) => (float) $p['balance'] > 0.009);
                    if ($unpaidPeriods->isNotEmpty()) {
                        return $unpaidPeriods->map(function ($p) use ($inst, $clientName, $groupCode, $isCollectible, $memberFreq) {
                            $pDueDate = $p['due_date'] ? \Carbon\Carbon::parse($p['due_date'])->format('d M Y') : 'N/A';
                            $pStatus = strtoupper($p['status']);
                            $pBalance = round((float) $p['balance'], 2);
                            $displayText = "[Chit: {$groupCode}] {$clientName} - Month #{$inst->month_number} - {$p['label']} (Due: {$pDueDate}) ({$pStatus}) - Balance: ₹" . number_format($pBalance, 2);

                            $disabled = false;
                if (!$isCollectible) {
                    $displayText .= ' (PREVIOUS INSTALLMENT PENDING)';
                                $disabled = true;
                            } elseif (!($p['is_next'] ?? false)) {
                                $unit = $memberFreq === 'weekly' ? 'WEEK' : 'DAY';
                                $displayText .= " (PREVIOUS {$unit} PENDING)";
                                $disabled = true;
                }

                return [
                                'id' => 'chit_' . $inst->id . '_p_' . $p['index'],
                                'text' => $displayText,
                                'amount' => $pBalance,
                                'disabled' => $disabled,
                                'chit_installment_id' => $inst->id,
                                'period_index' => (int) $p['index'],
                                'period_label' => (string) $p['label'],
                                'frequency' => $memberFreq,
                            ];
                        });
                    }
                }

                $netPending = max(0, (float) $inst->balance);
                $label = ($inst->status === 'partial') ? 'Balance' : 'Pending';
                $displayText = "[Chit: {$groupCode}] {$clientName} - Month #{$inst->month_number} ({$status}) - {$label}: ₹" . number_format($netPending, 2);
                if (!$isCollectible) {
                    $displayText .= ' (PREVIOUS INSTALLMENT PENDING)';
                }

                return [[
                    'id' => 'chit_' . $inst->id,
                    'text' => $displayText,
                    'amount' => $netPending,
                    'disabled' => !$isCollectible,
                    'chit_installment_id' => $inst->id,
                    'frequency' => 'monthly',
                ]];
            });
        }

        if ($collectionType === 'chit') {
            $allResults = $chitResults->values();
            $hasMore = $chitHasMore;
        } elseif ($collectionType === 'loan') {
            $allResults = $formattedResults->values();
            $hasMore = isset($emis) ? $emis->hasMorePages() : false;
        } else {
            $allResults = $formattedResults->concat($chitResults)->values();
            $hasMore = (isset($emis) && $emis->hasMorePages()) || $chitHasMore;
        }

        return response()->json([
            'results' => $allResults,
            'pagination' => [
                'more' => $hasMore,
            ],
        ]);
    }

    /**
     * Return loan and chit dues available to the logged-in agent for bulk collection.
     */
    public function assignedDues(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole('Agent')) {
            return response()->json(['success' => false, 'message' => 'This action is available to agents only.'], 403);
        }

        $agentId = $user->agent?->id;
        if (!$agentId) {
            return response()->json(['success' => false, 'message' => 'Agent profile not found. Please contact admin.'], 422);
        }

        $validated = $request->validate([
            'type' => 'nullable|in:loan,chit,all',
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $type = $validated['type'] ?? 'all';
        $search = trim($validated['q'] ?? '');
        $rows = collect();

        if ($type !== 'chit') {
            $loanQuery = Emi::with(['loanAccount.client'])
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->whereDoesntHave('collections', fn ($q) => $q->where('status', 'in_progress'));
            $loanQuery->where(function ($q) use ($agentId) {
                $q->whereHas('loanAccount.client', function ($clientQ) use ($agentId) {
                    $clientQ->where('assigned_to', $agentId);
                        // ->orWhere('added_by', $agentId);
                })->orWhereHas('activeAssignment', fn ($assignmentQ) => $assignmentQ->where('agent_id', $agentId));
            });

            if ($search !== '') {
                $loanQuery->where(function ($q) use ($search) {
                    $q->whereHas('loanAccount.client', function ($clientQ) use ($search) {
                        $clientQ->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%")
                            ->orWhere('alternate_phone', 'LIKE', "%{$search}%");
                    })->orWhereHas('loanAccount', fn ($accountQ) => $accountQ->where('account_number', 'LIKE', "%{$search}%"));
                });
            }

            $partialService = app(PartialPaymentConfigService::class);
            $loanRows = $loanQuery->orderBy('due_date')->get()->map(function (Emi $emi) use ($partialService) {
                $unpaidPrior = $this->loanHasUnpaidPriorEmi($emi);
                $amount = max(0, (float) $partialService->getOutstandingDueAmount($emi, $emi->loanAccount));

                return [
                    'id' => 'loan_' . $emi->id,
                    'type' => 'loan',
                    'client_name' => $emi->loanAccount?->client?->client_name ?? 'N/A',
                    'label' => 'EMI #' . $emi->instalment_number,
                    'account_or_group' => $emi->loanAccount?->account_number ?? 'N/A',
                    'due_date' => optional($emi->due_date)->format('Y-m-d'),
                    'amount' => round($amount, 2),
                    'status' => $emi->status,
                    'disabled' => $amount <= 0 || $unpaidPrior,
                    'disabled_reason' => $unpaidPrior ? 'Previous EMI is still pending.' : ($amount <= 0 ? 'No amount is due.' : null),
                ];
            });
            $rows = $rows->concat($loanRows);
        }

        if ($type !== 'loan') {
            $chitQuery = Installment::with(['member.client', 'group'])
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->whereDoesntHave('pendingCollections');
            $this->applyChitAgentScope($chitQuery, $agentId);

            if ($search !== '') {
                $chitQuery->where(function ($q) use ($search) {
                    $q->whereHas('member.client', function ($clientQ) use ($search) {
                        $clientQ->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%")
                            ->orWhere('alternate_phone', 'LIKE', "%{$search}%");
                    })->orWhereHas('group', fn ($groupQ) => $groupQ->where('group_code', 'LIKE', "%{$search}%"));
                });
            }

            $chitRows = $chitQuery->orderBy('due_date')->get()->flatMap(function (Installment $installment) {
                $member = $installment->member;
                $clientName = $member?->client?->client_name ?? 'N/A';
                $groupCode = $installment->group?->group_code ?? 'N/A';
                $isCollectible = $installment->isCollectible();
                $memberFreq = $member?->collection_frequency ?? 'monthly';
                $clientId = (int) ($member?->client_id ?? 0);

                $inProgressAmount = $installment->collections ? $installment->collections->where('status', 'in_progress')->sum('amount') : 0;

                if (in_array($memberFreq, ['weekly', 'daily'], true)) {
                    $instAmount = (float) $member->displayAmountForInstallment($installment, $clientId);
                    $paidAmt = (float) $installment->clientPaidShare($clientId);
                    $effectivePaid = $paidAmt + (float) $inProgressAmount;
                    $periods = $member->collectionPeriodSchedule($installment->due_date, $instAmount, $effectivePaid);

                    $unpaidPeriods = collect($periods)->filter(fn ($p) => (float) $p['balance'] > 0.009);
                    if ($unpaidPeriods->isNotEmpty()) {
                        return $unpaidPeriods->map(function ($p) use ($installment, $clientName, $groupCode, $isCollectible, $memberFreq) {
                            $pBalance = round((float) $p['balance'], 2);
                            $pDueDate = $p['due_date'] ? \Carbon\Carbon::parse($p['due_date'])->format('Y-m-d') : optional($installment->due_date)->format('Y-m-d');
                            $disabled = !$isCollectible || !($p['is_next'] ?? false);
                            $disabledReason = !$isCollectible
                                ? 'Previous installment must be paid first.'
                                : (!($p['is_next'] ?? false) ? 'Previous ' . ($memberFreq === 'weekly' ? 'week' : 'day') . ' must be paid first.' : null);

                return [
                                'id' => 'chit_' . $installment->id . '_p_' . $p['index'],
                                'type' => 'chit',
                                'chit_installment_id' => $installment->id,
                                'installment_id' => $installment->id,
                                'period_index' => (int) $p['index'],
                                'period_number' => (int) $p['index'],
                                'period_label' => (string) $p['label'],
                                'month_number' => (int) $installment->month_number,
                                'frequency' => $memberFreq,
                                'frequency_label' => ucfirst($memberFreq),
                                'client_name' => $clientName,
                                'label' => 'Month ' . $installment->month_number . ' - ' . $p['label'],
                                'account_or_group' => $groupCode,
                                'due_date' => $pDueDate,
                                'amount' => $pBalance,
                                'balance' => $pBalance,
                                'total_amount' => round((float) $p['amount'], 2),
                                'status' => $p['status'],
                                'disabled' => $disabled,
                                'disabled_reason' => $disabledReason,
                            ];
                        });
                    }
                }

                $amount = max(0, (float) $installment->balance - (float) $inProgressAmount);
                return [[
                    'id' => 'chit_' . $installment->id,
                    'type' => 'chit',
                    'chit_installment_id' => $installment->id,
                    'installment_id' => $installment->id,
                    'period_index' => null,
                    'period_number' => null,
                    'period_label' => null,
                    'month_number' => (int) $installment->month_number,
                    'frequency' => 'monthly',
                    'frequency_label' => 'Monthly',
                    'client_name' => $clientName,
                    'label' => 'Installment #' . $installment->month_number,
                    'account_or_group' => $groupCode,
                    'due_date' => optional($installment->due_date)->format('Y-m-d'),
                    'amount' => round($amount, 2),
                    'balance' => round($amount, 2),
                    'total_amount' => round((float) $installment->total_due, 2),
                    'status' => $installment->status,
                    'disabled' => $amount <= 0 || !$isCollectible,
                    'disabled_reason' => !$isCollectible
                        ? 'Previous installment must be paid first.'
                        : ($amount <= 0 ? 'No amount is due.' : null),
                ]];
            });
            $rows = $rows->concat($chitRows);
        }

        $rows = $rows->sortBy(fn ($row) => $row['due_date'] ?? '9999-12-31')->values();
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 25);
        $total = $rows->count();

        return response()->json([
            'success' => true,
            'data' => $rows->forPage($page, $perPage)->values(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Store multiple agent collections as pending in one transaction.
     */
    public function bulkStore(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole('Agent')) {
            return response()->json(['success' => false, 'message' => 'This action is available to agents only.'], 403);
        }

        $agentId = $user->agent?->id;
        if (!$agentId) {
            return response()->json(['success' => false, 'message' => 'Agent profile not found. Please contact admin.'], 422);
        }

        $validated = $request->validate([
            'collected_at' => 'required|date',
            'payment_method' => 'required|in:in_hand,upi,bank_transfer,cash,direct',
            'internal_bank_account_id' => 'required_if:payment_method,upi,bank_transfer|nullable|exists:bank_accounts,id',
            'payment_reference' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1|max:100',
            'items.*.id' => ['required', 'string', 'distinct', 'regex:/^(loan|chit)_[A-Za-z0-9_\-]+$/'],
            'items.*.amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $agentId) {
                $created = 0;
                $skipped = 0;
                $total = 0.0;
                $batchKey = BulkPaymentGroup::generateKey();
                $bulkRemarks = BulkPaymentGroup::appendRemarks(
                    $validated['remarks'] ?? null,
                    $batchKey,
                    BulkPaymentGroup::AGENT_MARKER
                );
                $paymentReference = $validated['payment_reference'] ?: $batchKey;
                $priorInProgressChit = [];
                $batchChitCollected = [];

                foreach ($validated['items'] as $item) {
                    [$type, $rawId] = explode('_', $item['id'], 2);
                    $amount = round((float) $item['amount'], 2);
                    $paymentType = 'full';

                    if ($type === 'loan') {
                        $id = (int) $rawId;
                        $emi = Emi::with(['loanAccount.client', 'activeAssignment'])
                            ->lockForUpdate()
                            ->findOrFail((int) $id);

                        if (!$this->agentCanAccessLoanEmi($emi, $agentId)) {
                            throw new AuthorizationException('You can only collect EMIs for your assigned clients.');
                        }
                        if (!in_array($emi->status, ['pending', 'overdue', 'partial'], true)) {
                            $skipped++;
                            continue;
                        }
                        if (EmiCollection::where('emi_id', $emi->id)->where('status', 'in_progress')->lockForUpdate()->exists()) {
                            $skipped++;
                            continue;
                        }
                        if ($this->loanHasUnpaidPriorEmi($emi)) {
                            throw ValidationException::withMessages(['items' => "Please clear previous pending EMIs before collecting EMI #{$emi->instalment_number}."]);
                        }

                        $partialService = app(PartialPaymentConfigService::class);
                        $outstanding = round((float) $partialService->getOutstandingDueAmount($emi, $emi->loanAccount), 2);
                        if ($amount > ($outstanding + 0.01)) {
                            throw ValidationException::withMessages(['items' => "EMI #{$emi->instalment_number} amount cannot exceed ₹" . number_format($outstanding, 2) . '.']);
                        }
                        $paymentType = $amount < ($outstanding - 0.01) ? 'partial' : 'full';
                        if ($paymentType === 'partial' && $partialService->isActive()) {
                            if ($error = $partialService->validatePartialAmount($emi, $amount, $emi->loanAccount)) {
                                throw ValidationException::withMessages(['items' => $error]);
                            }
                        }

                        EmiCollection::create([
                            'agent_id' => $agentId,
                            'emi_id' => $emi->id,
                            'amount' => $amount,
                            'payment_method' => $validated['payment_method'],
                            'bank_account_id' => $validated['internal_bank_account_id'] ?? null,
                            'payment_type' => $paymentType,
                            'payment_reference' => $paymentReference,
                            'status' => 'in_progress',
                            'collected_at' => $validated['collected_at'],
                            'remarks' => $bulkRemarks,
                        ]);

                        \App\Models\AgentActivity::create([
                            'emi_id' => $emi->id,
                            'agent_id' => $agentId,
                            'type' => 'payment',
                            'description' => '₹' . number_format($amount, 2),
                            'method' => strtoupper(str_replace('_', ' ', $validated['payment_method'])),
                            'reference' => $paymentReference,
                            'remarks' => $validated['remarks'] ?? null,
                            'action_at' => $validated['collected_at'],
                        ]);

                        \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                            ->where('status', 'assigned')
                            ->update([
                                'status' => 'visited',
                                'remarks' => DB::raw("CONCAT(COALESCE(remarks, ''), ' [Collection pending verification]')"),
                            ]);
                    } else {
                        [$installment, $periodIndex] = $this->resolveChitInstallmentAndPeriod($item);

                        if (!$installment) {
                            throw ValidationException::withMessages(['items' => "Chit Installment not found."]);
                        }

                        // Re-fetch with row lock
                        $installment = Installment::with(['member.client', 'group'])
                            ->lockForUpdate()
                            ->find($installment->id);

                        if (!$installment) {
                            throw ValidationException::withMessages(['items' => "Chit Installment not found."]);
                        }

                        if (!$this->agentCanAccessChitInstallment($installment, $agentId)) {
                            throw new AuthorizationException('You can only collect installments for your assigned clients.');
                        }
                        if (!in_array($installment->status, ['pending', 'overdue', 'partial'], true)) {
                            $skipped++;
                            continue;
                        }

                        $outstanding = round((float) $installment->balance, 2);

                        // Snapshot in-progress sum from DB ONLY ONCE per installment ID so that
                        // newly created collections within this batch loop are NOT double counted!
                        if (!isset($priorInProgressChit[$installment->id])) {
                            $priorInProgressChit[$installment->id] = round(
                                (float) ChitCollection::where('installment_id', $installment->id)
                                    ->where('status', 'in_progress')
                                    ->sum('amount'),
                                2
                            );
                        }

                        $priorInProgress = $priorInProgressChit[$installment->id];
                        $batchAlreadyCollected = round((float) ($batchChitCollected[$installment->id] ?? 0), 2);
                        $currentTotalForInstallment = round($batchAlreadyCollected + $amount, 2);

                        // True remaining collectible balance for this installment before current item
                        $remaining = max(0, round($outstanding - $priorInProgress - $batchAlreadyCollected, 2));

                        // If this installment is already fully covered by prior in-progress collections
                        // or by earlier items in this batch, or the balance is already 0, skip it gracefully.
                        if (($priorInProgress + $batchAlreadyCollected) >= ($outstanding - 0.01) || $outstanding <= 0.01) {
                            $skipped++;
                            continue;
                        }

                        if (($priorInProgress + $currentTotalForInstallment) > ($outstanding + 0.01)) {
                            throw ValidationException::withMessages([
                                'items' => "Total collection for Installment #{$installment->month_number} exceeds remaining balance (₹" . number_format($remaining, 2) . " remaining)."
                            ]);
                        }
                        $batchChitCollected[$installment->id] = $currentTotalForInstallment;

                        $paymentType = $amount < ($remaining - 0.01) ? 'partial' : 'full';
                        $clientId = $this->resolveChitCollectionClientId($installment);

                        $chitRemarks = $bulkRemarks;
                        if ($periodIndex && $installment->member && in_array($installment->member->collection_frequency, ['weekly', 'daily'], true)) {
                            $periodUnit = $installment->member->collection_frequency === 'weekly' ? 'Week' : 'Day';
                            $chitRemarks .= " [{$periodUnit} {$periodIndex}]";
                        }

                        ChitCollection::create([
                            'installment_id' => $installment->id,
                            'group_id' => $installment->group_id,
                            'member_id' => $installment->member_id,
                            'client_id' => $clientId,
                            'agent_id' => $agentId,
                            'amount' => $amount,
                            'share_percentage' => $installment->member?->effective_share_percentage,
                            'payment_method' => $validated['payment_method'] === 'in_hand' ? 'cash' : $validated['payment_method'],
                            'bank_account_id' => $validated['internal_bank_account_id'] ?? null,
                            'payment_type' => $paymentType,
                            'payment_reference' => $paymentReference,
                            'status' => 'in_progress',
                            'collected_at' => $validated['collected_at'],
                            'remarks' => $chitRemarks,
                        ]);
                    }

                    $created++;
                    $total += $amount;
                }

                return ['created_count' => $created, 'skipped_count' => $skipped, 'total_amount' => round($total, 2)];
            });
        } catch (AuthorizationException | ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Agent bulk collection failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Bulk collection failed. Please try again.'], 500);
        }

        $createdCount = $result['created_count'];
        $skippedCount = $result['skipped_count'];
        $totalAmount  = $result['total_amount'];

        if ($createdCount === 0 && $skippedCount > 0) {
        return response()->json([
                'success'       => true,
                'message'       => 'These collections are already submitted and pending admin verification.',
                'created_count' => 0,
                'skipped_count' => $skippedCount,
                'total_amount'  => 0.0,
            ]);
        }

        $message = $createdCount . ' collection(s) sent for admin verification.';
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} item(s) were already collected and skipped.";
        }

        return response()->json([
            'success'       => true,
            'message'       => $message,
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
            'total_amount'  => $totalAmount,
        ]);
    }

    private function loanHasUnpaidPriorEmi(Emi $emi): bool
    {
        $lastEmi = Emi::where('loan_account_id', $emi->loan_account_id)
            ->orderByDesc('instalment_number')
            ->first();
        $isLoanMatured = $lastEmi && $lastEmi->due_date && $lastEmi->due_date->lt(now());
        if ($isLoanMatured) {
            return false;
        }

        return Emi::where('loan_account_id', $emi->loan_account_id)
            ->where('instalment_number', '<', $emi->instalment_number)
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")')
            ->exists();
    }

    /**
     * Store a manually created collection
     */
    public function store(Request $request)
    {
        $isAgent = Auth::user()->hasRole('Agent');
        // Collected By = logged-in collector. Admin/Staff must not be attributed to the client's agent.
        $collectorAgentId = $isAgent
            ? (Auth::user()->agent?->id ?? $request->agent_id)
            : null;

        $rawEmiId = (string) $request->input('emi_id');

        // Handle Chit Installment collection
        if (str_starts_with($rawEmiId, 'chit_')) {
            [$installment, $resolvedPeriod] = $this->resolveChitInstallmentAndPeriod([
                'id' => $rawEmiId,
                'period_index' => $request->period_index,
                'period_number' => $request->period_number,
            ]);
            $periodIndex = $resolvedPeriod ?: ($request->filled('period_index') ? (int) $request->period_index : null);

            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|in:in_hand,upi,bank_transfer,cash,direct,payment_link',
                'payment_type' => 'nullable|in:full,partial',
                'payment_reference' => 'nullable|string|max:100',
                'collected_at' => 'required|date',
                'remarks' => 'nullable|string',
                'internal_bank_account_id' => 'required_if:payment_method,upi,bank_transfer|nullable|exists:bank_accounts,id',
            ]);

            if (!$installment) {
                return response()->json(['success' => false, 'message' => 'Invalid Chit Installment ID.'], 422);
            }

            if ($periodIndex && $installment->member && in_array($installment->member->collection_frequency, ['weekly', 'daily'], true)) {
                $periodUnit = $installment->member->collection_frequency === 'weekly' ? 'Week' : 'Day';
                $periodTag = "[{$periodUnit} {$periodIndex}]";
                if (!str_contains((string) $request->remarks, $periodTag)) {
                    $request->merge(['remarks' => trim(($request->remarks ?? '') . ' ' . $periodTag)]);
                }
            }

            if ($isAgent && $collectorAgentId && !$this->agentCanAccessChitInstallment($installment, $collectorAgentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only collect installments for your assigned clients.',
                ], 403);
            }

            if (!$installment->isCollectible()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please pay the previous installment(s) before collecting this installment.',
                ], 422);
            }

            // Agent-entered chit collections are held for admin verification.
            // Only admin/staff entries are applied to the installment straight away.
            if ($isAgent) {
                $outstanding = round((float) $installment->balance, 2);
                $inProgressSum = \App\Models\ChitCollection::where('installment_id', $installment->id)
                    ->where('status', 'in_progress')
                    ->sum('amount');

                if (((float) $request->amount + $inProgressSum) > ($outstanding + 0.01)) {
                    $remaining = max(0, $outstanding - $inProgressSum);
                    return response()->json([
                        'success' => false,
                        'message' => 'Collection amount exceeds remaining collectible balance (₹' . number_format($remaining, 2) . ' remaining).',
                    ], 422);
                }

                $clientId = $this->resolveChitCollectionClientId(
                    $installment,
                    $request->filled('client_id') ? (int) $request->client_id : null
                );

                \App\Models\ChitCollection::create([
                    'installment_id'    => $installment->id,
                    'group_id'          => $installment->group_id,
                    'member_id'         => $installment->member_id,
                    'client_id'         => $clientId,
                    'agent_id'          => $collectorAgentId,
                    'amount'            => (float) $request->amount,
                    'share_percentage'  => $installment->member?->effective_share_percentage,
                    'payment_method'    => $request->payment_method === 'in_hand' ? 'cash' : $request->payment_method,
                    'bank_account_id'   => $request->internal_bank_account_id,
                    'payment_type'      => $request->payment_type ?? 'full',
                    'payment_reference' => $request->payment_reference,
                    'status'            => 'in_progress',
                    'collected_at'      => $request->collected_at,
                    'remarks'           => trim(($request->remarks ?? '') . ' [Agent Collected]'),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Chit collection recorded and sent for admin verification.',
                ]);
            }

            $paymentService = app(\App\Services\ChitPaymentService::class);
            $result = $paymentService->collectInstallment($installment, [
                'paid_amount'   => (float) $request->amount,
                'payment_mode'  => $request->payment_method === 'in_hand' ? 'cash' : $request->payment_method,
                'payment_type'  => $request->payment_type ?? 'full',
                'paid_date'     => $request->collected_at,
                'reference_no'  => $request->payment_reference,
                'remarks'       => $request->remarks,
                'client_id'     => $installment->member?->client_id,
                'internal_bank_account_id' => $request->internal_bank_account_id,
            ], $collectorAgentId);

            return response()->json([
                'success' => true,
                'message' => 'Chit installment collection recorded successfully!',
            ]);
        }

        // Clean EMI ID if loan_ prefix passed
        if (str_starts_with($rawEmiId, 'loan_')) {
            $request->merge(['emi_id' => (int) str_replace('loan_', '', $rawEmiId)]);
        }

        $rules = [
            'emi_id' => 'required|exists:emis,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:in_hand,upi,bank_transfer,cash,direct,payment_link',
            'payment_type' => 'nullable|in:full,partial',
            'payment_reference' => 'nullable|string|max:100',
            'collected_at' => 'required|date',
            'remarks' => 'nullable|string',
            'internal_bank_account_id' => 'required_if:payment_method,upi,bank_transfer|nullable|exists:bank_accounts,id',
        ];

            $rules['agent_id'] = 'nullable|exists:agents,id';

        $request->validate($rules);

        if ($isAgent && !$collectorAgentId) {
            return response()->json([
                'success' => false,
                'message' => 'Agent profile not found. Please contact admin.',
            ], 422);
        }
        
        $emi = Emi::with(['loanAccount.client', 'activeAssignment'])->findOrFail($request->emi_id);

        if ($isAgent && $collectorAgentId && !$this->agentCanAccessLoanEmi($emi, $collectorAgentId)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only collect EMIs for your assigned clients.',
            ], 403);
        }

        // Check for unpaid EMIs prior to this one (ignoring those fully covered by pending (in_progress) collections)
        $lastEmi = Emi::where('loan_account_id', $emi->loan_account_id)
            ->orderByDesc('instalment_number')
            ->first();
        $isLoanMatured = ($lastEmi && $lastEmi->due_date && $lastEmi->due_date->lt(now()));

        $unpaidPrior = false;
        if (!$isLoanMatured) {
            $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                ->where('instalment_number', '<', $emi->instalment_number)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where(function($q) {
                    $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                })
                ->exists();
        }

        if ($unpaidPrior) {
            return response()->json([
                'success' => false,
                'message' => 'Please clear previous pending EMIs before paying for this instalment.'
            ], 400);
        }

        $loanAccount = $emi->loanAccount;
        $partialService = app(PartialPaymentConfigService::class);
        $pendingAmount = $partialService->getOutstandingDueAmount($emi, $loanAccount);
        $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');

        if ($isKandhuvatti) {
            // For open loans, if amount is less than pending interest due, it's a partial payment
            $isPartialPayment = $request->amount < ($pendingAmount - 0.01);
            
            if ($isPartialPayment) {
                if ($partialService->isActive()) {
                    if ($validationError = $partialService->validatePartialAmount($emi, (float) $request->amount, $loanAccount)) {
                        return response()->json([
                            'success' => false,
                            'message' => $validationError,
                        ], 422);
                    }
                }
            } else {
                // If amount is greater than pending interest, the excess is principal payment.
                // It cannot exceed the remaining outstanding principal.
                $excess = $request->amount - $pendingAmount;
                if ($excess > ($loanAccount->outstanding_amount + 0.01)) {
                    $maxAllowable = $pendingAmount + $loanAccount->outstanding_amount;
                    return response()->json([
                        'success' => false,
                        'message' => 'Collection amount cannot exceed the total outstanding due (Interest: ₹' . number_format($pendingAmount, 2) . ' + Principal: ₹' . number_format($loanAccount->outstanding_amount, 2) . ' = Total: ₹' . number_format($maxAllowable, 2) . ').'
                    ], 400);
                }
            }
        } else {
            $paymentType = $request->payment_type ?: ($request->amount < $pendingAmount ? 'partial' : 'full');
            $isPartialPayment = $paymentType === 'partial' || $request->amount < ($pendingAmount - 0.01);

            if ($isPartialPayment) {
                if ($request->amount <= 0 || $request->amount > ($pendingAmount + 0.01)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Partial payment amount must be between ₹1 and total pending EMI amount (₹' . number_format($pendingAmount, 2) . ').',
                    ], 422);
                }

                if ($partialService->isActive()) {
                    if ($validationError = $partialService->validatePartialAmount($emi, (float) $request->amount, $loanAccount)) {
                        return response()->json([
                            'success' => false,
                            'message' => $validationError,
                        ], 422);
                    }
                }
            } elseif ($request->amount > ($pendingAmount + 1)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Collection amount cannot exceed the pending EMI amount (₹' . number_format($pendingAmount, 0) . ').'
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            $paymentType = $request->input('payment_type');
            if (!$paymentType) {
                $paymentType = ($request->amount < $pendingAmount) ? 'partial' : 'full';
            }
            
            // Agent collections wait for admin verification.
            // Admin/Staff office collections are attributed to the logged-in user and auto-verified.
            $status = $isAgent ? 'in_progress' : 'verified';
            $remarksPrefix = $isAgent ? '[Agent Collected]' : '[Admin Created]';

            // Find existing pending collection for this EMI to avoid duplicates
            $collection = EmiCollection::where('emi_id', $emi->id)
                ->where('status', 'in_progress')
                ->first();

            if ($collection) {
                $newTotalAmount = $collection->amount + $request->amount;
                $isNowFull = ($newTotalAmount >= ($pendingAmount - 0.01));
                $newStatus = ($status === 'verified') ? 'verified' : 'in_progress';

                $updateData = [
                    'amount' => $newTotalAmount,
                    'payment_type' => $isNowFull ? 'full' : 'partial',
                    'payment_method' => $request->payment_method,
                    'status' => $newStatus,
                    'collected_at' => $request->collected_at,
                    'remarks' => trim(($collection->remarks ?? '') . "\n" . ($request->remarks ?? 'Additional collection') . ' ' . $remarksPrefix),
                    'verified_by' => ($newStatus === 'verified') ? Auth::id() : $collection->verified_by,
                    'verified_at' => ($newStatus === 'verified') ? now() : $collection->verified_at,
                ];

                if ($collectorAgentId && !$collection->agent_id) {
                    $updateData['agent_id'] = $collectorAgentId;
                }

                $collection->update($updateData);
            } else {
                $isAutoVerified = ($status === 'verified');
                $collection = EmiCollection::create([
                    'agent_id' => $collectorAgentId,
                    'emi_id' => $request->emi_id,
                    'amount' => $request->amount,
                    'payment_method' => $request->payment_method,
                    'bank_account_id' => $request->internal_bank_account_id,
                    'payment_type' => $paymentType,
                    'payment_reference' => $request->payment_reference,
                    'status' => $status,
                    'collected_at' => $request->collected_at,
                    'remarks' => trim(($request->remarks ?? '') . ' ' . $remarksPrefix),
                    'verified_by' => $isAutoVerified ? Auth::id() : null,
                    'verified_at' => $isAutoVerified ? now() : null,
                ]);
            }

            // Record this specific payment in AgentActivity for history popup
            $activityAgentId = $collectorAgentId ?? (Auth::user()->agent?->id ?? null);
            if ($activityAgentId) {
                \App\Models\AgentActivity::create([
                    'emi_id' => $emi->id,
                    'agent_id' => $activityAgentId,
                    'type' => 'payment',
                    'description' => "₹" . number_format($request->amount, 2),
                    'method' => strtoupper(str_replace('_', ' ', $request->payment_method)),
                    'reference' => $request->payment_reference,
                    'remarks' => $request->remarks,
                    'action_at' => $request->collected_at,
                ]);
            }
            
            // Update assignment status if exists
            if ($status === 'verified') {
                \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                    ->whereIn('status', ['assigned', 'visited'])
                    ->update([
                        'status' => 'resolved',
                        'resolved_at' => now()
                    ]);
            } else {
                \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                    ->whereIn('status', ['assigned'])
                    ->update([
                        'status' => 'visited',
                        'remarks' => DB::raw("CONCAT(COALESCE(remarks, ''), ' [Collection pending verification]')")
                    ]);
            }
            
            $isAutoVerified = ($collection->status === 'verified');
            
            // If auto-verified, use LoanPaymentService to process the payment and trigger all business rules
            if ($isAutoVerified) {
                $paymentService = app(\App\Services\LoanPaymentService::class);
                $isPrincipalCollection = $request->payment_type === 'principal';
                $result = $paymentService->processPayment(
                    $emi->id,
                    $isPrincipalCollection ? 0 : $collection->amount,
                    $collection->collected_at->format('Y-m-d'),
                    $collection->payment_method,
                    $collection->payment_reference,
                    $collection->remarks,
                    true, // skipHistory since we already created the collection record above
                    $isPrincipalCollection ? (float) $collection->amount : 0
                );

                if (!$result['success']) {
                    throw new \Exception($result['message']);
                }
            }
            
            $emi->refresh();
            $loanAccount = $emi->loanAccount->fresh();
            $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
            $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
            $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
            if (strlen($cleanMobile) === 10) {
                $cleanMobile = '91' . $cleanMobile;
            }
            $remainingBalance = $loanAccount->outstanding_amount;
            $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');

            if ($isAutoVerified) {
                $isFullyPaid = ($emi->status === 'paid');
                $emiBalance = max(0, $emi->pending_amount);
            } else {
                // Adjust remaining balance for unverified payments so the SMS reflects the expected outcome
                if ($isKandhuvatti) {
                    if ($request->payment_type === 'principal') {
                        $remainingBalance = max(0, $remainingBalance - $request->amount);
                    }
                } else {
                    $interestPart = (float)($emi->interest_amount ?? 0);
                    $alreadyPaid = (float)($emi->paid_amount ?? 0);
                    $unpaidInterest = max(0, $interestPart - min($alreadyPaid, $interestPart));
                    $principalPaidInThisTransaction = max(0, $request->amount - $unpaidInterest);
                    $remainingBalance = max(0, $remainingBalance - $principalPaidInThisTransaction);
                }
                $isFullyPaid = $isKandhuvatti
                    ? ($request->amount >= ($pendingAmount - 0.01))
                    : ($paymentType === 'full' || $request->amount >= ($pendingAmount - 0.01));
                $emiBalance = max(0, $pendingAmount - $request->amount);
            }

            $smsData = [
                'client_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) ?: ($client->client_name ?? 'Client'),
                'mobile_no' => $cleanMobile,
                'account_no' => $loanAccount->account_number,
                'amount_paid' => $request->amount,
                'remaining_balance' => $remainingBalance,
                'loan_mode' => $loanAccount->loan_mode,
                'payment_type' => ($isKandhuvatti && $request->payment_type === 'principal') ? 'principal' : (($isKandhuvatti) ? 'interest' : 'emi'),
                'application_number' => $loanAccount->application_number,
                'is_partial' => !$isFullyPaid,
                'emi_balance' => $emiBalance,
            ];
            $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Collection added successfully',
                'sms_data' => $smsData
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to add collection: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Assign a single EMI to an agent
     */
    public function assign(Request $request)
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'emi_id' => 'required|exists:emis,id',
            'remarks' => 'nullable|string'
        ]);
    
        $emi = Emi::findOrFail($request->emi_id);

        // Check if the EMI is already paid
        if ($emi->status === 'paid' || $emi->pending_amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot assign a fully paid EMI.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Create or update assignment
            \App\Models\EmiAgentAssignment::updateOrCreate(
                ['emi_id' => $emi->id],
                [
                    'agent_id' => $request->agent_id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'remarks' => trim(($request->remarks ?? '') . ' [Assigned via Agent Collections]')
                ]
            );

            // Also update Client assigned_to if needed
            if ($emi->loanAccount && $emi->loanAccount->client) {
                $client = $emi->loanAccount->client;
                $previousAgentId = $client->assigned_to;
                $client->update(['assigned_to' => $request->agent_id]);
                if ((int) $previousAgentId !== (int) $request->agent_id) {
                    $agent = \App\Models\Agent::find($request->agent_id);
                    if ($agent) {
                        event(new \App\Events\ClientAssignedToAgentEvent($client->fresh(), $agent));
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'EMI assigned successfully to the agent.']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to assign loan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get payment history for a specific collection (via EMI ID)
     */
    public function getHistory($emiId)
    {
        $collections = EmiCollection::with(['agent', 'verifiedBy'])
            ->where('emi_id', $emiId)
            ->orderBy('collected_at', 'desc')
            ->get()
            ->map(function($collection) {
                $statusLabel = match(strtolower($collection->status)) {
                    'verified', 'completed' => 'Verified',
                    'in_progress', 'pending' => 'Pending',
                    'rejected' => 'Rejected',
                    default => ucfirst($collection->status)
                };

                return [
                    'amount' => '₹' . number_format($collection->amount, 2) . ' (' . $statusLabel . ')',
                    'method' => strtoupper(str_replace('_', ' ', $collection->payment_method)),
                    'reference' => $collection->payment_reference ?? 'N/A',
                    'remarks' => $collection->remarks ?? 'N/A',
                    'date' => $collection->collected_at ? $collection->collected_at->format('d-m-Y H:i') : $collection->created_at->format('d-m-Y H:i'),
                    'agent' => $collection->getCollectedByLabel(),
                    
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => $collections
        ]);
    }

    /**
     * Get details for a specific EMI (used for pre-filling forms)
     */
    public function getEmiInfo($id)
    {
        $emi = Emi::with(['loanAccount.client'])->findOrFail($id);
        $clientName = $emi->loanAccount?->client?->client_name ?? 'N/A';
        $accNo = $emi->loanAccount?->account_number ?? 'N/A';
        
        // Calculate net pending (total amount - paid amount - in_progress collections)
        $inProgressSum = \App\Models\EmiCollection::where('emi_id', $emi->id)
            ->whereIn('status', ['in_progress', 'verified', 'completed'])
            ->sum('amount');
            
        $loanAccount = $emi->loanAccount;
        $partialService = app(PartialPaymentConfigService::class);
        $netPending = $partialService->getOutstandingDueAmount($emi, $loanAccount);
        $partialRules = $partialService->rulesForEmi($emi, $loanAccount);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $emi->id,
                'text' => "[#{$accNo}] {$clientName} - EMI #{$emi->instalment_number} - Pending: ₹" . number_format($netPending, 2),
                'amount' => $netPending,
                'agent_id' => $emi->loanAccount?->agent_id,
                'partial_payment' => $partialRules,
            ]
        ]);
    }

    /**
     * Partial payment rules for an EMI (admin/agent collection UI).
     */
    public function partialPaymentRules($id)
    {
        if (str_starts_with($id, 'chit_')) {
            if (preg_match('/^chit_(\d+)(?:_p_?(\d+))?$/', $id, $m)) {
                $realChitId = (int) $m[1];
                $periodIndex = isset($m[2]) ? (int) $m[2] : null;
            } else {
            $realChitId = (int) str_replace('chit_', '', $id);
                $periodIndex = null;
            }
            $installment = \App\Models\Installment::with('member')->find($realChitId);
            $balance = $installment ? (float) $installment->balance : 0;
            if ($periodIndex && $installment?->member && in_array($installment->member->collection_frequency, ['weekly', 'daily'], true)) {
                $clientId = (int) ($installment->member->client_id ?? 0);
                $instAmount = (float) $installment->member->displayAmountForInstallment($installment, $clientId);
                $paidShare = (float) $installment->clientPaidShare($clientId);
                $sched = $installment->member->collectionPeriodSchedule($installment->due_date, $instAmount, $paidShare);
                $match = collect($sched)->firstWhere('index', $periodIndex);
                if ($match) {
                    $balance = (float) $match['balance'];
                }
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'is_active' => true,
                    'allows_partial' => true,
                    'minimum_partial_amount' => 1,
                    'maximum_partial_amount' => (int) round($balance),
                    'outstanding_due' => round($balance, 2),
                    'timing_allowed' => true,
                    'timing_message' => null,
                ],
            ]);
        }

        if (str_starts_with($id, 'loan_')) {
            $id = (int) str_replace('loan_', '', $id);
        }

        $emi = Emi::with('loanAccount')->findOrFail($id);
        $partialService = app(PartialPaymentConfigService::class);
        $rules = $partialService->rulesForEmi($emi, $emi->loanAccount);

        // For admin / agent manual collection recording, allow partial payment if there is an outstanding balance
        $rules['allows_partial'] = ($rules['outstanding_due'] > 0);
        $rules['is_active'] = true;

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }
}
