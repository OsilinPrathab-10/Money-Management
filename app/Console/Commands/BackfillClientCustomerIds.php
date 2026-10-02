<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class BackfillClientCustomerIds extends Command
{
    protected $signature = 'clients:backfill-customer-ids';

    protected $description = 'Generate SDS-C customer IDs for clients that do not have one (local and live)';

    public function handle(): int
    {
        $count = Client::backfillMissingCustomerIds();
        $this->info($count > 0
            ? "Generated customer IDs for {$count} client(s)."
            : 'All clients already have a customer ID.');

        return self::SUCCESS;
    }
}
