<?php

namespace App\Listeners;

use App\Events\NewSettlementApplicationEvent;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNewSettlementApplicationNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(NewSettlementApplicationEvent $event): void
    {
        try {
            $this->notifications->newSettlementApplication($event->payout, $event->source);
        } catch (Throwable $e) {
            Log::error('SendNewSettlementApplicationNotification failed: ' . $e->getMessage());
        }
    }
}
