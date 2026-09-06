<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmiAgentAssignment;
use App\Models\EmiFollowup;
use App\Models\AgentNotification;
use App\Models\Agent;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendCaseAlertNotifications extends Command
{
    protected $signature = 'notifications:send-case-alerts';
    protected $description = 'Send push notifications for unresolved cases and upcoming appointment_to_visit';
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        parent::__construct();
        $this->pushService = $pushService;
    }

    public function handle()
    {
        $this->info('Starting case alert notification process...');

        $stats = ['unresolved_sent' => 0, 'visit_sent' => 0, 'errors' => 0];

        $this->sendUnresolvedCaseAlerts($stats);
        $this->sendFollowupDataMessages($stats);

        $this->newLine();
        $this->info('=== Case Alert Summary ===');
        $this->table(
            ['Type', 'Count'],
            [
                ['Unresolved Case Alerts Sent', $stats['unresolved_sent']],
                ['Followup Data Alerts Sent', $stats['visit_sent']],
                ['Errors', $stats['errors']],
            ]
        );

        Log::info('Case alert notification process completed', $stats);
        return 0;
    }

    private function sendFollowupDataMessages(array &$stats): void
    {
        $followups = EmiFollowup::where('status', 'appointment_to_visit')
            ->whereDate('followup_at', now()->toDateString())
            ->with(['agent.user'])
            ->get()
            ->groupBy('agent_id')
            ->filter(fn($group, $agentId) => !is_null($agentId));

        foreach ($followups as $agentId => $agentFollowups) {
            $alreadySent = AgentNotification::where('agent_id', $agentId)
                ->where('notification_type', 'appointment_to_visit_alert')
                ->where('created_at', '>=', now()->subHours(3))
                ->exists();

            if ($alreadySent)
                continue;

            $agent = $agentFollowups->first()->agent;
            if (!$agent || !$agent->user)
                continue;

            $deviceTokens = $agent->user->userDevice()
                ->where('user_type', 'Agent')
                ->pluck('device_token')
                ->filter()->unique()->values()->toArray();

            if (empty($deviceTokens))
                continue;

            $count = $agentFollowups->count();
            $title = 'Upcoming Visits Today';
            $body = "You have {$count} appointment_to_visit followup(s) scheduled for today.";

            $actionData = [
                'type' => 'appointment_to_visit',
                'count' => (string) $count,
                'followup_ids' => $agentFollowups->pluck('id')->map(fn($id) => (string) $id)->toArray(),
            ];

            try {
                $result = $this->pushService->sendPushNotification($deviceTokens, $title, $body, $actionData);
                $anySuccess = $result['success'] || collect($result['results'] ?? [])->contains('ok', true);

                if ($anySuccess) {
                    AgentNotification::create([
                        'agent_id' => $agentId,
                        'notification_type' => 'appointment_to_visit_alert',
                        'notification_id' => 'visit_' . $agentId . '_' . now()->format('YmdHis'),
                        'title' => $title,
                        'message' => $body,
                        'notification_type_label' => 'reminder',
                        'icon' => 'calendar',
                        'priority' => 'high',
                        'action_data' => $actionData,
                    ]);
                    $stats['visit_sent']++;
                    $this->info("  ✓ Visit alert sent to agent #{$agentId} ({$count} followups)");
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Failed to send visit alert', ['agent_id' => $agentId, 'error' => $e->getMessage()]);
            }
        }
    }

    private function sendUnresolvedCaseAlerts(array &$stats): void
    {
        $assignments = EmiAgentAssignment::whereIn('status', ['assigned', 'visited'])
            ->whereDate('assigned_at', '<', now()->toDateString()) // assigned before today
            ->whereHas('emi', function ($q) {
                $q->where('status', '!=', 'paid')
                    ->whereDoesntHave('followups')   // no followup logged at all
                    ->whereDoesntHave('collections') // no collection logged
                    ->whereDoesntHave('visitLogs');  // no visit logged
            })
            ->with(['emi.loanAccount.client', 'agent.user'])
            ->get()
            ->groupBy('agent_id');

        foreach ($assignments as $agentId => $agentAssignments) {
            // Skip if already sent within the last 3 hours
            $alreadySent = AgentNotification::where('agent_id', $agentId)
                ->where('notification_type', 'unresolved_cases_alert')
                ->where('created_at', '>=', now()->subHours(3))
                ->exists();

            if ($alreadySent)
                continue;

            $agent = $agentAssignments->first()->agent;
            if (!$agent || !$agent->user)
                continue;

            $deviceTokens = $agent->user->userDevice()
                ->where('user_type', 'Agent')
                ->pluck('device_token')
                ->filter()->unique()->values()->toArray();

            if (empty($deviceTokens))
                continue;

            $count = $agentAssignments->count();
            $title = 'Unresolved Cases';
            $body = "You have {$count} unresolved case(s) from previous day(s). Please follow up.";

            $actionData = [
                'type' => 'unresolved_cases',
                'count' => (string) $count,
                'case_ids' => $agentAssignments->pluck('id')->map(fn($id) => (string) $id)->toArray(),
            ];

            try {
                $result = $this->pushService->sendPushNotification($deviceTokens, $title, $body, $actionData);
                $anySuccess = $result['success'] || collect($result['results'] ?? [])->contains('ok', true);

                if ($anySuccess) {
                    AgentNotification::create([
                        'agent_id' => $agentId,
                        'notification_type' => 'unresolved_cases_alert',
                        'notification_id' => 'unresolved_' . $agentId . '_' . now()->format('YmdHis'),
                        'title' => $title,
                        'message' => $body,
                        'notification_type_label' => 'alert',
                        'icon' => 'warning',
                        'priority' => 'high',
                        'action_data' => $actionData,
                    ]);
                    $stats['unresolved_sent']++;
                    $this->info("  ✓ Unresolved alert sent to agent #{$agentId} ({$count} cases)");
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Failed to send unresolved case alert', ['agent_id' => $agentId, 'error' => $e->getMessage()]);
            }
        }
    }
}
