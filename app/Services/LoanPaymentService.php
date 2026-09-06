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
use Carbon\Carbon;

class LoanPaymentService
{
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

        $totalAmount = round(
            $outstandingAmount + $interestOutstanding + $foreclosureCharges + $extraChargeAmount
        );

        return [
            'outstanding_amount' => $outstandingAmount,
            'interest_outstanding' => $interestOutstanding,
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
        $interestOutstanding = $amounts['interest_outstanding'];
        $foreclosureCharges = $amounts['foreclosure_charges'];
        $extraChargeAmount = $amounts['extra_charge_amount'];
        $totalForeclosureAmount = $amounts['total_amount'];
        $chargesPercentage = $amounts['charges_percentage'];
        $extraChargePercent = $amounts['extra_charge_percent'];

        $notes = $options['foreclosure_notes'] ?? null;

        $oldTenure = (int) ($loanAccount->tenure ?? 0);
        $oldPrincipal = (float) $outstandingAmount;

        DB::beginTransaction();
        try {
            $now = now();

            // Mark every non-paid EMI as paid with current timestamp
            $loanAccount->emis()
                ->where('status', '!=', 'paid')
                ->get()
                ->each(function ($emi) use ($now) {
                    $emi->update([
                        'status' => 'paid',
                        'paid_amount' => $emi->total_due ?: $emi->total_amount,
                        'pending_amount' => 0,
                        'paid_date' => $now,
                        'remarks' => trim(($emi->remarks ?? '') . ' [Foreclosed settlement]')
                    ]);
                });

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
                    'foreclosure_charges' => $foreclosureCharges,
                    'extra_charge_percent' => $extraChargePercent,
                    'extra_charge_amount' => $extraChargeAmount,
                    'total_foreclosure_amount' => $totalForeclosureAmount
                ]
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
    public function processPayment($emiId, $amount, $date, $method, $reference = null, $remarks = null, $skipHistory = false, $principalAmount = 0, $bypassPriorCheck = false)
    {
        $emi = Emi::with('loanAccount')->findOrFail($emiId);
        
        // Redirect to Interest-Only logic if mode is Kandhuvatti
        if ($emi->loanAccount && $emi->loanAccount->loan_mode === 'interest_only') {
            return $this->processInterestOnlyPayment($emi->loanAccount, $amount, $date, $method, $reference, $remarks, $skipHistory, $principalAmount);
        }
        
        // Ensure the loan's EMIs are synchronized with the latest non-cumulative logic before processing
        $this->syncEmiBalances($emi->loan_account_id);
        
        // Apply dynamic penalty if overdue and grace period crossed
        $this->applyDynamicPenaltyIfNeeded($emi, $date);
        
        $emi->refresh(); // Refresh to get the updated total_due and status

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


        // Calculate potential new total paid
        $currentPaid = $emi->paid_amount ?? 0;
        $totalDue = $emi->total_due;
        $newTotalPaid = $currentPaid + $amount;

        $remainingDue = $totalDue - $newTotalPaid;

        $isFullPayment = $newTotalPaid >= ($totalDue - 0.01);

        DB::beginTransaction();
        try {
            $remainingPayment = (float) $amount;
            
            // Get this and all subsequent EMIs for this loan
            $targetAndFutureEmis = Emi::where('loan_account_id', $emi->loan_account_id)
                ->where('instalment_number', '>=', $emi->instalment_number)
                ->orderBy('instalment_number', 'asc')
                ->get();
                
            $firstIteration = true;
            $updatedEmis = [];
            
            foreach ($targetAndFutureEmis as $currentEmi) {
                if ($remainingPayment <= 0.001) {
                    break;
                }
                
                $currentEmi->refresh();
                $emiTotalDue = (float) $currentEmi->total_due;
                $emiPaidAmount = (float) ($currentEmi->paid_amount ?? 0);
                $emiPending = max(0.00, $emiTotalDue - $emiPaidAmount);
                
                // If this is a future EMI and it's already paid, skip it
                if (!$firstIteration && $emiPending <= 0.001) {
                    continue;
                }
                
                // Determine how much to apply to this EMI
                $appliedAmount = min($remainingPayment, $emiPending);
                
                // If it is the first iteration, and remainingPayment is larger than emiPending,
                // we apply only the portion that covers this EMI, and the rest goes to future EMIs.
                // If there are no future EMIs, we apply the entire remaining payment to this EMI.
                if ($firstIteration && $remainingPayment > $emiPending && $targetAndFutureEmis->count() > 1) {
                    $appliedAmount = $emiPending;
                } else if ($firstIteration && $targetAndFutureEmis->count() == 1) {
                    // Only one EMI exists, so apply all remaining
                    $appliedAmount = $remainingPayment;
                }
                
                // For last EMI in the list, if there is still excess payment, apply it all (overpayment)
                if ($currentEmi->id === $targetAndFutureEmis->last()->id) {
                    $appliedAmount = $remainingPayment;
                }
                
                $newPaidAmount = $emiPaidAmount + $appliedAmount;
                $newPendingAmount = max(0.00, $emiTotalDue - $newPaidAmount);
                
                $isEmiFull = $newPendingAmount <= 0.01;
                
                $updateData = [
                    'paid_amount' => $newPaidAmount,
                    'payment_method' => $method,
                    'payment_reference' => $reference,
                    'remarks' => $remarks ? trim(($currentEmi->remarks ?? '') . "\n" . $remarks) : $currentEmi->remarks,
                    'paid_date' => $date,
                    'pending_amount' => $newPendingAmount,
                ];
                
                if ($isEmiFull) {
                    $updateData['status'] = 'paid';
                    $updateData['is_partial_paid'] = false;
                    $updateData['partial_paid_date'] = null;
                    $updateData['partial_paid_amount'] = 0;
                } else {
                    $updateData['status'] = 'partial';
                    $updateData['is_partial_paid'] = true;
                    $updateData['partial_paid_amount'] = $newPaidAmount;
                    $updateData['partial_paid_date'] = $date;
                }
                
                $currentEmi->update($updateData);
                $updatedEmis[] = [
                    'emi' => $currentEmi,
                    'applied' => $appliedAmount,
                    'is_full' => $isEmiFull
                ];
                
                // Record this payment in history (Audit Trail)
                if (!$skipHistory) {
                    try {
                        \App\Models\EmiCollection::create([
                            'emi_id' => $currentEmi->id,
                            'amount' => $appliedAmount,
                            'payment_method' => $method,
                            'payment_type' => $isEmiFull ? 'full' : 'partial',
                            'payment_reference' => $reference,
                            'status' => 'verified',
                            'collected_at' => $date,
                            'verified_by' => Auth::id(),
                            'verified_at' => now(),
                            'remarks' => $remarks ?: ($isEmiFull ? 'Full EMI payment processed' : 'Partial EMI payment processed')
                        ]);
                    } catch (\Exception $e) {
                        Log::error('EmiCollection creation error in processPayment: ' . $e->getMessage());
                    }
                    
                    // Record this specific payment in AgentActivity for history popup
                    try {
                        $activityAgentId = Auth::user()->agent?->id ?? \App\Models\Agent::where('user_id', Auth::id())->value('id');
                        if ($activityAgentId) {
                            \App\Models\AgentActivity::create([
                                'emi_id' => $currentEmi->id,
                                'agent_id' => $activityAgentId,
                                'type' => 'payment',
                                'description' => "₹" . number_format($appliedAmount, 2),
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
                
                $remainingPayment -= $appliedAmount;
                $firstIteration = false;
            }
            
            // Fire events for all updated EMIs
            foreach ($updatedEmis as $item) {
                event(new \App\Events\PaymentReceivedEvent($item['emi'], $item['applied']));
            }
            
            // Update loan account totals
            $this->syncLoanTotals($emi->loan_account_id);
            
            // Sync EMI balances to carry forward pending amounts
            $this->syncEmiBalances($emi->loan_account_id);
            
            DB::commit();
            
            $emi->refresh();
            return [
                'success' => true,
                'message' => $emi->status === 'paid' ? 'EMI fully paid.' : 'Partial payment recorded.',
                'data' => [
                    'emi_id' => $emi->id,
                    'paid_amount' => $emi->paid_amount,
                    'due_amount' => $emi->pending_amount,
                    'status' => $emi->status
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

            // Delete Future EMIs
            $loanAccount->emis()->where('instalment_number', '>', $lastPaidInstalment)->delete();

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
                ->sum('total_amount');

            $totalPastPaid = DB::table('emis')
                ->where('loan_account_id', $loanAccount->id)
                ->where('instalment_number', '<=', $lastPaidInstalment)
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
    public function processPartialPayment($loanAccountId, $paymentAmount, $paymentDate, $paymentMethod, $paymentReference = null, $remarks = null, $skipHistory = false)
    {
        $loanAccount = LoanAccount::findOrFail($loanAccountId);

        // Redirect to Interest-Only logic if mode is Kandhuvatti
        if ($loanAccount->loan_mode === 'interest_only') {
            return $this->processInterestOnlyPayment($loanAccount, $paymentAmount, $paymentDate, $paymentMethod, $paymentReference, $remarks, $skipHistory);
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
                                'remarks' => $remarks ?: 'Partial payment processed via service'
                            ]);
                        } catch (\Exception $e) {
                            Log::error('EmiCollection creation error in LoanPaymentService: ' . $e->getMessage());
                        }
                    }

                    $emisUpdated[] = [
                        'month' => $emi->instalment_number,
                        'status' => $status,
                        'paid' => $paymentForThisEmi,
                        'pending' => $newPendingAmount,
                    ];

                    $lastUpdatedEmi = $emi;
                }
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
            $loan->emis()->where('status', '!=', 'paid')->get()->each(function ($emi) use ($payment) {
                $emi->update([
                    'status' => 'paid',
                    'paid_amount' => $emi->total_due,
                    'pending_amount' => 0,
                    'paid_date' => now(),
                    'payment_reference' => $payment->payment_id,
                ]);
            });

            $loan->update([
                'status' => 'closed',
                'is_foreclosed' => true,
                'closed_at' => now(),
                'outstanding_amount' => 0,
                'paid_amount' => $loan->total_payable,
                'foreclosure_amount' => $payment->amount, // payment amount
            ]);

            DB::commit();

            $this->syncLoanTotals($loanAccount->id);

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
        
        if ($loan->loan_mode !== 'interest_only') {
            $totalPaid = min($totalPaid, (float) $loan->total_payable);
        }
        $loan->paid_amount = $totalPaid;

        $isReducing = $loan->loanApplication && $loan->loanApplication->product && in_array($loan->loanApplication->product->interest_type, ['reducing', 'declining_balance']);

        if ($loan->loan_mode === 'interest_only') {
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

        $pendingEmisCount = $loan->emis()->whereIn('status', ['pending', 'partial', 'overdue'])->count();
        
        $shouldClose = false;
        if ($loan->loan_mode === 'interest_only') {
            if ($loan->outstanding_amount <= 0.05 && $pendingEmisCount <= 0) {
                $shouldClose = true;
            }
        } else {
            if ($loan->outstanding_amount <= 0.05 || $pendingEmisCount <= 0) {
                $shouldClose = true;
            }
        }

        if ($shouldClose && $loan->status === 'active') {
            $loan->status = 'closed';
            $loan->closed_at = $loan->closed_at ?? now();
        }

        $loan->save();
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
            // ONLY carry forward negative balances (overpayments/credits)
            // Do NOT carry forward positive arrears (let them stay on the original EMI row)
            $emi->previous_balance = ($carriedForwardBalance < 0) ? $carriedForwardBalance : 0;
            
            // Calculate total due for THIS specific installment
            $penaltyAmount = (float) ($emi->penalty_amount ?? 0);

            if ($loan->loan_mode === 'interest_only') {
                if ($emi->status !== 'paid') {
                    // Recalculate interest for this unpaid cycle based on principal paid in PRIOR cycles!
                    $priorPrincipalPaid = Emi::where('loan_account_id', $loan->id)
                        ->where('instalment_number', '<', $emi->instalment_number)
                        ->sum('principal_amount');
                    $priorOutstanding = max(0, (float)$loan->loan_amount - $priorPrincipalPaid);
                    $newInterest = round($priorOutstanding * ($loan->interest_rate / 100));
                    
                    $emi->interest_amount = $newInterest;
                    $emi->total_amount = $newInterest;
                }

                // For Kandhuvatti EMIs, the obligation is the interest amount for the cycle.
                // total_amount may be 0 or equal to interest_amount depending on how EMIs were generated;
                // always use interest_amount as the authoritative cycle obligation.
                $cycleAmount = (float) ($emi->interest_amount ?? $emi->total_amount ?? 0);
                $emi->total_due = (float) round(max(0, $cycleAmount + $emi->previous_balance + $penaltyAmount));

                // Recalculate pending: interest paid = paid_amount minus any principal repayment
                $paidAmount    = (float) ($emi->paid_amount ?? 0);
                $principalPaid = (float) ($emi->principal_amount ?? 0);
                $interestPaid  = max(0, $paidAmount - $principalPaid);
                $emi->pending_amount = (float) round(max(0, $emi->total_due - $interestPaid));
            } else {
                $totalAmount = (float) $emi->total_amount;
                // For display and internal logic, total_due now only represents the current month's obligation + penalties - overpayment credit
                $emi->total_due = max(0, $totalAmount + $emi->previous_balance + $penaltyAmount);

                // Recalculate pending amount for this specific installment
                $paidAmount = (float) ($emi->paid_amount ?? 0);
                $emi->pending_amount = $emi->total_due - $paidAmount;
            }
            
            // Update status based on pending amount and payments made
            if ($emi->pending_amount <= 0.01) {
                if ($emi->status !== 'paid') {
                    $emi->status = 'paid';
                    if (!$emi->paid_date) {
                        $emi->paid_date = now();
                    }
                }
            } else {
                if ($paidAmount > 0) {
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
    private function processInterestOnlyPayment($loanAccount, $paymentAmount, $paymentDate, $paymentMethod, $paymentReference = null, $remarks = null, $skipHistory = false, $explicitPrincipal = 0)
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

        DB::beginTransaction();
        try {
            $totalPayment = (float) $paymentAmount;
            if ($explicitPrincipal > 0 && $totalPayment <= 0.001) {
                $totalPayment = (float)$explicitPrincipal;
            }
            $remainingPayment = $totalPayment;
            
            // If explicitPrincipal is provided, we use that first, otherwise we use excess logic
            $principalPaid = (float)$explicitPrincipal;
            
            $emi = Emi::where('loan_account_id', $loanAccount->id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->orderBy('instalment_number', 'asc')
                ->first();

            if (!$emi) {
                // If no pending EMI but they want to pay principal, we might need to handle that.
                // For now, let's assume there's always at least one EMI in the 12-cycle buffer.
                throw new \Exception('No active interest cycle found for this loan.');
            }

            // Apply dynamic penalty if overdue and grace period crossed
            $this->applyDynamicPenaltyIfNeeded($emi, $paymentDate);
            $emi->refresh();

            // 1. Calculate and Apply Interest Payment
            // If explicitPrincipal was provided, interest is (Total - Principal)
            // If not, we pay interest first, then excess to principal
            
            if ($explicitPrincipal > 0) {
                $interestToPay = max(0, $totalPayment - $explicitPrincipal);
                $principalPaid = min($explicitPrincipal, $totalPayment); // Safety check
            } else {
                $interestDue = (float) $emi->pending_amount;
                $interestToPay = min($remainingPayment, $interestDue);
                $principalPaid = max(0, $remainingPayment - $interestToPay);
            }

            $emi->paid_amount += ($interestToPay + $principalPaid);
            $emi->principal_amount = ($emi->principal_amount ?? 0) + $principalPaid;
            $emi->pending_amount = max(0, ($emi->interest_amount - ($emi->paid_amount - $emi->principal_amount)));
            
            // Update EMI totals strictly to represent the interest cycle amount
            $emi->total_amount = $emi->interest_amount;
            $emi->total_due = $emi->interest_amount; 

            if ($emi->pending_amount <= 0.01) {
                $emi->status = 'paid';
                $emi->paid_date = $paymentDate;
            } else {
                $emi->status = 'partial';
                $emi->is_partial_paid = true;
                $emi->partial_paid_date = $paymentDate;
                $emi->partial_paid_amount = $emi->paid_amount;
            }
            $emi->save();

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
                    'remarks'           => ($remarks ? $remarks . ' | ' : '') . 'Kandhuvatti payment. Interest: ₹' . number_format($interestToPay, 2) . ', Principal: ₹' . number_format($principalPaid, 2)
                ]);
            }

            // 5. Maintain 12-cycle buffer: Schedule the NEXT cycle at the end of the list
            $totalPrincipalPaid = Emi::where('loan_account_id', $loanAccount->id)->sum('principal_amount');
            $outstandingPrincipal = max(0, (float)$loanAccount->loan_amount - $totalPrincipalPaid);

            if ($emi->status === 'paid' && $loanAccount->status === 'active' && $outstandingPrincipal > 0.01) {
                // Find the latest instalment number currently scheduled
                $lastEmi = Emi::where('loan_account_id', $loanAccount->id)
                    ->orderBy('instalment_number', 'desc')
                    ->first();
                
                $nextInstalmentNumber = ($lastEmi->instalment_number ?? $emi->instalment_number) + 1;
                
                // Calculate next interest based on remaining principal
                $totalPrincipalPaid = Emi::where('loan_account_id', $loanAccount->id)->sum('principal_amount');
                $outstandingPrincipal = max(0, (float)$loanAccount->loan_amount - $totalPrincipalPaid);
                $nextInterest = round($outstandingPrincipal * ($loanAccount->interest_rate / 100));
                
                // Calculate next due date based on frequency from the LAST emi's date
                $lastDate = \Carbon\Carbon::parse($lastEmi->due_date ?? $emi->due_date);
                $application = $loanAccount->loanApplication;
                $termUnit = strtolower((string)($application->term_unit ?? 'monthly'));
                
                if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
                    $nextDueDate = $lastDate->addWeek();
                } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
                    $nextDueDate = $lastDate->addDay();
                } else {
                    $nextDueDate = $lastDate->addMonth();
                }

                $nextEmi = Emi::create([
                    'loan_account_id'    => $loanAccount->id,
                    'instalment_number'  => $nextInstalmentNumber,
                    'principal_amount'   => 0,
                    'interest_amount'    => $nextInterest,
                    'total_amount'       => $nextInterest,
                    'due_date'           => $nextDueDate->format('Y-m-d'),
                    'previous_balance'   => 0,
                    'total_due'          => $nextInterest,
                    'pending_amount'     => $nextInterest,
                    'paid_amount'        => 0,
                    'status'             => 'pending',
                ]);

                // Auto-assign to agent if client has one
                $client = $loanAccount->client;
                if ($client && $client->assigned_to) {
                    \App\Models\EmiAgentAssignment::updateOrCreate(
                        ['emi_id' => $nextEmi->id],
                        [
                            'agent_id' => $client->assigned_to,
                            'status' => 'assigned',
                            'assigned_at' => now(),
                            'remarks' => 'Auto-assigned next cycle for Kandhuvatti loan buffer (12-cycle plan)'
                        ]
                    );
                }
            }

            $this->syncEmiBalances($loanAccount->id);
            $this->syncLoanTotals($loanAccount->id);
            
            // Fire PaymentReceivedEvent to trigger admin notification and play sound
            event(new \App\Events\PaymentReceivedEvent($emi, $totalPayment));

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

        if (!in_array($emi->status, ['paid', 'partial', 'overdue'])) {
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
                  ->orWhereIn('status', ['paid', 'partial']);
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

            // 3. Mark EmiCollections as undone or delete them
            $emi->collections()->where('status', 'verified')->delete();

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

            // Restore assignment status to 'assigned' or assign to client's current agent
            $assignment = \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)->first();
            if ($assignment) {
                $assignment->update([
                    'status' => 'assigned',
                    'resolved_at' => null,
                    'remarks' => trim(($assignment->remarks ?? '') . "\n[Restored to assigned because payment was undone]")
                ]);
            } else {
                $client = $loanAccount->client ?? ($loanAccount->loanApplication ? $loanAccount->loanApplication->client : null);
                if ($client && $client->assigned_to) {
                    \App\Models\EmiAgentAssignment::create([
                        'emi_id' => $emi->id,
                        'agent_id' => $client->assigned_to,
                        'status' => 'assigned',
                        'assigned_at' => now(),
                        'remarks' => 'Auto-assigned because payment was undone'
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
            $this->syncEmiBalances($loanAccount->id);
            $this->syncLoanTotals($loanAccount->id);
            $this->ensureKandhuvattiBuffer($loanAccount->id);

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
                  ->orWhereIn('status', ['paid', 'partial']);
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

            // 4. Delete the collection record
            $collection->delete();

            // 5. Delete AgentActivity related to this specific collection
            \App\Models\AgentActivity::where('emi_id', $emi->id)
                ->where('type', 'payment')
                ->where('description', '₹' . number_format($collection->amount, 2))
                ->delete();

            // 6. Save Emi changes
            $emi->save();

            // Revert assignment status to 'assigned' if EMI is no longer fully paid
            if ($emi->status !== 'paid') {
                $assignment = \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)->first();
                if ($assignment) {
                    $assignment->update([
                        'status' => 'assigned',
                        'resolved_at' => null,
                        'remarks' => trim(($assignment->remarks ?? '') . "\n[Restored to assigned because payment collection was deleted]")
                    ]);
                } else {
                    $client = $loanAccount->client ?? ($loanAccount->loanApplication ? $loanAccount->loanApplication->client : null);
                    if ($client && $client->assigned_to) {
                        \App\Models\EmiAgentAssignment::create([
                            'emi_id' => $emi->id,
                            'agent_id' => $client->assigned_to,
                            'status' => 'assigned',
                            'assigned_at' => now(),
                            'remarks' => 'Auto-assigned because payment collection was deleted'
                        ]);
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
            $this->syncEmiBalances($loanAccount->id);
            $this->syncLoanTotals($loanAccount->id);
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
     * Ensure a Kandhuvatti (interest-only) loan maintains a 12-cycle buffer if outstanding principal is positive.
     */
    public function ensureKandhuvattiBuffer(int $loanAccountId): void
    {
        $loanAccount = LoanAccount::with(['emis', 'loanApplication'])->findOrFail($loanAccountId);
        if ($loanAccount->loan_mode !== 'interest_only') {
            return;
        }

        $totalPrincipalPaid = $loanAccount->emis()->sum('principal_amount');
        $outstandingPrincipal = max(0, (float)$loanAccount->loan_amount - $totalPrincipalPaid);

        if ($outstandingPrincipal <= 0.01) {
            return;
        }

        $emisCount = $loanAccount->emis()->count();
        if ($emisCount < 12) {
            $needed = 12 - $emisCount;
            for ($i = 0; $i < $needed; $i++) {
                $lastEmi = Emi::where('loan_account_id', $loanAccount->id)
                    ->orderBy('instalment_number', 'desc')
                    ->first();
                $nextInstalmentNumber = ($lastEmi->instalment_number ?? 0) + 1;
                $nextInterest = round($outstandingPrincipal * ($loanAccount->interest_rate / 100));
                
                $lastDate = $lastEmi ? Carbon::parse($lastEmi->due_date) : Carbon::parse($loanAccount->disbursed_at);
                $application = $loanAccount->loanApplication;
                $termUnit = strtolower((string)($application->term_unit ?? 'monthly'));
                
                if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
                    $nextDueDate = $lastDate->addWeek();
                } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
                    $nextDueDate = $lastDate->addDay();
                } else {
                    $nextDueDate = $lastDate->addMonth();
                }

                $nextEmi = Emi::create([
                    'loan_account_id'    => $loanAccount->id,
                    'instalment_number'  => $nextInstalmentNumber,
                    'principal_amount'   => 0,
                    'interest_amount'    => $nextInterest,
                    'total_amount'       => $nextInterest,
                    'due_date'           => $nextDueDate->format('Y-m-d'),
                    'previous_balance'   => 0,
                    'total_due'          => $nextInterest,
                    'pending_amount'     => $nextInterest,
                    'paid_amount'        => 0,
                    'status'             => 'pending',
                ]);

                // Auto-assign to agent if client has one
                $client = $loanAccount->client;
                if ($client && $client->assigned_to) {
                    \App\Models\EmiAgentAssignment::updateOrCreate(
                        ['emi_id' => $nextEmi->id],
                        [
                            'agent_id' => $client->assigned_to,
                            'status' => 'assigned',
                            'assigned_at' => now(),
                            'remarks' => 'Auto-assigned next cycle for Kandhuvatti loan buffer (12-cycle plan)'
                        ]
                    );
                }
            }
        }
    }

    /**
     * Dynamically apply fixed penalty if overdue past the grace period.
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

        // Resolve penalty settings: use global if set, otherwise fallback to loan account settings
        $penaltyAmount = ($penaltyConfig->charge_value > 0) 
            ? $penaltyConfig->charge_value 
            : ($loanAccount->penalty ?? 0);

        $graceDays = ($penaltyConfig->eligibility_days !== null)
            ? $penaltyConfig->eligibility_days
            : ($loanAccount->grace_period_days ?? 0);

        if ($penaltyAmount <= 0) {
            return;
        }

        $dueDate = \Carbon\Carbon::parse($emi->due_date);
        $penaltyStartDate = $dueDate->copy()->addDays($graceDays);
        $payDate = \Carbon\Carbon::parse($paymentDate)->startOfDay();

        // If payment date is past the penalty start date, apply the penalty!
        if ($payDate->gt($penaltyStartDate)) {
            $emi->penalty_amount = $penaltyAmount;
            $emi->total_due += $penaltyAmount;
            $emi->pending_amount += $penaltyAmount;
            $emi->last_penalty_date = $payDate->toDateString();
            $emi->status = 'overdue';
            $emi->save();

            \Illuminate\Support\Facades\Log::info("Dynamically applied fixed penalty of ₹{$penaltyAmount} to EMI #{$emi->instalment_number} for Loan Account {$loanAccount->account_number} during payment.");
        }
    }
}

