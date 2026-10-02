<?php

namespace App\Console\Commands;

use App\Models\Account\BankTransaction;
use App\Models\Account\BankTransfer;
use App\Models\FixedDeposit;
use App\Models\Payout;
use App\Services\Account\AccountingTags;
use App\Services\Account\ChitAccountingService;
use Illuminate\Console\Command;

class BackfillBankChargeExpenses extends Command
{
    protected $signature = 'account:backfill-bank-charge-expenses {--dry-run : Show counts without creating expenses}';

    protected $description = 'Post missing Expense rows for Chit, Loan, FD, and internal bank transfer charges';

    public function handle(ChitAccountingService $accounting): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'Dry run — no expenses will be created.' : 'Backfilling bank charge expenses...');

        $created = 0;

        BankTransaction::query()
            ->where('transaction_type', 'debit')
            ->where(function ($q) {
                $q->where('entry_tag', AccountingTags::ENTRY_BANK_FEE)
                    ->orWhere('reference_number', 'like', '%-CHARGES')
                    ->orWhere('reference_number', 'like', '%-BANKCHG')
                    ->orWhere('description', 'like', 'Bank transfer charges%')
                    ->orWhere('description', 'like', 'Transfer charges for %');
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($accounting, $dry, &$created) {
                foreach ($rows as $row) {
                    $amount = round((float) $row->amount, 2);
                    $reference = (string) $row->reference_number;
                    if ($amount <= 0.009 || $reference === '' || ! $row->bank_account_id) {
                        continue;
                    }

                    $exists = \App\Models\Account\Expense::query()
                        ->where('reference_number', $reference)
                        ->where('entry_tag', AccountingTags::ENTRY_BANK_FEE)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    $created++;
                    if ($dry) {
                        continue;
                    }

                    $accounting->ensureBankChargeExpense(
                        $amount,
                        $reference,
                        optional($row->transaction_date)->toDateString() ?? now()->toDateString(),
                        (int) $row->bank_account_id,
                        $row->module_tag ?: AccountingTags::MODULE_OTHER,
                        (string) ($row->description ?: 'Bank transfer charges')
                    );
                }
            });

        BankTransfer::query()
            ->where('status', 'completed')
            ->where('transfer_charges', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($accounting, $dry, &$created) {
                foreach ($rows as $transfer) {
                    $reference = $transfer->transfer_number . '-CHARGES';
                    $exists = \App\Models\Account\Expense::query()
                        ->where('reference_number', $reference)
                        ->where('entry_tag', AccountingTags::ENTRY_BANK_FEE)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    $created++;
                    if ($dry) {
                        continue;
                    }

                    $accounting->recordInternalTransferCharges($transfer);
                }
            });

        Payout::query()
            ->where('banking_charges', '>', 0)
            ->whereNotNull('internal_bank_account_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($accounting, $dry, &$created) {
                foreach ($rows as $payout) {
                    $code = $payout->payout_code ?: ('PAY-' . $payout->id);
                    $reference = $code . '-BANKCHG';
                    $exists = \App\Models\Account\Expense::query()
                        ->where('reference_number', $reference)
                        ->where('entry_tag', AccountingTags::ENTRY_BANK_FEE)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    $created++;
                    if ($dry) {
                        continue;
                    }

                    $payout->loadMissing(['group', 'winner.client']);
                    $accounting->ensureBankChargeExpense(
                        (float) $payout->banking_charges,
                        $reference,
                        optional($payout->paid_date)->toDateString() ?? now()->toDateString(),
                        (int) $payout->internal_bank_account_id,
                        AccountingTags::MODULE_CHIT,
                        AccountingTags::bankTransferChargesDescription(
                            'Chit settlement',
                            $payout->group?->group_code ?? ('GRP-' . $payout->group_id),
                            $payout->winner?->client?->client_name ?? 'Member',
                            (float) ($payout->net_payout_amount ?? $payout->payout_amount ?? 0),
                            (float) $payout->banking_charges
                        )
                    );
                }
            });

        FixedDeposit::query()
            ->where('banking_charges', '>', 0)
            ->whereNotNull('internal_bank_account_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($accounting, $dry, &$created) {
                foreach ($rows as $fd) {
                    $reference = $fd->fd_number . '-BANKCHG';
                    $exists = \App\Models\Account\Expense::query()
                        ->where('reference_number', $reference)
                        ->where('entry_tag', AccountingTags::ENTRY_BANK_FEE)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    $created++;
                    if ($dry) {
                        continue;
                    }

                    $fd->loadMissing('client');
                    $accounting->ensureBankChargeExpense(
                        (float) $fd->banking_charges,
                        $reference,
                        optional($fd->closure_date)->toDateString()
                            ?? optional($fd->maturity_processed_at)->toDateString()
                            ?? now()->toDateString(),
                        (int) $fd->internal_bank_account_id,
                        AccountingTags::MODULE_FD,
                        AccountingTags::bankTransferChargesDescription(
                            'FD payout',
                            (string) $fd->fd_number,
                            $fd->client?->client_name ?? 'Customer',
                            (float) ($fd->closure_amount ?? $fd->maturity_amount ?? $fd->deposit_amount ?? 0),
                            (float) $fd->banking_charges
                        )
                    );
                }
            });

        $this->info($dry
            ? "Would create {$created} bank charge expense(s)."
            : "Created {$created} bank charge expense(s).");

        return self::SUCCESS;
    }
}
