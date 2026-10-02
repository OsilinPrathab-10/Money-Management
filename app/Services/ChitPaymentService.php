<?php

namespace App\Services;

use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\InstallmentSharePayment;
use App\Services\FixedDeposit\WalletService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChitPaymentService
{
    public static bool $suppressPaymentNotifications = false;

    public function __construct(
        protected PartialPaymentConfigService $partialPaymentConfig,
        protected WalletService $walletService,
    ) {}

    /**
     * Collect payment on a chit installment.
     *
     * Amounts are always tracked per co-owner: a shared seat's outstanding is
     * "this owner's slice of the total due, minus what this owner already paid",
     * never a slice of the shrinking overall balance.
     *
     * @param  array{paid_amount: float, payment_mode: string, payment_type: string, paid_date: string, reference_no?: string|null, remarks?: string|null, client_id?: int|null, single_seat_only?: mixed}  $data
     */
    public function collectInstallment(Installment $installment, array $data, ?int $collectedBy = null): array
    {
        $member = $installment->member;
        if (!$member) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Unable to resolve the chit membership for this installment.',
            ]);
        }

        $clientId = $this->resolvePayingClient($member, $data);

        $paid = round((float) ($data['paid_amount'] ?? 0), 2);
        if ($paid <= 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Collection amount must be greater than zero.',
            ]);
        }

        $singleSeatOnly = isset($data['single_seat_only'])
            && !in_array((string) $data['single_seat_only'], ['', '0', 'false'], true);

        $isSettled = in_array($installment->status, ['paid', 'waived'], true);
        if ($isSettled) {
            // Cumulative/consolidated pay for a client with 2 seats in the same group may
            // still route through a settled seat's id. Allow only when another open seat exists.
            if ($singleSeatOnly || !$this->clientHasOpenInstallmentsInGroup($installment, $clientId, $singleSeatOnly)) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'This installment is already settled.',
                ]);
            }
        } elseif (!$installment->isCollectible()) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Please pay the previous installment(s) before collecting this installment.',
            ]);
        }

        $paymentType = $data['payment_type'] ?? 'full';
        $isWalletPayment = ($data['payment_mode'] ?? '') === 'wallet';
        $paymentMode = $isWalletPayment
            ? 'wallet'
            : (($data['payment_mode'] ?? 'cash') === 'in_hand' ? 'cash' : ($data['payment_mode'] ?? 'cash'));

        if ($isWalletPayment) {
            $available = $this->walletService->balanceForClient($clientId);
            if ($available + 0.01 < $paid) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Insufficient wallet balance. Available: ₹' . number_format($available, 2),
                ]);
            }
        }

        $outcome = DB::transaction(function () use (
            $installment,
            $member,
            $clientId,
            $paid,
            $paymentType,
            $paymentMode,
            $singleSeatOnly,
            $data,
            $collectedBy
        ) {
            $membershipIds = GroupMember::query()
                ->where('group_id', $installment->group_id)
                ->when($singleSeatOnly, fn ($q) => $q->where('id', $installment->member_id))
                ->involvingClient($clientId)
                ->pluck('id');

            // Lock every open row first so two co-owners paying at the same moment
            // cannot both read the same outstanding balance.
            $openIds = Installment::whereIn('member_id', $membershipIds)
                ->whereNotIn('status', ['paid', 'waived'])
                ->orderBy('month_number')
                ->orderBy('member_id')
                ->lockForUpdate()
                ->pluck('id');

            $collectibleInstallments = Installment::with(['member.shares', 'sharePayments'])
                ->whereIn('id', $openIds)
                ->orderBy('month_number')
                ->orderBy('member_id')
                ->get();

            $anchorInstallment = $collectibleInstallments->first();
            if (!$anchorInstallment) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'No outstanding balance on this membership.',
                ]);
            }

            $totalOutstandingClientShare = round(
                $collectibleInstallments->sum(fn (Installment $item) => $item->clientBalanceShare($clientId)),
                2
            );

            if ($totalOutstandingClientShare <= 0) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'No outstanding balance for this customer on this membership.',
                ]);
            }

            if ($paid > ($totalOutstandingClientShare + 0.01)) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Collection amount cannot exceed the outstanding customer share balance of ₹' . number_format($totalOutstandingClientShare, 2) . '.',
                ]);
            }

            if ($paymentType !== 'full' && empty($data['bypass_min_validation'])) {
                if ($error = $this->partialPaymentConfig->validateChitPartialAmount($anchorInstallment, $paid, $clientId)) {
                    throw ValidationException::withMessages(['paid_amount' => $error]);
                }
            }

            $remaining = $paid;
            $affectedInstallments = 0;
            $finalStatus = 'partial';
            $paidMonths = [];

            foreach ($collectibleInstallments->groupBy('month_number')->sortKeys() as $monthItems) {
                if ($remaining <= 0.009) {
                    break;
                }

                $shares = [];
                $monthTotal = 0.0;
                foreach ($monthItems as $item) {
                    $share = $item->clientBalanceShare($clientId);
                    if ($share > 0.009) {
                        $shares[] = ['item' => $item, 'share' => $share];
                        $monthTotal = round($monthTotal + $share, 2);
                    }
                }

                if ($monthTotal <= 0.009) {
                    continue;
                }

                $paymentForMonth = round(min($remaining, $monthTotal), 2);
                $planned = 0.0;
                $lastIndex = count($shares) - 1;
                $monthTouched = false;

                foreach ($shares as $index => $info) {
                    /** @var Installment $item */
                    $item = $info['item'];

                    $portion = $index === $lastIndex
                        ? round($paymentForMonth - $planned, 2)
                        : round($paymentForMonth * ($info['share'] / $monthTotal), 2);
                    $planned = round($planned + $portion, 2);

                    // Never post more than this owner owes on this row; anything trimmed
                    // here stays in $remaining and rolls into the next month.
                    $portion = round(min($portion, $info['share']), 2);
                    if ($portion <= 0.009) {
                        continue;
                    }

                    $newPaid = round((float) $item->paid_amount + $portion, 2);
                    $due = round((float) $item->amount + (float) $item->penalty_amount, 2);
                    $status = $newPaid >= ($due - 0.01) ? 'paid' : 'partial';

                    $update = [
                        'paid_amount'  => $newPaid,
                        'payment_mode' => $paymentMode,
                        'status'       => $status,
                        'paid_date'    => $data['paid_date'],
                        'collected_by' => $collectedBy ?? Auth::id(),
                    ];

                    // Keep a co-owner's reference/remarks intact when the other owner pays.
                    if (!empty($data['reference_no'])) {
                        $update['reference_no'] = $data['reference_no'];
                    }
                    if (!empty($data['remarks'])) {
                        $update['remarks'] = $data['remarks'];
                    }

                    $item->update($update);

                    InstallmentSharePayment::create([
                        'installment_id' => $item->id,
                        'group_member_id' => $item->member_id,
                        'client_id' => $clientId,
                        'amount' => $portion,
                        'ownership_percentage' => $item->member
                            ? $item->member->ownershipPercentageFor($clientId)
                            : 100,
                        'payment_mode' => $paymentMode,
                        'reference_no' => $data['reference_no'] ?? null,
                        'remarks' => $data['remarks'] ?? null,
                        'paid_date' => $data['paid_date'],
                        'collected_by' => $collectedBy ?? Auth::id(),
                    ]);

                    $remaining = round($remaining - $portion, 2);
                    $affectedInstallments++;
                    $finalStatus = $status;
                    $monthTouched = true;
                }

                if ($monthTouched) {
                    $paidMonths[] = (int) $monthItems->first()->month_number;
                }
            }

            $applied = round($paid - $remaining, 2);

            if ($applied <= 0) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'No outstanding balance for this customer on this membership.',
                ]);
            }

            if ($paymentMode === 'wallet') {
                // Debit what was actually posted, so a trimmed remainder is never charged.
                $this->walletService->debit(
                    $clientId,
                    $applied,
                    'Chit Installment Payment',
                    'chit_installment',
                    $anchorInstallment->id,
                    ['group_id' => $anchorInstallment->group_id]
                );
            } elseif (empty($data['skip_cashbook'])) {
                $anchorInstallment->loadMissing(['group', 'member.client']);
                app(\App\Services\Account\ChitAccountingService::class)->recordInstallmentCollection(
                    $anchorInstallment,
                    $applied,
                    array_merge($data, [
                        'payment_mode' => $paymentMode,
                        'collected_by' => $collectedBy ?? Auth::id(),
                        'months' => $paidMonths ?: [(int) $anchorInstallment->month_number],
                    ])
                );
            }

            return [
                'applied' => $applied,
                'affected' => $affectedInstallments,
                'status' => $finalStatus,
                'months' => $paidMonths,
                'members' => $collectibleInstallments->pluck('member')->filter()->unique('id'),
                'group_code' => $anchorInstallment->group?->group_code
                    ?? ($anchorInstallment->loadMissing('group')->group?->group_code)
                    ?? ('GRP-' . $anchorInstallment->group_id),
                'client_name' => $anchorInstallment->member?->client?->client_name
                    ?? ($member->client?->client_name ?? 'Member'),
            ];
        });

        foreach ($outcome['members'] as $affectedMember) {
            \App\Models\ChitReferralBonus::generateForMember($affectedMember);
            app(ChitMemberTransferService::class)->tryReleaseOutgoingSettlementForMember($affectedMember);
        }

        $payingClient = \App\Models\Client::find($clientId);
        if (! self::$suppressPaymentNotifications) {
            event(new \App\Events\ChitInstallmentCollectedEvent(
                $installment->fresh(['member.client', 'group']),
                (float) $outcome['applied'],
                $payingClient,
                \App\Services\AppNotificationService::detectSource()
            ));
        }

        return [
            'success' => true,
            'status'  => $outcome['status'],
            'applied' => $outcome['applied'],
            'message' => $outcome['affected'] > 1
                ? 'Payment collected and split across ' . $outcome['affected'] . ' installments successfully!'
                : ($outcome['status'] === 'paid'
                    ? 'Installment collected successfully!'
                    : 'Partial payment recorded successfully!'),
        ];
    }

    /**
     * Whether this customer still has open (unpaid) installment rows in the group.
     * Used when a consolidated pay action is keyed off an already-settled seat id.
     */
    protected function clientHasOpenInstallmentsInGroup(
        Installment $installment,
        int $clientId,
        bool $singleSeatOnly
    ): bool {
        $membershipIds = GroupMember::query()
            ->where('group_id', $installment->group_id)
            ->when($singleSeatOnly, fn ($q) => $q->where('id', $installment->member_id))
            ->involvingClient($clientId)
            ->pluck('id');

        if ($membershipIds->isEmpty()) {
            return false;
        }

        return Installment::query()
            ->whereIn('member_id', $membershipIds)
            ->where('group_id', $installment->group_id)
            ->whereNotIn('status', ['paid', 'waived'])
            ->exists();
    }

    /**
     * Resolve who is paying, and refuse to post against a seat they do not own.
     */
    protected function resolvePayingClient(GroupMember $member, array $data): int
    {
        $requested = isset($data['client_id']) ? (int) $data['client_id'] : 0;

        if ($requested > 0) {
            if (!$member->involvesClient($requested)) {
                throw ValidationException::withMessages([
                    'client_id' => 'The selected customer is not an owner of this chit membership.',
                ]);
            }

            return $requested;
        }

        $clientId = (int) ($member->client_id ?? 0);

        if (!$clientId) {
            $clientId = (int) ($member->resolveOwnershipShares()->first()?->client_id ?? 0);
        }

        if (!$clientId) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Unable to resolve customer for payment.',
            ]);
        }

        return $clientId;
    }
}
