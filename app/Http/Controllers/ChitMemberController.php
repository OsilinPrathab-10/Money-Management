<?php

namespace App\Http\Controllers;

use App\Models\GroupMember;
use App\Models\ChitGroup;
use App\Models\Client;
use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChitMemberController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAgent = $user && $user->hasRole('Agent') && ! $user->hasRole('Admin');
        $agentId = $isAgent ? optional($user->agent)->id : null;

        $query = GroupMember::with([
            'group.scheme',
            'client.agent',
            'shares.client.agent',
            'referrerAgent',
            'referrerClient',
            'installments.sharePayments',
        ])->latest();

        if ($isAgent && $agentId) {
            $assignedClientIds = Client::where(function ($q) use ($agentId, $user) {
                $q->where('assigned_to', $agentId)
                  ->orWhere('added_by', $agentId)
                  ->orWhere('user_id', $user->id);
            })->pluck('id')->toArray();

            $query->where(function ($q) use ($agentId, $assignedClientIds) {
                $q->whereIn('client_id', $assignedClientIds)
                  ->orWhere('referred_by_agent_id', $agentId)
                  ->orWhereHas('shares', fn ($sq) => $sq->whereIn('client_id', $assignedClientIds));
            });

            $clients = Client::whereIn('id', $assignedClientIds)->where('status', 'active')->orderBy('client_name')->get();
        } else {
            $clients = Client::where('status', 'active')->orderBy('client_name')->get();
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('member_number', 'like', "%{$s}%")
                    ->orWhereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$s}%"))
                    ->orWhereHas('client', function ($c) use ($s) {
                        $c->where(function ($cq) use ($s) {
                            $cq->where('client_name', 'like', "%{$s}%")
                                ->orWhere('client_phone', 'like', "%{$s}%");
                        });
                    })
                    ->orWhereHas('shares.client', function ($c) use ($s) {
                        $c->where(function ($cq) use ($s) {
                            $cq->where('client_name', 'like', "%{$s}%")
                                ->orWhere('client_phone', 'like', "%{$s}%");
                        });
                    });
            });
        }

        $members = $query->paginate(15)->withQueryString();
        $groups = ChitGroup::whereIn('status', ['forming', 'active'])->orderBy('id', 'desc')->get();
        $agents = \App\Models\Agent::orderBy('agent_name')->get();
        $mode = 'index';

        return view('admin.chit.members.index', compact('members', 'groups', 'clients', 'agents', 'mode'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        $isAgent = $user && $user->hasRole('Agent') && ! $user->hasRole('Admin');
        $agentId = $isAgent ? optional($user->agent)->id : null;

        if ($isAgent && $agentId) {
            $assignedClientIds = Client::where(function ($q) use ($agentId, $user) {
                $q->where('assigned_to', $agentId)
                  ->orWhere('added_by', $agentId)
                  ->orWhere('user_id', $user->id);
            })->pluck('id')->toArray();

            $clients = Client::whereIn('id', $assignedClientIds)->where('status', 'active')->orderBy('client_name')->get();
        } else {
            $clients = Client::where('status', 'active')->orderBy('client_name')->get();
        }

        $groups = ChitGroup::whereIn('status', ['forming', 'active'])->orderBy('id', 'desc')->get();
        $agents = \App\Models\Agent::orderBy('agent_name')->get();
        $group = $request->filled('group_id') ? ChitGroup::find($request->group_id) : null;
        $mode = 'create';

        return view('admin.chit.members.index', compact('groups', 'clients', 'agents', 'group', 'mode'));
    }

    public function store(Request $request)
    {
        $this->validateMembershipRequest($request);

        $group = ChitGroup::findOrFail($request->group_id);

        $isShared = $request->boolean('is_shared');
        $shareRows = $this->extractShareRows($request, $isShared);
        $primaryClientId = (int) ($shareRows[0]['client_id'] ?? $request->client_id);

        $rawSharePct = $request->input('share_percentage');
        $sharePercentage = ($rawSharePct !== null && $rawSharePct !== '' && is_numeric($rawSharePct))
            ? max(1.0, (float) $rawSharePct)
            : 100.0;
        if ($isShared) {
            $sharePercentage = 100.0;
        }

        $seatsNeeded = $isShared ? 1.0 : max(0.01, round($sharePercentage / 100.0, 4));
        $remaining = $group->remainingSeats();
        if ($seatsNeeded > $remaining + 0.0001) {
            return back()->withInput()->with(
                'error',
                $remaining < 0.01
                    ? 'Group is full. No slots available.'
                    : 'Not enough seats left. Requested '
                        . rtrim(rtrim(number_format($sharePercentage, 2), '0'), '.')
                        . '% (' . rtrim(rtrim(number_format($seatsNeeded, 2), '0'), '.')
                        . ' seats), but only '
                        . rtrim(rtrim(number_format($remaining, 2), '0'), '.')
                        . ' seat(s) remain.'
            );
        }

        [$referredByAgentId, $referredByClientId] = $this->parseReferrer($request->referred_by);

        $initialStatus = ($group->is_private && $group->scheme_type === 'group_based')
            ? 'approved'
            : 'applied';

        if ($group->status === 'active' && $initialStatus === 'approved') {
            $initialStatus = 'active';
        }

        $joinedDate = $request->filled('joined_date') ? $request->joined_date : today()->format('Y-m-d');
        $needFields = GroupMember::resolveChitNeedFields(null, $group);

        $assignedAgentChanged = false;
        if ($request->filled('agent_id')) {
            $client = Client::find($primaryClientId);
            if ($client) {
                $assignedAgentChanged = (int) $client->assigned_to !== (int) $request->agent_id;
                $client->update(['assigned_to' => $request->agent_id]);
            }
            if (!$referredByAgentId && !$referredByClientId) {
                $referredByAgentId = $request->agent_id;
            }
        }

        $collectionFrequency = in_array($request->input('collection_frequency'), ['daily', 'weekly', 'monthly'], true)
            ? $request->input('collection_frequency')
            : 'monthly';

        $member = \Illuminate\Support\Facades\DB::transaction(function () use (
            $group,
            $primaryClientId,
            $isShared,
            $sharePercentage,
            $collectionFrequency,
            $referredByAgentId,
            $referredByClientId,
            $initialStatus,
            $joinedDate,
            $needFields,
            $shareRows
        ) {
            $memberNumber = $group->getNextAvailableMemberNumber();

            $member = GroupMember::create([
                'group_id' => $group->id,
                'client_id' => $primaryClientId,
                'is_shared' => $isShared,
                'share_percentage' => $sharePercentage,
                'collection_frequency' => $collectionFrequency,
                'referred_by_agent_id' => $referredByAgentId,
                'referred_by_client_id' => $referredByClientId,
                'member_number' => $memberNumber,
                'status' => $initialStatus,
                'joined_date' => $joinedDate,
                'chit_need_month' => null,
                'chit_need_date' => null,
                'approved_by' => in_array($initialStatus, ['approved', 'active']) ? Auth::id() : null,
                'approved_at' => in_array($initialStatus, ['approved', 'active']) ? now() : null,
            ]);

            $member->calculateAndStoreShareFields($group);

            try {
                $member->syncShares($isShared, $shareRows, (float) $group->chit_value);
            } catch (ValidationException $e) {
                $member->forceDelete();
                throw $e;
            }

            return $member;
        });

        if ($initialStatus === 'approved' || $initialStatus === 'active') {
            \App\Models\ChitReferralBonus::generateForMember($member);
        }

        if ($initialStatus === 'active') {
            $startDate = \Carbon\Carbon::parse($group->start_date);
            $group->generateInstallmentsForMember($member, $startDate);
        }

        $member->loadMissing(['client', 'group.scheme']);
        if ($initialStatus === 'applied') {
            event(new \App\Events\NewChitApplicationEvent($member, 'admin'));
        } elseif (in_array($initialStatus, ['approved', 'active'], true)) {
            event(new \App\Events\NewChitApplicationEvent($member, 'admin'));
            event(new \App\Events\ChitApplicationApproved($member));
        }

        if ($assignedAgentChanged && $request->filled('agent_id') && $member->client) {
            $agent = \App\Models\Agent::find($request->agent_id);
            if ($agent) {
                event(new \App\Events\ClientAssignedToAgentEvent($member->client, $agent));
            }
        }

        $msg = $group->is_private
            ? 'Member invited and approved for private group!'
            : 'Member enrolled successfully!';

        if ($isShared) {
            $msg .= ' Shared ownership saved.';
        } elseif ($sharePercentage > 100.001) {
            $msg .= ' Multi-seat share (' . rtrim(rtrim(number_format($sharePercentage, 2), '0'), '.') . '%) saved.';
        }

        return redirect()->route('chit.groups.show', $group)->with('success', $msg);
    }

    public function edit(GroupMember $member)
    {
        $member->load(['group', 'shares.client', 'client']);

        if (!$member->group) {
            return redirect()->route('chit.members.index')
                ->with('error', 'This membership belongs to a deleted group and can no longer be edited.');
        }

        $clients = Client::where('status', 'active')->orderBy('client_name')->get();
        $agents = \App\Models\Agent::orderBy('agent_name')->get();
        $mode = 'edit';

        return view('admin.chit.members.index', compact('member', 'clients', 'agents', 'mode'));
    }

    public function update(Request $request, GroupMember $member)
    {
        $request->merge(['group_id' => $member->group_id]);
        $this->validateMembershipRequest($request, updating: true);

        $group = $member->group;
        $isShared = $request->boolean('is_shared');
        $shareRows = $this->extractShareRows($request, $isShared);
        $rawSharePct = $request->input('share_percentage');
        $sharePercentage = ($rawSharePct !== null && $rawSharePct !== '' && is_numeric($rawSharePct))
            ? max(1.0, (float) $rawSharePct)
            : ($member->share_percentage ?? 100.0);
        if ($isShared) {
            $sharePercentage = 100.0;
        }

        $seatsNeeded = $isShared ? 1.0 : max(0.01, round($sharePercentage / 100.0, 4));
        $remaining = $group->remainingSeats($member->id);
        if ($seatsNeeded > $remaining + 0.0001) {
            return back()->withInput()->with(
                'error',
                'Not enough seats left for this share. Requested '
                    . rtrim(rtrim(number_format($sharePercentage, 2), '0'), '.')
                    . '% (' . rtrim(rtrim(number_format($seatsNeeded, 2), '0'), '.')
                    . ' seats), but only '
                    . rtrim(rtrim(number_format($remaining, 2), '0'), '.')
                    . ' seat(s) remain.'
            );
        }

        $collectionFrequency = in_array($request->input('collection_frequency'), ['daily', 'weekly', 'monthly'], true)
            ? $request->input('collection_frequency')
            : ($member->collection_frequency ?? 'monthly');

        [$referredByAgentId, $referredByClientId] = $this->parseReferrer($request->referred_by);

        if ($request->filled('agent_id')) {
            if ($member->client) {
                $previousAgentId = $member->client->assigned_to;
                $member->client->update(['assigned_to' => $request->agent_id]);
                if ((int) $previousAgentId !== (int) $request->agent_id) {
                    $agent = \App\Models\Agent::find($request->agent_id);
                    if ($agent) {
                        event(new \App\Events\ClientAssignedToAgentEvent($member->client->fresh(), $agent));
                    }
                }
            }
            if (!$referredByAgentId && !$referredByClientId) {
                $referredByAgentId = $request->agent_id;
            }
        }

        $member->update([
            'share_percentage' => $sharePercentage,
            'collection_frequency' => $collectionFrequency,
            'referred_by_agent_id' => $referredByAgentId,
            'referred_by_client_id' => $referredByClientId,
        ]);

        $member->calculateAndStoreShareFields($group);
        $member->syncShares($isShared, $shareRows, (float) $group->chit_value);

        if ($member->status === 'active' || $group->installments()->where('member_id', $member->id)->exists()) {
            $startDate = \Carbon\Carbon::parse($group->start_date);
            $group->generateInstallmentsForMember($member, $startDate);
        }

        return redirect()->route('chit.groups.show', $group)->with('success', 'Membership updated successfully!');
    }

    public function approve(GroupMember $member)
    {
        $member->loadMissing('group.scheme');
        $group = $member->group;

        if (! $group) {
            return $this->respondGroupAction(request(), 'This membership is not linked to a valid chit group.', false);
        }

        if ($member->status !== 'applied') {
            return $this->respondGroupAction(request(), 'Only applied members can be approved.', false);
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

            \App\Models\ChitReferralBonus::generateForMember($member->fresh());

            if ($shouldGenerateSchedule) {
                $startDate = $group->start_date
                    ? \Carbon\Carbon::parse($group->start_date)
                    : ($member->joined_date ? \Carbon\Carbon::parse($member->joined_date) : today());
                $group->generateInstallmentsForMember($member->fresh(), $startDate);
            }
        });

        return $this->respondGroupAction(
            request(),
            $shouldGenerateSchedule
                ? 'Member approved and installment schedule generated!'
                : 'Member approved! Installments will be generated when the group is activated.'
        );
    }

    public function reject(GroupMember $member)
    {
        if (! in_array($member->status, ['applied', 'approved'])) {
            return $this->respondGroupAction(request(), 'Only applied or approved members can be rejected.', false);
        }

        $member->update([
            'status' => 'rejected',
            'remarks' => $member->remarks
                ? $member->remarks . ' | Rejected by ' . Auth::user()->name . ' on ' . now()->format('d M Y')
                : 'Rejected by ' . Auth::user()->name . ' on ' . now()->format('d M Y'),
        ]);

        return $this->respondGroupAction(request(), 'Member rejected successfully.');
    }

    /**
     * Cancel an enrolled client's chit seat (keeps history; frees the seat).
     * Use Delete for pre-active applications that should be removed entirely.
     */
    public function cancel(Request $request, GroupMember $member)
    {
        if (in_array($member->status, GroupMember::INACTIVE_STATUSES, true)) {
            return $this->respondGroupAction($request, 'This chit membership is already cancelled or closed.', false);
        }

        if (in_array($member->status, ['completed'], true)) {
            return $this->respondGroupAction($request, 'Completed chit memberships cannot be cancelled.', false);
        }

        if (! in_array($member->status, ['active', 'approved', 'defaulted', 'frozen'], true)) {
            return $this->respondGroupAction(
                $request,
                'Only active / approved members can be cancelled. Use Delete to remove applications.',
                false
            );
        }

        $hasPaidSettlement = Payout::where('winner_member_id', $member->id)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidSettlement || $member->has_won_auction) {
            return $this->respondGroupAction(
                $request,
                'Cannot cancel: this member already has a paid settlement. Use Transfer if needed.',
                false
            );
        }

        $reason = trim((string) $request->input('reason', ''));

        DB::transaction(function () use ($member, $reason) {
            $pendingPayouts = Payout::where('winner_member_id', $member->id)
                ->whereIn('status', ['pending', 'processing'])
                ->get();

            foreach ($pendingPayouts as $payout) {
                $note = 'Cancelled with membership on ' . now()->format('d M Y');
                $payout->update([
                    'status' => 'cancelled',
                    'remarks' => $payout->remarks ? ($payout->remarks . ' | ' . $note) : $note,
                ]);
            }

            $note = 'Chit cancelled by ' . (Auth::user()->name ?? 'Admin') . ' on ' . now()->format('d M Y');
            if ($reason !== '') {
                $note .= ' — ' . $reason;
            }

            $member->update([
                'status' => 'withdrawn',
                'remarks' => $member->remarks ? ($member->remarks . ' | ' . $note) : $note,
            ]);
        });

        return $this->respondGroupAction(
            $request,
            'Chit cancelled. Client can apply for settlement: paid months minus foreman commission month.'
        );
    }

    public function destroy(GroupMember $member)
    {
        $member->loadMissing(['installments.sharePayments']);

        if (! $member->canDeleteFromGroup()) {
            $message = $member->hasFirstMonthPayment()
                ? 'Cannot delete after the first month installment is paid. Use Cancel Chit instead.'
                : 'This membership cannot be deleted.';

            return $this->respondGroupAction(request(), $message, false);
        }

        $group = $member->group;

        DB::transaction(function () use ($member) {
            if (class_exists(\App\Models\ChitCollection::class)) {
                \App\Models\ChitCollection::where('member_id', $member->id)->get()->each->delete();
            }

            if (class_exists(\App\Models\ChitReferralBonus::class)) {
                \App\Models\ChitReferralBonus::where('group_member_id', $member->id)->delete();
            }

            // Remove this member's settlement applications from the Settlement Applications module
            Payout::where('winner_member_id', $member->id)->delete();
            $member->installments()->get()->each->delete();
            $member->shares()->delete();
            $member->delete();
        });

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Client removed from the chit group.']);
        }

        return redirect()->route('chit.groups.show', $group)->with('success', 'Client removed from the chit group.');
    }

    protected function respondGroupAction(Request $request, string $message, bool $success = true)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        }

        return $success
            ? back()->with('success', $message)
            : back()->with('error', $message);
    }

    protected function validateMembershipRequest(Request $request, bool $updating = false): void
    {
        $rules = [
            'is_shared' => 'nullable|boolean',
            'share_percentage' => 'nullable|numeric|min:1|max:10000',
            'collection_frequency' => 'nullable|in:monthly,weekly,daily',
            'agent_id' => 'nullable|exists:agents,id',
            'referred_by' => 'nullable|string',
            'joined_date' => 'nullable|date',
            'chit_need_date' => 'nullable|date',
            'chit_need_month' => 'nullable|integer|min:1|max:12',
            'shares' => 'nullable|array',
            'shares.*.client_id' => 'nullable|exists:clients,id',
            'shares.*.ownership_percentage' => 'nullable|numeric|min:0.01|max:100',
        ];

        if (! $updating) {
            $rules['group_id'] = 'required|exists:chit_groups,id';
        }

        if (! $request->boolean('is_shared')) {
            $rules['client_id'] = 'required|exists:clients,id';
        } else {
            $rules['shares'] = 'required|array|min:2';
            $rules['shares.*.client_id'] = 'required|exists:clients,id';
            $rules['shares.*.ownership_percentage'] = 'required|numeric|min:0.01|max:100';
        }

        $request->validate($rules);
    }

    /**
     * @return array<int, array{client_id:int, ownership_percentage:float}>
     */
    protected function extractShareRows(Request $request, bool $isShared): array
    {
        if (! $isShared) {
            return [[
                'client_id' => (int) $request->client_id,
                'ownership_percentage' => 100,
            ]];
        }

        $rows = [];
        foreach ($request->input('shares', []) as $row) {
            if (empty($row['client_id'])) {
                continue;
            }
            $rows[] = [
                'client_id' => (int) $row['client_id'],
                'ownership_percentage' => (float) ($row['ownership_percentage'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    protected function parseReferrer(?string $referredBy): array
    {
        $referredByAgentId = null;
        $referredByClientId = null;

        if ($referredBy) {
            $parts = explode(':', $referredBy);
            if (count($parts) === 2) {
                if ($parts[0] === 'agent') {
                    $referredByAgentId = $parts[1];
                } elseif ($parts[0] === 'client') {
                    $referredByClientId = $parts[1];
                }
            }
        }

        return [$referredByAgentId, $referredByClientId];
    }
}
