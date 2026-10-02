<?php

namespace App\Listeners;

use App\Events\PaymentReceivedEvent;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateAdminNotificationForPayment
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(PaymentReceivedEvent $event): void
    {
        try {
            $this->notifications->emiCollected(
                $event->emi,
                (float) $event->amount,
                AppNotificationService::detectSource()
            );
        } catch (Throwable $e) {
            Log::error('Failed to create notifications for payment: ' . $e->getMessage());
        }
    }
}
