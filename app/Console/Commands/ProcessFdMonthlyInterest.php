<?php

namespace App\Console\Commands;

use App\Services\FixedDeposit\FixedDepositMonthlyInterestService;
use Illuminate\Console\Command;

class ProcessFdMonthlyInterest extends Command
{
    protected $signature = 'fd:pay-monthly-interest';

    protected $description = 'Credit monthly FD interest to customer wallets';

    public function handle(FixedDepositMonthlyInterestService $service): int
    {
        $result = $service->processDuePayouts();

        $this->info("Monthly FD interest processed: {$result['processed']} payout(s).");

        if ($result['skipped'] > 0) {
            $this->warn("Skipped/errors: {$result['skipped']}");
            foreach ($result['errors'] as $error) {
                $this->line('  - ' . $error);
            }
        }

        return self::SUCCESS;
    }
}
