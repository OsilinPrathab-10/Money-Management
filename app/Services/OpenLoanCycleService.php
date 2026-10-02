<?php

namespace App\Services;

use App\Models\Emi;
use App\Models\EmiAgentAssignment;
use App\Models\LoanAccount;
use App\Support\CalendarWeek;
use Carbon\Carbon;

/**
 * Interest cycles for open loans (loan_mode = interest_only, a.k.a. Kandhuvatti).
 *
 * Open loans have no fixed tenure, so cycles are not projected into the future.
 * A cycle row exists only once its due date has arrived: a daily loan gains one
 * cycle per day, a weekly loan one per week, a monthly loan one per month. A
 * loan whose start date is in the past is backfilled from that start date up to
 * today so nothing is missed.
 */
class OpenLoanCycleService
{
    /** Safety valve so a very old daily loan cannot spin forever. */
    private const MAX_CYCLES_PER_RUN = 5000;

    public function isOpenLoan(LoanAccount $loanAccount): bool
    {
        return ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
    }

    /**
     * Create every interest cycle that has become due up to $asOf (default today).
     * Never creates a cycle dated after $asOf.
     *
     * @return int number of cycles created
     */
    public function syncDueCycles(LoanAccount $loanAccount, ?Carbon $asOf = null): int
    {
        if (! $this->isOpenLoan($loanAccount)) {
            return 0;
        }

        // Closed, foreclosed or written-off loans stop accruing cycles.
        if (strtolower((string) $loanAccount->status) !== 'active' || $loanAccount->closed_at) {
            return 0;
        }

        $today = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $frequency = $this->frequencyFor($loanAccount);

        $lastCycle = $loanAccount->emis()->orderByDesc('instalment_number')->first();

        if ($lastCycle) {
            $nextNumber = (int) $lastCycle->instalment_number + 1;
            $nextDue = $this->advance(Carbon::parse($lastCycle->due_date)->startOfDay(), $frequency);
        } else {
            $firstDue = $this->firstDueDateFor($loanAccount);
            if (! $firstDue) {
                return 0;
            }
            $nextNumber = 1;
            $nextDue = $firstDue->copy()->startOfDay();
        }

        $created = 0;
        while ($nextDue->lte($today) && $created < self::MAX_CYCLES_PER_RUN) {
            // Stop the moment the principal is cleared - no further interest is due.
            if ($this->outstandingPrincipal($loanAccount) <= 0.01) {
                break;
            }

            $this->createCycle($loanAccount, $nextNumber, $nextDue);

            $created++;
            $nextNumber++;
            $nextDue = $this->advance($nextDue, $frequency);
        }

        return $created;
    }

    /**
     * Generate a specific count of upcoming interest cycles for an open loan on demand.
     *
     * @param LoanAccount $loanAccount
     * @param int $count Number of cycles to generate (e.g. 10 days, 10 weeks, 5 months)
     * @return int Number of cycles created
     */
    public function generateManualCycles(LoanAccount $loanAccount, int $count): int
    {
        if (! $this->isOpenLoan($loanAccount)) {
            return 0;
        }

        // Closed, foreclosed or written-off loans stop accruing cycles.
        if (strtolower((string) $loanAccount->status) !== 'active' || $loanAccount->closed_at) {
            return 0;
        }

        if ($this->outstandingPrincipal($loanAccount) <= 0.01) {
            return 0;
        }

        $count = max(1, min((int) $count, 365));
        $frequency = $this->frequencyFor($loanAccount);

        $lastCycle = $loanAccount->emis()->orderByDesc('instalment_number')->first();

        if ($lastCycle && ! empty($lastCycle->due_date)) {
            $nextNumber = (int) $lastCycle->instalment_number + 1;
            $nextDue = $this->advance(Carbon::parse($lastCycle->due_date)->startOfDay(), $frequency);
        } else {
            $firstDue = $this->firstDueDateFor($loanAccount);
            if (! $firstDue) {
                $firstDue = Carbon::now()->startOfDay();
            }
            $nextNumber = 1;
            $nextDue = $firstDue->copy()->startOfDay();
        }

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($this->outstandingPrincipal($loanAccount) <= 0.01) {
                break;
            }

            $this->createCycle($loanAccount, $nextNumber, $nextDue);

            $created++;
            $nextNumber++;
            $nextDue = $this->advance($nextDue, $frequency);
        }

        return $created;
    }

    /**
     * Remove cycles dated after $asOf that nobody has paid against. Used by the
     * cleanup command for loans disbursed before cycles were capped at today.
     *
     * @return int number of cycles deleted
     */
    public function pruneFutureCycles(LoanAccount $loanAccount, ?Carbon $asOf = null): int
    {
        if (! $this->isOpenLoan($loanAccount)) {
            return 0;
        }

        $today = ($asOf ?? Carbon::now())->copy()->startOfDay();

        $futureCycles = $loanAccount->emis()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>', $today->toDateString())
            ->orderByDesc('instalment_number')
            ->get();

        $deleted = 0;
        foreach ($futureCycles as $cycle) {
            if (! $this->isUntouched($cycle)) {
                continue;
            }

            EmiAgentAssignment::where('emi_id', $cycle->id)->delete();
            $cycle->delete();
            $deleted++;
        }

        return $deleted;
    }

    /**
     * A cycle is safe to drop only when no money and no collection is attached.
     */
    protected function isUntouched(Emi $cycle): bool
    {
        if (in_array(strtolower((string) $cycle->status), ['paid', 'partial'], true)) {
            return false;
        }

        if ((float) ($cycle->paid_amount ?? 0) > 0.01 || (float) ($cycle->partial_paid_amount ?? 0) > 0.01) {
            return false;
        }

        if ((float) ($cycle->penalty_amount ?? 0) > 0.01) {
            return false;
        }

        return ! $cycle->collections()->exists();
    }

    /**
     * daily | weekly | monthly, taken from the application's term unit.
     */
    public function frequencyFor(LoanAccount $loanAccount): string
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

    /**
     * Due date of cycle #1: the chosen EMI start date, else one period after
     * disbursement. Mirrors how disbursement anchored the old 31-cycle plan.
     */
    public function firstDueDateFor(LoanAccount $loanAccount): ?Carbon
    {
        $loanAccount->loadMissing('loanApplication');
        $application = $loanAccount->loanApplication;

        if ($application && $application->emi_start_year && $application->emi_start_month && $application->emi_start_day) {
            return Carbon::create(
                (int) $application->emi_start_year,
                (int) $application->emi_start_month,
                (int) $application->emi_start_day
            )->startOfDay();
        }

        if ($application && ! empty($application->emi_start_date)) {
            return Carbon::parse($application->emi_start_date)->startOfDay();
        }

        $disbursed = $loanAccount->disbursed_at ?? $loanAccount->created_at;
        if (! $disbursed) {
            return null;
        }

        return $this->advance(Carbon::parse($disbursed)->startOfDay(), $this->frequencyFor($loanAccount));
    }

    /**
     * Step one collection period forward using calendar days/weeks so year-end
     * never skips a year.
     */
    public function advance(Carbon $date, string $frequency): Carbon
    {
        $previous = $date->copy()->startOfDay();

        if ($frequency === 'daily') {
            return $previous->copy()->addDay();
        }

        if ($frequency === 'weekly') {
            return CalendarWeek::fixSkippedYear($previous, CalendarWeek::addWeeks($previous, 1));
        }

        return CalendarWeek::fixSkippedYear($previous, $previous->copy()->addMonth());
    }

    /**
     * Principal still owed. Open-loan cycles carry interest only, so principal
     * moves only when a principal payment is recorded against a cycle.
     */
    public function outstandingPrincipal(LoanAccount $loanAccount): float
    {
        $principalPaid = (float) $loanAccount->emis()->sum('principal_amount');

        return max(0, (float) $loanAccount->loan_amount - $principalPaid);
    }

    protected function createCycle(LoanAccount $loanAccount, int $instalmentNumber, Carbon $dueDate): Emi
    {
        $interest = round($this->outstandingPrincipal($loanAccount) * ((float) $loanAccount->interest_rate / 100));

        $cycle = Emi::create([
            'loan_account_id' => $loanAccount->id,
            'instalment_number' => $instalmentNumber,
            'principal_amount' => 0,
            'interest_amount' => $interest,
            'total_amount' => $interest,
            'due_date' => $dueDate->format('Y-m-d'),
            'previous_balance' => 0,
            'total_due' => $interest,
            'pending_amount' => $interest,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $loanAccount->loadMissing('client');
        if ($loanAccount->client?->assigned_to) {
            EmiAgentAssignment::updateOrCreate(
                ['emi_id' => $cycle->id],
                [
                    'agent_id' => $loanAccount->client->assigned_to,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'remarks' => 'Auto-assigned open loan interest cycle',
                ]
            );
        }

        // Keep the in-memory relation fresh for callers that loaded it.
        if ($loanAccount->relationLoaded('emis')) {
            $loanAccount->unsetRelation('emis');
        }

        return $cycle;
    }
}
