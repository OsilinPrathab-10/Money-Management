<?php

namespace App\Listeners;

use App\Events\FdApplicationRejected;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFdApplicationRejectedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(FdApplicationRejected $event): void
    {
        try {
            $this->notifications->fdRejected($event->application, $event->reason);
        } catch (Throwable $e) {
            Log::error('SendFdApplicationRejectedNotification failed: ' . $e->getMessage());
        }
    }
}
