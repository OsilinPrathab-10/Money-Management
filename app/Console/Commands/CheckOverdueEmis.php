<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Emi;
use App\Events\EmiOverdueEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CheckOverdueEmis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emi:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue EMIs and fire notification events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue EMIs...');

        $today = Carbon::now(); // Keep time component for accuracy if needed, but startOfDay for comparison
        $todayDate = $today->copy()->startOfDay();

        // Find all EMIs that are overdue or partial but past due date
        // We exclude 'paid' and 'carried_forward'
        $overdueEmis = Emi::whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '<', $todayDate)
            ->with(['loanAccount.loanApplication.client', 'loanAccount.client'])
            ->get();

        if ($overdueEmis->isEmpty()) {
            $this->info('No overdue EMIs found.');
            return 0;
        }

        $this->info("Found {$overdueEmis->count()} overdue/partial EMI(s).");

        $notificationCount = 0;
        $penaltyCount = 0;
        $carryForwardCount = 0;

        foreach ($overdueEmis as $emi) {
            DB::beginTransaction();
            try {
                // 1. Apply Penalty if not already applied
                $penaltyConfig = \App\Models\LoanConfiguration::getPenaltyConfig();
                $isPenaltyActive = $penaltyConfig && $penaltyConfig->is_active;

                if ($isPenaltyActive && $emi->penalty_amount == 0) {
                    $loanAccount = $emi->loanAccount;
                    
                    // Resolve settings
                    $penaltyAmount = ($penaltyConfig->charge_value > 0)
                        ? $penaltyConfig->charge_value
                        : ($loanAccount->penalty ?? 0);

                    $graceDays = ($penaltyConfig->eligibility_days !== null)
                        ? $penaltyConfig->eligibility_days
                        : ($loanAccount->grace_period_days ?? 0);

                    $penaltyStartDate = Carbon::parse($emi->due_date)->addDays($graceDays);

                    if ($todayDate->gt($penaltyStartDate) && $penaltyAmount > 0) {
                        // Add penalty to total due and pending
                        $newTotalDue = $emi->total_due + $penaltyAmount;
                        $newPending = $emi->pending_amount + $penaltyAmount;

                        $emi->update([
                            'penalty_amount' => $penaltyAmount,
                            'total_due' => $newTotalDue,
                            'pending_amount' => $newPending,
                            'last_penalty_date' => $todayDate,
                            'status' => 'overdue', // Force status to overdue if it was pending/partial
                            'remarks' => trim(($emi->remarks ?? '') . " [Penalty of ₹" . number_format($penaltyAmount, 2) . " applied]")
                        ]);
                        $penaltyCount++;
                    }
                }

                // 2. Check for Carry Forward (End of Month Logic)
                if ($todayDate->format('Y-m') > $emi->due_date->format('Y-m')) {
                    $balanceToMove = $emi->pending_amount;

                    if ($balanceToMove > 0) {
                        // Find Next EMI
                        $nextEmi = Emi::where('loan_account_id', $emi->loan_account_id)
                            ->where('instalment_number', $emi->instalment_number + 1)
                            ->first();

                        if ($nextEmi) {
                            // Move balance to next EMI
                            $nextEmi->update([
                                'previous_balance' => ($nextEmi->previous_balance ?? 0) + $balanceToMove,
                                'total_due' => ($nextEmi->total_due ?? $nextEmi->total_amount) + $balanceToMove,
                                'pending_amount' => ($nextEmi->pending_amount ?? $nextEmi->total_amount) + $balanceToMove
                            ]);

                            // Mark current EMI as carried forward (closed)
                            $emi->update([
                                'pending_amount' => 0,
                                'status' => 'carried_forward', // Custom status
                                'balance_forward' => $balanceToMove,
                                'remarks' => trim(($emi->remarks ?? '') . " [Balance $balanceToMove carried forward to EMI #{$nextEmi->instalment_number}]")
                            ]);
                            
                            $carryForwardCount++;
                            $this->info("  - Carried forward {$balanceToMove} from EMI #{$emi->instalment_number} to #{$nextEmi->instalment_number}");
                        } else {

                        }
                    }
                }

                if ($emi->status === 'overdue') {
                     event(new EmiOverdueEvent($emi));
                     $notificationCount++;
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to process overdue EMI #{$emi->id}: " . $e->getMessage());
                $this->error("  - Failed to process EMI #{$emi->id}");
            }
        }

        $this->info("Processed: {$penaltyCount} penalties applied, {$carryForwardCount} balances carried forward.");
        return 0;
    }
}
