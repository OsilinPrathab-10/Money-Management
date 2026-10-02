<?php

namespace App\Listeners;

use App\Events\ChitSettlementPaidEvent;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendChitSettlementPaidNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(ChitSettlementPaidEvent $event): void
    {
        try {
            $this->notifications->settlementPaid($event->payout);
        } catch (Throwable $e) {
            Log::error('SendChitSettlementPaidNotification failed: ' . $e->getMessage());
        }
    }
}
