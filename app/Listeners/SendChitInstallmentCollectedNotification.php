<?php

namespace App\Listeners;

use App\Events\ChitInstallmentCollectedEvent;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendChitInstallmentCollectedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(ChitInstallmentCollectedEvent $event): void
    {
        try {
            $this->notifications->chitInstallmentCollected(
                $event->installment,
                $event->amount,
                $event->client,
                $event->source
            );
        } catch (Throwable $e) {
            Log::error('SendChitInstallmentCollectedNotification failed: ' . $e->getMessage());
        }
    }
}
