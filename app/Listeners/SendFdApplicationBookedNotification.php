<?php

namespace App\Listeners;

use App\Events\FdApplicationBooked;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFdApplicationBookedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(FdApplicationBooked $event): void
    {
        try {
            $this->notifications->fdBooked($event->application);
        } catch (Throwable $e) {
            Log::error('SendFdApplicationBookedNotification failed: ' . $e->getMessage());
        }
    }
}
