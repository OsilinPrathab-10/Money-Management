<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\ChitGroup;
use App\Models\ChitMemberTransfer;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChitPayoutService
{
    public function __construct(
        protected \App\Services\Account\BankTransactionsService $bankTransactionsService,
        protected \App\Services\Account\ChitAccountingService $chitAccountingService
    ) {}

    public function getNextSettlementMonth(ChitGroup $group): int
    {
        $next = (int) $group->current_month + 1;
        while ($next <= (int) $group->total_months && $group->isForemanCommissionMonth($next)) {
            $next++;
        }
        return $next;
    }

    /**
     * Last non-foreman settlement month in the group schedule.
     */
    public function lastEligibleSettlementMonth(ChitGroup $group): int
    {
        $month = (int) $group->total_months;
        while ($month >= 1 && $group->isForemanCommissionMonth($month)) {
            $month--;
        }

        return max(1, $month);
    }

    public function memberMustSettleLast(GroupMember $member): bool
    {
        // Incoming after transfer gets a fresh schedule — no last-month lock.
        return false;
    }

    /** Outgoing member after a completed ticket transfer. */
    public function isOutgoingTransferredMember(GroupMember $member): bool
    {
        return $member->status === 'transferred';
    }

    /** Client cancelled / withdrawn from the group — refund-style contribution settlement. */
    public function isCancelledWithdrawnMember(GroupMember $member): bool
    {
        return in_array($member->status, ['withdrawn', 'cancelled'], true);
    }

    /**
     * Contribution-based settlement (paid months − foreman commission month):
     * outgoing transfer or cancelled/withdrawn client (not the incoming replacement).
     */
    public function usesContributionSettlement(GroupMember $member): bool
    {
        return $this->isOutgoingTransferredMember($member)
            || $this->isCancelledWithdrawnMember($member);
    }

    /**
     * Client paid installments excluding the foreman-commission month.
     * Example: paid ₹5,000 × 5 months, month 1 foreman → settlement ₹20,000.
     */
    public function contributionSettlementAmount(GroupMember $member): float
    {
        $member->loadMissing(['group.scheme']);
        $group = $member->group;

        if (! $group) {
            return 0.0;
        }

        // Include soft-deleted installments (source seat cleaned up after cross-group transfer).
        $installments = Installment::withTrashed()
            ->where('member_id', $member->id)
            ->get();

        $total = $installments
            ->filter(function (Installment $i) use ($group) {
                if ((float) $i->paid_amount <= 0.009) {
                    return false;
                }

                // Foreman commission month is retained — not paid out on cancel/transfer.
                return ! $group->isForemanCommissionMonth((int) $i->month_number);
            })
            ->sum(fn (Installment $i) => (float) $i->paid_amount);

        return round(max(0, (float) $total), 2);
    }

    /**
     * Latest month with a paid amount excluding foreman-commission month.
     */
    public function lastPaidNonForemanMonth(GroupMember $member): int
    {
        $member->loadMissing(['group.scheme']);
        $group = $member->group;

        if (! $group) {
            return 0;
        }

        $month = Installment::withTrashed()
            ->where('member_id', $member->id)
            ->get()
            ->filter(function (Installment $i) use ($group) {
                if ((float) $i->paid_amount <= 0.009) {
                    return false;
                }

                return ! $group->isForemanCommissionMonth((int) $i->month_number);
            })
            ->max('month_number');

        return (int) ($month ?? 0);
    }

    public function outgoingTransferBuyoutAlreadyPaid(GroupMember $member): bool
    {
        // Paid contribution settlement application counts as done.
        if (Payout::query()
            ->where('winner_member_id', $member->id)
            ->where('status', 'paid')
            ->exists()
        ) {
            return true;
        }

        return ChitMemberTransfer::query()
            ->where('outgoing_member_id', $member->id)
            ->where('outgoing_settlement_status', 'paid')
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Sync transfer receipt when contribution settlement is paid (no second wallet credit).
     */
    public function markOutgoingTransferBuyoutPaid(GroupMember $member): void
    {
        ChitMemberTransfer::query()
            ->where('outgoing_member_id', $member->id)
            ->whereIn('outgoing_settlement_status', ['pending', 'not_applicable'])
            ->where('status', 'completed')
            ->update([
                'outgoing_settlement_status' => 'paid',
                'outgoing_settlement_paid_at' => now(),
            ]);
    }

    public function originalSettlementForMonth(ChitGroup $group, int $monthNumber): ?Payout
    {
        return $group->payouts()
            ->where('month_number', $monthNumber)
            ->where('payout_kind', Payout::KIND_ORIGINAL)  
            ->whereNotIn('status', ['cancelled'])
            ->first();
    }

    public function settlementForMonth(ChitGroup $group, int $monthNumber): ?Payout
    {
        return $this->originalSettlementForMonth($group, $monthNumber);
    }

    public function advanceSettlementsForMonth(ChitGroup $group, int $monthNumber): Collection
    {
        return $group->payouts()
            ->where('month_number', $monthNumber)
            ->where('payout_kind', Payout::KIND_ADVANCE)
            ->whereNotIn('status', ['cancelled'])
            ->with('winner.client')
            ->get();
    }

    public function canInitiateSettlement(ChitGroup $group): bool
    {
        if ($group->status !== 'active') {
            return false;
        }

        $nextMonth = $this->getNextSettlementMonth($group);

        if ($nextMonth > (int) $group->total_months || $group->isForemanCommissionMonth($nextMonth)) {
            return false;
        }

        return !$this->originalSettlementForMonth($group, $nextMonth);
    }

    public function canInitiateAdvanceSettlement(ChitGroup $group, GroupMember $member, ?int $monthNumber = null): bool
    {
        if (!$group->allowsAdvancePayouts()) {
            return false;
        }

        if ($group->status !== 'active') {
            return false;
        }

        $month = $monthNumber ?? (int) $group->current_month;
        if ($month <= 0) {
            return false;
        }

        $original = $this->originalSettlementForMonth($group, $month);
        if (!$original || $original->status !== 'paid') {
            return false;
        }

        return $this->isMemberEligible($group, $member);
    }

    public function getAdvanceEligibleMembers(ChitGroup $group, ?int $monthNumber = null): Collection
    {
        $month = $monthNumber ?? (int) $group->current_month;
        if (!$group->allowsAdvancePayouts() || $month <= 0) {
            return collect();
        }

        $original = $this->originalSettlementForMonth($group, $month);
        if (!$original || $original->status !== 'paid') {
            return collect();
        }

        return $group->members
            ->filter(fn (GroupMember $member) => $this->canInitiateAdvanceSettlement($group, $member, $month))
            ->values();
    }

    /**
     * @return array{kind: string, month_number: int, original_payout: ?Payout}
     */
    public function resolveSettlementContext(ChitGroup $group, GroupMember $member, ?string $requestedKind = null, ?int $requestedMonthNumber = null): array
    {
        $lastMonth = $this->lastEligibleSettlementMonth($group);
        $mustSettleLast = $this->memberMustSettleLast($member);
        $isOutgoing = $this->isOutgoingTransferredMember($member);
        $isCancelled = $this->isCancelledWithdrawnMember($member);
        $isAdminOverride = Auth::check() && Auth::user()->hasAnyRole(['Admin', 'admin']);

        // Outgoing transfer / cancelled client: settle paid months minus foreman commission month.
        if ($isOutgoing || $isCancelled) {
            $monthNumber = $this->lastPaidNonForemanMonth($member);
            if ($monthNumber < 1) {
                throw ValidationException::withMessages([
                    'member_id' => 'No paid installments (excluding foreman commission month) found for settlement.',
                ]);
            }

            return [
                'kind' => Payout::KIND_ORIGINAL,
                'month_number' => $monthNumber,
                'original_payout' => null,
            ];
        }

        // Explicit Advance Amount (same month, 2nd+ member after original is paid).
        if ($requestedKind === Payout::KIND_ADVANCE) {
            return $this->resolveAdvanceSettlementContext($group, $member);
        }

        if ($mustSettleLast && ! $isAdminOverride
            && $requestedMonthNumber !== null
            && $requestedMonthNumber > 0
            && (int) $requestedMonthNumber !== (int) $lastMonth
        ) {
            throw ValidationException::withMessages([
                'month_number' => 'Transferred members can only take settlement in Month ' . $lastMonth . ' (last eligible month). Only Admin can override.',
            ]);
        }

        if ($mustSettleLast && (! $isAdminOverride || $requestedMonthNumber === null || $requestedMonthNumber <= 0)) {
            $monthNumber = $lastMonth;
        } else {
            $preferredNeed = $member->preferredChitNeedPeriod();
            if ($requestedMonthNumber !== null && $requestedMonthNumber > 0) {
                $monthNumber = (int) $requestedMonthNumber;
            } elseif ($preferredNeed) {
                $monthNumber = $preferredNeed;
            } else {
                $monthNumber = $this->getNextSettlementMonth($group);
            }
        }

        // Same-month multi-member: if that month already has a paid original → Advance Amount.
        if ($group->allowsAdvancePayouts()
            && ! $mustSettleLast
            && $this->canInitiateAdvanceSettlement($group, $member, $monthNumber)
        ) {
            $original = $this->originalSettlementForMonth($group, $monthNumber);
            if ($original && $original->status === 'paid') {
                return [
                    'kind' => Payout::KIND_ADVANCE,
                    'month_number' => $monthNumber,
                    'original_payout' => $original,
                ];
            }
        }

        if ($group->isForemanCommissionMonth($monthNumber)) {
            throw ValidationException::withMessages([
                'month_number' => 'Member settlements are not allowed for Month ' . $monthNumber . ' (Foreman Commission month).',
            ]);
        }

        return [
            'kind' => Payout::KIND_ORIGINAL,
            'month_number' => $monthNumber,
            'original_payout' => null,
        ];
    }

    /**
     * @return array{kind: string, month_number: int, original_payout: Payout}
     */
    protected function resolveAdvanceSettlementContext(ChitGroup $group, GroupMember $member): array
    {
        if ($this->memberMustSettleLast($member)) {
            throw ValidationException::withMessages([
                'member_id' => 'Transferred members can only take settlement in the last eligible month (not advance).',
            ]);
        }

        $month = (int) $group->current_month;
        $original = $this->originalSettlementForMonth($group, $month);

        if (!$group->allowsAdvancePayouts()) {
            throw ValidationException::withMessages([
                'member_id' => 'Advance Amount payouts are only available for non-registered chits.',
            ]);
        }

        if (!$original || $original->status !== 'paid') {
            throw ValidationException::withMessages([
                'group_id' => 'An original payout must be completed before advance payouts can be recorded.',
            ]);
        }

        if (!$this->isMemberEligible($group, $member)) {
            throw ValidationException::withMessages([
                'member_id' => 'This member is not eligible for an advance payout.',
            ]);
        }

        return [
            'kind' => Payout::KIND_ADVANCE,
            'month_number' => $month,
            'original_payout' => $original,
        ];
    }

    public function isMemberEligible(ChitGroup $group, GroupMember $member): bool
    {
        if ((int) $member->group_id !== (int) $group->id) {
            return false;
        }

        $allowedStatuses = ['active', 'approved'];
        if ($this->isOutgoingTransferredMember($member)) {
            $allowedStatuses[] = 'transferred';
        }
        if ($this->isCancelledWithdrawnMember($member)) {
            $allowedStatuses[] = 'withdrawn';
            $allowedStatuses[] = 'cancelled';
        }

        if (! in_array($member->status, $allowedStatuses, true)) {
            return false;
        }

        if ($member->has_won_auction) {
            return false;
        }

        if ($this->isOutgoingTransferredMember($member) || $this->isCancelledWithdrawnMember($member)) {
            if ($this->contributionSettlementAmount($member) <= 0.009) {
                return false;
            }
            if ($this->isOutgoingTransferredMember($member) && $this->outgoingTransferBuyoutAlreadyPaid($member)) {
                return false;
            }
        }

        return ! $group->payouts()
            ->where('winner_member_id', $member->id)
            ->whereIn('status', ['pending', 'paid', 'processing'])
            ->exists();
    }

    public function memberHasCompletedSettlement(ChitGroup $group, GroupMember $member): bool
    {
        return $group->payouts()
            ->where('winner_member_id', $member->id)
            ->where('status', 'paid')
            ->exists();
    }

    /**
     * @return array{commission: float, payout_amount: float, winning_bid: float, month_number: int}
     */
    public function calculateAmounts(ChitGroup $group, ?float $winningBid = null, ?int $monthNumber = null, ?GroupMember $member = null): array
    {
        $group->loadMissing('scheme');
        $month = $monthNumber ?? $this->getNextSettlementMonth($group);

        if ($member && $this->usesContributionSettlement($member)) {
            $payoutAmount = $this->contributionSettlementAmount($member);

            return [
                'commission' => 0.0,
                'payout_amount' => round($payoutAmount, 2),
                'winning_bid' => round($payoutAmount, 2),
                'month_number' => $month,
            ];
        }

        $commission = round((float) $group->chit_value * (float) $group->commission_pct / 100, 2);
        $bid = $winningBid ?? (float) $group->chit_value;
        $payoutAmount = $group->resolvePayoutAmountForMonth($month, $winningBid);

        if ($member) {
            $sharePct = (float) ($member->effective_share_percentage ?? 100.00);
            $payoutAmount = round($payoutAmount * ($sharePct / 100.0), 2);
        }

        return [
            'commission' => $commission,
            'payout_amount' => round($payoutAmount, 2),
            'winning_bid' => round($bid, 2),
            'month_number' => $month,
        ];
    }

    public function getGroupOverview(ChitGroup $group): array
    {
        $group->loadMissing(['members.client', 'scheme', 'payouts.winner.client', 'payouts.processedBy']);
        $nextMonth = $this->getNextSettlementMonth($group);
        $currentSettlement = $this->originalSettlementForMonth($group, $nextMonth);
        $amounts = $this->calculateAmounts($group, null, $nextMonth);
        $advanceMonth = (int) $group->current_month;
        $advanceEligibleMembers = $this->getAdvanceEligibleMembers($group, $advanceMonth);
        $advanceSettlements = $advanceMonth > 0
            ? $this->advanceSettlementsForMonth($group, $advanceMonth)
            : collect();

        $eligibleMembers = $group->members
            ->filter(fn (GroupMember $member) => $this->isMemberEligible($group, $member))
            ->values();

        $status = 'awaiting';
        if ($group->current_month >= $group->total_months) {
            $status = 'completed';
        } elseif ($currentSettlement) {
            $status = $currentSettlement->status;
        }

        return [
            'group' => $group,
            'group_name' => $group->scheme->name ?? $group->group_code,
            'group_code' => $group->group_code,
            'registration_label' => $group->registration_type_label,
            'allows_advance' => $group->allowsAdvancePayouts(),
            'total_members' => $group->members->count(),
            'current_month' => $nextMonth,
            'advance_period' => $advanceMonth,
            'completed_months' => (int) $group->current_month,
            'settlement' => $currentSettlement,
            'settlement_amount' => $amounts['payout_amount'],
            'eligible_members' => $eligibleMembers,
            'advance_eligible_members' => $advanceEligibleMembers,
            'advance_settlements' => $advanceSettlements,
            'can_initiate' => $this->canInitiateSettlement($group),
            'status' => $status,
            'settlement_date' => $currentSettlement?->paid_date,
            'processed_by' => $currentSettlement?->processedBy,
        ];
    }

    public function getAllGroupsOverview(?string $search = null, ?int $clientId = null, ?int $groupId = null): Collection
    {
        $query = ChitGroup::with(['members.client', 'scheme', 'payouts.winner.client', 'payouts.processedBy'])
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('group_code');

        if ($groupId) {
            $query->where('id', $groupId);
        }

        if ($clientId) {
            $query->whereHas('members', fn (Builder $m) => $m->where('client_id', $clientId));
        } elseif (!empty($search)) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('group_code', 'like', "%{$search}%")
                    ->orWhereHas('scheme', fn (Builder $s) => $s->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('members.client', fn (Builder $c) => $c
                        ->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_phone', 'like', "%{$search}%"));
            });
        }

        return $query->get()
            ->map(fn (ChitGroup $group) => $this->getGroupOverview($group));
    }

    public function historyQuery(array $filters = []): Builder
    {
        $query = Payout::with(['group.scheme', 'winner.client', 'processedBy', 'initiatedBy', 'originalPayout'])
            ->latest('paid_date')
            ->latest('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payout_kind'])) {
            $query->where('payout_kind', $filters['payout_kind']);
        }

        if (!empty($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }

        if (!empty($filters['client_id'])) {
            $clientId = (int) $filters['client_id'];
            $query->whereHas('winner', fn (Builder $w) => $w->where('client_id', $clientId));
        }

        if (!empty($filters['month_number'])) {
            $query->where('month_number', $filters['month_number']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('payout_code', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('winner.client', fn (Builder $c) => $c
                        ->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_phone', 'like', "%{$search}%"))
                    ->orWhereHas('group', fn (Builder $g) => $g
                        ->where('group_code', 'like', "%{$search}%"));
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('paid_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('paid_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    public function initiateSettlement(ChitGroup $group, GroupMember $member, ?int $initiatedBy = null, ?string $payoutKind = null, ?int $monthNumber = null, ?string $appliedSource = null): Payout
    {
        $context = $this->resolveSettlementContext($group, $member, $payoutKind, $monthNumber);
        $this->assertCanInitiate($group, $member, $context);

        $monthNumber = $context['month_number'];
        $amounts = $this->calculateAmounts($group, null, $monthNumber, $member);
        $auction = $this->resolveAuction($group, $monthNumber);
        $source = $appliedSource ?? \App\Services\AppNotificationService::detectSource();

        $payout = DB::transaction(function () use ($group, $member, $monthNumber, $amounts, $auction, $initiatedBy, $context, $source) {
            $this->assertNoDuplicate($group, $member, $monthNumber, $context['kind']);

            $remarks = null;
            if ($this->isCancelledWithdrawnMember($member)) {
                $remarks = 'Cancel settlement: paid months − foreman commission month (₹'
                    . number_format((float) $amounts['payout_amount'], 2) . ')';
            } elseif ($this->isOutgoingTransferredMember($member)) {
                $remarks = 'Outgoing transfer settlement: paid months − foreman commission month (₹'
                    . number_format((float) $amounts['payout_amount'], 2) . ')';
            }

            return Payout::create([
                'payout_code' => Payout::generateCode(),
                'group_id' => $group->id,
                'auction_id' => $auction->id,
                'winner_member_id' => $member->id,
                'month_number' => $monthNumber,
                'payout_kind' => $context['kind'],
                'original_payout_id' => $context['original_payout']?->id,
                'chit_value' => $group->chit_value,
                'winning_bid' => $amounts['winning_bid'],
                'commission_amount' => $amounts['commission'],
                'payout_amount' => $amounts['payout_amount'],
                'share_percentage' => $member->effective_share_percentage,
                'status' => 'pending',
                'remarks' => $remarks,
                'initiated_by' => $initiatedBy ?? Auth::id(),
                'applied_source' => $source,
            ]);
        });

        event(new \App\Events\NewSettlementApplicationEvent(
            $payout->loadMissing(['winner.client', 'group']),
            $source
        ));

        return $payout;
    }

    /**
     * @param  array{payment_mode: string, paid_date?: string|null, bank_name?: string|null, account_number?: string|null, ifsc_code?: string|null, upi_id?: string|null, reference_no?: string|null, remarks?: string|null}  $paymentData
     */
    public function processSettlement(Payout $payout, array $paymentData, ?int $processedBy = null): Payout
    {
        if ($payout->status === 'paid') {
            throw ValidationException::withMessages([
                'status' => 'This settlement has already been processed.',
            ]);
        }

        if ($payout->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'This settlement has been cancelled.',
            ]);
        }

        $payout = DB::transaction(function () use ($payout, $paymentData, $processedBy) {
            $payout = Payout::whereKey($payout->id)->lockForUpdate()->with(['group', 'winner'])->firstOrFail();

            if (in_array($payout->status, ['paid', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This settlement cannot be processed.',
                ]);
            }

            $paidDate = $paymentData['paid_date'] ?? today()->toDateString();
            $fees = $this->resolveSettlementFees($paymentData, (float) $payout->payout_amount);

            $isOutgoingContribution = $payout->winner && $this->isOutgoingTransferredMember($payout->winner);
            $isCancelContribution = $payout->winner && $this->isCancelledWithdrawnMember($payout->winner);
            $isContributionRefund = $isOutgoingContribution || $isCancelContribution;
            if (! $isContributionRefund && $payout->group) {
                $sharePct = (float) ($payout->share_percentage ?? $payout->winner?->effective_share_percentage ?? 100);
                $this->assertDividendPoolAllowsPayout(
                    $payout->group,
                    (float) $payout->payout_amount,
                    $sharePct,
                    (float) ($payout->chit_value ?? $payout->group->chit_value)
                );
            }

            $bankAccountId = $paymentData['internal_bank_account_id'] ?? null;
            // Resolve cash/bank account up-front (validates electronic modes require a bank).
            $resolvedBankId = $this->chitAccountingService->resolvePayoutBankAccountId(
                (string) ($paymentData['payment_mode'] ?? 'cash'),
                (int) ($bankAccountId ?: 0),
                (float) $fees['net_payout_amount']
            );

            $payout->update([
                'payment_mode' => $paymentData['payment_mode'],
                'internal_bank_account_id' => $resolvedBankId,
                'bank_name' => $paymentData['bank_name'] ?? null,
                'account_number' => $paymentData['account_number'] ?? null,
                'ifsc_code' => $paymentData['ifsc_code'] ?? null,
                'upi_id' => $paymentData['upi_id'] ?? null,
                'reference_no' => $paymentData['reference_no'] ?? null,
                'remarks' => $paymentData['remarks'] ?? null,
                'processing_fee' => $fees['processing_fee'],
                'document_charges' => $fees['document_charges'],
                'other_charges' => $fees['other_charges'],
                'banking_charges' => $fees['banking_charges'],
                'net_payout_amount' => $fees['net_payout_amount'],
                'settlement_document' => $paymentData['settlement_document'] ?? $payout->settlement_document,
                'other_document' => $paymentData['other_document'] ?? $payout->other_document,
                'collateral_document' => $paymentData['collateral_document'] ?? $payout->collateral_document,
                'status' => 'paid',
                'paid_date' => $paidDate,
                'processed_by' => $processedBy ?? Auth::id(),
            ]);

            $this->chitAccountingService->recordSettlementPayout(
                $payout->fresh(['group', 'winner.client']),
                array_merge($paymentData, ['internal_bank_account_id' => $resolvedBankId])
            );

            if ($payout->winner) {
                $payout->winner->update(['has_won_auction' => true]);
                \App\Models\ChitReferralBonus::generateForMember($payout->winner);

                if ($this->isOutgoingTransferredMember($payout->winner)) {
                    $this->markOutgoingTransferBuyoutPaid($payout->winner);
                }
            }

            if ($payout->auction_id && ! $isContributionRefund) {
                Auction::whereKey($payout->auction_id)->update([
                    'winner_member_id' => $payout->winner_member_id,
                    'winning_bid' => $payout->winning_bid,
                    'status' => 'completed',
                ]);
            }

            // Contribution refunds (transfer buyout / cancelled client) do not advance the group calendar.
            if ($payout->isOriginal() && ! $isContributionRefund) {
                $group = $payout->group;
                $groupUpdates = ['current_month' => max((int) $group->current_month, (int) $payout->month_number)];

                if ($groupUpdates['current_month'] >= $group->total_months) {
                    $groupUpdates['status'] = 'completed';
                }

                $group->update($groupUpdates);

                if (($groupUpdates['status'] ?? null) === 'completed') {
                    $group->markActiveMembersCompleted();
                }
            }

            if (! $isOutgoingContribution) {
                $this->syncDividendPoolForPaidPayout($payout->fresh(['group', 'winner']), $processedBy ?? Auth::id());
                $this->applySchemePostPayoutInstallmentAdjustment($payout->fresh(['group.scheme', 'winner']));
            }

            return $payout->fresh(['group.scheme', 'winner.client', 'auction', 'processedBy', 'initiatedBy', 'originalPayout']);
        });

        event(new \App\Events\ChitSettlementPaidEvent($payout));

        return $payout;
    }

    /**
     * @param  array{payment_mode: string, paid_date?: string|null, bank_name?: string|null, account_number?: string|null, ifsc_code?: string|null, upi_id?: string|null, reference_no?: string|null, remarks?: string|null}  $paymentData
     */
    public function settleAndPay(ChitGroup $group, GroupMember $member, array $paymentData, ?int $processedBy = null, ?string $payoutKind = null): Payout
    {
        $context = $this->resolveSettlementContext($group, $member, $payoutKind);
        $this->assertCanInitiate($group, $member, $context);

        $monthNumber = $context['month_number'];
        $amounts = $this->calculateAmounts($group, null, $monthNumber, $member);
        $auction = $this->resolveAuction($group, $monthNumber);
        $processedBy = $processedBy ?? Auth::id();
        $paidDate = $paymentData['paid_date'] ?? today()->toDateString();
        $isOutgoingContribution = $this->isOutgoingTransferredMember($member);
        $isCancelContribution = $this->isCancelledWithdrawnMember($member);
        $isContributionRefund = $isOutgoingContribution || $isCancelContribution;

        $payout = DB::transaction(function () use ($group, $member, $monthNumber, $amounts, $auction, $paymentData, $processedBy, $paidDate, $context, $isContributionRefund, $isOutgoingContribution) {
            $this->assertNoDuplicate($group, $member, $monthNumber, $context['kind']);
            $fees = $this->resolveSettlementFees($paymentData, (float) $amounts['payout_amount']);

            if (! $isContributionRefund) {
                $sharePct = (float) ($member->effective_share_percentage ?? 100);
                $this->assertDividendPoolAllowsPayout(
                    $group,
                    (float) $amounts['payout_amount'],
                    $sharePct,
                    (float) $group->chit_value
                );
            }

            $bankAccountId = $paymentData['internal_bank_account_id'] ?? null;
            $resolvedBankId = $this->chitAccountingService->resolvePayoutBankAccountId(
                (string) ($paymentData['payment_mode'] ?? 'cash'),
                (int) ($bankAccountId ?: 0),
                (float) $fees['net_payout_amount']
            );
            $payoutCode = Payout::generateCode();

            $payout = Payout::create([
                'payout_code' => $payoutCode,
                'group_id' => $group->id,
                'auction_id' => $auction->id,
                'winner_member_id' => $member->id,
                'month_number' => $monthNumber,
                'payout_kind' => $context['kind'],
                'original_payout_id' => $context['original_payout']?->id,
                'chit_value' => $group->chit_value,
                'winning_bid' => $amounts['winning_bid'],
                'commission_amount' => $amounts['commission'],
                'payout_amount' => $amounts['payout_amount'],
                'share_percentage' => $member->effective_share_percentage,
                'processing_fee' => $fees['processing_fee'],
                'document_charges' => $fees['document_charges'],
                'other_charges' => $fees['other_charges'],
                'banking_charges' => $fees['banking_charges'],
                'net_payout_amount' => $fees['net_payout_amount'],
                'payment_mode' => $paymentData['payment_mode'],
                'internal_bank_account_id' => $resolvedBankId,
                'bank_name' => $paymentData['bank_name'] ?? null,
                'account_number' => $paymentData['account_number'] ?? null,
                'ifsc_code' => $paymentData['ifsc_code'] ?? null,
                'upi_id' => $paymentData['upi_id'] ?? null,
                'reference_no' => $paymentData['reference_no'] ?? null,
                'remarks' => $paymentData['remarks'] ?? null,
                'settlement_document' => $paymentData['settlement_document'] ?? null,
                'other_document' => $paymentData['other_document'] ?? null,
                'collateral_document' => $paymentData['collateral_document'] ?? null,
                'status' => 'paid',
                'paid_date' => $paidDate,
                'initiated_by' => $processedBy,
                'processed_by' => $processedBy,
            ]);

            $this->chitAccountingService->recordSettlementPayout(
                $payout->load(['group', 'winner.client']),
                array_merge($paymentData, ['internal_bank_account_id' => $resolvedBankId])
            );

            $member->update(['has_won_auction' => true]);
            \App\Models\ChitReferralBonus::generateForMember($member);

            if ($isOutgoingContribution) {
                $this->markOutgoingTransferBuyoutPaid($member);
            } elseif (! $isContributionRefund) {
                Auction::whereKey($auction->id)->update([
                    'winner_member_id' => $member->id,
                    'winning_bid' => $amounts['winning_bid'],
                    'status' => 'completed',
                ]);
            }

            if ($context['kind'] === Payout::KIND_ORIGINAL && ! $isContributionRefund) {
                $groupUpdates = ['current_month' => max((int) $group->current_month, $monthNumber)];
                if ($groupUpdates['current_month'] >= $group->total_months) {
                    $groupUpdates['status'] = 'completed';
                }
                $group->update($groupUpdates);

                if (($groupUpdates['status'] ?? null) === 'completed') {
                    $group->markActiveMembersCompleted();
                }
            }

            if (! $isContributionRefund) {
                $this->syncDividendPoolForPaidPayout($payout->fresh(['group', 'winner']), $processedBy);
                $this->applySchemePostPayoutInstallmentAdjustment($payout->fresh(['group.scheme', 'winner']));
            }

            return $payout->fresh(['group.scheme', 'winner.client', 'processedBy', 'initiatedBy', 'originalPayout']);
        });

        event(new \App\Events\ChitSettlementPaidEvent($payout));

        return $payout;
    }

    protected function applySchemePostPayoutInstallmentAdjustment(Payout $payout): void
    {
        $winner = $payout->winner;
        $group = $payout->group;
        $scheme = $group?->scheme;

        if ($winner && $group && $scheme && (float) $scheme->post_payout_installment_adjustment > 0) {
            $startMonth = (int) $payout->month_number + 1;
            $adj = (float) $scheme->post_payout_installment_adjustment;
            $baseInst = (float) ($winner->custom_installment_amount ?: $group->installment_amount);
            $newInstallment = $adj >= $baseInst ? $adj : ($baseInst + $adj);

            $winner->updateFutureInstallmentAmount($newInstallment, $startMonth);
        }
    }

    public function cancelSettlement(Payout $payout, ?int $cancelledBy = null, ?string $remarks = null): Payout
    {
        if ($payout->status === 'paid') {
            throw ValidationException::withMessages([
                'status' => 'Paid settlements cannot be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($payout, $remarks) {
            $payout = Payout::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Paid settlements cannot be cancelled.',
                ]);
            }

            $payout->update([
                'status' => 'cancelled',
                'remarks' => $remarks ?? $payout->remarks,
            ]);

            if ($payout->winner_member_id) {
                \App\Models\GroupMember::whereKey($payout->winner_member_id)->update([
                    'chit_need_month' => null,
                    'chit_need_date' => null,
                ]);
            }

            return $payout->fresh();
        });
    }

    public function createAuctionSettlement(
        ChitGroup $group,
        Auction $auction,
        GroupMember $winner,
        float $winningBid,
        ?int $initiatedBy = null
    ): Payout {
        if ($auction->group_id !== $group->id) {
            throw ValidationException::withMessages([
                'auction_id' => 'Auction does not belong to this group.',
            ]);
        }

        if ($this->originalSettlementForMonth($group, $auction->month_number)) {
            throw ValidationException::withMessages([
                'month_number' => 'A settlement already exists for this month.',
            ]);
        }

        if (!$this->isMemberEligible($group, $winner)) {
            throw ValidationException::withMessages([
                'member_id' => 'This member is not eligible for settlement.',
            ]);
        }

        $amounts = $this->calculateAmounts($group, $winningBid, $auction->month_number);

        return DB::transaction(function () use ($group, $auction, $winner, $amounts, $initiatedBy) {
            return Payout::create([
                'payout_code' => Payout::generateCode(),
                'group_id' => $group->id,
                'auction_id' => $auction->id,
                'winner_member_id' => $winner->id,
                'month_number' => $auction->month_number,
                'payout_kind' => Payout::KIND_ORIGINAL,
                'chit_value' => $group->chit_value,
                'winning_bid' => $amounts['winning_bid'],
                'commission_amount' => $amounts['commission'],
                'payout_amount' => $amounts['payout_amount'],
                'status' => 'pending',
                'initiated_by' => $initiatedBy ?? Auth::id(),
            ]);
        });
    }

    /**
     * @param  array{kind: string, month_number: int, original_payout: ?Payout}  $context
     */
    protected function assertCanInitiate(ChitGroup $group, GroupMember $member, array $context): void
    {
        if ($group->status !== 'active') {
            throw ValidationException::withMessages([
                'group_id' => 'Settlements can only be initiated for active groups.',
            ]);
        }

        if ($group->isForemanCommissionMonth($context['month_number'])) {
            throw ValidationException::withMessages([
                'group_id' => 'Member settlements are not allowed for Month ' . $context['month_number'] . ' (Foreman Commission month).',
            ]);
        }

        $isOutgoingContribution = $this->isOutgoingTransferredMember($member)
            || $this->isCancelledWithdrawnMember($member);

        // Advance Amount shares the same calendar month as a paid original — do not block on that original.
        if ($context['kind'] === Payout::KIND_ADVANCE) {
            if ($this->isCancelledWithdrawnMember($member)) {
                throw ValidationException::withMessages([
                    'member_id' => 'Cancelled clients can only apply for contribution settlement (paid months − foreman commission), not advance payout.',
                ]);
            }
            if (!$group->allowsAdvancePayouts()) {
                throw ValidationException::withMessages([
                    'group_id' => 'Advance Amount payouts are only available for non-registered chits.',
                ]);
            }

            if (!$this->canInitiateAdvanceSettlement($group, $member, $context['month_number'])) {
                throw ValidationException::withMessages([
                    'member_id' => 'This member is not eligible for an advance payout.',
                ]);
            }

            return;
        }

        // One original settlement per month (advance payouts may share the month).
        if (! $isOutgoingContribution
            && $group->payouts()
                ->where('month_number', $context['month_number'])
                ->where('payout_kind', Payout::KIND_ORIGINAL)
                ->whereIn('status', ['pending', 'paid', 'processing'])
                ->whereHas('winner', fn (Builder $w) => $w->whereNotIn('status', ['transferred', 'withdrawn', 'cancelled']))
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'month_number' => "A settlement payout already exists for Month {$context['month_number']} in Group {$group->group_code}. Additional members can take Advance Amount after that settlement is paid.",
            ]);
        }

        if (! $isOutgoingContribution && $group->current_month >= $group->total_months) {
            throw ValidationException::withMessages([
                'group_id' => 'All monthly settlements for this group have been completed.',
            ]);
        }

        if ($this->isOutgoingTransferredMember($member) && $this->outgoingTransferBuyoutAlreadyPaid($member)) {
            throw ValidationException::withMessages([
                'member_id' => 'Outgoing transfer settlement has already been paid for this member.',
            ]);
        }

        if ($this->usesContributionSettlement($member) && $this->contributionSettlementAmount($member) <= 0.009) {
            throw ValidationException::withMessages([
                'member_id' => 'No eligible paid installments (excluding foreman commission month) for contribution settlement.',
            ]);
        }

        $activeApplication = Payout::where('group_id', $group->id)
            ->where('winner_member_id', $member->id)
            ->whereIn('status', ['pending', 'processing', 'paid'])
            ->first();

        if ($activeApplication) {
            $statusLabel = ucfirst($activeApplication->status);
            throw ValidationException::withMessages([
                'member_id' => "This member already has an active settlement application ({$statusLabel}) for Group {$group->group_code}. Re-applying is allowed after cancellation or rejection.",
            ]);
        }

        if (!$this->isMemberEligible($group, $member)) {
            throw ValidationException::withMessages([
                'member_id' => 'This member is not eligible for settlement.',
            ]);
        }
    }

    protected function assertNoDuplicate(ChitGroup $group, GroupMember $member, int $monthNumber, string $payoutKind): void
    {
        $memberPayout = Payout::where('group_id', $group->id)
            ->where('winner_member_id', $member->id)
            ->whereIn('status', ['pending', 'paid', 'processing'])
            ->lockForUpdate()
            ->first();

        if ($memberPayout) {
            $statusLabel = ucfirst($memberPayout->status);
            throw ValidationException::withMessages([
                'member_id' => "This member already has an active settlement application ({$statusLabel}) for Group {$group->group_code}. Re-applying is allowed after cancellation or rejection.",
            ]);
        }
    }

    /**
     * @return array{processing_fee: float, document_charges: float, other_charges: float, banking_charges: float, net_payout_amount: float}
     */
    public function resolveSettlementFees(array $paymentData, float $grossPayout): array
    {
        $processing = round((float) ($paymentData['processing_fee'] ?? 0), 2);
        $document = round((float) ($paymentData['document_charges'] ?? 0), 2);
        $other = round((float) ($paymentData['other_charges'] ?? 0), 2);
        $banking = round((float) ($paymentData['banking_charges'] ?? 0), 2);
        // Banking charges are company bank cost — do not reduce member settlement payout.
        $net = round(max(0, $grossPayout - $processing - $document - $other), 2);

        return [
            'processing_fee' => $processing,
            'document_charges' => $document,
            'other_charges' => $other,
            'banking_charges' => $banking,
            'net_payout_amount' => $net,
        ];
    }

    public function defaultSettlementFees(): array
    {
        return [
            'processing_fee' => (float) \App\Models\ChitConfiguration::get('settlement_processing_fee', 0),
            'document_charges' => (float) \App\Models\ChitConfiguration::get('settlement_document_charges', 0),
            'other_charges' => (float) \App\Models\ChitConfiguration::get('settlement_other_charges', 0),
            'banking_charges' => (float) \App\Models\ChitConfiguration::get('settlement_banking_charges', 0),
        ];
    }

    /**
     * Credit surplus (chit − payout) or debit excess (payout − chit) from group dividend pool.
     */
    public function syncDividendPoolForPaidPayout(Payout $payout, ?int $createdBy = null): void
    {
        $group = $payout->group;
        if (! $group) {
            return;
        }

        if ($payout->winner && (
            $this->isOutgoingTransferredMember($payout->winner)
            || $this->isCancelledWithdrawnMember($payout->winner)
        )) {
            return;
        }

        $sharePct = (float) ($payout->share_percentage ?? $payout->winner?->effective_share_percentage ?? 100);
        $this->assertDividendPoolAllowsPayout(
            $group,
            (float) $payout->payout_amount,
            $sharePct,
            (float) ($payout->chit_value ?? $group->chit_value)
        );

        $group->applySettlementToDividendPool($payout, $createdBy);
    }

    /**
     * Block paying above chit value when the dividend pool cannot cover the excess.
     */
    public function assertDividendPoolAllowsPayout(
        ChitGroup $group,
        float $payoutAmount,
        float $sharePercentage = 100.0,
        ?float $chitValue = null
    ): void {
        $delta = $group->dividendPoolDeltaForPayout($payoutAmount, $sharePercentage, $chitValue);
        if ($delta['diff'] >= -0.009) {
            return;
        }

        $user = Auth::user();
        $isAdmin = $user && ($user->id === 1 || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'admin', 'Super Admin', 'super_admin', 'super-admin'])));

        // Admin override allows processing/releasing payouts even when pool balance is insufficient
        if ($isAdmin) {
            return;
        }

        $needed = abs($delta['diff']);
        $balance = (float) $group->dividend_pool_balance;
        if ($balance + 0.009 < $needed) {
            throw ValidationException::withMessages([
                'payout_amount' => sprintf(
                    'Payout ₹%s exceeds chit value ₹%s by ₹%s. Dividend pool has only ₹%s — accumulate surplus from earlier months first.',
                    number_format($delta['payout_amount'], 2),
                    number_format($delta['effective_chit'], 2),
                    number_format($needed, 2),
                    number_format($balance, 2)
                ),
            ]);
        }
    }

    protected function resolveAuction(ChitGroup $group, int $monthNumber): Auction
    {
        $auction = Auction::where('group_id', $group->id)
            ->where('month_number', $monthNumber)
            ->first();

        if ($auction) {
            return $auction;
        }

        return Auction::create([
            'group_id' => $group->id,
            'month_number' => $monthNumber,
            'auction_date' => today(),
            'status' => 'completed',
            'min_bid' => $group->installment_amount,
            'max_bid' => $group->chit_value,
            'winner_member_id' => null,
        ]);
    }
}
