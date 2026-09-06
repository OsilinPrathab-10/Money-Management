<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLoanDisbursementNotification
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
    public function handle(object $event): void
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
                    "Application Disbursement 🎉",
                    "Your Application ₹{$event->application->application_number} disbursement by Management.",
                    [
                        'screen' => 'disbursed',
                        'application_number' => (string) $event->application->application_number,
                    ]
                );
            }
        } catch (Throwable $e) {
            Log::error('SendLoanDisbursementNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
