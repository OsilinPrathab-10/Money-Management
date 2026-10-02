<?php

namespace App\Listeners;

use App\Events\KycApproved;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendKycApprovedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(KycApproved $event): void
    {
        try {
            if (! $event->client) {
                return;
            }

            $this->notifications->kycApproved($event->client);
        } catch (Throwable $e) {
            Log::error('SendKYCApprovedNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
