<?php

namespace App\Console\Commands;

use App\Models\Emi;
use App\Models\LoanAccount;
use App\Support\CalendarWeek;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixSkippedScheduleYears extends Command
{
    protected $signature = 'schedules:fix-year-skips';

    protected $description = 'Fix weekly due dates that skipped a calendar year (e.g. 2026 then 2028)';

    public function handle(): int
    {
        $emiFixed = $this->repairWeeklyEmis();

        $this->info($emiFixed > 0
            ? "Corrected {$emiFixed} weekly EMI due date(s)."
            : 'No weekly EMI year skips found.');

        return self::SUCCESS;
    }

    protected function repairWeeklyEmis(): int
    {
        $fixed = 0;

        $accounts = LoanAccount::query()
            ->whereNull('deleted_at')
            ->whereHas('loanApplication', function ($q) {
                $q->whereIn('term_unit', ['weekly', 'week', 'weeks']);
            })
            ->with(['emis' => fn ($q) => $q->orderBy('instalment_number'), 'loanApplication'])
            ->get();

        foreach ($accounts as $account) {
            $emis = $account->emis;
            if ($emis->count() < 2) {
                continue;
            }

            $first = $emis->first()->due_date
                ? Carbon::parse($emis->first()->due_date)->startOfDay()
                : null;
            if (! $first) {
                continue;
            }

            $previous = null;
            $needsRebuild = false;
            foreach ($emis as $emi) {
                if (! $emi->due_date) {
                    continue;
                }
                $actual = Carbon::parse($emi->due_date)->startOfDay();
                if ($previous && (int) $actual->year >= (int) $previous->year + 2) {
                    $needsRebuild = true;
                    break;
                }
                $n = max(1, (int) $emi->instalment_number);
                $expected = CalendarWeek::addWeeks($first, $n - 1);
                if ((int) $actual->year !== (int) $expected->year) {
                    $needsRebuild = true;
                    break;
                }
                $previous = $actual;
            }

            if (! $needsRebuild) {
                continue;
            }

            foreach ($emis as $emi) {
                $n = max(1, (int) $emi->instalment_number);
                $expected = CalendarWeek::addWeeks($first, $n - 1);
                $actual = $emi->due_date ? Carbon::parse($emi->due_date)->startOfDay() : null;
                if (! $actual || $actual->toDateString() !== $expected->toDateString()) {
                    $emi->due_date = $expected->toDateString();
                    $emi->saveQuietly();
                    $fixed++;
                }
            }
        }

        return $fixed;
    }
}
