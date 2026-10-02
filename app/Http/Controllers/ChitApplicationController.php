<?php

namespace App\Http\Controllers;

use App\Models\ChitGroup;
use App\Models\Client;
use App\Models\GroupMember;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChitApplicationController extends Controller
{
    protected function isAgentUser(): bool
    {
        $user = Auth::user();

        return $user && $user->hasRole('Agent') && ! $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']);
    }

    protected function isApproverUser(): bool
    {
        $user = Auth::user();

        return $user && $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']);
    }

    protected function currentAgentId(): ?int
    {
        $user = Auth::user();

        return $user ? optional($user->agent)->id : null;
    }

    /**
     * Agent list: only applications this agent submitted (not all assigned clients).
     */
    protected function scopeToAgentClients(Builder $query, ?int $agentId = null): Builder
    {
        $agentId = $agentId ?? $this->currentAgentId();
        $userId = Auth::id();
        $userName = Auth::user()?->name;

        if (! $agentId || ! $userId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($userId, $agentId, $userName) {
            $q->where('applied_by', $userId);

            // Legacy rows before applied_by existed
            if ($userName) {
                $q->orWhere(function ($legacy) use ($agentId, $userName) {
                    $legacy->whereNull('applied_by')
                        ->where('referred_by_agent_id', $agentId)
                        ->where('remarks', 'like', 'Applied by ' . $userName . '%');
                });
            }
        });
    }

    protected function agentOwnsClient(Client $client): bool
    {
        $agentId = $this->currentAgentId();

        return $agentId
            && ((int) $client->added_by === (int) $agentId || (int) $client->assigned_to === (int) $agentId);
    }

    protected function agentCanAccessApplication(GroupMember $member): bool
    {
        $userId = Auth::id();
        if ($userId && (int) $member->applied_by === (int) $userId) {
            return true;
        }

        $agentId = $this->currentAgentId();
        $userName = Auth::user()?->name;
        if (
            $agentId
            && ! $member->applied_by
            && (int) $member->referred_by_agent_id === (int) $agentId
            && $userName
            && str_starts_with((string) $member->remarks, 'Applied by ' . $userName)
        ) {
            return true;
        }

        return false;
    }

    protected function applicationCounts(?int $agentId = null): array
    {
        $base = GroupMember::query()->whereIn('status', ['applied', 'approved', 'active', 'rejected']);

        if ($this->isAgentUser()) {
            $this->scopeToAgentClients($base, $agentId);
        }

        return [
            'applied' => (clone $base)->where('status', 'applied')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'total' => (clone $base)->count(),
        ];
    }

    public function index()
    {
        $isAgent = $this->isAgentUser();
        $agentId = $isAgent ? $this->currentAgentId() : null;

        $verifiedClientsQuery = Client::whereHas('kycDetail', function ($q) {
            $q->where('status', 'verified');
        })->orderBy('client_name');

        if ($isAgent) {
            if (! $agentId) {
                $verifiedClientsQuery->whereRaw('1 = 0');
            } else {
                $verifiedClientsQuery->where(function ($q) use ($agentId) {
                    $q->where('added_by', $agentId)
                        ->orWhere('assigned_to', $agentId);
                });
            }
        }

        $verifiedClients = $verifiedClientsQuery->get();

        $availableGroups = ChitGroup::with('scheme')
            ->whereIn('status', ['forming', 'active'])
            ->withCount(['members' => fn ($q) => $q->whereNotIn('status', ['rejected', 'transferred', 'withdrawn', 'cancelled'])])
            ->orderBy('id', 'desc')
            ->get()
            ->filter(fn ($g) => $g->members_count < $g->total_members)
            ->each(function ($g) {
                $g->settlement_amount = $g->resolvePayoutAmountForMonth((int) $g->current_month + 1);
            });

        $filterGroups = ChitGroup::with('scheme')
            ->orderBy('id', 'desc')
            ->get(['id', 'group_code', 'scheme_id']);

        $agents = \App\Models\Agent::orderBy('agent_name')->get();
        $counts = $this->applicationCounts($agentId);

        return view('admin.chit.applications.index', compact(
            'verifiedClients',
            'availableGroups',
            'filterGroups',
            'agents',
            'counts',
            'isAgent'
        ));
    }

    public function show(GroupMember $member)
    {
        $member->load(['client.agent', 'group.scheme', 'referrerAgent', 'referrerClient', 'approvedBy', 'installments']);

        if ($this->isAgentUser()) {
            if (! $this->agentCanAccessApplication($member)) {
                abort(403, 'You can only view chit applications for your own clients.');
            }
        }

        if (! $member->group) {
            return redirect()->route('chit.applications.index')
                ->with('error', 'Application group no longer exists.');
        }

        return view('admin.chit.applications.show', compact('member'));
    }

    public function data(Request $request): JsonResponse
    {
        $isAgent = $this->isAgentUser();
        $agentId = $isAgent ? $this->currentAgentId() : null;

        $query = GroupMember::with(['group.scheme', 'client.agent', 'shares.client.agent', 'referrerAgent', 'referrerClient'])
            ->latest();

        if ($isAgent) {
            $this->scopeToAgentClients($query, $agentId);
        }

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

        $total = $query->count();

        $start = $request->input('start', 0);
        $length = $request->input('length', 15);
        $applications = $query->skip($start)->take($length)->get();

        $data = $applications->map(function ($member) {
            $clientName = $member->client->client_name ?? '—';
            $clientPhone = $member->client->client_phone ?? '—';
            $group = $member->group;
            $scheme = $group->scheme ?? null;
            $month = (int) ($group->current_month ?? 1);
            $installment = $group
                ? (float) $member->seatInstallmentAmount($group, $month)
                : 0.0;

            return [
                'id' => $member->id,
                'client_name' => $clientName,
                'client_phone' => $clientPhone,
                'client_email' => $member->client->client_email ?? '—',
                'alternate_phone' => $member->client->alternate_phone ?? '—',
                'group_code' => $group->group_code ?? '—',
                'group_status' => $group->status ?? '—',
                'scheme_name' => $scheme->name ?? '—',
                'chit_value' => number_format((float) ($group->chit_value ?? 0), 0),
                'installment' => number_format($installment, 0),
                'share_percentage' => (float) $member->effective_share_percentage,
                'collection_frequency' => $member->collection_frequency ?? 'monthly',
                'collection_frequency_label' => $member->collection_frequency_label ?? 'Monthly',
                'collection_split_amount' => number_format(
                    $member->collectionSplitAmount($installment),
                    2
                ),
                'total_months' => (int) ($group->total_months ?? 0),
                'settlement_amount' => number_format((float) $member->settlement_amount, 0),
                'status' => $member->status,
                'status_badge' => $member->status_badge,
                'applied_at' => $member->created_at->format('d M Y'),
                'joined_date' => optional($member->joined_date)->format('d M Y') ?? '—',
                'assigned_agent' => $member->assigned_agent_name,
                'member_number' => $member->member_number,
                'chit_need_month' => $member->chit_need_month_label,
                'chit_need_periods' => $member->chit_need_periods ? implode(', ', $member->chit_need_periods) : '—',
                'remarks' => $member->remarks ?? '',
                'approved_at' => $member->approved_at ? $member->approved_at->format('d M Y, h:i A') : '—',
            ];
        });

        return response()->json([
            'draw' => $request->input('draw', 1),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
            'counts' => $this->applicationCounts($agentId),
        ]);
    }

    public function apply(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'group_id' => 'required|exists:chit_groups,id',
            'referred_by' => 'nullable|string',
            'chit_need_month' => 'nullable|integer|min:1|max:12',
            'collection_frequency' => 'nullable|in:monthly,weekly,daily',
        ]);

        $client = Client::findOrFail((int) $request->client_id);

        if ($this->isAgentUser() && ! $this->agentOwnsClient($client)) {
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

        [$referredByAgentId, $referredByClientId] = $this->parseReferrer($request->referred_by);

        // Agent apply: default referrer to the logged-in agent when none selected.
        if ($this->isAgentUser() && ! $referredByAgentId && ! $referredByClientId) {
            $referredByAgentId = $this->currentAgentId();
        }

        $needFields = GroupMember::resolveChitNeedFields(
            $request->filled('chit_need_month') ? (int) $request->chit_need_month : null,
            $group
        );

        $member = \Illuminate\Support\Facades\DB::transaction(function () use (
            $group,
            $clientId,
            $referredByAgentId,
            $referredByClientId,
            $needFields,
            $collectionFrequency
        ) {
            $memberNumber = $group->getNextAvailableMemberNumber();

            return GroupMember::create([
                'group_id' => $group->id,
                'client_id' => $clientId,
                'is_shared' => false,
                'collection_frequency' => $collectionFrequency,
                'referred_by_agent_id' => $referredByAgentId,
                'referred_by_client_id' => $referredByClientId,
                'member_number' => $memberNumber,
                'status' => 'applied',
                'joined_date' => today()->format('Y-m-d'),
                'chit_need_month' => $needFields['chit_need_month'],
                'chit_need_date' => $needFields['chit_need_date'],
                'applied_by' => Auth::id(),
                'remarks' => 'Applied by ' . Auth::user()->name . ' on ' . now()->format('d M Y'),
            ]);
        });

        event(new \App\Events\NewChitApplicationEvent(
            $member->loadMissing(['client', 'group.scheme']),
            $this->isAgentUser() ? 'agent' : 'admin'
        ));

        return response()->json([
            'success' => true,
            'message' => 'Chit application submitted successfully for ' . ($member->client->client_name ?? 'client') . '.',
        ]);
    }

    public function approve(Request $request, GroupMember $member)
    {
        if (! $this->isApproverUser()) {
            if ($request->ajax()) {
                return response()->json(['message' => 'Only administrators and staff can approve chit applications.'], 403);
            }
            abort(403, 'Only administrators and staff can approve chit applications.');
        }

        if ($member->status !== 'applied') {
            if ($request->ajax()) {
                return response()->json(['message' => 'Only applied members can be approved.'], 422);
            }
            return back()->with('error', 'Only applied members can be approved.');
        }

        $member->loadMissing('group.scheme');
        $group = $member->group;

        if (! $group) {
            if ($request->ajax()) {
                return response()->json(['message' => 'This application is not linked to a valid chit group.'], 422);
            }
            return back()->with('error', 'This application is not linked to a valid chit group.');
        }

        $shouldGenerateSchedule = $group->status === 'active'
            || $group->installments()->exists();

        $status = $shouldGenerateSchedule ? 'active' : 'approved';

        \Illuminate\Support\Facades\DB::transaction(function () use ($member, $group, $status, $shouldGenerateSchedule) {
            $member->update([
                'status' => $status,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            if ($shouldGenerateSchedule) {
                $startDate = $group->start_date
                    ? Carbon::parse($group->start_date)
                    : ($member->joined_date ? Carbon::parse($member->joined_date) : today());

                $group->generateInstallmentsForMember($member->fresh(), $startDate);
            }

            \App\Models\ChitReferralBonus::generateForMember($member->fresh());

            if ($member->chit_need_month && in_array($group->status, ['active', 'forming'], true)) {
                try {
                    $needMonth = (int) $member->chit_need_month;
                    $payoutService = app(\App\Services\ChitPayoutService::class);
                    $source = $member->applied_by ? 'admin' : 'customer';
                    $payoutService->initiateSettlement(
                        group: $group,
                        member: $member->fresh(),
                        initiatedBy: $member->client?->user_id ?? Auth::id(),
                        payoutKind: 'original',
                        monthNumber: $needMonth,
                        appliedSource: $source
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Auto-initiate settlement application for approved member failed: ' . $e->getMessage());
                }
            }
        });

        $message = $shouldGenerateSchedule
            ? 'Application approved and installment schedule generated successfully.'
            : 'Application approved successfully. Installments will be generated when the group is activated.';

        event(new \App\Events\ChitApplicationApproved($member->fresh(['client', 'group'])));

        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }
        return redirect()->route('chit.applications.show', $member)->with('success', $message);
    }

    public function reject(Request $request, GroupMember $member)
    {
        if (! $this->isApproverUser()) {
            if ($request->ajax()) {
                return response()->json(['message' => 'Only administrators and staff can reject chit applications.'], 403);
            }
            abort(403, 'Only administrators and staff can reject chit applications.');
        }

        if (! in_array($member->status, ['applied', 'approved'])) {
            if ($request->ajax()) {
                return response()->json(['message' => 'Only applied or approved members can be rejected.'], 422);
            }
            return back()->with('error', 'Only applied or approved members can be rejected.');
        }

        $member->update([
            'status' => 'rejected',
            'remarks' => $member->remarks
                ? $member->remarks . ' | Rejected by ' . Auth::user()->name . ' on ' . now()->format('d M Y')
                : 'Rejected by ' . Auth::user()->name . ' on ' . now()->format('d M Y'),
        ]);

        event(new \App\Events\ChitApplicationRejected($member->fresh(['client', 'group'])));

        if ($request->ajax()) {
            return response()->json(['message' => 'Application rejected.']);
        }
        return back()->with('success', 'Application rejected.');
    }

    private function parseReferrer(?string $referredBy): array
    {
        $agentId = null;
        $clientId = null;

        if ($referredBy) {
            if (str_starts_with($referredBy, 'agent_')) {
                $agentId = (int) str_replace('agent_', '', $referredBy);
            } elseif (str_starts_with($referredBy, 'client_')) {
                $clientId = (int) str_replace('client_', '', $referredBy);
            }
        }

        return [$agentId, $clientId];
    }
}
