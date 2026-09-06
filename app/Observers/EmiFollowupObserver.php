<?php

namespace App\Observers;

use App\Models\EmiFollowup;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;

class EmiFollowupObserver
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Handle the EmiFollowup "created" event.
     */
    public function created(EmiFollowup $followup): void
    {
        // Reminders are now handled by SendCaseAlertNotifications command 
        // 5 minutes before scheduled time to ensure background alarms.
    }

    /**
     * Handle the EmiFollowup "updated" event.
     */
    public function updated(EmiFollowup $followup): void
    {
        // Reminders are now handled by SendCaseAlertNotifications command
        // 5 minutes before scheduled time to ensure background alarms.
    }
}
