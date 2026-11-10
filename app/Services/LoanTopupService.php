<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Emi;
use App\Models\EmiAgentAssignment;
use App\Models\LoanAccount;
use App\Models\LoanTopup;
use App\Models\Account\BankAccount;
use App\Services\Account\AccountingTags;
use App\Services\Account\BankTransactionsService;
use App\Services\Account\SocietyBankAccountingService;
use App\Services\AppNotificationService;
use App\Support\CalendarWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanTopupService
{
    protected EmiCalculator $emiCalculator;
    protected LoanPaymentService $loanPaymentService;
    protected AppNotificationService $notificationService;

    public function __construct(
        EmiCalculator $emiCalculator,
        LoanPaymentService $loanPaymentService,
        AppNotificationService $notificationService
    ) {
        $this->emiCalculator = $emiCalculator;
        $this->loanPaymentService = $loanPaymentService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get loan top-up eligibility, metrics and preview calculations with flexible tenure & frequency.
     */
    public function getTopupInfo(
        LoanAccount $loanAccount,
        ?float $topupAmount = null,
        $tenureOptions = null,
        ?float $customRate = null
    ): array {
        $loanAccount->loadMissing(['emis', 'loanApplication.client', 'loanApplication.product', 'product', 'topups']);

        $client = $loanAccount->client ?? optional($loanAccount->loanApplication)->client;
        $isOpenLoan = $loanAccount->isOpenLoan();
        $loanFrequency = $loanAccount->frequency; // default: daily, weekly, monthly
        $loanFrequencyLabel = $loanAccount->frequency_label;

        $isEligible = strtolower((string)$loanAccount->status) === 'active' && !(bool)$loanAccount->is_foreclosed;

        // Cutoff: last completely paid instalment
        $paidEmis = $loanAccount->emis->where('status', 'paid');
        $paidCount = $paidEmis->count();
        $lastPaidInstalment = $paidEmis->max('instalment_number') ?? 0;

        // Unpaid future EMIs
        $unpaidEmis = $loanAccount->emis->where('instalment_number', '>', $lastPaidInstalment);
        $remainingCount = $unpaidEmis->count();

        // Remaining principal
        if ($isOpenLoan) {
            $currentPrincipal = (float) $loanAccount->openLoanRemainingPrincipal();
        } else {
            $sumUnpaidPrincipals = (float) $unpaidEmis->sum(function ($emi) {
                $alreadyPaid = (float) ($emi->paid_amount ?? 0);
                $interestPart = (float) ($emi->interest_amount ?? 0);
                $principalPaid = max(0, $alreadyPaid - $interestPart);
                return max(0, (float) ($emi->principal_amount ?? 0) - $principalPaid);
            });

            if ($sumUnpaidPrincipals > 0.01) {
                $currentPrincipal = round($sumUnpaidPrincipals, 2);
            } else {
                $currentPrincipal = round((float) ($loanAccount->remaining_principal_balance ?? $loanAccount->outstanding_amount ?? 0), 2);
            }
        }

        // Parse tenure and frequency options
        $tenureMode = 'add';
        $additionalTenure = 0;
        $requestedNewRemainingTenure = null;
        $selectedFrequency = $loanFrequency;
        $selectedEmiDay = (int) ($loanAccount->emi_day ?: 1);
        $customFirstDueDate = null;

        if (is_array($tenureOptions)) {
            $tenureMode = $tenureOptions['tenure_mode'] ?? 'add';
            $additionalTenure = max(0, (int) ($tenureOptions['additional_tenure'] ?? 0));
            $requestedNewRemainingTenure = !empty($tenureOptions['new_remaining_tenure']) ? (int) $tenureOptions['new_remaining_tenure'] : null;
            if (!empty($tenureOptions['emi_day'])) {
                $selectedEmiDay = (int) $tenureOptions['emi_day'];
            }
            if (!empty($tenureOptions['first_due_date'])) {
                $customFirstDueDate = $tenureOptions['first_due_date'];
            }
        } elseif (is_numeric($tenureOptions)) {
            $additionalTenure = max(0, (int) $tenureOptions);
        }

        // Determine effective remaining tenure
        if ($tenureMode === 'set' && $requestedNewRemainingTenure > 0) {
            $newRemainingTenure = max(1, $requestedNewRemainingTenure);
            $effectiveAdditionalTenure = max(0, $newRemainingTenure - $remainingCount);
        } elseif ($tenureMode === 'keep') {
            $newRemainingTenure = max(1, $remainingCount);
            $effectiveAdditionalTenure = 0;
        } else { // 'add'
            $effectiveAdditionalTenure = $additionalTenure;
            $newRemainingTenure = max(1, $remainingCount + $additionalTenure);
        }

        // Next due date calculation
        $nextEmi = $loanAccount->emis
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sortBy('instalment_number')
            ->first();

        $defaultNextDueDate = $nextEmi && $nextEmi->due_date
            ? Carbon::parse($nextEmi->due_date)->format('Y-m-d')
            : $this->calculateNextDueDateFromNow($loanAccount, $selectedFrequency, $selectedEmiDay);

        $nextDueDate = $customFirstDueDate ?: $defaultNextDueDate;

        $currentRate = (float) ($loanAccount->interest_rate ?? 0);
        $currentEmiAmount = (float) ($loanAccount->emi_amount ?? optional($nextEmi)->total_amount ?? 0);

        $bankAccounts = BankAccount::where('is_active', true)->orderBy('account_name')->get(['id', 'account_name', 'bank_name', 'account_number', 'current_balance']);

        $response = [
            'loan_account_id' => $loanAccount->id,
            'account_number' => $loanAccount->customer_loan_account_number ?? $loanAccount->account_number,
            'client_id' => optional($client)->id,
            'client_name' => optional($client)->client_name,
            'client_phone' => optional($client)->phone_number ?? optional($client)->client_phone,
            'is_eligible' => $isEligible,
            'status' => $loanAccount->status,
            'loan_mode' => $loanAccount->loan_mode ?? 'emi',
            'is_open_loan' => $isOpenLoan,
            'frequency' => $selectedFrequency,
            'loan_frequency' => $loanFrequency,
            'frequency_label' => ucfirst($selectedFrequency),
            'emi_day' => $selectedEmiDay,
            'current_loan_amount' => (float) $loanAccount->loan_amount,
            'current_outstanding_amount' => (float) $loanAccount->outstanding_amount,
            'current_principal_balance' => $currentPrincipal,
            'current_interest_rate' => $currentRate,
            'current_emi_amount' => $currentEmiAmount,
            'paid_emis_count' => $paidCount,
            'remaining_emis_count' => $remainingCount,
            'total_tenure' => (int) $loanAccount->tenure,
            'next_due_date' => $nextDueDate,
            'total_topups_count' => $loanAccount->topups->count(),
            'total_topup_amount' => (float) ($loanAccount->topup_amount ?? 0),
            'bank_accounts' => $bankAccounts,
        ];

        // If top-up amount specified, generate preview
        if ($topupAmount !== null && $topupAmount > 0) {
            $effectiveRate = ($customRate !== null && $customRate > 0) ? (float) $customRate : $currentRate;
            $newPrincipalBase = round($currentPrincipal + $topupAmount, 2);

            $product = $loanAccount->product ?? optional($loanAccount->loanApplication)->product;
            $interestType = $product ? ($product->interest_type ?? 'flat') : 'flat';

            $sampleSchedule = [];

            if ($isOpenLoan) {
                // Open loan: dynamic interest per period
                $ratePerPeriod = match ($selectedFrequency) {
                    'daily' => ($effectiveRate / 100) / 365,
                    'weekly' => ($effectiveRate / 100) / 52,
                    default => ($effectiveRate / 100) / 12,
                };
                $newEmiAmount = round($newPrincipalBase * $ratePerPeriod, 2);
                $totalNewPayable = round((float)$loanAccount->paid_amount + $newPrincipalBase, 2);
                $firstNewDueDate = $nextDueDate;
            } else {
                // Generate preview schedule
                if ($customFirstDueDate) {
                    $startDateForCalc = $this->resolveCalcBaseDateFromFirstDueDate($customFirstDueDate, $selectedFrequency);
                } else {
                    $startDateForCalc = $this->resolveCalcBaseDate($loanAccount, $lastPaidInstalment, $selectedFrequency);
                }

                $previewSchedule = $this->emiCalculator->generateSchedule(
                    principal: $newPrincipalBase,
                    annualRate: $effectiveRate,
                    term: $newRemainingTenure,
                    startDate: $startDateForCalc,
                    emiDay: $selectedEmiDay,
                    frequency: $selectedFrequency,
                    interestType: $interestType
                );

                $newEmiAmount = (float) $previewSchedule['emi'];
                $totalNewPayable = round((float)$loanAccount->paid_amount + (float)$previewSchedule['total_payment'], 2);
                $firstNewDueDate = $previewSchedule['schedule'][0]['due_date'] ?? $nextDueDate;

                // Provide first 5 installments for visual preview
                $sampleSchedule = array_map(function ($row, $idx) use ($lastPaidInstalment) {
                    return [
                        'instalment_number' => $lastPaidInstalment + $idx + 1,
                        'due_date' => $row['due_date'],
                        'principal' => (float)$row['principal'],
                        'interest' => (float)$row['interest'],
                        'emi_amount' => (float)$row['emi_amount'],
                        'remaining_balance' => (float)$row['remaining_balance'],
                    ];
                }, array_slice($previewSchedule['schedule'], 0, 5), array_keys(array_slice($previewSchedule['schedule'], 0, 5)));
            }

            $unitLabel = match ($selectedFrequency) {
                'daily' => 'Days',
                'weekly' => 'Weeks',
                default => 'Months',
            };

            $response['preview'] = [
                'topup_amount' => $topupAmount,
                'tenure_mode' => $tenureMode,
                'additional_tenure' => $effectiveAdditionalTenure,
                'new_remaining_tenure' => $newRemainingTenure,
                'new_total_tenure' => $paidCount + $newRemainingTenure,
                'new_principal_balance' => $newPrincipalBase,
                'effective_interest_rate' => $effectiveRate,
                'new_emi_amount' => $newEmiAmount,
                'total_new_payable' => $totalNewPayable,
                'first_new_due_date' => $firstNewDueDate,
                'frequency' => $selectedFrequency,
                'frequency_label' => ucfirst($selectedFrequency),
                'unit_label' => $unitLabel,
                'emi_day' => $selectedEmiDay,
                'interest_type' => $interestType,
                'sample_schedule' => $sampleSchedule,
            ];
        }

        return $response;
    }

    /**
     * Process a Top-Up on an ongoing loan and regenerate the schedule based on selected frequency and tenure options.
     */
    public function processTopup(int|string $loanAccountId, array $data, ?int $processedById = null): array
    {
        $loanAccount = LoanAccount::with(['emis', 'loanApplication.client', 'loanApplication.product', 'product'])
            ->findOrFail($loanAccountId);

        if (strtolower((string)$loanAccount->status) !== 'active' || (bool)$loanAccount->is_foreclosed) {
            return [
                'success' => false,
                'message' => 'Loan is not active or has been foreclosed/closed.',
            ];
        }

        $topupAmount = round((float) ($data['topup_amount'] ?? 0), 2);
        if ($topupAmount <= 0) {
            return [
                'success' => false,
                'message' => 'Top-up amount must be greater than zero.',
            ];
        }

        // Top-up always keeps the loan's original repayment frequency
        $frequency = $loanAccount->frequency;

        $emiDay = !empty($data['emi_day']) ? (int) $data['emi_day'] : (int) ($loanAccount->emi_day ?: 1);
        $customFirstDueDate = !empty($data['first_due_date']) ? $data['first_due_date'] : null;

        $customRate = isset($data['interest_rate']) && (float)$data['interest_rate'] > 0
            ? (float)$data['interest_rate']
            : (float)($loanAccount->interest_rate ?? 0);

        $processingFee = max(0, round((float) ($data['processing_fee'] ?? 0), 2));
        $netDisbursedAmount = max(0, round($topupAmount - $processingFee, 2));

        $paymentMethod = $data['payment_method'] ?? 'bank_transfer';
        $internalBankAccountId = !empty($data['internal_bank_account_id']) ? (int)$data['internal_bank_account_id'] : null;
        $utrNumber = $data['utr_number'] ?? null;
        $remarks = $data['remarks'] ?? 'Top-Up Loan disbursed';

        $disbursedAt = !empty($data['disbursed_at'])
            ? Carbon::parse($data['disbursed_at'])
            : Carbon::now();

        $client = $loanAccount->client ?? optional($loanAccount->loanApplication)->client;
        $isOpenLoan = $loanAccount->isOpenLoan();

        // 1. Identify Paid vs Unpaid EMIs
        $paidEmis = $loanAccount->emis()->where('status', 'paid')->get();
        $paidCount = $paidEmis->count();
        $lastPaidInstalment = $paidEmis->max('instalment_number') ?? 0;
        $unpaidEmis = $loanAccount->emis()->where('instalment_number', '>', $lastPaidInstalment)->get();
        $remainingCount = $unpaidEmis->count();

        // Tenure handling: 'add', 'set', 'keep'
        $tenureMode = $data['tenure_mode'] ?? 'add';
        if ($tenureMode === 'set' && !empty($data['new_remaining_tenure'])) {
            $newRemainingTenure = max(1, (int)$data['new_remaining_tenure']);
            $additionalTenure = max(0, $newRemainingTenure - $remainingCount);
        } elseif ($tenureMode === 'keep') {
            $newRemainingTenure = max(1, $remainingCount);
            $additionalTenure = 0;
        } else {
            $additionalTenure = max(0, (int) ($data['additional_tenure'] ?? 0));
            $newRemainingTenure = max(1, $remainingCount + $additionalTenure);
        }

        // Calculate remaining principal before topup
        if ($isOpenLoan) {
            $oldRemainingPrincipal = (float) $loanAccount->openLoanRemainingPrincipal();
        } else {
            $sumUnpaidPrincipals = (float) $unpaidEmis->sum(function ($emi) {
                $alreadyPaid = (float) ($emi->paid_amount ?? 0);
                $interestPart = (float) ($emi->interest_amount ?? 0);
                $principalPaid = max(0, $alreadyPaid - $interestPart);
                return max(0, (float) ($emi->principal_amount ?? 0) - $principalPaid);
            });

            if ($sumUnpaidPrincipals > 0.01) {
                $oldRemainingPrincipal = round($sumUnpaidPrincipals, 2);
            } else {
                $oldRemainingPrincipal = round((float) ($loanAccount->remaining_principal_balance ?? $loanAccount->outstanding_amount ?? 0), 2);
            }
        }

        $newPrincipalBase = round($oldRemainingPrincipal + $topupAmount, 2);
        $previousLoanAmount = (float) $loanAccount->loan_amount;
        $newLoanAmount = round($previousLoanAmount + $topupAmount, 2);
        $previousEmiAmount = (float) ($loanAccount->emi_amount ?? 0);
        $previousOutstanding = (float) $loanAccount->outstanding_amount;
        $oldTenure = (int) $loanAccount->tenure;

        DB::beginTransaction();
        try {
            $nextDueDate = null;
            $newEmiAmount = $previousEmiAmount;

            if ($isOpenLoan) {
                // Open Loan / Kandhuvatti (interest only)
                $ratePerPeriod = match ($frequency) {
                    'daily' => ($customRate / 100) / 365,
                    'weekly' => ($customRate / 100) / 52,
                    default => ($customRate / 100) / 12,
                };
                $newEmiAmount = round($newPrincipalBase * $ratePerPeriod, 2);
                $newTenure = 0;
                $newRemainingTenure = 0;

                // Sync open loan cycles or update pending cycles
                $pendingCycles = $loanAccount->emis()->where('status', '!=', 'paid')->get();
                foreach ($pendingCycles as $cycle) {
                    $cycle->update([
                        'interest_amount' => $newEmiAmount,
                        'total_amount' => $newEmiAmount,
                        'total_due' => $newEmiAmount,
                        'pending_amount' => max(0, $newEmiAmount - (float)$cycle->paid_amount),
                        'closing_balance' => $newPrincipalBase,
                    ]);
                }

                $nextCycle = $pendingCycles->sortBy('instalment_number')->first();
                $nextDueDate = $nextCycle && $nextCycle->due_date
                    ? Carbon::parse($nextCycle->due_date)->format('Y-m-d')
                    : $this->calculateNextDueDateFromNow($loanAccount, $frequency, $emiDay);

            } else {
                // Standard EMI Loan: Daily / Weekly / Monthly
                $newTenure = $paidCount + $newRemainingTenure;

                // Carry forward partial balance from ongoing EMI if any
                $carryForwardBalance = (float) $loanAccount->emis()
                    ->where('instalment_number', '<=', $lastPaidInstalment)
                    ->whereIn('status', ['partial', 'overdue'])
                    ->sum('pending_amount');

                // Delete future unpaid EMIs
                $loanAccount->emis()->where('instalment_number', '>', $lastPaidInstalment)->forceDelete();

                // Base date for schedule generation
                if ($customFirstDueDate) {
                    $startDateForCalc = $this->resolveCalcBaseDateFromFirstDueDate($customFirstDueDate, $frequency);
                } else {
                    $startDateForCalc = $this->resolveCalcBaseDate($loanAccount, $lastPaidInstalment, $frequency);
                }

                $product = $loanAccount->product ?? optional($loanAccount->loanApplication)->product;
                $interestType = $product ? ($product->interest_type ?? 'flat') : 'flat';

                $scheduleData = $this->emiCalculator->generateSchedule(
                    principal: $newPrincipalBase,
                    annualRate: $customRate,
                    term: $newRemainingTenure,
                    startDate: $startDateForCalc,
                    emiDay: $emiDay,
                    frequency: $frequency,
                    interestType: $interestType
                );

                $newEmiAmount = (float) $scheduleData['emi'];

                // Insert regenerated EMIs
                $assignedAgentId = optional($client)->assigned_to;

                foreach ($scheduleData['schedule'] as $index => $item) {
                    $instalmentNumber = $lastPaidInstalment + $index + 1;
                    $totalAmt = (float) $item['emi_amount'];

                    // Inject carry forward into first regenerated EMI
                    $prevBal = ($index === 0 && $carryForwardBalance > 0) ? round($carryForwardBalance, 2) : 0.00;
                    $recordTotalDue = round($totalAmt + $prevBal, 2);

                    if ($index === 0) {
                        $nextDueDate = $item['due_date'];
                    }

                    $newEmi = Emi::create([
                        'loan_account_id' => $loanAccount->id,
                        'instalment_number' => $instalmentNumber,
                        'principal_amount' => $item['principal'],
                        'interest_amount' => $item['interest'],
                        'total_amount' => $totalAmt,
                        'due_date' => $item['due_date'],
                        'opening_balance' => $item['opening_balance'] ?? 0,
                        'closing_balance' => $item['closing_balance'] ?? 0,
                        'previous_balance' => $prevBal,
                        'total_due' => $recordTotalDue,
                        'pending_amount' => $recordTotalDue,
                        'paid_amount' => 0,
                        'status' => 'pending',
                        'is_partial_paid' => false,
                    ]);

                    if ($assignedAgentId) {
                        EmiAgentAssignment::updateOrCreate(
                            ['emi_id' => $newEmi->id],
                            [
                                'agent_id' => $assignedAgentId,
                                'status' => 'assigned',
                                'assigned_at' => now(),
                                'remarks' => 'Auto-assigned upon loan top-up regeneration'
                            ]
                        );
                    }
                }
            }

            // Calculate new total payable
            $totalPastPaid = (float) DB::table('emis')
                ->where('loan_account_id', $loanAccount->id)
                ->where('instalment_number', '<=', $lastPaidInstalment)
                ->whereNull('deleted_at')
                ->sum('paid_amount');

            $totalFuturePayable = (float) DB::table('emis')
                ->where('loan_account_id', $loanAccount->id)
                ->where('instalment_number', '>', $lastPaidInstalment)
                ->whereNull('deleted_at')
                ->sum('total_amount');

            $newTotalPayable = round($totalPastPaid + $totalFuturePayable, 2);
            $newOutstanding = round($totalFuturePayable, 2);

            // Update Loan Account
            $loanAccount->update([
                'loan_amount' => $newLoanAmount,
                'topup_amount' => round((float)($loanAccount->topup_amount ?? 0) + $topupAmount, 2),
                'disbursed_amount' => round((float)($loanAccount->disbursed_amount ?? 0) + $netDisbursedAmount, 2),
                'interest_rate' => $customRate,
                'emi_amount' => $newEmiAmount,
                'emi_day' => $emiDay,
                'tenure' => $isOpenLoan ? 0 : $newTenure,
                'total_payable' => $newTotalPayable,
                'outstanding_amount' => $newOutstanding,
            ]);

            $this->loanPaymentService->syncLoanTotals($loanAccount->id);
            $this->loanPaymentService->syncEmiBalances($loanAccount->id);
            $loanAccount->refresh();

            // Record in loan_topups audit table
            $topupRecord = LoanTopup::create([
                'loan_account_id' => $loanAccount->id,
                'client_id' => optional($client)->id ?? $loanAccount->client_id,
                'topup_amount' => $topupAmount,
                'previous_loan_amount' => $previousLoanAmount,
                'new_loan_amount' => $newLoanAmount,
                'previous_outstanding_amount' => $previousOutstanding,
                'new_outstanding_amount' => (float)$loanAccount->outstanding_amount,
                'previous_emi_amount' => $previousEmiAmount,
                'new_emi_amount' => $newEmiAmount,
                'interest_rate' => $customRate,
                'previous_tenure' => $oldTenure,
                'new_tenure' => (int)$loanAccount->tenure,
                'additional_tenure' => $additionalTenure,
                'frequency' => $frequency,
                'emi_day' => $emiDay,
                'processing_fee' => $processingFee,
                'disbursed_amount' => $netDisbursedAmount,
                'payment_method' => $paymentMethod,
                'internal_bank_account_id' => $internalBankAccountId,
                'utr_number' => $utrNumber,
                'disbursed_at' => $disbursedAt,
                'next_due_date' => $nextDueDate,
                'processed_by' => $processedById ?? auth()->id(),
                'remarks' => $remarks,
            ]);

            // Bank Disbursement Debit
            if ($internalBankAccountId && $netDisbursedAmount > 0) {
                try {
                    $bankTxService = app(BankTransactionsService::class);
                    $txDescription = 'Loan Top-Up Disbursement for Account ' .
                        ($loanAccount->customer_loan_account_number ?? $loanAccount->account_number) .
                        ' - ' . optional($client)->client_name;

                    $bankTxService->createLoanDisbursementTransaction(
                        $internalBankAccountId,
                        $netDisbursedAmount,
                        $utrNumber,
                        $txDescription,
                        $disbursedAt,
                        AccountingTags::MODULE_LOAN,
                        AccountingTags::ENTRY_TOPUP
                    );
                } catch (\Throwable $e) {
                    Log::warning('Loan Top-Up bank transaction failed: ' . $e->getMessage(), [
                        'loan_account_id' => $loanAccount->id,
                        'bank_account_id' => $internalBankAccountId,
                    ]);
                }
            }

            // Processing Fee Revenue Record
            if ($internalBankAccountId && $processingFee > 0) {
                try {
                    $bankAccounting = app(SocietyBankAccountingService::class);
                    $bankAccounting->postExternalRevenue(
                        'Loan Top-Up Fee',
                        'LOAN-TOPUP-FEE',
                        $processingFee,
                        "Loan top-up fee — {$loanAccount->customer_loan_account_number} — " . optional($client)->client_name,
                        ($utrNumber ?: $loanAccount->customer_loan_account_number) . '-TOPUPFEE',
                        $disbursedAt->toDateString(),
                        $internalBankAccountId,
                        AccountingTags::MODULE_LOAN,
                        AccountingTags::ENTRY_PROC_FEE
                    );
                } catch (\Throwable $e) {
                    Log::warning('Loan top-up fee revenue record failed: ' . $e->getMessage());
                }
            }

            // Customer, Admin & Agent Notifications
            if ($client) {
                $this->sendTopupNotifications($client, $loanAccount, $topupRecord, $nextDueDate);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Top-Up Loan of ₹' . number_format($topupAmount, 2) . ' processed and ' . ucfirst($frequency) . ' EMI schedule regenerated successfully.',
                'data' => [
                    'topup_id' => $topupRecord->id,
                    'loan_account_id' => $loanAccount->id,
                    'account_number' => $loanAccount->customer_loan_account_number ?? $loanAccount->account_number,
                    'topup_amount' => $topupAmount,
                    'new_loan_amount' => $newLoanAmount,
                    'previous_emi_amount' => $previousEmiAmount,
                    'new_emi_amount' => $newEmiAmount,
                    'frequency' => $frequency,
                    'next_due_date' => $nextDueDate,
                    'new_outstanding' => (float)$loanAccount->outstanding_amount,
                    'new_tenure' => (int)$loanAccount->tenure,
                ],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Loan Top-Up failed: ' . $e->getMessage(), [
                'loan_account_id' => $loanAccountId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process top-up: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send in-app, mobile push, admin and agent notifications upon top-up.
     */
    protected function sendTopupNotifications(
        Client $client,
        LoanAccount $loanAccount,
        LoanTopup $topup,
        ?string $nextDueDate
    ): void {
        $accountNo = $loanAccount->customer_loan_account_number ?? $loanAccount->account_number;
        $clientName = $client->client_name ?? 'Client';
        $topupAmtFormatted = '₹' . number_format($topup->topup_amount, 2);
        $newEmiFormatted = '₹' . number_format($topup->new_emi_amount, 2);
        $freqText = ucfirst($topup->frequency ?? 'Monthly');
        $dueDateText = $nextDueDate ? Carbon::parse($nextDueDate)->format('d M Y') : 'the upcoming cycle';

        $customerTitle = 'Loan Top-Up Disbursed';
        $customerMsg = "Your Loan #{$accountNo} has been topped up with {$topupAmtFormatted}. Your revised {$freqText} EMI is {$newEmiFormatted} due on {$dueDateText}.";

        $adminTitle = 'Loan Top-Up Disbursed';
        $adminMsg = "Loan #{$accountNo} for {$clientName} was topped up with {$topupAmtFormatted}. Revised {$freqText} EMI is {$newEmiFormatted}.";

        $agentTitle = 'Client Loan Top-Up Disbursed';
        $agentMsg = "Loan #{$accountNo} for {$clientName} was topped up with {$topupAmtFormatted}. Next EMI {$newEmiFormatted} is due on {$dueDateText}.";

        try {
            $this->notificationService->notifyAll($client, [
                'type' => 'loan_topup',
                'title' => $customerTitle,
                'admin_message' => $adminMsg,
                'customer_title' => $customerTitle,
                'customer_message' => $customerMsg,
                'agent_title' => $agentTitle,
                'agent_message' => $agentMsg,
                'link' => route('client-loan-emi-details', $loanAccount->id),
                'icon' => 'ri-add-circle-line',
                'related_id' => $loanAccount->id,
                'fcm_type' => 'loan_topup',
                'data' => [
                    'screen' => 'loan_details',
                    'loan_account_id' => (string) $loanAccount->id,
                    'account_number' => (string) $accountNo,
                    'topup_amount' => (string) $topup->topup_amount,
                    'new_emi_amount' => (string) $topup->new_emi_amount,
                    'frequency' => (string) ($topup->frequency ?? 'monthly'),
                    'next_due_date' => (string) ($nextDueDate ?? ''),
                    'action' => 'topup_disbursed',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('AppNotificationService failed on topup: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the base date for regenerating the schedule from the last paid installment.
     */
    protected function resolveCalcBaseDate(LoanAccount $loanAccount, int $lastPaidInstalment, string $frequency): string
    {
        $lastPaid = $loanAccount->emis
            ->where('instalment_number', $lastPaidInstalment)
            ->first();

        if ($lastPaid && $lastPaid->due_date) {
            return Carbon::parse($lastPaid->due_date)->format('Y-m-d');
        }

        // If no paid EMI exists, use disbursement date or today, stepped back by 1 period
        // because generateSchedule adds 1 period for the first installment.
        $base = $loanAccount->disbursed_at ? Carbon::parse($loanAccount->disbursed_at) : Carbon::today();
        if ($frequency === 'daily') {
            $base->subDay();
        } elseif ($frequency === 'weekly') {
            $base = CalendarWeek::addWeeks($base, -1);
        } else {
            $base->subMonth();
        }

        return $base->format('Y-m-d');
    }

    /**
     * Resolve base date when admin explicitly specifies the first due date.
     */
    protected function resolveCalcBaseDateFromFirstDueDate(string $firstDueDate, string $frequency): string
    {
        $date = Carbon::parse($firstDueDate);
        if ($frequency === 'daily') {
            return $date->subDay()->format('Y-m-d');
        } elseif ($frequency === 'weekly') {
            return CalendarWeek::addWeeks($date, -1)->format('Y-m-d');
        } else {
            return $date->subMonth()->format('Y-m-d');
        }
    }

    /**
     * Fallback calculation for next due date from now.
     */
    protected function calculateNextDueDateFromNow(LoanAccount $loanAccount, string $frequency, ?int $emiDay = null): string
    {
        $date = Carbon::today();
        if ($frequency === 'daily') {
            return $date->addDay()->format('Y-m-d');
        } elseif ($frequency === 'weekly') {
            $day = ($emiDay !== null && $emiDay >= 1 && $emiDay <= 7) ? $emiDay : ($loanAccount->emi_day ?: 1);
            return CalendarWeek::alignToIsoWeekday($date->copy()->addWeek(), (int) $day)->format('Y-m-d');
        } else {
            $date->addMonth();
            $targetDay = ($emiDay !== null && $emiDay >= 1 && $emiDay <= 31) ? $emiDay : ($loanAccount->emi_day ?: $date->day);
            $date->day = min($targetDay, $date->daysInMonth);
            return $date->format('Y-m-d');
        }
    }
}
