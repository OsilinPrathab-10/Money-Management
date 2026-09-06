<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmiCollection;
use App\Models\Emi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixStuckCollections extends Command
{
    protected $signature = 'collections:fix-stuck';
    protected $description = 'Fix collections that are stuck in in_progress status but payment was successful';

    public function handle()
    {
        $this->info('Finding stuck collections...');

        $stuckCollections = EmiCollection::where('status', 'in_progress')
            ->where('payment_method', 'direct')
            ->whereNotNull('payment_reference')
            ->get();

        if ($stuckCollections->isEmpty()) {
            $this->info('No stuck collections found.');
            return 0;
        }

        $this->info("Found {$stuckCollections->count()} stuck collections.");

        $fixed = 0;

        foreach ($stuckCollections as $collection) {
            DB::beginTransaction();
            try {
                $emi = Emi::find($collection->emi_id);
                
                if (!$emi) {
                    $this->warn("EMI #{$collection->emi_id} not found for collection #{$collection->id}");
                    continue;
                }

                $this->info("Processing collection #{$collection->id} for EMI #{$emi->id}");

                $collection->update([
                    'status' => 'completed',
                    'collected_at' => now(),
                ]);

                $emi->paid_amount = ($emi->paid_amount ?? 0) + $collection->amount;
                $totalDue = $emi->total_amount + ($emi->penalty_amount ?? 0);
                $emi->pending_amount = max(0, $totalDue - $emi->paid_amount);

                if ($emi->pending_amount <= 0) {
                    $emi->status = 'paid';
                    $emi->paid_date = now();
                } else {
                    $emi->status = 'partial';
                    $emi->is_partial_paid = true;
                    $emi->partial_paid_amount = $emi->paid_amount;
                    $emi->partial_paid_date = now();
                }

                $emi->payment_method = 'online';
                $emi->save();

                if ($emi->status === 'paid') {
                    app(\App\Services\LoanPaymentService::class)
                        ->syncLoanTotals($emi->loan_account_id);

                    \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                        ->whereIn('status', ['assigned', 'visited'])
                        ->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                            'remarks' => 'Recovered via Online Payment (Fixed)',
                        ]);
                }

                DB::commit();
                $fixed++;

                $this->info("✓ Fixed collection #{$collection->id} - EMI #{$emi->id} now {$emi->status}");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("✗ Failed to fix collection #{$collection->id}: {$e->getMessage()}");
                Log::error('Failed to fix stuck collection', [
                    'collection_id' => $collection->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("\nFixed {$fixed} out of {$stuckCollections->count()} collections.");
        return 0;
    }
}
