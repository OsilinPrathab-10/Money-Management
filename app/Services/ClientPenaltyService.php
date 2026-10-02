<?php

namespace App\Services;

use App\Models\Emi;
use App\Models\GroupMember;
use App\Models\Installment;
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

    /**
     * Save chit-membership penalty settings and apply to overdue unpaid installments.
     *
     * @return array{updated_installments:int, penalty_type:string, penalty_value:float}
     */
    public function applyChitPenalty(GroupMember $member, string $penaltyType, float $penaltyValue, ?int $graceDays = null): array
    {
        $globalGrace = (int) \App\Models\ChitConfiguration::get('penalty_grace_days', 0);
        $graceDays = $graceDays !== null ? max(0, $graceDays) : $globalGrace;

        $member->update([
            'penalty_enabled' => true,
            'penalty_type' => $penaltyType === 'percentage' ? 'percentage' : 'fixed',
            'penalty_value' => $penaltyValue,
            'penalty_grace_days' => $graceDays,
        ]);

        $today = Carbon::today();
        $updated = 0;

        $installments = Installment::where('member_id', $member->id)
            ->whereNotIn('status', ['paid', 'waived'])
            ->where('due_date', '<', $today)
            ->get();

        foreach ($installments as $inst) {
            $penaltyStartDate = Carbon::parse($inst->due_date)->addDays($graceDays);
            if ($today->lte($penaltyStartDate)) {
                if ($inst->status === 'pending') {
                    $inst->status = 'overdue';
                    if ((float) $inst->penalty_amount !== 0.0) {
                        $inst->penalty_amount = 0;
                    }
                    $inst->save();
                }
                continue;
            }

            $newPenalty = $this->calculateChitInstallmentPenalty($inst, $penaltyType, $penaltyValue);
            $dirty = false;

            if ((float) $inst->penalty_amount !== $newPenalty) {
                $inst->penalty_amount = $newPenalty;
                $dirty = true;
            }
            if ($inst->status !== 'overdue') {
                $inst->status = 'overdue';
                $dirty = true;
            }

            if ($dirty) {
                $inst->save();
                $updated++;
            }
        }

        return [
            'updated_installments' => $updated,
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

    public function calculateChitInstallmentPenalty(Installment $inst, string $penaltyType, float $penaltyValue): float
    {
        if ($penaltyValue <= 0) {
            return 0.0;
        }

        if ($penaltyType === 'percentage') {
            return round(($penaltyValue / 100) * (float) $inst->amount, 2);
        }

        return round($penaltyValue, 2);
    }
}
