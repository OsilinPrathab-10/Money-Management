<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerSettlementLog
{
    public static function write(string $event, array $context = []): void
    {
        $parts = [];
        foreach ($context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                $parts[] = $key . '=' . json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $parts[] = $key . '=' . $value;
            }
        }

        $line = '[' . now()->toDateTimeString() . '] ' . $event;
        if ($parts) {
            $line .= ' | ' . implode(' | ', $parts);
        }

        try {
            file_put_contents(storage_path('logs/customer-settlement.log'), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // fall through to laravel log
        }

        try {
            Log::info('CustomerSettlement ' . $event, $context);
        } catch (Throwable) {
        }
    }
}
