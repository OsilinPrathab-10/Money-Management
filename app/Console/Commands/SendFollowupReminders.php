<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmiFollowup;
use App\Models\AgentNotification;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendFollowupReminders extends Command
{
    protected $signature = 'followup:send-reminders';
    protected $description = 'Send push notifications for scheduled followups at exact time';
    protected $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        parent::__construct();
        $this->pushService = $pushService;
    }

    public function handle()
    {
        $this->info('Starting followup reminder process...');

        // Target followups scheduled 5 minutes from now
        $targetMinute = Carbon::now()->addMinutes(5)->format('Y-m-d H:i');
        $stats = [
            'total_checked' => 0,
            'notifications_sent' => 0,
            'already_sent' => 0,
            'no_device' => 0,
            'errors' => 0,
        ];

        $followups = EmiFollowup::whereNotNull('followup_at')
            ->whereRaw("DATE_FORMAT(followup_at, '%Y-%m-%d %H:%i') = ?", [$targetMinute])
            ->with([
                'emi.loanAccount.client',
                'agent.user.userDevice'
            ])
            ->get();

        $this->info("Found {$followups->count()} followup(s) scheduled for {$targetMinute} (5 min reminder)");

        foreach ($followups as $followup) {
            $stats['total_checked']++;

            $alreadySent = AgentNotification::where('agent_id', $followup->agent_id)
                ->where('notification_type', 'followup_reminder')
                ->where('notification_id', $followup->id)
                ->exists();

            if ($alreadySent) {
                $stats['already_sent']++;
                $this->line("  - Skipped: Followup #{$followup->id} - Already sent");
                continue;
            }

            $agent = $followup->agent;
            if (!$agent || !$agent->user) {
                $stats['errors']++;
                $this->error("  ✗ Error: Followup #{$followup->id} - Agent or user not found");
                continue;
            }

            $deviceTokens = $agent->agentDevice()
                ->pluck('device_token')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (empty($deviceTokens)) {
                $stats['no_device']++;
                $this->line("  - Skipped: Followup #{$followup->id} - No device token");
                continue;
            }

            $emi = $followup->emi;
            $loanAccount = $emi->loanAccount;
            $client = $loanAccount->client ?? null;

            $title = 'Followup Reminder';
            $customerName = $client ? $client->client_name : 'Customer';
            $loanNumber = $loanAccount->account_number ?? 'N/A';
            $visitType = config("followup.status_options.{$followup->status}") ?? $followup->status;
            $scheduledTime = $followup->followup_at->format('h:i A');
            $followupTime = Carbon::now(); // Actual time when notification is sent (5 min before scheduled)
            $profileImage = $client ? ($client->profile_image_url ?? '') : ''; // Use profile_image_url accessor to get full URL
            $badge = '';
            if ($emi && $emi->status === 'overdue') {
                $badge = 'Overdue';
            } elseif ($emi && $emi->risk_level === 'high') {
                $badge = 'High Risk';
            }
            
            $dueAmount = $emi ? ($emi->pending_amount ?? 0) : 0;
            $statusLabel = $visitType; // Formatted status without underscore

            $body = "{$visitType} with {$customerName} (Loan: {$loanNumber}) starts in 5 minutes at {$scheduledTime}";

            $actionData = [
                'type' => 'followup_reminder',
                'followup_id' => (string) $followup->id,
                'emi_id' => (string) $followup->emi_id,
                'loan_id' => (string) ($loanAccount->id ?? ''),
                'loan_account_id' => (string) ($loanAccount->id ?? ''),
                'loan_number' => (string) $loanNumber,
                'agent_id' => (string) $followup->agent_id,
                'status' => (string) $followup->status,
                'visit_type' => (string) $visitType,
                'status_label' => (string) $statusLabel, // Formatted status like "Call Back"
                'followup_at' => $followup->followup_at->toIso8601String(),
                'followup_time' => $followupTime->toIso8601String(), // Actual push notification send time (5 min before scheduled)
                'customer_name' => (string) $customerName,
                'profile_image' => (string) $profileImage,
                'badge' => (string) $badge, // "Overdue", "High Risk", or empty
                'due_amount' => (float) $dueAmount,
                'remarks' => (string) ($followup->remarks ?? ''),
                'priority' => 'high'
            ];

            try {
                // sendPushNotification now includes both visible notification and high-priority data/overrides
                $result = $this->pushService->sendPushNotification(
                    $deviceTokens,
                    $title,
                    $body,
                    $actionData
                );

                $anySuccess = $result['success'] || collect($result['results'] ?? [])->contains('ok', true);

                if ($anySuccess) {
                    AgentNotification::create([
                        'agent_id' => $followup->agent_id,
                        'notification_type' => 'followup_reminder',
                        'notification_id' => (string) $followup->id,
                        'title' => $title,
                        'message' => $body,
                        'notification_type_label' => 'followup',
                        'icon' => 'schedule',
                        'priority' => 'high',
                        'action_data' => $actionData,
                    ]);

                    $tokenCount = count($deviceTokens);
                    $stats['notifications_sent']++;
                    $this->info("  ✓ Sent: Followup #{$followup->id} to {$agent->agent_name} ({$tokenCount} token(s))");
                } else {
                    $stats['errors']++;
                    $errorMsg = $result['error'] ?? ($result['message'] ?? 'Unknown error');
                    $this->error("  ✗ Failed: Followup #{$followup->id} - {$errorMsg}");
                    Log::error('Followup notification failed', [
                        'followup_id' => $followup->id,
                        'error' => $errorMsg,
                        'result' => $result
                    ]);
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->error("  ✗ Error: Followup #{$followup->id} - {$e->getMessage()}");
                Log::error('Followup reminder command error', [
                    'followup_id' => $followup->id,
                    'error' => $e->getMessage()
                ]);
            }

        }

        $this->newLine();
        $this->info('=== Followup Reminder Summary ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Checked', $stats['total_checked']],
                ['Notifications Sent', $stats['notifications_sent']],
                ['Already Sent (Skipped)', $stats['already_sent']],
                ['No Device Token', $stats['no_device']],
                ['Errors', $stats['errors']],
            ]
        );

        Log::info('Followup reminder process completed', $stats);

        return 0;
    }
}
