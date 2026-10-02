<?php

namespace App\Services\Account;

use App\Models\Account\BankTransaction;
use Illuminate\Support\Facades\Auth;
use App\Models\Account\BankAccount;

class BankTransactionsService
{
    public function createVendorPayment($vendorPayment)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $vendorPayment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance - $vendorPayment->payment_amount : -$vendorPayment->payment_amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $vendorPayment->bank_account_id;
        $bankTransaction->transaction_date = $vendorPayment->payment_date;
        $bankTransaction->transaction_type = 'debit';
        $bankTransaction->reference_number = $vendorPayment->payment_number;
        $bankTransaction->description = 'Vendor Payment #' . $vendorPayment->payment_number . ' - ' . $vendorPayment->vendor->name;
        $bankTransaction->amount = $vendorPayment->payment_amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

         // Update bank account balance
        $this->updateBankBalance($vendorPayment->bank_account_id, -$vendorPayment->payment_amount);
    }

    public function createCustomerPayment($customerPayment)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $customerPayment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $customerPayment->payment_amount : $customerPayment->payment_amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $customerPayment->bank_account_id;
        $bankTransaction->transaction_date = $customerPayment->payment_date;
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $customerPayment->payment_number;
        $bankTransaction->description = 'Customer Payment #' . $customerPayment->payment_number . ' - ' . $customerPayment->customer->name;
        $bankTransaction->amount = $customerPayment->payment_amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        // Update bank account balance
        $this->updateBankBalance($customerPayment->bank_account_id, $customerPayment->payment_amount);
    }

    public function createTransferBankTransactions($transfer)
    {
        // Get running balance for source account
        $fromLastTransaction = BankTransaction::where('bank_account_id', $transfer->from_account_id)->orderBy('id', 'desc')->first();
        $fromRunningBalance = $fromLastTransaction ? $fromLastTransaction->running_balance - $transfer->transfer_amount : -$transfer->transfer_amount;

        $fromDescription = 'Transfer to ' . $transfer->toAccount->account_name;
        if ($transfer->transfer_charges > 0) {
            $fromDescription .= ' (Transfer Charges: ₹' . number_format((float)$transfer->transfer_charges, 2) . ')';
        }

        // Debit transaction from source account
        $debitTransaction = new BankTransaction();
        $debitTransaction->bank_account_id = $transfer->from_account_id;
        $debitTransaction->transaction_date = $transfer->transfer_date;
        $debitTransaction->transaction_type = 'debit';
        $debitTransaction->reference_number = $transfer->transfer_number;
        $debitTransaction->description = $fromDescription;
        $debitTransaction->module_tag = AccountingTags::MODULE_TRANSFER;
        $debitTransaction->entry_tag = AccountingTags::ENTRY_TRANSFER;
        $debitTransaction->amount = $transfer->transfer_amount;
        $debitTransaction->running_balance = $fromRunningBalance;
        $debitTransaction->transaction_status = 'cleared';
        $debitTransaction->reconciliation_status = 'unreconciled';
        $debitTransaction->created_by = creatorId();
        $debitTransaction->save();

        // Get running balance for destination account
        $toLastTransaction = BankTransaction::where('bank_account_id', $transfer->to_account_id)->orderBy('id', 'desc')->first();
        $toRunningBalance = $toLastTransaction ? $toLastTransaction->running_balance + $transfer->transfer_amount : $transfer->transfer_amount;

        // Credit transaction to destination account
        $creditTransaction = new BankTransaction();
        $creditTransaction->bank_account_id = $transfer->to_account_id;
        $creditTransaction->transaction_date = $transfer->transfer_date;
        $creditTransaction->transaction_type = 'credit';
        $creditTransaction->reference_number = $transfer->transfer_number;
        $creditTransaction->description = 'Transfer from ' . $transfer->fromAccount->account_name;
        $creditTransaction->module_tag = AccountingTags::MODULE_TRANSFER;
        $creditTransaction->entry_tag = AccountingTags::ENTRY_TRANSFER;
        $creditTransaction->amount = $transfer->transfer_amount;
        $creditTransaction->running_balance = $toRunningBalance;
        $creditTransaction->transaction_status = 'cleared';
        $creditTransaction->reconciliation_status = 'unreconciled';
        $creditTransaction->created_by = creatorId();
        $creditTransaction->save();

        // Additional debit for transfer charges from debited bank account (if any)
        if ($transfer->transfer_charges > 0) {
            $chargesRunningBalance = $fromRunningBalance - $transfer->transfer_charges;

            $chargesTransaction = new BankTransaction();
            $chargesTransaction->bank_account_id = $transfer->from_account_id;
            $chargesTransaction->transaction_date = $transfer->transfer_date;
            $chargesTransaction->transaction_type = 'debit';
            $chargesTransaction->reference_number = $transfer->transfer_number . '-CHARGES';
            $chargesTransaction->description = 'Transfer charges for ' . $transfer->transfer_number . ' (Transfer to ' . $transfer->toAccount->account_name . ')';
            $chargesTransaction->module_tag = AccountingTags::MODULE_TRANSFER;
            $chargesTransaction->entry_tag = AccountingTags::ENTRY_BANK_FEE;
            $chargesTransaction->amount = $transfer->transfer_charges;
            $chargesTransaction->running_balance = $chargesRunningBalance;
            $chargesTransaction->transaction_status = 'cleared';
            $chargesTransaction->reconciliation_status = 'unreconciled';
            $chargesTransaction->created_by = creatorId();
            $chargesTransaction->save();
        }
    }

    public function updateBankBalance($bankAccountId, $amount) {
        $bankAccount = BankAccount::find($bankAccountId);
        $bankAccount->current_balance += $amount;
        $bankAccount->save();

        // Update running balance for latest transaction
        $latestTransaction = BankTransaction::where('bank_account_id', $bankAccountId)
                            ->latest()
                            ->first();

        if ($latestTransaction) {
            $latestTransaction->running_balance = $bankAccount->current_balance;
            $latestTransaction->save();
        }
    }
    public function createRetainerPayment($retainerPayment)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $retainerPayment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $retainerPayment->payment_amount : $retainerPayment->payment_amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $retainerPayment->bank_account_id;
        $bankTransaction->transaction_date = $retainerPayment->payment_date;
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $retainerPayment->payment_number;
        $bankTransaction->description = 'Retainer Payment #' . $retainerPayment->payment_number . ' - ' . $retainerPayment->customer->name;
        $bankTransaction->amount = $retainerPayment->payment_amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        // Update bank account balance
        $this->updateBankBalance($retainerPayment->bank_account_id, $retainerPayment->payment_amount);
    }

    public function createRevenuePayment($revenue)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $revenue->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $revenue->amount : $revenue->amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $revenue->bank_account_id;
        $bankTransaction->transaction_date = $revenue->revenue_date;
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $revenue->revenue_number;
        $bankTransaction->description = 'Revenue Posted: ' . ($revenue->description ?? 'Revenue transaction');
        $bankTransaction->module_tag = $revenue->module_tag ?: AccountingTags::MODULE_OTHER;
        $bankTransaction->entry_tag = $revenue->entry_tag;
        $bankTransaction->amount = $revenue->amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        // Update bank account balance
        $this->updateBankBalance($revenue->bank_account_id, $revenue->amount);
    }

    public function createExpensePayment($expense)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $expense->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance - $expense->amount : -$expense->amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $expense->bank_account_id;
        $bankTransaction->transaction_date = $expense->expense_date;
        $bankTransaction->transaction_type = 'debit';
        $bankTransaction->reference_number = $expense->expense_number;
        $bankTransaction->description = 'Expense Posted: ' . ($expense->description ?? 'Expense transaction');
        $bankTransaction->module_tag = $expense->module_tag ?: AccountingTags::MODULE_OTHER;
        $bankTransaction->entry_tag = $expense->entry_tag;
        $bankTransaction->amount = $expense->amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        // Update bank account balance (negative amount to decrease balance)
        $this->updateBankBalance($expense->bank_account_id, -$expense->amount);
    }
    public function createCommissionPayment($commissionPayment)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $commissionPayment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance - $commissionPayment->payment_amount : -$commissionPayment->payment_amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $commissionPayment->bank_account_id;
        $bankTransaction->transaction_date = $commissionPayment->payment_date;
        $bankTransaction->transaction_type = 'debit';
        $bankTransaction->reference_number = $commissionPayment->payment_number;
        $bankTransaction->description = 'Commission Payment #' . $commissionPayment->payment_number . ' - ' . $commissionPayment->agent->name;
        $bankTransaction->amount = $commissionPayment->payment_amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        // Update bank account balance (negative amount to decrease balance)
        $this->updateBankBalance($commissionPayment->bank_account_id, -$commissionPayment->payment_amount);
    }

    public function createPayrollPayment($payrollEntry)
    {
        $bankAccountId = $payrollEntry->payroll->bank_account_id;
        $lastTransaction = BankTransaction::where('bank_account_id', $bankAccountId)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance - $payrollEntry->net_pay : -$payrollEntry->net_pay;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $bankAccountId;
        $bankTransaction->transaction_date = now();
        $bankTransaction->transaction_type = 'debit';
        $bankTransaction->reference_number = 'PAYROLL-' . $payrollEntry->id;
        $bankTransaction->description = 'Salary Payment - ' . $payrollEntry->employee->user->name;
        $bankTransaction->amount = $payrollEntry->net_pay;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        $this->updateBankBalance($bankAccountId, -$payrollEntry->net_pay);
    }

    public function createPosPayment($posSale, $bankAccountId)
    {
        $posSale->load('payment');
        $amount = $posSale->payment->discount_amount ?? 0;

        $lastTransaction = BankTransaction::where('bank_account_id', $bankAccountId)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $amount : $amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $bankAccountId;
        $bankTransaction->transaction_date = $posSale->pos_date ?? now();
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $posSale->sale_number;
        $bankTransaction->description = 'POS Sale ' . $posSale->sale_number;
        $bankTransaction->amount = $amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        $this->updateBankBalance($bankAccountId, $amount);
    }

    public function createMobileServicePayment($payment)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $payment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
         $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $payment->payment_amount : $payment->payment_amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $payment->bank_account_id;
        $bankTransaction->transaction_date = $payment->payment_date;
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $payment->payment_number;
        $bankTransaction->description = 'Mobile Service Payment: ' . ($payment->description ?? 'Mobile service transaction');
        $bankTransaction->amount = $payment->payment_amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        // Update bank account balance
        $this->updateBankBalance($payment->bank_account_id, $payment->payment_amount);
    }

    public function createMarkFleetBookingPayment($payment)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $payment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $payment->payment_amount : $payment->payment_amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $payment->bank_account_id;
        $bankTransaction->transaction_date = $payment->payment_date;
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $payment->payment_number;
        $bankTransaction->description = 'Fleet Booking Payment: ' . ($payment->description ?? 'Fleet booking transaction');
        $bankTransaction->amount = $payment->payment_amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        // Update bank account balance
        $this->updateBankBalance($payment->bank_account_id, $payment->payment_amount);
    }

    public function createBeautyBookingPayment($booking)
    {
        // Find bank account by payment gateway
        $bankAccount = BankAccount::where('payment_gateway', $booking->payment_option)->where('created_by', $booking->created_by)
            ->first();
        if (!$bankAccount) {
            throw new \Exception('Bank account not found for payment gateway: ' . $booking->payment_option);
        }

        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $bankAccount->id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $booking->price : $booking->price;
        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $bankAccount->id;
        $bankTransaction->transaction_date = $booking->date ?? now();
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $booking->payment_number ?? 'BEAUTY-' . $booking->id;
        $bankTransaction->description = 'Beauty Booking Payment via ' . $booking->payment_option;
        $bankTransaction->amount = $booking->price;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = $booking->created_by;
        $bankTransaction->save();

        // Update bank account balance
        $this->updateBankBalance($bankAccount->id, $booking->price);
    }

    public function createDairyCattlePayment($dairyCattlePayment)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $dairyCattlePayment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $dairyCattlePayment->payment_amount : $dairyCattlePayment->payment_amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $dairyCattlePayment->bank_account_id;
        $bankTransaction->transaction_date = $dairyCattlePayment->payment_date;
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $dairyCattlePayment->payment_number;
        $bankTransaction->description = 'Dairy Cattle Payment: ' . ($dairyCattlePayment->description ?? 'Dairy cattle transaction');
        $bankTransaction->amount = $dairyCattlePayment->payment_amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        // Update bank account balance
        $this->updateBankBalance($dairyCattlePayment->bank_account_id, $dairyCattlePayment->payment_amount);
    }

    public function createCateringOrderPayment($payment)
    {
        // Get current running balance for the bank account
        $lastTransaction = BankTransaction::where('bank_account_id', $payment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $payment->amount : $payment->amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $payment->bank_account_id;
        $bankTransaction->transaction_date = $payment->payment_date;
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $payment->reference_number;
        $bankTransaction->description = 'Catering Order Payment #' . $payment->id;
        $bankTransaction->amount = $payment->amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        // Update bank account balance
        $this->updateBankBalance($payment->bank_account_id, $payment->amount);
    }

    public function createUpdateSalesAgentCommissionPayment($payment)
    {
        $lastTransaction = BankTransaction::where('bank_account_id', $payment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance - $payment->payment_amount : -$payment->payment_amount;

        $agentName = $payment->agent && $payment->agent->user ? $payment->agent->user->name : 'Agent';

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $payment->bank_account_id;
        $bankTransaction->transaction_date = $payment->payment_date;
        $bankTransaction->transaction_type = 'debit';
        $bankTransaction->reference_number = $payment->payment_number;
        $bankTransaction->description = 'Commission Payment #' . $payment->payment_number . ' - ' . $agentName;
        $bankTransaction->amount = $payment->payment_amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        $this->updateBankBalance($payment->bank_account_id, -$payment->payment_amount);
    }

    public function createCommissionAdjustmentBankTransaction($adjustment)
    {
        // Only create bank transaction if adjustment has bank_account_id
        if (!isset($adjustment->bank_account_id) || !$adjustment->bank_account_id) {
            return;
        }
        $lastTransaction = BankTransaction::where('bank_account_id', $adjustment->bank_account_id)
            ->orderBy('id', 'desc')
            ->first();
        $agentName = $adjustment->agent && $adjustment->agent->user ? $adjustment->agent->user->name : 'Agent';
        $amount = abs($adjustment->adjustment_amount);

        // Bonus/Correction(+) = Debit (cash out to agent)
        // Penalty/Correction(-) = Credit (cash in from agent)
        if ($adjustment->adjustment_type === 'bonus' || ($adjustment->adjustment_type === 'correction' && $adjustment->adjustment_amount > 0)) {
            $transactionType = 'debit';
            $runningBalance = $lastTransaction ? $lastTransaction->running_balance - $amount : -$amount;
            $balanceChange = -$amount;
        } else {
            $transactionType = 'credit';
            $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $amount : $amount;
            $balanceChange = $amount;
        }

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $adjustment->bank_account_id;
        $bankTransaction->transaction_date = $adjustment->adjustment_date;
        $bankTransaction->transaction_type = $transactionType;
        $bankTransaction->reference_number = 'ADJ-' . $adjustment->id;
        $bankTransaction->description = 'Commission Adjustment (' . ucfirst($adjustment->adjustment_type) . ') - ' . $agentName;
        $bankTransaction->amount = $amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = creatorId();
        $bankTransaction->save();

        $this->updateBankBalance($adjustment->bank_account_id, $balanceChange);
    }

    public function createLoanDisbursementTransaction($bankAccountId, $amount, $referenceNumber, $description, $date = null, ?string $moduleTag = null, ?string $entryTag = null)
    {
        return $this->createChitPayoutTransaction(
            $bankAccountId,
            $amount,
            $referenceNumber,
            $description,
            $date,
            $moduleTag ?? AccountingTags::MODULE_LOAN,
            $entryTag ?? AccountingTags::ENTRY_DISBURSEMENT
        );
    }

    /**
     * Debit company bank for NEFT/IMPS/transfer charges (internal tracking; not client deduction).
     */
    public function createBankTransferChargesDebit(
        $bankAccountId,
        $amount,
        $referenceNumber,
        $description,
        $date = null,
        ?string $moduleTag = null
    ) {
        return $this->createChitPayoutTransaction(
            $bankAccountId,
            $amount,
            $referenceNumber,
            $description,
            $date,
            $moduleTag ?? AccountingTags::MODULE_OTHER,
            AccountingTags::ENTRY_BANK_FEE
        );
    }

    public function createLoanCollectionTransaction($bankAccountId, $amount, $referenceNumber, $description, $date = null, ?string $moduleTag = null, ?string $entryTag = null)
    {
        return $this->createChitCollectionTransaction(
            $bankAccountId,
            $amount,
            $referenceNumber,
            $description,
            $date,
            $moduleTag ?? AccountingTags::MODULE_LOAN,
            $entryTag ?? AccountingTags::ENTRY_EMI
        );
    }

    public function createFdDepositTransaction($bankAccountId, $amount, $referenceNumber, $description, $date = null, ?string $moduleTag = null, ?string $entryTag = null)
    {
        return $this->createChitCollectionTransaction(
            $bankAccountId,
            $amount,
            $referenceNumber,
            $description,
            $date,
            $moduleTag ?? AccountingTags::MODULE_FD,
            $entryTag ?? AccountingTags::ENTRY_DEPOSIT
        );
    }

    public function createFdPayoutTransaction($bankAccountId, $amount, $referenceNumber, $description, $date = null, ?string $moduleTag = null, ?string $entryTag = null)
    {
        return $this->createChitPayoutTransaction(
            $bankAccountId,
            $amount,
            $referenceNumber,
            $description,
            $date,
            $moduleTag ?? AccountingTags::MODULE_FD,
            $entryTag ?? AccountingTags::ENTRY_PAYOUT
        );
    }

    /**
     * Credit company bank/cash for a chit installment collection.
     */
    public function createChitCollectionTransaction($bankAccountId, $amount, $referenceNumber, $description, $date = null, ?string $moduleTag = null, ?string $entryTag = null)
    {
        $date = $date ?: now();
        $lastTransaction = BankTransaction::where('bank_account_id', $bankAccountId)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $amount : $amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $bankAccountId;
        $bankTransaction->transaction_date = $date;
        $bankTransaction->transaction_type = 'credit';
        $bankTransaction->reference_number = $referenceNumber;
        $bankTransaction->description = $description;
        $bankTransaction->module_tag = $moduleTag ?? AccountingTags::MODULE_CHIT;
        $bankTransaction->entry_tag = $entryTag ?? AccountingTags::ENTRY_INSTALLMENT;
        $bankTransaction->amount = $amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = Auth::id() ?: (function_exists('creatorId') ? creatorId() : 1);
        $bankTransaction->save();

        $this->updateBankBalance($bankAccountId, $amount);

        return $bankTransaction;
    }

    /**
     * Debit company bank/cash for a chit settlement payout.
     */
    public function createChitPayoutTransaction($bankAccountId, $amount, $referenceNumber, $description, $date = null, ?string $moduleTag = null, ?string $entryTag = null)
    {
        $date = $date ?: now();
        $lastTransaction = BankTransaction::where('bank_account_id', $bankAccountId)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance - $amount : -$amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $bankAccountId;
        $bankTransaction->transaction_date = $date;
        $bankTransaction->transaction_type = 'debit';
        $bankTransaction->reference_number = $referenceNumber;
        $bankTransaction->description = $description;
        $bankTransaction->module_tag = $moduleTag ?? AccountingTags::MODULE_CHIT;
        $bankTransaction->entry_tag = $entryTag ?? AccountingTags::ENTRY_SETTLEMENT;
        $bankTransaction->amount = $amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = Auth::id() ?: (function_exists('creatorId') ? creatorId() : 1);
        $bankTransaction->save();

        $this->updateBankBalance($bankAccountId, -$amount);

        return $bankTransaction;
    }

    public function createLoanReversalTransaction($bankAccountId, $amount, $referenceNumber, $description, $date = null, ?string $moduleTag = null, ?string $entryTag = null)
    {
        $date = $date ?: now();
        $lastTransaction = BankTransaction::where('bank_account_id', $bankAccountId)
            ->orderBy('id', 'desc')
            ->first();
        $runningBalance = $lastTransaction ? $lastTransaction->running_balance - $amount : -$amount;

        $bankTransaction = new BankTransaction();
        $bankTransaction->bank_account_id = $bankAccountId;
        $bankTransaction->transaction_date = $date;
        $bankTransaction->transaction_type = 'debit';
        $bankTransaction->reference_number = $referenceNumber;
        $bankTransaction->description = $description;
        $bankTransaction->module_tag = $moduleTag ?? AccountingTags::MODULE_LOAN;
        $bankTransaction->entry_tag = $entryTag ?? AccountingTags::ENTRY_REVERSAL;
        $bankTransaction->amount = $amount;
        $bankTransaction->running_balance = $runningBalance;
        $bankTransaction->transaction_status = 'cleared';
        $bankTransaction->reconciliation_status = 'unreconciled';
        $bankTransaction->created_by = Auth::id() ?: (function_exists('creatorId') ? creatorId() : 1);
        $bankTransaction->save();

        $this->updateBankBalance($bankAccountId, -$amount);

        return $bankTransaction;
    }

    /**
     * Remove (or partially reduce) an original collection credit from the cashbook.
     * Used when EMI / chit payments are undone so Bank Transactions no longer show them.
     *
     * Handles cascade payments: one bank credit for multiple EMIs/months can be reduced
     * when only the latest installment is undone.
     *
     * Pass bankAccountId = 0 to discover the real account from matching credits
     * (prevents Bank Transfer undos from incorrectly targeting Cash in Hand).
     *
     * @param  array{
     *   references?: list<string|null>,
     *   description_contains?: list<string>,
     *   identity_contains?: list<string>,
     *   emi_numbers?: list<int>,
     *   month_numbers?: list<int>,
     *   module_tag?: string|null,
     *   entry_tags?: list<string>,
     *   allow_partial_reduce?: bool
     * }  $options
     */
    public function removeCollectionTransactions(int $bankAccountId, float $amount, array $options = []): int
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0.009) {
            return 0;
        }

        $references = collect($options['references'] ?? [])
            ->filter(fn ($r) => is_string($r) && trim($r) !== '')
            ->map(fn ($r) => trim($r))
            ->unique()
            ->values()
            ->all();

        $needles = collect($options['description_contains'] ?? [])
            ->filter(fn ($n) => is_string($n) && trim($n) !== '')
            ->map(fn ($n) => trim($n))
            ->values()
            ->all();

        $identities = collect($options['identity_contains'] ?? [])
            ->filter(fn ($n) => is_string($n) && trim($n) !== '')
            ->map(fn ($n) => trim($n))
            ->values()
            ->all();

        // Back-compat: treat description_contains as identity keys when identity not given.
        if ($identities === [] && $needles !== []) {
            $identities = $needles;
        }

        $emiNumbers = collect($options['emi_numbers'] ?? [])
            ->map(fn ($n) => (int) $n)
            ->filter(fn ($n) => $n > 0)
            ->unique()
            ->values()
            ->all();

        $monthNumbers = collect($options['month_numbers'] ?? [])
            ->map(fn ($n) => (int) $n)
            ->filter(fn ($n) => $n > 0)
            ->unique()
            ->values()
            ->all();

        $moduleTag = $options['module_tag'] ?? null;
        $entryTags = $options['entry_tags'] ?? [];
        $allowPartialReduce = array_key_exists('allow_partial_reduce', $options)
            ? (bool) $options['allow_partial_reduce']
            : true;

        $scopedId = $bankAccountId > 0 ? $bankAccountId : null;

        $buildBaseQuery = function (?int $scopedBankId, bool $exactAmount) use ($amount, $moduleTag, $entryTags) {
            $creditQuery = BankTransaction::query()
                ->where('transaction_type', 'credit')
                ->where(function ($q) {
                    $q->whereNull('description')
                        ->orWhere('description', 'not like', '%Rev%');
                });

            if ($exactAmount) {
                $creditQuery->whereRaw('ABS(amount - ?) < 0.02', [$amount]);
            } else {
                // Cascade credits: larger than the undone slice.
                $creditQuery->where('amount', '>', $amount - 0.02);
            }

            if ($scopedBankId && $scopedBankId > 0) {
                $creditQuery->where('bank_account_id', $scopedBankId);
            }

            if ($moduleTag) {
                $creditQuery->where(function ($q) use ($moduleTag) {
                    $q->where('module_tag', $moduleTag)->orWhereNull('module_tag');
                });
            }

            if ($entryTags) {
                $creditQuery->where(function ($q) use ($entryTags) {
                    $q->whereIn('entry_tag', $entryTags)->orWhereNull('entry_tag');
                });
            }

            return $creditQuery;
        };

        $applyMatchKeys = function ($query) use ($references, $identities) {
            if ($references === [] && $identities === []) {
                return $query;
            }

            return $query->where(function ($q) use ($references, $identities) {
                foreach ($references as $ref) {
                    $q->orWhere('reference_number', $ref)
                        ->orWhere('reference_number', 'like', $ref . '-%')
                        ->orWhere('reference_number', 'like', $ref . '%');
                }
                // OR across identity keys (account / group) — do not require every needle.
                foreach ($identities as $identity) {
                    $q->orWhere('description', 'like', '%' . $identity . '%')
                        ->orWhere('reference_number', 'like', '%' . $identity . '%');
                }
            });
        };

        $candidates = collect();

        foreach ([true, false] as $exactAmount) {
            if (! $exactAmount && ! $allowPartialReduce) {
                continue;
            }

            $q = $applyMatchKeys($buildBaseQuery($scopedId, $exactAmount));
            $batch = $q->orderByDesc('id')->limit(30)->get();

            if ($batch->isEmpty() && $scopedId) {
                $batch = $applyMatchKeys($buildBaseQuery(null, $exactAmount))
                    ->orderByDesc('id')
                    ->limit(30)
                    ->get();
            }

            if ($batch->isEmpty() && ($references !== [] || $identities !== [])) {
                // Loose IC fallback (still keyed by ref/identity).
                $fallback = BankTransaction::query()
                    ->where('transaction_type', 'credit')
                    ->where(function ($dq) {
                        $dq->where('description', 'like', 'Loan IC%')
                            ->orWhere('description', 'like', 'Chit IC%')
                            ->orWhere('description', 'like', 'Chit installment collection%')
                            ->orWhereIn('entry_tag', [
                                AccountingTags::ENTRY_EMI,
                                AccountingTags::ENTRY_INSTALLMENT,
                            ]);
                    })
                    ->where('description', 'not like', '%Rev%');

                if ($exactAmount) {
                    $fallback->whereRaw('ABS(amount - ?) < 0.02', [$amount]);
                } else {
                    $fallback->where('amount', '>', $amount - 0.02);
                }

                $batch = $applyMatchKeys($fallback)->orderByDesc('id')->limit(20)->get();
            }

            $candidates = $candidates->merge($batch);
            if ($exactAmount && $candidates->isNotEmpty()) {
                break;
            }
        }

        $candidates = $candidates->unique('id')->values();

        if ($candidates->isEmpty()) {
            return 0;
        }

        // Prefer credits that mention the specific EMI / month being undone.
        $ranked = $candidates->sortByDesc(function ($credit) use ($amount, $emiNumbers, $monthNumbers) {
            $score = 0;
            $creditAmount = round((float) $credit->amount, 2);
            $desc = (string) ($credit->description ?? '');

            if (abs($creditAmount - $amount) < 0.02) {
                $score += 100;
            }

            foreach ($emiNumbers as $emiNo) {
                if ($this->descriptionMentionsEmi($desc, $emiNo)) {
                    $score += 40;
                }
            }
            foreach ($monthNumbers as $monthNo) {
                if ($this->descriptionMentionsMonth($desc, $monthNo)) {
                    $score += 40;
                }
            }

            $score += min(20, (int) $credit->id % 20);

            return $score;
        })->values();

        $deletedIds = [];
        $refsToClear = $references;
        $remaining = $amount;
        $affectedAccounts = [];

        foreach ($ranked as $credit) {
            if ($remaining <= 0.009) {
                break;
            }

            $creditAmount = round((float) $credit->amount, 2);
            $desc = (string) ($credit->description ?? '');

            // Soft-filter: if EMI/month hints exist, skip unrelated cascade rows.
            if ($emiNumbers !== [] && ! $this->descriptionMentionsAnyEmi($desc, $emiNumbers)
                && abs($creditAmount - $remaining) > 0.02
                && abs($creditAmount - $amount) > 0.02) {
                // Still allow exact-ish amount matches without EMI text (legacy rows).
                continue;
            }
            if ($monthNumbers !== [] && ! $this->descriptionMentionsAnyMonth($desc, $monthNumbers)
                && abs($creditAmount - $remaining) > 0.02
                && abs($creditAmount - $amount) > 0.02) {
                continue;
            }

            $affectedAccounts[(int) $credit->bank_account_id] = true;
            if ($credit->reference_number) {
                $refsToClear[] = (string) $credit->reference_number;
            }

            if ($creditAmount <= ($remaining + 0.02)) {
                $deletedIds[] = $credit->id;
                $this->deleteMatchingRevenueForCredit($credit);
                $remaining = round($remaining - $creditAmount, 2);
                $credit->delete();
                continue;
            }

            if (! $allowPartialReduce) {
                continue;
            }

            // Cascade credit: shrink it by the undone slice.
            $newAmount = round($creditAmount - $remaining, 2);
            if ($newAmount <= 0.009) {
                $deletedIds[] = $credit->id;
                $this->deleteMatchingRevenueForCredit($credit);
                $credit->delete();
            } else {
                $this->reduceMatchingRevenueForCredit($credit, $remaining);
                $credit->amount = $newAmount;
                $credit->save();
                $deletedIds[] = $credit->id; // count as touched
            }
            $remaining = 0.0;
        }

        $refsToClear = collect($refsToClear)->filter()->unique()->values()->all();
        $accountIdsForReversal = array_keys($affectedAccounts);
        if ($scopedId && ! in_array($scopedId, $accountIdsForReversal, true)) {
            $accountIdsForReversal[] = $scopedId;
        }

        foreach ($accountIdsForReversal as $accountId) {
            if ($accountId <= 0) {
                continue;
            }

            $reversalQuery = BankTransaction::query()
                ->where('bank_account_id', $accountId)
                ->where('transaction_type', 'debit')
                ->where(function ($q) use ($amount, $refsToClear, $identities) {
                    $q->where(function ($rq) use ($amount) {
                        $rq->whereRaw('ABS(amount - ?) < 0.02', [$amount])
                            ->where(function ($tq) {
                                $tq->where('entry_tag', AccountingTags::ENTRY_REVERSAL)
                                    ->orWhere('description', 'like', 'Loan IC Rev%')
                                    ->orWhere('description', 'like', 'Chit IC Rev%')
                                    ->orWhere('reference_number', 'like', 'REV-%')
                                    ->orWhere('reference_number', 'like', 'UNDO-%');
                            });
                    });

                    foreach ($refsToClear as $ref) {
                        $q->orWhere('reference_number', 'REV-' . $ref)
                            ->orWhere('reference_number', 'UNDO-' . $ref)
                            ->orWhere('reference_number', $ref);
                    }

                    foreach ($identities as $identity) {
                        $q->orWhere(function ($dq) use ($identity, $amount) {
                            $dq->whereRaw('ABS(amount - ?) < 0.02', [$amount])
                                ->where(function ($d) {
                                    $d->where('description', 'like', 'Loan IC Rev%')
                                        ->orWhere('description', 'like', 'Chit IC Rev%');
                                })
                                ->where('description', 'like', '%' . $identity . '%');
                        });
                    }
                });

            foreach ($reversalQuery->orderByDesc('id')->limit(5)->get() as $reversal) {
                $deletedIds[] = $reversal->id;
                $affectedAccounts[(int) $reversal->bank_account_id] = true;
                $reversal->delete();
            }
        }

        foreach (array_keys($affectedAccounts) as $accountId) {
            $this->recalculateRunningBalances((int) $accountId);
        }

        return count(array_unique($deletedIds));
    }

    protected function descriptionMentionsAnyEmi(string $description, array $emiNumbers): bool
    {
        foreach ($emiNumbers as $emiNo) {
            if ($this->descriptionMentionsEmi($description, (int) $emiNo)) {
                return true;
            }
        }

        return false;
    }

    protected function descriptionMentionsAnyMonth(string $description, array $monthNumbers): bool
    {
        foreach ($monthNumbers as $monthNo) {
            if ($this->descriptionMentionsMonth($description, (int) $monthNo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when description mentions EMI #N, including ranges like "EMI #3–5".
     */
    protected function descriptionMentionsEmi(string $description, int $emiNumber): bool
    {
        if ($emiNumber < 1 || $description === '') {
            return false;
        }

        if (preg_match('/EMI\s*#\s*' . $emiNumber . '(?!\d)/i', $description)) {
            return true;
        }

        if (! preg_match('/EMI\s*#\s*([0-9,\s–\-]+)/iu', $description, $m)) {
            return false;
        }

        return in_array($emiNumber, $this->expandNumberListToken($m[1]), true);
    }

    /**
     * True when description mentions MN, including ranges like "M3–5".
     */
    protected function descriptionMentionsMonth(string $description, int $monthNumber): bool
    {
        if ($monthNumber < 1 || $description === '') {
            return false;
        }

        if (preg_match('/(?<![A-Za-z0-9])M\s*' . $monthNumber . '(?!\d)/i', $description)) {
            return true;
        }

        if (! preg_match('/(?<![A-Za-z0-9])M\s*([0-9,\s–\-]+)/iu', $description, $m)) {
            return false;
        }

        return in_array($monthNumber, $this->expandNumberListToken($m[1]), true);
    }

    /**
     * Expand "1–3, 5, 7-8" into [1,2,3,5,7,8].
     *
     * @return list<int>
     */
    protected function expandNumberListToken(string $token): array
    {
        $out = [];
        $parts = preg_split('/\s*,\s*/', trim($token)) ?: [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d+)\s*[–\-]\s*(\d+)$/u', $part, $rm)) {
                $start = (int) $rm[1];
                $end = (int) $rm[2];
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }
                for ($i = $start; $i <= $end; $i++) {
                    $out[] = $i;
                }
                continue;
            }
            if (ctype_digit($part)) {
                $out[] = (int) $part;
            }
        }

        return array_values(array_unique($out));
    }

    protected function deleteMatchingRevenueForCredit(BankTransaction $credit): void
    {
        $ref = trim((string) ($credit->reference_number ?? ''));
        $amount = round((float) $credit->amount, 2);

        $query = \App\Models\Account\Revenue::query()
            ->whereRaw('ABS(amount - ?) < 0.02', [$amount]);

        if ($ref !== '') {
            $query->where(function ($q) use ($ref) {
                $q->where('reference_number', $ref)
                    ->orWhere('reference_number', 'UNDO-' . $ref)
                    ->orWhere('reference_number', 'like', $ref . '%');
            });
        } elseif (! empty($credit->description)) {
            $query->where('description', $credit->description);
        } else {
            return;
        }

        $query->orderByDesc('id')->limit(3)->get()->each->delete();
    }

    protected function reduceMatchingRevenueForCredit(BankTransaction $credit, float $reduceBy): void
    {
        $reduceBy = round(max(0, $reduceBy), 2);
        if ($reduceBy <= 0.009) {
            return;
        }

        $ref = trim((string) ($credit->reference_number ?? ''));
        $amount = round((float) $credit->amount, 2);

        $query = \App\Models\Account\Revenue::query()
            ->whereRaw('ABS(amount - ?) < 0.02', [$amount]);

        if ($ref !== '') {
            $query->where(function ($q) use ($ref) {
                $q->where('reference_number', $ref)
                    ->orWhere('reference_number', 'like', $ref . '%');
            });
        } elseif (! empty($credit->description)) {
            $query->where('description', $credit->description);
        } else {
            return;
        }

        $revenue = $query->orderByDesc('id')->first();
        if (! $revenue) {
            return;
        }

        $newAmount = round((float) $revenue->amount - $reduceBy, 2);
        if ($newAmount <= 0.009) {
            $revenue->delete();
        } else {
            $revenue->amount = $newAmount;
            $revenue->save();
        }
    }

    /**
     * Delete historical undo artifacts: REV/UNDO / "IC Rev" rows and their matching original credits.
     * Also removes CHIT-COLL credits whose installment is unpaid / missing (left behind by older undos).
     */
    public function cleanupUndoneCollectionArtifacts(): array
    {
        $reversals = BankTransaction::query()
            ->where(function ($q) {
                $q->where('entry_tag', AccountingTags::ENTRY_REVERSAL)
                    ->orWhere('description', 'like', 'Loan IC Rev%')
                    ->orWhere('description', 'like', 'Chit IC Rev%')
                    ->orWhere('reference_number', 'like', 'REV-%')
                    ->orWhere('reference_number', 'like', 'UNDO-%');
            })
            ->orderBy('id')
            ->get();

        $removed = 0;
        $affectedAccounts = [];

        foreach ($reversals as $reversal) {
            $bankAccountId = (int) $reversal->bank_account_id;
            $affectedAccounts[$bankAccountId] = true;
            $amount = round((float) $reversal->amount, 2);
            $baseRef = preg_replace('/^(REV-|UNDO-)/i', '', (string) $reversal->reference_number);

            $originalQuery = BankTransaction::query()
                ->where('bank_account_id', $bankAccountId)
                ->where('transaction_type', 'credit')
                ->where('amount', $amount)
                ->where('id', '<', $reversal->id)
                ->where(function ($q) use ($baseRef) {
                    if ($baseRef !== '') {
                        $q->where('reference_number', $baseRef)
                            ->orWhere('reference_number', 'like', $baseRef . '-%');
                    }
                    $q->orWhere(function ($dq) {
                        $dq->where(function ($d) {
                            $d->where('description', 'like', 'Loan IC%')
                                ->orWhere('description', 'like', 'Chit IC%')
                                ->orWhere('description', 'like', 'Chit installment collection%')
                                ->orWhereIn('entry_tag', [
                                    AccountingTags::ENTRY_EMI,
                                    AccountingTags::ENTRY_INSTALLMENT,
                                ]);
                        })->where('description', 'not like', '%Rev%');
                    });
                })
                ->orderByDesc('id');

            $original = $originalQuery->first();
            if ($original) {
                $original->delete();
                $removed++;
            }

            $reversal->delete();
            $removed++;
        }

        // Orphan installment credits left after older undos that never posted a reversal row.
        $orphanCredits = BankTransaction::query()
            ->where('transaction_type', 'credit')
            ->where('module_tag', AccountingTags::MODULE_CHIT)
            ->where('entry_tag', AccountingTags::ENTRY_INSTALLMENT)
            ->where('reference_number', 'like', 'CHIT-COLL-%')
            ->get();

        foreach ($orphanCredits as $credit) {
            if (! preg_match('/CHIT-COLL-(\d+)/', (string) $credit->reference_number, $m)) {
                continue;
            }

            $installment = \App\Models\Installment::query()->find((int) $m[1]);
            $isOrphan = ! $installment
                || (float) $installment->paid_amount <= 0.009
                || in_array((string) $installment->status, ['pending', 'overdue'], true);

            if (! $isOrphan) {
                continue;
            }

            $affectedAccounts[(int) $credit->bank_account_id] = true;
            $reference = (string) $credit->reference_number;
            $credit->delete();
            $removed++;

            if ($reference !== '') {
                \App\Models\Account\Revenue::query()
                    ->where('reference_number', $reference)
                    ->delete();
            }
        }

        foreach (array_keys($affectedAccounts) as $accountId) {
            $this->recalculateRunningBalances((int) $accountId);
        }

        return [
            'removed' => $removed,
            'accounts' => count($affectedAccounts),
        ];
    }

    /**
     * Rebuild running balances and current balance after transaction removals.
     */
    public function recalculateRunningBalances(int $bankAccountId): void
    {
        $bankAccount = BankAccount::find($bankAccountId);
        if (! $bankAccount) {
            return;
        }

        $running = 0.0;

        $transactions = BankTransaction::where('bank_account_id', $bankAccountId)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;

            if ($transaction->transaction_type === 'credit') {
                $running += $amount;
            } else {
                $running -= $amount;
            }

            $rounded = round($running, 2);

            if ((float) $transaction->running_balance !== $rounded) {
                $transaction->running_balance = $rounded;
                $transaction->saveQuietly();
            }
        }

        $bankAccount->current_balance = round($running, 2);
        $bankAccount->saveQuietly();
    }
}
