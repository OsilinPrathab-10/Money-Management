<?php

namespace App\Observers;

use App\Models\Agent;
use App\Models\AgentNotification;
use App\Models\EmiAgentAssignment;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;

class EmiAgentAssignmentObserver
{
    /** @var array<int, list<int>> */
    protected static array $pendingPushByAgent = [];

    protected static bool $flushScheduled = false;

    public function created(EmiAgentAssignment $assignment): void
    {
        $this->recordAssignment($assignment);
    }

    public function updated(EmiAgentAssignment $assignment): void
    {
        if ($assignment->wasChanged('agent_id') && $assignment->agent_id) {
            $this->recordAssignment($assignment);
        }
    }

    protected function recordAssignment(EmiAgentAssignment $assignment): void
    {
        try {
            $this->storeInboxNotification($assignment);
            $agentId = (int) $assignment->agent_id;
            if ($agentId <= 0) {
                return;
            }
            self::$pendingPushByAgent[$agentId][] = (int) $assignment->id;
            $this->scheduleFlush();
        } catch (\Throwable $e) {
            Log::error('Failed to queue case assigned notification', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function storeInboxNotification(EmiAgentAssignment $assignment): void
    {
        $notificationId = 'assignment_' . $assignment->id;
        if (AgentNotification::where('notification_id', $notificationId)->exists()) {
            return;
        }

        $assignment->loadMissing(['emi.loanAccount.client']);
        $client = $assignment->emi?->loanAccount?->client;
        $customerName = $client?->client_name ?? 'a customer';
        $loanNumber = $assignment->emi?->loanAccount?->account_number
            ?? $assignment->emi?->loanAccount?->customer_loan_account_number
            ?? 'N/A';
        $pendingAmount = $assignment->emi?->pending_amount ?? 0;

        AgentNotification::create([
            'agent_id' => $assignment->agent_id,
            'notification_type' => 'case_assigned',
            'notification_id' => $notificationId,
            'title' => 'New Case Assigned',
            'message' => "A new case has been assigned to you: {$customerName} (Loan: {$loanNumber}) - ₹" . number_format((float) $pendingAmount, 2) . ' pending',
            'notification_type_label' => 'assignment',
            'icon' => 'briefcase',
            'priority' => 'high',
            'action_data' => [
                'type' => 'case_assigned',
                'assignment_id' => (string) $assignment->id,
                'emi_id' => (string) $assignment->emi_id,
                'loan_account_id' => (string) ($assignment->emi?->loan_account_id ?? ''),
            ],
        ]);
    }

    protected function scheduleFlush(): void
    {
        if (self::$flushScheduled) {
            return;
        }
        self::$flushScheduled = true;

        dispatch(function () {
            $batches = self::$pendingPushByAgent;
            self::$pendingPushByAgent = [];
            self::$flushScheduled = false;
            self::flushPushes($batches);
        })->afterResponse();
    }

    public static function flushPushes(array $batches): void
    {
        if ($batches === []) {
            return;
        }

        $push = app(PushNotificationService::class);

        foreach ($batches as $agentId => $assignmentIds) {
            $assignmentIds = array_values(array_unique(array_filter($assignmentIds)));
            if ($assignmentIds === []) {
                continue;
            }

            $agent = Agent::with('user')->find($agentId);
            if (! $agent || ! $agent->user) {
                continue;
            }

            $count = count($assignmentIds);
            $first = EmiAgentAssignment::with(['emi.loanAccount.client'])->find($assignmentIds[0]);

            if ($count === 1 && $first) {
                $client = $first->emi?->loanAccount?->client;
                $customerName = $client?->client_name ?? 'a customer';
                $loanNumber = $first->emi?->loanAccount?->account_number
                    ?? $first->emi?->loanAccount?->customer_loan_account_number
                    ?? 'N/A';
                $pendingAmount = $first->emi?->pending_amount ?? 0;
                $title = 'New Case Assigned';
                $body = "A new case has been assigned to you: {$customerName} (Loan: {$loanNumber}) - ₹" . number_format((float) $pendingAmount, 2) . ' pending';
            } else {
                $title = 'New Cases Assigned';
                $body = $count . ' new cases have been assigned to you.';
            }

            try {
                $push->sendToAgent($agent, $title, $body, 'case_assigned', [
                    'assignment_id' => (string) $assignmentIds[0],
                    'count' => (string) $count,
                ]);
                Log::info("Case assigned notification sent to agent #{$agentId}", ['count' => $count]);
            } catch (\Throwable $e) {
                Log::error('Failed to send case assigned push', [
                    'agent_id' => $agentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
