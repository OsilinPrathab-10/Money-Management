<?php

namespace App\Listeners;

use App\Events\NewUserRegistrationEvent;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Log;

class CreateAdminNotificationForUserRegistration
{
    /**
     * Handle the event.
     */
    public function handle(NewUserRegistrationEvent $event): void
    {
        try {
            $user = $event->user;

            // Check if a notification already exists for this user
            $existingNotification = AdminNotification::where('type', 'new_user_registration')
                ->where('related_id', $user->id)
                ->first();

            // If notification already exists, don't create a new one
            if ($existingNotification) {
                return;
            }

            AdminNotification::create([
                'type' => 'new_user_registration',
                'title' => 'New User Registration',
                'message' => sprintf(
                    'New user %s (%s) has registered',
                    $user->name,
                    $user->phone ?? $user->email
                ),
                'link' => route('user-management'),
                'icon' => 'ri-user-add-line',
                'related_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create admin notification for user registration: ' . $e->getMessage());
        }
    }
}
