<?php

namespace App\Services\FixedDeposit;

use Carbon\Carbon;

class FixedDepositInterestService
{
    /**
     * Calculate interest and maturity for an FD.
     *
     * @return array{interest_amount: float, maturity_amount: float, maturity_date: Carbon, tenure_months: int, years: float}
     */
    public function calculate(
        float $principal,
        float $ratePercent,
        string $interestType,
        string $frequency,
        int $tenure,
        string $tenureType,
        Carbon|string $startDate
    ): array {
        $start = $startDate instanceof Carbon ? $startDate->copy() : Carbon::parse($startDate);
        $tenureMonths = $tenureType === 'years' ? $tenure * 12 : $tenure;
        $years = $tenureMonths / 12;
        $maturityDate = $start->copy()->addMonthsNoOverflow($tenureMonths);
        $rate = $ratePercent / 100;

        if ($interestType === 'compound_interest') {
            $n = $this->compoundsPerYear($frequency);
            $maturityAmount = $principal * pow(1 + ($rate / $n), $n * $years);
            $interestAmount = $maturityAmount - $principal;
        } else {
            // Simple Interest = P × R × T
            $interestAmount = $principal * $rate * $years;
            $maturityAmount = $principal + $interestAmount;
        }

        return [
            'interest_amount' => round($interestAmount, 2),
            'maturity_amount' => round($maturityAmount, 2),
            'maturity_date' => $maturityDate,
            'tenure_months' => $tenureMonths,
            'years' => round($years, 6),
        ];
    }

    /**
     * Eligible interest for premature withdrawal (pro-rata simple for elapsed time).
     * Uses the FD's contracted rate but only for completed tenure period.
     */
    public function calculatePrematureInterest(
        float $principal,
        float $ratePercent,
        string $interestType,
        string $frequency,
        Carbon|string $startDate,
        Carbon|string $withdrawalDate
    ): array {
        $start = $startDate instanceof Carbon ? $startDate->copy()->startOfDay() : Carbon::parse($startDate)->startOfDay();
        $end = $withdrawalDate instanceof Carbon ? $withdrawalDate->copy()->startOfDay() : Carbon::parse($withdrawalDate)->startOfDay();

        $days = max(0, $start->diffInDays($end));
        $years = $days / 365;
        $rate = $ratePercent / 100;

        if ($interestType === 'compound_interest' && $years > 0) {
            $n = $this->compoundsPerYear($frequency);
            $maturityAmount = $principal * pow(1 + ($rate / $n), $n * $years);
            $interestAmount = $maturityAmount - $principal;
        } else {
            $interestAmount = $principal * $rate * $years;
        }

        return [
            'eligible_interest' => round(max(0, $interestAmount), 2),
            'days' => $days,
            'years' => round($years, 6),
        ];
    }

    public function calculatePenalty(
        ?string $penaltyType,
        ?float $penaltyValue,
        float $principal,
        float $eligibleInterest
    ): float {
        if (!$penaltyType || $penaltyValue === null) {
            return 0.0;
        }

        if ($penaltyType === 'percentage') {
            // Penalty on eligible interest by default; fall back to principal if interest is 0
            $base = $eligibleInterest > 0 ? $eligibleInterest : $principal;

            return round($base * ($penaltyValue / 100), 2);
        }

        return round((float) $penaltyValue, 2);
    }

    private function compoundsPerYear(string $frequency): int
    {
        return match ($frequency) {
            'monthly' => 12,
            'quarterly' => 4,
            'half_yearly' => 2,
            'yearly' => 1,
            default => 1,
        };
    }
}
