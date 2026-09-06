<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('emi:check-overdue')
    ->daily()
    ->at('09:00')
    ->description('Check for overdue EMIs and send notifications');

// Send EMI Payment Reminders
Schedule::command('emi:send-reminders')
    ->daily()
    ->at('08:00')
    ->description('Send EMI payment reminder emails based on due dates');

// Auto Database Backup
Schedule::command('backup:auto')
    ->daily()
    ->at('02:00')
    ->description('Automatically backup database based on configured frequency');

use Illuminate\Support\Facades\Log;

// Send Followup Reminders (Push Notifications)
Schedule::command('followup:send-reminders')
    ->everyMinute()
    ->description('Send push notifications for scheduled followups');

// Send Case Alert Notifications (Unresolved cases + Appointment visits)
Schedule::command('notifications:send-case-alerts')
    ->everyMinute() // Handles appointment_to_visit 5-min reminders every minute
    ->description('Send push notifications for unresolved cases and appointment visits');

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('penalties:apply')->everyMinute();
Schedule::command('update-emi:status')->everyMinute();
Schedule::command('logs:clear-old')->daily();
