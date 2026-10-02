<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Installment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'group_id', 'member_id', 'month_number', 'due_date', 'amount', 'share_percentage',
        'paid_amount', 'penalty_amount', 'status', 'paid_date',
        'payment_mode', 'reference_no', 'remarks', 'collected_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
        'share_percentage' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
    ];

    public function group()
    {
        return $this->belongsTo(ChitGroup::class, 'group_id');
    }

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'member_id');
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function collections()
    {
        return $this->hasMany(ChitCollection::class, 'installment_id');
    }

    public function chitCollections()
    {
        return $this->collections();
    }

    public function pendingCollections()
    {
        return $this->collections()->where('status', 'in_progress');
    }

    /**
     * Amount an agent has collected that an admin has not verified yet.
     *
     * Agent collections sit in chit_collections as `in_progress` and leave
     * paid_amount untouched until verification, so this is the only thing that
     * separates "collected, awaiting verification" from "nothing collected".
     */
    public function pendingCollectedAmount(?int $clientId = null): float
    {
        $rows = $this->relationLoaded('collections')
            ? $this->collections
            : $this->collections()->get();

        $rows = $rows->where('status', 'in_progress');

        if ($clientId !== null) {
            // Rows recorded before per-owner tracking have no client_id, so they
            // count towards whichever co-owner is being viewed.
            $rows = $rows->filter(fn ($row) => $row->client_id === null
                || (int) $row->client_id === $clientId);
        }

        return round((float) $rows->sum('amount'), 2);
    }

    /**
     * Status to show users, folding in collections that await verification.
     *
     * Settled rows are left alone: a stray unverified collection against an
     * already paid or waived installment must not drag it back to in_progress.
     */
    public function effectiveStatus(?int $clientId = null): string
    {
        $status = (string) $this->status;

        if (in_array($status, ['paid', 'waived'], true)) {
            return $status;
        }

        return $this->pendingCollectedAmount($clientId) > 0.009 ? 'in_progress' : $status;
    }

    public function sharePayments()
    {
        return $this->hasMany(InstallmentSharePayment::class, 'installment_id');
    }

    /**
     * Restrict to installments owed by clients who still hold a seat in the group,
     * so figures never include enrollments that were rejected, withdrawn or transferred
     * out, nor rows whose membership no longer exists.
     */
    public function scopeForEnrolledMembers($query)
    {
        return $query->whereHas('member', function ($mq) {
            $mq->whereNotIn('status', GroupMember::INACTIVE_STATUSES);
        });
    }

    /**
     * Portion of this installment (principal + penalty) that a given co-owner is liable for.
     * Uses the same independent-share + ownership rules as displayAmountForInstallment.
     */
    public function clientDueShare(int $clientId): float
    {
        if (! $this->member) {
            return round((float) $this->total_due, 2);
        }

        $principal = $this->member->displayAmountForInstallment($this, $clientId);
        $penalty = $this->member->penaltyAmountForClient((float) $this->penalty_amount, $clientId);

        return round($principal + $penalty, 2);
    }

    /**
     * How much this co-owner has actually paid on this installment.
     *
     * Payments recorded before per-owner tracking existed have no share rows, so the
     * untracked remainder is attributed by ownership percentage. This keeps the sum
     * across all owners exactly equal to `paid_amount` either way.
     */
    public function clientPaidShare(int $clientId): float
    {
        $rows = $this->relationLoaded('sharePayments')
            ? $this->sharePayments
            : $this->sharePayments()->get();

        $tracked = round((float) $rows->where('client_id', $clientId)->sum('amount'), 2);
        $untracked = round((float) $this->paid_amount - (float) $rows->sum('amount'), 2);

        if ($untracked > 0.001) {
            $tracked += $this->member
                ? $this->member->amountForClient($untracked, $clientId)
                : $untracked;
        }

        return round($tracked, 2);
    }

    /**
     * Outstanding amount this co-owner still owes, capped by the installment balance
     * so a co-owner's overpayment can never inflate what the others are charged.
     */
    public function clientBalanceShare(int $clientId): float
    {
        $outstanding = round($this->clientDueShare($clientId) - $this->clientPaidShare($clientId), 2);

        return max(0.0, min(round((float) $this->balance, 2), $outstanding));
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'paid'    => 'success',
            'partial' => 'warning',
            'overdue' => 'danger',
            'waived'  => 'info',
            default   => 'secondary',
        };
    }

    public function getTotalDueAttribute(): float
    {
        return $this->amount + $this->penalty_amount;
    }

    public function getBalanceAttribute(): float
    {
        return $this->total_due - $this->paid_amount;
    }

    public function isForemanCommission(): bool
    {
        $scheme = $this->group?->scheme;
        if (! $scheme) {
            return (int) $this->month_number === 1;
        }

        return $scheme->isForemanCommissionMonth((int) $this->month_number);
    }

    public function getAmountDisplayAttribute(): string
    {
        return '₹' . number_format((float) $this->amount, 2);
    }

    public $is_consolidated = false;
    public $consolidated_collectible = true;
    public $consolidated_undoable = false;

    public function isCollectible(): bool
    {
        if ($this->is_consolidated) {
            return $this->consolidated_collectible;
        }

        if (in_array($this->status, ['paid', 'waived'], true)) {
            return false;
        }

        // Overdue / past-due installments are never locked behind earlier months —
        // collectors can clear any overdue row without sequential blocking.
        if ($this->status === 'overdue') {
            return true;
        }

        if ($this->due_date && $this->due_date->lt(today())) {
            return true;
        }

        // Pending / partial installments that are not yet due still follow
        // sequential collection: earlier months must be settled first.
        $hasUnpaidPrevious = self::where('member_id', $this->member_id)
            ->where('group_id', $this->group_id)
            ->where('month_number', '<', $this->month_number)
            ->whereNotIn('status', ['paid', 'waived'])
            ->exists();

        return !$hasUnpaidPrevious;
    }

    public function isUndoable(?int $clientId = null): bool
    {
        if ($this->is_consolidated) {
            return (bool) ($this->consolidated_undoable ?? false);
        }

        $targetClientId = $clientId ?? ($this->viewing_client_id ?? null);

        if ($targetClientId) {
            $paid = $this->clientPaidShare((int) $targetClientId);
            if ($paid <= 0.009) {
                return false;
            }

            $succeeding = self::where('member_id', $this->member_id)
                ->where('group_id', $this->group_id)
                ->where('month_number', '>', $this->month_number)
                ->get();

            foreach ($succeeding as $sInst) {
                if ($sInst->clientPaidShare((int) $targetClientId) > 0.009) {
                    return false;
                }
            }

            return true;
        }

        $paid = (float) $this->paid_amount;
        if (!in_array($this->status, ['paid', 'partial'], true) && $paid <= 0.009) {
            return false;
        }

        $hasPaidSucceeding = self::where('member_id', $this->member_id)
            ->where('group_id', $this->group_id)
            ->where('month_number', '>', $this->month_number)
            ->where(function ($q) {
                $q->whereIn('status', ['paid', 'partial'])
                  ->orWhere('paid_amount', '>', 0.009);
            })
            ->exists();

        return !$hasPaidSucceeding;
    }

    /**
     * Apply automated penalties to overdue chit installments.
     * Uses per-membership override when enabled; otherwise global Chit Configuration.
     */
    public static function applyAutomatedPenalties()
    {
        $globalEnabled = ChitConfiguration::get('penalty_enabled', '0') === '1';
        $globalType = ChitConfiguration::get('penalty_type', 'fixed');
        $globalValue = (float) ChitConfiguration::get('penalty_value', 0);
        $globalGraceDays = (int) ChitConfiguration::get('penalty_grace_days', 0);

        $today = today();

        $installments = self::whereNotIn('status', ['paid', 'waived'])
            ->where('due_date', '<', $today)
            ->with('member')
            ->get();

        foreach ($installments as $inst) {
            $member = $inst->member;
            $memberOverride = $member && (bool) ($member->penalty_enabled ?? false);

            if (! $memberOverride && ! $globalEnabled) {
                continue;
            }

            if ($memberOverride) {
                $type = $member->penalty_type ?: 'fixed';
                $value = (float) ($member->penalty_value ?? 0);
                $graceDays = $member->penalty_grace_days !== null
                    ? (int) $member->penalty_grace_days
                    : $globalGraceDays;
            } else {
                $type = $globalType;
                $value = $globalValue;
                $graceDays = $globalGraceDays;
            }

            $penaltyStartDate = $inst->due_date->copy()->addDays($graceDays);
            $dirty = false;

            if ($today->gt($penaltyStartDate)) {
                $calculatedPenalty = 0.00;
                if ($value > 0) {
                    if ($type === 'percentage') {
                        $calculatedPenalty = round(($value / 100) * (float) $inst->amount, 2);
                    } else {
                        $calculatedPenalty = round($value, 2);
                    }
                }

                if ((float) $inst->penalty_amount !== $calculatedPenalty) {
                    $inst->penalty_amount = $calculatedPenalty;
                    $dirty = true;
                }

                if ($inst->status !== 'overdue') {
                    $inst->status = 'overdue';
                    $dirty = true;
                }
            } elseif ($inst->status === 'pending') {
                // Within grace period but past due date: mark overdue, no penalty yet
                $inst->status = 'overdue';
                if ((float) $inst->penalty_amount !== 0.0) {
                    $inst->penalty_amount = 0;
                }
                $dirty = true;
            }

            if ($dirty) {
                $inst->save();
            }
        }
    }
}
