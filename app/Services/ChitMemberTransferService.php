<?php

namespace App\Services;

use App\Models\AuctionBid;
use App\Models\ChitConfiguration;
use App\Models\ChitGroup;
use App\Models\ChitMemberTransfer;
use App\Models\Client;
use App\Models\DividendDistribution;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\Payout;
use App\Services\FixedDeposit\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChitMemberTransferService
{
    public function __construct(
        protected WalletService $walletService,
        protected ChitPayoutService $payoutService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(GroupMember $outgoing, ?int $destinationGroupId = null): array
    {
        $this->assertTransferEligible($outgoing);
        $outgoing->loadMissing(['client']);

        $amounts = $this->calculateAmounts($outgoing);
        $group = $outgoing->group;
        $amounts['same_group_installment_sample'] = round((float) ($group?->getInstallmentAmountForMonth(1) ?? 0), 2);
        $amounts['same_group_total_months'] = (int) ($group?->total_months ?: $group?->scheme?->duration_months ?: 0);

        if ($destinationGroupId) {
            $destination = ChitGroup::with('scheme')->find($destinationGroupId);
            if ($destination && (int) $destination->id !== (int) $outgoing->group_id) {
                $amounts['credit_allocation'] = $this->previewCreditAllocation(
                    (float) $amounts['paid_installments_total'],
                    $destination
                );
                $amounts['destination_group_code'] = $destination->group_code;
                $amounts['destination_installment_sample'] = round(
                    (float) $destination->getInstallmentAmountForMonth(1),
                    2
                );

                $clientName = (string) ($outgoing->client->client_name ?? 'Client');
                $alreadyActive = GroupMember::where('group_id', $destination->id)
                    ->where('client_id', $outgoing->client_id)
                    ->where('id', '!=', $outgoing->id)
                    ->where('status', 'active')
                    ->exists();

                $amounts['already_active_in_destination'] = $alreadyActive;
                $amounts['second_seat_label'] = $alreadyActive
                    ? trim($clientName) . ' B'
                    : null;
                $amounts['client_name'] = $clientName;
            }
        }

        return $amounts;
    }

    /**
     * @param  array{
     *     transfer_type?: string,
     *     destination_group_id?: int|null,
     *     incoming_client_id?: int|null,
     *     chit_need_month?: int|null,
     *     referred_by?: string|null,
     *     takeover_payment_mode?: string,
     *     takeover_reference_no?: string|null,
     *     outgoing_settlement_mode?: string,
     *     outgoing_settlement_reference_no?: string|null,
     *     transfer_date?: string,
     *     remarks?: string|null,
     * }  $data
     */
    public function process(GroupMember $outgoing, array $data): ChitMemberTransfer
    {
        $type = ($data['transfer_type'] ?? 'same_group') === 'cross_group'
            ? 'cross_group'
            : 'same_group';

        if ($type === 'cross_group') {
            return $this->processCrossGroup($outgoing, $data);
        }

        return $this->processSameGroup($outgoing, $data);
    }

    /**
     * Same-group ticket takeover: outgoing leaves; incoming replaces the seat with a
     * full fresh schedule. Prepaid credit covers completed rounds; remaining months stay due.
     * Outgoing settlement = paid installments − foreman commission (via settlement app / release).
     *
     * @param  array<string, mixed>  $data
     */
    public function processSameGroup(GroupMember $outgoing, array $data): ChitMemberTransfer
    {
        $this->assertTransferEligible($outgoing);

        $group = $outgoing->group;
        $amounts = $this->calculateAmounts($outgoing);
        $incomingClient = Client::where('status', 'active')->findOrFail((int) $data['incoming_client_id']);

        if ((int) $incomingClient->id === (int) $outgoing->client_id) {
            throw ValidationException::withMessages([
                'incoming_client_id' => 'Incoming member must be a different customer.',
            ]);
        }

        $this->assertIncomingClientEligible($group, $incomingClient, $outgoing);

        // Optional incoming collection at transfer (FIFO across installments).
        // Leave 0 to start fully unpaid from scratch.
        $incomingPayment = round(max(0, (float) ($data['incoming_payment_amount'] ?? $data['takeover_amount'] ?? 0)), 2);
        $takeoverAmount = $incomingPayment;
        $paymentMode = strtolower((string) ($data['incoming_payment_mode'] ?? $data['takeover_payment_mode'] ?? ''));
        $paymentRef = $data['incoming_payment_reference_no'] ?? $data['takeover_reference_no'] ?? null;
        $bankAccountId = isset($data['internal_bank_account_id']) ? (int) $data['internal_bank_account_id'] : null;

        if ($incomingPayment > 0.009) {
            if (! in_array($paymentMode, ['cash', 'in_hand', 'upi', 'bank_transfer', 'wallet'], true)) {
                throw ValidationException::withMessages([
                    'incoming_payment_mode' => 'Select a payment mode for the incoming collection amount.',
                ]);
            }
            if (in_array($paymentMode, ['upi', 'bank_transfer'], true) && (! $bankAccountId || $bankAccountId < 1)) {
                throw ValidationException::withMessages([
                    'internal_bank_account_id' => 'Select the company bank account for UPI / bank transfer.',
                ]);
            }
        } else {
            $paymentMode = '';
            $bankAccountId = null;
            $paymentRef = null;
        }

        $outgoingSettlement = $amounts['outgoing_settlement_amount'];
        $transferDate = $data['transfer_date'] ?? today()->format('Y-m-d');

        [$referredByAgentId, $referredByClientId] = $this->parseReferrer($data['referred_by'] ?? null);
        $needFields = ! empty($data['chit_need_month'])
            ? GroupMember::resolveChitNeedFields((int) $data['chit_need_month'], $group)
            : [
                'chit_need_month' => $outgoing->chit_need_month,
                'chit_need_date' => $outgoing->chit_need_date,
            ];

        return DB::transaction(function () use (
            $outgoing,
            $group,
            $incomingClient,
            $amounts,
            $takeoverAmount,
            $incomingPayment,
            $paymentMode,
            $paymentRef,
            $bankAccountId,
            $outgoingSettlement,
            $transferDate,
            $data,
            $referredByAgentId,
            $referredByClientId,
            $needFields
        ) {
            $this->cancelOpenSettlementApplications($outgoing);

            $ticketNumber = (int) $outgoing->member_number;
            $slotReleaseNumber = 900000 + (int) $outgoing->id;

            $outgoing->update(['member_number' => $slotReleaseNumber]);

            // Close unpaid dues on outgoing seat (paid history kept for their settlement).
            Installment::where('member_id', $outgoing->id)
                ->whereNotIn('status', ['paid', 'waived'])
                ->update(['status' => 'waived']);

            $incoming = GroupMember::create([
                'group_id' => $group->id,
                'client_id' => $incomingClient->id,
                'is_shared' => false,
                'referred_by_agent_id' => $referredByAgentId,
                'referred_by_client_id' => $referredByClientId,
                'member_number' => $ticketNumber,
                'status' => 'active',
                'joined_date' => $transferDate,
                'chit_need_month' => $needFields['chit_need_month'],
                'chit_need_date' => $needFields['chit_need_date'],
                'has_won_auction' => false,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'transferred_from_member_id' => $outgoing->id,
                'remarks' => 'Joined via member transfer from ticket #' . $ticketNumber,
            ]);

            $incoming->shares()->create([
                'client_id' => $incomingClient->id,
                'ownership_percentage' => 100,
                'share_amount' => round((float) $group->chit_value, 2),
                'is_primary' => true,
            ]);

            // Fresh full schedule (same EMI amounts). Optionally collect now and split FIFO.
            $startDate = \Carbon\Carbon::parse($group->start_date ?? $transferDate);
            $group->generateInstallmentsForMember($incoming, $startDate);

            $allocation = ['applied' => 0.0, 'months_full' => 0, 'partial_month' => null, 'partial_amount' => 0.0];
            if ($incomingPayment > 0.009) {
                $allocation = $this->applyPrepaidCreditToInstallments(
                    $incoming,
                    $incomingPayment,
                    'Incoming payment at same-group transfer',
                    $paymentMode === 'in_hand' ? 'cash' : $paymentMode
                );
            }

            $remainingUnpaid = Installment::where('member_id', $incoming->id)
                ->whereNotIn('status', ['paid', 'waived'])
                ->count();

            DividendDistribution::where('member_id', $outgoing->id)
                ->where('status', 'pending')
                ->update(['member_id' => $incoming->id]);

            AuctionBid::where('member_id', $outgoing->id)
                ->where('is_winner', false)
                ->update(['member_id' => $incoming->id]);

            $transfer = ChitMemberTransfer::create([
                'transfer_code' => ChitMemberTransfer::generateCode(),
                'transfer_type' => 'same_group',
                'group_id' => $group->id,
                'destination_group_id' => null,
                'outgoing_member_id' => $outgoing->id,
                'incoming_member_id' => $incoming->id,
                'ticket_number' => $ticketNumber,
                'transfer_date' => $transferDate,
                'completed_rounds' => $amounts['completed_rounds'],
                'remaining_installments' => $remainingUnpaid,
                'paid_installments_total' => $amounts['paid_installments_total'],
                'outstanding_at_transfer' => $amounts['outstanding_at_transfer'],
                'takeover_amount' => $takeoverAmount,
                'outgoing_settlement_amount' => $outgoingSettlement,
                'transfer_fee' => $amounts['transfer_fee'],
                'takeover_payment_mode' => $incomingPayment > 0.009 ? ($paymentMode === 'in_hand' ? 'cash' : $paymentMode) : null,
                'takeover_reference_no' => $incomingPayment > 0.009 ? $paymentRef : null,
                'outgoing_settlement_mode' => null,
                'outgoing_settlement_reference_no' => null,
                'outgoing_settlement_status' => 'not_applicable',
                'outgoing_settlement_paid_at' => null,
                'remarks' => $data['remarks'] ?? null,
                'processed_by' => Auth::id(),
                'status' => 'completed',
            ]);

            if ($incomingPayment > 0.009 && (float) ($allocation['applied'] ?? 0) > 0.009) {
                $this->recordTakeoverPayment(
                    $transfer,
                    $incoming,
                    (float) $allocation['applied'],
                    $paymentMode === 'in_hand' ? 'cash' : $paymentMode,
                    $paymentRef,
                    $bankAccountId
                );
            }

            $outgoing->update([
                'status' => 'transferred',
                'member_number' => $slotReleaseNumber,
                'transferred_to_member_id' => $incoming->id,
                'remarks' => trim(($outgoing->remarks ? $outgoing->remarks . ' | ' : '')
                    . 'Transferred to ' . $incomingClient->client_name
                    . ' on ' . \Carbon\Carbon::parse($transferDate)->format('d M Y')
                    . ' (' . $transfer->transfer_code . ')'),
            ]);

            $this->purgeTransferredSourceSeat($outgoing);

            \App\Models\ChitReferralBonus::generateForMember($incoming);

            return $transfer->load(['outgoingMember.client', 'incomingMember.client', 'group', 'destinationGroup']);
        });
    }

    /**
     * Cross-group move: same client leaves source group and joins another.
     * Paid total in source is applied as prepaid credit on destination EMIs
     * (full months first, then partial). Settlement = paid − foreman on source group.
     *
     * @param  array<string, mixed>  $data
     */
    public function processCrossGroup(GroupMember $outgoing, array $data): ChitMemberTransfer
    {
        $this->assertTransferEligible($outgoing);

        $sourceGroup = $outgoing->group;
        $destinationGroup = ChitGroup::with('scheme')
            ->whereKey((int) ($data['destination_group_id'] ?? 0))
            ->first();

        if (! $destinationGroup) {
            throw ValidationException::withMessages([
                'destination_group_id' => 'Please select a valid destination group.',
            ]);
        }

        if ((int) $destinationGroup->id === (int) $sourceGroup->id) {
            throw ValidationException::withMessages([
                'destination_group_id' => 'Choose a different group. Same-group takeover uses the other transfer mode.',
            ]);
        }

        if ($destinationGroup->status !== 'active') {
            throw ValidationException::withMessages([
                'destination_group_id' => 'Destination group must be active.',
            ]);
        }

        $client = Client::where('status', 'active')->findOrFail((int) $outgoing->client_id);

        $amounts = $this->calculateAmounts($outgoing);
        $takeoverAmount = 0.0;
        $transferFee = $amounts['transfer_fee'];
        $transferDate = $data['transfer_date'] ?? today()->format('Y-m-d');

        [$referredByAgentId, $referredByClientId] = $this->parseReferrer($data['referred_by'] ?? null);
        $needFields = ! empty($data['chit_need_month'])
            ? GroupMember::resolveChitNeedFields((int) $data['chit_need_month'], $destinationGroup)
            : [
                'chit_need_month' => null,
                'chit_need_date' => null,
            ];

        return DB::transaction(function () use (
            $outgoing,
            $sourceGroup,
            $destinationGroup,
            $client,
            $amounts,
            $takeoverAmount,
            $transferFee,
            $transferDate,
            $data,
            $referredByAgentId,
            $referredByClientId,
            $needFields
        ) {
            $this->cancelOpenSettlementApplications($outgoing);

            // Active in source is expected. Pending destination apps are cleared.
            // If already active in destination, require confirm_second_seat to add as "Name B".
            $this->assertClientCanJoinDestination(
                $destinationGroup,
                $client,
                $outgoing,
                ! empty($data['confirm_second_seat'])
            );

            $activeCount = GroupMember::where('group_id', $destinationGroup->id)
                ->whereNotIn('status', ['rejected', 'transferred', 'withdrawn', 'cancelled'])
                ->count();

            if ($activeCount >= (int) $destinationGroup->total_members) {
                throw ValidationException::withMessages([
                    'destination_group_id' => 'Destination group is full. No open seats available.',
                ]);
            }

            $oldTicket = (int) $outgoing->member_number;
            $slotReleaseNumber = 900000 + (int) $outgoing->id;

            Installment::where('member_id', $outgoing->id)
                ->whereNotIn('status', ['paid', 'waived'])
                ->update(['status' => 'waived']);

            $newTicket = $destinationGroup->getNextAvailableMemberNumber();

            $incoming = GroupMember::create([
                'group_id' => $destinationGroup->id,
                'client_id' => $client->id,
                'is_shared' => false,
                'referred_by_agent_id' => $referredByAgentId,
                'referred_by_client_id' => $referredByClientId,
                'member_number' => $newTicket,
                'status' => 'active',
                'joined_date' => $transferDate,
                'chit_need_month' => $needFields['chit_need_month'],
                'chit_need_date' => $needFields['chit_need_date'],
                'has_won_auction' => false,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'transferred_from_member_id' => $outgoing->id,
                'remarks' => 'Joined via cross-group transfer from '
                    . ($sourceGroup->group_code ?? 'group')
                    . ' ticket #' . $oldTicket,
            ]);

            $incoming->shares()->create([
                'client_id' => $client->id,
                'ownership_percentage' => 100,
                'share_amount' => round((float) $destinationGroup->chit_value, 2),
                'is_primary' => true,
            ]);

            $startDate = \Carbon\Carbon::parse($destinationGroup->start_date ?? $transferDate);
            $destinationGroup->generateInstallmentsForMember($incoming, $startDate);

            // Apply source paid total onto destination EMIs: full months then partial.
            $this->applyPrepaidCreditToInstallments(
                $incoming,
                (float) $amounts['paid_installments_total'],
                'Cross-group transfer prepaid credit from ' . ($sourceGroup->group_code ?? 'source group')
            );

            $transfer = ChitMemberTransfer::create([
                'transfer_code' => ChitMemberTransfer::generateCode(),
                'transfer_type' => 'cross_group',
                'group_id' => $sourceGroup->id,
                'destination_group_id' => $destinationGroup->id,
                'outgoing_member_id' => $outgoing->id,
                'incoming_member_id' => $incoming->id,
                'ticket_number' => $oldTicket,
                'transfer_date' => $transferDate,
                'completed_rounds' => $amounts['completed_rounds'],
                'remaining_installments' => $amounts['remaining_installments'],
                'paid_installments_total' => $amounts['paid_installments_total'],
                'outstanding_at_transfer' => $amounts['outstanding_at_transfer'],
                'takeover_amount' => $takeoverAmount,
                'outgoing_settlement_amount' => 0,
                'transfer_fee' => $transferFee,
                'takeover_payment_mode' => null,
                'takeover_reference_no' => null,
                'outgoing_settlement_mode' => null,
                'outgoing_settlement_reference_no' => null,
                // Cross-group: no transfer buyout — member applies via Settlement Applications.
                'outgoing_settlement_status' => 'not_applicable',
                'outgoing_settlement_paid_at' => null,
                'remarks' => $data['remarks'] ?? null,
                'processed_by' => Auth::id(),
                'status' => 'completed',
            ]);

            $outgoing->update([
                'status' => 'transferred',
                'member_number' => $slotReleaseNumber,
                'transferred_to_member_id' => $incoming->id,
                'remarks' => trim(($outgoing->remarks ? $outgoing->remarks . ' | ' : '')
                    . 'Moved to group ' . ($destinationGroup->group_code ?? $destinationGroup->id)
                    . ' ticket #' . $newTicket
                    . ' on ' . \Carbon\Carbon::parse($transferDate)->format('d M Y')
                    . ' (' . $transfer->transfer_code . ')'),
            ]);

            // Remove this seat from the source group (GRP28 etc.) — keep row soft-deleted for settlement history.
            $this->purgeTransferredSourceSeat($outgoing);

            \App\Models\ChitReferralBonus::generateForMember($incoming);

            return $transfer->load(['outgoingMember.client', 'incomingMember.client', 'group', 'destinationGroup']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function calculateAmounts(GroupMember $outgoing): array
    {
        $outgoing->loadMissing(['installments', 'group']);

        /** @var Collection<int, Installment> $installments */
        $installments = $outgoing->installments->sortBy('month_number')->values();

        $unpaidInstallments = $installments->filter(
            fn (Installment $i) => ! in_array($i->status, ['paid', 'waived'], true)
        );

        // Total actually paid (includes partials) — used for credit + takeover base.
        $paidTotal = round((float) $installments->sum(fn (Installment $i) => (float) $i->paid_amount), 2);
        $completedRounds = $installments->filter(
            fn (Installment $i) => in_array($i->status, ['paid', 'waived'], true)
        )->count();
        $outstanding = round((float) $unpaidInstallments->sum(fn (Installment $i) => max(0, (float) $i->balance)), 2);
        $remainingCount = $unpaidInstallments->count();
        $transferFee = round((float) ChitConfiguration::get('member_transfer_fee', '0'), 2);

        // Same-group takeover: incoming pays for completed rounds (+ fee).
        $takeoverAmount = round($paidTotal + $transferFee, 2);
        // Outgoing settlement: paid months excluding foreman-commission month.
        $outgoingSettlement = $this->payoutService->contributionSettlementAmount($outgoing);

        $remainingMonths = $unpaidInstallments->pluck('month_number')->values()->all();

        return [
            'completed_rounds' => $completedRounds,
            'remaining_installments' => $remainingCount,
            'paid_installments_total' => $paidTotal,
            'outstanding_at_transfer' => $outstanding,
            'transfer_fee' => $transferFee,
            'takeover_amount' => $takeoverAmount,
            'outgoing_settlement_amount' => $outgoingSettlement,
            'remaining_month_numbers' => $remainingMonths,
            'installment_summary' => $installments->map(fn (Installment $i) => [
                'month_number' => $i->month_number,
                'amount' => (float) $i->amount,
                'paid_amount' => (float) $i->paid_amount,
                'balance' => max(0, (float) $i->balance),
                'status' => $i->status,
            ])->values()->all(),
        ];
    }

    /**
     * Apply prepaid / collected credit FIFO onto a member's unpaid installments
     * (full months first, then a partial month if credit remains).
     *
     * @return array{applied: float, months_full: int, partial_month: ?int, partial_amount: float, leftover: float}
     */
    public function applyPrepaidCreditToInstallments(
        GroupMember $member,
        float $credit,
        string $remark = 'Transfer prepaid credit',
        ?string $paymentMode = null
    ): array {
        $credit = round(max(0, $credit), 2);
        $empty = [
            'applied' => 0.0,
            'months_full' => 0,
            'partial_month' => null,
            'partial_amount' => 0.0,
            'leftover' => $credit,
        ];

        if ($credit <= 0.009) {
            return $empty;
        }

        $installments = Installment::where('member_id', $member->id)
            ->whereNotIn('status', ['paid', 'waived'])
            ->orderBy('month_number')
            ->lockForUpdate()
            ->get();

        $applied = 0.0;
        $monthsFull = 0;
        $partialMonth = null;
        $partialAmount = 0.0;
        $remaining = $credit;
        $mode = $paymentMode ? strtolower($paymentMode) : null;

        foreach ($installments as $inst) {
            if ($remaining <= 0.009) {
                break;
            }

            $due = round((float) $inst->amount + (float) $inst->penalty_amount - (float) $inst->paid_amount, 2);
            if ($due <= 0.009) {
                continue;
            }

            $portion = min($remaining, $due);
            $newPaid = round((float) $inst->paid_amount + $portion, 2);
            $totalDue = round((float) $inst->amount + (float) $inst->penalty_amount, 2);
            $status = $newPaid >= ($totalDue - 0.01) ? 'paid' : 'partial';

            $inst->update([
                'paid_amount' => $newPaid,
                'status' => $status,
                'paid_date' => $status === 'paid' ? ($inst->paid_date ?? now()) : ($newPaid > 0.009 ? ($inst->paid_date ?? now()) : $inst->paid_date),
                'payment_mode' => $mode ?: ($inst->payment_mode ?: 'transfer_credit'),
                'remarks' => trim(($inst->remarks ? $inst->remarks . ' | ' : '') . $remark),
            ]);

            $applied = round($applied + $portion, 2);
            $remaining = round($remaining - $portion, 2);

            if ($status === 'paid') {
                $monthsFull++;
            } else {
                $partialMonth = (int) $inst->month_number;
                $partialAmount = $portion;
            }
        }

        return [
            'applied' => $applied,
            'months_full' => $monthsFull,
            'partial_month' => $partialMonth,
            'partial_amount' => $partialAmount,
            'leftover' => $remaining,
        ];
    }

    /**
     * Undo erroneous same-group "catch-up credit" that marked incoming EMIs paid.
     * Incoming same-group seats must pay the full schedule from scratch.
     *
     * @return int Number of installment rows reset
     */
    public function clearSameGroupIncomingCatchUpCredits(?int $incomingMemberId = null): int
    {
        $incomingIds = ChitMemberTransfer::query()
            ->where('transfer_type', 'same_group')
            ->where('status', 'completed')
            ->when($incomingMemberId, fn ($q) => $q->where('incoming_member_id', $incomingMemberId))
            ->pluck('incoming_member_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($incomingIds === []) {
            return 0;
        }

        $installments = Installment::query()
            ->whereIn('member_id', $incomingIds)
            ->where(function ($q) {
                $q->where('payment_mode', 'transfer_credit')
                    ->orWhere('remarks', 'like', '%Same-group transfer catch-up credit%')
                    ->orWhere('remarks', 'like', '%catch-up credit%')
                    ->orWhere('remarks', 'like', '%Takeover payment via transfer%')
                    ->orWhere('remarks', 'like', '%transfer catch-up%');
            })
            ->where(function ($q) {
                $q->whereIn('status', ['paid', 'partial'])
                    ->orWhere('paid_amount', '>', 0.009);
            })
            ->get();

        $reset = 0;

        foreach ($installments as $inst) {
            // Keep months that have a real collection entry (not transfer auto-credit).
            $hasRealCollection = false;
            if (class_exists(\App\Models\ChitCollection::class)) {
                $hasRealCollection = \App\Models\ChitCollection::where('installment_id', $inst->id)->exists();
            }
            if ($hasRealCollection) {
                continue;
            }

            $dueDate = $inst->due_date;
            $isOverdue = $dueDate && $dueDate->lt(today());

            $inst->update([
                'paid_amount' => 0,
                'payment_mode' => null,
                'reference_no' => null,
                'remarks' => null,
                'status' => $isOverdue ? 'overdue' : 'pending',
                'paid_date' => null,
                'collected_by' => null,
            ]);
            $reset++;
        }

        // Refresh remaining_installments on affected transfer receipts.
        if ($reset > 0) {
            foreach ($incomingIds as $memberId) {
                $count = Installment::where('member_id', $memberId)
                    ->whereNotIn('status', ['paid', 'waived'])
                    ->count();
                ChitMemberTransfer::query()
                    ->where('transfer_type', 'same_group')
                    ->where('incoming_member_id', $memberId)
                    ->update(['remaining_installments' => $count]);
            }
        }

        return $reset;
    }

    /**
     * Simulate how source paid credit would land on a destination group's schedule.
     *
     * @return array<string, mixed>
     */
    public function previewCreditAllocation(float $credit, ChitGroup $group): array
    {
        $credit = round(max(0, $credit), 2);
        $totalPeriods = (int) ($group->total_months ?: $group->scheme?->duration_months ?: 0);
        $remaining = $credit;
        $monthsFull = 0;
        $partialMonth = null;
        $partialAmount = 0.0;
        $lines = [];

        for ($m = 1; $m <= $totalPeriods && $remaining > 0.009; $m++) {
            $due = round((float) $group->getInstallmentAmountForMonth($m), 2);
            if ($due <= 0.009) {
                continue;
            }

            $portion = min($remaining, $due);
            if ($portion >= ($due - 0.01)) {
                $monthsFull++;
                $lines[] = ['month' => $m, 'amount' => $due, 'type' => 'full'];
            } else {
                $partialMonth = $m;
                $partialAmount = $portion;
                $lines[] = ['month' => $m, 'amount' => $portion, 'type' => 'partial'];
            }
            $remaining = round($remaining - $portion, 2);
        }

        return [
            'credit' => $credit,
            'months_fully_covered' => $monthsFull,
            'partial_month' => $partialMonth,
            'partial_amount' => $partialAmount,
            'leftover_credit' => $remaining,
            'lines' => $lines,
        ];
    }

    /**
     * Money-only takeover: debit incoming wallet when needed. Does not mark EMIs paid
     * (EMI catch-up is applied separately via applyPrepaidCreditToInstallments).
     */
    protected function recordTakeoverPayment(
        ChitMemberTransfer $transfer,
        GroupMember $incoming,
        float $takeoverAmount,
        string $paymentMode,
        ?string $referenceNo,
        ?int $bankAccountId = null
    ): void {
        if ($paymentMode === 'wallet') {
            $this->walletService->debit(
                (int) $incoming->client_id,
                $takeoverAmount,
                'Chit member transfer takeover — ' . $transfer->group->group_code . ' #' . $transfer->ticket_number,
                'chit_member_transfer',
                $transfer->id,
                [
                    'transfer_code' => $transfer->transfer_code,
                    'outgoing_member_id' => $transfer->outgoing_member_id,
                    'reference_no' => $referenceNo,
                ]
            );

            return;
        }

        app(\App\Services\Account\ChitAccountingService::class)->recordTransferTakeoverCollection(
            $takeoverAmount,
            $paymentMode,
            'Chit member transfer takeover — ' . ($transfer->group->group_code ?? '') . ' #' . $transfer->ticket_number,
            $referenceNo ?: ($transfer->transfer_code ?? ('XFER-' . $transfer->id)),
            $bankAccountId
        );
    }

    public function incomingInstallmentsFullySettled(GroupMember $incoming): bool
    {
        return ! Installment::query()
            ->where('member_id', $incoming->id)
            ->whereNotIn('status', ['paid', 'waived'])
            ->exists();
    }

    /**
     * Outgoing settlement is via Settlement Applications only (paid − foreman).
     * Transfer receipt no longer releases wallet/cash buyouts.
     */
    public function tryReleaseOutgoingSettlementForMember(GroupMember $incoming): ?ChitMemberTransfer
    {
            return null;
    }

    public function releaseOutgoingSettlement(ChitMemberTransfer $transfer): ChitMemberTransfer
    {
            throw ValidationException::withMessages([
            'settlement' => 'Outgoing settlement is not released from the transfer receipt. Apply via Settlement Applications (paid months − foreman commission) for the outgoing member.',
        ]);
    }

    public function assertTransferEligible(GroupMember $outgoing): void
    {
        $outgoing->loadMissing(['group', 'installments', 'payouts']);

        // Stale "completed" seats in an still-active group can be transferred
        // (and are reactivated so the rest of the flow treats them as open).
        if (
            $outgoing->status === 'completed'
            && $outgoing->group
            && $outgoing->group->status === 'active'
            && ! $outgoing->has_won_auction
            && $outgoing->payouts->where('status', 'paid')->isEmpty()
        ) {
            $outgoing->update(['status' => 'active']);
            $outgoing->refresh();
        }

        $reason = $outgoing->transferBlockReason($outgoing->group);
        if ($reason !== null) {
            throw ValidationException::withMessages([
                'member' => $reason,
            ]);
        }

        if ($outgoing->installments()->doesntExist()) {
        $group = $outgoing->group;
            if ($group && $group->status === 'active' && $group->start_date) {
                $group->generateInstallmentsForMember(
                    $outgoing,
                    \Carbon\Carbon::parse($group->start_date)
                );
                $outgoing->load('installments');
            }
        }

        if ($outgoing->installments()->doesntExist()) {
            throw ValidationException::withMessages([
                'member' => 'No installment schedule found for this membership.',
            ]);
        }
    }

    /**
     * Drop open settlement applications so transfer can proceed cleanly.
     */
    protected function cancelOpenSettlementApplications(GroupMember $outgoing): void
    {
        $open = Payout::where('group_id', $outgoing->group_id)
            ->where('winner_member_id', $outgoing->id)
            ->whereIn('status', ['pending', 'processing', 'failed'])
            ->get();

        foreach ($open as $payout) {
            $this->payoutService->cancelSettlement(
                $payout,
                Auth::id(),
                'Cancelled automatically because the member was transferred.'
            );
        }
    }

    protected function assertIncomingClientEligible($group, Client $incomingClient, GroupMember $outgoing): void
    {
        $activeDuplicate = GroupMember::where('group_id', $group->id)
            ->where('client_id', $incomingClient->id)
            ->where('id', '!=', $outgoing->id)
            ->whereIn('status', ['active', 'approved', 'applied'])
            ->exists();

        if ($activeDuplicate) {
            throw ValidationException::withMessages([
                'incoming_client_id' => 'This customer already has an active enrollment in this group.',
            ]);
        }
    }

    /**
     * Cross-group: client may already be active in the destination.
     * With confirm_second_seat, a second ticket is added (display name becomes Name A / Name B).
     * Pending applied/approved rows in the destination are withdrawn.
     */
    protected function assertClientCanJoinDestination(
        $destinationGroup,
        Client $client,
        GroupMember $outgoing,
        bool $confirmSecondSeat = false
    ): void {
        $conflicts = GroupMember::where('group_id', $destinationGroup->id)
            ->where('client_id', $client->id)
            ->where('id', '!=', $outgoing->id)
            ->whereIn('status', ['active', 'approved', 'applied'])
            ->get();

        $alreadyActive = $conflicts->firstWhere('status', 'active');
        if ($alreadyActive && ! $confirmSecondSeat) {
            $label = trim((string) ($client->client_name ?? 'Client')) . ' B';
            throw ValidationException::withMessages([
                'destination_group_id' => 'This customer is already an active member of the selected destination group. Confirm to add a second seat as "' . $label . '".',
                'confirm_second_seat' => 'Confirmation required to add a second seat.',
            ]);
        }

        foreach ($conflicts->whereIn('status', ['approved', 'applied']) as $pending) {
            $pending->update([
                'status' => 'withdrawn',
                'remarks' => trim(($pending->remarks ? $pending->remarks . ' | ' : '')
                    . 'Withdrawn automatically — replaced by cross-group transfer from member #' . $outgoing->id),
            ]);
        }
    }

    /**
     * Soft-delete the transferred source seat and its schedule so the client no longer
     * appears in the old group (e.g. GRP00028). Paid history is retained via soft deletes
     * for contribution settlement (paid − foreman).
     */
    protected function purgeTransferredSourceSeat(\App\Models\GroupMember $outgoing): void
    {
        Installment::where('member_id', $outgoing->id)
            ->whereNotIn('status', ['paid'])
            ->where('paid_amount', '<=', 0)
            ->delete();

        DividendDistribution::where('member_id', $outgoing->id)
            ->where('status', 'pending')
            ->delete();

        AuctionBid::where('member_id', $outgoing->id)
            ->where('is_winner', false)
            ->delete();

        if (method_exists($outgoing, 'shares')) {
            $outgoing->shares()->delete();
        }

        $outgoing->delete();
    }

    /** @return array{0: ?int, 1: ?int} */
    protected function parseReferrer(?string $referredBy): array
    {
        if (! $referredBy || ! str_contains($referredBy, ':')) {
            return [null, null];
        }

        [$type, $id] = explode(':', $referredBy, 2);

        return match ($type) {
            'agent' => [(int) $id, null],
            'client' => [null, (int) $id],
            default => [null, null],
        };
    }
}
