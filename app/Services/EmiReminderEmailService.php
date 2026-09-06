<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\EmiReminderLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EmiReminderEmailService
{
    /**
     * Send EMI reminder email
     *
     * @param Emi $emi
     * @param string $reminderType
     * @return array
     */
    public function sendReminderEmail(Emi $emi, string $reminderType): array
    {
        try {
            // Load relationships
            $emi->load([
                'loanAccount.loanApplication.client',
                'loanAccount.client'
            ]);

            $loanAccount = $emi->loanAccount;
            $client = optional($loanAccount->loanApplication)->client ?? $loanAccount->client;

            // Check if client exists and has email
            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'Client not found for this EMI'
                ];
            }

            if (empty($client->client_email)) {
                return [
                    'success' => false,
                    'message' => 'Client does not have an email address'
                ];
            }

            // Get email template based on reminder type
            $templateIdentifier = $this->getTemplateIdentifier($reminderType);
            $emailTemplate = EmailTemplate::where('identifier', $templateIdentifier)
                ->where('status', true)
                ->first();

            if (!$emailTemplate) {
                return [
                    'success' => false,
                    'message' => "Email template '{$templateIdentifier}' not found or inactive"
                ];
            }

            // Get base placeholders from DocumentPlaceholderService
            $placeholders = DocumentPlaceholderService::getReplacements($loanAccount);

            // Add EMI-specific placeholders
            $dpd = $this->calculateDPD($emi->due_date);
            $placeholders = array_merge($placeholders, [
                '{{emi_number}}' => $emi->instalment_number,
                '{{emi_instalment_number}}' => $emi->instalment_number,
                '{{emi_amount}}' => number_format($emi->total_amount, 2),
                '{{emi_total_amount}}' => number_format($emi->total_amount, 2),
                '{{due_date}}' => $emi->due_date->format('d-m-Y'),
                '{{emi_due_date}}' => $emi->due_date->format('d-m-Y'),
                '{{dpd}}' => $dpd,
                '{{days_past_due}}' => $dpd,
                '{{penalty_amount}}' => number_format($emi->penalty_amount ?? 0, 2),
                '{{emi_penalty}}' => number_format($emi->penalty_amount ?? 0, 2),
                '{{principal_amount}}' => number_format($emi->principal_amount, 2),
                '{{interest_amount}}' => number_format($emi->interest_amount, 2),
                '{{pending_amount}}' => number_format($emi->pending_amount ?? $emi->total_amount, 2),
            ]);

            // Replace placeholders in subject and body
            $subject = DocumentPlaceholderService::replacePlaceholders(
                $emailTemplate->subject,
                $placeholders
            );

            $body = DocumentPlaceholderService::replacePlaceholders(
                $emailTemplate->email_body,
                $placeholders
            );

            // Send email
            Mail::to($client->client_email)->send(
                new \App\Mail\EmiReminderMail($loanAccount, $client, $emi, $subject, $body)
            );

            // Log the reminder
            EmiReminderLog::create([
                'loan_account_id' => $loanAccount->id,
                'emi_id' => $emi->id,
                'reminder_type' => $reminderType,
                'sent_at' => now(),
                'channel' => 'email',
            ]);

            Log::info('EMI reminder email sent successfully', [
                'emi_id' => $emi->id,
                'loan_account_id' => $loanAccount->id,
                'client_email' => $client->client_email,
                'reminder_type' => $reminderType,
            ]);

            return [
                'success' => true,
                'message' => 'EMI reminder email sent successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send EMI reminder email', [
                'emi_id' => $emi->id,
                'reminder_type' => $reminderType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get template identifier based on reminder type
     *
     * @param string $reminderType
     * @return string
     */
    private function getTemplateIdentifier(string $reminderType): string
    {
        $mapping = [
            'before_due' => 'emi_before_due',
            'due_today' => 'emi_due_today',
            'overdue' => 'emi_overdue',
            'urgent_overdue' => 'emi_urgent_overdue',
        ];

        return $mapping[$reminderType] ?? 'emi_before_due';
    }

    /**
     * Calculate Days Past Due (DPD)
     *
     * @param Carbon $dueDate
     * @return int
     */
    private function calculateDPD($dueDate): int
    {
        $today = Carbon::now()->startOfDay();
        $due = Carbon::parse($dueDate)->startOfDay();
        
        return max(0, $today->diffInDays($due, false) * -1);
    }

    /**
     * Check if reminder already sent
     *
     * @param int $emiId
     * @param string $reminderType
     * @return bool
     */
    public function isReminderAlreadySent(int $emiId, string $reminderType): bool
    {
        return EmiReminderLog::where('emi_id', $emiId)
            ->where('reminder_type', $reminderType)
            ->exists();
    }
}
