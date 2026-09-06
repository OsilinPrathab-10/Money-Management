<?php

namespace App\Services;

use Carbon\Carbon;

class EmiCalculator
{
    /**
     * Generate EMI schedule with start and end dates
     *
     * @param float $principal Loan amount
     * @param float $annualRate Annual interest rate (%)
     * @param int $term Number of units (days/weeks/months)
     * @param string|null $startDate (optional) Start date, defaults to today
     * @param int|null $emiDay (optional) Day of month/week for EMI, defaults to start date's day
     * @param string $frequency (optional) 'daily', 'weekly', 'monthly', defaults to 'monthly'
     * @return array
     */
    public function generateSchedule(
        float $principal,
        float $annualRate,
        int $term,
        string $startDate = null,
        int $emiDay = null,
        string $frequency = 'monthly',
        string $interestType = 'flat'
    ): array {
        $schedule = [];
        $intervals = $term;
        $start = $startDate ? Carbon::parse($startDate) : Carbon::today();
        $dueDate = clone $start;

        if ($interestType === 'reducing' || $interestType === 'declining_balance') {
            $ratePerPeriod = 0;
            if ($frequency === 'daily') {
                $ratePerPeriod = ($annualRate / 100) / 365;
            } elseif ($frequency === 'weekly') {
                $ratePerPeriod = ($annualRate / 100) / 52;
            } else {
                $ratePerPeriod = ($annualRate / 100) / 12;
            }

            if ($ratePerPeriod > 0) {
                // Standard EMI formula: P * r * (1+r)^n / ((1+r)^n - 1)
                $emi = round($principal * ($ratePerPeriod * pow(1 + $ratePerPeriod, $intervals)) / (pow(1 + $ratePerPeriod, $intervals) - 1));
            } else {
                $emi = round($principal / $intervals);
            }

            $baseEmi = $emi;
            $currentBalance = $principal;

            for ($i = 1; $i <= $intervals; $i++) {
                // Calculate next due date based on frequency
                if ($frequency === 'daily') {
                    $dueDate->addDay();
                } elseif ($frequency === 'weekly') {
                    $dueDate->addWeek();
                    if ($emiDay !== null && $emiDay >= 1 && $emiDay <= 7) {
                        // Set to specific day of week (1=Mon, 7=Sun)
                        $dueDate->setISODate($dueDate->year, $dueDate->weekOfYear, $emiDay);
                    }
                } else {
                    $dueDate->addMonth();
                    if ($emiDay !== null) {
                        $dueDate->day = min($emiDay, $dueDate->daysInMonth);
                    }
                }

                $currentInterest = round($currentBalance * $ratePerPeriod);
                $currentPrincipal = $emi - $currentInterest;

                // Adjustment for last EMI to ensure totals match exactly (all rounded difference is adjusted here)
                if ($i == $intervals || $currentPrincipal > $currentBalance) {
                    $currentPrincipal = $currentBalance;
                    $currentInterest = round($currentBalance * $ratePerPeriod);
                    $emi = $currentPrincipal + $currentInterest;
                }

                $openingBalance = $currentBalance;
                $currentBalance = round($currentBalance - $currentPrincipal);
                $closingBalance = $currentBalance;

                $schedule[] = [
                    'month' => $i,
                    'year' => $dueDate->year,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'emi_amount' => (float)$emi,
                    'principal' => (float)$currentPrincipal,
                    'interest' => (float)$currentInterest,
                    'opening_balance' => (float)$openingBalance,
                    'closing_balance' => (float)$closingBalance,
                    'remaining_balance' => (float)$currentBalance,
                ];
            }
        } else {
            // Flat Interest Calculation Logic - Rounded UP using ceil to ensure the last EMI/interest is reduced
            $totalInterest = ceil($principal * ($annualRate / 100));
            $totalPayable = $principal + $totalInterest;
            $emi = ceil($totalPayable / $intervals);
            
            $baseEmi = $emi;
            $interestPerEmi = ceil($totalInterest / $intervals);
            $principalPerEmi = max(0, $emi - $interestPerEmi);
            $currentBalance = $principal;
            $remainingInterest = $totalInterest;

            for ($i = 1; $i <= $intervals; $i++) {
                // Calculate next due date based on frequency
                if ($frequency === 'daily') {
                    $dueDate->addDay();
                } elseif ($frequency === 'weekly') {
                    $dueDate->addWeek();
                    if ($emiDay !== null && $emiDay >= 1 && $emiDay <= 7) {
                        // Set to specific day of week (1=Mon, 7=Sun)
                        $dueDate->setISODate($dueDate->year, $dueDate->weekOfYear, $emiDay);
                    }
                } else {
                    $dueDate->addMonth();
                    if ($emiDay !== null) {
                        $dueDate->day = min($emiDay, $dueDate->daysInMonth);
                    }
                }

                $currentInterest = $interestPerEmi;
                $currentPrincipal = $principalPerEmi;

                // Adjustment for last EMI to ensure totals match exactly (all rounded difference is adjusted here)
                if ($i == $intervals) {
                    $currentPrincipal = $currentBalance;
                    $currentInterest = $remainingInterest;
                    $emi = $currentPrincipal + $currentInterest;
                }

                $openingBalance = $currentBalance;
                $currentBalance = round($currentBalance - $currentPrincipal);
                $closingBalance = $currentBalance;
                $remainingInterest = round($remainingInterest - $currentInterest);

                $schedule[] = [
                    'month' => $i,
                    'year' => $dueDate->year,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'emi_amount' => (float)$emi,
                    'principal' => (float)$currentPrincipal,
                    'interest' => (float)$currentInterest,
                    'opening_balance' => (float)$openingBalance,
                    'closing_balance' => (float)$closingBalance,
                    'remaining_balance' => (float)$currentBalance,
                ];
            }
        }

        // Group schedule by year
        $grouped = [];
        foreach ($schedule as $row) {
            $grouped[$row['year']][] = $row;
        }

        return [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $dueDate->format('Y-m-d'),
            'emi' => (float)$baseEmi,
            'total_interest' => round(array_sum(array_column($schedule, 'interest'))),
            'total_payment' => round(array_sum(array_column($schedule, 'emi_amount'))),
            'schedule' => $schedule,
            'schedule_by_year' => $grouped,
        ];
    }
}
