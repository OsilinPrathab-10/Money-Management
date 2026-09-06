<?php

namespace App\Observers;

use App\Models\LoanAccount;
use App\Events\GenerateDocument;
use App\Services\LoanDocumentEmailService;
use Illuminate\Support\Facades\Log;

class LoanAccountObserver
{
    /**
     * Handle the LoanAccount "updated" event.
     * 
     * This observer detects when a loan account status changes to 'closed'
     * and automatically triggers document generation and email sending.
     */
    public function updated(LoanAccount $loanAccount): void
    {
        // Check if status was changed to 'closed'
        if ($loanAccount->isDirty('status') && $loanAccount->status === 'closed') {
            Log::info('LoanAccountObserver: Loan status changed to closed', [
                'loan_id' => $loanAccount->id,
                'account_number' => $loanAccount->account_number,
                'original_status' => $loanAccount->getOriginal('status'),
                'new_status' => $loanAccount->status
            ]);

            // Ensure closed_at is set
            if (!$loanAccount->closed_at) {
                $loanAccount->closed_at = now();
                $loanAccount->saveQuietly(); // Save without triggering observer again
                
                Log::info('LoanAccountObserver: Set closed_at timestamp', [
                    'loan_id' => $loanAccount->id,
                    'closed_at' => $loanAccount->closed_at
                ]);
            }

            // Trigger document generation in the background
            try {
                Log::info('LoanAccountObserver: Triggering document generation event', [
                    'loan_id' => $loanAccount->id
                ]);

                event(new GenerateDocument($loanAccount));

                // Wait for documents to be generated
                // Note: In production, consider using queued jobs instead of sleep
                sleep(3);

                // Send loan closure email with documents
                $emailService = app(LoanDocumentEmailService::class);
                $emailResult = $emailService->sendLoanDocumentsEmail($loanAccount->id, 'loan_closed');

                if ($emailResult['success']) {
                    Log::info('LoanAccountObserver: Loan closure email sent successfully', [
                        'loan_id' => $loanAccount->id,
                        'documents_sent' => $emailResult['documents_sent'] ?? 0
                    ]);
                } else {
                    Log::warning('LoanAccountObserver: Loan closure email failed', [
                        'loan_id' => $loanAccount->id,
                        'reason' => $emailResult['message']
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('LoanAccountObserver: Failed to send loan closure email', [
                    'loan_id' => $loanAccount->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't throw exception - we don't want to fail the loan closure
                // if email sending fails
            }
        }
    }

    /**
     * Handle the LoanAccount "created" event.
     */
    public function created(LoanAccount $loanAccount): void
    {
        // Log loan account creation
        Log::info('LoanAccountObserver: New loan account created', [
            'loan_id' => $loanAccount->id,
            'account_number' => $loanAccount->account_number,
            'status' => $loanAccount->status
        ]);
    }
}
