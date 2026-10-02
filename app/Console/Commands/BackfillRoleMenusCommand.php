<?php

namespace App\Console\Commands;

use App\Services\MenuAccessService;
use Illuminate\Console\Command;

class BackfillRoleMenusCommand extends Command
{
    protected $signature = 'menus:backfill-roles {--overwrite : Replace existing role menu assignments}';

    protected $description = 'Backfill role_menus from verticalMenu.json role lists';

    public function handle(MenuAccessService $menus): int
    {
        $count = $menus->backfillRoleMenusFromJson((bool) $this->option('overwrite'));

        if ($count === 0) {
            $this->info('No changes. Role menus already exist (use --overwrite to rebuild).');

            return self::SUCCESS;
        }

        $this->info("Backfilled {$count} role menu assignments.");

        return self::SUCCESS;
    }
}
