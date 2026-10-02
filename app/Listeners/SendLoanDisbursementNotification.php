<?php

namespace App\Listeners;

use App\Events\LoanDisbursement;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLoanDisbursementNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(LoanDisbursement $event): void
    {
        try {
            if (! $event->client || ! $event->application) {
                return;
            }

            $this->notifications->loanDisbursed($event->client, $event->application);
        } catch (Throwable $e) {
            Log::error('SendLoanDisbursementNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
