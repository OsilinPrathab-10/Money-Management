<?php

namespace App\Listeners;

use App\Events\KycApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Services\PushNotificationService;
use Throwable;

class SendKycApprovedNotification
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
    public function handle(KycApproved $event): void
    {
        try {
            $client = $event->client;
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
                    "KYC  Approved",
                    "Your KYC ₹{$event->client->client_name} approved by Management.",
                    [
                        'screen' => 'kyc-approved',
                    ]
                );
            }
        } catch (Throwable $e) {
            Log::error('SendKYCApprovedNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
