<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupMember extends Model
{
    use SoftDeletes;

    /**
     * Enrollments that no longer hold a seat in the group, so their installments
     * must not be counted towards the group's collection figures.
     */
    public const INACTIVE_STATUSES = ['rejected', 'transferred', 'withdrawn', 'cancelled'];

    /** Seats that can still be transferred to another client / group. */
    public const TRANSFERABLE_STATUSES = ['active', 'approved', 'applied', 'frozen', 'defaulted', 'completed'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($member) {
            // Release the member number to avoid unique constraint issues with soft-deleted records
            $member->member_number = 900000 + (int) $member->id;
            $member->saveQuietly();
        });
    }

    /**
     * Whether Actions → Transfer Member should be a working link.
     */
    public function canBeTransferred(?ChitGroup $group = null): bool
    {
        return $this->transferBlockReason($group) === null;
    }

    /**
     * Human reason Transfer is blocked, or null when transfer is allowed.
     */
    public function transferBlockReason(?ChitGroup $group = null): ?string
    {
        $group ??= $this->group;

        if (in_array($this->status, self::INACTIVE_STATUSES, true)) {
            return 'This membership is already closed.';
        }

        if ($this->status === 'rejected') {
            return 'Rejected applications cannot be transferred.';
        }

        if (! in_array($this->status, self::TRANSFERABLE_STATUSES, true)) {
            return 'This membership status cannot be transferred.';
        }

        if (! $group || $group->status !== 'active') {
            return 'Transfers are allowed only for active chit groups.';
        }

        if ($this->is_shared) {
            return 'Shared memberships cannot be transferred.';
        }

        if ($this->has_won_auction) {
            return 'Member has already received settlement.';
        }

        $hasPaidPayout = $this->relationLoaded('payouts')
            ? $this->payouts->where('status', 'paid')->isNotEmpty()
            : ($group->relationLoaded('payouts')
                ? $group->payouts->where('winner_member_id', $this->id)->where('status', 'paid')->isNotEmpty()
                : $this->payouts()->where('status', 'paid')->exists());

        if ($hasPaidPayout) {
            return 'Member has already received settlement.';
        }

        return null;
    }

    /**
     * Soft-deleted seats (outgoing transfer) must still resolve for settlement routes.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::withTrashed()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    protected $fillable = [
        'group_id',
        'client_id',
        'is_shared',
        'share_percentage',
        'collection_frequency',
        'share_installment',
        'share_interest',
        'share_payout',
        'referred_by_agent_id',
        'referred_by_client_id',
        'member_number',
        'status',
        'joined_date',
        'chit_need_date',
        'chit_need_month',
        'has_won_auction',
        'approved_by',
        'applied_by',
        'approved_at',
        'remarks',
        'penalty_enabled',
        'penalty_type',
        'penalty_value',
        'penalty_grace_days',
        'signed_agreement',
        'transferred_to_member_id',
        'transferred_from_member_id',
        'custom_installment_amount',
        'custom_installment_start_month',
    ];

    protected $casts = [
        'joined_date' => 'date',
        'chit_need_date' => 'date',
        'chit_need_month' => 'integer',
        'approved_at' => 'datetime',
        'has_won_auction' => 'boolean',
        'is_shared' => 'boolean',
        'share_percentage' => 'decimal:2',
        'share_installment' => 'decimal:2',
        'share_interest' => 'decimal:2',
        'share_payout' => 'decimal:2',
        'penalty_enabled' => 'boolean',
        'penalty_value' => 'decimal:2',
        'penalty_grace_days' => 'integer',
    ];

    public function getEffectiveSharePercentageAttribute(): float
    {
        return (float) ($this->share_percentage ?? 100.00);
    }

    /**
     * How many group seats this membership occupies.
     * Independent multi-seat: 500% => 5 seats. Shared co-ownership always occupies 1 seat.
     */
    public function seatEquivalentCount(): float
    {
        if ($this->is_shared) {
            return 1.0;
        }

        return max(0.01, round($this->effective_share_percentage / 100.0, 4));
    }

    /**
     * True when share is not a single full seat (partial OR multi-seat cumulative).
     */
    public function hasNonStandardShare(): bool
    {
        $pct = $this->effective_share_percentage;

        return $pct < 99.999 || $pct > 100.001;
    }

    public static function collectionFrequencies(): array
    {
        return [
            'monthly' => 'Monthly',
            'weekly' => 'Weekly',
            'daily' => 'Daily',
        ];
    }

    public function getCollectionFrequencyAttribute($value): string
    {
        $freq = strtolower((string) ($value ?: 'monthly'));

        return in_array($freq, ['daily', 'weekly', 'monthly'], true) ? $freq : 'monthly';
    }

    public function getCollectionFrequencyLabelAttribute(): string
    {
        return self::collectionFrequencies()[$this->collection_frequency] ?? 'Monthly';
    }

    /**
     * Live window for a monthly installment: from this due date (e.g. 15 Sep)
     * up to — but not including — the next due date (e.g. 15 Oct).
     * Daily/weekly slices are generated inside this window, not the calendar month.
     *
     * @return array{start:\Carbon\Carbon|null,end:\Carbon\Carbon|null,last:\Carbon\Carbon|null,days:int}
     */
    public function collectionWindow($monthDueDate = null, $nextDueDate = null): array
    {
        $start = $this->asCollectionDate($monthDueDate);
        if (! $start) {
            return ['start' => null, 'end' => null, 'last' => null, 'days' => 0];
        }

        $end = $this->asCollectionDate($nextDueDate);
        if (! $end || ! $end->gt($start)) {
            $end = $start->copy()->addMonthsNoOverflow(1);
        }

        $days = max(1, (int) $start->diffInDays($end));
        $last = $end->copy()->subDay();
        if ($last->lt($start)) {
            $last = $start->copy();
            $days = 1;
        }

        return [
            'start' => $start,
            'end' => $end,
            'last' => $last,
            'days' => $days,
        ];
    }

    /**
     * How many collection parts a monthly installment is split into.
     * Daily = actual days from this due date to the next (15 Sep → 15 Oct = 30).
     * Weekly = calendar weeks that cover that same window (ceil days/7).
     */
    public function collectionSplitCount($monthDueDate = null, $nextDueDate = null): int
    {
        $window = $this->collectionWindow($monthDueDate, $nextDueDate);
        $days = $window['days'] > 0 ? $window['days'] : 30;

        return match ($this->collection_frequency) {
            'daily' => $days,
            'weekly' => max(1, (int) ceil($days / 7)),
            default => 1,
        };
    }

    /**
     * Equal daily/weekly slice amount (whole rupees). Remainder is applied on the last part.
     */
    public function collectionSplitAmount(float $monthlyAmount, $monthDueDate = null, $nextDueDate = null): float
    {
        $parts = $this->collectionSplitCount($monthDueDate, $nextDueDate);
        if ($parts <= 1) {
            return round(max(0, $monthlyAmount), 2);
        }

        // Round down to whole rupees so 100.23 → 100; leftover goes to last installment.
        return (float) floor(max(0, $monthlyAmount) / $parts);
    }

    /**
     * Build per-period amounts that sum exactly to the monthly installment.
     * Equal parts are whole-rupee floored; last part gets the remainder.
     *
     * @return array<int, float>
     */
    public function collectionPeriodAmounts(float $monthlyAmount, $monthDueDate = null, $nextDueDate = null): array
    {
        $parts = $this->collectionSplitCount($monthDueDate, $nextDueDate);
        $monthlyAmount = round(max(0, $monthlyAmount), 2);
        if ($parts <= 1) {
            return [$monthlyAmount];
        }

        $base = (float) floor($monthlyAmount / $parts);
        $amounts = [];
        $allocated = 0.0;
        for ($i = 1; $i <= $parts; $i++) {
            if ($i === $parts) {
                $amounts[] = round(max(0, $monthlyAmount - $allocated), 2);
            } else {
                $amounts[] = $base;
                $allocated = round($allocated + $base, 2);
            }
        }

        return $amounts;
    }

    /**
     * Suggested collection amount, capped to remaining balance.
     */
    public function suggestedCollectionAmount(
        float $monthlyAmount,
        ?float $remainingBalance = null,
        $monthDueDate = null,
        $nextDueDate = null
    ): float {
        $split = $this->collectionSplitAmount($monthlyAmount, $monthDueDate, $nextDueDate);
        if ($remainingBalance === null) {
            return $split;
        }

        return round(min($split, max(0, $remainingBalance)), 2);
    }

    protected function asCollectionDate($value): ?\Carbon\Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            $date = $value instanceof \Carbon\Carbon
                ? $value->copy()
                : \Carbon\Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }

        return $date->startOfDay();
    }

    /**
     * Build day/week collection slices nested under a monthly installment.
     * Keeps DB rows monthly (settlements stay month-aligned); UI shows frequency parts.
     *
     * $inProgressAmount is money an agent has collected that an admin has not
     * verified yet. It is allocated after $paidAmount so those slices report
     * in_progress instead of looking uncollected, and it leaves `balance` as the
     * amount still genuinely collectible.
     *
     * @return array<int, array{
     *   index:int,label:string,due_date:\Carbon\Carbon|null,period_end:\Carbon\Carbon|null,amount:float,
     *   paid:float,in_progress:float,balance:float,status:string,is_next:bool,is_current:bool
     * }>
     */
    public function collectionPeriodSchedule(
        $monthDueDate,
        float $displayAmount,
        float $paidAmount,
        float $inProgressAmount = 0.0,
        $nextDueDate = null
    ): array {
        $window = $this->collectionWindow($monthDueDate, $nextDueDate);
        $parts = $this->collectionSplitCount($monthDueDate, $nextDueDate);
        if ($parts <= 1 || $displayAmount <= 0.009) {
            return [];
        }

        $freq = $this->collection_frequency;
        $unitLabel = $freq === 'daily' ? 'Day' : 'Week';
        $periodAmounts = $this->collectionPeriodAmounts($displayAmount, $monthDueDate, $nextDueDate);
        $dueBase = $window['start'];
        $lastDay = $window['last'];
        $today = today();

        $periods = [];
        $paidLeft = round(max(0, $paidAmount), 2);
        $pendingLeft = round(max(0, $inProgressAmount), 2);
        $foundNext = false;

        for ($i = 1; $i <= $parts; $i++) {
            $amount = (float) ($periodAmounts[$i - 1] ?? 0);

            $periodPaid = round(min($amount, max(0, $paidLeft)), 2);
            $paidLeft = round(max(0, $paidLeft - $periodPaid), 2);

            $periodPending = round(min(max(0, $amount - $periodPaid), max(0, $pendingLeft)), 2);
            $pendingLeft = round(max(0, $pendingLeft - $periodPending), 2);

            $balance = round(max(0, $amount - $periodPaid - $periodPending), 2);

            if ($periodPending > 0.009) {
                // Collected by an agent, waiting on admin verification.
                $status = 'in_progress';
            } elseif ($balance <= 0.009 && $periodPaid > 0.009) {
                $status = 'paid';
            } elseif ($periodPaid > 0.009 && $balance > 0.009) {
                $status = 'partial';
            } else {
                $status = 'pending';
            }

            $dueDate = null;
            $periodEnd = null;
            if ($dueBase) {
                if ($freq === 'daily') {
                    $dueDate = $dueBase->copy()->addDays($i - 1);
                    if ($lastDay && $dueDate->gt($lastDay)) {
                        $dueDate = $lastDay->copy();
                    }
                    $periodEnd = $dueDate?->copy();
                } else {
                    $dueDate = \App\Support\CalendarWeek::addWeeks($dueBase, $i - 1);
                    if ($lastDay && $dueDate->gt($lastDay)) {
                        $dueDate = $lastDay->copy();
                    }
                    $periodEnd = $dueDate ? $dueDate->copy()->addDays(6) : null;
                    if ($lastDay && $periodEnd && $periodEnd->gt($lastDay)) {
                        $periodEnd = $lastDay->copy();
                    }
                }
                if ($status === 'pending' && $dueDate && $dueDate->lt($today)) {
                    $status = 'overdue';
                }
            }

            $isCurrent = false;
            if ($dueDate && $periodEnd) {
                $isCurrent = $today->betweenIncluded($dueDate, $periodEnd);
            } elseif ($dueDate) {
                $isCurrent = $today->isSameDay($dueDate);
            }

            $isNext = false;
            if (! $foundNext && $balance > 0.009) {
                $isNext = true;
                $foundNext = true;
            }

            $periods[] = [
                'index' => $i,
                'label' => $unitLabel . ' ' . $i,
                'due_date' => $dueDate,
                'period_end' => $periodEnd,
                'amount' => $amount,
                'paid' => $periodPaid,
                'in_progress' => $periodPending,
                'balance' => $balance,
                'status' => $status,
                'is_next' => $isNext,
                'is_current' => $isCurrent,
            ];
        }

        return $periods;
    }

    /**
     * Seat installment for this member after independent sharing % (before co-owner split).
     */
    public function seatInstallmentAmount(?ChitGroup $group = null, ?int $monthNumber = 1): float
    {
        $group = $group ?? $this->group;
        $mNum = max(1, (int) ($monthNumber ?? 1));

        if ($this->custom_installment_amount > 0 && $mNum >= ($this->custom_installment_start_month ?? 1)) {
            $base = (float) $this->custom_installment_amount;
        } elseif ($this->share_installment !== null && $mNum === 1) {
            return round((float) $this->share_installment, 2);
        } else {
            $base = $group ? (float) $group->getInstallmentAmountForMonth($mNum) : (float) ($this->share_installment ?? 0);
        }

        $sharePct = max(0, $this->effective_share_percentage);
        $shareAmt = round($base * ($sharePct / 100.0), 2);

        if ($group) {
            $shareAmt += $group->clientWiseForemanExtraForShare($mNum, $sharePct);
        }

        return round($shareAmt, 2);
    }

    /**
     * Amount due for an installment row after independent share % (and optional client co-ownership).
     */
    public function displayAmountForInstallment(Installment $installment, ?int $clientId = null): float
    {
        $group = $this->group ?? $installment->group;
        $stored = (float) $installment->amount;
        $sharePct = max(0, (float) ($installment->share_percentage ?? $this->effective_share_percentage));
        $mNum = (int) $installment->month_number;

        $amount = $stored;
        $extra = $group ? $group->clientWiseForemanExtraForShare($mNum, $sharePct) : 0.0;
        $fullExtra = $group ? $group->clientWiseForemanExtraForShare($mNum, 100.0) : 0.0;
        // Re-scale legacy rows that still store the full (1-seat) group installment.
        if ($group && abs($sharePct - 100.0) > 0.001) {
            $full = ($this->custom_installment_amount > 0 && $mNum >= ($this->custom_installment_start_month ?? 1))
                ? (float) $this->custom_installment_amount
                : (float) $group->getInstallmentAmountForMonth($mNum);
            $fullWithExtra = round($full + $fullExtra, 2);
            if ($full > 0 && abs($stored - $full) < 0.05) {
                $amount = round($full * ($sharePct / 100.0), 2);
            } elseif ($full > 0 && $fullExtra > 0 && abs($stored - $fullWithExtra) < 0.05) {
                $amount = round(($full * ($sharePct / 100.0)) + $extra, 2);
            }
        }

        if ($group && $extra > 0.009) {
            $seat = $this->seatInstallmentAmount($group, $mNum);
            if (abs($amount - $seat) > 0.05 && abs($amount + $extra - $seat) < 0.06) {
                $amount = $seat;
            }
        }

        if ($clientId && $this->is_shared) {
            return $this->amountForClient($amount, $clientId);
        }

        return round($amount, 2);
    }

    /**
     * Monthly EMI for a co-owner (or full seat EMI when not shared / no client).
     * Seat EMI applies Independent Sharing %; co-ownership then splits that seat amount.
     */
    public function installmentAmountForClient(?int $clientId = null, ?ChitGroup $group = null, ?int $monthNumber = 1): float
    {
        $seat = $this->seatInstallmentAmount($group, $monthNumber);
        if ($clientId && $this->is_shared) {
            return $this->amountForClient($seat, $clientId);
        }

        return round($seat, 2);
    }

    /**
     * Payable installment for customer/agent APIs: schedule amount only (no dividend / equal-split).
     * Prefers the next unpaid installment row when present.
     */
    public function apiInstallmentAmountForClient(?int $clientId = null, ?ChitGroup $group = null): float
    {
        $group = $group ?? $this->group;
        $installments = $this->relationLoaded('installments')
            ? $this->installments
            : ($this->exists ? $this->installments()->get() : collect());

        $next = $installments
            ->filter(fn ($inst) => ! in_array($inst->status, ['paid', 'waived'], true))
            ->sortBy('month_number')
            ->first();

        if ($next) {
            return $this->displayAmountForInstallment($next, $clientId);
        }

        $month = max(1, (int) ($group?->current_month ?? 1));

        return $this->installmentAmountForClient($clientId, $group, $month);
    }

    /**
     * Replace nested group/scheme equal-split installment with the payable schedule amount.
     */
    public function applyApiInstallmentToArray(array $arr, float $amount): array
    {
        $arr['installment'] = $amount;
        $arr['installment_amount'] = $amount;

        if (isset($arr['group']) && is_array($arr['group'])) {
            $arr['group']['installment_amount'] = $amount;
            if (isset($arr['group']['scheme']) && is_array($arr['group']['scheme'])) {
                $arr['group']['scheme']['installment_amount'] = $amount;
            }
        }

        return $arr;
    }

    /**
     * Penalty portion for a co-owner (full penalty when not shared).
     */
    public function penaltyAmountForClient(float $penaltyAmount, ?int $clientId = null): float
    {
        $penaltyAmount = round(max(0, $penaltyAmount), 2);
        if ($clientId && $this->is_shared) {
            return $this->amountForClient($penaltyAmount, $clientId);
        }

        return $penaltyAmount;
    }

    public function calculateAndStoreShareFields(?ChitGroup $group = null): void
    {
        $group = $group ?? $this->group;
        if (! $group) {
            return;
        }

        $sharePct = $this->effective_share_percentage;
        $ratio = $sharePct / 100.0;

        $schemeInstallment = (float) $group->getInstallmentAmountForMonth(1);
        $schemeStandardPayout = (float) $group->resolvePayoutAmountForMonth(2);
        $stdInstallment = (float) ($group->chit_value && $group->total_months ? ($group->chit_value / $group->total_months) : $schemeInstallment);
        $monthInterest = max(0, $stdInstallment - $schemeInstallment);

        $this->share_installment = round($schemeInstallment * $ratio, 2);
        $this->share_interest = round($monthInterest * $ratio, 2);
        $this->share_payout = round($schemeStandardPayout * $ratio, 2);
        $this->saveQuietly();
    }

    public function referrerAgent()
    {
        return $this->belongsTo(Agent::class, 'referred_by_agent_id');
    }

    /**
     * Assigned collection/sales agent from the primary client record.
     */
    public function getAssignedAgentNameAttribute(): string
    {
        $agent = $this->client?->agent
            ?? $this->shares->first()?->client?->agent;

        return $agent?->agent_name ?? '—';
    }

    public function referrerClient()
    {
        return $this->belongsTo(Client::class, 'referred_by_client_id');
    }

    public function getReferrerAttribute()
    {
        return $this->referrerAgent ?: $this->referrerClient;
    }

    public function group()
    {
        return $this->belongsTo(ChitGroup::class, 'group_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function shares()
    {
        return $this->hasMany(GroupMemberShare::class, 'group_member_id')->orderByDesc('is_primary')->orderBy('id');
    }

    /**
     * Memberships where the client is primary owner or a share holder.
     */
    public function scopeInvolvingClient($query, int $clientId)
    {
        return $query->where(function ($q) use ($clientId) {
            $q->where('client_id', $clientId)
                ->orWhereHas('shares', fn ($s) => $s->where('client_id', $clientId));
        });
    }

    /**
     * Resolve ownership rows. Existing single-owner memberships without child rows = 100%.
     */
    public function resolveOwnershipShares(): Collection
    {
        $shares = $this->relationLoaded('shares') ? $this->shares : $this->shares()->with('client')->get();

        if (! $this->is_shared || $shares->isEmpty()) {
        $chitValue = (float) ($this->group?->chit_value ?? 0);
            $sharePct = (float) ($this->effective_share_percentage ?? $this->share_percentage ?? 100.00);

        return collect([
            new GroupMemberShare([
                'group_member_id' => $this->id,
                'client_id' => $this->client_id,
                    'ownership_percentage' => $sharePct,
                    'share_amount' => round($chitValue * ($sharePct / 100.0), 2),
                'is_primary' => true,
            ]),
        ]);
        }

        return $shares;
    }

    public function ownershipPercentageFor(int $clientId): float
    {
        if (! $this->is_shared) {
            if ((int) $this->client_id === $clientId) {
                return (float) ($this->effective_share_percentage ?? $this->share_percentage ?? 100.0);
            }
            return 0.0;
        }

        $share = $this->resolveOwnershipShares()->firstWhere('client_id', $clientId);

        return $share ? (float) $share->ownership_percentage : 0.0;
    }

    public function involvesClient(int $clientId): bool
    {
        return $this->ownershipPercentageFor($clientId) > 0;
    }

    /**
     * Split an amount across share holders. Remainder cents go to the primary owner.
     *
     * @return array<int, float> client_id => amount
     */
    public function allocateAmount(float $amount): array
    {
        $shares = $this->resolveOwnershipShares();
        $amount = round($amount, 2);
        $allocated = [];
        $running = 0.0;
        $primaryClientId = (int) ($shares->firstWhere('is_primary', true)?->client_id ?? $this->client_id);

        foreach ($shares as $share) {
            $part = round($amount * ((float) $share->ownership_percentage / 100), 2);
            $allocated[(int) $share->client_id] = $part;
            $running = round($running + $part, 2);
        }

        $diff = round($amount - $running, 2);
        if (abs($diff) >= 0.01 && isset($allocated[$primaryClientId])) {
            $allocated[$primaryClientId] = round($allocated[$primaryClientId] + $diff, 2);
        }

        return $allocated;
    }

    public function amountForClient(float $amount, int $clientId): float
    {
        if (! $this->is_shared) {
            if ((int) $this->client_id === $clientId) {
                $pct = (float) ($this->effective_share_percentage ?? $this->share_percentage ?? 100.0);
                return round($amount * ($pct / 100.0), 2);
            }
            return 0.0;
        }

        return $this->allocateAmount($amount)[$clientId] ?? 0.0;
    }

    /**
     * Sync share rows from request payload. Enforces 100% total and unique clients.
     *
     * @param  array<int, array{client_id:int|string, ownership_percentage:float|string}>  $shareRows
     */
    public function syncShares(bool $isShared, array $shareRows, float $chitValue): void
    {
        if (! $isShared) {
            $primaryClientId = (int) $this->client_id;
            $shareRows = [[
                'client_id' => $primaryClientId,
                'ownership_percentage' => 100,
            ]];
        }

        $normalized = [];
        $seen = [];
        $totalPct = 0.0;

        foreach ($shareRows as $row) {
            $clientId = (int) ($row['client_id'] ?? 0);
            $pct = round((float) ($row['ownership_percentage'] ?? 0), 2);

            if ($clientId <= 0 || $pct <= 0) {
                continue;
            }

            if (isset($seen[$clientId])) {
                throw ValidationException::withMessages([
                    'shares' => 'Duplicate customers are not allowed in the same shared membership.',
                ]);
            }

            $seen[$clientId] = true;
            $normalized[] = [
                'client_id' => $clientId,
                'ownership_percentage' => $pct,
            ];
            $totalPct = round($totalPct + $pct, 2);
        }

        if ($isShared && count($normalized) < 2) {
            throw ValidationException::withMessages([
                'shares' => 'Shared membership requires at least 2 customers.',
            ]);
        }

        if (empty($normalized)) {
            throw ValidationException::withMessages([
                'client_id' => 'At least one customer is required.',
            ]);
        }

        if (abs($totalPct - 100) > 0.01) {
            throw ValidationException::withMessages([
                'shares' => 'Ownership percentages must total exactly 100%. Current total: ' . $totalPct . '%.',
            ]);
        }

        $primaryClientId = (int) $normalized[0]['client_id'];

        $this->guardOwnershipChangeAfterPayment($normalized);

        DB::transaction(function () use ($normalized, $chitValue, $primaryClientId, $isShared) {
            $this->shares()->delete();

            foreach ($normalized as $index => $row) {
                $this->shares()->create([
                    'client_id' => $row['client_id'],
                    'ownership_percentage' => $row['ownership_percentage'],
                    'share_amount' => round($chitValue * ($row['ownership_percentage'] / 100), 2),
                    'is_primary' => $index === 0,
                ]);
            }

            $this->update([
                'client_id' => $primaryClientId,
                'is_shared' => $isShared,
            ]);
        });
    }

    /**
     * Re-splitting ownership after money has come in would silently rewrite what every
     * owner owes (and what they are credited with), so it is only allowed while the
     * membership has no payments against it.
     *
     * @param  array<int, array{client_id:int, ownership_percentage:float}>  $normalized
     */
    protected function guardOwnershipChangeAfterPayment(array $normalized): void
    {
        if (! $this->exists) {
            return;
        }

        $current = $this->shares()->get();
        if ($current->isEmpty()) {
            return;
        }

        $before = $current->mapWithKeys(fn ($s) => [(int) $s->client_id => round((float) $s->ownership_percentage, 2)])->all();
        $after = collect($normalized)->mapWithKeys(fn ($r) => [(int) $r['client_id'] => round((float) $r['ownership_percentage'], 2)])->all();

        ksort($before);
        ksort($after);

        if ($before === $after) {
            return;
        }

        $hasPayments = $this->installments()->where('paid_amount', '>', 0)->exists();

        if ($hasPayments) {
            throw ValidationException::withMessages([
                'shares' => 'Ownership cannot be changed after payments have been collected on this membership. Undo the collected installments first.',
            ]);
        }
    }

    public function getDisplayMemberNumberAttribute(): string
    {
        if ($this->is_shared) {
            $shares = $this->resolveOwnershipShares();
            if ($shares->count() > 1) {
                $numbers = [];
                foreach ($shares->values() as $index => $share) {
                    $numbers[] = $index === 0
                        ? (string) $this->member_number
                        : $this->member_number . '.' . $index;
                }
                return implode(', ', $numbers);
            }
        }
        return (string) $this->member_number;
    }

    public function memberNumberForClient(int $clientId): string
    {
        if (! $this->is_shared) {
            return (string) $this->member_number;
        }

        $shares = $this->resolveOwnershipShares()->values();
        $index = $shares->search(fn ($s) => (int) $s->client_id === $clientId);

        if ($index === false || $index === 0) {
            return (string) $this->member_number;
        }

        return $this->member_number . '.' . $index;
    }

    /**
     * When the same client holds multiple seats in this group, return A/B/C… for this seat.
     */
    public function enrollmentSuffix(): ?string
    {
        if (! $this->client_id || ! $this->group_id) {
            return null;
        }

        $enrollmentIds = self::query()
            ->where('group_id', $this->group_id)
            ->where('client_id', $this->client_id)
            ->whereNotIn('status', ['transferred', 'rejected', 'withdrawn', 'cancelled'])
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($enrollmentIds) < 2) {
            return null;
        }

        $index = array_search((int) $this->id, $enrollmentIds, true);
        if ($index === false) {
            return null;
        }

        return chr(65 + (int) $index);
    }

    /**
     * Client name with seat letter when the same person has multiple chits (e.g. Gokul A / Gokul B).
     */
    public function displayClientName(?\App\Models\Client $client = null): string
    {
        if (! $client) {
            if (! $this->relationLoaded('client')) {
                $this->load('client');
            }
            $client = $this->getRelationValue('client');
        }

        $base = (string) (
            ($client ? ($client->getAttributes()['client_name'] ?? null) : null)
            ?? '—'
        );

        $suffix = $this->enrollmentSuffix();

        return $suffix ? ($base . ' ' . $suffix) : $base;
    }

    /**
     * One display row per ownership share so shared seats (e.g. #4 and #4.1) render separately.
     *
     * @return Collection<int, object{
     *     member: self,
     *     client: ?Client,
     *     client_id: int,
     *     member_number_label: string,
     *     ownership_percentage: float,
     *     is_share_row: bool,
     *     row_key: string,
     *     display_name: string,
     *     enrollment_suffix: ?string,
     *     single_seat_only: bool
     * }>
     */
    public function ownerDisplayRows(): Collection
    {
        $enrollmentSuffix = $this->enrollmentSuffix();

        if ($this->is_shared) {
            $shares = $this->resolveOwnershipShares()->values();
            if ($shares->count() > 1) {
                return $shares->map(function ($share, $index) {
                    $shareClient = $share->client;
                    $name = $shareClient->client_name ?? ('#' . $share->client_id);

                    return (object) [
                        'member' => $this,
                        'client' => $shareClient,
                        'client_id' => (int) $share->client_id,
                        'member_number_label' => $index === 0
                            ? (string) $this->member_number
                            : $this->member_number . '.' . $index,
                        'ownership_percentage' => (float) $share->ownership_percentage,
                        'is_share_row' => true,
                        'row_key' => $this->id . '-' . (int) $share->client_id,
                        'display_name' => $name,
                        'enrollment_suffix' => null,
                        'single_seat_only' => true,
                    ];
                });
            }
        }

        $client = $this->getRelationValue('client') ?: $this->client()->first();
        $displayName = $this->displayClientName($client);

        return collect([(object) [
            'member' => $this,
            'client' => $client,
            'client_id' => (int) $this->client_id,
            'member_number_label' => (string) $this->member_number,
            'ownership_percentage' => 100.0,
            'is_share_row' => false,
            'row_key' => (string) $this->id,
            'display_name' => $displayName,
            'enrollment_suffix' => $enrollmentSuffix,
            'single_seat_only' => true,
        ]]);
    }

    public function getOwnersDisplayAttribute(): string
    {
        $shares = $this->resolveOwnershipShares();

        if (! $this->is_shared || $shares->count() <= 1) {
            return $this->client->client_name ?? '—';
        }

        return $shares->values()->map(function ($share, $index) {
            $name = $share->client?->client_name ?? ('#' . $share->client_id);
            $numLabel = $index === 0
                ? (string) $this->member_number
                : $this->member_number . '.' . $index;

            return '#' . $numLabel . ' ' . $name . ' (' . rtrim(rtrim(number_format((float) $share->ownership_percentage, 2, '.', ''), '0'), '.') . '%)';
        })->implode(' + ');
    }

    public function getClientAttribute()
    {
        if (! $this->relationLoaded('client')) {
            $this->setRelation('client', $this->client()->getResults());
        }

        $client = $this->getRelationValue('client');
        if (! $client) {
            return null;
        }

        $clonedClient = clone $client;
        $suffix = $this->enrollmentSuffix();
        if ($suffix) {
            $baseName = (string) ($clonedClient->getAttributes()['client_name'] ?? '');
            $clonedClient->client_name = $baseName . ' ' . $suffix;
        }

        return $clonedClient;
    }

    public function installments()
    {
        return $this->hasMany(Installment::class, 'member_id');
    }

    /**
     * True when period/month 1 has any real payment (full, partial, or waived).
     */
    public function hasFirstMonthPayment(): bool
    {
        if ($this->relationLoaded('installments')) {
            $monthOne = $this->installments->first(function ($inst) {
                return (int) ($inst->month_number ?? 0) === 1;
            });

            if (! $monthOne) {
                return false;
            }

            if ((float) ($monthOne->paid_amount ?? 0) > 0.009) {
                return true;
            }

            if (in_array($monthOne->status, ['paid', 'partial', 'waived'], true)) {
                return true;
            }

            if ($monthOne->relationLoaded('sharePayments')
                && (float) $monthOne->sharePayments->sum('amount') > 0.009) {
                return true;
            }

            return false;
        }

        return $this->installments()
            ->where('month_number', 1)
            ->where(function ($q) {
                $q->where('paid_amount', '>', 0.009)
                    ->orWhereIn('status', ['paid', 'partial', 'waived'])
                    ->orWhereHas('sharePayments', fn ($sq) => $sq->where('amount', '>', 0.009));
            })
            ->exists();
    }

    /**
     * Delete seat until the first month installment is paid.
     * After any month-1 payment, use Cancel Chit instead.
     */
    public function canDeleteFromGroup(): bool
    {
        if ($this->has_won_auction) {
            return false;
        }

        if (in_array($this->status, ['completed', 'defaulted', 'transferred', 'cancelled'], true)) {
            return false;
        }

        // Applications / cleanup seats
        if (in_array($this->status, ['applied', 'rejected', 'withdrawn'], true)) {
            return true;
        }

        // After approval (including active / frozen): delete only until month 1 is paid
        if (in_array($this->status, ['approved', 'active', 'frozen'], true)) {
            return ! $this->hasFirstMonthPayment();
        }

        return false;
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function transferredTo()
    {
        return $this->belongsTo(self::class, 'transferred_to_member_id');
    }

    public function transferredFrom()
    {
        return $this->belongsTo(self::class, 'transferred_from_member_id');
    }

    public function outgoingTransfer()
    {
        return $this->hasOne(ChitMemberTransfer::class, 'outgoing_member_id');
    }

    public function incomingTransfer()
    {
        return $this->hasOne(ChitMemberTransfer::class, 'incoming_member_id');
    }

    /**
     * Dynamic status accessor: completed tenure or ended group evaluates to 'completed'.
     */
    public function getStatusAttribute($value): string
    {
        $raw = strtolower(trim((string) $value));
        if (in_array($raw, ['rejected', 'transferred', 'withdrawn', 'cancelled', 'completed', 'closed'], true)) {
            return $raw;
        }

        if ($this->hasCompletedTenure()) {
            if (in_array($raw, ['active', 'approved', ''], true) && $this->exists) {
                $this->attributes['status'] = 'completed';
                $this->saveQuietly();
            }
            return 'completed';
        }

        return $raw ?: 'active';
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->badgeForStatus($this->status);
    }

    public function getStatusLabelAttribute(): string
    {
        return match (strtolower(trim((string) $this->status))) {
            'applied' => 'Applied',
            'approved' => 'Approved',
            'active' => 'Active',
            'completed' => 'Completed',
            'closed' => 'Closed',
            'defaulted' => 'Defaulted',
            'withdrawn' => 'Withdrawn',
            'transferred' => 'Transferred',
            'cancelled' => 'Cancelled',
            'rejected' => 'Rejected',
            'frozen' => 'Frozen',
            default => ucfirst((string) $this->status),
        };
    }

    /**
     * Status shown to the customer app: group completion/close wins over a leftover member "active".
     */
    public function customerFacingStatus(): string
    {
        $memberStatus = strtolower(trim((string) $this->status));
        $groupStatus = strtolower(trim((string) ($this->group?->status ?? '')));

        if (in_array($memberStatus, ['rejected', 'withdrawn', 'cancelled', 'transferred', 'defaulted', 'applied'], true)) {
            return $memberStatus;
        }

        if ($groupStatus === 'terminated') {
            return 'closed';
        }

        if (in_array($groupStatus, ['completed', 'closed'], true)) {
            return $groupStatus;
        }

        if (in_array($memberStatus, ['completed', 'closed'], true)) {
            return $memberStatus;
        }

        if ($this->hasCompletedTenure()) {
            return 'completed';
        }

        return $this->status ?: 'active';
    }

    public function customerFacingStatusBadge(): string
    {
        return $this->badgeForStatus($this->customerFacingStatus());
    }

    protected function badgeForStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'active' => 'success',
            'approved' => 'info',
            'applied' => 'warning',
            'rejected' => 'danger',
            'defaulted' => 'danger',
            'completed', 'closed' => 'primary',
            'transferred' => 'secondary',
            'withdrawn' => 'secondary',
            'cancelled' => 'secondary',
            'frozen' => 'warning',
            default => 'secondary',
        };
    }

    public function customerFacingStatusLabel(): string
    {
        return match ($this->customerFacingStatus()) {
            'applied' => 'Applied',
            'approved' => 'Approved',
            'active' => 'Active',
            'completed' => 'Completed',
            'closed' => 'Closed',
            'defaulted' => 'Defaulted',
            'withdrawn' => 'Withdrawn',
            'transferred' => 'Transferred',
            'cancelled' => 'Cancelled',
            'rejected' => 'Rejected',
            'frozen' => 'Frozen',
            default => ucfirst((string) $this->status),
        };
    }

    /**
     * Persist completed/closed when the group has ended but the seat is still stored as active.
     */
    public function persistCustomerFacingStatus(): string
    {
        $facing = $this->customerFacingStatus();
        $current = strtolower(trim((string) $this->status));

        if (
            in_array($facing, ['completed', 'closed'], true)
            && in_array($current, ['active', 'approved'], true)
            && $this->exists
        ) {
            $this->status = $facing;
            $this->saveQuietly();
        }

        return $facing;
    }

    /**
     * Seat has finished its chit tenure (all months paid, or group already ended).
     */
    public function hasCompletedTenure(): bool
    {
        $group = $this->group;
        if (! $group) {
            return false;
        }

        $groupStatus = strtolower((string) $group->status);
        if (in_array($groupStatus, ['completed', 'closed', 'terminated'], true)) {
            return true;
        }

        $total = (int) ($group->total_months ?? 0);
        if ($total < 1) {
            return false;
        }

        $paid = $this->relationLoaded('installments')
            ? $this->installments->whereIn('status', ['paid', 'waived'])->count()
            : $this->installments()->whereIn('status', ['paid', 'waived'])->count();

        return $paid >= $total;
    }

    /**
     * Filter by the status the customer app should see (group completed/closed counts as completed).
     */
    public function scopeWhereCustomerFacingStatus($query, ?string $status)
    {
        $status = strtolower(trim((string) $status));
        if ($status === '' || $status === 'all') {
            return $query;
        }

        $endedGroup = ['completed', 'closed', 'terminated'];

        if (in_array($status, ['completed', 'closed'], true)) {
            return $query->where(function ($q) use ($endedGroup) {
                $q->whereIn('status', ['completed', 'closed'])
                    ->orWhereHas('group', fn ($g) => $g->whereIn('status', $endedGroup));
            });
        }

        if (in_array($status, ['active', 'approved'], true)) {
            return $query->whereIn('status', ['active', 'approved'])
                ->whereHas('group', fn ($g) => $g->whereNotIn('status', $endedGroup));
        }

        return $query->where('status', $status);
    }

    public function getPaidInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'paid')->count();
    }

    public function getPendingInstallmentsCountAttribute(): int
    {
        return $this->installments()->whereIn('status', ['pending', 'overdue'])->count();
    }

    public function getApplicationNumberAttribute(): string
    {
        if (! empty($this->attributes['application_number'])) {
            return $this->attributes['application_number'];
        }

        $groupCode = $this->group?->group_code ?? 'CHT';
        $num = str_pad((string) ($this->member_number ?? $this->id), 3, '0', STR_PAD_LEFT);

        return 'APP-' . $groupCode . '-' . $num;
    }

    public function getAccountNumberAttribute(): string
    {
        $groupCode = $this->group?->group_code ?? 'CHT';

        return $groupCode . '-' . str_pad((string) $this->member_number, 3, '0', STR_PAD_LEFT);
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return round((float) $this->installments->sum(function ($inst) {
            return max(0, (float) $inst->balance);
        }), 2);
    }

    public function getTotalPaidAttribute(): float
    {
        return round((float) $this->installments->sum('paid_amount'), 2);
    }

    public function scopeAccounts($query)
    {
        return $query->whereIn('status', ['active', 'completed', 'defaulted'])
            ->whereHas('group');
    }

    public static function resolveChitNeedFields(?int $month, ?ChitGroup $group = null): array
    {
        if (! $month || $month < 1) {
            return ['chit_need_month' => null, 'chit_need_date' => null];
        }

        $total = (int) ($group?->total_months ?? 0);

        // Customer/agent apps send the chit period (1..N). Admin calendar dropdown is 1..12.
        if ($total > 0 && $month > 12 && $month <= $total) {
            return self::resolveChitNeedFromPeriod($month, $group);
        }

        if (! $month || $month > 12) {
            return ['chit_need_month' => null, 'chit_need_date' => null];
        }

        $year = $group?->start_date
            ? \Carbon\Carbon::parse($group->start_date)->year
            : now()->year;

        return [
            'chit_need_month' => $month,
            'chit_need_date' => sprintf('%04d-%02d-01', $year, $month),
        ];
    }

    /**
     * Persist the selected chit period (from customer/agent months_list) plus that period's date.
     */
    public static function resolveChitNeedFromPeriod(?int $period, ?ChitGroup $group = null): array
    {
        if (! $period || $period < 1) {
            return ['chit_need_month' => null, 'chit_need_date' => null];
        }

        $total = (int) ($group?->total_months ?? 0);
        if ($total > 0 && $period > $total) {
            return ['chit_need_month' => null, 'chit_need_date' => null];
        }

        return [
            'chit_need_month' => $period,
            'chit_need_date' => $group?->periodDate($period)?->toDateString(),
        ];
    }

    public function getChitNeedFormattedAttribute(): string
    {
        $period = $this->preferredChitNeedPeriod();

        if (! $period) {
            return '—';
        }

        if ($this->group) {
            return 'Month ' . $period . ' — ' . $this->group->periodCalendarLabel($period);
        }

        return 'Month ' . $period;
    }

    public function getChitNeedMonthLabelAttribute(): string
    {
        return $this->chit_need_formatted;
    }

    /**
     * Applied chit-need period from the original application (not the settlement payout month).
     */
    public function preferredChitNeedPeriod(): ?int
    {
        $latest = $this->latestSettlementPayout();
        if ($latest && $latest->isRejectedApplication()) {
            return null;
        }

        $this->loadMissing('group');
        $group = $this->group;
        $stored = (int) ($this->chit_need_month ?? 0);
        $total = max(0, (int) ($group?->total_months ?? 0));

        if ($stored < 1 && ! $this->chit_need_date) {
            return null;
        }

        $fromDate = ($this->chit_need_date && $group)
            ? $group->periodNumberForDate($this->chit_need_date)
            : null;

        // Customer app stores the tenure period (1..N). Admin calendar dropdown stores 1..12 + date.
        if ($total > 0 && $stored >= 1 && $stored <= $total) {
            if (! $this->chit_need_date || ($fromDate && $fromDate === $stored) || $stored > 12) {
                return $stored;
            }
        }

        if ($fromDate) {
            return $fromDate;
        }

        if ($stored < 1) {
            return null;
        }

        if (! $group) {
            return $stored;
        }

        if ($stored >= 1 && $stored <= 12) {
            $periods = $group->chitMonthsForCalendarMonth($stored);
            if (! empty($periods)) {
                $nextMonth = max(1, (int) ($group->current_month ?? 0) + 1);
        foreach ($periods as $period) {
            if ((int) $period >= $nextMonth) {
                return (int) $period;
            }
        }

        return (int) $periods[0];
            }
        }

        return ($total > 0 && $stored <= $total) ? $stored : null;
    }

    /** @return array<int, int> */
    public function getChitNeedPeriodsAttribute(): array
    {
        $period = $this->preferredChitNeedPeriod();

        return $period ? [$period] : [];
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class, 'winner_member_id');
    }

    /** @return \Illuminate\Support\Collection<int, Payout> */
    protected function settlementPayoutsCollection()
    {
        if ($this->relationLoaded('payouts')) {
            return collect($this->payouts);
        }

        if ($this->relationLoaded('group') && $this->group && $this->group->relationLoaded('payouts')) {
            return $this->group->payouts->where('winner_member_id', $this->id)->values();
        }

        return $this->exists ? $this->payouts()->get() : collect();
    }

    public function latestSettlementPayout(): ?Payout
    {
        return $this->settlementPayoutsCollection()->sortByDesc('id')->first();
    }

    public function activeSettlementPayout(): ?Payout
    {
        return $this->settlementPayoutsCollection()
            ->filter(fn (Payout $payout) => $payout->isActiveApplication())
            ->sortByDesc('id')
            ->first();
    }

    /**
     * Month shown in group Settlement Month: active application only.
     * When there is no active settlement application (or if rejected/cancelled), returns null.
     */
    public function displaySettlementMonthNumber(): ?int
    {
        $active = $this->activeSettlementPayout();
        if ($active && (int) $active->month_number > 0) {
            return (int) $active->month_number;
        }

        return null;
    }

    public function displaySettlementMonthFormatted(): string
    {
        $period = $this->displaySettlementMonthNumber();
        if (! $period) {
            return '—';
        }

        $this->loadMissing('group');
        if ($this->group) {
            return 'Month ' . $period . ' — ' . $this->group->periodCalendarLabel($period);
        }

        return 'Month ' . $period;
    }

    /** Customer API settlement status: applied | approved | rejected | eligible | ineligible. */
    public function customerSettlementFacing(): array
    {
        $info = $this->settlement_status_info;

        return [
            'status' => $info['customer_status'] ?? 'ineligible',
            'label' => $info['customer_status_label'] ?? ($info['label'] ?? 'Not Eligible'),
            'badge' => $info['customer_status_badge'] ?? ($info['badge'] ?? 'secondary'),
            'can_reapply' => (bool) ($info['can_reapply'] ?? false),
            'is_eligible' => ($info['status'] ?? '') === 'eligible' || ! empty($info['can_reapply']),
            'amount' => (float) ($info['amount'] ?? 0),
            'last_application_status' => $info['last_application_status'] ?? null,
        ];
    }

    /**
     * Seat settlement after Independent Sharing % (before co-owner split).
     * Uses the same rule as ChitPayoutService::calculateAmounts.
     */
    public function seatSettlementAmount(?int $monthNumber = null): float
    {
        $group = $this->group;
        if (! $group) {
            return 0.0;
        }

        $month = $monthNumber
            ?? $this->preferredChitNeedPeriod()
            ?? ((int) ($group->current_month ?? 0) + 1);
        $month = max(1, (int) $month);

        return (float) app(\App\Services\ChitPayoutService::class)
            ->calculateAmounts($group, null, $month, $this)['payout_amount'];
    }

    /**
     * Settlement amount for display: seat share, optionally split for a co-owner client.
     */
    public function settlementAmountForClient(?int $clientId = null, ?int $monthNumber = null): float
    {
        $seat = $this->seatSettlementAmount($monthNumber);
        if ($clientId && $this->is_shared) {
            return $this->amountForClient($seat, $clientId);
        }

        return round($seat, 2);
    }

    public function getSettlementAmountAttribute(): float
    {
        return $this->seatSettlementAmount();
    }

    public function getSettlementStatusInfoAttribute(): array
    {
        if (!$this->group) {
            return [
                'status' => 'ineligible',
                'label' => 'Not Eligible',
                'badge' => 'secondary',
                'amount' => 0.0,
                'payout' => null,
                'customer_status' => 'ineligible',
                'customer_status_label' => 'Not Eligible',
                'customer_status_badge' => 'secondary',
                'can_reapply' => false,
            ];
        }

        $payouts = $this->settlementPayoutsCollection();
        $activePayout = $payouts
            ->filter(fn (Payout $payout) => $payout->isActiveApplication())
            ->sortByDesc('id')
            ->first();
        $latestPayout = $payouts->sortByDesc('id')->first();
        $paidPayout = $payouts->firstWhere('status', 'paid');
        $processingPayout = $payouts->firstWhere('status', 'processing');
        $pendingPayout = $payouts->firstWhere('status', 'pending');
        $wasRejected = $latestPayout && $latestPayout->isRejectedApplication() && ! $activePayout;

        if ($paidPayout || $this->has_won_auction) {
            return [
                'status' => 'done',
                'label' => 'Settlement Done',
                'badge' => 'success',
                'amount' => (float) ($paidPayout?->payout_amount ?? $this->settlement_amount),
                'payout' => $paidPayout,
                'customer_status' => 'approved',
                'customer_status_label' => 'Approved',
                'customer_status_badge' => 'success',
                'can_reapply' => false,
            ];
        }

        if ($processingPayout) {
            return [
                'status' => 'pending',
                'label' => 'Approved',
                'badge' => 'success',
                'amount' => (float) $processingPayout->payout_amount,
                'payout' => $processingPayout,
                'customer_status' => 'approved',
                'customer_status_label' => 'Approved',
                'customer_status_badge' => 'success',
                'can_reapply' => false,
            ];
        }

        if ($pendingPayout) {
            return [
                'status' => 'pending',
                'label' => 'Applied',
                'badge' => 'warning',
                'amount' => (float) $pendingPayout->payout_amount,
                'payout' => $pendingPayout,
                'customer_status' => 'applied',
                'customer_status_label' => 'Applied',
                'customer_status_badge' => 'warning',
                'can_reapply' => false,
            ];
        }

        if (in_array($this->status, ['active', 'approved'], true)) {
            return [
                'status' => 'eligible',
                'label' => $wasRejected ? 'Eligible to Re-apply' : 'Eligible for Settlement',
                'badge' => 'info',
                'amount' => (float) $this->settlement_amount,
                'payout' => null,
                'customer_status' => 'eligible',
                'customer_status_label' => $wasRejected ? 'Eligible to Re-apply' : 'Eligible for Settlement',
                'customer_status_badge' => 'info',
                'can_reapply' => $wasRejected,
                'last_application_status' => $wasRejected ? 'rejected' : null,
            ];
        }

        if (in_array($this->status, ['withdrawn', 'cancelled'], true)) {
            $refund = app(\App\Services\ChitPayoutService::class)->contributionSettlementAmount($this);
            if ($refund > 0.009) {
                return [
                    'status' => 'eligible',
                    'label' => 'Cancel settlement (paid − foreman)',
                    'badge' => 'warning',
                    'amount' => $refund,
                    'payout' => null,
                    'customer_status' => 'eligible',
                    'customer_status_label' => $wasRejected ? 'Eligible to Re-apply' : 'Eligible for Settlement',
                    'customer_status_badge' => 'warning',
                    'can_reapply' => $wasRejected,
                    'last_application_status' => $wasRejected ? 'rejected' : null,
                ];
            }
        }

        return [
            'status' => 'ineligible',
            'label' => 'Not Eligible',
            'badge' => 'secondary',
            'amount' => 0.0,
            'payout' => null,
            'customer_status' => 'ineligible',
            'customer_status_label' => 'Not Eligible',
            'customer_status_badge' => 'secondary',
            'can_reapply' => false,
            'last_application_status' => $wasRejected ? 'rejected' : null,
        ];
    }

    /**
     * Update future monthly installment amount for this member in this group.
     */
    public function updateFutureInstallmentAmount(float $newAmount, int $startFromMonth): void
    {
        $this->custom_installment_amount = $newAmount;
        $this->custom_installment_start_month = max(1, $startFromMonth);
        $this->save();

        $sharePct = max(0, (float) ($this->effective_share_percentage ?? 100.0));
        $memberAmount = round($newAmount * ($sharePct / 100.0), 2);

        // Update all unpaid/pending future installments for this member in this group
        Installment::query()
            ->where('group_id', $this->group_id)
            ->where('member_id', $this->id)
            ->where('month_number', '>=', $startFromMonth)
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'overdue'])
                  ->orWhere('paid_amount', '<=', 0.009);
            })
            ->get()
            ->each(function (Installment $inst) use ($memberAmount) {
                $inst->amount = $memberAmount;
                $inst->save();
            });

        Installment::applyAutomatedPenalties();
        $this->calculateAndStoreShareFields($this->group);
    }

    /**
     * Ensure installments exist for this member in the group.
     */
    public function ensureInstallmentsExist(): void
    {
        $group = $this->group;
        if (! $group) {
            return;
        }

        $installments = $this->relationLoaded('installments')
            ? $this->installments
            : $this->installments()->get();

        if ($installments->isEmpty()) {
            $startDate = $group->start_date
                ? \Carbon\Carbon::parse($group->start_date)
                : ($this->joined_date ? \Carbon\Carbon::parse($this->joined_date) : now());

            try {
                $group->generateInstallmentsForMember($this, $startDate);
                $this->unsetRelation('installments');
                $this->load('installments.collections');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Could not auto-generate installments for member {$this->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Human label for an installment/period status. Keeps multi-word statuses
     * such as in_progress from rendering as "In_progress".
     */
    public static function installmentStatusLabel(?string $status): string
    {
        return ucwords(str_replace('_', ' ', (string) $status));
    }

    /**
     * Badge colour for an installment/period status.
     */
    public static function installmentStatusBadge(?string $status): string
    {
        return match ((string) $status) {
            'paid' => 'success',
            'partial' => 'warning',
            'overdue' => 'danger',
            'waived' => 'secondary',
            'in_progress' => 'info',
            default => 'info',
        };
    }

    /**
     * Build month-wise array of installments where each month contains nested
     * weekly or daily periods depending on the member's collection frequency.
     */
    public function buildMonthWiseInstallments($clientOrId = null): array
    {
        $this->ensureInstallmentsExist();

        $clientId = $clientOrId instanceof Client ? (int) $clientOrId->id : ($clientOrId ? (int) $clientOrId : (int) $this->client_id);
        $group = $this->group;
        $memberFreq = $this->collection_frequency ?? 'monthly';
        $installments = $this->relationLoaded('installments')
            ? $this->installments->sortBy('month_number')->values()
            : $this->installments()->with('collections')->orderBy('month_number')->get();

        if ($installments->isEmpty()) {
            return $this->buildProjectedMonthWiseInstallments($clientId);
        }

        $monthWise = [];
        $installmentList = $installments->values();

        foreach ($installmentList as $idx => $inst) {
            $instAmount = (float) $this->displayAmountForInstallment($inst, $clientId);
            $divAmount = (float) $this->amountForClient((float) $inst->dividend_amount, $clientId);
            $penAmount = (float) $this->penaltyAmountForClient((float) $inst->penalty_amount, $clientId);
            $paidAmt = (float) $inst->clientPaidShare($clientId);
            $balAmt = (float) $inst->clientBalanceShare($clientId);
            $netPayable = round(max(0, $instAmount - $divAmount + $penAmount), 2);

            // An agent collection stays out of paid_amount until an admin verifies
            // it, so surface it as in_progress rather than pending/overdue.
            $pendingAmt = (float) $inst->pendingCollectedAmount($clientId);
            $effStatus = $inst->effectiveStatus($clientId);
            $uncollected = round(max(0, $balAmt - $pendingAmt), 2);

            $encodedId = \App\Support\HashId::encode($inst->id);
            $isPaidOrPartial = in_array($inst->status, ['paid', 'partial'], true) || $paidAmt > 0;
            $receiptUrl = $isPaidOrPartial ? url('/payment-receipts/chit/' . $encodedId) : null;
            $latestColl = $inst->relationLoaded('collections') ? $inst->collections->sortByDesc('id')->first() : null;
            $receiptNum = $inst->reference_no ?? optional($latestColl)->receipt_no ?? ('RCP-' . str_pad((string) $inst->id, 6, '0', STR_PAD_LEFT));

            $rawPeriods = [];
            if (in_array($memberFreq, ['daily', 'weekly'], true)) {
                $nextDue = $installmentList[$idx + 1]->due_date ?? null;
                $rawPeriods = $this->collectionPeriodSchedule(
                    $inst->due_date,
                    $instAmount,
                    $paidAmt,
                    $pendingAmt,
                    $nextDue
                );
            }

            $periods = [];
            if (! empty($rawPeriods)) {
                foreach ($rawPeriods as $p) {
                    $pStatus = (string) $p['status'];
                    $pPending = (float) ($p['in_progress'] ?? 0);
                    $periods[] = [
                        'period_number' => (int) $p['index'],
                        'index' => (int) $p['index'],
                        'label' => (string) $p['label'],
                        'period_label' => (string) $p['label'],
                        'due_date' => $p['due_date'] ? \Carbon\Carbon::parse($p['due_date'])->format('Y-m-d') : null,
                        'period_end' => ! empty($p['period_end']) ? \Carbon\Carbon::parse($p['period_end'])->format('Y-m-d') : null,
                        'amount' => (float) $p['amount'],
                        'paid_amount' => (float) $p['paid'],
                        'balance' => (float) $p['balance'],
                        'due_amount' => (float) $p['balance'],
                        'status' => $pStatus,
                        'status_label' => $pStatus === 'in_progress' ? 'In Progress' : ucfirst($pStatus),
                        'status_badge' => match ($pStatus) {
                            'paid' => 'success',
                            'partial' => 'warning',
                            'overdue' => 'danger',
                            'waived' => 'secondary',
                            'in_progress' => 'info',
                            default => 'info',
                        },
                        'pending_verification_amount' => $pPending,
                        'has_pending_verification' => $pPending > 0.009,
                        'is_next' => (bool) ($p['is_next'] ?? false),
                        'is_current' => (bool) ($p['is_current'] ?? false),
                    ];
                }
            } else {
                $periodStatus = $effStatus;
                $periodBalance = $uncollected;
                $periods[] = [
                    'period_number' => 1,
                    'index' => 1,
                    'label' => 'Month ' . $inst->month_number,
                    'period_label' => 'Month ' . $inst->month_number,
                    'due_date' => optional($inst->due_date)?->format('Y-m-d'),
                    'amount' => $instAmount,
                    'paid_amount' => $paidAmt,
                    'balance' => $periodBalance,
                    'due_amount' => $periodBalance,
                    'raw_balance' => $balAmt,
                    'status' => $periodStatus,
                    'status_label' => self::installmentStatusLabel($periodStatus),
                    'status_badge' => self::installmentStatusBadge($periodStatus),
                    'pending_verification_amount' => $pendingAmt,
                    'has_pending_verification' => $pendingAmt > 0.009,
                    'is_next' => ($periodStatus !== 'paid' && $periodStatus !== 'in_progress' && $periodBalance > 0),
                ];
            }

            $monthDate = $inst->due_date ? \Carbon\Carbon::parse($inst->due_date) : null;
            $monthName = $monthDate ? ('Month ' . $inst->month_number . ' (' . $monthDate->format('M Y') . ')') : ('Month ' . $inst->month_number);

            $monthWise[] = [
                'id' => $inst->id,
                'installment_id' => $inst->id,
                'month_number' => (int) $inst->month_number,
                'month_name' => $monthName,
                'month_label' => 'Month ' . $inst->month_number,
                'due_date' => optional($inst->due_date)?->format('Y-m-d'),
                'installment_amount' => $instAmount,
                'dividend_amount' => $divAmount,
                'penalty_amount' => $penAmount,
                'net_payable' => $netPayable,
                'amount' => $netPayable,
                'total_amount' => $netPayable,
                'paid_amount' => $paidAmt,
                'balance' => $uncollected,
                'due_amount' => $uncollected,
                'raw_balance' => $balAmt,
                'paid_date' => optional($inst->paid_date)?->format('Y-m-d H:i:s'),
                'status' => $effStatus,
                'status_label' => self::installmentStatusLabel($effStatus),
                'status_badge' => self::installmentStatusBadge($effStatus),
                'pending_verification_amount' => $pendingAmt,
                'has_pending_verification' => $pendingAmt > 0.009,
                'collectible_balance' => $uncollected,
                'collection_frequency' => $memberFreq,
                'collection_frequency_label' => $this->collection_frequency_label ?? ucfirst($memberFreq),
                'periods_count' => count($periods),
                'periods' => $periods,
                'payment_mode' => $inst->payment_mode ?? '—',
                'receipt_number' => $receiptNum,
                'receipt_url' => $receiptUrl,
                'receipt_view_url' => $receiptUrl,
                'receipt_download_url' => $receiptUrl,
                'receipt_print_url' => $isPaidOrPartial ? url('/payment-receipts/chit/' . $encodedId . '/print') : null,
            ];
        }

        return $monthWise;
    }

    /**
     * Build projected month-wise schedule when DB installments don't exist yet.
     */
    public function buildProjectedMonthWiseInstallments($clientOrId = null): array
    {
        $clientId = $clientOrId instanceof Client ? (int) $clientOrId->id : ($clientOrId ? (int) $clientOrId : (int) $this->client_id);
        $group = $this->group;
        $memberFreq = $this->collection_frequency ?? 'monthly';
        $totalMonths = (int) ($group?->total_months ?: $group?->scheme?->duration_months ?: 1);
        $baseMonthlyAmount = (float) $this->apiInstallmentAmountForClient($clientId, $group);
        $baseDate = $group?->start_date
            ? \Carbon\Carbon::parse($group->start_date)
            : ($this->joined_date ? \Carbon\Carbon::parse($this->joined_date) : now());

        $monthWise = [];

        for ($m = 1; $m <= $totalMonths; $m++) {
            $mDueDate = $baseDate->copy()->addMonthsNoOverflow($m - 1);
            $mNextDue = $baseDate->copy()->addMonthsNoOverflow($m);
            $rawPeriods = [];
            if (in_array($memberFreq, ['daily', 'weekly'], true)) {
                $rawPeriods = $this->collectionPeriodSchedule($mDueDate, $baseMonthlyAmount, 0.0, 0.0, $mNextDue);
            }

            $periods = [];
            if (! empty($rawPeriods)) {
                foreach ($rawPeriods as $p) {
                    $periods[] = [
                        'period_number' => (int) $p['index'],
                        'index' => (int) $p['index'],
                        'label' => (string) $p['label'],
                        'period_label' => (string) $p['label'],
                        'due_date' => $p['due_date'] ? \Carbon\Carbon::parse($p['due_date'])->format('Y-m-d') : null,
                        'period_end' => ! empty($p['period_end']) ? \Carbon\Carbon::parse($p['period_end'])->format('Y-m-d') : null,
                        'amount' => (float) $p['amount'],
                        'paid_amount' => 0.0,
                        'pending_verification_amount' => 0.0,
                        'has_pending_verification' => false,
                        'balance' => (float) $p['amount'],
                        'due_amount' => (float) $p['amount'],
                        'status' => (string) $p['status'],
                        'status_label' => self::installmentStatusLabel($p['status']),
                        'status_badge' => self::installmentStatusBadge($p['status']),
                        'is_next' => (bool) ($p['is_next'] ?? false),
                        'is_current' => (bool) ($p['is_current'] ?? false),
                    ];
                }
            } else {
                $periods[] = [
                    'period_number' => 1,
                    'index' => 1,
                    'label' => 'Month ' . $m,
                    'period_label' => 'Month ' . $m,
                    'due_date' => $mDueDate->format('Y-m-d'),
                    'amount' => $baseMonthlyAmount,
                    'paid_amount' => 0.0,
                    'pending_verification_amount' => 0.0,
                    'has_pending_verification' => false,
                    'balance' => $baseMonthlyAmount,
                    'due_amount' => $baseMonthlyAmount,
                    'status' => $mDueDate->lt(today()) ? 'overdue' : 'pending',
                    'status_label' => $mDueDate->lt(today()) ? 'Overdue' : 'Pending',
                    'status_badge' => $mDueDate->lt(today()) ? 'danger' : 'info',
                    'is_next' => ($m === 1),
                ];
            }

            $monthWise[] = [
                'id' => null,
                'installment_id' => null,
                'month_number' => $m,
                'month_name' => 'Month ' . $m . ' (' . $mDueDate->format('M Y') . ')',
                'month_label' => 'Month ' . $m,
                'due_date' => $mDueDate->format('Y-m-d'),
                'installment_amount' => $baseMonthlyAmount,
                'dividend_amount' => 0.0,
                'penalty_amount' => 0.0,
                'net_payable' => $baseMonthlyAmount,
                'amount' => $baseMonthlyAmount,
                'total_amount' => $baseMonthlyAmount,
                'paid_amount' => 0.0,
                'balance' => $baseMonthlyAmount,
                'due_amount' => $baseMonthlyAmount,
                'paid_date' => null,
                'status' => $mDueDate->lt(today()) ? 'overdue' : 'pending',
                'status_label' => $mDueDate->lt(today()) ? 'Overdue' : 'Pending',
                'status_badge' => $mDueDate->lt(today()) ? 'danger' : 'info',
                'pending_verification_amount' => 0.0,
                'has_pending_verification' => false,
                'collectible_balance' => $baseMonthlyAmount,
                'collection_frequency' => $memberFreq,
                'collection_frequency_label' => $this->collection_frequency_label ?? ucfirst($memberFreq),
                'periods_count' => count($periods),
                'periods' => $periods,
                'payment_mode' => '—',
                'receipt_number' => null,
                'receipt_url' => null,
                'receipt_view_url' => null,
                'receipt_download_url' => null,
                'receipt_print_url' => null,
            ];
        }

        return $monthWise;
    }
}

