<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class VerificationApiHit extends Model
{
    protected $fillable = [
        'service',
        'endpoint',
        'success',
        'http_status',
        'request_payload',
        'response_message',
        'user_id',
        'ip_address',
    ];

    protected $casts = [
        'success' => 'boolean',
        'request_payload' => 'array',
    ];

    public const DEFAULT_PRICES = [
        'cibil' => 118,
        'aadhaar' => 5,
        'pan' => 4,
        'bank' => 5,
    ];

    public const PRICE_SETTING_KEYS = [
        'cibil' => 'apihub_price_cibil',
        'aadhaar' => 'apihub_price_aadhaar',
        'pan' => 'apihub_price_pan',
        'bank' => 'apihub_price_bank',
    ];

    /**
     * Resolved tariff: AdminSetting override → config/env → defaults.
     *
     * @return array{cibil: float, aadhaar: float, pan: float, bank: float}
     */
    public static function resolvePricing(): array
    {
        $config = config('services.apihub.pricing', self::DEFAULT_PRICES);
        $pricing = [];

        foreach (self::PRICE_SETTING_KEYS as $service => $settingKey) {
            $stored = null;
            try {
                $stored = AdminSetting::get($settingKey);
            } catch (Throwable $e) {
                $stored = null;
            }

            if ($stored !== null && $stored !== '') {
                $pricing[$service] = round((float) $stored, 2);
            } else {
                $pricing[$service] = round((float) ($config[$service] ?? self::DEFAULT_PRICES[$service] ?? 0), 2);
            }
        }

        return $pricing;
    }

    public static function getPriceForService(?string $service): float
    {
        $service = strtolower(trim((string) $service));
        $prices = self::resolvePricing();

        return (float) ($prices[$service] ?? 0);
    }

    public function getPriceAttribute(): float
    {
        return self::getPriceForService($this->service);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeService($query, string $service)
    {
        return $query->where('service', $service);
    }
}
