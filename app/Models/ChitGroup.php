<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChitGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'group_code',
        'scheme_id',
        'branch_id',
        'start_date',
        'end_date',
        'current_month',
        'total_months',
        'chit_value',
        'dividend_pool_balance',
        'total_members',
        'installment_amount',
        'commission_pct',
        'auction_type',
        'scheme_type',
        'registration_type',
        'registration_number',
        'registration_date',
        'registering_authority',
        'registration_office',
        'registration_certificate_number',
        'registration_certificate_path',
        'registration_valid_from',
        'registration_valid_until',
        'installment_frequency',
        'fixed_return_amount',
        'is_private',
        'group_leader_id',
        'status',
        'remarks',
        'created_by',
        'referral_commission_pct',
    ];

    protected $casts = [
        'start_date'              => 'date',
        'end_date'                => 'date',
        'chit_value'              => 'decimal:2',
        'dividend_pool_balance'   => 'decimal:2',
        'installment_amount'      => 'decimal:2',
        'commission_pct'          => 'decimal:2',
        'fixed_return_amount'     => 'decimal:2',
        'is_private'              => 'boolean',
        'referral_commission_pct' => 'decimal:2',
        'registration_date'       => 'date',
        'registration_valid_from' => 'date',
        'registration_valid_until'=> 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ChitGroup $group) {
            // 1. Remove auction winners & settlement payouts first (due to foreign key referencing auctions)
            Payout::where('group_id', $group->id)->delete();

            // 2. Remove dividends & dividend pool entries
            Dividend::where('group_id', $group->id)->delete();
            ChitDividendPoolEntry::where('group_id', $group->id)->delete();

            // 3. Remove bids for auctions in this group
            $auctionIds = Auction::where('group_id', $group->id)->pluck('id');
            if ($auctionIds->isNotEmpty()) {
                AuctionBid::whereIn('auction_id', $auctionIds)->delete();
            }

            // 4. Remove auctions
            Auction::where('group_id', $group->id)->delete();
        });
    }

    public function scheme()
    {
        return $this->belongsTo(ChitScheme::class, 'scheme_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function members()
    {
        return $this->hasMany(GroupMember::class, 'group_id');
    }

    public function validMembers()
    {
        return $this->members()->whereNotIn('status', GroupMember::INACTIVE_STATUSES);
    }

    public function getValidMembersCountAttribute(): int
    {
        if ($this->relationLoaded('validMembers')) {
            return $this->validMembers->count();
        }

        if ($this->relationLoaded('members')) {
            return $this->members->whereNotIn('status', GroupMember::INACTIVE_STATUSES)->count();
        }

        return (int) $this->validMembers()->count();
    }

    /**
     * Seats occupied by valid members (sum of share%/100). Multi-seat = 500% counts as 5.
     */
    public function occupiedSeats(?int $exceptMemberId = null): float
    {
        $members = null;

        if ($this->relationLoaded('validMembers')) {
            $members = $this->validMembers;
        } elseif ($this->relationLoaded('members')) {
            $members = $this->members->whereNotIn('status', GroupMember::INACTIVE_STATUSES)->values();
        }

        if ($members !== null) {
            return round(
                $members
                    ->when($exceptMemberId, fn ($c) => $c->where('id', '!=', $exceptMemberId))
                    ->sum(fn (GroupMember $m) => $m->seatEquivalentCount()),
                4
            );
        }

        $query = $this->validMembers();
        if ($exceptMemberId) {
            $query->where('id', '!=', $exceptMemberId);
        }

        return round(
            $query->get()->sum(fn (GroupMember $m) => $m->seatEquivalentCount()),
            4
        );
    }

    public function remainingSeats(?int $exceptMemberId = null): float
    {
        return max(0, round((float) $this->total_members - $this->occupiedSeats($exceptMemberId), 4));
    }

    /**
     * Display label for seat fill, e.g. "5/20" or "4.5/20" (400% + 50% shares).
     */
    public function seatsFillLabel(?int $exceptMemberId = null): string
    {
        $filled = $this->occupiedSeats($exceptMemberId);
        $total = (int) $this->total_members;
        $filledLabel = abs($filled - round($filled)) < 0.001
            ? (string) (int) round($filled)
            : rtrim(rtrim(number_format($filled, 2, '.', ''), '0'), '.');

        return $filledLabel . '/' . $total;
    }

    /**
     * Progress percent for seat fill bar (0–100).
     */
    public function seatsFillPercent(?int $exceptMemberId = null): float
    {
        $total = (float) $this->total_members;
        if ($total <= 0) {
            return 0.0;
        }

        return min(100, round(($this->occupiedSeats($exceptMemberId) / $total) * 100, 2));
    }

    /**
     * True when at least one membership holds more than one seat (share > 100%).
     */
    public function hasMultiSeatMembers(): bool
    {
        $members = $this->relationLoaded('validMembers')
            ? $this->validMembers
            : ($this->relationLoaded('members')
                ? $this->members->whereNotIn('status', GroupMember::INACTIVE_STATUSES)
                : $this->validMembers()->get());

        return $members->contains(fn (GroupMember $m) => $m->seatEquivalentCount() > 1.001);
    }

    public function getMembersCountAttribute(): int
    {
        if (array_key_exists('members_count', $this->attributes)) {
            return (int) $this->attributes['members_count'];
        }

        // Prefer seat occupancy for fill metrics; keep at least enrollment count.
        return (int) max($this->valid_members_count, (int) ceil($this->occupiedSeats()));
    }

    public function getIsFullAttribute(): bool
    {
        return $this->occupiedSeats() >= (float) $this->total_members - 0.0001;
    }

    public function activeMembers()
    {
        return $this->members()->whereIn('status', ['active', 'approved']);
    }

    public function groupLeader()
    {
        return $this->belongsTo(Client::class, 'group_leader_id');
    }

    public function installments()
    {
        return $this->hasMany(Installment::class, 'group_id');
    }

    public function auctions()
    {
        return $this->hasMany(Auction::class, 'group_id');
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class, 'group_id');
    }

    public function dividends()
    {
        return $this->hasMany(Dividend::class, 'group_id');
    }

    public function dividendPoolEntries()
    {
        return $this->hasMany(ChitDividendPoolEntry::class, 'group_id')->latest('id');
    }

    /**
     * Effective chit value for a settlement seat (scaled by share %).
     * Supports multi-seat cumulative shares (e.g. 500% => 5 × chit value).
     */
    public function effectiveChitValueForPayout(float $sharePercentage = 100.0, ?float $chitValue = null): float
    {
        $base = $chitValue ?? (float) $this->chit_value;
        $pct = max(0, $sharePercentage);

        return round($base * ($pct / 100), 2);
    }

    /**
     * Difference vs chit value: positive = surplus to pool, negative = draw from pool.
     *
     * @return array{diff: float, effective_chit: float, payout_amount: float}
     */
    public function dividendPoolDeltaForPayout(float $payoutAmount, float $sharePercentage = 100.0, ?float $chitValue = null): array
    {
        $effectiveChit = $this->effectiveChitValueForPayout($sharePercentage, $chitValue);
        $payoutAmount = round($payoutAmount, 2);

        return [
            'diff' => round($effectiveChit - $payoutAmount, 2),
            'effective_chit' => $effectiveChit,
            'payout_amount' => $payoutAmount,
        ];
    }

    /**
     * Apply paid settlement to group dividend pool.
     * payout < chit → credit surplus; payout > chit → debit excess from pool.
     */
    public function applySettlementToDividendPool(Payout $payout, ?int $createdBy = null): ?ChitDividendPoolEntry
    {
        if ((int) $payout->group_id !== (int) $this->id) {
            return null;
        }

        if ($payout->status !== 'paid') {
            return null;
        }

        // Avoid double-posting for the same payout.
        if (ChitDividendPoolEntry::where('payout_id', $payout->id)->exists()) {
            return ChitDividendPoolEntry::where('payout_id', $payout->id)->first();
        }

        $sharePct = (float) ($payout->share_percentage ?? 100);
        if ($sharePct <= 0) {
            $sharePct = 100;
        }

        $delta = $this->dividendPoolDeltaForPayout(
            (float) $payout->payout_amount,
            $sharePct,
            (float) ($payout->chit_value ?? $this->chit_value)
        );

        if (abs($delta['diff']) < 0.01) {
            return null;
        }

        $group = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
        $balance = (float) $group->dividend_pool_balance;

        if ($delta['diff'] > 0) {
            $amount = $delta['diff'];
            $entryType = ChitDividendPoolEntry::TYPE_CREDIT;
            $balanceAfter = round($balance + $amount, 2);
            $remarks = sprintf(
                'Month %d surplus: chit ₹%s − payout ₹%s → pool +₹%s',
                (int) $payout->month_number,
                number_format($delta['effective_chit'], 2),
                number_format($delta['payout_amount'], 2),
                number_format($amount, 2)
            );
        } else {
            $amount = abs($delta['diff']);
            $entryType = ChitDividendPoolEntry::TYPE_DEBIT;
            $balanceAfter = round($balance - $amount, 2);
            $remarks = sprintf(
                'Month %d excess payout: payout ₹%s − chit ₹%s → pool −₹%s',
                (int) $payout->month_number,
                number_format($delta['payout_amount'], 2),
                number_format($delta['effective_chit'], 2),
                number_format($amount, 2)
            );
        }

        $group->update(['dividend_pool_balance' => $balanceAfter]);
        $this->dividend_pool_balance = $balanceAfter;

        return ChitDividendPoolEntry::create([
            'group_id' => $group->id,
            'payout_id' => $payout->id,
            'month_number' => $payout->month_number,
            'entry_type' => $entryType,
            'amount' => $amount,
            'chit_value' => $delta['effective_chit'],
            'payout_amount' => $delta['payout_amount'],
            'balance_after' => $balanceAfter,
            'remarks' => $remarks,
            'created_by' => $createdBy,
        ]);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'forming' => 'info',
            'completed' => 'primary',
            'terminated' => 'danger',
            default => 'secondary',
        };
    }

    public function getSchemeTypeLabelAttribute(): string
    {
        return match ($this->scheme_type) {
            'fixed' => 'Fixed Chit',
            'auction' => 'Auction-Based',
            'group_based' => 'Group-Based (Private)',
            default => ucfirst($this->scheme_type ?? 'auction'),
        };
    }

    public function getSchemeTypeBadgeColorAttribute(): string
    {
        return match ($this->scheme_type) {
            'fixed' => 'primary',
            'auction' => 'warning',
            'flexible' => 'info',
            'fixed_return' => 'success',
            'daily_weekly' => 'danger',
            'group_based' => 'dark',
            default => 'secondary',
        };
    }

    public function supportsAuction(): bool
    {
        return in_array($this->scheme_type, ['auction', 'flexible']);
    }

    public function usesRotationPayout(): bool
    {
        return in_array($this->scheme_type, ['fixed', 'fixed_return', 'group_based']);
    }

    public function getNextSettlementMonth(): int
    {
        return (int) $this->current_month + 1;
    }

    /**
     * Calendar date for a chit period (same rule as installment due months).
     */
    public function periodDate(int $monthNumber): ?\Carbon\Carbon
    {
        if ($monthNumber < 1 || ! $this->start_date) {
            return null;
        }

        $base = \Carbon\Carbon::parse($this->start_date)->startOfDay();
        $freq = $this->installment_frequency ?? 'monthly';

        return match ($freq) {
            'daily' => $base->copy()->addDays($monthNumber - 1),
            'weekly' => \App\Support\CalendarWeek::addWeeks($base, $monthNumber - 1),
            default => $base->copy()->addMonthsNoOverflow($monthNumber - 1),
        };
    }

    /**
     * Chit period number that contains the given date.
     */
    public function periodNumberForDate($date): ?int
    {
        if (! $date || ! $this->start_date) {
            return null;
        }

        $target = \Carbon\Carbon::parse($date)->startOfDay();
        $freq = $this->installment_frequency ?? 'monthly';
        $total = max(1, (int) $this->total_months);

        for ($m = 1; $m <= $total; $m++) {
            $periodDate = $this->periodDate($m);
            if (! $periodDate) {
                continue;
            }

            $matches = match ($freq) {
                'daily' => $periodDate->isSameDay($target),
                'weekly' => $periodDate->toDateString() === $target->copy()->startOfWeek()->toDateString()
                    || ($periodDate->lte($target) && $periodDate->copy()->addDays(6)->gte($target)),
                default => $periodDate->year === $target->year && $periodDate->month === $target->month,
            };

            if ($matches) {
                return $m;
            }
        }

        return null;
    }

    /**
     * Human label for a chit period (e.g. "April 2026").
     */
    public function periodCalendarLabel(int $monthNumber): string
    {
        if ($monthNumber < 1) {
            return '—';
        }

        $date = $this->periodDate($monthNumber);
        if (! $date) {
            return 'Month ' . $monthNumber;
        }

        $freq = $this->installment_frequency ?? 'monthly';

        return match ($freq) {
            'daily' => $date->format('d M Y'),
            'weekly' => 'Week of ' . $date->format('d M Y'),
            default => $date->format('F Y'),
        };
    }

    /**
     * Inclusive end date for an N-period chit (same rule as installment month N).
     * Example: start March 2026, 22 months → December 2027.
     */
    public static function calculateEndDate(
        \Carbon\Carbon $startDate,
        int $totalMonths,
        string $frequency = 'monthly'
    ): \Carbon\Carbon {
        $totalMonths = max(1, $totalMonths);
        $base = $startDate->copy()->startOfDay();

        return match ($frequency) {
            'daily' => $base->copy()->addDays(($totalMonths * 30) - 1),
            'weekly' => \App\Support\CalendarWeek::addWeeks($base, $totalMonths - 1),
            default => $base->copy()->addMonthsNoOverflow($totalMonths - 1),
        };
    }

    /**
     * Recalculate and persist end_date from start_date + total_months.
     */
    public function syncEndDate(bool $save = true): ?\Carbon\Carbon
    {
        if (! $this->start_date || (int) $this->total_months < 1) {
            return null;
        }

        $end = self::calculateEndDate(
            \Carbon\Carbon::parse($this->start_date),
            (int) $this->total_months,
            $this->installment_frequency ?? 'monthly'
        );

        $this->end_date = $end->toDateString();

        if ($save && $this->exists) {
            $dirty = $this->isDirty('end_date');
            if ($dirty) {
                $this->saveQuietly();
            }
        }

        return $end;
    }

    /**
     * True when the group's scheduled last month is already behind the current month.
     */
    public function hasPassedEndDate(): bool
    {
        $end = $this->end_date;
        if (! $end) {
            $this->syncEndDate(false);
            $end = $this->end_date;
        }
        if (! $end) {
            return false;
        }

        $endDate = \Carbon\Carbon::parse($end)->startOfDay();
        if (in_array($this->installment_frequency ?? 'monthly', ['weekly', 'daily'], true)) {
            return $endDate->lt(now()->startOfDay());
        }

        return $endDate->copy()->startOfMonth()->lt(now()->startOfMonth());
    }

    public function getStartMonthLabelAttribute(): string
    {
        return $this->periodCalendarLabel(1);
    }

    public function getEndMonthLabelAttribute(): string
    {
        return $this->periodCalendarLabel(max(1, (int) $this->total_months));
    }

    /**
     * Which period number "today" falls in, based on group start_date.
     */
    public function resolveCalendarPeriodNumber(?\Carbon\Carbon $asOf = null): int
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $total = max(1, (int) $this->total_months);

        if (! $this->start_date) {
            return max(1, min($total, (int) ($this->current_month ?: 1)));
        }

        $start = \Carbon\Carbon::parse($this->start_date)->startOfDay();
        if ($asOf->lt($start)) {
            return 1;
        }

        $freq = $this->installment_frequency ?? 'monthly';
        $elapsed = match ($freq) {
            'daily' => $start->diffInDays($asOf) + 1,
            'weekly' => (int) floor($start->diffInDays($asOf) / 7) + 1,
            default => $start->copy()->startOfMonth()->diffInMonths($asOf->copy()->startOfMonth()) + 1,
        };

        return max(1, min($total, (int) $elapsed));
    }

    public function isPeriodFullyCollected(int $monthNumber): bool
    {
        $rows = $this->installments()
            ->forEnrolledMembers()
            ->where('month_number', $monthNumber)
            ->get();

        if ($rows->isEmpty()) {
            return false;
        }

        return $rows->every(fn (Installment $inst) => (float) $inst->balance <= 0.01
            || in_array($inst->status, ['paid', 'waived'], true));
    }

    /**
     * Mark past unpaid periods overdue, then set current_month to the period
     * that should be collected (earliest incomplete through calendar month,
     * or next month when the calendar month is fully collected).
     */
    public function syncOperatingMonth(): int
    {
        if ($this->status !== 'active') {
            return max(1, (int) ($this->current_month ?: 1));
        }

        $calendar = $this->resolveCalendarPeriodNumber();
        $total = max(1, (int) $this->total_months);

        Installment::query()
            ->where('group_id', $this->id)
            ->where('status', 'pending')
            ->where(function ($q) use ($calendar) {
                $q->whereDate('due_date', '<', today())
                    ->orWhere('month_number', '<', $calendar);
            })
            ->update(['status' => 'overdue']);

        $operating = $calendar;
        for ($m = 1; $m <= $calendar; $m++) {
            if (! $this->isPeriodFullyCollected($m)) {
                $operating = $m;
                break;
            }
            $operating = $m;
        }

        if ($this->isPeriodFullyCollected($operating) && $operating < $total) {
            $operating = min($total, $operating + 1);
        }

        $operating = max(1, min($total, $operating));

        if ((int) $this->current_month !== $operating) {
            $this->forceFill(['current_month' => $operating])->saveQuietly();
        }

        return $operating;
    }

    /**
     * @return array<int, array{month:int,label:string}>
     */
    public function periodFilterOptions(): array
    {
        $options = [];
        $total = max(1, (int) $this->total_months);
        for ($m = 1; $m <= $total; $m++) {
            $options[] = [
                'month' => $m,
                'label' => 'Month ' . $m . ' — ' . $this->periodCalendarLabel($m),
            ];
        }

        return $options;
    }

    public function settlementForMonth(int $monthNumber): ?Payout
    {
        return $this->payouts
            ->first(fn (Payout $payout) => (int) $payout->month_number === $monthNumber && $payout->isOriginal())
            ?? $this->payouts()
                ->where('month_number', $monthNumber)
                ->where('payout_kind', Payout::KIND_ORIGINAL)
                ->whereNotIn('status', ['cancelled'])
                ->first();
    }

    public function getCurrentMonthSettlement(): ?Payout
    {
        return $this->settlementForMonth($this->getNextSettlementMonth());
    }

    public function paidSettlementsCount(): int
    {
        return $this->payouts()
            ->where('status', 'paid')
            ->where('payout_kind', Payout::KIND_ORIGINAL)
            ->count();
    }

    public function isRegistered(): bool
    {
        $type = $this->registration_type ?? $this->scheme?->registration_type;

        return $type === ChitScheme::REGISTRATION_REGISTERED;
    }

    public function allowsAdvancePayouts(): bool
    {
        return !$this->isRegistered();
    }

    public function getRegistrationTypeLabelAttribute(): string
    {
        return $this->isRegistered() ? 'Registered' : 'Non-Registered';
    }

    /**
     * Whether all chit months have a paid settlement (or current month has reached the end).
     */
    public function isFullySettled(): bool
    {
        if ((int) $this->current_month >= (int) $this->total_months) {
            return true;
        }

        return $this->paidSettlementsCount() >= (int) $this->total_months;
    }

    /**
     * Groups that can still accept new members.
     */
    public function scopeOpenForEnrollment($query)
    {
        return $query->whereIn('status', ['forming', 'active']);
    }

    public function isOpenForEnrollment(): bool
    {
        return in_array((string) $this->status, ['forming', 'active'], true);
    }

    /**
     * Active groups can be closed at any time while the chit is running.
     */
    public function canBeClosed(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Flip leftover active/approved seats to completed once the group itself is finished.
     */
    public function markActiveMembersCompleted(): int
    {
        return $this->members()
            ->whereIn('status', ['active', 'approved', 'applied', 'frozen'])
            ->update(['status' => 'completed']);
    }

    public function closeBlockReason(): ?string
    {
        if ($this->status === 'completed') {
            return 'This group is already closed.';
        }

        if ($this->status === 'terminated') {
            return 'This group was terminated and cannot be closed again.';
        }

        if ($this->status !== 'active') {
            return 'Only active groups can be closed. Activate the group first.';
        }

        return null;
    }

    public function periodUnitLabel(): string
    {
        return $this->scheme?->periodUnitLabel() ?? match ($this->installment_frequency ?? 'monthly') {
            'weekly' => 'Week',
            'daily' => 'Day',
            default => 'Month',
        };
    }

    public function periodUnitLabelPlural(): string
    {
        return $this->scheme?->periodUnitLabelPlural() ?? match ($this->installment_frequency ?? 'monthly') {
            'weekly' => 'Weeks',
            'daily' => 'Days',
            default => 'Months',
        };
    }

    public function closeProgressLabel(): string
    {
        $paid = $this->paidSettlementsCount();
        $total = (int) $this->total_months;

        $unit = $this->periodUnitLabel();

        return "Settlements {$paid}/{$total} · {$unit} {$this->current_month}/{$total}";
    }

    /**
     * Schedule installment for APIs (no dividend / chit-value equal-split).
     */
    public function apiInstallmentAmount(?int $monthNumber = null): float
    {
        $month = max(1, (int) ($monthNumber ?? $this->current_month ?? 1));

        return $this->getInstallmentAmountForMonth($month);
    }

    public function getInstallmentAmountForMonth(int $monthNumber): float
    {
        $entry = $this->scheduleEntryForMonth($monthNumber);
        $fallback = (float) $this->installment_amount;

        if ($entry && isset($entry['installment_amount'])) {
            $scheduled = (float) $entry['installment_amount'];

            // Foreman month must collect the normal per-client installment.
            // Older schedules sometimes stored the full chit value there by mistake.
            if ($this->isForemanCommissionMonth($monthNumber)) {
                $chitValue = (float) $this->chit_value;
                if ($scheduled <= 0
                    || ($chitValue > 0 && abs($scheduled - $chitValue) < 0.01)
                    || ($chitValue > 0 && $scheduled > $chitValue * 0.9)
                ) {
                    return $fallback > 0 ? $fallback : $scheduled;
                }
            }

            return $scheduled;
        }

        return $fallback;
    }

    /**
     * Calculate the monthly dividend amount for a given month number.
     * Checks recorded dividends, completed auctions, or falls back to scheme schedule difference.
     *
     * @param int $monthNumber
     * @param GroupMember|null $member
     * @param int|null $clientId
     * @return float
     */
    public function getMonthlyDividendAmount(int $monthNumber, ?GroupMember $member = null, ?int $clientId = null): float
    {
        $dividend = $this->relationLoaded('dividends')
            ? $this->dividends->firstWhere('month_number', $monthNumber)
            : $this->dividends()->where('month_number', $monthNumber)->first();

        $fullMemberDiv = 0.0;

        if ($dividend && (float) $dividend->per_member_dividend > 0) {
            $fullMemberDiv = (float) $dividend->per_member_dividend;
        } else {
            $auction = $this->relationLoaded('auctions')
                ? $this->auctions->firstWhere('month_number', $monthNumber)
                : $this->auctions()->where('month_number', $monthNumber)->first();

            if ($auction && (float) $auction->discount > 0) {
                $comm = round((float) $this->chit_value * (float) $this->commission_pct / 100, 2);
                $netDiv = max(0, (float) $auction->discount - $comm);
                $totalM = (int) ($this->total_members ?: 1);
                $fullMemberDiv = round($netDiv / $totalM, 2);
            } else {
                if ($this->chit_value > 0 && $this->total_months > 0 && ! $this->isForemanCommissionMonth($monthNumber)) {
                    $stdInstallment = round((float) $this->chit_value / (float) $this->total_months, 2);
                    $schedInstallment = (float) $this->getInstallmentAmountForMonth($monthNumber);
                    if ($stdInstallment > $schedInstallment) {
                        $fullMemberDiv = round($stdInstallment - $schedInstallment, 2);
                    }
                }
            }
        }

        if ($member && $clientId) {
            return $member->amountForClient($fullMemberDiv, $clientId);
        }

        if ($member) {
            $sharePct = (float) ($member->effective_share_percentage ?? 100);
            return round($fullMemberDiv * ($sharePct / 100.0), 2);
        }

        return $fullMemberDiv;
    }

    public function isForemanCommissionMonth(int $monthNumber): bool
    {
        return $this->scheme
            ? $this->scheme->isForemanCommissionMonth($monthNumber)
            : $monthNumber === 1;
    }

    public function foremanCommissionMonth(): int
    {
        return $this->scheme?->foremanCommissionMonth() ?? 1;
    }

    /**
     * Foreman commission is based on valid seats active in this group.
     */
    public function foremanCommissionMemberCount(): int
    {
        if ($this->relationLoaded('members')) {
            $c = $this->members
                ->whereNotIn('status', GroupMember::INACTIVE_STATUSES)
                ->count();
            return $c > 0 ? $c : (int) ($this->total_members ?: 1);
        }

        $c = (int) $this->validMembers()->count();
        return $c > 0 ? $c : (int) ($this->total_members ?: 1);
    }

    /**
     * Active / valid members used for foreman commission (share-aware).
     *
     * @return \Illuminate\Support\Collection<int, GroupMember>
     */
    public function foremanCommissionMembers()
    {
        if ($this->relationLoaded('members')) {
            return $this->members
                ->whereNotIn('status', GroupMember::INACTIVE_STATUSES)
                ->values();
        }

        return $this->validMembers()->get();
    }

    public function usesClientWiseForemanCommission(): bool
    {
        return (bool) $this->scheme?->usesClientWiseForemanCommission();
    }

    public function clientWiseForemanExtraForShare(int $monthNumber, float $sharePct = 100.0): float
    {
        return $this->scheme
            ? $this->scheme->clientWiseForemanExtraForShare($monthNumber, $sharePct)
            : 0.0;
    }

    /**
     * Total foreman commission = sum of each active member's share-scaled
     * installment for the foreman-commission month, or configured commission percentage.
     * Client-wise mode (month 0): fixed amount × members actually added.
     */
    public function foremanCommissionAmount(): float
    {
        $members = $this->foremanCommissionMembers();

        if ($this->usesClientWiseForemanCommission()) {
            $per = $this->scheme->clientWiseForemanAmount();
            $total = 0.0;
            foreach ($members as $member) {
                $pct = max(0.0, (float) $member->effective_share_percentage);
                $total += round($per * ($pct / 100.0), 2);
            }

            return round($total, 2);
        }

        $month = $this->foremanCommissionMonth();
        $total = 0.0;

        foreach ($members as $member) {
            $inst = null;
            if ($member->relationLoaded('installments')) {
                $inst = $member->installments
                    ->where('group_id', $this->id)
                    ->firstWhere('month_number', $month);
            }

            if ($inst) {
                $total += $member->displayAmountForInstallment($inst);
            } else {
                $total += $member->seatInstallmentAmount($this, $month);
            }
        }

        if ($total <= 0) {
            if ($this->chit_value > 0 && $this->commission_pct > 0) {
                $total = (float) $this->chit_value * ((float) $this->commission_pct / 100.0);
            } else if ($this->installment_amount > 0 && $this->total_members > 0) {
                $total = (float) $this->installment_amount * (int) $this->total_members;
            }
        }

        return round($total, 2);
    }

    /**
     * FC extra actually collected (client-wise month 0). Extra is recognised
     * only after the regular installment portion of that month is paid.
     */
    public function collectedForemanCommissionAmount(): float
    {
        if ($this->usesClientWiseForemanCommission()) {
            $month = $this->scheme->clientWiseForemanCollectionMonth();
            if ($month < 1) {
                return 0.0;
            }

            $rows = $this->installments()
                ->forEnrolledMembers()
                ->where('month_number', $month)
                ->with('member')
                ->get();

            $total = 0.0;
            foreach ($rows as $inst) {
                $pct = (float) ($inst->share_percentage ?? $inst->member?->effective_share_percentage ?? 100);
                $extra = $this->clientWiseForemanExtraForShare($month, $pct);
                if ($extra <= 0.009) {
                    continue;
                }
                $regular = max(0.0, round((float) $inst->amount - $extra, 2));
                $paid = (float) $inst->paid_amount;
                $total += max(0.0, min($extra, round($paid - $regular, 2)));
            }

            return round($total, 2);
        }

        $month = $this->foremanCommissionMonth();
        if ($month < 1) {
            return 0.0;
        }

        return round((float) $this->installments()
            ->forEnrolledMembers()
            ->where('month_number', $month)
            ->sum('paid_amount'), 2);
    }

    /**
     * Amount recognised on the group for balance / revenue.
     * Client-wise (month 0): only after the extra is collected.
     */
    public function recognizedForemanCommissionAmount(): float
    {
        if ($this->usesClientWiseForemanCommission()) {
            return $this->collectedForemanCommissionAmount();
        }

        return $this->foremanCommissionAmount();
    }

    /**
     * Summary payload for group-view Foreman Commission cards.
     *
     * @return array{month:int,base_installment:float,member_count:int,amount:float,expected_amount:float,collected_amount:float,pending_amount:float,uses_share_scaling:bool,client_wise:bool,per_member:float}
     */
    public function foremanCommissionSummary(): array
    {
        $members = $this->foremanCommissionMembers();
        $memberCount = $members->count();
        $usesShareScaling = $members->contains(
            fn (GroupMember $m) => $m->hasNonStandardShare()
        );
        $expected = $this->foremanCommissionAmount();
        $collected = $this->collectedForemanCommissionAmount();
        $pending = round(max(0, $expected - $collected), 2);

        if ($this->usesClientWiseForemanCommission()) {
            $per = $this->scheme->clientWiseForemanAmount();
            $collectMonth = $this->scheme->clientWiseForemanCollectionMonth();

            return [
                'month' => $collectMonth,
                'installment' => $per,
                'base_installment' => $per,
                'member_count' => $memberCount,
                'amount' => $collected,
                'expected_amount' => $expected,
                'collected_amount' => $collected,
                'pending_amount' => $pending,
                'uses_share_scaling' => $usesShareScaling,
                'client_wise' => true,
                'per_member' => $per,
            ];
        }

        $month = $this->foremanCommissionMonth();
        $baseInstallment = (float) $this->getInstallmentAmountForMonth($month);
        if ($memberCount === 0) {
            $memberCount = (int) ($this->total_members ?: 1);
        }

        return [
            'month' => $month,
            'installment' => $baseInstallment,
            'base_installment' => $baseInstallment,
            'member_count' => $memberCount,
            'amount' => $expected,
            'expected_amount' => $expected,
            'collected_amount' => $collected,
            'pending_amount' => $pending,
            'uses_share_scaling' => $usesShareScaling,
            'client_wise' => false,
            'per_member' => $baseInstallment,
        ];
    }

    /**
     * Re-apply scheme installment amounts (including client-wise FC extra)
     * on unpaid rows when the scheme is edited.
     */
    public function resyncUnpaidInstallmentAmounts(): void
    {
        if (! $this->start_date) {
            return;
        }

        $start = \Carbon\Carbon::parse($this->start_date);
        foreach ($this->validMembers()->get() as $member) {
            $this->generateInstallmentsForMember($member, $start);
        }
    }

    public function installmentAmountDisplayForMonth(int $monthNumber): string
    {
        return '₹' . number_format($this->getInstallmentAmountForMonth($monthNumber), 2);
    }

    public function getScheduledPayoutAmountForMonth(int $monthNumber): ?float
    {
        $entry = $this->scheduleEntryForMonth($monthNumber);

        if (!$entry) {
            return null;
        }

        $val = $entry['payout_amount'] ?? $entry['payout'] ?? $entry['settlement_amount'] ?? null;

        if ($val === '' || $val === null) {
            $monthInstallment = isset($entry['installment_amount']) ? (float) $entry['installment_amount'] : (float) $this->getInstallmentAmountForMonth($monthNumber);
            $totalMembers = (int) ($this->total_members ?: ($this->scheme?->total_members ?: 0));
            $commission = round((float) $this->chit_value * (float) $this->commission_pct / 100, 2);

            if ($monthInstallment > 0 && $totalMembers > 0) {
                return round(max(0, ($monthInstallment * $totalMembers) - $commission), 2);
            }
            return null;
        }

        $clean = preg_replace('/[^\d.]/', '', (string) $val);

        if ($clean !== '') {
            return round((float) $clean, 2);
        }

        if ($this->isForemanCommissionMonth($monthNumber) || str_contains(strtolower((string) $val), 'foreman')) {
            return 0.0;
        }

        return null;
    }

    public function resolvePayoutAmountForMonth(int $monthNumber, ?float $winningBid = null): float
    {
        $commission = round((float) $this->chit_value * (float) $this->commission_pct / 100, 2);

        // 1. If an auction winning bid is explicitly passed, use winning_bid - commission
        if ($winningBid !== null && $winningBid > 0 && $this->supportsAuction()) {
            return round(max(0, $winningBid - $commission), 2);
        }

        // 2. Always prioritize the scheme's scheduled payout amount for this month if defined/calculated from scheme
        $scheduled = $this->getScheduledPayoutAmountForMonth($monthNumber);
        if ($scheduled !== null) {
            return $scheduled;
        }

        // 3. Fallback: month payout from that month's installment × total members − commission
        $monthInstallment = $this->getInstallmentAmountForMonth($monthNumber);
        if ($monthInstallment > 0 && (int) $this->total_members > 0) {
            return round(max(0, ($monthInstallment * (int) $this->total_members) - $commission), 2);
        }

        if ($this->fixed_return_amount) {
            return round((float) $this->fixed_return_amount, 2);
        }

        if ($winningBid !== null && $winningBid > 0) {
            return round(max(0, $winningBid - $commission), 2);
        }

        return round(max(0, (float) $this->chit_value - $commission), 2);
    }

    protected function scheduleEntryForMonth(int $monthNumber): ?array
    {
        $scheme = $this->scheme;
        if (!$scheme && $this->scheme_id) {
            $scheme = \App\Models\ChitScheme::find($this->scheme_id);
        }

        $payoutSchedule = $scheme?->payout_schedule;

        if (empty($payoutSchedule) || !is_array($payoutSchedule)) {
            return null;
        }

        foreach ($payoutSchedule as $item) {
            $no = $item['installment_no'] ?? $item['month'] ?? $item['month_number'] ?? null;
            if ($no !== null && (int) $no === $monthNumber) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Generate installment schedule from the group start date (inclusive of start month).
     * Unlike loans (first EMI usually next month), chit month 1 is due on start_date itself.
     */
    public function generateInstallmentsForMember(GroupMember $member, \Carbon\Carbon $startDate): void
    {
        // Always align to group start_date — never member joined_date (that skipped the start month like loans).
        $baseDate = $this->start_date
            ? \Carbon\Carbon::parse($this->start_date)->startOfDay()
            : $startDate->copy()->startOfDay();
        $frequency = $this->installment_frequency ?? 'monthly';
        $totalPeriods = (int) ($this->total_months ?: $this->scheme?->duration_months ?: 0);

        if ($totalPeriods < 1) {
            return;
        }

        switch ($frequency) {
            case 'daily':
                $totalDays = $totalPeriods * 30;
                for ($day = 0; $day < $totalDays; $day++) {
                    $dueDate = $baseDate->copy()->addDays($day);
                    $amount = $this->getInstallmentAmountForMonth($day + 1);
                    $this->upsertMemberInstallment($member, $day + 1, $dueDate, $amount);
                }
                break;

            case 'weekly':
                $previousDue = null;
                for ($week = 1; $week <= $totalPeriods; $week++) {
                    $dueDate = \App\Support\CalendarWeek::addWeeks($baseDate, $week - 1);
                    if ($previousDue) {
                        $dueDate = \App\Support\CalendarWeek::fixSkippedYear($previousDue, $dueDate);
                    }
                    $amount = $this->getInstallmentAmountForMonth($week);
                    $this->upsertMemberInstallment($member, $week, $dueDate, $amount);
                    $previousDue = $dueDate->copy()->startOfDay();
                }
                break;

            case 'monthly':
            default:
                $previousDue = null;
                for ($month = 1; $month <= $totalPeriods; $month++) {
                    // Month 1 = start month (addMonths(0)); not next month like loan EMI.
                    $dueDate = $baseDate->copy()->addMonthsNoOverflow($month - 1);
                    if ($previousDue) {
                        $dueDate = \App\Support\CalendarWeek::fixSkippedYear($previousDue, $dueDate);
                    }
                    $amount = $this->getInstallmentAmountForMonth($month);
                    $this->upsertMemberInstallment($member, $month, $dueDate, $amount);
                    $previousDue = $dueDate->copy()->startOfDay();
                }
                break;
        }

        // Past due rows for this new member should show as overdue immediately.
        Installment::query()
            ->where('member_id', $member->id)
            ->where('group_id', $this->id)
            ->where('status', 'pending')
            ->whereDate('due_date', '<', today())
            ->update(['status' => 'overdue']);
    }

    /**
     * Create pending installment, or correct due date/amount on unpaid ones when regenerating.
     */
    protected function upsertMemberInstallment(GroupMember $member, int $monthNumber, \Carbon\Carbon $dueDate, float $amount): void
    {
        $baseAmount = ($member->custom_installment_amount > 0 && $monthNumber >= ($member->custom_installment_start_month ?? 1))
            ? (float) $member->custom_installment_amount
            : $amount;

        $sharePct = (float) ($member->effective_share_percentage ?? 100.00);
        $memberAmount = round($baseAmount * ($sharePct / 100.0), 2);
        $memberAmount = round($memberAmount + $this->clientWiseForemanExtraForShare($monthNumber, $sharePct), 2);

        $installment = Installment::firstOrNew([
            'group_id' => $this->id,
            'member_id' => $member->id,
            'month_number' => $monthNumber,
        ]);

        if (! $installment->exists) {
            $installment->fill([
                'due_date' => $dueDate,
                'amount' => $memberAmount,
                'share_percentage' => $sharePct,
                'status' => 'pending',
            ]);
            $installment->save();

            return;
        }

        // Fix schedule dates for unpaid rows, and any row whose year skipped (2026 → 2028).
        $yearWrong = $installment->due_date
            && (int) $installment->due_date->year !== (int) $dueDate->year;
        $unpaid = in_array($installment->status, ['pending', 'overdue'], true)
            && (float) $installment->paid_amount <= 0;

        if ($unpaid || $yearWrong) {
            $installment->due_date = $dueDate;
            if ($unpaid) {
            $installment->amount = $memberAmount;
            $installment->share_percentage = $sharePct;
            }
            $installment->save();
        }
    }

    /**
     * Correct installment due dates that jumped a calendar year
     * (e.g. Dec 2026 followed by Jan 2028 instead of Jan 2027).
     */
    public function repairSkippedYearInstallmentDates(): int
    {
        if (! $this->start_date) {
            return 0;
        }

        $start = \Carbon\Carbon::parse($this->start_date)->startOfDay();
        $freq = $this->installment_frequency ?? 'monthly';
        $fixed = 0;

        $byMember = $this->installments()
            ->orderBy('member_id')
            ->orderBy('month_number')
            ->get()
            ->groupBy('member_id');

        foreach ($byMember as $rows) {
            $previous = null;
            foreach ($rows as $installment) {
                $n = max(1, (int) $installment->month_number);
                $expected = match ($freq) {
                    'daily' => $start->copy()->addDays($n - 1),
                    'weekly' => \App\Support\CalendarWeek::addWeeks($start, $n - 1),
                    default => $start->copy()->addMonthsNoOverflow($n - 1),
                };

                $actual = $installment->due_date
                    ? $installment->due_date->copy()->startOfDay()
                    : null;

                if ($previous && $actual) {
                    $actual = \App\Support\CalendarWeek::fixSkippedYear($previous, $actual);
                }

                $yearWrong = $actual && (int) $actual->year !== (int) $expected->year;
                $unpaid = in_array($installment->status, ['pending', 'overdue'], true)
                    && (float) $installment->paid_amount <= 0;

                if ($yearWrong || ($actual && $actual->toDateString() !== $expected->toDateString() && $unpaid && abs($actual->diffInDays($expected)) >= 300)) {
                    $installment->due_date = $expected;
                    $installment->saveQuietly();
                    $fixed++;
                    $actual = $expected->copy();
                } elseif ($actual && $actual->toDateString() !== $installment->due_date->toDateString()) {
                    $installment->due_date = $actual;
                    $installment->saveQuietly();
                    $fixed++;
                }

                $previous = ($actual ?? $expected)->copy()->startOfDay();
            }
        }

        return $fixed;
    }

    /**
     * Calendar months (1-12) mapped to chit period numbers for this group.
     * E.g. start_date January → month 1 = Jan, month 13 = Jan next year.
     *
     * @return array<int, int>
     */
    public function chitMonthsForCalendarMonth(int $calendarMonth): array
    {
        if (!$this->start_date || $calendarMonth < 1 || $calendarMonth > 12) {
            return [];
        }

        $months = [];
        $total = max(1, (int) $this->total_months);

        for ($m = 1; $m <= $total; $m++) {
            $periodDate = $this->periodDate($m);
            if ($periodDate && (int) $periodDate->month === $calendarMonth) {
                $months[] = $m;
            }
        }

        return $months;
    }

    public function getNextAvailableMemberNumber(): int
    {
        $existingNumbers = \App\Models\GroupMember::withTrashed()
            ->where('group_id', $this->id)
            ->where('member_number', '<', 900000)
            ->lockForUpdate()
            ->pluck('member_number')
            ->map(fn ($n) => (int) $n)
            ->toArray();

        $number = 1;
        while (in_array($number, $existingNumbers, true)) {
            $number++;
        }

        return $number;
    }

    public static function generateCode(): string
    {
        $last = self::withTrashed()->latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'GRP' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
