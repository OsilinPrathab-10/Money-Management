<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Services\PushNotificationService;
use Throwable;

class SendLoanApplicationRejectedNotification
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
                    "Application Rejected",
                    "Your Application ₹{$event->application->application_number} rejected by Management.",
                    [
                        'screen' => 'rejected',
                        'application_number' => (string) $event->application->application_number,
                    ]
                );
            }
        } catch (Throwable $e) {
            Log::error('SendLoanApplicationRejectedNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
