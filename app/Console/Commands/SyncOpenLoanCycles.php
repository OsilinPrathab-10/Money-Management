<?php

namespace App\Console\Commands;

use App\Models\LoanAccount;
use App\Services\OpenLoanCycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOpenLoanCycles extends Command
{
    protected $signature = 'loans:sync-open-cycles
                            {--loan= : Limit the run to a single loan account id}';

    protected $description = 'Create interest cycles for open (interest-only) loans that have fallen due, without generating any future dates';

    public function handle(OpenLoanCycleService $cycles): int
    {
        $query = LoanAccount::query()
            ->where('loan_mode', 'interest_only')
            ->where('status', 'active')
            ->with('loanApplication');

        if ($loanId = $this->option('loan')) {
            $query->where('id', (int) $loanId);
        }

        $created = 0;
        $touched = 0;

        $query->chunkById(100, function ($accounts) use ($cycles, &$created, &$touched) {
            foreach ($accounts as $account) {
                $made = $cycles->syncDueCycles($account);

                if ($made > 0) {
                    $created += $made;
                    $touched++;
                    $this->line("Loan #{$account->id}: +{$made} cycle(s)");
                }
            }
        });

        Log::info("Open loan cycle sync finished — {$created} cycle(s) across {$touched} loan(s)");
        $this->info("Created {$created} interest cycle(s) across {$touched} open loan(s).");

        return self::SUCCESS;
    }
}
