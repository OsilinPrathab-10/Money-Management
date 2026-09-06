<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Emi;
use App\Events\EmiOverdueEvent;
use Illuminate\Support\Facades\DB;

class UpdateEmiStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-emi:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emi status update from pending to overdue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info("Installment status update started at " . now());

        $count = 0;

        Emi::where('status', 'pending')
            ->whereNull('paid_date')
            ->where('due_date', '<', today())
            ->chunkById(100, function ($emis) use (&$count) {

                DB::transaction(function () use ($emis, &$count) {

                    foreach ($emis as $emi) {
                        $emi->update(['status' => 'overdue']);

                        // Fire event AFTER update
                        event(new EmiOverdueEvent($emi));

                        $count++;
                    }
                });
            });

        Log::info("Installment status update completed — Total updated: {$count}");

        $this->info("Updated {$count} installments.");

        return 0;
    }

}
