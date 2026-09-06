<?php

namespace App\Observers;

use App\Models\EmiAgentAssignment;
use App\Models\AgentNotification;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;

class EmiAgentAssignmentObserver
{
    public function created(EmiAgentAssignment $assignment): void
    {
        try {
            $assignment->load(['agent.user', 'emi.loanAccount.client']);

            $agent = $assignment->agent;
            if (!$agent || !$agent->user) return;

            $deviceTokens = $agent->user->userDevice()
                ->where('user_type', 'Agent')
                ->pluck('device_token')
                ->filter()->unique()->values()->toArray();

            if (empty($deviceTokens)) return;

            $client = $assignment->emi?->loanAccount?->client;
            $customerName = $client?->client_name ?? 'a customer';
            $loanNumber = $assignment->emi?->loanAccount?->account_number ?? 'N/A';
            $pendingAmount = $assignment->emi?->pending_amount ?? 0;

            $title = 'New Case Assigned';
            $body = "A new case has been assigned to you: {$customerName} (Loan: {$loanNumber}) - ₹" . number_format($pendingAmount, 2) . " pending";

            $actionData = [
                'type' => 'case_assigned',
                'assignment_id' => (string) $assignment->id,
                'emi_id' => (string) $assignment->emi_id,
                'loan_account_id' => (string) ($assignment->emi?->loan_account_id ?? ''),
            ];

            $pushService = app(PushNotificationService::class);
            $result = $pushService->sendPushNotification($deviceTokens, $title, $body, $actionData);
            $anySuccess = $result['success'] || collect($result['results'] ?? [])->contains('ok', true);

            if ($anySuccess) {
                AgentNotification::create([
                    'agent_id' => $assignment->agent_id,
                    'notification_type' => 'case_assigned',
                    'notification_id' => 'assignment_' . $assignment->id,
                    'title' => $title,
                    'message' => $body,
                    'notification_type_label' => 'assignment',
                    'icon' => 'briefcase',
                    'priority' => 'high',
                    'action_data' => $actionData,
                ]);

                Log::info("Case assigned notification sent to agent #{$assignment->agent_id} for assignment #{$assignment->id}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to send case assigned notification', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
