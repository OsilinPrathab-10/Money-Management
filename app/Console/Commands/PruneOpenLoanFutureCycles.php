<?php

namespace App\Console\Commands;

use App\Models\LoanAccount;
use App\Services\OpenLoanCycleService;
use Illuminate\Console\Command;

/**
 * Manual cleanup for open loans disbursed before cycles were capped at today.
 * Deliberately not scheduled - deleting rows is always an explicit decision.
 */
class PruneOpenLoanFutureCycles extends Command
{
    protected $signature = 'loans:prune-open-cycles
                            {--loan= : Limit the run to a single loan account id}
                            {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete future-dated, never-collected interest cycles from open (interest-only) loans';

    public function handle(OpenLoanCycleService $cycles): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = LoanAccount::query()
            ->where('loan_mode', 'interest_only')
            ->with('loanApplication');

        if ($loanId = $this->option('loan')) {
            $query->where('id', (int) $loanId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('No open loans matched.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $today = now()->startOfDay()->toDateString();
            $total = 0;

            foreach ($accounts as $account) {
                $count = $account->emis()
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '>', $today)
                    ->whereNotIn('status', ['paid', 'partial'])
                    ->where(function ($q) {
                        $q->whereNull('paid_amount')->orWhere('paid_amount', '<=', 0.01);
                    })
                    ->count();

                if ($count > 0) {
                    $this->line("Loan #{$account->id}: {$count} future cycle(s) would be removed");
                    $total += $count;
                }
            }

            $this->warn("Dry run — {$total} cycle(s) would be deleted. Re-run without --dry-run to apply.");

            return self::SUCCESS;
        }

        if (! $this->option('loan') && ! $this->confirm('Delete future-dated uncollected cycles from ALL open loans?', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $touched = 0;

        foreach ($accounts as $account) {
            $removed = $cycles->pruneFutureCycles($account);

            if ($removed > 0) {
                $deleted += $removed;
                $touched++;
                $this->line("Loan #{$account->id}: -{$removed} cycle(s)");
            }
        }

        $this->info("Deleted {$deleted} future cycle(s) across {$touched} open loan(s).");

        return self::SUCCESS;
    }
}
