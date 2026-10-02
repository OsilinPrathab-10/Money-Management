<?php

namespace App\Listeners;

use App\Events\NewLoanApplicationEvent;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateAdminNotificationForLoanApplication
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(NewLoanApplicationEvent $event): void
    {
        try {
            $this->notifications->newLoanApplication(
                $event->loanApplication,
                $event->source ?? 'admin'
            );
        } catch (Throwable $e) {
            Log::error('Failed to create notifications for loan application: ' . $e->getMessage());
        }
    }
}
