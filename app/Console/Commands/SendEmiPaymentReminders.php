<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LoanAccount;
use App\Models\Emi;
use App\Services\EmiReminderEmailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendEmiPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emi:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send EMI payment reminder emails based on due dates and DPD';

    /**
     * EMI Reminder Email Service
     */
    protected $reminderService;

    /**
     * Create a new command instance.
     */
    public function __construct(EmiReminderEmailService $reminderService)
    {
        parent::__construct();
        $this->reminderService = $reminderService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting EMI payment reminder process...');
        
        $today = Carbon::now()->startOfDay();
        $stats = [
            'total_emis_checked' => 0,
            'reminders_sent' => 0,
            'reminders_skipped' => 0,
            'errors' => 0,
        ];

        // Fetch all active loan accounts
        $activeLoanAccounts = LoanAccount::where('status', 'active')
            ->with(['emis' => function($query) {
                $query->whereIn('status', ['pending', 'partial'])
                    ->orderBy('due_date', 'asc');
            }])
            ->get();

        $this->info("Found {$activeLoanAccounts->count()} active loan account(s).");

        foreach ($activeLoanAccounts as $loanAccount) {
            foreach ($loanAccount->emis as $emi) {
                $stats['total_emis_checked']++;
                
                // Skip if EMI is already paid
                if ($emi->status === 'paid') {
                    continue;
                }


                // Calculate DPD (Days Past Due)
                $dueDate = Carbon::parse($emi->due_date)->startOfDay();
                $dpd = $today->diffInDays($dueDate, false) * -1; // Positive = overdue, Negative = not yet due
                $daysUntilDue = $today->diffInDays($dueDate, false); // Positive = days until due


                // Determine reminder type based on DPD and days until due
                $reminderType = $this->determineReminderType($dpd, $daysUntilDue);

                if (!$reminderType) {
                    // No reminder needed for this EMI
                    continue;
                }

                // Check if reminder already sent
                if ($this->reminderService->isReminderAlreadySent($emi->id, $reminderType)) {
                    $stats['reminders_skipped']++;
                    $this->line("  - Skipped: EMI #{$emi->instalment_number} (Loan: {$loanAccount->account_number}) - {$reminderType} reminder already sent");
                    continue;
                }

                // Send reminder email
                try {
                    $result = $this->reminderService->sendReminderEmail($emi, $reminderType);
                    
                    if ($result['success']) {
                        $stats['reminders_sent']++;
                        $this->info("  ✓ Sent: EMI #{$emi->instalment_number} (Loan: {$loanAccount->account_number}) - {$reminderType} reminder");

                        // Send SMS if it's the 3-day reminder
                        if ($reminderType === 'before_due' && $daysUntilDue === 3) {
                            $client = optional($loanAccount->loanApplication)->client ?? $loanAccount->client;
                            if ($client && !empty($client->client_phone)) {
                                \App\Utils\SMSUtility::emiReminder(
                                    $client->client_phone,
                                    $client->client_name,
                                    $loanAccount->account_number,
                                    $emi->total_amount,
                                    $dueDate->format('d-m-Y')
                                );
                                $this->info("  ✓ SMS Sent: 3-day reminder to {$client->client_phone}");
                            }
                        }
                    } else {
                        $stats['errors']++;
                        $this->error("  ✗ Failed: EMI #{$emi->instalment_number} - {$result['message']}");
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $this->error("  ✗ Error: EMI #{$emi->instalment_number} - {$e->getMessage()}");
                    Log::error('EMI reminder command error', [
                        'emi_id' => $emi->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Display summary
        $this->newLine();
        $this->info('=== EMI Payment Reminder Summary ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total EMIs Checked', $stats['total_emis_checked']],
                ['Reminders Sent', $stats['reminders_sent']],
                ['Reminders Skipped (Duplicate)', $stats['reminders_skipped']],
                ['Errors', $stats['errors']],
            ]
        );

        Log::info('EMI payment reminder process completed', $stats);

        return 0;
    }

    /**
     * Determine reminder type based on DPD and days until due
     *
     * @param int $dpd Days Past Due (negative if not yet due)
     * @param int $daysUntilDue Days until due date
     * @return string|null
     */
    private function determineReminderType(int $dpd, int $daysUntilDue): ?string
    {
        // 3 days before due date
        if ($daysUntilDue === 3) {
            return 'before_due';
        }

        // 1 day before due date
        if ($daysUntilDue === 1) {
            return 'before_due';
        }

        // On due date
        if ($dpd === 0) {
            return 'due_today';
        }

        // 3 days overdue
        if ($dpd === 3) {
            return 'overdue';
        }

        // 7 days overdue (urgent)
        if ($dpd === 7) {
            return 'urgent_overdue';
        }

        // No reminder needed
        return null;
    }
}
