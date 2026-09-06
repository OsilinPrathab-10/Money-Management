<?php

namespace App\Services;

use App\Models\Emi;
use App\Models\LoanAccount;
use App\Models\LoanConfiguration;
use Carbon\Carbon;

class PartialPaymentConfigService
{
    public const DEFAULT_MINIMUM_PARTIAL_PERCENTAGE = 10.0;

    protected ?LoanConfiguration $config = null;

    public function getConfig(): ?LoanConfiguration
    {
        if ($this->config === null) {
            $this->config = LoanConfiguration::getPartialPaymentConfig();
        }

        return $this->config;
    }

    public function isActive(): bool
    {
        $config = $this->getConfig();

        return $config && $config->is_active;
    }

    /**
     * Saved minimum % from loan configuration (defaults to 10 when not set).
     */
    public function getConfiguredMinimumPercentage(): float
    {
        $config = $this->getConfig();

        if (!$config || $config->minimum_partial_percentage === null) {
            return self::DEFAULT_MINIMUM_PARTIAL_PERCENTAGE;
        }

        return (float) $config->minimum_partial_percentage;
    }

    /**
     * Minimum % applied to validations (0 when partial payments are disabled).
     */
    public function getMinimumPercentage(): float
    {
        if (!$this->isActive()) {
            return 0.0;
        }

        return $this->getConfiguredMinimumPercentage();
    }

    public function getTiming(): string
    {
        $config = $this->getConfig();

        return $config?->partial_payment_timing ?? 'anytime';
    }

    public function getPenaltyCalculationMethod(): string
    {
        $config = $this->getConfig();

        return $config?->penalty_calculation_method ?? 'emi_amount';
    }

    public function getInProgressCollectionSum(Emi $emi): float
    {
        return (float) ($emi->collections()
            ->where('status', 'in_progress')
            ->sum('amount') ?? 0);
    }

    /**
     * Full amount still owed on this EMI (after in-progress collections).
     */
    public function getOutstandingDueAmount(Emi $emi, ?LoanAccount $loanAccount = null): float
    {
        $loanAccount = $loanAccount ?? $emi->loanAccount;
        $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';

        $paidAmount = (float) ($emi->paid_amount ?? 0);
        $inProgressSum = $this->getInProgressCollectionSum($emi);
        $effectivePaid = $paidAmount + $inProgressSum;
        $previousBalance = (float) ($emi->previous_balance ?? 0);
        $penaltyAmount = (float) ($emi->penalty_amount ?? 0);

        if ($isKandhuvatti) {
            $interestAmount = (float) ($emi->interest_amount ?? $emi->total_amount ?? 0);
            $principalPaidOnEmi = (float) ($emi->principal_amount ?? 0);
            $interestPaid = max(0, $paidAmount - $principalPaidOnEmi);

            return max(0.0, $previousBalance + $interestAmount + $penaltyAmount - $interestPaid - $inProgressSum);
        }

        if (!is_null($emi->pending_amount)) {
            return max(0.0, round((float) $emi->pending_amount - $inProgressSum, 2));
        }

        $totalAmount = (float) ($emi->total_amount ?? 0);

        return max(0.0, round($previousBalance + $totalAmount + $penaltyAmount - $effectivePaid, 2));
    }

    /**
     * Base amount for minimum partial % when method is "original EMI amount".
     */
    public function getOriginalEmiBaseAmount(Emi $emi, ?LoanAccount $loanAccount = null): float
    {
        $loanAccount = $loanAccount ?? $emi->loanAccount;
        $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
        $previousBalance = (float) ($emi->previous_balance ?? 0);

        if ($isKandhuvatti) {
            return max(0.0, (float) ($emi->interest_amount ?? $emi->total_amount ?? 0) + $previousBalance);
        }

        return max(0.0, (float) ($emi->total_amount ?? 0) + $previousBalance);
    }

    public function getMinimumPartialBaseAmount(Emi $emi, ?LoanAccount $loanAccount = null): float
    {
        $loanAccount = $loanAccount ?? $emi->loanAccount;

        if ($this->getPenaltyCalculationMethod() === 'emi_plus_partial_remaining') {
            return $this->getOutstandingDueAmount($emi, $loanAccount);
        }

        return $this->getOriginalEmiBaseAmount($emi, $loanAccount);
    }

    public function calculateMinimumPartialAmount(Emi $emi, ?LoanAccount $loanAccount = null): int
    {
        if (!$this->isActive()) {
            return 0;
        }

        $percentage = $this->getMinimumPercentage();
        $base = $this->getMinimumPartialBaseAmount($emi, $loanAccount);
        $minimum = (int) ceil(($base * $percentage) / 100.0);
        $outstanding = (int) ceil($this->getOutstandingDueAmount($emi, $loanAccount));

        if ($outstanding <= 0) {
            return 0;
        }

        return min($minimum, $outstanding);
    }

    public function validateTiming(Emi $emi): ?string
    {
        if (!$this->isActive()) {
            return 'Partial payments are disabled in loan configuration.';
        }

        if (!$emi->due_date) {
            return null;
        }

        $dueDate = Carbon::parse($emi->due_date)->startOfDay();
        $today = now()->startOfDay();

        return match ($this->getTiming()) {
            'before_due' => $today->gt($dueDate)
                ? 'Partial payments are only allowed before the due date (' . $dueDate->format('d-m-Y') . ').'
                : null,
            'after_due' => $today->lte($dueDate)
                ? 'Partial payments are only allowed after the due date (' . $dueDate->format('d-m-Y') . ').'
                : null,
            default => null,
        };
    }

    public function validatePartialAmount(Emi $emi, float $amount, ?LoanAccount $loanAccount = null): ?string
    {
        if (!$this->isActive()) {
            return 'Partial payments are disabled in loan configuration.';
        }

        if ($timingError = $this->validateTiming($emi)) {
            return $timingError;
        }

        if ($amount <= 0) {
            return 'Partial payment amount must be greater than zero.';
        }

        if (floor($amount) != $amount) {
            return 'Partial payment amount must be a whole number (no decimal values).';
        }

        $outstanding = $this->getOutstandingDueAmount($emi, $loanAccount);

        if ($amount > ($outstanding + 0.01)) {
            return 'Collection amount cannot exceed the pending EMI amount (₹' . number_format($outstanding, 0) . ').';
        }

        $minimum = $this->calculateMinimumPartialAmount($emi, $loanAccount);

        if ($minimum > 0 && $amount < $minimum) {
            $pct = $this->getMinimumPercentage();
            $baseLabel = $this->getPenaltyCalculationMethod() === 'emi_plus_partial_remaining'
                ? 'outstanding balance'
                : 'EMI amount';

            return "Partial payment must be at least ₹{$minimum} ({$pct}% of {$baseLabel}).";
        }

        return null;
    }

    /**
     * Global settings for UI before an EMI is selected.
     */
    public function getGlobalSettings(): array
    {
        $config = $this->getConfig();

        return [
            'is_active' => $this->isActive(),
            'minimum_partial_percentage' => $this->getConfiguredMinimumPercentage(),
            'partial_payment_timing' => $this->getTiming(),
            'penalty_calculation_method' => $this->getPenaltyCalculationMethod(),
            'has_saved_config' => (bool) $config,
        ];
    }

    public function rulesForEmi(Emi $emi, ?LoanAccount $loanAccount = null): array
    {
        $loanAccount = $loanAccount ?? $emi->loanAccount;
        $outstanding = $this->getOutstandingDueAmount($emi, $loanAccount);
        $minimum = $this->calculateMinimumPartialAmount($emi, $loanAccount);
        $timingError = $this->validateTiming($emi);
        $configuredPct = $this->getConfiguredMinimumPercentage();

        return [
            'is_active' => $this->isActive(),
            'minimum_partial_percentage' => $configuredPct,
            'partial_payment_timing' => $this->getTiming(),
            'penalty_calculation_method' => $this->getPenaltyCalculationMethod(),
            'minimum_partial_amount' => $minimum,
            'maximum_partial_amount' => (int) round($outstanding),
            'outstanding_due' => round($outstanding, 2),
            'original_emi_base' => round($this->getOriginalEmiBaseAmount($emi, $loanAccount), 2),
            'timing_allowed' => $timingError === null,
            'timing_message' => $timingError,
            'allows_partial' => $this->isActive() && $timingError === null && $outstanding > 0,
        ];
    }
}
