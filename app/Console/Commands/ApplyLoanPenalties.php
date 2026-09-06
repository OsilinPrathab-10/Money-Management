<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Emi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ApplyLoanPenalties extends Command
{
    protected $signature = 'penalties:apply';

    protected $description = 'Apply daily penalty for overdue EMIs';

    public function handle()
    {
        Log::info("Penalty scheduler started at " . now());

        // Check if penalty configuration is globally active
        $penaltyConfig = \App\Models\LoanConfiguration::getPenaltyConfig();
        if (!$penaltyConfig || !$penaltyConfig->is_active) {
            Log::info("Penalty calculation is globally disabled.");
            $this->info("Penalty calculation is globally disabled.");
            return;
        }

        $today = Carbon::today();
        $count = 0;

        $emis = Emi::whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '<', $today)
            ->get();

        foreach ($emis as $emi) {

            DB::transaction(function () use ($emi, &$count, $today, $penaltyConfig) {

                // Already fully paid
                if ($emi->pending_amount <= 0) {
                    return;
                }

                $loanAccount = $emi->loanAccount;

                // Resolve penalty settings: use global if set, otherwise fallback to loan account settings
                $penaltyAmount = ($penaltyConfig->charge_value > 0) 
                    ? $penaltyConfig->charge_value 
                    : ($loanAccount->penalty ?? 0);

                $graceDays = ($penaltyConfig->eligibility_days !== null)
                    ? $penaltyConfig->eligibility_days
                    : ($loanAccount->grace_period_days ?? 0);

                $penaltyStartDate = Carbon::parse($emi->due_date)
                    ->addDays($graceDays);

                if ($today->lte($penaltyStartDate)) {
                    return;
                }

                // If penalty is already applied, don't apply it again
                if ($emi->penalty_amount > 0) {
                    return;
                }

                $penalty = round($penaltyAmount, 2);

                // ✅ Update EMI
                $emi->penalty_amount = $penalty;
                $emi->total_due += $penalty;
                $emi->pending_amount += $penalty;
                $emi->last_penalty_date = $today;
                $emi->status = 'overdue';

                $emi->save();

                $count++;
            });
        }

        Log::info("Penalty finished — Total penalized EMIs: {$count}");
        $this->info("Penalty calculation completed.");
    }
}

