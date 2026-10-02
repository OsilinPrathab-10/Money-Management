<?php

namespace App\Listeners;

use App\Events\NewFdApplicationEvent;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNewFdApplicationNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(NewFdApplicationEvent $event): void
    {
        try {
            $this->notifications->newFdApplication($event->application, $event->source);
        } catch (Throwable $e) {
            Log::error('SendNewFdApplicationNotification failed: ' . $e->getMessage());
        }
    }
}
