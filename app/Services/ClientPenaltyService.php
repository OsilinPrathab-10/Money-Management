<?php

namespace App\Services;

use App\Models\Emi;
use App\Models\LoanAccount;
use Carbon\Carbon;

class ClientPenaltyService
{
    /**
     * Save loan-account penalty settings and apply to overdue unpaid EMIs.
     *
     * @return array{updated_emis:int, penalty_type:string, penalty_value:float}
     */
    public function applyLoanPenalty(LoanAccount $loanAccount, string $penaltyType, float $penaltyValue, ?int $graceDays = null): array
    {
        $graceDays = $graceDays !== null
            ? max(0, $graceDays)
            : (int) ($loanAccount->grace_period_days ?? 0);

        $storedType = $penaltyType === 'percentage' ? 'percentage' : 'rupees';

        $loanAccount->update([
            'penalty' => $penaltyValue,
            'penalty_type' => $storedType,
            'grace_period_days' => $graceDays,
        ]);

        $today = Carbon::today();
        $updated = 0;

        $emis = Emi::where('loan_account_id', $loanAccount->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '<', $today)
            ->where('pending_amount', '>', 0)
            ->get();

        foreach ($emis as $emi) {
            $penaltyStartDate = Carbon::parse($emi->due_date)->addDays($graceDays);
            if ($today->lte($penaltyStartDate)) {
                continue;
            }

            $newPenalty = $this->calculateLoanEmiPenalty($emi, $loanAccount, $penaltyType, $penaltyValue);
            if ($newPenalty < 0) {
                $newPenalty = 0;
            }

            $oldPenalty = (float) ($emi->penalty_amount ?? 0);
            $diff = round($newPenalty - $oldPenalty, 2);

            if (abs($diff) < 0.01 && (float) $emi->penalty_amount === $newPenalty) {
                continue;
            }

            $emi->penalty_amount = $newPenalty;
            $emi->total_due = round((float) $emi->total_due + $diff, 2);
            $emi->pending_amount = round((float) $emi->pending_amount + $diff, 2);
            $emi->last_penalty_date = $today;
            $emi->status = 'overdue';
            $emi->save();
            $updated++;
        }

        return [
            'updated_emis' => $updated,
            'penalty_type' => $penaltyType === 'percentage' ? 'percentage' : 'fixed',
            'penalty_value' => $penaltyValue,
        ];
    }

    public function calculateLoanEmiPenalty(Emi $emi, LoanAccount $loanAccount, string $penaltyType, float $penaltyValue): float
    {
        if ($penaltyType !== 'percentage') {
            return round(max(0, $penaltyValue), 2);
        }

        $base = (float) ($emi->principal_amount ?? 0);
        if ($base <= 0) {
            $base = (float) $loanAccount->remaining_principal_balance;
        }

        return round(($base * $penaltyValue) / 100, 2);
    }
}
