<?php

namespace App\Listeners;

use App\Events\EmiOverdueEvent;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Log;

class CreateAdminNotificationForEmiOverdue
{
    /**
     * Handle the event.
     */
    public function handle(EmiOverdueEvent $event): void
    {
        try {
            $emi = $event->emi;
            $loanAccount = $emi->loanAccount;
            $client = optional($loanAccount->loanApplication)->client ?? $loanAccount->client;

            // Check if a notification already exists for this EMI overdue
            $existingNotification = AdminNotification::where('type', 'emi_overdue')
                ->where('related_id', $emi->id)
                ->first();

            // If notification already exists, don't create a new one
            if ($existingNotification) {
                return;
            }

            AdminNotification::create([
                'type' => 'emi_overdue',
                'title' => 'EMI Overdue Alert',
                'message' => sprintf(
                    'EMI #%d (₹%s) is overdue for %s - Due date: %s',
                    $emi->instalment_number,
                    number_format($emi->total_amount, 2),
                    $client->client_name ?? 'Unknown Client',
                    $emi->due_date->format('d-m-Y')
                ),
                'link' => route('emi-repayments-show', $emi->id),
                'icon' => 'ri-alarm-warning-line',
                'related_id' => $emi->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create admin notification for EMI overdue: ' . $e->getMessage());
        }
    }
}
