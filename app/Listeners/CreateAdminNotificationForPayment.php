<?php

namespace App\Listeners;

use App\Events\PaymentReceivedEvent;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Log;

class CreateAdminNotificationForPayment
{
    /**
     * Handle the event.
     */
    public function handle(PaymentReceivedEvent $event): void
    {
        try {
            $emi = $event->emi;
            $amount = $event->amount;
            $loanAccount = $emi->loanAccount;
            $client = optional($loanAccount->loanApplication)->client ?? $loanAccount->client;

            // Check if a notification already exists for this payment
            $existingNotification = AdminNotification::where('type', 'payment_received')
                ->where('related_id', $emi->id)
                ->first();

            // If notification already exists, don't create a new one
            if ($existingNotification) {
                return;
            }

            $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
            $instalmentLabel = $isKandhuvatti ? 'Kandhuvatti Cycle' : 'EMI';
            $title = $isKandhuvatti ? 'Kandhuvatti Payment' : 'EMI Payment';

            AdminNotification::create([
                'type' => 'payment_received',
                'title' => $title,
                'message' => sprintf(
                    'Payment of ₹%s received from %s for %s #%d',
                    number_format($amount, 2),
                    $client->client_name ?? 'Unknown Client',
                    $instalmentLabel,
                    $emi->instalment_number
                ),
                'link' => route('view-receipt', $emi->id),
                'icon' => 'ri-money-rupee-circle-line',
                'related_id' => $emi->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create admin notification for payment: ' . $e->getMessage());
        }
    }
}
