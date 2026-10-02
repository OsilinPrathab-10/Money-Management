<?php

namespace App\Console\Commands;

use App\Services\ChitGroupCompletionService;
use Illuminate\Console\Command;

class CompleteEndedChitGroups extends Command
{
    protected $signature = 'chit:complete-ended-groups';

    protected $description = 'Mark chit groups as completed after their end month and congratulate members who finished all months';

    public function handle(ChitGroupCompletionService $completion): int
    {
        $count = $completion->completeEndedGroups(true);

        $this->info("Completed {$count} ended chit group(s).");

        return self::SUCCESS;
    }
}
