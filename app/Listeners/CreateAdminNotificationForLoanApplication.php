<?php

namespace App\Listeners;

use App\Events\NewLoanApplicationEvent;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Log;

class CreateAdminNotificationForLoanApplication
{
    /**
     * Handle the event.
     */
    public function handle(NewLoanApplicationEvent $event): void
    {
        try {
            $application = $event->loanApplication;
            $client = $application->client;

            // Check if a notification already exists for this application
            $existingNotification = AdminNotification::where('type', 'new_loan_application')
                ->where('related_id', $application->id)
                ->first();

            // If notification already exists, don't create a new one
            if ($existingNotification) {
                return;
            }

            AdminNotification::create([
                'type' => 'new_loan_application',
                'title' => 'New Loan Application',
                'message' => sprintf(
                    '%s has submitted a new loan application',
                    $client->client_name ?? 'Unknown Client',
                ),
                'link' => route('loan-application-view', $application->id),
                'icon' => 'ri-file-list-3-line',
                'related_id' => $application->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create admin notification for loan application: ' . $e->getMessage());
        }
    }
}
