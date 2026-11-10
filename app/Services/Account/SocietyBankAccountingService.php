<?php

namespace App\Services\Account;

use App\Models\Account\BankAccount;
use App\Models\Account\Expense;
use App\Models\Account\ExpenseCategories;
use App\Models\Account\Revenue;
use App\Models\Account\RevenueCategories;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Society bank accounting service: bank account resolution, bank transfer charges,
 * and external Day Book revenue & expense postings for loans, FDs, and banking.
 */
class SocietyBankAccountingService
{
    public function __construct(
        protected BankTransactionsService $bankTransactionsService
    ) {}

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
                'description' => 'Auto-created for society bank accounting',
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
                'description' => 'Auto-created for society bank accounting',
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
