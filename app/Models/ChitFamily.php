<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ChitFamily extends Model
{
    protected $fillable = [
        'name',
        'primary_client_id',
        'notes',
        'created_by',
    ];

    public function primaryClient()
    {
        return $this->belongsTo(Client::class, 'primary_client_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function familyMembers()
    {
        return $this->hasMany(ChitFamilyMember::class, 'family_id');
    }

    public function members()
    {
        return $this->belongsToMany(Client::class, 'chit_family_members', 'family_id', 'client_id')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Get the first collectible (current) unpaid installment for each active chit membership.
     */
    public function getCurrentDues(): Collection
    {
        $clientIds = $this->familyMembers()->pluck('client_id');

        if ($clientIds->isEmpty()) {
            return collect();
        }

        $groupMembers = GroupMember::with(['client', 'group.scheme'])
            ->whereIn('client_id', $clientIds)
            ->where('status', 'active')
            ->get();

        $dues = collect();

        foreach ($groupMembers as $groupMember) {
            $installment = Installment::where('member_id', $groupMember->id)
                ->whereNotIn('status', ['paid', 'waived'])
                ->orderBy('month_number')
                ->get()
                ->first(fn (Installment $inst) => $inst->isCollectible());

            if ($installment) {
                $dues->push([
                    'installment_id' => $installment->id,
                    'installment'    => $installment,
                    'client'         => $groupMember->client,
                    'group'          => $groupMember->group,
                    'group_member'   => $groupMember,
                    'balance'        => round((float) $installment->balance, 2),
                    'month_number'   => $installment->month_number,
                    'due_date'       => $installment->due_date,
                ]);
            }
        }

        return $dues->sortBy(fn ($row) => $row['client']->client_name ?? '');
    }

    public function getTotalCurrentDueAttribute(): float
    {
        return round((float) $this->getCurrentDues()->sum('balance'), 2);
    }

    public function getActiveChitsCountAttribute(): int
    {
        $clientIds = $this->familyMembers()->pluck('client_id');

        if ($clientIds->isEmpty()) {
            return 0;
        }

        return GroupMember::whereIn('client_id', $clientIds)
            ->where('status', 'active')
            ->count();
    }
}
