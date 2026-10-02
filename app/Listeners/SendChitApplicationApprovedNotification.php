<?php

namespace App\Listeners;

use App\Events\ChitApplicationApproved;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendChitApplicationApprovedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(ChitApplicationApproved $event): void
    {
        try {
            $this->notifications->chitApproved($event->member);
        } catch (Throwable $e) {
            Log::error('SendChitApplicationApprovedNotification failed: ' . $e->getMessage());
        }
    }
}
