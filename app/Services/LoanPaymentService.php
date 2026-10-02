<?php

namespace App\Services;

use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\LoanConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Services\Account\AccountingTags;
use App\Services\Account\ChitAccountingService;
use App\Services\FixedDeposit\WalletService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LoanPaymentService
{
    /**
     * When true, processPayment does not fire PaymentReceivedEvent (use for bulk verify).
     */
    public static bool $suppressPaymentNotifications = false;

    /**
     * When true, skip opening/closing EMI+loan balance sync inside processPayment.
     */
    public static bool $suppressBalanceSync = false;
    /**
     * Calculate foreclosure principal, interest, charges and total (rounded to nearest rupee).
     */
    public function calculateForeclosureAmounts(LoanAccount $loanAccount, array $options = []): array
    {
        $foreclosureConfig = LoanConfiguration::getForeclosureConfig();
        $chargesPercentage = (float) ($options['charges_percentage']
            ?? $loanAccount->getForeclosureChargesPercentage()
            ?? optional($foreclosureConfig)->charges_percentage
            ?? 0);

        $ongoingEmi = $loanAccount->emis()
            ->whereNotIn('status', ['paid', 'carried_forward'])
            ->orderBy('instalment_number', 'asc')
            ->first();

        $unpaidEmis = $loanAccount->emis()->where('status', '!=', 'paid')->get();
        $sumOfEmiPrincipals = $unpaidEmis->sum(function ($emi) {
            $alreadyPaid = (float) ($emi->paid_amount ?? 0);
            $interestPart = (float) ($emi->interest_amount ?? 0);
            $principalPaid = max(0, $alreadyPaid - $interestPart);

            return max(0, (float) ($emi->principal_amount ?? 0) - $principalPaid);
        });

        if ($sumOfEmiPrincipals <= 0.01) {
            $ratio = ($loanAccount->loan_amount > 0 && $loanAccount->total_payable > 0)
                ? ($loanAccount->loan_amount / $loanAccount->total_payable)
                : 0.85;
            $outstandingAmount = round($loanAccount->outstanding_amount * $ratio);
        } else {
            $outstandingAmount = round($sumOfEmiPrincipals);
        }

        $interestOutstanding = $this->calculateForeclosureInterestOutstanding(
            $loanAccount,
            $outstandingAmount,
            $ongoingEmi
        );

        $foreclosureCharges = round(($outstandingAmount * $chargesPercentage) / 100);

        $extraChargePercent = 0;
        $extraChargeAmount = 0;
        if (!empty($options['override_mode']) && isset($options['extra_charge'])) {
            $extraChargePercent = (float) $options['extra_charge'];
            $extraChargeAmount = round(($outstandingAmount * $extraChargePercent) / 100);
        }

        // A settlement discount is a concession on interest only: it lowers what
        // the client pays and lowers the interest booked to revenue by the same
        // amount. It can never exceed the interest being charged.
        $discountPercentage = (float) ($options['discount_percentage'] ?? 0);
        $discountAmount = (float) ($options['discount_amount'] ?? 0);

        if ($discountPercentage > 0) {
            $discountAmount = round(($interestOutstanding * $discountPercentage) / 100);
        }

        $discountAmount = min(round(max(0, $discountAmount)), $interestOutstanding);
        $netInterest = round(max(0, $interestOutstanding - $discountAmount));

        $totalAmount = round(
            $outstandingAmount + $netInterest + $foreclosureCharges + $extraChargeAmount
        );

        return [
            'outstanding_amount' => $outstandingAmount,
            'interest_outstanding' => $interestOutstanding,
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount,
            'net_interest' => $netInterest,
            'foreclosure_charges' => $foreclosureCharges,
            'extra_charge_percent' => $extraChargePercent,
            'extra_charge_amount' => $extraChargeAmount,
            'total_amount' => $totalAmount,
            'charges_percentage' => $chargesPercentage,
            'includes_current_month_interest' => $this->foreclosureIncludesCurrentCycleInterest($loanAccount, $ongoingEmi),
        ];
    }

    /**
     * Interest for foreclosure: daily accrual since last payment, based on cycle frequency.
     */
    protected function calculateForeclosureInterestOutstanding(
        LoanAccount $loanAccount,
        float $outstandingAmount,
        ?Emi $ongoingEmi
    ): float {
        $lastPaidEmi = $loanAccount->emis()
            ->where('status', 'paid')
            ->orderByDesc('paid_date')
            ->first();

        $fromDate = $lastPaidEmi
            ? Carbon::parse($lastPaidEmi->paid_date)
            : Carbon::parse($loanAccount->disbursed_at);

        $days = max(0, (int) $fromDate->diffInDays(now()));
        
        // Determine cycle unit days
        $application = $loanAccount->loanApplication;
        $termUnit = $application ? strtolower((string)$application->term_unit) : 'monthly';
        
        if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
            $daysInCycle = 7;
        } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
            $daysInCycle = 1;
        } else {
            $daysInCycle = 30; // monthly
        }

        // Get interest portion from the ongoing EMI, or fallback to cycle interest calculation
        $interestPerEmi = 0;
        if ($ongoingEmi) {
            $interestPerEmi = (float)($ongoingEmi->interest_amount ?? 0);
        }
        
        if ($interestPerEmi <= 0) {
            if ($loanAccount->loan_mode === 'interest_only') {
                $interestPerEmi = $outstandingAmount * ((float)$loanAccount->interest_rate / 100);
            } else {
                $totalInterest = $loanAccount->loan_amount * ((float)$loanAccount->interest_rate / 100);
                $interestPerEmi = $loanAccount->tenure > 0 ? ($totalInterest / $loanAccount->tenure) : 0;
            }
        }

        // Accrued interest is based on fraction of current cycle elapsed
        $dailyRate = $interestPerEmi / $daysInCycle;
        $accruedInterest = round($dailyRate * $days);

        $currentCycleInterest = $this->resolveCurrentCycleForeclosureInterest($loanAccount, $ongoingEmi);

        if ($currentCycleInterest > 0) {
            return max($accruedInterest, $currentCycleInterest);
        }

        return $accruedInterest;
    }

    protected function foreclosureIncludesCurrentCycleInterest(LoanAccount $loanAccount, ?Emi $ongoingEmi): bool
    {
        if (!$ongoingEmi || !$ongoingEmi->due_date) {
            return false;
        }

        $dueDate = Carbon::parse($ongoingEmi->due_date);
        $application = $loanAccount->loanApplication;
        $termUnit = $application ? strtolower((string)$application->term_unit) : 'monthly';

        if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
            return $dueDate->isSameWeek(now());
        } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
            return $dueDate->isSameDay(now());
        }

        return $dueDate->isSameMonth(now()) && $dueDate->year === now()->year;
    }

    protected function resolveCurrentCycleForeclosureInterest(LoanAccount $loanAccount, ?Emi $ongoingEmi): float
    {
        if (!$this->foreclosureIncludesCurrentCycleInterest($loanAccount, $ongoingEmi)) {
            return 0;
        }

        $interestPart = (float) ($ongoingEmi->interest_amount ?? 0);
        $paidAmount = (float) ($ongoingEmi->paid_amount ?? 0);
        $interestPaid = min($paidAmount, $interestPart);
        $remainingEmiInterest = max(0, $interestPart - $interestPaid);

        if ($loanAccount->loan_mode === 'interest_only') {
            $priorPrincipalPaid = $loanAccount->emis()
                ->where('instalment_number', '<', $ongoingEmi->instalment_number)
                ->sum('principal_amount');
            $priorOutstanding = max(0, (float) $loanAccount->loan_amount - (float) $priorPrincipalPaid);
            $cycleInterest = round($priorOutstanding * ((float) $loanAccount->interest_rate / 100));

            return max($remainingEmiInterest, $cycleInterest);
        }

        return round($remainingEmiInterest);
    }

    /**
     * Unpaid open-loan cycle whose due date falls in the current day/week/month.
     */
    protected function resolveCurrentCycleEmi(LoanAccount $loanAccount): ?Emi
    {
        $unpaid = $loanAccount->emis()
            ->whereNotIn('status', ['paid', 'closed', 'carried_forward'])
            ->orderBy('instalment_number')
            ->get();

        return $unpaid->first(
            fn (Emi $emi) => $this->foreclosureIncludesCurrentCycleInterest($loanAccount, $emi)
        );
    }

    /**
     * Open loan: only the current day/week/month cycle is paid.
     * Overdue and other unpaid cycles are closed (not paid).
     */
    protected function settleEmisOnForeclosure(
        LoanAccount $loanAccount,
        ?Emi $ongoingEmi,
        float $interestOutstanding,
        $now,
        ?string $paymentReference = null
    ): void {
        $isOpenLoan = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
        $currentCycleEmi = $isOpenLoan
            ? $this->resolveCurrentCycleEmi($loanAccount)
            : $ongoingEmi;

        $loanAccount->emis()
            ->whereNotIn('status', ['paid', 'closed'])
            ->get()
            ->each(function ($emi) use ($now, $currentCycleEmi, $interestOutstanding, $isOpenLoan, $paymentReference) {
                $isCurrent = $currentCycleEmi && (int) $emi->id === (int) $currentCycleEmi->id;

                if ($isOpenLoan && ! $isCurrent) {
                    $emi->update([
                        'status' => 'closed',
                        'pending_amount' => 0,
                        'paid_date' => null,
                        'remarks' => trim(($emi->remarks ?? '') . ' [Foreclosed - closed]'),
                    ]);

                    return;
                }

                $payload = [
                    'status' => 'paid',
                    'paid_amount' => $emi->total_due,
                    'pending_amount' => 0,
                    'paid_date' => $now,
                    'remarks' => trim(($emi->remarks ?? '') . ' [Foreclosed settlement]'),
                ];

                if ($isCurrent && $interestOutstanding > 0) {
                    $payload['interest_amount'] = $interestOutstanding;
                } elseif (! $isOpenLoan && ! $isCurrent) {
                    $payload['interest_amount'] = 0;
                }

                if ($paymentReference) {
                    $payload['payment_reference'] = $paymentReference;
                }

                $emi->update($payload);
            });
    }

    /**
     * Normalize open-loan repayment frequency.
     */
    protected function resolveOpenLoanTermUnit(LoanAccount $loanAccount): string
    {
        $loanAccount->loadMissing('loanApplication');
        $termUnit = strtolower((string) (
            $loanAccount->loanApplication->term_unit
            ?? $loanAccount->term_unit
            ?? 'monthly'
        ));

        if (in_array($termUnit, ['week', 'weeks', 'weekly'], true)) {
            return 'weekly';
        }
        if (in_array($termUnit, ['day', 'days', 'daily'], true)) {
            return 'daily';
        }

        return 'monthly';
    }

    protected function openLoanDaysInCycle(string $termUnit): int
    {
        return match ($termUnit) {
            'weekly' => 7,
            'daily' => 1,
            default => 30,
        };
    }

    /**
     * Start of the current interest cycle (previous due date or disbursement).
     */
    protected function openLoanCycleStartDate(LoanAccount $loanAccount, Emi $emi): Carbon
    {
        $prev = Emi::where('loan_account_id', $loanAccount->id)
            ->where('instalment_number', '<', $emi->instalment_number)
            ->orderByDesc('instalment_number')
            ->first();

        if ($prev && $prev->due_date) {
            return Carbon::parse($prev->due_date)->startOfDay();
        }

        if ($loanAccount->disbursed_at) {
            return Carbon::parse($loanAccount->disbursed_at)->startOfDay();
        }

        if ($emi->due_date) {
            $days = $this->openLoanDaysInCycle($this->resolveOpenLoanTermUnit($loanAccount));

            return Carbon::parse($emi->due_date)->subDays($days)->startOfDay();
        }

        return now()->startOfDay();
    }

    /**
     * Full-cycle interest for an open-loan EMI (max charge for the period).
     */
    protected function openLoanFullCycleInterest(LoanAccount $loanAccount, Emi $emi): float
    {
        $stored = (float) ($emi->interest_amount ?? 0);
        if ($stored > 0.009 && ($emi->status === 'paid' || abs($stored - round($stored)) < 0.001)) {
            return (float) \App\Support\RupeeRound::one($stored);
        }

        $priorPrincipalPaid = Emi::where('loan_account_id', $loanAccount->id)
            ->where('instalment_number', '<', $emi->instalment_number)
            ->sum('principal_amount');
        $priorOutstanding = max(0, (float) $loanAccount->loan_amount - (float) $priorPrincipalPaid);

        return (float) \App\Support\RupeeRound::one($priorOutstanding * ((float) $loanAccount->interest_rate / 100));
    }

    /**
     * Accrued open-loan interest as of a date.
     * Monthly / weekly: only crossed days (before due). Daily: full cycle (not prorated).
     */
    protected function calculateOpenLoanAccruedInterest(
        LoanAccount $loanAccount,
        Emi $emi,
        $asOfDate,
        ?float $fullCycleInterest = null
    ): float {
        $full = $fullCycleInterest ?? $this->openLoanFullCycleInterest($loanAccount, $emi);
        if ($full <= 0.009) {
            return 0.0;
        }

        $termUnit = $this->resolveOpenLoanTermUnit($loanAccount);

        // Daily open loan: full cycle interest always (no early-day proration).
        if ($termUnit === 'daily') {
            return round($full, 2);
        }

        $asOf = Carbon::parse($asOfDate)->startOfDay();
        $due = $emi->due_date ? Carbon::parse($emi->due_date)->startOfDay() : null;

        // On or after due date → full cycle interest.
        if ($due && $asOf->gte($due)) {
            return round($full, 2);
        }

        $cycleStart = $this->openLoanCycleStartDate($loanAccount, $emi);
        if ($asOf->lt($cycleStart)) {
            return 0.0;
        }

        $daysInCycle = $this->openLoanDaysInCycle($termUnit);
        $daysElapsed = min($daysInCycle, max(0, (int) $cycleStart->diffInDays($asOf)));

        if ($daysElapsed <= 0) {
            return 0.0;
        }

        return round(min($full, ($full / $daysInCycle) * $daysElapsed), 2);
    }

    /**
     * Interest still payable on an open-loan cycle as of a date (accrued − already paid interest).
     */
    protected function openLoanInterestDue(LoanAccount $loanAccount, Emi $emi, $asOfDate): float
    {
        $full = $this->openLoanFullCycleInterest($loanAccount, $emi);
        $accrued = $this->calculateOpenLoanAccruedInterest($loanAccount, $emi, $asOfDate, $full);
        $paidAmount = (float) ($emi->paid_amount ?? 0);
        $principalPaid = (float) ($emi->principal_amount ?? 0);
        $interestPaid = max(0, $paidAmount - $principalPaid);
        $penalty = (float) ($emi->penalty_amount ?? 0);

        return round(max(0, ($accrued + $penalty) - $interestPaid), 2);
    }

    /**
     * Foreclose a loan account
     *
     * @param int $loanAccountId
     * @param array $options
     * @return array
     */
    public function foreclose($loanAccountId, array $options = [])
    {
        $loanAccount = LoanAccount::with('emis')->findOrFail($loanAccountId);

        // Validate loan status
        if ($loanAccount->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Loan is not active'
            ];
        }

        // Check if the ongoing EMI is partially paid
        $ongoingEmi = $loanAccount->emis()
            ->whereNotIn('status', ['paid', 'carried_forward'])
            ->orderBy('instalment_number', 'asc')
            ->first();

        if ($ongoingEmi && ($ongoingEmi->status === 'partial' || $ongoingEmi->is_partial_paid || ($ongoingEmi->paid_amount > 0 && $ongoingEmi->pending_amount > 0))) {
            return [
                'success' => false,
                'message' => 'Foreclosure is not allowed because the ongoing EMI is partially paid. Please clear the pending EMI amount fully first.'
            ];
        }

        // Get foreclosure configuration from database
        $foreclosureConfig = LoanConfiguration::getForeclosureConfig();

        if (!$foreclosureConfig || !$foreclosureConfig->is_active) {
            return [
                'success' => false,
                'message' => 'Foreclosure is not enabled in system configuration'
            ];
        }

        $isOverride = $options['override_mode'] ?? false;

        // Determine eligibility
        $eligibilityMonths = $loanAccount->getForeclosureEligibilityMonths();

        // Override eligibility if provided
        if ($isOverride && isset($options['eligibility_months'])) {
            $eligibilityMonths = $options['eligibility_months'];
        }

        // Check eligibility
        $paidEmisCount = $loanAccount->emis()->where('status', 'paid')->count();

        $application = $loanAccount->loanApplication;
        $termUnit = $application ? strtolower((string)$application->term_unit) : 'monthly';
        $displayUnit = match($termUnit) {
            'daily', 'day', 'days' => 'days',
            'weekly', 'week', 'weeks' => 'weeks',
            default => 'months'
        };

        if (!$isOverride && $paidEmisCount < $eligibilityMonths) {
            return [
                'success' => false,
                'message' => "Not eligible for foreclosure. Required: {$eligibilityMonths} {$displayUnit}. Paid: {$paidEmisCount} {$displayUnit}."
            ];
        }

        $amounts = $this->calculateForeclosureAmounts($loanAccount, array_merge($options, [
            'charges_percentage' => $options['charges_percentage'] ?? $foreclosureConfig->charges_percentage,
            'override_mode' => $isOverride,
        ]));

        $outstandingAmount = $amounts['outstanding_amount'];
        $grossInterest = $amounts['interest_outstanding'];
        $discountAmount = $amounts['discount_amount'];
        $discountPercentage = $amounts['discount_percentage'];
        // Everything downstream - what the client pays, what is booked to
        // revenue and what is stamped on the EMI - uses interest after discount.
        $interestOutstanding = $amounts['net_interest'];
        $foreclosureCharges = $amounts['foreclosure_charges'];
        $extraChargeAmount = $amounts['extra_charge_amount'];
        $totalForeclosureAmount = $amounts['total_amount'];
        $chargesPercentage = $amounts['charges_percentage'];
        $extraChargePercent = $amounts['extra_charge_percent'];

        $notes = $options['foreclosure_notes'] ?? null;
        if ($discountAmount > 0) {
            $discountNote = $discountPercentage > 0
                ? "Interest discount {$discountPercentage}% (₹{$discountAmount}) applied on ₹{$grossInterest} interest."
                : "Interest discount ₹{$discountAmount} applied on ₹{$grossInterest} interest.";
            $notes = trim(($notes ? $notes . ' ' : '') . $discountNote);
        }
        $paymentMethod = strtolower(trim((string) ($options['payment_method'] ?? 'in_hand')));
        if ($paymentMethod === 'cash') {
            $paymentMethod = 'in_hand';
        }
        $requestedBankId = (int) ($options['internal_bank_account_id'] ?? $options['bank_account_id'] ?? 0);
        $paymentReference = trim((string) ($options['payment_reference'] ?? ''));

        $oldTenure = (int) ($loanAccount->tenure ?? 0);
        $oldPrincipal = (float) $outstandingAmount;

        DB::beginTransaction();
        try {
            $now = now();
            $chitAccounting = app(ChitAccountingService::class);
            $resolvedBankId = $chitAccounting->resolveCollectionBankAccountId($paymentMethod, $requestedBankId);

            $chargesPosted = round($foreclosureCharges + $extraChargeAmount);

            $loanAccount->loadMissing('client');
            $accountNumber = $loanAccount->customer_loan_account_number
                ?? $loanAccount->account_number
                ?? 'N/A';
            $clientName = $loanAccount->client?->client_name ?? 'Client';
            $cashbookRef = $paymentReference !== ''
                ? $paymentReference
                : ('LOAN-FC-' . $loanAccount->id . '-' . $now->format('YmdHis'));

            $this->recordLoanCollectionInCashbook(
                $resolvedBankId,
                $paymentMethod,
                (float) $totalForeclosureAmount,
                $cashbookRef,
                AccountingTags::loanForeclosureDescription(
                    (string) $accountNumber,
                    (string) $clientName,
                    Auth::id()
                ),
                $now,
                AccountingTags::ENTRY_FORECLOSE,
                true
            );

            $this->postForeclosureRevenues(
                $loanAccount,
                (float) $interestOutstanding,
                $chargesPosted,
                (int) $resolvedBankId,
                $cashbookRef,
                $now->toDateString(),
                (string) $accountNumber,
                (string) $clientName
            );

            $this->settleEmisOnForeclosure(
                $loanAccount,
                $ongoingEmi,
                (float) $interestOutstanding,
                $now
            );

            $emiIds = $loanAccount->emis()->pluck('id');
            \App\Models\EmiAgentAssignment::whereIn('emi_id', $emiIds)
                ->whereIn('status', ['assigned', 'visited'])
                ->get()
                ->each(function ($assignment) use ($now) {
                    $assignment->update([
                        'status' => 'resolved',
                        'resolved_at' => $now,
                        'remarks' => trim(($assignment->remarks ?? '') . ' [Loan foreclosed]'),
                    ]);
                });

            // Update loan account
            $loanAccount->update([
                'status' => 'closed',
                'is_foreclosed' => true,
                'closed_at' => $now,
                'outstanding_amount' => 0,
                'pending_amount' => 0,
                'paid_amount' => $loanAccount->total_payable,
                'foreclosure_amount' => $totalForeclosureAmount,
                'foreclosure_interest_amount' => $interestOutstanding,
                'foreclosure_charges_amount' => $chargesPosted,
                'foreclosure_charges_percentage' => $chargesPercentage,
                'foreclosure_discount_percentage' => $discountPercentage,
                'foreclosure_discount_amount' => $discountAmount,
                'foreclosure_payment_method' => $paymentMethod,
                'foreclosure_bank_account_id' => $resolvedBankId,
                'foreclosure_notes' => $notes,
                'foreclosure_processed_by' => Auth::id(),
            ]);

            DB::commit();

            // Auto-generate foreclosure documents
            try {
                event(new \App\Events\GenerateDocument($loanAccount));
                Log::info('Foreclosure documents generation triggered', [
                    'loan_id' => $loanAccount->id,
                    'account_number' => $loanAccount->account_number
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to trigger document generation for foreclosure', [
                    'loan_id' => $loanAccount->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the foreclosure if document generation fails
            }

            // Send foreclosure email with documents
            try {
                Log::info('Preparing to send foreclosure email', [
                    'loan_id' => $loanAccount->id
                ]);

                // Wait for documents to be generated
                sleep(3);

                $emailService = app(\App\Services\LoanDocumentEmailService::class);
                $emailResult = $emailService->sendLoanDocumentsEmail($loanAccount->id, 'loan_foreclosed');

                if ($emailResult['success']) {
                    Log::info('Foreclosure email sent successfully', [
                        'loan_id' => $loanAccount->id,
                        'documents_sent' => $emailResult['documents_sent'] ?? 0
                    ]);
                } else {
                    Log::warning('Foreclosure email failed', [
                        'loan_id' => $loanAccount->id,
                        'reason' => $emailResult['message']
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Foreclosure email exception', [
                    'loan_id' => $loanAccount->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't fail the foreclosure if email fails
            }

            Log::info('Foreclosure completed', [
                'loan_id' => $loanAccount->id,
                'outstanding_amount' => $outstandingAmount,
                'charges_percentage' => $chargesPercentage,
                'foreclosure_charges' => $foreclosureCharges,
                'extra_charge_percent' => $extraChargePercent,
                'extra_charge_amount' => $extraChargeAmount,
                'total_foreclosure_amount' => $totalForeclosureAmount,
                'is_override' => $isOverride
            ]);

            return [
                'success' => true,
                'message' => 'Loan foreclosed successfully',
                'data' => [
                    'loan_id' => $loanAccount->id,
                    'outstanding_amount' => $outstandingAmount,
                    'gross_interest' => $grossInterest,
                    'discount_percentage' => $discountPercentage,
                    'discount_amount' => $discountAmount,
                    'interest_charged' => $interestOutstanding,
                    'foreclosure_charges' => $foreclosureCharges,
                    'extra_charge_percent' => $extraChargePercent,
                    'extra_charge_amount' => $extraChargeAmount,
                    'total_foreclosure_amount' => $totalForeclosureAmount
                ]
            ];

        } catch (ValidationException $e) {
            DB::rollBack();
            $message = collect($e->errors())->flatten()->first() ?: 'Please select a collection bank account.';

            return [
                'success' => false,
                'message' => $message,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Foreclosure failed', [
                'loan_id' => $loanAccountId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Foreclosure failed: ' . $e->getMessage()
            ];
        }
    }
    /**
     * Process EMI payment (Partial or Full)
     *
     * @param int $emiId
     * @param float $amount
     * @param string $date
     * @param string $method
     * @param string|null $reference
     * @param string|null $remarks
     * @param bool $skipHistory
     * @param float $principalAmount
     * @param bool $bypassPriorCheck
     * @return array
     */
    public function processPayment($emiId, $amount, $date, $method, $reference = null, $remarks = null, $skipHistory = false, $principalAmount = 0, $bypassPriorCheck = false, $bankAccountId = null, ?int $agentId = null, bool $skipCashbook = false)
    {
        $emi = Emi::with('loanAccount')->findOrFail($emiId);
        
        // Redirect to Interest-Only logic if mode is Kandhuvatti
        if ($emi->loanAccount && $emi->loanAccount->loan_mode === 'interest_only') {
            return $this->processInterestOnlyPayment($emi->loanAccount, $amount, $date, $method, $reference, $remarks, $skipHistory, $principalAmount, $bankAccountId, $emi, $skipCashbook);
        }
        
        // Ensure the loan's EMIs are synchronized with the latest non-cumulative logic before processing
        if (! self::$suppressBalanceSync) {
            $this->syncEmiBalances($emi->loan_account_id);
        }
        
        // Apply dynamic penalty if overdue and grace period crossed
        $this->applyDynamicPenaltyIfNeeded($emi, $date);
        
        $emi->refresh(); // Refresh to get the updated total_due and status
        $loanAccount = $emi->loanAccount->fresh();

        // Check for unpaid EMIs prior to this one (ignoring those fully covered by pending collections)
        $lastEmi = Emi::where('loan_account_id', $emi->loan_account_id)
            ->orderByDesc('instalment_number')
            ->first();
        $isLoanMatured = ($lastEmi && $lastEmi->due_date && $lastEmi->due_date->lt(Carbon::now()));

        if (!$bypassPriorCheck && !$isLoanMatured) {
            $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                ->where('instalment_number', '<', $emi->instalment_number)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where(function($q) {
                    $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                })
                ->exists();

            if ($unpaidPrior) {
                return [
                    'success' => false,
                    'message' => 'Please clear previous pending EMIs before paying for this instalment.'
                ];
            }
        }


        $electronicMethods = ['upi', 'bank_transfer', 'gpay', 'google_pay', 'phonepe', 'phone_pe', 'qr', 'qr_code'];
        if (in_array($method, $electronicMethods, true) && !$bankAccountId && ! $skipHistory) {
            return [
                'success' => false,
                'message' => 'Collection Bank Account is mandatory when paying via UPI / GPay / QR or Bank Transfer.'
            ];
        }

        // Resolve once so cash → Cash in Hand id is stored on collections (undo must not guess).
        // Wallet stays outside the company cashbook.
        if ($method !== 'wallet') {
            try {
                $bankAccountId = app(\App\Services\Account\ChitAccountingService::class)
                    ->resolveCollectionBankAccountId((string) $method, (int) ($bankAccountId ?? 0));
            } catch (\Illuminate\Validation\ValidationException $e) {
                return [
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?: 'Invalid collection bank account.',
                ];
            }
        }

        // Calculate potential new total paid
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Payment amount must be greater than zero.'
            ];
        }

        $remainingOutstanding = round((float) ($loanAccount->outstanding_amount ?? 0), 2);
        if ($amount > ($remainingOutstanding + 0.01)) {
            return [
                'success' => false,
                'message' => 'Payment amount cannot exceed the remaining loan outstanding of ₹' . number_format($remainingOutstanding, 2) . '.'
            ];
        }

        $pendingEmis = Emi::where('loan_account_id', $emi->loan_account_id)
            ->where('instalment_number', '>=', $emi->instalment_number)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('instalment_number')
            ->get();

        $allocatableOutstanding = round((float) $pendingEmis->sum(function ($pendingEmi) {
            return max(0, (float) ($pendingEmi->pending_amount ?? 0));
        }), 2);

        if ($amount > ($allocatableOutstanding + 0.01)) {
            return [
                'success' => false,
                'message' => 'Payment amount cannot exceed the allocatable pending EMI balance of ₹' . number_format($allocatableOutstanding, 2) . '.'
            ];
        }

        if ($method === 'wallet') {
            $clientId = (int) ($loanAccount->client_id ?? 0);
            if (!$clientId) {
                return ['success' => false, 'message' => 'Unable to resolve customer for wallet payment.'];
            }
            $walletService = app(WalletService::class);
            if ($walletService->balanceForClient($clientId) + 0.01 < $amount) {
                return [
                    'success' => false,
                    'message' => 'Insufficient wallet balance. Available: ₹' . number_format($walletService->balanceForClient($clientId), 2),
                ];
            }
        }

        DB::beginTransaction();
        try {
            if ($method === 'wallet') {
                app(WalletService::class)->debit(
                    (int) $loanAccount->client_id,
                    round((float) $amount, 2),
                    'Loan EMI Payment',
                    'loan_emi',
                    $emi->id,
                    ['loan_account_id' => $loanAccount->id, 'instalment_number' => $emi->instalment_number]
                );
            }

            $remainingPayment = round((float) $amount, 2);
            $updatedEmis = [];
            $cashbookEmiNumbers = [];
            $cashbookPostedTotal = 0.0;

            foreach ($pendingEmis as $pendingEmi) {
                if ($remainingPayment <= 0.01) {
                    break;
                }

                $pendingForThisEmi = round((float) ($pendingEmi->pending_amount ?? 0), 2);
                if ($pendingForThisEmi <= 0.01) {
                    continue;
                }

                $paymentForThisEmi = round(min($remainingPayment, $pendingForThisEmi), 2);
                $currentPaid = round((float) ($pendingEmi->paid_amount ?? 0), 2);
                $newTotalPaid = round($currentPaid + $paymentForThisEmi, 2);
                $remainingDue = round(max(0, $pendingForThisEmi - $paymentForThisEmi), 2);
                // Snap paisa residuals so ₹4000 never becomes ₹4000.01
                if ($remainingDue <= 0.009) {
                    $remainingDue = 0.0;
                }
                $isFullPayment = $remainingDue <= 0.009;

                $updateData = [
                    'paid_amount' => $newTotalPaid,
                    'payment_method' => $method,
                    'payment_reference' => $reference,
                    'remarks' => $remarks ? trim(($pendingEmi->remarks ?? '') . "\n" . $remarks) : $pendingEmi->remarks,
                    'paid_date' => $date,
                    'pending_amount' => $remainingDue,
                ];

                if ($isFullPayment) {
                    $updateData['status'] = 'paid';
                    $updateData['pending_amount'] = 0;
                    $updateData['is_partial_paid'] = false;
                    $updateData['partial_paid_date'] = null;
                    $updateData['partial_paid_amount'] = 0;
                } else {
                    $updateData['status'] = 'partial';
                    $updateData['is_partial_paid'] = true;
                    $updateData['partial_paid_date'] = $date;
                    $updateData['partial_paid_amount'] = $newTotalPaid;
                }

                $pendingEmi->update($updateData);

                if (!$skipHistory) {
                    try {
                        // Only attribute to an agent when an Agent user actually collected.
                        // Never fall back to the client's assigned agent — that mislabels Admin/Staff collections.
                        $effectiveAgentId = $agentId;
                        if (!$effectiveAgentId && Auth::check()) {
                            $user = Auth::user();
                            if ($user->hasRole('Agent') && ! $user->hasAnyRole(['Admin', 'Staff', 'Super Admin'])) {
                                $effectiveAgentId = optional($user->agent)->id;
                            }
                        }

                        \App\Models\EmiCollection::create([
                            'agent_id' => $effectiveAgentId,
                            'emi_id' => $pendingEmi->id,
                            'amount' => $paymentForThisEmi,
                            'payment_method' => $method,
                            'payment_type' => $isFullPayment ? 'full' : 'partial',
                            'payment_reference' => $reference,
                            'status' => 'verified',
                            'collected_at' => $date,
                            'verified_by' => Auth::id(),
                            'verified_at' => now(),
                            'remarks' => $remarks ?: ($isFullPayment ? 'EMI payment processed' : 'Partial EMI payment processed'),
                            'bank_account_id' => $bankAccountId,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('EmiCollection creation error in processPayment: ' . $e->getMessage());
                    }
                }

                $cashbookEmiNumbers[] = (int) $pendingEmi->instalment_number;
                $cashbookPostedTotal = round($cashbookPostedTotal + $paymentForThisEmi, 2);

                $updatedEmis[] = [
                    'emi_id' => $pendingEmi->id,
                    'instalment_number' => $pendingEmi->instalment_number,
                    'paid_amount' => $paymentForThisEmi,
                    'status' => $updateData['status'],
                    'balance' => $remainingDue,
                ];

                $remainingPayment = round($remainingPayment - $paymentForThisEmi, 2);
            }

            // One bank transaction for the whole payment (covers 1..N EMIs).
            if (! $skipCashbook && $cashbookPostedTotal > 0.009) {
                $loanAccount->loadMissing('client');
                $accountNumber = $loanAccount->customer_loan_account_number
                    ?? $loanAccount->account_number
                    ?? 'N/A';
                $clientName = $loanAccount->client?->client_name ?? 'Client';
                $this->recordLoanCollectionInCashbook(
                    $bankAccountId,
                    (string) $method,
                    (float) $cashbookPostedTotal,
                    $reference ?: ('COLL-' . time()),
                    \App\Services\Account\AccountingTags::loanIcDescription(
                        (string) $accountNumber,
                        (string) $clientName,
                        $cashbookEmiNumbers,
                        Auth::id()
                    ),
                    $date
                );
            }

            if (!$skipHistory) {
                try {
                    $activityAgentId = Auth::user()->agent?->id ?? \App\Models\Agent::where('user_id', Auth::id())->value('id');
                    if ($activityAgentId) {
                        \App\Models\AgentActivity::create([
                            'emi_id' => $emi->id,
                            'agent_id' => $activityAgentId,
                            'type' => 'payment',
                            'description' => "₹" . number_format($amount, 2),
                            'method' => strtoupper(str_replace('_', ' ', $method)),
                            'reference' => $reference,
                            'remarks' => $remarks,
                            'action_at' => $date,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('AgentActivity creation error in processPayment: ' . $e->getMessage());
                }
            }

            if (! self::$suppressPaymentNotifications) {
                event(new \App\Events\PaymentReceivedEvent($emi, $amount));
            }

            // Update loan account totals
            if (! self::$suppressBalanceSync) {
                $this->syncLoanTotals($emi->loan_account_id);
                $this->syncEmiBalances($emi->loan_account_id);
            }

            DB::commit();

            $updatedCount = count($updatedEmis);
            $latestState = Emi::find($emiId);

            return [
                'success' => true,
                'message' => $updatedCount > 1
                    ? 'Payment recorded and applied across ' . $updatedCount . ' EMIs.'
                    : (($latestState && $latestState->status === 'paid') ? 'EMI fully paid.' : 'Partial payment recorded.'),
                'data' => [
                    'emi_id' => $emi->id,
                    'paid_amount' => $amount,
                    'due_amount' => $latestState ? max(0, (float) $latestState->pending_amount) : 0,
                    'status' => $latestState?->status,
                    'applied_emis' => $updatedEmis,
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing failed', [
                'emi_id' => $emiId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Payment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process Principal Prepayment
     * Reduces principal amount and recalculates tenure (EMI amount stays same)
     *
     * @param int $loanAccountId
     * @param float $prepaymentAmount
     * @param string $paymentDate
     * @param string $paymentMethod
     * @param string|null $paymentReference
     * @param string|null $remarks
     * @return array
     */
    public function processPrepayment($loanAccountId, $prepaymentAmount, $paymentDate, $paymentMethod, $paymentReference = null, $remarks = null)
    {
        $loanAccount = LoanAccount::with(['emis', 'loanApplication'])->findOrFail($loanAccountId);

        // Validate loan status
        if ($loanAccount->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Loan is not active'
            ];
        }

        // Get prepayment configuration from database
        $prepaymentConfig = LoanConfiguration::getPrepaymentConfig();

        if (!$prepaymentConfig || !$prepaymentConfig->is_active) {
            return [
                'success' => false,
                'message' => 'Prepayment is not enabled in system configuration'
            ];
        }

        // Check eligibility - minimum EMIs completed
        $paidEmisCount = $loanAccount->emis()->where('status', 'paid')->count();
        $eligibilityMonths = $prepaymentConfig->eligibility_months ?? 0;

        if ($paidEmisCount < $eligibilityMonths) {
            return [
                'success' => false,
                'message' => "Not eligible for prepayment. Required: {$eligibilityMonths} EMIs. Completed: {$paidEmisCount} EMIs."
            ];
        }

        // Detect current principal balance from unpaid EMIs
        $unpaidEmis = $loanAccount->emis()->where('status', '!=', 'paid')->get();
        $sumOfEmiPrincipals = $unpaidEmis->sum(function($emi) {
            $alreadyPaid = (float)($emi->paid_amount ?? 0);
            $interestPart = (float)($emi->interest_amount ?? 0);
            $principalPaid = max(0, $alreadyPaid - $interestPart);
            return max(0, (float)($emi->principal_amount ?? 0) - $principalPaid);
        });

        // FALLBACK: If principal_amount column is empty/0, use the outstanding_amount (Total) 
        // as a proxy, adjusted by the original principal/total_payable ratio.
        if ($sumOfEmiPrincipals <= 0.01) {
            $ratio = ($loanAccount->loan_amount > 0 && $loanAccount->total_payable > 0) 
                     ? ($loanAccount->loan_amount / $loanAccount->total_payable) 
                     : 0.85; // Default to 85% if can't calculate
            $outstandingPrincipal = round($loanAccount->outstanding_amount * $ratio, 2);
            Log::warning("Prepayment: EMI principal_amount data missing. Estimated principal via ratio {$ratio}.", [
                'loan_id' => $loanAccount->id,
                'estimated_principal' => $outstandingPrincipal
            ]);
        } else {
            $outstandingPrincipal = round($sumOfEmiPrincipals, 2);
        }

        // Validate prepayment amount
        if ($prepaymentAmount <= 0) {
            return [
                'success' => false,
                'message' => 'Prepayment amount must be greater than zero'
            ];
        }

        if ($prepaymentAmount > $outstandingPrincipal + 0.01) {
             return [
                'success' => false,
                'message' => "Amount (₹" . number_format($prepaymentAmount, 2) . ") exceeds remaining principal (₹" . number_format($outstandingPrincipal, 2) . ")"
            ];
        }

        // Determine charges
        $chargeType = $prepaymentConfig->charge_type ?? 'percentage';
        $chargeValue = $prepaymentConfig->charge_value ?? 0;
        $prepaymentCharge = ($chargeType === 'percentage') ? (($prepaymentAmount * $chargeValue) / 100) : $chargeValue;
        $totalPayableByCustomer = $prepaymentAmount + $prepaymentCharge;

        // Calculate new balance
        $newPrincipalBase = round($outstandingPrincipal - $prepaymentAmount, 2);

        // Detect EMI amount to maintain
        $emiAmount = (float)($loanAccount->emi_amount ?? 0);
        if ($emiAmount <= 0) {
            $firstEmi = $loanAccount->emis()->orderBy('instalment_number', 'asc')->first();
            $emiAmount = $firstEmi ? (float)$firstEmi->total_amount : 0;
            if ($emiAmount <= 0 && $loanAccount->tenure > 0) {
                $emiAmount = (float)$loanAccount->total_payable / (float)$loanAccount->tenure;
            }
        }

        // Calculate New Tenure
        $interestRate = (float)($loanAccount->interest_rate ?? 0);
        $monthlyRate = $interestRate / 12 / 100;
        
        if ($newPrincipalBase <= 1) {
            $newRemainingTenure = 0;
        } elseif ($monthlyRate > 0) {
            $denom = $emiAmount - ($newPrincipalBase * $monthlyRate);
            if ($denom <= 0) {
                // EMI doesn't even cover interest. Fallback: maintain current remaining tenure
                $lastPaidIns = $loanAccount->emis()->whereIn('status', ['paid', 'partial'])->max('instalment_number') ?? 0;
                $newRemainingTenure = max(1, $loanAccount->tenure - $lastPaidIns);
            } else {
                $newRemainingTenure = (int)ceil(log($emiAmount / $denom) / log(1 + $monthlyRate));
            }
        } else {
            $newRemainingTenure = (int)ceil($newPrincipalBase / $emiAmount);
        }

        $newRemainingTenure = max(0, $newRemainingTenure);

        DB::beginTransaction();
        try {
            // Identify and Carry Forward
            $lastPaidInstalment = $loanAccount->emis()->whereIn('status', ['paid', 'partial'])->max('instalment_number') ?? 0;
            $carryForwardBalance = $loanAccount->emis()->whereIn('status', ['partial', 'overdue'])->sum('pending_amount');

            // Delete Future EMIs. These rows are replaced by the regenerated schedule below,
            // so remove them outright rather than leaving soft deleted duplicates behind.
            $loanAccount->emis()->where('instalment_number', '>', $lastPaidInstalment)->forceDelete();

            // Reschedule
            if ($newRemainingTenure > 0) {
                $this->regenerateEmiSchedule(
                    $loanAccount, 
                    $newPrincipalBase, 
                    $emiAmount, 
                    $newRemainingTenure,
                    $lastPaidInstalment, 
                    $paymentDate, 
                    $carryForwardBalance
                );
            }

            // Record this prepayment in history (Audit Trail)
            try {
                // Prepayment is applied to the principal. 
                // We record it as a 'partial' payment type since it's not a full closure.
                \App\Models\EmiCollection::create([
                    'emi_id' => $loanAccount->emis()->where('instalment_number', '>', $lastPaidInstalment)->first()->id ?? null,
                    'agent_id' => null, 
                    'amount' => $prepaymentAmount,
                    'payment_method' => $paymentMethod,
                    'payment_type' => 'partial',
                    'status' => 'verified',
                    'collected_at' => $paymentDate,
                    'remarks' => $remarks ?: 'Prepayment processed'
                ]);
            } catch (\Exception $e) {
                Log::error('EmiCollection creation error in processPrepayment: ' . $e->getMessage());
            }

            // Update Account Totals
            // Use DB query for sum to avoid stale collection memory issues
            $totalFutureEmisAmount = DB::table('emis')
                ->where('loan_account_id', $loanAccount->id)
                ->where('instalment_number', '>', $lastPaidInstalment)
                ->whereNull('deleted_at')
                ->sum('total_amount');

            $totalPastPaid = DB::table('emis')
                ->where('loan_account_id', $loanAccount->id)
                ->where('instalment_number', '<=', $lastPaidInstalment)
                ->whereNull('deleted_at')
                ->sum('paid_amount');

            $newTotalPayable = $totalPastPaid + $prepaymentAmount + $totalFutureEmisAmount;

            $loanAccount->refresh(); // Refresh to get updated EMI count

            $loanAccount->update([
                'prepayment_amount' => ($loanAccount->prepayment_amount ?? 0) + $prepaymentAmount,
                'tenure' => $loanAccount->emis()->count(),
                'total_payable' => $newTotalPayable,
            ]);

            $this->syncLoanTotals($loanAccount->id);

            Log::info('Prepayment Finalized', [
                'loan_id' => $loanAccount->id,
                'prepayment' => $prepaymentAmount,
                'new_principal' => $newPrincipalBase,
                'new_remaining_tenure' => $newRemainingTenure,
                'new_total_tenure' => $loanAccount->tenure,
                'new_total_payable' => $newTotalPayable
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Prepayment processed successfully',
                'data' => [
                    'prepayment_amount' => $prepaymentAmount,
                    'prepayment_charge' => round($prepaymentCharge, 2),
                    'total_payable' => round($totalPayableByCustomer, 2),
                    'old_principal' => round($oldPrincipal, 2),
                    'new_principal' => $newPrincipalBase,
                    'old_tenure' => $oldTenure,
                    'new_tenure' => (int) $loanAccount->tenure,
                    'reduced_tenure' => $newRemainingTenure,
                    'account_total_payable' => $newTotalPayable
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Prepayment processing failed', [
                'loan_account_id' => $loanAccountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Prepayment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Regenerate EMI schedule after prepayment
     *
     * @param LoanAccount $loanAccount
     * @param float $principal
     * @param float $emiAmount
     * @param int $tenure
     * @param int $startInstalmentNumber
     * @param string $startDate
     * @return void
     */
    private function regenerateEmiSchedule(
        $loanAccount,
        $principal,
        $emiAmount,
        $tenure,
        $startInstalmentNumber,
        $startDate,
        $carryForwardBalance = 0
    ) {
        $interestRate = $loanAccount->interest_rate ?? 0;
        $monthlyInterestRate = $interestRate / 12 / 100;

        $remainingPrincipal = $principal;
        $currentDate = \Carbon\Carbon::parse($startDate);

        for ($i = 1; $i <= $tenure; $i++) {

            $instalmentNumber = $startInstalmentNumber + $i;

            // Calculate interest for this month
            $interestAmount = round($remainingPrincipal * $monthlyInterestRate);

            // Principal component
            $principalAmount = $emiAmount - $interestAmount;

            // Last EMI adjustment
            if ($i == $tenure || $principalAmount > $remainingPrincipal) {
                $principalAmount = $remainingPrincipal;
                $interestAmount = round($remainingPrincipal * $monthlyInterestRate);
                $totalAmount = $principalAmount + $interestAmount;
            } else {
                $totalAmount = $emiAmount;
            }

            // Ensure principal doesn't go negative
            $principalAmount = max(0, $principalAmount);

            // Inject carry-forward balance into FIRST EMI ONLY
            $previousBalance = ($i === 1 && $carryForwardBalance > 0) ? round($carryForwardBalance) : 0;
            
            // Calculate total due for this EMI record
            $recordTotalDue = round($totalAmount + $previousBalance);

            // Calculate due date
            $nextDueDate = $this->calculateNextDueDate($currentDate, $loanAccount->emi_day);

            $openingBalance = round($remainingPrincipal, 2);
            $closingBalance = round($remainingPrincipal - $principalAmount, 2);

            Emi::create([
                'loan_account_id'   => $loanAccount->id,
                'instalment_number' => $instalmentNumber,
                'principal_amount' => round($principalAmount),
                'interest_amount'  => round($interestAmount),
                'total_amount'     => round($totalAmount),
                'previous_balance' => $previousBalance,
                'total_due'        => $recordTotalDue,
                'pending_amount'   => $recordTotalDue,
                'due_date'         => $nextDueDate,
                'status'           => 'pending',
                'opening_balance'  => $openingBalance,
                'closing_balance'  => $closingBalance,
            ]);

            // Update remaining principal
            $remainingPrincipal = round($remainingPrincipal - $principalAmount);

            // Update current date for next iteration base
            $currentDate = \Carbon\Carbon::parse($nextDueDate);
        }
    }

    /**
     * Calculate next due date based on EMI day
     */
    private function calculateNextDueDate($currentDate, $emiDay)
    {
        $date = \Carbon\Carbon::parse($currentDate);
        
        // Move to next month
        $date->addMonth();
        
        // Set fixed EMI day if available
        if ($emiDay) {
            // detailed handling for end of month days (29, 30, 31)
            $daysInMonth = $date->daysInMonth;
            $targetDay = min($emiDay, $daysInMonth);
            $date->day = $targetDay;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Process Partial Payment with Cascading Balance Logic
     *
     * Rules:
     * - Payment always clears previous balance first
     * - Then applies to current EMI
     * - Unpaid balance moves to next month
     *
     * @param int $loanAccountId
     * @param float $paymentAmount
     * @param string $paymentDate
     * @param string $paymentMethod
     * @param string|null $paymentReference
     * @param string|null $remarks
     * @param bool $skipHistory
     * @return array
     */
    public function processPartialPayment($loanAccountId, $paymentAmount, $paymentDate, $paymentMethod, $paymentReference = null, $remarks = null, $skipHistory = false, $bankAccountId = null)
    {
        $loanAccount = LoanAccount::findOrFail($loanAccountId);

        // Redirect to Interest-Only logic if mode is Kandhuvatti
        if ($loanAccount->loan_mode === 'interest_only') {
            return $this->processInterestOnlyPayment($loanAccount, $paymentAmount, $paymentDate, $paymentMethod, $paymentReference, $remarks, $skipHistory, 0, $bankAccountId);
        }

        $loanAccount->load('emis');

        // Validate loan status

        // Validate payment amount
        if ($paymentAmount <= 0) {
            return [
                'success' => false,
                'message' => 'Payment amount must be greater than zero'
            ];
        }

        if (in_array($paymentMethod, ['upi', 'bank_transfer'], true) && ! $bankAccountId) {
            return [
                'success' => false,
                'message' => 'Collection Bank Account is mandatory when paying via UPI / GPay / QR or Bank Transfer.',
            ];
        }

        if ($paymentMethod !== 'wallet') {
            try {
                $bankAccountId = app(\App\Services\Account\ChitAccountingService::class)
                    ->resolveCollectionBankAccountId((string) $paymentMethod, (int) ($bankAccountId ?? 0));
            } catch (\Illuminate\Validation\ValidationException $e) {
                return [
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?: 'Invalid collection bank account.',
                ];
            }
        }

        $loanAccount->refresh();
        $remainingOutstanding = round((float) ($loanAccount->outstanding_amount ?? 0), 2);
        if ($paymentAmount > ($remainingOutstanding + 0.01)) {
            return [
                'success' => false,
                'message' => 'Payment amount cannot exceed the remaining loan outstanding of ₹' . number_format($remainingOutstanding, 2) . '.'
            ];
        }

        DB::beginTransaction();
        try {
            // Get the first unpaid/partial/overdue EMI in chronological order
            // We focus on the oldest due first.
            $pendingEMI = Emi::where('loan_account_id', $loanAccountId)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->orderBy('instalment_number')
                ->first();

            if (!$pendingEMI) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No pending EMIs found'
                ];
            }

            // Apply payment ONLY to this current EMI (and potentially others if this one is fully paid?
            // User request implies: "if user have 10000 as emi 1 and pay 5000... till month ends"
            // So we pay towards the oldest pending EMI. If that gets fully paid, we can move to next?
            // "if user have 10000 as emi 1 and pay 5000 and that will appear on the same month till the month ends"
            // This suggests sticking to the current month's EMI.
            // But if I pay 15000 for a 10000 EMI?
            // Usually we clear the oldest first.
            // Let's implement logic: Pay indefinitely towards oldest pending.
            // BUT do not update Next Month's Previous Balance here.

            $remainingPayment = $paymentAmount;
            $emisUpdated = [];
            $lastUpdatedEmi = null;
            $cashbookEmiNumbers = [];
            $cashbookPostedTotal = 0.0;

             $pendingEmis = Emi::where('loan_account_id', $loanAccountId)
                 ->whereIn('status', ['pending', 'partial', 'overdue'])
                 ->orderBy('instalment_number')
                 ->get();

            foreach ($pendingEmis as $emi) {
                if ($remainingPayment <= 0) break;

                // Calculate total due for this EMI
                // Note: previous_balance is fixed for this EMI once generated/carried forward.
                $previousBalance = (float) ($emi->previous_balance ?? 0);
                $penaltyAmount   = (float) ($emi->penalty_amount ?? 0);
                $totalAmount     = (float) ($emi->total_amount ?? 0);
                $totalDue        = $previousBalance + $totalAmount + $penaltyAmount;
                
                $currentPaid     = (float) ($emi->paid_amount ?? 0);
                $outstandingForThisEmi = $totalDue - $currentPaid;

                // Amount to pay for this specific EMI
                $paymentForThisEmi = min($remainingPayment, $outstandingForThisEmi);

                if ($paymentForThisEmi > 0) {
                    $newPaidAmount = $currentPaid + $paymentForThisEmi;
                    $remainingPayment -= $paymentForThisEmi;

                    // Calculate pending to check status
                    $newPendingAmount = max(0, $totalDue - $newPaidAmount);

                    // Determine status
                    if ($newPendingAmount <= 0.01) {
                        $status = 'paid';
                        $isPartialPaid = false;
                    } else {
                        $status = 'partial';
                        $isPartialPaid = true;
                    }

                    // Update EMI
                    $emi->update([
                        'paid_amount' => $newPaidAmount,
                        'pending_amount' => $newPendingAmount,
                        'status' => $status,
                        'is_partial_paid' => $isPartialPaid,
                        'partial_paid_amount' => $isPartialPaid ? $newPaidAmount : 0,
                        'partial_paid_date' => $isPartialPaid ? $paymentDate : null,
                        'paid_date' => ($status === 'paid') ? $paymentDate : $emi->paid_date,
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference,
                        'remarks' => $remarks,
                    ]);

                    // Record this payment in history (Audit Trail)
                    if (!$skipHistory) {
                        try {
                            \App\Models\EmiCollection::create([
                                'emi_id' => $emi->id,
                                'agent_id' => null, // Service call might not have an agent context
                                'amount' => $paymentForThisEmi,
                                'payment_method' => $paymentMethod,
                                'payment_type' => 'partial',
                                'payment_reference' => $paymentReference,
                                'status' => 'verified',
                                'collected_at' => $paymentDate,
                                'verified_by' => Auth::id(),
                                'verified_at' => now(),
                                'remarks' => $remarks ?: 'Partial payment processed via service',
                                'bank_account_id' => $bankAccountId,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('EmiCollection creation error in LoanPaymentService: ' . $e->getMessage());
                        }
                    }

                    $cashbookEmiNumbers[] = (int) $emi->instalment_number;
                    $cashbookPostedTotal = round($cashbookPostedTotal + $paymentForThisEmi, 2);

                    $emisUpdated[] = [
                        'month' => $emi->instalment_number,
                        'status' => $status,
                        'paid' => $paymentForThisEmi,
                        'pending' => $newPendingAmount,
                    ];

                    $lastUpdatedEmi = $emi;
                }
            }

            if ($cashbookPostedTotal > 0.009) {
                $loanAccount->loadMissing('client');
                $accountNumber = $loanAccount->customer_loan_account_number ?? $loanAccount->account_number ?? 'N/A';
                $clientName = $loanAccount->client?->client_name ?? 'Client';
                $this->recordLoanCollectionInCashbook(
                    $bankAccountId,
                    (string) $paymentMethod,
                    (float) $cashbookPostedTotal,
                    $paymentReference ?: ('COLL-' . time()),
                    \App\Services\Account\AccountingTags::loanIcDescription(
                        (string) $accountNumber,
                        (string) $clientName,
                        $cashbookEmiNumbers,
                        Auth::id()
                    ),
                    $paymentDate
                );
            }

            // Log transaction
            Log::info('Partial payment processed (Non-Cascading)', [
                'loan_account_id' => $loanAccountId,
                'payment_amount' => $paymentAmount,
                'emis_updated' => count($emisUpdated),
                'payment_date' => $paymentDate,
            ]);

            DB::commit();
            $this->syncLoanTotals($loanAccountId);
            $this->syncEmiBalances($loanAccountId);

            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'payment_amount' => $paymentAmount,
                    'emis_updated' => $emisUpdated,
                    'next_month_due' => 0, // No longer calculating this dynamically here
                ]
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Partial payment processing failed', [
                'loan_account_id' => $loanAccountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Payment failed: ' . $e->getMessage()
            ];
        }
    }

    public function apply(Payment $payment)
    {
        $loan = LoanAccount::findOrFail($payment->loan_account_id);

        DB::beginTransaction();
        try {

            $loan->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    public function finalizeForeclosure(Payment $payment)
    {
        $loan = LoanAccount::with('emis')->findOrFail($payment->loan_account_id);

        DB::beginTransaction();
        try {
            $ongoingEmi = $loan->emis()
                ->whereNotIn('status', ['paid', 'closed', 'carried_forward'])
                ->orderBy('instalment_number')
                ->first();

            $this->settleEmisOnForeclosure($loan, $ongoingEmi, 0.0, now(), $payment->payment_id);

            $loan->update([
                'status' => 'closed',
                'is_foreclosed' => true,
                'closed_at' => now(),
                'outstanding_amount' => 0,
                'paid_amount' => $loan->total_payable,
                'foreclosure_amount' => $payment->amount, // payment amount
            ]);

            DB::commit();

            $this->syncLoanTotals($loan->id);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    public function syncLoanTotals(int $loanAccountId): void
    {
        $loan = LoanAccount::with(['emis', 'loanApplication.product'])->findOrFail($loanAccountId);

        $totalPaid = $loan->emis()->sum('paid_amount');
        $totalPaid += ($loan->prepayment_amount ?? 0);
        $loan->paid_amount = $totalPaid;

        $isReducing = $loan->loanApplication && $loan->loanApplication->product && in_array($loan->loanApplication->product->interest_type, ['reducing', 'declining_balance']);

        if ($loan->isSettled()) {
            // Foreclosure/closure settles the whole account. Without this the
            // interest-only branch below would recompute the full principal,
            // because open-loan cycles never carry a principal amount.
            $loan->outstanding_amount = 0;
        } elseif ($loan->loan_mode === 'interest_only') {
            // For Kandhuvatti, outstanding is ONLY reduced by the principal portion paid
            $principalPaid = $loan->emis()->sum('principal_amount');
            $loan->outstanding_amount = max(0, (float)$loan->loan_amount - $principalPaid);
        } elseif ($isReducing) {
            // For Reducing Balance loans, outstanding is remaining principal balance
            $principalPaid = $loan->emis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return (float)($emi->principal_amount ?? 0);
                return max(0, $alreadyPaid - $interestPart);
            });
            $loan->outstanding_amount = max(0, (float)$loan->loan_amount - $principalPaid);
        } else {
            // Standard EMI logic
            $loan->outstanding_amount = max(
                0,
                (float) $loan->total_payable - $totalPaid
            );
        }

        $pendingInterestEmis = $loan->emis()->whereIn('status', ['pending', 'partial', 'overdue'])->count();
        $shouldClose = false;

        if ($loan->isOpenLoan()) {
            $remainingPrincipal = $loan->openLoanRemainingPrincipal();
            $loan->outstanding_amount = $remainingPrincipal;
            if ($remainingPrincipal <= 0.05 && $pendingInterestEmis <= 0) {
                $shouldClose = true;
            } elseif (! $loan->is_foreclosed && $remainingPrincipal > 0.05 && $loan->status === 'closed') {
                $loan->status = 'active';
                $loan->closed_at = null;
            }
        } else {
            if ($loan->outstanding_amount <= 0.05 || $pendingInterestEmis <= 0) {
                $shouldClose = true;
            }
        }

        if ($shouldClose && $loan->status !== 'closed') {
            $loan->status = 'closed';
            $loan->closed_at = $loan->closed_at ?? now();
        }

        $loan->save();
    }

    /**
     * Calculate loan closing & settlement summary (collected, loan amount, interest, balance)
     *
     * @param LoanAccount $loanAccount
     * @return array
     */
    public function calculateLoanClosingSummary(LoanAccount $loanAccount): array
    {
        $loanAccount->loadMissing('emis');
        $isInterestOnly = ($loanAccount->loan_mode === 'interest_only');

        $totalCollected = (float) $loanAccount->emis()->sum('paid_amount') + (float) ($loanAccount->prepayment_amount ?? 0);
        $totalLoanAmount = (float) $loanAccount->loan_amount;

        if ($isInterestOnly) {
            $principalPaid = (float) $loanAccount->emis()->sum('principal_amount');
            $interestPaid = max(0, $totalCollected - $principalPaid);
            $totalInterestRequired = (float) $loanAccount->emis()->sum('interest_amount');
            $outstandingBalance = max(0, $totalLoanAmount - $principalPaid);
            $pendingEmisCount = $loanAccount->emis()->whereIn('status', ['pending', 'partial', 'overdue'])->count();
            $isEligibleForClose = ($outstandingBalance <= 0.05 && $pendingEmisCount <= 0);

            return [
                'loan_account_id'      => $loanAccount->id,
                'loan_mode'            => 'interest_only',
                'total_loan_amount'    => $totalLoanAmount,
                'principal_paid'       => $principalPaid,
                'interest_paid'        => $interestPaid,
                'total_collected'      => $totalCollected,
                'total_interest_req'   => $totalInterestRequired,
                'outstanding_balance'  => $outstandingBalance,
                'pending_emis_count'   => $pendingEmisCount,
                'is_eligible_for_close'=> $isEligibleForClose,
                'status'               => $loanAccount->status,
            ];
        } else {
            $totalPayable = (float) ($loanAccount->total_payable ?: ($totalLoanAmount + (float) $loanAccount->emis()->sum('interest_amount')));
            $totalInterest = max(0, $totalPayable - $totalLoanAmount);
            $outstandingBalance = max(0, $totalPayable - $totalCollected);
            $pendingEmisCount = $loanAccount->emis()->whereIn('status', ['pending', 'partial', 'overdue'])->count();
            $isEligibleForClose = ($outstandingBalance <= 0.05 || $pendingEmisCount <= 0);

            return [
                'loan_account_id'      => $loanAccount->id,
                'loan_mode'            => $loanAccount->loan_mode ?? 'emi',
                'total_loan_amount'    => $totalLoanAmount,
                'total_interest'       => $totalInterest,
                'total_payable'        => $totalPayable,
                'total_collected'      => $totalCollected,
                'outstanding_balance'  => $outstandingBalance,
                'pending_emis_count'   => $pendingEmisCount,
                'is_eligible_for_close'=> $isEligibleForClose,
                'status'               => $loanAccount->status,
            ];
        }
    }

    /**
     * Settle and close a loan account cleanly
     *
     * @param LoanAccount $loanAccount
     * @param array $options
     * @return array
     */
    public function closeLoanAccount(LoanAccount $loanAccount, array $options = []): array
    {
        $summary = $this->calculateLoanClosingSummary($loanAccount);
        $remarks = $options['remarks'] ?? 'Loan account settled and closed.';

        DB::beginTransaction();
        try {
            // Mark all non-paid EMIs as paid/satisfied
            $now = now();
            $loanAccount->emis()
                ->where('status', '!=', 'paid')
                ->get()
                ->each(function ($emi) use ($now, $remarks) {
                    $emi->update([
                        'status'         => 'paid',
                        'pending_amount' => 0,
                        'paid_date'      => $now,
                        'remarks'        => trim(($emi->remarks ?? '') . ' [' . $remarks . ']'),
                    ]);
                });

            $loanAccount->update([
                'status'             => 'closed',
                'closed_at'          => $now,
                'outstanding_amount' => 0,
                'pending_amount'     => 0,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Loan account closed successfully.',
                'summary' => $summary,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('closeLoanAccount error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to close loan account: ' . $e->getMessage(),
            ];
        }
    }


    /**
     * Synchronize EMI balances across the schedule
     * Ensures pending amounts from previous months carry forward correctly
     *
     * @param int $loanAccountId
     * @return void
     */
    public function syncEmiBalances(int $loanAccountId): void
    {
        $loan = LoanAccount::find($loanAccountId);
        if (!$loan) return;

        $emis = Emi::where('loan_account_id', $loanAccountId)
            ->orderBy('instalment_number', 'asc')
            ->get();

        $carriedForwardBalance = 0;

        foreach ($emis as $index => $emi) {
            if ($emi->status === 'closed') {
                continue;
            }

            // ONLY carry forward negative balances (overpayments/credits)
            // Do NOT carry forward positive arrears (let them stay on the original EMI row)
            $emi->previous_balance = ($carriedForwardBalance < 0) ? $carriedForwardBalance : 0;
            
            // Calculate total due for THIS specific installment
            $penaltyAmount = (float) ($emi->penalty_amount ?? 0);

            if ($loan->loan_mode === 'interest_only') {
                if ($emi->status !== 'paid') {
                    // Recalculate full-cycle interest for this unpaid cycle based on principal paid in PRIOR cycles.
                    $newInterest = $this->openLoanFullCycleInterest($loan, $emi);
                    $emi->interest_amount = $newInterest;
                    $emi->total_amount = $newInterest;
                }

                $fullCycle = (float) ($emi->interest_amount ?? $emi->total_amount ?? 0);
                $penaltyAmount = (float) ($emi->penalty_amount ?? 0);
                $paidAmount = round((float) ($emi->paid_amount ?? 0), 2);
                $emi->paid_amount = $paidAmount;
                $principalPaid = round((float) ($emi->principal_amount ?? 0), 2);
                $interestPaid = max(0, round($paidAmount - $principalPaid, 2));

                // Monthly/weekly before due: obligation = crossed-days accrued (+ penalty).
                // Daily / on-or-after due: full cycle.
                $accrued = $this->calculateOpenLoanAccruedInterest($loan, $emi, now(), $fullCycle);
                $cycleObligation = round($accrued + $emi->previous_balance + $penaltyAmount, 2);
                // Never show less obligation than interest already collected on this cycle.
                $cycleObligation = max($cycleObligation, round($interestPaid + $emi->previous_balance, 2));

                // For Open Loans (interest_only), total_due and total_amount represent cycle interest only (plus penalty).
                // Principal repayment is tracked separately in principal_amount and does not inflate the cycle interest due.
                $emi->total_due = (float) round(max(0, $cycleObligation), 2);
                $emi->total_amount = (float) round(max(0, $fullCycle), 2);
                $emi->pending_amount = (float) round(max(0, $emi->total_due - $interestPaid), 2);
                if ($emi->pending_amount <= 0.009) {
                    $emi->pending_amount = 0;
                }
            } else {
                $totalAmount = (float) $emi->total_amount;
                // For display and internal logic, total_due now only represents the current month's obligation + penalties - overpayment credit
                $emi->total_due = round(max(0, $totalAmount + $emi->previous_balance + $penaltyAmount), 2);

                // Recalculate pending amount for this specific installment
                $paidAmount = round((float) ($emi->paid_amount ?? 0), 2);
                $emi->paid_amount = $paidAmount;
                $emi->pending_amount = round($emi->total_due - $paidAmount, 2);
                if (abs((float) $emi->pending_amount) <= 0.009) {
                    $emi->pending_amount = 0;
                }
            }
            
            // Update status based on pending amount and payments made
            if ($emi->pending_amount <= 0.01) {
                if ($emi->status !== 'paid') {
                    $hasCollected = ((float) ($paidAmount ?? 0)) > 0.01;
                    // Open loan day-0 (no crossed days yet): keep cycle pending with ₹0 accrued — do not auto-close.
                    if (
                        $loan->loan_mode === 'interest_only'
                        && ! $hasCollected
                        && $emi->due_date
                        && Carbon::parse($emi->due_date)->startOfDay()->gt(now()->startOfDay())
                    ) {
                        $emi->status = 'pending';
                    } else {
                        $emi->status = 'paid';
                        if (!$emi->paid_date) {
                            $emi->paid_date = now();
                        }
                    }
                }
            } else {
                if (($paidAmount ?? 0) > 0) {
                    $emi->status = 'partial';
                } else {
                    $emi->status = ($emi->due_date && $emi->due_date->isPast()) ? 'overdue' : 'pending';
                }
            }

            $emi->save();
            
            // Carry forward ONLY negative pending amounts (credits) for the next installment
            // Arrears (positive pending) stay on the current installment row
            $carriedForwardBalance = ($emi->pending_amount < 0) ? $emi->pending_amount : 0;
        }
    }

    /**
     * Process payment for Interest-Only (Kandhuvatti) loans
     */
    private function processInterestOnlyPayment($loanAccount, $paymentAmount, $paymentDate, $paymentMethod, $paymentReference = null, $remarks = null, $skipHistory = false, $explicitPrincipal = 0, $bankAccountId = null, $targetEmi = null, bool $skipCashbook = false)
    {
        // Validate loan status
        // Allow payment if:
        //   - Loan is active, OR
        //   - Loan is 'closed' but there are still pending interest EMIs to collect
        //     (happens when full principal was just paid but the closing month's interest is still due)
        $hasPendingInterestEmi = Emi::where('loan_account_id', $loanAccount->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->exists();

        if ($loanAccount->status !== 'active' && !$hasPendingInterestEmi) {
            return [
                'success' => false,
                'message' => 'Loan is not active'
            ];
        }

        if (in_array($paymentMethod, ['upi', 'bank_transfer'], true) && !$bankAccountId) {
            return [
                'success' => false,
                'message' => 'Collection Bank Account is mandatory when paying via UPI / GPay / QR or Bank Transfer.'
            ];
        }

        if ($paymentMethod !== 'wallet') {
            try {
                $bankAccountId = app(\App\Services\Account\ChitAccountingService::class)
                    ->resolveCollectionBankAccountId((string) $paymentMethod, (int) ($bankAccountId ?? 0));
            } catch (\Illuminate\Validation\ValidationException $e) {
                return [
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?: 'Invalid collection bank account.',
                ];
            }
        }

        if ($paymentMethod === 'wallet') {
            $clientId = (int) ($loanAccount->client_id ?? 0);
            if (!$clientId) {
                return ['success' => false, 'message' => 'Unable to resolve customer for wallet payment.'];
            }
            $walletService = app(WalletService::class);
            if ($walletService->balanceForClient($clientId) + 0.01 < (float) $paymentAmount) {
                return [
                    'success' => false,
                    'message' => 'Insufficient wallet balance. Available: ₹' . number_format($walletService->balanceForClient($clientId), 2),
                ];
            }
        }

        DB::beginTransaction();
        try {
            if ($paymentMethod === 'wallet') {
                app(WalletService::class)->debit(
                    (int) $loanAccount->client_id,
                    round((float) $paymentAmount, 2),
                    'Loan EMI Payment',
                    'loan_emi',
                    $loanAccount->id,
                    ['loan_account_id' => $loanAccount->id, 'mode' => 'interest_only']
                );
            }

            $totalPayment = (float) $paymentAmount;
            if ($explicitPrincipal > 0 && $totalPayment <= 0.001) {
                $totalPayment = (float)$explicitPrincipal;
            }
            $remainingPayment = $totalPayment;
            
            // If explicitPrincipal is provided, we use that first, otherwise we use excess logic
            $principalPaid = (float)$explicitPrincipal;
            
            // When a specific interest cycle was chosen (e.g. bulk paying several months),
            // settle that cycle instead of always falling back to the earliest unpaid one.
            // Principal-only collections may land on a cycle that is already paid.
            $emi = null;
            $allowPaidTarget = $explicitPrincipal > 0.01;
            if ($targetEmi && (int) $targetEmi->loan_account_id === (int) $loanAccount->id) {
                if (in_array($targetEmi->status, ['pending', 'partial', 'overdue'], true)
                    || ($allowPaidTarget && in_array($targetEmi->status, ['paid', 'closed'], true))) {
                    $emi = $targetEmi->fresh();
                }
            }

            if (!$emi) {
                $emi = Emi::where('loan_account_id', $loanAccount->id)
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->orderBy('instalment_number', 'asc')
                    ->first();
            }

            if (!$emi && $allowPaidTarget) {
                $emi = $targetEmi?->fresh()
                    ?? Emi::where('loan_account_id', $loanAccount->id)
                        ->orderByDesc('instalment_number')
                        ->first();
            }

            if (!$emi) {
                throw new \Exception('No active interest cycle found for this loan.');
            }

            $isPrincipalOnlyOnSettledCycle = $explicitPrincipal > 0.01
                && in_array($emi->status, ['paid', 'closed'], true);

            if ($isPrincipalOnlyOnSettledCycle) {
                $maxPrincipal = max(0, (float) ($loanAccount->outstanding_amount ?? $loanAccount->loan_amount));
                $principalPaid = min((float) $explicitPrincipal, $maxPrincipal);
                if ($principalPaid <= 0.01) {
                    throw new \Exception('No outstanding principal remaining.');
                }

                $emi->principal_amount = (float) ($emi->principal_amount ?? 0) + $principalPaid;
                $emi->paid_amount = (float) ($emi->paid_amount ?? 0) + $principalPaid;
                $emi->save();
                $interestToPay = 0;
            } else {
            // Apply dynamic penalty if overdue and grace period crossed
            $this->applyDynamicPenaltyIfNeeded($emi, $paymentDate);
            $emi->refresh();

            // Full-cycle max interest for this period (based on outstanding principal)
            $fullCycleInterest = $this->openLoanFullCycleInterest($loanAccount, $emi);
            if ($emi->status !== 'paid' && abs((float) $emi->interest_amount - $fullCycleInterest) > 0.009) {
                $emi->interest_amount = $fullCycleInterest;
                $emi->total_amount = $fullCycleInterest;
            }

            // Monthly/weekly early or mid-cycle: charge only crossed-days accrued interest.
            // Daily: full cycle (proration not applicable).
            $termUnit = $this->resolveOpenLoanTermUnit($loanAccount);
            $accruedInterest = $this->calculateOpenLoanAccruedInterest(
                $loanAccount,
                $emi,
                $paymentDate,
                $fullCycleInterest
            );
            $interestDueNow = $this->openLoanInterestDue($loanAccount, $emi, $paymentDate);

            // 1. Calculate and Apply Interest Payment
            // If explicitPrincipal was provided, interest is (Total - Principal)
            // If not, we pay interest first, then excess to principal
            
            if ($explicitPrincipal > 0) {
                $interestToPay = max(0, $totalPayment - $explicitPrincipal);
                // Cap interest portion at accrued/due for monthly & weekly early pays
                if ($termUnit !== 'daily') {
                    $interestToPay = min($interestToPay, $interestDueNow);
                    $principalPaid = min($explicitPrincipal, max(0, $totalPayment - $interestToPay));
                } else {
                    $principalPaid = min($explicitPrincipal, $totalPayment);
                }
            } else {
                // When paying an interest cycle without explicit principal,
                // the entire payment applies to interest up to the cycle/pending amount.
                // Principal MUST NEVER be reduced when collecting interest only!
                $targetInterest = max($interestDueNow, (float)$emi->interest_amount, (float)$emi->pending_amount, $fullCycleInterest);
                $interestToPay = min($remainingPayment, $targetInterest);
                $principalPaid = 0.0;
            }

            $emi->paid_amount = (float) ($emi->paid_amount ?? 0) + ($interestToPay + $principalPaid);
            $emi->principal_amount = (float) ($emi->principal_amount ?? 0) + $principalPaid;

            $interestPaidTotal = max(0, (float) $emi->paid_amount - (float) ($emi->principal_amount ?? 0));
            $asOf = Carbon::parse($paymentDate)->startOfDay();
            $dueDate = $emi->due_date ? Carbon::parse($emi->due_date)->startOfDay() : null;
            $isEarlyOrMidCycle = $termUnit !== 'daily' && $dueDate && $asOf->lt($dueDate);

            // Effective interest obligation after this payment
            $effectiveInterest = $isEarlyOrMidCycle
                ? max($accruedInterest, $interestPaidTotal)
                : $fullCycleInterest;

            $penalty = (float) ($emi->penalty_amount ?? 0);
            $emi->total_due = round(max(0, $effectiveInterest + $penalty), 2);
            $emi->pending_amount = round(max(0, $emi->total_due - $interestPaidTotal), 2);

            if ($emi->pending_amount <= 0.01) {
                // Closing the interest cycle — early pay writes down unearned interest to accrued days only
                if ($isEarlyOrMidCycle) {
                    $emi->interest_amount = round(max($accruedInterest, $interestPaidTotal), 2);
                    $emi->total_amount = $emi->interest_amount;
                    $emi->total_due = round($emi->total_amount + $penalty, 2);
                } else {
                    $emi->interest_amount = $fullCycleInterest;
                    $emi->total_amount = $fullCycleInterest;
                    $emi->total_due = round($fullCycleInterest + $penalty, 2);
                }
                $emi->pending_amount = 0;
                $emi->status = 'paid';
                $emi->paid_date = $paymentDate;
            } else {
                $emi->status = 'partial';
                $emi->is_partial_paid = true;
                $emi->partial_paid_date = $paymentDate;
                $emi->partial_paid_amount = $emi->paid_amount;
                // Keep full-cycle ceiling so remaining days can still accrue until due date
                $emi->interest_amount = $fullCycleInterest;
                $emi->total_amount = $fullCycleInterest;
                $emi->total_due = round($fullCycleInterest + $penalty, 2);
            }
            $emi->save();
            }

            // 2. Update loan account principal and check for closure
            if ($principalPaid > 0) {
                $totalPrincipalPaid = Emi::where('loan_account_id', $loanAccount->id)->sum('principal_amount');
                $outstandingPrincipal = max(0, (float)$loanAccount->loan_amount - $totalPrincipalPaid);
                
                $loanAccount->outstanding_amount = round($outstandingPrincipal, 2);
                
                if ($loanAccount->outstanding_amount <= 0.01) {
                    // Principal fully paid! Delete future completely unpaid interest cycles
                    Emi::where('loan_account_id', $loanAccount->id)
                        ->where('instalment_number', '>', $emi->instalment_number)
                        ->where(function($q) {
                            $q->where('paid_amount', '<=', 0.01)
                              ->orWhereNull('paid_amount');
                        })
                        ->delete();
                } else {
                    // Recalculate ALL FUTURE cycles of the loan based on the new remaining principal!
                    $newInterest = round($outstandingPrincipal * ($loanAccount->interest_rate / 100));
                    
                    Emi::where('loan_account_id', $loanAccount->id)
                        ->where('instalment_number', '>', $emi->instalment_number)
                        ->whereIn('status', ['pending', 'partial', 'overdue'])
                        ->update([
                            'interest_amount' => $newInterest,
                            'total_amount'    => $newInterest,
                            'total_due'       => $newInterest,
                            'pending_amount'  => $newInterest,
                        ]);
                }
                $loanAccount->save();
            }

            // 4. Create Audit Trail (Collection record)
            if (!$skipHistory) {
                $currentUser = auth()->user();
                $agentId = null;
                if ($currentUser && $currentUser->hasRole('Agent')) {
                    $agentId = optional($currentUser->agent)->id;
                }

                \App\Models\EmiCollection::create([
                    'emi_id'            => $emi->id,
                    'agent_id'          => $agentId,
                    'amount'            => $totalPayment,
                    'payment_method'    => $paymentMethod,
                    'payment_type'      => 'interest_only',
                    'payment_reference' => $paymentReference,
                    'status'            => 'verified',
                    'collected_at'      => $paymentDate,
                    'verified_by'       => $currentUser ? $currentUser->id : null,
                    'verified_at'       => now(),
                    'remarks'           => ($remarks ? $remarks . ' | ' : '') . 'Kandhuvatti payment. Interest: ₹' . number_format($interestToPay, 2) . ', Principal: ₹' . number_format($principalPaid, 2),
                    'bank_account_id'   => $bankAccountId,
                ]);
            }

            if (! $skipCashbook) {
                $accountNumber = $loanAccount->customer_loan_account_number ?: 'N/A';
                $clientName = $loanAccount->client ? $loanAccount->client->client_name : 'Client';
                $this->recordLoanCollectionInCashbook(
                    $bankAccountId,
                    (string) $paymentMethod,
                    (float) $totalPayment,
                    $paymentReference ?: 'COLL-' . time(),
                    \App\Services\Account\AccountingTags::loanIcDescription(
                        (string) $accountNumber,
                        (string) $clientName,
                        null,
                        Auth::id()
                    ),
                    $paymentDate
                );
            }

            // 5. Top up the schedule with any cycle that has already fallen due.
            // Cycles dated in the future are never created here.
            app(OpenLoanCycleService::class)->syncDueCycles($loanAccount->fresh());

            $this->syncEmiBalances($loanAccount->id);
            $this->syncLoanTotals($loanAccount->id);

            if (! self::$suppressPaymentNotifications) {
                event(new \App\Events\PaymentReceivedEvent($emi, $totalPayment));
            }

            DB::commit();
            return [
                'success' => true,
                'message' => 'Kandhuvatti payment processed. New Principal: ₹' . number_format($loanAccount->outstanding_amount, 2),
                'new_balance' => $loanAccount->outstanding_amount,
                'remarks' => 'Kandhuvatti payment. Interest: ₹' . number_format($interestToPay, 2) . ', Principal: ₹' . number_format($principalPaid, 2),
                'data' => [
                    'payment_amount' => $totalPayment,
                    'new_outstanding' => $loanAccount->outstanding_amount
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing interest-only payment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Admin-only: Undo a fully paid EMI payment
     */
    public function undoEmiPayment($emiId, $reason = null)
    {
        $emi = Emi::with(['loanAccount', 'collections'])->findOrFail($emiId);

        if (!in_array($emi->status, ['paid', 'partial', 'partially_paid', 'overdue'])) {
            return [
                'success' => false,
                'message' => 'Only paid or partially paid EMIs can be undone.'
            ];
        }

        if ((float)$emi->paid_amount <= 0.001) {
            return [
                'success' => false,
                'message' => 'This EMI has no payments to undo.'
            ];
        }

        $loanAccount = $emi->loanAccount;

        // Enforce descending order undo only (i.e. can only undo the latest paid/partial EMI)
        $latestPaidEmi = Emi::where('loan_account_id', $loanAccount->id)
            ->where(function($q) {
                $q->where('paid_amount', '>', 0.001)
                  ->orWhereIn('status', ['paid', 'partial', 'partially_paid']);
            })
            ->orderBy('instalment_number', 'desc')
            ->first();

        if ($latestPaidEmi && $latestPaidEmi->id !== $emi->id) {
            return [
                'success' => false,
                'message' => 'You can only undo payments in descending order. Please undo instalment/cycle #' . $latestPaidEmi->instalment_number . ' first.'
            ];
        }

        DB::beginTransaction();
        try {
            // 1. Gather previous payment data for audit log
            $previousCollections = $emi->collections()->where('status', 'verified')->get();
            
            $previousData = [
                'emi' => [
                    'id' => $emi->id,
                    'instalment_number' => $emi->instalment_number,
                    'paid_amount' => $emi->paid_amount,
                    'paid_date' => $emi->paid_date ? $emi->paid_date->format('Y-m-d') : null,
                    'principal_amount' => $emi->principal_amount,
                    'interest_amount' => $emi->interest_amount,
                    'penalty_amount' => $emi->penalty_amount,
                    'status' => $emi->status,
                ],
                'collections' => $previousCollections->map(function($col) {
                    return [
                        'id' => $col->id,
                        'amount' => $col->amount,
                        'payment_method' => $col->payment_method,
                        'payment_type' => $col->payment_type,
                        'payment_reference' => $col->payment_reference,
                        'collected_at' => $col->collected_at ? $col->collected_at->format('Y-m-d H:i:s') : null,
                        'remarks' => $col->remarks,
                    ];
                })->toArray()
            ];

            // 2. Create Audit Log
            $currentUser = Auth::user();
            \App\Models\PaymentAuditLog::create([
                'client_name' => optional(optional(optional($loanAccount)->loanApplication)->client)->client_name,
                'loan_code_name' => optional(optional(optional($loanAccount)->loanApplication)->product)->loan_name,
                'loan_code' => $loanAccount->account_number,
                'receipt_number' => 'RCP-' . str_pad($emi->id, 6, '0', STR_PAD_LEFT),
                'payment_mode' => $emi->payment_method,
                'payment_type' => 'full',
                'payment_amount' => $emi->paid_amount,
                'payment_date' => $emi->paid_date ? $emi->paid_date->format('d-m-Y H:i:s') : null,
                'payment_status' => $emi->status,
                'payment_remark' => $emi->remarks,
                'payment_created_by' => $currentUser ? $currentUser->name : 'System',
                'payment_created_at' => $emi->created_at ? $emi->created_at->format('d-m-Y H:i:s') : null,
                'payment_updated_at' => $emi->updated_at ? $emi->updated_at->format('d-m-Y H:i:s') : null,
                'emi_id' => $emi->id,
                'reason_to_undo' => $reason,
                'loan_account_id' => $loanAccount->id,
                'admin_id' => $currentUser ? $currentUser->id : null,
                'admin_name' => $currentUser ? $currentUser->name : 'Admin',
                'action_type' => 'UNDO',
                'previous_payment_data' => $previousData
            ]);

            // Remove associated bank transactions (original credit + any prior REV/UNDO debit).
            // One payment may cover multiple EMIs in a single cashbook credit — remove/reduce by total.
            $verifiedCollections = $emi->collections()->where('status', 'verified')->get();
            $cashbookCollections = $verifiedCollections->filter(function ($col) {
                $method = strtolower((string) ($col->payment_method ?? ''));

                return $method !== 'wallet' && (float) $col->amount > 0.009;
            });

            if ($cashbookCollections->isNotEmpty()) {
                try {
                    $bankTxService = app(\App\Services\Account\BankTransactionsService::class);
                    $chitAccounting = app(\App\Services\Account\ChitAccountingService::class);

                    $primary = $cashbookCollections->sortByDesc('id')->first();
                    $bankAccountId = $chitAccounting->resolveBankAccountIdForCollectionUndo(
                        (string) ($primary->payment_method ?? $emi->payment_method ?? 'cash'),
                        (int) ($primary->bank_account_id
                            ?: ($cashbookCollections->first(fn ($c) => (int) ($c->bank_account_id ?? 0) > 0)?->bank_account_id ?? 0))
                    );

                    $accountNumber = $loanAccount->customer_loan_account_number ?? $loanAccount->account_number ?? 'N/A';
                    $refs = [];
                    foreach ($cashbookCollections as $col) {
                        if (! empty($col->payment_reference)) {
                            $refs[] = (string) $col->payment_reference;
                            $refs[] = $col->payment_reference . '-' . $emi->instalment_number;
                        }
                        $refs[] = (string) $col->id;
                        $refs[] = 'COLL-' . $col->id;
                    }
                    if (! empty($emi->payment_reference)) {
                        $refs[] = (string) $emi->payment_reference;
                    }

                    $undoAmount = round((float) $cashbookCollections->sum('amount'), 2);
                    $removed = $bankTxService->removeCollectionTransactions(
                        $bankAccountId,
                        $undoAmount,
                        [
                            'references' => array_values(array_unique(array_filter($refs))),
                            'identity_contains' => array_values(array_filter([
                                (string) $accountNumber,
                            ])),
                            'emi_numbers' => [(int) $emi->instalment_number],
                            'module_tag' => \App\Services\Account\AccountingTags::MODULE_LOAN,
                            'entry_tags' => [\App\Services\Account\AccountingTags::ENTRY_EMI],
                            'allow_partial_reduce' => true,
                        ]
                    );

                    if ($removed < 1) {
                        Log::warning('EMI undo: no matching bank transaction removed', [
                            'emi_id' => $emi->id,
                            'amount' => $undoAmount,
                            'account' => $accountNumber,
                            'bank_account_id' => $bankAccountId,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Bank transaction removal error in undoEmiPayment: ' . $e->getMessage());
                    throw $e;
                }
            }

            // 3. Mark EmiCollections as undone or delete them
            $emi->collections()->delete();

            // 4. Also delete any AgentActivity related to payment on this EMI
            \App\Models\AgentActivity::where('emi_id', $emi->id)->where('type', 'payment')->delete();

            // 5. Restore EMI properties
            $emi->paid_amount = 0;
            $emi->paid_date = null;
            $emi->payment_method = null;
            $emi->payment_reference = null;
            $emi->remarks = null;
            
            if ($loanAccount->loan_mode === 'interest_only') {
                $emi->principal_amount = 0;
            }
            
            $emi->status = 'pending';
            $emi->save();

            // Revert EmiAgentAssignment
            $assignment = \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)->first();
            if ($assignment) {
                $assignment->update([
                    'status' => 'assigned',
                    'resolved_at' => null,
                ]);
            } else {
                $client = $loanAccount->client ?? optional($loanAccount->loanApplication)->client;
                if ($client && $client->assigned_to) {
                    \App\Models\EmiAgentAssignment::create([
                        'emi_id' => $emi->id,
                        'agent_id' => $client->assigned_to,
                        'status' => 'assigned',
                        'assigned_at' => now(),
                        'remarks' => 'Auto-assigned on payment undo'
                    ]);
                }
            }

            // 6. For Kandhuvatti loans, if we undo the paid EMI, and a subsequent buffer EMI was created, we should clean up.
            if ($loanAccount->loan_mode === 'interest_only') {
                $futureEmis = Emi::where('loan_account_id', $loanAccount->id)
                    ->where('instalment_number', '>', $emi->instalment_number)
                    ->get();
                
                foreach ($futureEmis as $fEmi) {
                    if ($fEmi->paid_amount <= 0.01) {
                        \App\Models\EmiAgentAssignment::where('emi_id', $fEmi->id)->delete();
                        $fEmi->delete();
                    }
                }
            }

            // 7. If the loan account was closed, revert it back to active!
            if ($loanAccount->status === 'closed') {
                $loanAccount->status = 'active';
                $loanAccount->closed_at = null;
                $loanAccount->save();
            }

            // 8. Re-sync balances & totals
            $this->syncLoanTotals($loanAccount->id);
            $this->syncEmiBalances($loanAccount->id);
            $this->ensureKandhuvattiBuffer($loanAccount->id);

            // 9. Send Admin Notification for Undo Payment
            \App\Models\AdminNotification::create([
                'type' => 'undo_payment',
                'title' => 'Payment Undone',
                'message' => 'Payment of ₹' . number_format($emi->paid_amount, 2) . ' for Loan A/C ' . $loanAccount->account_number . ' (EMI #' . $emi->instalment_number . ') was undone.',
                'link' => route('loan-account-view', $loanAccount->id),
                'icon_class' => 'ri-arrow-go-back-line',
                'badge_color' => 'warning'
            ]);

            DB::commit();
            return [
                'success' => true,
                'message' => 'Payment undone successfully.'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Undo payment failed', ['emi_id' => $emiId, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Failed to undo payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Admin-only: Delete a single payment/collection entry
     */
    public function deleteEmiCollection($collectionId, $reason = null)
    {
        $collection = \App\Models\EmiCollection::with(['emi.loanAccount'])->findOrFail($collectionId);
        $emi = $collection->emi;
        $loanAccount = $emi->loanAccount;

        // Enforce descending order undo/deletion only (i.e. can only delete collection from the latest paid/partial EMI)
        $latestPaidEmi = Emi::where('loan_account_id', $loanAccount->id)
            ->where(function($q) {
                $q->where('paid_amount', '>', 0.001)
                  ->orWhereIn('status', ['paid', 'partial', 'partially_paid']);
            })
            ->orderBy('instalment_number', 'desc')
            ->first();

        if ($latestPaidEmi && $latestPaidEmi->id !== $emi->id) {
            return [
                'success' => false,
                'message' => 'You can only delete payment entries in descending order. Please delete or undo instalment/cycle #' . $latestPaidEmi->instalment_number . ' first.'
            ];
        }

        DB::beginTransaction();
        try {
            // 1. Gather previous payment data for audit log
            $previousData = [
                'collection' => [
                    'id' => $collection->id,
                    'amount' => $collection->amount,
                    'payment_method' => $collection->payment_method,
                    'payment_type' => $collection->payment_type,
                    'payment_reference' => $collection->payment_reference,
                    'collected_at' => $collection->collected_at ? $collection->collected_at->format('Y-m-d H:i:s') : null,
                    'remarks' => $collection->remarks,
                    'status' => $collection->status,
                ],
                'emi' => [
                    'id' => $emi->id,
                    'instalment_number' => $emi->instalment_number,
                    'paid_amount' => $emi->paid_amount,
                    'paid_date' => $emi->paid_date ? $emi->paid_date->format('Y-m-d') : null,
                    'principal_amount' => $emi->principal_amount,
                    'interest_amount' => $emi->interest_amount,
                    'penalty_amount' => $emi->penalty_amount,
                    'status' => $emi->status,
                ]
            ];

            // 2. Create Audit Log
            $currentUser = Auth::user();
            \App\Models\PaymentAuditLog::create([
                'client_name' => optional(optional(optional($loanAccount)->loanApplication)->client)->client_name,
                'loan_code_name' => optional(optional(optional($loanAccount)->loanApplication)->product)->loan_name,
                'loan_code' => $loanAccount->account_number,
                'receipt_number' => 'RCP-' . str_pad($emi->id, 6, '0', STR_PAD_LEFT),
                'payment_mode' => $collection->payment_method,
                'payment_type' => $collection->payment_type,
                'payment_amount' => $collection->amount,
                'payment_date' => $collection->collected_at ? $collection->collected_at->format('Y-m-d') : null,
                'payment_status' => $collection->status,
                'payment_remark' => $collection->remarks,
                'payment_created_by' => $currentUser ? $currentUser->name : 'System',
                'payment_created_at' => $collection->created_at ? $collection->created_at->format('Y-m-d H:i:s') : null,
                'payment_updated_at' => $collection->updated_at ? $collection->updated_at->format('Y-m-d H:i:s') : null,
                'emi_id' => $emi->id,
                'reason_to_undo' => $reason,
                'loan_account_id' => $loanAccount->id,
                'admin_id' => $currentUser ? $currentUser->id : null,
                'admin_name' => $currentUser ? $currentUser->name : 'Admin',
                'action_type' => 'DELETE',
                'previous_payment_data' => $previousData
            ]);

            // 3. Deduct from EMI paid_amount
            $emi->paid_amount = max(0, $emi->paid_amount - $collection->amount);
            
            // If Kandhuvatti (interest-only), deduct from principal paid if this collection represented a principal payment
            if ($loanAccount->loan_mode === 'interest_only') {
                $principalPaid = 0;
                if (preg_match('/Principal:\s*₹?\s*([0-9.,]+)/u', $collection->remarks ?? '', $matches)) {
                    $principalPaid = (float)str_replace(',', '', $matches[1]);
                } else if ($collection->payment_type === 'partial' || str_contains(strtolower($collection->remarks ?? ''), 'prepayment') || str_contains(strtolower($collection->remarks ?? ''), 'principal')) {
                    $principalPaid = $collection->amount;
                }
                $emi->principal_amount = max(0, $emi->principal_amount - $principalPaid);
            }

            // Remove associated bank transaction (original credit + any REV/UNDO debit)
            try {
                $method = strtolower((string) ($collection->payment_method ?? $emi->payment_method ?? 'cash'));
                if ($method !== 'wallet' && (float) $collection->amount > 0.009) {
                    $bankTxService = app(\App\Services\Account\BankTransactionsService::class);
                    $chitAccounting = app(\App\Services\Account\ChitAccountingService::class);
                    $bankAccountId = $chitAccounting->resolveBankAccountIdForCollectionUndo(
                        $method,
                        (int) ($collection->bank_account_id ?: 0)
                    );

                    $accountNumber = $loanAccount->customer_loan_account_number ?? $loanAccount->account_number ?? 'N/A';
                    $refs = array_values(array_filter([
                        $collection->payment_reference,
                        $collection->payment_reference
                            ? $collection->payment_reference . '-' . $emi->instalment_number
                            : null,
                        (string) $collection->id,
                        'COLL-' . $collection->id,
                        $emi->payment_reference,
                    ]));

                    $removed = $bankTxService->removeCollectionTransactions(
                        $bankAccountId,
                        (float) $collection->amount,
                        [
                            'references' => $refs,
                            'identity_contains' => array_values(array_filter([
                                (string) $accountNumber,
                            ])),
                            'emi_numbers' => [(int) $emi->instalment_number],
                            'module_tag' => \App\Services\Account\AccountingTags::MODULE_LOAN,
                            'entry_tags' => [\App\Services\Account\AccountingTags::ENTRY_EMI],
                            'allow_partial_reduce' => true,
                        ]
                    );

                    if ($removed < 1) {
                        Log::warning('EMI collection delete: no matching bank transaction removed', [
                            'collection_id' => $collection->id,
                            'emi_id' => $emi->id,
                            'amount' => (float) $collection->amount,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Bank transaction removal error in deleteEmiCollection: ' . $e->getMessage());
                throw $e;
            }

            // 4. Delete the collection record
            $collection->delete();

            // 5. Delete AgentActivity related to this specific collection
            \App\Models\AgentActivity::where('emi_id', $emi->id)
                ->where('type', 'payment')
                ->where('description', '₹' . number_format($collection->amount, 2))
                ->delete();

            // 6. Save Emi changes
            $emi->save();

            // Revert EmiAgentAssignment
            $assignment = \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)->first();
            if ($assignment) {
                $assignment->update([
                    'status' => 'assigned',
                    'resolved_at' => null,
                ]);
            } else {
                $client = $loanAccount->client ?? optional($loanAccount->loanApplication)->client;
                if ($client && $client->assigned_to) {
                    \App\Models\EmiAgentAssignment::create([
                        'emi_id' => $emi->id,
                        'agent_id' => $client->assigned_to,
                        'status' => 'assigned',
                        'assigned_at' => now(),
                        'remarks' => 'Auto-assigned on payment collection deletion'
                    ]);
                }
            }

            // 7. If the loan account was closed, revert it back to active!
            if ($loanAccount->status === 'closed') {
                $loanAccount->status = 'active';
                $loanAccount->closed_at = null;
                $loanAccount->save();
            }

            // 8. Re-sync balances & totals
            $this->syncLoanTotals($loanAccount->id);
            $this->syncEmiBalances($loanAccount->id);
            $this->ensureKandhuvattiBuffer($loanAccount->id);

            DB::commit();
            return [
                'success' => true,
                'message' => 'Payment entry deleted successfully.'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete payment collection failed', ['collection_id' => $collectionId, 'error' => $e->getMessage()]);
            return [       
                'success' => false,
                'message' => 'Failed to delete payment entry: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restore any open-loan interest cycle that is due but missing, for example
     * after a payment was undone or a collection deleted. Cycles dated in the
     * future are never recreated.
     */
    public function ensureKandhuvattiBuffer(int $loanAccountId): void
    {
        $loanAccount = LoanAccount::with(['emis', 'loanApplication'])->findOrFail($loanAccountId);

        app(OpenLoanCycleService::class)->syncDueCycles($loanAccount);
    }

    /**
     * Dynamically apply penalty (fixed or percentage) if overdue past the grace period.
     */
    public function applyDynamicPenaltyIfNeeded(Emi $emi, string $paymentDate)
    {
        $penaltyConfig = \App\Models\LoanConfiguration::getPenaltyConfig();
        if (!$penaltyConfig || !$penaltyConfig->is_active) {
            return;
        }

        // Only apply penalty if not already applied
        if ($emi->penalty_amount > 0) {
            return;
        }

        // Only apply for pending/partial/overdue EMIs
        if (!in_array($emi->status, ['pending', 'partial', 'overdue'])) {
            return;
        }

        $loanAccount = $emi->loanAccount;
        if (!$loanAccount) {
            return;
        }

        $penaltyChargeType = $penaltyConfig->penalty_charge_type ?? 'fixed';

        $graceDays = ($penaltyConfig->eligibility_days !== null)
            ? $penaltyConfig->eligibility_days
            : ($loanAccount->grace_period_days ?? 0);

        $dueDate = \Carbon\Carbon::parse($emi->due_date);
        $penaltyStartDate = $dueDate->copy()->addDays($graceDays);
        $payDate = \Carbon\Carbon::parse($paymentDate)->startOfDay();

        // If payment date is past the penalty start date, apply the penalty!
        if ($payDate->gt($penaltyStartDate)) {
            $penaltyAmount = $penaltyConfig->calculatePenaltyForEmi($emi, $loanAccount);

            if ($penaltyAmount <= 0) {
                return;
            }

            $emi->penalty_amount = $penaltyAmount;
            $emi->total_due += $penaltyAmount;
            $emi->pending_amount += $penaltyAmount;
            $emi->last_penalty_date = $payDate->toDateString();
            $emi->status = 'overdue';
            $emi->save();

            $typeLabel = ($penaltyChargeType === 'percentage')
                ? "{$penaltyConfig->charge_value}% of EMI principal"
                : "₹{$penaltyAmount}";

            \Illuminate\Support\Facades\Log::info("Dynamically applied {$typeLabel} penalty (₹{$penaltyAmount}) to EMI #{$emi->instalment_number} for Loan Account {$loanAccount->account_number} during payment.");
        }
    }

    /**
     * Credit company bank / Cash in Hand for a loan EMI collection.
     * Always posts (including agent verify with skipHistory). Wallet is skipped.
     */
    public function recordLoanCollectionInCashbook(
        $bankAccountId,
        string $method,
        float $amount,
        string $reference,
        string $description,
        $date,
        ?string $entryTag = null,
        bool $throwOnFailure = false
    ): void {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0.009) {
            return;
        }

        $method = strtolower(trim($method));
        if (in_array($method, ['wallet'], true)) {
            return;
        }

        try {
            $resolvedId = app(\App\Services\Account\ChitAccountingService::class)
                ->resolveCollectionBankAccountId($method, (int) ($bankAccountId ?? 0));

            if (! $resolvedId) {
                if ($throwOnFailure) {
                    throw new \RuntimeException('Collection bank account could not be resolved.');
                }

                return;
            }

            app(\App\Services\Account\BankTransactionsService::class)->createLoanCollectionTransaction(
                $resolvedId,
                $amount,
                $reference,
                $description,
                $date,
                AccountingTags::MODULE_LOAN,
                $entryTag ?: AccountingTags::ENTRY_EMI
            );
        } catch (\Throwable $e) {
            Log::error('Bank/cashbook recording error for loan collection: ' . $e->getMessage(), [
                'method' => $method,
                'amount' => $amount,
                'bank_account_id' => $bankAccountId,
            ]);
            if ($throwOnFailure) {
                throw $e;
            }
        }
    }

    protected function postForeclosureRevenues(
        LoanAccount $loanAccount,
        float $interestAmount,
        float $chargesAmount,
        int $bankAccountId,
        string $reference,
        string $date,
        string $accountNumber,
        string $clientName
    ): void {
        $chitAccounting = app(ChitAccountingService::class);
        $lines = [
            [
                'amount' => round(max(0, $interestAmount), 2),
                'name' => 'Loan Interest',
                'code' => 'LOAN-INT',
                'entry' => AccountingTags::ENTRY_INTEREST,
                'description' => "Loan interest — {$accountNumber} — {$clientName} — foreclosure",
                'suffix' => 'INT',
            ],
            [
                'amount' => round(max(0, $chargesAmount), 2),
                'name' => 'Loan Foreclosure Charges',
                'code' => 'LOAN-FORECLOSE',
                'entry' => AccountingTags::ENTRY_FORECLOSE,
                'description' => "Loan foreclosure charges — {$accountNumber} — {$clientName}",
                'suffix' => 'FC',
            ],
        ];

        foreach ($lines as $line) {
            if ($line['amount'] <= 0.009) {
                continue;
            }

            $chitAccounting->postExternalRevenue(
                $line['name'],
                $line['code'],
                $line['amount'],
                $line['description'],
                $reference . '-' . $line['suffix'],
                $date,
                $bankAccountId,
                AccountingTags::MODULE_LOAN,
                $line['entry']
            );
        }
    }
}

