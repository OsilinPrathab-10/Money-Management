<?php

namespace App\Services\Account;

use App\Models\FixedDeposit;
use Illuminate\Support\Facades\Log;

/**
 * Posts Fixed Deposit deposits and payouts into the Accounts module cashbook
 * (bank / Cash in Hand), aligned with ChitAccountingService resolution rules.
 */
class FdAccountingService
{
    public function __construct(
        protected BankTransactionsService $bankTransactionsService,
        protected ChitAccountingService $chitAccounting
    ) {}

    /**
     * Credit company bank/cash when a new FD deposit is received.
     * Skips renewals (no fresh cash intake) and wallet-funded creations.
     *
     * @param  array{payment_mode?: string, deposit_date?: string|null, internal_bank_account_id?: int|null, bank_account_id?: int|null}  $data
     */
    public function reverseDeposit(FixedDeposit $fd): void
    {
        if (! empty($fd->renewed_from_id)) {
            return;
        }

        $amount = round((float) $fd->deposit_amount, 2);
        if ($amount <= 0) {
            return;
        }

        try {
            $this->bankTransactionsService->removeCollectionTransactions(0, $amount, [
                'references' => array_values(array_filter([(string) $fd->fd_number])),
                'description_contains' => array_values(array_filter([
                    (string) $fd->fd_number,
                    'FD deposit',
                ])),
                'module_tag' => AccountingTags::MODULE_FD,
                'entry_tags' => [AccountingTags::ENTRY_DEPOSIT],
                'allow_partial_reduce' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('FD deposit cashbook reverse failed', [
                'fd_id' => $fd->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function recordDeposit(FixedDeposit $fd, array $data = []): void
    {
        if (! empty($fd->renewed_from_id) || ! empty($data['renewed_from_id'])) {
            return;
        }

        $amount = round((float) $fd->deposit_amount, 2);
        if ($amount <= 0) {
            return;
        }

        $paymentMode = strtolower((string) ($data['payment_mode'] ?? 'cash'));
        if (in_array($paymentMode, ['wallet'], true)) {
            return;
        }

        try {
            $bankAccountId = $this->chitAccounting->resolveCollectionBankAccountId(
                $paymentMode,
                (int) ($data['internal_bank_account_id'] ?? $data['bank_account_id'] ?? 0)
            );

            if (! $bankAccountId) {
                return;
            }

            $clientName = $fd->client?->client_name ?? 'Customer';
            $ref = $fd->fd_number;
            $description = "FD deposit receipt — {$ref} — {$clientName}";
            $date = $data['deposit_date'] ?? optional($fd->deposit_date)->toDateString() ?? now()->toDateString();

            $this->bankTransactionsService->createFdDepositTransaction(
                $bankAccountId,
                $amount,
                $ref,
                $description,
                $date,
                AccountingTags::MODULE_FD,
                AccountingTags::ENTRY_DEPOSIT
            );
        } catch (\Throwable $e) {
            Log::error('FD deposit cashbook post failed', [
                'fd_id' => $fd->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Debit company bank/cash for FD maturity / premature / close payouts.
     * Wallet and chit allocation do not move company cash (liability / internal).
     *
     * @param  array{
     *   payment_mode?: string,
     *   payout_option?: string,
     *   payment_date?: string|null,
     *   internal_bank_account_id?: int|null,
     *   processing_fee?: float|int,
     *   document_charges?: float|int,
     *   other_charges?: float|int,
     *   banking_charges?: float|int
     * }  $options
     */
    public function recordPayout(FixedDeposit $fd, float $netAmount, array $options = []): ?int
    {
        $netAmount = round(max(0, $netAmount), 2);
        $bankingCharges = round((float) ($options['banking_charges'] ?? $fd->banking_charges ?? 0), 2);
        $mode = strtolower((string) (
            $options['payment_mode']
            ?? $options['payout_option']
            ?? $fd->closure_payment_mode
            ?? 'cash'
        ));

        if (in_array($mode, ['wallet', 'chit'], true)) {
            $this->recordRetainedFees($fd, $options);

            return null;
        }

        if ($netAmount <= 0) {
            $this->recordRetainedFees($fd, $options);

            return null;
        }

        try {
            $companyOutflow = round($netAmount + max(0, $bankingCharges), 2);
            $bankAccountId = $this->chitAccounting->resolvePayoutBankAccountId(
                $mode,
                (int) ($options['internal_bank_account_id'] ?? $fd->internal_bank_account_id ?? 0),
                $companyOutflow
            );

            if (! $bankAccountId) {
                return null;
            }

            $clientName = $fd->client?->client_name ?? 'Customer';
            $label = match (true) {
                ($options['txn_label'] ?? '') !== '' => (string) $options['txn_label'],
                default => 'FD payout',
            };
            $ref = $fd->fd_number;
            $description = "{$label} — {$ref} — {$clientName}";
            $date = $options['payment_date']
                ?? $options['withdrawal_date']
                ?? $options['closure_date']
                ?? now()->toDateString();

            $this->bankTransactionsService->createFdPayoutTransaction(
                $bankAccountId,
                $netAmount,
                $ref,
                $description,
                $date,
                AccountingTags::MODULE_FD,
                AccountingTags::ENTRY_PAYOUT
            );

            if ($bankingCharges > 0.009) {
                $this->chitAccounting->recordBankTransferCharges(
                    $bankAccountId,
                    $bankingCharges,
                    $ref . '-BANKCHG',
                    $date,
                    AccountingTags::MODULE_FD,
                    'FD payout',
                    (string) $ref,
                    $clientName,
                    $netAmount
                );
            }

            $this->recordRetainedFees($fd, array_merge($options, [
                'bank_account_id' => $bankAccountId,
                'payment_date' => $date,
            ]));

            return $bankAccountId;
        } catch (\Throwable $e) {
            Log::error('FD payout cashbook post failed', [
                'fd_id' => $fd->id,
                'net_amount' => $netAmount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Debit cashbook when a customer withdraws wallet balance (cash leaves the company).
     */
    public function recordWalletWithdrawal(
        int $clientId,
        float $amount,
        string $paymentMode,
        ?int $bankAccountId = null,
        ?string $reference = null,
        ?string $date = null
    ): void {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return;
        }

        $mode = strtolower(trim($paymentMode));
        $resolvedId = $this->chitAccounting->resolvePayoutBankAccountId(
            $mode,
            (int) ($bankAccountId ?? 0),
            $amount
        );

        if (! $resolvedId) {
            return;
        }

        $ref = $reference ?: ('WALLET-WD-' . $clientId . '-' . now()->format('YmdHis'));
        $this->bankTransactionsService->createFdPayoutTransaction(
            $resolvedId,
            $amount,
            $ref,
            "Customer wallet withdrawal — client #{$clientId}",
            $date ?: now()->toDateString(),
            AccountingTags::MODULE_FD,
            AccountingTags::ENTRY_WALLET_WD
        );
    }

    /**
     * Fees retained on FD payout stay with the company — Day Book revenue (split by type).
     */
    protected function recordRetainedFees(FixedDeposit $fd, array $options): void
    {
        $date = $options['payment_date']
            ?? $options['withdrawal_date']
            ?? $options['closure_date']
            ?? now()->toDateString();
        $bankId = $options['bank_account_id'] ?? $options['internal_bank_account_id'] ?? null;
        $bankId = $bankId ? (int) $bankId : null;
        $clientName = $fd->client?->client_name ?? 'Customer';

        $feeLines = [
            [
                'amount' => round((float) ($options['processing_fee'] ?? $fd->processing_fee ?? 0), 2),
                'name' => 'FD Processing Fee',
                'code' => 'FD-PROC',
                'entry' => AccountingTags::ENTRY_PROC_FEE,
                'label' => 'processing fee',
                'suffix' => 'PROC',
            ],
            [
                'amount' => round((float) ($options['document_charges'] ?? $fd->document_charges ?? 0), 2),
                'name' => 'FD Document Fee',
                'code' => 'FD-DOC',
                'entry' => AccountingTags::ENTRY_DOC_FEE,
                'label' => 'document fee',
                'suffix' => 'DOC',
            ],
            [
                'amount' => round((float) ($options['other_charges'] ?? $fd->other_charges ?? 0), 2),
                'name' => 'FD Other Charges',
                'code' => 'FD-OTHER',
                'entry' => AccountingTags::ENTRY_OTHER_FEE,
                'label' => 'other charges',
                'suffix' => 'OTHER',
            ],
        ];

        foreach ($feeLines as $fee) {
            if ($fee['amount'] <= 0.009) {
                continue;
            }

            try {
                $this->chitAccounting->postExternalRevenue(
                    $fee['name'],
                    $fee['code'],
                    $fee['amount'],
                    "FD {$fee['label']} — {$fd->fd_number} — {$clientName}",
                    $fd->fd_number . '-' . $fee['suffix'],
                    $date,
                    $bankId,
                    AccountingTags::MODULE_FD,
                    $fee['entry']
                );
            } catch (\Throwable $e) {
                Log::warning('FD fee revenue post failed', [
                    'fd_id' => $fd->id,
                    'fee_code' => $fee['code'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
