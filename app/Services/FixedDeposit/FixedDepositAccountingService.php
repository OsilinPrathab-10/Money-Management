<?php

namespace App\Services\FixedDeposit;

use App\Models\Account\ChartOfAccount;
use App\Models\Account\JournalEntry;
use App\Models\Account\JournalEntryItem;
use App\Models\FixedDeposit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts FD-related journals when Chart of Accounts is available.
 * Uses Customer Deposits (2350), Cash (1000), Bank (1200), Interest Expense (5500).
 */
class FixedDepositAccountingService
{
    public function reverseCreation(FixedDeposit $fd): void
    {
        JournalEntry::where('reference_type', 'fixed_deposit')
            ->where('reference_id', $fd->id)
            ->get()
            ->each(function (JournalEntry $journal) {
                $journal->items()->delete();
                $journal->delete();
            });
    }

    public function postCreation(FixedDeposit $fd): ?JournalEntry
    {
        return $this->safePost(function () use ($fd) {
            $amount = (float) $fd->deposit_amount;

            return $this->createBalancedEntry(
                date: $fd->deposit_date,
                referenceType: 'fixed_deposit',
                referenceId: $fd->id,
                description: 'FD Creation - ' . $fd->fd_number,
                lines: [
                    ['code' => '1000', 'debit' => $amount, 'credit' => 0, 'desc' => 'Cash received for FD'],
                    ['code' => '2350', 'debit' => 0, 'credit' => $amount, 'desc' => 'Fixed Deposit liability'],
                ]
            );
        }, 'FD creation journal');
    }

    public function postMaturityWalletCredit(FixedDeposit $fd, float $principal, float $interest): ?JournalEntry
    {
        return $this->safePost(function () use ($fd, $principal, $interest) {
            $total = $principal + $interest;
            $lines = [
                ['code' => '2350', 'debit' => $principal, 'credit' => 0, 'desc' => 'Release FD principal'],
                ['code' => '2350', 'debit' => 0, 'credit' => $total, 'desc' => 'Customer wallet / deposit liability'],
            ];

            if ($interest > 0) {
                $lines[] = ['code' => '5500', 'debit' => $interest, 'credit' => 0, 'desc' => 'FD interest expense'];
            }

            return $this->createBalancedEntry(
                date: now()->toDateString(),
                referenceType: 'fixed_deposit_maturity',
                referenceId: $fd->id,
                description: 'Fixed Deposit Maturity Credit - ' . $fd->fd_number,
                lines: $lines
            );
        }, 'FD maturity journal');
    }

    public function postBankPayout(FixedDeposit $fd, float $amount, float $interest = 0): ?JournalEntry
    {
        return $this->safePost(function () use ($fd, $amount, $interest) {
            $principal = $amount - $interest;
            $lines = [
                ['code' => '2350', 'debit' => $principal > 0 ? $principal : $amount, 'credit' => 0, 'desc' => 'Release FD liability'],
                ['code' => '1200', 'debit' => 0, 'credit' => $amount, 'desc' => 'Bank transfer payout'],
            ];

            if ($interest > 0) {
                $lines[] = ['code' => '5500', 'debit' => $interest, 'credit' => 0, 'desc' => 'FD interest expense'];
            }

            return $this->createBalancedEntry(
                date: $fd->payment_date?->toDateString() ?? now()->toDateString(),
                referenceType: 'fixed_deposit_bank_payout',
                referenceId: $fd->id,
                description: 'FD Bank Transfer - ' . $fd->fd_number,
                lines: $lines
            );
        }, 'FD bank payout journal');
    }

    public function postPrematureWithdrawal(FixedDeposit $fd, float $payable, float $interest, float $penalty): ?JournalEntry
    {
        return $this->safePost(function () use ($fd, $payable, $interest, $penalty) {
            $principal = (float) $fd->deposit_amount;
            $lines = [
                ['code' => '2350', 'debit' => $principal, 'credit' => 0, 'desc' => 'Release FD principal'],
                ['code' => '2350', 'debit' => 0, 'credit' => $payable, 'desc' => 'Payable to customer'],
            ];

            if ($interest > 0) {
                $lines[] = ['code' => '5500', 'debit' => $interest, 'credit' => 0, 'desc' => 'Eligible FD interest'];
            }

            if ($penalty > 0) {
                $lines[] = ['code' => '4100', 'debit' => 0, 'credit' => $penalty, 'desc' => 'Premature withdrawal penalty income'];
            }

            return $this->createBalancedEntry(
                date: now()->toDateString(),
                referenceType: 'fixed_deposit_premature',
                referenceId: $fd->id,
                description: 'FD Premature Withdrawal - ' . $fd->fd_number,
                lines: $lines
            );
        }, 'FD premature journal');
    }

    public function postMonthlyInterestCredit(FixedDeposit $fd, float $amount): ?JournalEntry
    {
        return $this->safePost(function () use ($fd, $amount) {
            return $this->createBalancedEntry(
                date: now()->toDateString(),
                referenceType: 'fixed_deposit_monthly_interest',
                referenceId: $fd->id,
                description: 'FD Monthly Interest — ' . $fd->fd_number,
                lines: [
                    ['code' => '5500', 'debit' => $amount, 'credit' => 0, 'desc' => 'FD monthly interest expense'],
                    ['code' => '2350', 'debit' => 0, 'credit' => $amount, 'desc' => 'Customer wallet liability'],
                ]
            );
        }, 'FD monthly interest journal');
    }

    public function postClosure(FixedDeposit $fd, float $amount): ?JournalEntry
    {
        return $this->safePost(function () use ($fd, $amount) {
            return $this->createBalancedEntry(
                date: $fd->closure_date?->toDateString() ?? now()->toDateString(),
                referenceType: 'fixed_deposit_closure',
                referenceId: $fd->id,
                description: 'FD Closure - ' . $fd->fd_number,
                lines: [
                    ['code' => '2350', 'debit' => $amount, 'credit' => 0, 'desc' => 'Close FD liability'],
                    ['code' => '1000', 'debit' => 0, 'credit' => $amount, 'desc' => 'Closure payout'],
                ]
            );
        }, 'FD closure journal');
    }

    private function safePost(callable $callback, string $label): ?JournalEntry
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            Log::warning("{$label} skipped: " . $e->getMessage());

            return null;
        }
    }

    private function createBalancedEntry(
        string $date,
        string $referenceType,
        int $referenceId,
        string $description,
        array $lines
    ): JournalEntry {
        $userId = function_exists('creatorId') ? creatorId() : Auth::id();
        $resolved = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $account = ChartOfAccount::where('account_code', $line['code'])
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->first()
                ?? ChartOfAccount::where('account_code', $line['code'])->first();

            if (!$account) {
                throw new \RuntimeException("Account {$line['code']} not found");
            }

            $debit = round((float) $line['debit'], 2);
            $credit = round((float) $line['credit'], 2);
            $totalDebit += $debit;
            $totalCredit += $credit;
            $resolved[] = compact('account', 'debit', 'credit') + ['desc' => $line['desc']];
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \RuntimeException("Unbalanced journal: Dr {$totalDebit} Cr {$totalCredit}");
        }

        $journal = JournalEntry::create([
            'journal_date' => $date,
            'entry_type' => 'automatic',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'status' => 'posted',
            'creator_id' => Auth::id(),
            'created_by' => $userId,
        ]);

        foreach ($resolved as $line) {
            JournalEntryItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $line['account']->id,
                'description' => $line['desc'],
                'debit_amount' => $line['debit'],
                'credit_amount' => $line['credit'],
                'creator_id' => Auth::id(),
                'created_by' => $userId,
            ]);

            if (method_exists($line['account'], 'updateBalance')) {
                // no-op if not present
            }
        }

        return $journal;
    }
}
