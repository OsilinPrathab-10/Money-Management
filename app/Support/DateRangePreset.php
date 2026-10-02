<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

class DateRangePreset
{
    /**
     * Resolve from/to Y-m-d dates from a request using date_preset or explicit from/to fields.
     *
     * @return array{0:?string,1:?string,2:string} [from, to, preset]
     */
    public static function resolve(
        Request $request,
        string $fromKey = 'from_date',
        string $toKey = 'to_date',
        string $presetKey = 'date_preset'
    ): array {
        $preset = strtolower(trim((string) $request->input($presetKey, '')));
        $preset = match ($preset) {
            'all_time', 'alltime' => 'all',
            'week' => 'this_week',
            'month' => 'this_month',
            'year' => 'this_year',
            default => $preset,
        };
        $from = $request->input($fromKey);
        $to = $request->input($toKey);

        if (in_array($preset, ['all', 'all_time'], true)) {
            return [null, null, 'all'];
        }

        if ($preset === 'custom') {
            return [self::normalizeDate($from), self::normalizeDate($to), 'custom'];
        }

        if ($preset !== '') {
            [$resolvedFrom, $resolvedTo] = self::range($preset);

            return [$resolvedFrom, $resolvedTo, $preset];
        }

        if ($from || $to) {
            return [self::normalizeDate($from), self::normalizeDate($to), 'custom'];
        }

        return [null, null, 'all'];
    }

    /**
     * @return array{0:?string,1:?string,2:string}
     */
    public static function applyToRequest(
        Request $request,
        string $fromKey = 'from_date',
        string $toKey = 'to_date',
        string $presetKey = 'date_preset'
    ): array {
        [$from, $to, $preset] = self::resolve($request, $fromKey, $toKey, $presetKey);
        $request->merge([
            $fromKey => $from,
            $toKey => $to,
            $presetKey => $preset,
        ]);

        return [$from, $to, $preset];
    }

    /**
     * @return array{0:?string,1:?string}
     */
    public static function range(string $preset): array
    {
        $today = Carbon::today();

        return match ($preset) {
            'today', 'daily' => [$today->toDateString(), $today->toDateString()],
            'yesterday' => [
                $today->copy()->subDay()->toDateString(),
                $today->copy()->subDay()->toDateString(),
            ],
            'this_week', 'week' => [
                $today->copy()->startOfWeek()->toDateString(),
                $today->copy()->endOfWeek()->toDateString(),
            ],
            'this_month', 'month' => [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ],
            'this_year', 'year' => [
                $today->copy()->startOfYear()->toDateString(),
                $today->copy()->endOfYear()->toDateString(),
            ],
            default => [null, null],
        };
    }

    protected static function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
