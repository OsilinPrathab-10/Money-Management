<?php

namespace App\Listeners;

use App\Events\ChitApplicationRejected;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendChitApplicationRejectedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(ChitApplicationRejected $event): void
    {
        try {
            $this->notifications->chitRejected($event->member);
        } catch (Throwable $e) {
            Log::error('SendChitApplicationRejectedNotification failed: ' . $e->getMessage());
        }
    }
}
