<?php

namespace App\Listeners;

use App\Events\LoanApplicationRejected;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLoanApplicationRejectedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(LoanApplicationRejected $event): void
    {
        try {
            if (! $event->client || ! $event->application) {
                return;
            }

            $this->notifications->loanRejected(
                $event->client,
                $event->application,
                $event->application->remarks ?? null
            );
        } catch (Throwable $e) {
            Log::error('SendLoanApplicationRejectedNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
