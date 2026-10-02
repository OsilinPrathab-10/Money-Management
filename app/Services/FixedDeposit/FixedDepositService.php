<?php

namespace App\Services\FixedDeposit;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositApplication;
use App\Models\FixedDepositChitAllocation;
use App\Models\FixedDepositRenewal;
use App\Models\FixedDepositScheme;
use App\Models\FixedDepositTransaction;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Services\ChitPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FixedDepositService
{
    public function __construct(
        protected FixedDepositInterestService $interestService,
        protected FixedDepositMonthlyInterestService $monthlyInterestService,
        protected WalletService $walletService,
        protected FixedDepositAccountingService $accountingService,
        protected FixedDepositAuditService $auditService,
        protected ChitPaymentService $chitPaymentService,
        protected \App\Services\Account\FdAccountingService $fdCashbookService,
    ) {}

    public function create(array $data): FixedDeposit
    {
        return DB::transaction(function () use ($data) {
            /** @var FixedDepositScheme $scheme */
            $scheme = FixedDepositScheme::active()->findOrFail($data['scheme_id']);

            $amount = round((float) $data['deposit_amount'], 2);
            $isRenewal = !empty($data['renewed_from_id']);
            if (!$isRenewal && ($amount < (float) $scheme->min_deposit_amount || $amount > (float) $scheme->max_deposit_amount)) {
                throw ValidationException::withMessages([
                    'deposit_amount' => sprintf(
                        'Deposit amount must be between ₹%s and ₹%s.',
                        number_format((float) $scheme->min_deposit_amount, 2),
                        number_format((float) $scheme->max_deposit_amount, 2)
                    ),
                ]);
            }

            $tenure = (int) $data['tenure'];
            if ($tenure < $scheme->min_tenure || $tenure > $scheme->max_tenure) {
                throw ValidationException::withMessages([
                    'tenure' => "Tenure must be between {$scheme->min_tenure} and {$scheme->max_tenure} {$scheme->tenure_type}.",
                ]);
            }

            $startDate = Carbon::parse($data['start_date'] ?? $data['deposit_date']);
            $calc = $this->interestService->calculate(
                $amount,
                (float) $scheme->interest_rate,
                $scheme->deposit_type,
                $scheme->interest_frequency,
                $tenure,
                $scheme->tenure_type,
                $startDate
            );

            $fd = FixedDeposit::create([
                'fd_number' => FixedDeposit::generateFdNumber(),
                'client_id' => $data['client_id'],
                'scheme_id' => $scheme->id,
                'deposit_amount' => $amount,
                'deposit_date' => $data['deposit_date'],
                'start_date' => $startDate->toDateString(),
                'maturity_date' => $calc['maturity_date']->toDateString(),
                'tenure' => $tenure,
                'tenure_type' => $scheme->tenure_type,
                'interest_rate' => $scheme->interest_rate,
                'interest_type' => $scheme->deposit_type,
                'interest_frequency' => $scheme->interest_frequency,
                'interest_amount' => $calc['interest_amount'],
                'maturity_amount' => $calc['maturity_amount'],
                'interest_paid_to_wallet' => 0,
                'monthly_interest_to_wallet' => array_key_exists('monthly_interest_to_wallet', $data)
                    ? (bool) $data['monthly_interest_to_wallet']
                    : true,
                'nominee_name' => $data['nominee_name'] ?? null,
                'nominee_relation' => $data['nominee_relation'] ?? null,
                'payout_option' => $data['payout_option'] ?? $scheme->default_payout_option,
                'auto_renewal' => array_key_exists('auto_renewal', $data)
                    ? (bool) $data['auto_renewal']
                    : (bool) $scheme->auto_renewal,
                'renewal_type' => $data['renewal_type'] ?? $scheme->renewal_type,
                'status' => 'active',
                'remarks' => $data['remarks'] ?? null,
                'renewed_from_id' => $data['renewed_from_id'] ?? null,
                'internal_bank_account_id' => $data['internal_bank_account_id'] ?? null,
                'created_by' => Auth::id(),
            ]);

            FixedDepositTransaction::create([
                'fixed_deposit_id' => $fd->id,
                'transaction_type' => 'creation',
                'amount' => $amount,
                'principal_amount' => $amount,
                'interest_amount' => $calc['interest_amount'],
                'payment_mode' => $data['payment_mode'] ?? 'cash',
                'description' => 'Fixed Deposit Created',
                'created_by' => Auth::id(),
            ]);

            $this->accountingService->postCreation($fd);
            $this->fdCashbookService->recordDeposit($fd, $data);
            $this->auditService->log(
                'Fixed Deposit Created',
                $fd->id,
                $scheme->id,
                null,
                $fd->toArray(),
                $data['remarks'] ?? null
            );

            return $fd->fresh(['client', 'scheme']);
        });
    }

    public function update(FixedDeposit $fd, array $data): FixedDeposit
    {
        return DB::transaction(function () use ($fd, $data) {
            $fd = FixedDeposit::where('id', $fd->id)->lockForUpdate()->first();

            if (!$fd->canEdit()) {
                throw new RuntimeException('Closed, cancelled, or processed Fixed Deposits cannot be edited.');
            }

            $previous = $fd->toArray();
            $meta = [
                'nominee_name' => $data['nominee_name'] ?? null,
                'nominee_relation' => $data['nominee_relation'] ?? null,
                'payout_option' => $data['payout_option'] ?? $fd->payout_option,
                'auto_renewal' => (bool) ($data['auto_renewal'] ?? false),
                'renewal_type' => ($data['auto_renewal'] ?? false) ? ($data['renewal_type'] ?? null) : null,
                'remarks' => $data['remarks'] ?? null,
            ];

            if (!$fd->canChangeFinancials()) {
                $fd->update($meta);
                $this->auditService->log('Fixed Deposit Updated', $fd->id, $fd->scheme_id, $previous, $fd->fresh()->toArray());

                return $fd->fresh(['client', 'scheme']);
            }

            $scheme = FixedDepositScheme::findOrFail($data['scheme_id']);
            $amount = round((float) $data['deposit_amount'], 2);
            if ($amount < (float) $scheme->min_deposit_amount || $amount > (float) $scheme->max_deposit_amount) {
                throw ValidationException::withMessages([
                    'deposit_amount' => sprintf(
                        'Deposit amount must be between ₹%s and ₹%s.',
                        number_format((float) $scheme->min_deposit_amount, 2),
                        number_format((float) $scheme->max_deposit_amount, 2)
                    ),
                ]);
            }

            $tenure = (int) $data['tenure'];
            if ($tenure < $scheme->min_tenure || $tenure > $scheme->max_tenure) {
                throw ValidationException::withMessages([
                    'tenure' => "Tenure must be between {$scheme->min_tenure} and {$scheme->max_tenure} {$scheme->tenure_type}.",
                ]);
            }

            $startDate = Carbon::parse($data['start_date'] ?? $data['deposit_date']);
            $calc = $this->interestService->calculate(
                $amount,
                (float) $scheme->interest_rate,
                $scheme->deposit_type,
                $scheme->interest_frequency,
                $tenure,
                $scheme->tenure_type,
                $startDate
            );

            $creationTxn = $fd->transactions()->where('transaction_type', 'creation')->latest('id')->first();
            $oldPayMode = strtolower((string) ($creationTxn->payment_mode ?? 'cash'));
            $newPayMode = strtolower((string) ($data['payment_mode'] ?? $oldPayMode));
            $oldBankId = (int) ($fd->internal_bank_account_id ?? 0);
            $newBankId = (int) ($data['internal_bank_account_id'] ?? $oldBankId);

            $financialChanged =
                (int) $fd->scheme_id !== (int) $scheme->id
                || abs((float) $fd->deposit_amount - $amount) > 0.009
                || optional($fd->deposit_date)?->toDateString() !== Carbon::parse($data['deposit_date'])->toDateString()
                || optional($fd->start_date)?->toDateString() !== $startDate->toDateString()
                || (int) $fd->tenure !== $tenure
                || $oldPayMode !== $newPayMode
                || $oldBankId !== $newBankId;

            if ($financialChanged) {
                $this->fdCashbookService->reverseDeposit($fd);
                $this->accountingService->reverseCreation($fd);
            }

            $fd->update(array_merge($meta, [
                'scheme_id' => $scheme->id,
                'deposit_amount' => $amount,
                'deposit_date' => $data['deposit_date'],
                'start_date' => $startDate->toDateString(),
                'maturity_date' => $calc['maturity_date']->toDateString(),
                'tenure' => $tenure,
                'tenure_type' => $scheme->tenure_type,
                'interest_rate' => $scheme->interest_rate,
                'interest_type' => $scheme->deposit_type,
                'interest_frequency' => $scheme->interest_frequency,
                'interest_amount' => $calc['interest_amount'],
                'maturity_amount' => $calc['maturity_amount'],
                'internal_bank_account_id' => $data['internal_bank_account_id'] ?? $fd->internal_bank_account_id,
            ]));

            $creationTxn?->update([
                'amount' => $amount,
                'principal_amount' => $amount,
                'interest_amount' => $calc['interest_amount'],
                'payment_mode' => $data['payment_mode'] ?? $oldPayMode,
                'description' => 'Fixed Deposit Created',
            ]);

            $fresh = $fd->fresh(['client', 'scheme']);
            if ($financialChanged) {
                $this->accountingService->postCreation($fresh);
                $this->fdCashbookService->recordDeposit($fresh, $data);
            }
            $this->auditService->log('Fixed Deposit Updated', $fd->id, $scheme->id, $previous, $fresh->toArray());

            return $fresh;
        });
    }

    public function delete(FixedDeposit $fd): void
    {
        DB::transaction(function () use ($fd) {
            $fd = FixedDeposit::where('id', $fd->id)->lockForUpdate()->first();

            if (!$fd->canDelete()) {
                throw new RuntimeException('Closed, cancelled, or processed Fixed Deposits cannot be deleted.');
            }

            $snapshot = $fd->toArray();
            $this->fdCashbookService->reverseDeposit($fd);
            $this->accountingService->reverseCreation($fd);
            $this->auditService->log('Fixed Deposit Deleted', $fd->id, $fd->scheme_id, $snapshot, null);
            $fd->delete();
        });
    }

    public function processMaturity(FixedDeposit $fd, array $options = []): FixedDeposit
    {
        return DB::transaction(function () use ($fd, $options) {
            $fd = FixedDeposit::where('id', $fd->id)->lockForUpdate()->first();

            if ($fd->maturity_processed_at !== null) {
                throw new RuntimeException('Maturity has already been processed for this Fixed Deposit.');
            }

            if (!in_array($fd->status, ['active', 'matured'], true)) {
                throw new RuntimeException('Only active or matured Fixed Deposits can be processed.');
            }

            if ($fd->maturity_date->gt(Carbon::today()) && empty($options['force'])) {
                throw new RuntimeException('Fixed Deposit has not reached maturity date yet.');
            }

            // Auto renewal takes precedence
            if ($fd->auto_renewal) {
                return $this->renew($fd, $fd->renewal_type ?? 'principal_interest');
            }

            $principal = (float) $fd->deposit_amount;
            $interestRemaining = $this->monthlyInterestService->remainingInterest($fd);
            $interest = $interestRemaining > 0 ? $interestRemaining : (float) $fd->interest_amount;
            $total = round($principal + $interest, 2);
            $payout = $options['payout_option'] ?? $fd->payout_option;

            $processingFee = round((float) ($options['processing_fee'] ?? 0), 2);
            $documentCharges = round((float) ($options['document_charges'] ?? 0), 2);
            $otherCharges = round((float) ($options['other_charges'] ?? 0), 2);
            $bankingCharges = round((float) ($options['banking_charges'] ?? 0), 2);
            $netAmount = round($total - $processingFee - $documentCharges - $otherCharges, 2);
            if ($netAmount < 0) {
                $netAmount = 0;
            }

            $bankAccountId = $options['internal_bank_account_id'] ?? null;
            $payoutMode = $payout === 'bank_transfer' ? 'bank_transfer' : ($payout === 'cash' ? 'cash' : $payout);

            if (in_array($payout, ['bank_transfer', 'cash', 'upi'], true)) {
                $bankAccountId = $this->fdCashbookService->recordPayout($fd, $netAmount, array_merge($options, [
                    'payment_mode' => $payoutMode,
                    'payout_option' => $payout,
                    'txn_label' => 'Fixed Deposit Maturity Payout',
                    'processing_fee' => $processingFee,
                    'document_charges' => $documentCharges,
                    'other_charges' => $otherCharges,
                    'banking_charges' => $bankingCharges,
                ]));
            } elseif (in_array($payout, ['wallet', 'chit'], true)) {
                $this->fdCashbookService->recordPayout($fd, $netAmount, array_merge($options, [
                    'payment_mode' => $payout,
                    'payout_option' => $payout,
                    'processing_fee' => $processingFee,
                    'document_charges' => $documentCharges,
                    'other_charges' => $otherCharges,
                    'banking_charges' => $bankingCharges,
                ]));
            }

            $txn = FixedDepositTransaction::create([
                'fixed_deposit_id' => $fd->id,
                'transaction_type' => 'maturity',
                'amount' => $netAmount,
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'payment_mode' => $payout,
                'description' => 'Fixed Deposit Maturity Credit',
                'meta' => array_merge($options, [
                    'processing_fee' => $processingFee,
                    'document_charges' => $documentCharges,
                    'other_charges' => $otherCharges,
                    'banking_charges' => $bankingCharges,
                    'net_amount' => $netAmount
                ]),
                'created_by' => Auth::id(),
            ]);

            if ($payout === 'wallet') {
                $this->walletService->credit(
                    $fd->client_id,
                    $netAmount,
                    'Fixed Deposit Maturity Credit',
                    'fixed_deposit',
                    $fd->id,
                    ['fd_number' => $fd->fd_number, 'principal' => $principal, 'interest' => $interest]
                );
                $this->accountingService->postMaturityWalletCredit($fd, $principal, $interest);
                $this->auditService->log('Wallet Credit', $fd->id, $fd->scheme_id, null, [
                    'amount' => $netAmount,
                    'type' => 'maturity',
                ]);
            } elseif ($payout === 'bank_transfer' || $payout === 'cash') {
                $fd->fill([
                    'bank_name' => $options['bank_name'] ?? $fd->bank_name,
                    'account_number' => $options['account_number'] ?? $fd->account_number,
                    'ifsc_code' => $options['ifsc_code'] ?? $fd->ifsc_code,
                    'utr_reference' => $options['utr_reference'] ?? null,
                    'payment_date' => $options['payment_date'] ?? now()->toDateString(),
                ]);
                $this->accountingService->postBankPayout($fd, $netAmount, $interest);
            } elseif ($payout === 'chit') {
                $this->allocateToChit($fd, $txn, $netAmount, $options);
            }

            $proofPath = $this->handlePaymentProofUpload($fd, $options);
            if ($proofPath) {
                $fd->payment_proof = $proofPath;
            }

            $fd->customer_bank_name = $options['customer_bank_name'] ?? $options['bank_name'] ?? $fd->customer_bank_name;
            $fd->customer_account_number = $options['customer_account_number'] ?? $options['account_number'] ?? $fd->customer_account_number;
            $fd->customer_ifsc_code = $options['customer_ifsc_code'] ?? $options['ifsc_code'] ?? $fd->customer_ifsc_code;
            $fd->customer_branch_name = $options['customer_branch_name'] ?? $fd->customer_branch_name;
            $fd->customer_holder_name = $options['customer_holder_name'] ?? $fd->customer_holder_name;

            $fd->status = 'matured';
            $fd->maturity_processed_at = now();
            $fd->payout_option = $payout;
            $fd->closure_date = $options['payment_date'] ?? now()->toDateString();
            $fd->closure_amount = $netAmount;
            $fd->processing_fee = $processingFee;
            $fd->document_charges = $documentCharges;
            $fd->other_charges = $otherCharges;
            $fd->banking_charges = $bankingCharges;
            $fd->internal_bank_account_id = $bankAccountId;
            $fd->save();

            $this->auditService->log('Maturity Processed', $fd->id, $fd->scheme_id, null, [
                'payout_option' => $payout,
                'amount' => $netAmount,
                'processing_fee' => $processingFee,
                'document_charges' => $documentCharges,
                'other_charges' => $otherCharges,
                'banking_charges' => $bankingCharges,
            ]);

            return $fd->fresh(['client', 'scheme', 'transactions', 'chitAllocations']);
        });
    }

    public function allocateToChit(FixedDeposit $fd, FixedDepositTransaction $txn, float $total, array $options): array
    {
        $allocationAmount = round((float) ($options['allocation_amount'] ?? $total), 2);
        $allocationType = $options['allocation_type'] ?? 'pending_installment';
        $groupId = $options['chit_group_id'] ?? null;

        if ($allocationAmount <= 0) {
            throw ValidationException::withMessages([
                'allocation_amount' => 'Allocation amount must be greater than zero.',
            ]);
        }

        if ($allocationAmount > $total) {
            throw ValidationException::withMessages([
                'allocation_amount' => 'Allocation amount cannot exceed maturity amount.',
            ]);
        }

        $applied = 0.0;
        $meta = [];

        if (in_array($allocationType, ['pending_installment', 'advance'], true) && $groupId) {
            $member = GroupMember::where('group_id', $groupId)
                ->where('client_id', $fd->client_id)
                ->whereIn('status', ['active', 'approved'])
                ->first();

            if (!$member) {
                throw ValidationException::withMessages([
                    'chit_group_id' => 'Customer is not an active member of the selected chit group.',
                ]);
            }

            $installments = Installment::where('group_id', $groupId)
                ->where('member_id', $member->id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->orderBy('month_number')
                ->get();

            $remaining = $allocationAmount;
            foreach ($installments as $installment) {
                if ($remaining <= 0.01) {
                    break;
                }

                $balance = round((float) $installment->balance, 2);
                if ($balance <= 0.01) {
                    continue;
                }

                $pay = min($remaining, $balance);
                try {
                    $this->chitPaymentService->collectInstallment($installment, [
                        'paid_amount' => $pay,
                        'payment_mode' => 'wallet',
                        'payment_type' => $pay + 0.01 >= $balance ? 'full' : 'partial',
                        'paid_date' => now()->toDateString(),
                        'reference_no' => $fd->fd_number,
                        'remarks' => 'FD Maturity Chit Adjustment',
                        'bypass_min_validation' => true,
                    ], Auth::id());
                    $applied += $pay;
                    $remaining = round($remaining - $pay, 2);
                    $meta['installments'][] = ['id' => $installment->id, 'amount' => $pay];
                } catch (\Throwable $e) {
                    $meta['errors'][] = $e->getMessage();
                    break;
                }
            }
        } else {
            // settlement / join_new / chit_wallet — record allocation; excess handling below
            $applied = $allocationAmount;
            $meta['note'] = 'Allocation recorded for ' . $allocationType;
        }

        FixedDepositChitAllocation::create([
            'fixed_deposit_id' => $fd->id,
            'fd_transaction_id' => $txn->id,
            'chit_group_id' => $groupId,
            'allocation_type' => $allocationType,
            'amount' => $applied > 0 ? $applied : $allocationAmount,
            'meta' => $meta,
            'created_by' => Auth::id(),
        ]);

        FixedDepositTransaction::create([
            'fixed_deposit_id' => $fd->id,
            'transaction_type' => 'chit_adjustment',
            'amount' => $applied > 0 ? $applied : $allocationAmount,
            'description' => 'Chit Adjustment from FD',
            'meta' => $meta,
            'created_by' => Auth::id(),
        ]);

        $this->auditService->log('Chit Adjustment', $fd->id, $fd->scheme_id, null, [
            'amount' => $applied > 0 ? $applied : $allocationAmount,
            'allocation_type' => $allocationType,
            'chit_group_id' => $groupId,
        ]);

        $used = $applied > 0 ? $applied : $allocationAmount;
        $excess = round($total - $used, 2);
        $remainingPayable = 0.0;

        if ($excess > 0.01) {
            $this->walletService->credit(
                $fd->client_id,
                $excess,
                'Remaining Balance after Chit Allocation',
                'fixed_deposit',
                $fd->id
            );
        } elseif ($excess < -0.01) {
            $remainingPayable = abs($excess);
        }

        return [
            'allocated' => $used,
            'excess_to_wallet' => max(0, $excess),
            'remaining_payable' => $remainingPayable,
        ];
    }

    public function prematureWithdraw(FixedDeposit $fd, array $options = []): array
    {
        return DB::transaction(function () use ($fd, $options) {
            $fd = FixedDeposit::with('scheme')->where('id', $fd->id)->lockForUpdate()->first();

            if ($fd->status !== 'active') {
                throw new RuntimeException('Only active Fixed Deposits can be withdrawn prematurely.');
            }

            $scheme = $fd->scheme;
            if (!$scheme || !$scheme->premature_withdrawal_allowed) {
                throw new RuntimeException('Premature withdrawal is not allowed for this scheme.');
            }

            $withdrawalDate = Carbon::parse($options['withdrawal_date'] ?? now());
            $interestCalc = $this->interestService->calculatePrematureInterest(
                (float) $fd->deposit_amount,
                (float) $fd->interest_rate,
                $fd->interest_type,
                $fd->interest_frequency,
                $fd->start_date,
                $withdrawalDate
            );

            $eligibleInterest = $interestCalc['eligible_interest'];
            $penalty = $this->interestService->calculatePenalty(
                $scheme->premature_penalty_type,
                $scheme->premature_penalty_value !== null ? (float) $scheme->premature_penalty_value : null,
                (float) $fd->deposit_amount,
                $eligibleInterest
            );

            $payable = round((float) $fd->deposit_amount + $eligibleInterest - $penalty, 2);
            if ($payable < 0) {
                $payable = 0;
            }

            $payout = $options['payout_option'] ?? 'wallet';

            $processingFee = round((float) ($options['processing_fee'] ?? 0), 2);
            $documentCharges = round((float) ($options['document_charges'] ?? 0), 2);
            $otherCharges = round((float) ($options['other_charges'] ?? 0), 2);
            $bankingCharges = round((float) ($options['banking_charges'] ?? 0), 2);
            $netAmount = round($payable - $processingFee - $documentCharges - $otherCharges, 2);
            if ($netAmount < 0) {
                $netAmount = 0;
            }

            $bankAccountId = $options['internal_bank_account_id'] ?? null;
            if (in_array($payout, ['bank_transfer', 'cash', 'upi'], true)) {
                $bankAccountId = $this->fdCashbookService->recordPayout($fd, $netAmount, array_merge($options, [
                    'payment_mode' => $payout === 'bank_transfer' ? 'bank_transfer' : $payout,
                    'payout_option' => $payout,
                    'txn_label' => 'Fixed Deposit Premature Withdrawal',
                    'payment_date' => $withdrawalDate->toDateString(),
                    'withdrawal_date' => $withdrawalDate->toDateString(),
                    'processing_fee' => $processingFee,
                    'document_charges' => $documentCharges,
                    'other_charges' => $otherCharges,
                    'banking_charges' => $bankingCharges,
                ]));
            } elseif (in_array($payout, ['wallet', 'chit'], true)) {
                $this->fdCashbookService->recordPayout($fd, $netAmount, array_merge($options, [
                    'payment_mode' => $payout,
                    'payout_option' => $payout,
                    'payment_date' => $withdrawalDate->toDateString(),
                    'processing_fee' => $processingFee,
                    'document_charges' => $documentCharges,
                    'other_charges' => $otherCharges,
                    'banking_charges' => $bankingCharges,
                ]));
            }

            $txn = FixedDepositTransaction::create([
                'fixed_deposit_id' => $fd->id,
                'transaction_type' => 'premature',
                'amount' => $netAmount,
                'principal_amount' => $fd->deposit_amount,
                'interest_amount' => $eligibleInterest,
                'penalty_amount' => $penalty,
                'payment_mode' => $payout,
                'description' => 'Premature Withdrawal',
                'meta' => [
                    'days' => $interestCalc['days'],
                    'penalty_type' => $scheme->premature_penalty_type,
                    'penalty_value' => $scheme->premature_penalty_value,
                    'processing_fee' => $processingFee,
                    'document_charges' => $documentCharges,
                    'other_charges' => $otherCharges,
                    'net_amount' => $netAmount,
                ],
                'created_by' => Auth::id(),
            ]);

            if ($payout === 'wallet') {
                $this->walletService->credit(
                    $fd->client_id,
                    $netAmount,
                    'Premature Withdrawal Credit',
                    'fixed_deposit',
                    $fd->id
                );
            } elseif (in_array($payout, ['bank_transfer', 'cash'], true)) {
                $fd->fill([
                    'bank_name' => $options['bank_name'] ?? null,
                    'account_number' => $options['account_number'] ?? null,
                    'ifsc_code' => $options['ifsc_code'] ?? null,
                    'utr_reference' => $options['utr_reference'] ?? null,
                    'payment_date' => $withdrawalDate->toDateString(),
                ]);
            } elseif ($payout === 'chit') {
                $this->allocateToChit($fd, $txn, $netAmount, $options);
            }

            $this->accountingService->postPrematureWithdrawal($fd, $netAmount, $eligibleInterest, $penalty);

            $proofPath = $this->handlePaymentProofUpload($fd, $options);
            if ($proofPath) {
                $fd->payment_proof = $proofPath;
            }

            $fd->customer_bank_name = $options['customer_bank_name'] ?? $options['bank_name'] ?? $fd->customer_bank_name;
            $fd->customer_account_number = $options['customer_account_number'] ?? $options['account_number'] ?? $fd->customer_account_number;
            $fd->customer_ifsc_code = $options['customer_ifsc_code'] ?? $options['ifsc_code'] ?? $fd->customer_ifsc_code;
            $fd->customer_branch_name = $options['customer_branch_name'] ?? $fd->customer_branch_name;
            $fd->customer_holder_name = $options['customer_holder_name'] ?? $fd->customer_holder_name;

            $fd->status = 'premature_closed';
            $fd->closure_date = $withdrawalDate->toDateString();
            $fd->closure_amount = $netAmount;
            $fd->closure_payment_mode = $payout;
            $fd->closed_by = Auth::id();
            $fd->closure_remarks = $options['remarks'] ?? null;
            $fd->processing_fee = $processingFee;
            $fd->document_charges = $documentCharges;
            $fd->other_charges = $otherCharges;
            $fd->banking_charges = $bankingCharges;
            $fd->internal_bank_account_id = $bankAccountId;
            $fd->save();

            $this->auditService->log('Premature Withdrawal', $fd->id, $fd->scheme_id, null, [
                'payable' => $netAmount,
                'eligible_interest' => $eligibleInterest,
                'penalty' => $penalty,
                'processing_fee' => $processingFee,
                'document_charges' => $documentCharges,
                'other_charges' => $otherCharges,
            ], $options['remarks'] ?? null);

            return [
                'fd' => $fd->fresh(['client', 'scheme']),
                'eligible_interest' => $eligibleInterest,
                'penalty' => $penalty,
                'payable' => $netAmount,
                'transaction' => $txn,
            ];
        });
    }

    public function renew(FixedDeposit $fd, ?string $renewalType = null): FixedDeposit
    {
        return DB::transaction(function () use ($fd, $renewalType) {
            $fd = FixedDeposit::with('scheme')->where('id', $fd->id)->lockForUpdate()->first();

            if ($fd->maturity_processed_at !== null && $fd->status === 'renewed') {
                throw new RuntimeException('This Fixed Deposit has already been renewed.');
            }

            $type = $renewalType ?? $fd->renewal_type ?? 'principal_interest';
            $principal = (float) $fd->deposit_amount;
            $interest = (float) $fd->interest_amount;
            $carryAmount = $type === 'principal_only' ? $principal : round($principal + $interest, 2);
            $interestCarried = $type === 'principal_only' ? 0.0 : $interest;

            $newStart = $fd->maturity_date->copy()->addDay();

            $newFd = $this->create([
                'client_id' => $fd->client_id,
                'scheme_id' => $fd->scheme_id,
                'deposit_amount' => $carryAmount,
                'deposit_date' => $newStart->toDateString(),
                'start_date' => $newStart->toDateString(),
                'tenure' => $fd->tenure,
                'payout_option' => $fd->payout_option,
                'auto_renewal' => $fd->auto_renewal,
                'renewal_type' => $fd->renewal_type,
                'nominee_name' => $fd->nominee_name,
                'nominee_relation' => $fd->nominee_relation,
                'remarks' => 'Auto renewed from ' . $fd->fd_number,
                'renewed_from_id' => $fd->id,
            ]);

            FixedDepositRenewal::create([
                'old_fd_id' => $fd->id,
                'new_fd_id' => $newFd->id,
                'renewal_type' => $type,
                'principal_carried' => $principal,
                'interest_carried' => $interestCarried,
                'renewed_at' => now(),
                'renewed_by' => Auth::id(),
                'remarks' => 'Renewal of ' . $fd->fd_number,
            ]);

            FixedDepositTransaction::create([
                'fixed_deposit_id' => $fd->id,
                'transaction_type' => 'renewal',
                'amount' => $carryAmount,
                'principal_amount' => $principal,
                'interest_amount' => $interestCarried,
                'description' => 'Renewed to ' . $newFd->fd_number,
                'reference' => $newFd->fd_number,
                'created_by' => Auth::id(),
            ]);

            $fd->status = 'renewed';
            $fd->maturity_processed_at = now();
            $fd->save();

            $this->auditService->log('Renewal', $fd->id, $fd->scheme_id, null, [
                'new_fd_number' => $newFd->fd_number,
                'renewal_type' => $type,
                'carry_amount' => $carryAmount,
            ]);

            return $newFd;
        });
    }

    public function close(FixedDeposit $fd, array $data): FixedDeposit
    {
        return DB::transaction(function () use ($fd, $data) {
            $fd = FixedDeposit::where('id', $fd->id)->lockForUpdate()->first();

            if (!in_array($fd->status, ['matured', 'active'], true)) {
                throw new RuntimeException('Only matured or active Fixed Deposits can be closed manually.');
            }

            if ($fd->isReadOnly() && $fd->status === 'closed') {
                throw new RuntimeException('Fixed Deposit is already closed.');
            }

            $amount = round((float) ($data['closure_amount'] ?? $fd->maturity_amount), 2);

            $processingFee = round((float) ($data['processing_fee'] ?? 0), 2);
            $documentCharges = round((float) ($data['document_charges'] ?? 0), 2);
            $otherCharges = round((float) ($data['other_charges'] ?? 0), 2);
            $bankingCharges = round((float) ($data['banking_charges'] ?? 0), 2);
            $netAmount = round($amount - $processingFee - $documentCharges - $otherCharges, 2);
            if ($netAmount < 0) {
                $netAmount = 0;
            }

            $bankAccountId = $data['internal_bank_account_id'] ?? null;
            $paymentMode = strtolower((string) ($data['payment_mode'] ?? 'cash'));

            $bankAccountId = $this->fdCashbookService->recordPayout($fd, $netAmount, array_merge($data, [
                'payment_mode' => $paymentMode,
                'payout_option' => $paymentMode,
                'txn_label' => 'Fixed Deposit Manual Closure',
                'payment_date' => $data['closure_date'] ?? now()->toDateString(),
                'processing_fee' => $processingFee,
                'document_charges' => $documentCharges,
                'other_charges' => $otherCharges,
                'banking_charges' => $bankingCharges,
            ]));

            if ($paymentMode === 'wallet' && $netAmount > 0.009) {
                $this->walletService->credit(
                    $fd->client_id,
                    $netAmount,
                    'Fixed Deposit Manual Closure Credit',
                    'fixed_deposit',
                    $fd->id
                );
            }

            $fd->update([
                'status' => 'closed',
                'closure_date' => $data['closure_date'] ?? now()->toDateString(),
                'closure_amount' => $netAmount,
                'closure_payment_mode' => $paymentMode,
                'closure_transaction_ref' => $data['transaction_reference'] ?? null,
                'closed_by' => Auth::id(),
                'closure_remarks' => $data['remarks'] ?? null,
                'maturity_processed_at' => $fd->maturity_processed_at ?? now(),
                'processing_fee' => $processingFee,
                'document_charges' => $documentCharges,
                'other_charges' => $otherCharges,
                'banking_charges' => $bankingCharges,
                'internal_bank_account_id' => $bankAccountId,
            ]);

            FixedDepositTransaction::create([
                'fixed_deposit_id' => $fd->id,
                'transaction_type' => 'closure',
                'amount' => $netAmount,
                'payment_mode' => $paymentMode,
                'reference' => $data['transaction_reference'] ?? null,
                'description' => 'Manual Closure',
                'created_by' => Auth::id(),
            ]);

            $this->accountingService->postClosure($fd, $netAmount);
            $this->auditService->log('Closure', $fd->id, $fd->scheme_id, null, $fd->toArray(), $data['remarks'] ?? null);

            return $fd->fresh(['client', 'scheme']);
        });
    }

    public function submitCustomerApplication(array $data): FixedDepositApplication
    {
        $client = Client::with('kycDetail')->findOrFail($data['client_id']);
        if (optional($client->kycDetail)->status !== 'verified') {
            throw ValidationException::withMessages([
                'client_id' => 'KYC must be verified before applying for an FD.',
            ]);
        }

        $scheme = FixedDepositScheme::active()->find($data['scheme_id']);
        if (! $scheme) {
            throw ValidationException::withMessages([
                'scheme_id' => 'Selected FD scheme is not active.',
            ]);
        }

        $amount = round((float) $data['deposit_amount'], 2);
        if ($amount < (float) $scheme->min_deposit_amount || $amount > (float) $scheme->max_deposit_amount) {
            throw ValidationException::withMessages([
                'deposit_amount' => sprintf(
                    'Deposit amount must be between ₹%s and ₹%s.',
                    number_format((float) $scheme->min_deposit_amount, 0),
                    number_format((float) $scheme->max_deposit_amount, 0)
                ),
            ]);
        }

        $tenure = (int) $data['tenure'];
        if ($tenure < $scheme->min_tenure || $tenure > $scheme->max_tenure) {
            throw ValidationException::withMessages([
                'tenure' => "Tenure must be between {$scheme->min_tenure} and {$scheme->max_tenure} {$scheme->tenure_type}.",
            ]);
        }

        $existing = FixedDepositApplication::where('client_id', $client->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
        if ($existing) {
            throw ValidationException::withMessages([
                'client_id' => 'You already have a pending or approved FD application.',
            ]);
        }

        $startDate = Carbon::parse($data['start_date'] ?? $data['deposit_date'] ?? now());
        $calc = $this->previewCalculation($scheme, $amount, $tenure, $startDate->toDateString());

        return FixedDepositApplication::create([
            'client_id' => $client->id,
            'scheme_id' => $scheme->id,
            'deposit_amount' => $amount,
            'tenure' => $tenure,
            'tenure_type' => $scheme->tenure_type,
            'interest_rate' => $scheme->interest_rate,
            'interest_amount' => $calc['interest_amount'],
            'maturity_amount' => $calc['maturity_amount'],
            'deposit_date' => $startDate->toDateString(),
            'start_date' => $startDate->toDateString(),
            'maturity_date' => $calc['maturity_date']->toDateString(),
            'nominee_name' => $data['nominee_name'] ?? null,
            'nominee_relation' => $data['nominee_relation'] ?? ($data['nominee_relationship'] ?? null),
            'payout_option' => $data['payout_option'] ?? $scheme->default_payout_option,
            'status' => 'pending',
            'remarks' => $data['remarks'] ?? null,
            'applied_at' => now(),
            'created_by' => Auth::id(),
        ]);
    }

    public function previewCalculation(FixedDepositScheme $scheme, float $amount, int $tenure, string $startDate): array
    {
        return $this->interestService->calculate(
            $amount,
            (float) $scheme->interest_rate,
            $scheme->deposit_type,
            $scheme->interest_frequency,
            $tenure,
            $scheme->tenure_type,
            Carbon::parse($startDate)
        );
    }

    protected function handlePaymentProofUpload(FixedDeposit $fd, array $options): ?string
    {
        $proofFile = $options['payment_proof'] ?? null;
        if ($proofFile && $proofFile instanceof \Illuminate\Http\UploadedFile && $proofFile->isValid()) {
            $fileName = 'fd_settlement_' . $fd->id . '_' . time() . '.' . $proofFile->getClientOriginalExtension();
            $uploadDir = public_path('uploads/fd_settlements');
            if (!\Illuminate\Support\Facades\File::exists($uploadDir)) {
                \Illuminate\Support\Facades\File::makeDirectory($uploadDir, 0755, true, true);
            }
            $proofFile->move($uploadDir, $fileName);
            $relPath = 'uploads/fd_settlements/' . $fileName;

            // Sync to storage/app/public for symlink compatibility
            $storageDest = storage_path('app/public/uploads/fd_settlements/' . $fileName);
            \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($storageDest));
            \Illuminate\Support\Facades\File::copy($uploadDir . '/' . $fileName, $storageDest);

            return $relPath;
        }

        return null;
    }
}
