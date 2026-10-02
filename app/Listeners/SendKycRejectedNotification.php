<?php

namespace App\Listeners;

use App\Events\KycRejected;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendKycRejectedNotification
{
    public function __construct(protected AppNotificationService $notifications) {}

    public function handle(KycRejected $event): void
    {
        try {
            if (! $event->client) {
                return;
            }

            $this->notifications->kycRejected($event->client, $event->reason ?? null);
        } catch (Throwable $e) {
            Log::error('SendKYCRejectedNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
