<?php

namespace App\Support;

use Hashids\Hashids;

/**
 * Obfuscates integer primary keys for URLs (not encryption; use HTTPS for secrecy).
 */
class HashId
{
    private static ?Hashids $connection = null;

    public static function hashids(): Hashids
    {
        if (self::$connection === null) {
            $key = (string) config('app.key', '');
            $salt = hash('sha256', $key.'loan-esy-cash-route-hashids');
            self::$connection = new Hashids($salt, (int) config('hashids.length', 12));
        }

        return self::$connection;
    }

    public static function encode(int|string $id): string
    {
        return self::hashids()->encode((int) $id);
    }

    public static function decode(string $value): ?int
    {
        $nums = self::hashids()->decode($value);

        return isset($nums[0]) ? (int) $nums[0] : null;
    }
}
