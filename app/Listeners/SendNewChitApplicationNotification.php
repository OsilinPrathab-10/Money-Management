<?php

namespace App\Listeners;

use App\Events\NewChitApplicationEvent;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNewChitApplicationNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(NewChitApplicationEvent $event): void
    {
        try {
            $this->notifications->newChitApplication($event->member, $event->source);
        } catch (Throwable $e) {
            Log::error('SendNewChitApplicationNotification failed: ' . $e->getMessage());
        }
    }
}
