<?php

namespace App\Listeners;

use App\Events\EmiOverdueEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEmiOverdueNotification
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
    public function handle(EmiOverdueEvent $event): void
    {
        try {
            $emi = $event->emi;
            $loan = $emi->loanAccount;
            $client = $loan->client;

            if (!$client) {
                Log::warning('No client found in event for EMI overdue', ['emi_id' => $emi->id]);
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
                    "EMI Overdue ⚠️",
                    "Your EMI of ₹{$emi->total_amount} due on {$emi->due_date->format('d-m-Y')} is now overdue.",
                    [
                        'screen' => 'emi_overdue',
                        'emi_id' => (string) $emi->id,
                        'loan_id' => (string) $loan->id,
                    ]
                );
            }

        } catch (Throwable $e) {
            Log::error('SendEmiOverdueNotification failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

    }
}
