<?php

namespace App\Listeners;

use App\Events\KycRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Services\PushNotificationService;
use Throwable;

class SendKycRejectedNotification
{
    protected PushNotificationService $fcm;

    /**
     * Create the event listener.
     */
    public function __construct(PushNotificationService $fcm)
    {
        $this->fcm = $fcm;
    }

    /**
     * Handle the event.
     */
    public function handle(KycRejected $event): void
    {
        try {
            $client = $event->client;
            $reason = $event->reason;

            if (!$client) {
                Log::warning('No client found in event');
                return;
            }

            $user = $client->user;
            if (!$user) {
                Log::warning('No user associated with client', ['client_id' => $client->id]);
                return;
            }

            $devices = $user->userDevice()->get() ?? collect();

            if ($devices->isEmpty()) {
                Log::info('No devices found for client to send push', [
                    'client_id' => $client->id,
                    'user_id' => $user->id,
                ]);
                return;
            }

            foreach ($devices as $device) {
                $this->fcm->sendPushNotification(
                    $device->device_token,
                    "KYC Rejected",
                    "Your KYC ₹{$event->client->client_name} rejected by Management.",
                    [
                        'screen' => 'kyc-rejected',
                        'reason' => $reason,
                    ]
                );
            }
        } catch (Throwable $e) {
            Log::error('SendKYCRejectedNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
