<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Calendar-week helpers that never use ISO week-year.
 *
 * 2026 has 53 ISO weeks. Combining calendar year 2027 with ISO week 53
 * (setISODate(2027, 53)) overflows to 2028-01-03 and skips 2027.
 */
class CalendarWeek
{
    /**
     * Add whole weeks as 7 calendar days from the given date.
     */
    public static function addWeeks(Carbon $date, int $weeks): Carbon
    {
        return $date->copy()->startOfDay()->addDays(7 * $weeks);
    }

    /**
     * Move forward to ISO weekday 1=Monday … 7=Sunday (keep the date if it already matches).
     * Avoids Carbon::next($day) which uses 0=Sunday…6=Saturday and crashes on 7.
     */
    public static function alignToIsoWeekday(Carbon $date, int $isoWeekday): Carbon
    {
        $isoWeekday = max(1, min(7, $isoWeekday));
        $aligned = $date->copy()->startOfDay();
        $guard = 0;
        while ((int) $aligned->dayOfWeekIso !== $isoWeekday && $guard < 7) {
            $aligned->addDay();
            $guard++;
        }

        return $aligned;
    }

    /**
     * After December, the next week/month must be January of the following year                                  
     * (2026 → 2027), never two years ahead (2026 → 2028).
     */
    public static function fixSkippedYear(Carbon $previous, Carbon $current): Carbon
    {
        $fixed = $current->copy()->startOfDay();
        $prev = $previous->copy()->startOfDay();

        while ((int) $fixed->year >= (int) $prev->year + 2) {
            $fixed->subYear();
        }

        return $fixed;
    }

    /**
     * Build a date from calendar Y-M-D only (not ISO week-year).
     */
    public static function of(Carbon $date): Carbon
    {
        return Carbon::create(
            (int) $date->year,
            (int) $date->month,
            (int) $date->day,
            0,
            0,
            0,
            $date->timezone
        );
    }
}
