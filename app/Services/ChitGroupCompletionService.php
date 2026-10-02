<?php

namespace App\Services;

use App\Models\ChitGroup;
use App\Models\GroupMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChitGroupCompletionService
{
    public function __construct(
        protected AppNotificationService $notifications
    ) {}

    /**
     * Mark forming/active groups as completed once their end month has passed.
     */
    public function completeEndedGroups(bool $includeMissingEndDates = false): int
    {
        $cutoff = now()->startOfMonth()->toDateString();

        $query = ChitGroup::query()->whereIn('status', ['forming', 'active']);

        if ($includeMissingEndDates) {
            $groups = $query->get()->filter(function (ChitGroup $group) {
                $group->syncEndDate();

                return $group->hasPassedEndDate();
            });
        } else {
            $groups = $query->whereNotNull('end_date')
                ->whereDate('end_date', '<', $cutoff)
                ->get();
        }

        $count = 0;
        foreach ($groups as $group) {
            if ($this->completeGroupIfEnded($group)) {
                $count++;
            }
        }

        return $count;
    }

    public function completeGroupIfEnded(ChitGroup $group): bool
    {
        if (! in_array($group->status, ['forming', 'active'], true)) {
            return false;
        }

        $group->syncEndDate();
        if (! $group->hasPassedEndDate()) {
            return false;
        }

        try {
            DB::transaction(function () use ($group) {
                $note = 'Auto-completed on ' . now()->format('d M Y') . ' after chit end date.';
                $group->update([
                    'status' => 'completed',
                    'current_month' => max((int) $group->current_month, (int) $group->total_months),
                    'end_date' => $group->end_date ?? now()->toDateString(),
                    'remarks' => trim(($group->remarks ? $group->remarks . "\n" : '') . $note),
                ]);
                $group->markActiveMembersCompleted();
            });
        } catch (Throwable $e) {
            Log::error('Failed to auto-complete chit group: ' . $e->getMessage(), [
                'group_id' => $group->id,
            ]);

            return false;
        }

        $group->refresh();
        $this->congratulateEligibleMembers($group);

        return true;
    }

    public function completeMemberTenureIfDone(GroupMember $member): bool
    {
        $member->load(['group', 'installments', 'client']);

        if (! $member->hasCompletedTenure()) {
            return false;
        }

        if (in_array($member->status, ['active', 'approved'], true)) {
            $member->update(['status' => 'completed']);
        }

        $this->sendCongratulations($member->fresh(['client', 'group']));

        return true;
    }

    public function congratulateEligibleMembers(ChitGroup $group): void
    {
        $members = $group->members()
            ->with(['client', 'group', 'installments'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->get();

        foreach ($members as $member) {
            $this->completeMemberTenureIfDone($member);
        }
    }

    protected function sendCongratulations(GroupMember $member): void
    {
        try {
            $this->notifications->chitTenureCompleted($member);
        } catch (Throwable $e) {
            Log::error('Chit congratulations notification failed: ' . $e->getMessage(), [
                'member_id' => $member->id,
            ]);
        }
    }
}
