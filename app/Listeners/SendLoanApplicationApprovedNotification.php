<?php

namespace App\Listeners;

use App\Events\LoanApplicationApproved;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLoanApplicationApprovedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(LoanApplicationApproved $event): void
    {
        try {
            if (! $event->client || ! $event->application) {
                return;
            }

            $this->notifications->loanApproved($event->client, $event->application);
        } catch (Throwable $e) {
            Log::error('SendLoanApplicationApprovedNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
