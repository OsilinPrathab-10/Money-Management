<?php

namespace App\Console\Commands;

use App\Models\Account\BankTransaction;
use App\Models\Account\Expense;
use App\Models\Account\Revenue;
use App\Services\Account\AccountingTags;
use Illuminate\Console\Command;

class BackfillAccountingTags extends Command
{
    protected $signature = 'account:backfill-tags {--dry-run : Show counts without updating}';

    protected $description = 'Best-effort backfill module_tag / entry_tag on bank_transactions, revenues, and expenses from description/category patterns';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'Dry run — no updates will be written.' : 'Backfilling accounting tags...');

        $bankUpdated = $this->backfillBank($dry);
        $revUpdated = $this->backfillRevenues($dry);
        $expUpdated = $this->backfillExpenses($dry);

        $this->info("Bank transactions tagged: {$bankUpdated}");
        $this->info("Revenues tagged: {$revUpdated}");
        $this->info("Expenses tagged: {$expUpdated}");

        return self::SUCCESS;
    }

    protected function backfillBank(bool $dry): int
    {
        $updated = 0;
        BankTransaction::query()
            ->where(function ($q) {
                $q->whereNull('module_tag')->orWhereNull('entry_tag')->orWhere('module_tag', '')->orWhere('entry_tag', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dry, &$updated) {
                foreach ($rows as $row) {
                    [$module, $entry] = $this->inferFromText((string) $row->description, (string) $row->reference_number);
                    if (! $module && ! $entry) {
                        continue;
                    }
                    $updated++;
                    if ($dry) {
                        continue;
                    }
                    $row->module_tag = $row->module_tag ?: $module;
                    $row->entry_tag = $row->entry_tag ?: $entry;
                    $row->save();
                }
            });

        return $updated;
    }

    protected function backfillRevenues(bool $dry): int
    {
        $updated = 0;
        Revenue::query()
            ->with('category:id,category_code,category_name')
            ->where(function ($q) {
                $q->whereNull('module_tag')->orWhereNull('entry_tag')->orWhere('module_tag', '')->orWhere('entry_tag', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dry, &$updated) {
                foreach ($rows as $row) {
                    $code = strtoupper((string) ($row->category?->category_code ?? ''));
                    [$module, $entry] = $this->inferFromCategoryCode($code);
                    if (! $module) {
                        [$module, $entry] = $this->inferFromText(
                            (string) $row->description . ' ' . (string) ($row->category?->category_name ?? ''),
                            (string) $row->reference_number
                        );
                    }
                    if (! $module && ! $entry) {
                        continue;
                    }
                    $updated++;
                    if ($dry) {
                        continue;
                    }
                    $row->module_tag = $row->module_tag ?: $module;
                    $row->entry_tag = $row->entry_tag ?: $entry;
                    $row->save();
                }
            });

        return $updated;
    }

    protected function backfillExpenses(bool $dry): int
    {
        $updated = 0;
        Expense::query()
            ->with('category:id,category_code,category_name')
            ->where(function ($q) {
                $q->whereNull('module_tag')->orWhereNull('entry_tag')->orWhere('module_tag', '')->orWhere('entry_tag', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dry, &$updated) {
                foreach ($rows as $row) {
                    [$module, $entry] = $this->inferFromText(
                        (string) $row->description . ' ' . (string) ($row->category?->category_name ?? ''),
                        (string) $row->reference_number
                    );
                    if (! $module && ! $entry) {
                        continue;
                    }
                    $updated++;
                    if ($dry) {
                        continue;
                    }
                    $row->module_tag = $row->module_tag ?: $module;
                    $row->entry_tag = $row->entry_tag ?: $entry;
                    $row->save();
                }
            });

        return $updated;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    protected function inferFromCategoryCode(string $code): array
    {
        return match (true) {
            str_starts_with($code, 'CHIT-COLL') => [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_INSTALLMENT],
            str_starts_with($code, 'CHIT-PROC') => [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_PROC_FEE],
            str_starts_with($code, 'CHIT-DOC') => [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_DOC_FEE],
            str_starts_with($code, 'CHIT-OTHER') => [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_OTHER_FEE],
            str_starts_with($code, 'CHIT-BANK') => [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_BANK_FEE],
            str_starts_with($code, 'CHIT-FEE') => [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_OTHER_FEE],
            str_starts_with($code, 'CHIT-COMM') => [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_FOREMAN_COMM],
            str_starts_with($code, 'LOAN-PROC') => [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_PROC_FEE],
            str_starts_with($code, 'LOAN-DOC') => [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_DOC_FEE],
            str_starts_with($code, 'LOAN-OTHER') => [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_OTHER_FEE],
            str_starts_with($code, 'LOAN-BANK') => [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_BANK_FEE],
            str_starts_with($code, 'LOAN-INT') => [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_INTEREST],
            str_starts_with($code, 'LOAN-FORECLOSE') => [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_FORECLOSE],
            str_starts_with($code, 'FD-PROC') => [AccountingTags::MODULE_FD, AccountingTags::ENTRY_PROC_FEE],
            str_starts_with($code, 'FD-DOC') => [AccountingTags::MODULE_FD, AccountingTags::ENTRY_DOC_FEE],
            str_starts_with($code, 'FD-OTHER') => [AccountingTags::MODULE_FD, AccountingTags::ENTRY_OTHER_FEE],
            str_starts_with($code, 'FD-FEE') => [AccountingTags::MODULE_FD, AccountingTags::ENTRY_OTHER_FEE],
            default => [null, null],
        };
    }

    /**
     * @return array{0:?string,1:?string}
     */
    protected function inferFromText(string $description, string $reference = ''): array
    {
        $text = strtolower($description . ' ' . $reference);

        if (str_contains($text, 'loan foreclosure') || str_contains($text, 'loan fc') || str_contains($text, 'foreclosure charges')) {
            return [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_FORECLOSE];
        }
        if (str_contains($text, 'loan interest') || str_contains($text, 'foreclosure interest')) {
            return [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_INTEREST];
        }
        if (str_contains($text, 'foreman commission') || str_contains($text, 'chit foreman')) {
            return [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_FOREMAN_COMM];
        }
        if (str_contains($text, 'processing fee') || str_contains($text, 'processing fee retained') || str_contains($text, '-proc')) {
            $module = $this->moduleFromText($text);

            return [$module, AccountingTags::ENTRY_PROC_FEE];
        }
        if (str_contains($text, 'document fee') || str_contains($text, 'document charge') || str_contains($text, '-doc')) {
            $module = $this->moduleFromText($text);

            return [$module, AccountingTags::ENTRY_DOC_FEE];
        }
        if (str_contains($text, 'banking charge') || str_contains($text, 'bank fee') || str_contains($text, '-bank')) {
            $module = $this->moduleFromText($text);

            return [$module, AccountingTags::ENTRY_BANK_FEE];
        }
        if (str_contains($text, 'settlement fee') || str_contains($text, 'fees retained')) {
            $module = $this->moduleFromText($text);

            return [$module, AccountingTags::ENTRY_OTHER_FEE];
        }
        if (str_contains($text, 'wallet withdrawal')) {
            return [AccountingTags::MODULE_FD, AccountingTags::ENTRY_WALLET_WD];
        }
        if (str_contains($text, 'member transfer')) {
            return [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_TRANSFER];
        }
        if (str_contains($text, 'installment collection reversed') || str_contains($text, 'reversal:') || str_contains($text, 'chit ic rev') || str_contains($text, 'loan ic rev')) {
            $module = (str_contains($text, 'chit') || str_contains($text, 'chit ic'))
                ? AccountingTags::MODULE_CHIT
                : AccountingTags::MODULE_LOAN;

            return [$module, AccountingTags::ENTRY_REVERSAL];
        }
        if (str_contains($text, 'chit installment') || str_contains($text, 'chit ic')) {
            return [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_INSTALLMENT];
        }
        if (str_contains($text, 'chit settlement')) {
            return [AccountingTags::MODULE_CHIT, AccountingTags::ENTRY_SETTLEMENT];
        }
        if (str_contains($text, 'loan disbursement')) {
            return [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_DISBURSEMENT];
        }
        if (str_contains($text, 'emi payment') || str_contains($text, 'loan account') || str_contains($text, 'loan ic')) {
            return [AccountingTags::MODULE_LOAN, AccountingTags::ENTRY_EMI];
        }
        if (str_contains($text, 'fd deposit')) {
            return [AccountingTags::MODULE_FD, AccountingTags::ENTRY_DEPOSIT];
        }
        if (str_contains($text, 'fixed deposit') || str_contains($text, 'fd payout') || str_contains($text, 'premature') || str_contains($text, 'manual closure')) {
            return [AccountingTags::MODULE_FD, AccountingTags::ENTRY_PAYOUT];
        }
        if (str_contains($text, 'withdrawn by') || str_starts_with($text, 'withdrawn by')) {
            return [AccountingTags::MODULE_OTHER, AccountingTags::ENTRY_WITHDRAWAL];
        }
        if (str_contains($text, 'deposited by') || str_starts_with($text, 'deposited by')) {
            return [AccountingTags::MODULE_OTHER, AccountingTags::ENTRY_DEPOSIT];
        }
        if (str_contains($text, 'chit')) {
            return [AccountingTags::MODULE_CHIT, null];
        }
        if (str_contains($text, 'loan')) {
            return [AccountingTags::MODULE_LOAN, null];
        }
        if (str_contains($text, 'fd ') || str_contains($text, 'fixed deposit')) {
            return [AccountingTags::MODULE_FD, null];
        }

        return [null, null];
    }

    protected function moduleFromText(string $text): string
    {
        if (str_contains($text, 'chit')) {
            return AccountingTags::MODULE_CHIT;
        }
        if (str_contains($text, 'loan')) {
            return AccountingTags::MODULE_LOAN;
        }
        if (str_contains($text, 'fd') || str_contains($text, 'fixed deposit')) {
            return AccountingTags::MODULE_FD;
        }

        return AccountingTags::MODULE_OTHER;
    }
}
