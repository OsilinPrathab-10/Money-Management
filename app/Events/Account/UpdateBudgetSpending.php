<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\JournalEntry;

class UpdateBudgetSpending
{
    use Dispatchable;

    public function __construct(
        public JournalEntry $journalEntry,
    ) {}
}
