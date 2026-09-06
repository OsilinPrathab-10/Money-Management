<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LoanAccount;
use App\Models\Emi;

class CloseLoanForTesting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan:close-test {loan_id : The ID of the loan account to close}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close a loan account for testing (triggers observer and sends email)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $loanId = $this->argument('loan_id');

        // Find the loan account
        $loanAccount = LoanAccount::with(['emis', 'loanApplication.client'])->find($loanId);

        if (!$loanAccount) {
            $this->error("Loan account with ID {$loanId} not found!");
            return 1;
        }

        // Display loan info
        $this->info("Loan Account: {$loanAccount->account_number}");
        $this->info("Current Status: {$loanAccount->status}");
        $this->info("Outstanding Amount: ₹{$loanAccount->outstanding_amount}");
        
        $client = $loanAccount->loanApplication->client;
        $this->info("Client: {$client->client_name} ({$client->client_email})");

        // Confirm action
        if (!$this->confirm('Do you want to close this loan account?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        // Mark all EMIs as paid
        $this->info('Marking all EMIs as paid...');
        $emis = $loanAccount->emis()->whereIn('status', ['pending', 'overdue'])->get();
        
        foreach ($emis as $emi) {
            $emi->update([
                'status' => 'paid',
                'paid_amount' => $emi->total_amount,
                'paid_date' => now(),
                'payment_method' => 'bank_transfer'
            ]);
        }

        $this->info("Updated {$emis->count()} EMIs to 'paid' status.");

        // Update loan account totals
        $totalPaid = $loanAccount->emis()->sum('paid_amount');
        $loanAccount->paid_amount = $totalPaid;
        $loanAccount->outstanding_amount = 0;

        // Close the loan (this will trigger the observer!)
        $this->info('Closing loan account...');
        $loanAccount->status = 'closed';
        $loanAccount->closed_at = now();
        $loanAccount->save();  // This triggers the observer!

        $this->info('✅ Loan account closed successfully!');
        $this->info('📧 Email should be sent to: ' . $client->client_email);
        $this->info('📄 Check storage/logs/laravel.log for email sending logs');

        return 0;
    }
}
