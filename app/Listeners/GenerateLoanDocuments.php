<?php

namespace App\Listeners;

use App\Events\GenerateDocument;
use App\Services\LoanDocumentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\LoanAccount;

class GenerateLoanDocuments
{
    protected $documentService;

    /**
     * Create the event listener.
     */
    public function __construct(LoanDocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Handle the event - Auto-generate documents when loan is disbursed
     */
    public function handle(GenerateDocument $event): void
    {
        $loanAccount = $event->loanAccount;

        // Load required relationships for document generation
        $loanAccount->load(['loanApplication.client', 'loanApplication.product']);

        Log::info('Auto-generating documents for disbursed loan', [
            'loan_id' => $loanAccount->id,
            'account_number' => $loanAccount->account_number,
            'status' => $loanAccount->status
        ]);

        // Get all available documents for this loan based on its status
        $availableDocuments = $this->documentService->getAvailableDocuments($loanAccount);

        if ($availableDocuments->isEmpty()) {
            Log::warning('No documents available for loan', [
                'loan_id' => $loanAccount->id,
                'status' => $loanAccount->status
            ]);
            return;
        }

        // Generate and save each document
        foreach ($availableDocuments as $template) {
            try {
                $this->documentService->generateAndSaveDocument(
                    $loanAccount->id,
                    $template->type
                );

                Log::info('Document generated successfully', [
                    'loan_id' => $loanAccount->id,
                    'document_type' => $template->type
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to generate document', [
                    'loan_id' => $loanAccount->id,
                    'document_type' => $template->type,
                    'error' => $e->getMessage()
                ]);
                // Continue with other documents even if one fails
            }
        }

        Log::info('Completed auto-generation of documents', [
            'loan_id' => $loanAccount->id,
            'documents_generated' => $availableDocuments->count()
        ]);
    }
}
