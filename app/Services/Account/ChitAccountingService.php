<?php

namespace App\Services\Account;

use App\Models\Account\BankAccount;
use App\Models\Account\Expense;
use App\Models\Account\ExpenseCategories;
use App\Models\Account\Revenue;
use App\Models\Account\RevenueCategories;
use App\Models\Installment;
use App\Models\Payout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Posts chit installment collections and settlement payouts into the Accounts module
 * (bank / cash book + Day Book revenue & expense records).
 */
class ChitAccountingService
{
    public function __construct(
        protected BankTransactionsService $bankTransactionsService
    ) {}

    /**
     * Credit bank/cash for an installment collection and post a Day Book revenue row.
     *
     * @param  array{payment_mode?: string, paid_date?: string|null, reference_no?: string|null, internal_bank_account_id?: int|null, bank_account_id?: int|null}  $data
     */
    public function recordInstallmentCollection(Installment $installment, float $amount, array $data = []): void
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return;
        }

        if (! empty($data['skip_cashbook'])) {
            return;
        }

        $paymentMode = strtolower((string) ($data['payment_mode'] ?? 'cash'));
        if (in_array($paymentMode, ['wallet'], true)) {
            // Wallet collections stay inside customer wallets; no company bank movement.
            return;
        }

        try {
            $bankAccountId = $this->resolveCollectionBankAccountId(
                $paymentMode,
                (int) ($data['internal_bank_account_id'] ?? $data['bank_account_id'] ?? 0)
            );

            if (! $bankAccountId) {
                return;
            }

            $groupCode = $installment->group?->group_code ?? ('GRP-' . $installment->group_id);
            $clientName = $installment->member?->client?->client_name ?? 'Member';
            $ref = $data['reference_no']
                ?: ('CHIT-COLL-' . $installment->id . '-' . now()->format('YmdHis'));
            $collectedBy = isset($data['collected_by']) ? (int) $data['collected_by'] : Auth::id();
            $months = $data['months'] ?? $installment->month_number;
            $description = AccountingTags::chitIcDescription(
                $groupCode,
                $months,
                $clientName,
                $collectedBy
            );
            $date = $data['paid_date'] ?? now()->toDateString();

            $this->bankTransactionsService->createChitCollectionTransaction(
                $bankAccountId,
                $amount,
                $ref,
                $description,
                $date,
                AccountingTags::MODULE_CHIT,
                AccountingTags::ENTRY_INSTALLMENT
            );

            $this->createPostedRevenue(
                'Chit IC',
                'CHIT-COLL',
                $amount,
                $description,
                $ref,
                $date,
                $bankAccountId,
                AccountingTags::MODULE_CHIT,
                AccountingTags::ENTRY_INSTALLMENT
            );
        } catch (\Throwable $e) {
            Log::error('Chit installment accounting post failed', [
                'installment_id' => $installment->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * One bank/cashbook credit for a bulk chit collection covering many installments.
     *
     * @param  array{
     *   payment_mode?: string,
     *   paid_date?: string|null,
     *   reference_no?: string|null,
     *   internal_bank_account_id?: int|null,
     *   bank_account_id?: int|null,
     *   collected_by?: int|null,
     *   lines?: list<array{group_code?: string, month?: int|string|null, client_name?: string}>
     * }  $data
     */
    public function recordBulkInstallmentCollection(float $amount, array $data = []): void
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0.009) {
            return;
        }

        $paymentMode = strtolower((string) ($data['payment_mode'] ?? 'cash'));
        if (in_array($paymentMode, ['wallet', ''], true)) {
            return;
        }

        $bankAccountId = $this->resolveCollectionBankAccountId(
            $paymentMode,
            (int) ($data['internal_bank_account_id'] ?? $data['bank_account_id'] ?? 0)
        );
        if (! $bankAccountId) {
            return;
        }

        $lines = $data['lines'] ?? [];
        $collectedBy = isset($data['collected_by']) ? (int) $data['collected_by'] : Auth::id();
        $description = AccountingTags::chitIcBulkDescription($lines, $collectedBy);
        $ref = $data['reference_no'] ?: ('CHIT-BULK-' . now()->format('YmdHis'));
        $date = $data['paid_date'] ?? now()->toDateString();

        $this->bankTransactionsService->createChitCollectionTransaction(
            $bankAccountId,
            $amount,
            $ref,
            $description,
            $date,
            AccountingTags::MODULE_CHIT,
            AccountingTags::ENTRY_INSTALLMENT
        );

        $this->createPostedRevenue(
            'Chit IC',
            'CHIT-COLL',
            $amount,
            $description,
            $ref,
            $date,
            $bankAccountId,
            AccountingTags::MODULE_CHIT,
            AccountingTags::ENTRY_INSTALLMENT
        );
    }

    /**
     * Debit bank/cash for settlement net payout and post fee/commission revenue.
     * Net payout is tracked only via bank debit (no matching Expense) to avoid Day Book double-count.
     *
     * @param  array{payment_mode?: string, paid_date?: string|null, reference_no?: string|null, internal_bank_account_id?: int|null}  $paymentData
     */
    public function recordSettlementPayout(Payout $payout, array $paymentData = []): void
    {
        $net = round((float) ($payout->net_payout_amount ?? $payout->payout_amount ?? 0), 2);
        $paymentMode = strtolower((string) ($paymentData['payment_mode'] ?? $payout->payment_mode ?? 'cash'));
        $paidDate = $paymentData['paid_date'] ?? optional($payout->paid_date)->toDateString() ?? now()->toDateString();
        $groupCode = $payout->group?->group_code ?? ('GRP-' . $payout->group_id);
        $clientName = $payout->winner?->client?->client_name ?? 'Member';
        $code = $payout->payout_code ?: ('PAY-' . $payout->id);

        try {
            $bankingCharges = round((float) ($payout->banking_charges ?? 0), 2);
            $companyOutflow = round($net + max(0, $bankingCharges), 2);
            $bankAccountId = $this->resolvePayoutBankAccountId(
                $paymentMode,
                (int) ($paymentData['internal_bank_account_id'] ?? $payout->internal_bank_account_id ?? 0),
                $companyOutflow
            );

            if ($bankAccountId && $net > 0) {
                $description = "Chit settlement payout — {$code} — {$groupCode} — {$clientName}";
                $this->bankTransactionsService->createChitPayoutTransaction(
                    $bankAccountId,
                    $net,
                    $code,
                    $description,
                    $paidDate,
                    AccountingTags::MODULE_CHIT,
                    AccountingTags::ENTRY_SETTLEMENT
                );
                // Bank debit already records the outflow in the cashbook / Day Book bank section.
                // Do not also post a matching Expense (avoids double-counting net payout).
            }

            $this->recordBankTransferCharges(
                $bankAccountId,
                $bankingCharges,
                $code . '-BANKCHG',
                $paidDate,
                AccountingTags::MODULE_CHIT,
                'Chit settlement',
                $groupCode,
                $clientName,
                (float) $net
            );

            $feeLines = [
                [
                    'amount' => round((float) ($payout->processing_fee ?? 0), 2),
                    'name' => 'Chit Processing Fee',
                    'code' => 'CHIT-PROC',
                    'entry' => AccountingTags::ENTRY_PROC_FEE,
                    'label' => 'processing fee',
                    'suffix' => 'PROC',
                ],
                [
                    'amount' => round((float) ($payout->document_charges ?? 0), 2),
                    'name' => 'Chit Document Fee',
                    'code' => 'CHIT-DOC',
                    'entry' => AccountingTags::ENTRY_DOC_FEE,
                    'label' => 'document fee',
                    'suffix' => 'DOC',
                ],
                [
                    'amount' => round((float) ($payout->other_charges ?? 0), 2),
                    'name' => 'Chit Other Charges',
                    'code' => 'CHIT-OTHER',
                    'entry' => AccountingTags::ENTRY_OTHER_FEE,
                    'label' => 'other charges',
                    'suffix' => 'OTHER',
                ],
            ];

            foreach ($feeLines as $fee) {
                if ($fee['amount'] <= 0) {
                    continue;
                }
                $this->createPostedRevenue(
                    $fee['name'],
                    $fee['code'],
                    $fee['amount'],
                    "Chit settlement {$fee['label']} retained — {$code} — {$groupCode} — {$clientName}",
                    $code . '-' . $fee['suffix'],
                    $paidDate,
                    $bankAccountId,
                    AccountingTags::MODULE_CHIT,
                    $fee['entry']
                );
            }

            $commission = round((float) ($payout->commission_amount ?? 0), 2);
            if ($commission > 0) {
                $this->createPostedRevenue(
                    'Chit Foreman Commission',
                    'CHIT-COMM',
                    $commission,
                    "Chit foreman commission — {$code} — {$groupCode} — {$clientName}",
                    $code . '-COMM',
                    $paidDate,
                    $bankAccountId,
                    AccountingTags::MODULE_CHIT,
                    AccountingTags::ENTRY_FOREMAN_COMM
                );
            }
        } catch (\Throwable $e) {
            Log::error('Chit settlement accounting post failed', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Resolve bank for collections.
     * Cash / in_hand → Cash in Hand.
     * Legacy cheque (no longer offered in UI) → Cash in Hand if no bank selected.
     * UPI / bank_transfer → selected company bank required.
     */
    public function resolveCollectionBankAccountId(string $paymentMode, int $requestedBankId = 0): ?int
    {
        $mode = strtolower(trim($paymentMode));
        $mode = str_replace([' ', '-'], '_', $mode);
        if (in_array($mode, ['in_hand', 'cash'], true)) {
            return $this->ensureCashInHandAccount()->id;
        }

        // Legacy cheque rows only — option removed from payment UIs.
        if ($mode === 'cheque') {
            if ($requestedBankId > 0) {
                return $requestedBankId;
            }

            return $this->ensureCashInHandAccount()->id;
        }

        if (in_array($mode, ['upi', 'bank_transfer', 'admin_bank_transfer', 'agent_bank_transfer'], true)) {
            if ($requestedBankId <= 0) {
                throw ValidationException::withMessages([
                    'internal_bank_account_id' => 'Please select a company bank account for this payment mode.',
                ]);
            }

            return $requestedBankId;
        }

        // Fallback: use selected bank or cash book so the movement is still tracked.
        if ($requestedBankId > 0) {
            return $requestedBankId;
        }

        return $this->ensureCashInHandAccount()->id;
    }

    /**
     * Resolve bank for payouts. Cash → Cash in Hand; electronic → required bank with balance check.
     */
    public function resolvePayoutBankAccountId(string $paymentMode, int $requestedBankId, float $netAmount): ?int
    {
        $mode = strtolower($paymentMode);

        if (in_array($mode, ['in_hand', 'cash'], true)) {
            $cash = $this->ensureCashInHandAccount();
            if ($netAmount > 0 && (float) $cash->current_balance < $netAmount) {
                // Still allow cash payout tracking; negative running balance is possible for cash book.
            }

            return $cash->id;
        }

        // Legacy cheque without a selected bank → Cash in Hand (same as collections).
        if ($mode === 'cheque' && $requestedBankId <= 0) {
            return $this->ensureCashInHandAccount()->id;
        }

        $bankId = $requestedBankId > 0 ? $requestedBankId : 0;
        if ($bankId <= 0) {
            throw ValidationException::withMessages([
                'internal_bank_account_id' => 'Please select a company bank account to release this settlement.',
            ]);
        }

        $bankAccount = BankAccount::findOrFail($bankId);
        if ($netAmount > 0 && (float) $bankAccount->current_balance < $netAmount) {
            throw ValidationException::withMessages([
                'internal_bank_account_id' => "Insufficient balance in '{$bankAccount->account_name}' (Current: ₹"
                    . number_format((float) $bankAccount->current_balance, 2) . ').',
            ]);
        }

        return $bankId;
    }

    /**
     * Public Day Book expense helper for cross-module posts (e.g. bank transfer charges).
     */
    public function postExternalExpense(
        string $categoryName,
        string $categoryCode,
        float $amount,
        string $description,
        string $reference,
        string $date,
        ?int $bankAccountId = null,
        ?string $moduleTag = null,
        ?string $entryTag = null
    ): Expense {
        return $this->createPostedExpense(
            $categoryName,
            $categoryCode,
            $amount,
            $description,
            $reference,
            $date,
            $bankAccountId,
            $moduleTag,
            $entryTag
        );
    }

    /**
     * Company bank-transfer / NEFT charges: debit bank + auto-create posted Expense.
     * Does not reduce the client payout (company cost).
     */
    public function recordBankTransferCharges(
        ?int $bankAccountId,
        float $amount,
        string $reference,
        string $date,
        string $moduleTag,
        string $moduleLabel,
        string $accountOrGroup,
        string $clientName,
        float $transferAmount
    ): void {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0.009 || ! $bankAccountId) {
            return;
        }

        $description = AccountingTags::bankTransferChargesDescription(
            $moduleLabel,
            $accountOrGroup,
            $clientName,
            $transferAmount,
            $amount
        );

        $existingExpense = $this->bankChargeExpenseExists($reference);

        if (! $existingExpense && ! $this->bankChargeDebitExists($reference)) {
            $this->bankTransactionsService->createBankTransferChargesDebit(
                $bankAccountId,
                $amount,
                $reference,
                $description,
                $date,
                $moduleTag
            );
        }

        $this->ensureBankChargeExpense(
            $amount,
            $reference,
            $date,
            $bankAccountId,
            $moduleTag,
            $description
        );
    }

    /**
     * Internal bank-to-bank transfer charges: Expense only (bank debit is already posted).
     */
    public function recordInternalTransferCharges(\App\Models\Account\BankTransfer $transfer): ?Expense
    {
        $amount = round((float) ($transfer->transfer_charges ?? 0), 2);
        if ($amount <= 0.009 || ! $transfer->from_account_id) {
            return null;
        }

        $transfer->loadMissing(['fromAccount', 'toAccount']);
        $fromName = $transfer->fromAccount?->account_name ?? 'Source';
        $toName = $transfer->toAccount?->account_name ?? 'Destination';
        $date = optional($transfer->transfer_date)->toDateString() ?? now()->toDateString();
        $reference = $transfer->transfer_number . '-CHARGES';

        return $this->ensureBankChargeExpense(
            $amount,
            $reference,
            $date,
            (int) $transfer->from_account_id,
            AccountingTags::MODULE_TRANSFER,
            AccountingTags::bankTransferChargesDescription(
                'Internal transfer',
                (string) $transfer->transfer_number,
                $fromName . ' → ' . $toName,
                (float) $transfer->transfer_amount,
                $amount
            )
        );
    }

    /**
     * Create a posted Expense for a bank charge when one does not already exist.
     */
    public function ensureBankChargeExpense(
        float $amount,
        string $reference,
        string $date,
        ?int $bankAccountId,
        string $moduleTag,
        string $description
    ): ?Expense {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0.009 || ! $bankAccountId || $reference === '') {
            return null;
        }

        $existing = Expense::query()
            ->where('reference_number', $reference)
            ->where('entry_tag', AccountingTags::ENTRY_BANK_FEE)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createPostedExpense(
            'Bank Transfer Charges',
            'BANK-XFER-CHG',
            $amount,
            $description,
            $reference,
            $date,
            $bankAccountId,
            $moduleTag,
            AccountingTags::ENTRY_BANK_FEE
        );
    }

    protected function bankChargeExpenseExists(string $reference): bool
    {
        return Expense::query()
            ->where('reference_number', $reference)
            ->where('entry_tag', AccountingTags::ENTRY_BANK_FEE)
            ->exists();
    }

    protected function bankChargeDebitExists(string $reference): bool
    {
        return \App\Models\Account\BankTransaction::query()
            ->where('reference_number', $reference)
            ->where('entry_tag', AccountingTags::ENTRY_BANK_FEE)
            ->exists();
    }

    /**
     * Public Day Book revenue helper for cross-module posts (e.g. FD fees).
     */
    public function postExternalRevenue(
        string $categoryName,
        string $categoryCode,
        float $amount,
        string $description,
        string $reference,
        string $date,
        ?int $bankAccountId = null,
        ?string $moduleTag = null,
        ?string $entryTag = null
    ): Revenue {
        return $this->createPostedRevenue(
            $categoryName,
            $categoryCode,
            $amount,
            $description,
            $reference,
            $date,
            $bankAccountId,
            $moduleTag,
            $entryTag
        );
    }

    /**
     * Remove a prior installment collection from the cashbook (undo payment).
     * Deletes the original credit (and any REV/UNDO debit) instead of posting a reversal row.
     *
     * Important: never re-map UPI / Bank Transfer onto Cash in Hand during undo.
     * Prefer the stored bank id, otherwise match the original credit by reference/description.
     */
    public function reverseInstallmentCollection(Installment $installment, float $amount, array $data = []): void
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return;
        }

        $paymentMode = strtolower((string) ($data['payment_mode'] ?? $installment->payment_mode ?? 'cash'));
        if (in_array($paymentMode, ['wallet', ''], true)) {
            return;
        }

        try {
            $requestedBankId = (int) ($data['internal_bank_account_id'] ?? $data['bank_account_id'] ?? 0);
            $bankAccountId = $this->resolveBankAccountIdForCollectionUndo($paymentMode, $requestedBankId);

            $groupCode = $installment->group?->group_code ?? ('GRP-' . $installment->group_id);
            $ref = (string) ($data['reference_no'] ?? $installment->reference_no ?? '');
            $refs = array_values(array_filter([
                $ref !== '' ? $ref : null,
                'CHIT-COLL-' . $installment->id,
                'INST-' . $installment->id,
            ]));

            $options = [
                'references' => $refs,
                'identity_contains' => array_values(array_filter([
                    $groupCode,
                ])),
                'description_contains' => array_values(array_filter([
                    $groupCode,
                ])),
                'month_numbers' => [(int) $installment->month_number],
                'module_tag' => AccountingTags::MODULE_CHIT,
                'entry_tags' => [AccountingTags::ENTRY_INSTALLMENT],
                'allow_partial_reduce' => true,
            ];

            // bankAccountId may be 0 — removeCollectionTransactions will discover the real bank
            // from the matching credit so Bank Transfer undos stay on that bank.
            $removed = $this->bankTransactionsService->removeCollectionTransactions(
                $bankAccountId,
                $amount,
                $options
            );

            if ($removed < 1) {
                Log::warning('Chit undo: no matching bank transaction removed', [
                    'installment_id' => $installment->id,
                    'amount' => $amount,
                    'group' => $groupCode,
                    'month' => $installment->month_number,
                    'bank_account_id' => $bankAccountId,
                    'refs' => $refs,
                ]);
            }

            // Also remove matching Day Book revenue rows for this collection.
            $revenueQuery = Revenue::query()
                ->whereRaw('ABS(amount - ?) < 0.02', [$amount])
                ->where(function ($q) use ($refs, $groupCode, $installment) {
                    foreach ($refs as $reference) {
                        $q->orWhere('reference_number', $reference)
                            ->orWhere('reference_number', 'UNDO-' . $reference)
                            ->orWhere('reference_number', 'like', $reference . '%');
                    }
                    $q->orWhere(function ($dq) use ($groupCode, $installment) {
                        $dq->where('module_tag', AccountingTags::MODULE_CHIT)
                            ->where('entry_tag', AccountingTags::ENTRY_INSTALLMENT)
                            ->where('description', 'like', '%' . $groupCode . '%')
                            ->where(function ($mq) use ($installment) {
                                $month = (int) $installment->month_number;
                                $mq->where('description', 'like', '%M' . $month . '%')
                                    ->orWhere('description', 'like', '% M' . $month . ' %');
                            });
                    });
                });

            $revenueQuery->orderByDesc('id')->limit(3)->get()->each->delete();
        } catch (\Throwable $e) {
            Log::error('Chit installment reverse accounting failed', [
                'installment_id' => $installment->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Resolve bank for undo without remapping electronic payments onto Cash in Hand.
     * Legacy cheque without a bank still uses Cash in Hand (same as collect).
     */
    public function resolveBankAccountIdForCollectionUndo(string $paymentMode, int $requestedBankId = 0): int
    {
        if ($requestedBankId > 0) {
            return $requestedBankId;
        }

        $mode = strtolower(trim($paymentMode));
        $mode = str_replace([' ', '-'], '_', $mode);

        // Electronic modes must keep the original bank row — discover via tx match (id 0).
        if (in_array($mode, ['upi', 'bank_transfer', 'admin_bank_transfer', 'agent_bank_transfer'], true)) {
            return 0;
        }

        // Cash / legacy cheque (no bank selected) → Cash in Hand.
        if (in_array($mode, ['in_hand', 'cash', 'cheque', ''], true)) {
            return (int) $this->ensureCashInHandAccount()->id;
        }

        // Unknown mode: do not invent Cash in Hand; match original credit instead.
        return 0;
    }

    /**
     * Credit cashbook for member-transfer takeover paid by cash/UPI/bank.
     */
    public function recordTransferTakeoverCollection(
        float $amount,
        string $paymentMode,
        string $description,
        string $reference,
        ?int $bankAccountId = null,
        ?string $date = null
    ): void {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return;
        }

        $mode = strtolower($paymentMode);
        if (in_array($mode, ['wallet', ''], true)) {
            return;
        }

        $resolvedId = $this->resolveCollectionBankAccountId($mode, (int) ($bankAccountId ?? 0));
        if (! $resolvedId) {
            return;
        }

        $this->bankTransactionsService->createChitCollectionTransaction(
            $resolvedId,
            $amount,
            $reference,
            $description,
            $date ?: now()->toDateString(),
            AccountingTags::MODULE_CHIT,
            AccountingTags::ENTRY_TRANSFER
        );
    }

    /**
     * Debit cashbook when outgoing transfer settlement is paid outside wallet.
     */
    public function recordTransferSettlementPayout(
        float $amount,
        string $paymentMode,
        string $description,
        string $reference,
        ?int $bankAccountId = null,
        ?string $date = null
    ): void {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return;
        }

        $mode = strtolower($paymentMode);
        if (in_array($mode, ['wallet', ''], true)) {
            return;
        }

        $resolvedId = $this->resolvePayoutBankAccountId($mode, (int) ($bankAccountId ?? 0), $amount);
        if (! $resolvedId) {
            return;
        }

        $this->bankTransactionsService->createChitPayoutTransaction(
            $resolvedId,
            $amount,
            $reference,
            $description,
            $date ?: now()->toDateString(),
            AccountingTags::MODULE_CHIT,
            AccountingTags::ENTRY_TRANSFER
        );
    }

    public function ensureCashInHandAccount(): BankAccount
    {
        $creatorId = $this->actorId();

        $existing = BankAccount::query()
            ->where(function ($q) use ($creatorId) {
                $q->where('created_by', $creatorId)->orWhere('creator_id', $creatorId);
            })
            ->where(function ($q) {
                $q->where('account_type', 'cash')
                    ->orWhere('account_name', 'like', '%Cash in Hand%')
                    ->orWhere('account_name', 'like', '%Cash In Hand%')
                    ->orWhere('bank_name', 'like', '%Cash%');
            })
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return BankAccount::create([
            'account_number' => 'CASH-001',
            'account_name' => 'Cash in Hand',
            'bank_name' => 'Cash',
            'branch_name' => 'Head Office',
            'account_type' => 'cash',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
            'created_by' => $creatorId,
            'creator_id' => $creatorId,
        ]);
    }

    protected function createPostedRevenue(
        string $categoryName,
        string $categoryCode,
        float $amount,
        string $description,
        string $reference,
        string $date,
        ?int $bankAccountId,
        ?string $moduleTag = null,
        ?string $entryTag = null
    ): Revenue {
        $actorId = $this->actorId();
        $category = $this->ensureRevenueCategory($categoryName, $categoryCode, $actorId);

        return Revenue::create([
            'revenue_date' => $date,
            'category_id' => $category->id,
            'bank_account_id' => $bankAccountId,
            'amount' => $amount,
            'description' => $description,
            'module_tag' => $moduleTag,
            'entry_tag' => $entryTag,
            'reference_number' => $reference,
            'status' => 'posted',
            'approved_by' => Auth::id() ?: $actorId,
            'created_by' => $actorId,
            'creator_id' => $actorId,
        ]);
    }

    protected function createPostedExpense(
        string $categoryName,
        string $categoryCode,
        float $amount,
        string $description,
        string $reference,
        string $date,
        ?int $bankAccountId,
        ?string $moduleTag = null,
        ?string $entryTag = null
    ): Expense {
        $actorId = $this->actorId();
        $category = $this->ensureExpenseCategory($categoryName, $categoryCode, $actorId);

        return Expense::create([
            'expense_date' => $date,
            'category_id' => $category->id,
            'bank_account_id' => $bankAccountId,
            'amount' => $amount,
            'description' => $description,
            'module_tag' => $moduleTag,
            'entry_tag' => $entryTag,
            'reference_number' => $reference,
            'status' => 'posted',
            'approved_by' => Auth::id() ?: $actorId,
            'created_by' => $actorId,
            'creator_id' => $actorId,
        ]);
    }

    protected function ensureRevenueCategory(string $name, string $code, int $actorId): RevenueCategories
    {
        return RevenueCategories::firstOrCreate(
            [
                'category_code' => $code,
                'created_by' => $actorId,
            ],
            [
                'category_name' => $name,
                'description' => 'Auto-created for chit fund accounting',
                'is_active' => true,
                'creator_id' => $actorId,
            ]
        );
    }

    protected function ensureExpenseCategory(string $name, string $code, int $actorId): ExpenseCategories
    {
        return ExpenseCategories::firstOrCreate(
            [
                'category_code' => $code,
                'created_by' => $actorId,
            ],
            [
                'category_name' => $name,
                'description' => 'Auto-created for chit fund accounting',
                'is_active' => true,
                'creator_id' => $actorId,
            ]
        );
    }

    protected function actorId(): int
    {
        if (function_exists('creatorId')) {
            try {
                $id = (int) creatorId();
                if ($id > 0) {
                    return $id;
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return (int) (Auth::id() ?: 1);
    }
}
