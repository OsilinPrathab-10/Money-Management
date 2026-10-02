<?php

namespace App\Listeners;

use App\Events\FdApplicationApproved;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFdApplicationApprovedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(FdApplicationApproved $event): void
    {
        try {
            $this->notifications->fdApproved($event->application);
        } catch (Throwable $e) {
            Log::error('SendFdApplicationApprovedNotification failed: ' . $e->getMessage());
        }
    }
}
