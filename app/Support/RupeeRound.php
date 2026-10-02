<?php

namespace App\Support;

use App\Models\Emi;
use App\Models\LoanAccount;
use Illuminate\Support\Collection;

/**
 * Whole-rupee rounding with leftover applied to the last item.
 */
class RupeeRound
{
    public static function one(float $amount): int
    {
        return (int) round($amount);
    }

    /**
     * @param  array<int|string, float|int>  $amounts
     * @return array<int|string, int>
     */
    public static function distribute(array $amounts): array
    {
        if ($amounts === []) {
            return [];
        }

        $rounded = [];
        foreach ($amounts as $key => $amount) {
            $rounded[$key] = self::one((float) $amount);
        }

        $diff = self::one((float) array_sum($amounts)) - array_sum($rounded);
        if ($diff !== 0) {
            $lastKey = array_key_last($rounded);
            $rounded[$lastKey] += $diff;
        }

        return $rounded;
    }

    /**
     * Persist paise on unpaid Open Loan interest cycles onto the last EMI.
     *
     * @param  Collection<int, Emi>|null  $emis
     */
    public static function persistOpenLoanInterest(LoanAccount $account, ?Collection $emis = null): void
    {
        if (! $account->isOpenLoan()) {
            return;
        }

        $unpaid = ($emis ?? $account->emis)
            ->filter(fn (Emi $emi) => in_array($emi->status, ['pending', 'overdue', 'partial'], true))
            ->sortBy('instalment_number')
            ->values();

        if ($unpaid->isEmpty()) {
            return;
        }

        $raw = $unpaid->map(fn (Emi $emi) => (float) ($emi->interest_amount ?? $emi->total_amount ?? 0))->all();
        $hasPaise = collect($raw)->contains(fn ($amount) => abs($amount - round($amount)) > 0.001);
        if (! $hasPaise) {
            return;
        }

        $rounded = self::distribute($raw);

        foreach ($unpaid as $index => $emi) {
            $newInterest = (float) ($rounded[$index] ?? self::one((float) ($emi->interest_amount ?? 0)));
            if (abs((float) ($emi->interest_amount ?? 0) - $newInterest) < 0.005) {
                continue;
            }

            $principalOnEmi = (float) ($emi->principal_amount ?? 0);
            $interestPaid = max(0, (float) ($emi->paid_amount ?? 0) - $principalOnEmi);

            $emi->interest_amount = $newInterest;
            $emi->total_amount = $newInterest;
            $emi->total_due = $newInterest;
            $emi->pending_amount = max(0, $newInterest - $interestPaid);
            $emi->save();
        }
    }
}
