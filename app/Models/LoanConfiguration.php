<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'eligibility_months',
        'eligibility_weeks',
        'eligibility_days',
        'charges_percentage',
        'charges_percentage_weekly',
        'charges_percentage_daily',
        'charge_type',
        'charge_value',
        'charge_value_weekly',
        'charge_value_daily',
        'extra_charge',
        'minimum_partial_percentage',
        'partial_payment_timing',
        'penalty_calculation_method',
        'is_active',
    ];

    protected $casts = [
        'eligibility_months' => 'integer',
        'eligibility_weeks' => 'integer',
        'eligibility_days' => 'integer',
        'charges_percentage' => 'decimal:2',
        'charges_percentage_weekly' => 'decimal:2',
        'charges_percentage_daily' => 'decimal:2',
        'charge_type' => 'string',
        'charge_value' => 'decimal:2',
        'charge_value_weekly' => 'decimal:2',
        'charge_value_daily' => 'decimal:2',
        'extra_charge' => 'decimal:2',
        'minimum_partial_percentage' => 'decimal:2',
        'partial_payment_timing' => 'string',
        'penalty_calculation_method' => 'string',
        'is_active' => 'boolean',
    ];

    /**
     * Get foreclosure configuration
     */
    public static function getForeclosureConfig()
    {
        return self::where('type', 'foreclosure')->first();
    }

    /**
     * Get prepayment configuration
     */
    public static function getPrepaymentConfig()
    {
        return self::where('type', 'prepayment')->first();
    }

    /**
     * Get partial payment configuration
     */
    public static function getPartialPaymentConfig()
    {
        return self::where('type', 'partial_payment')->first();
    }

    /**
     * Get penalty configuration
     */
    public static function getPenaltyConfig()
    {
        return self::where('type', 'penalty')->first();
    }

    /**
     * Update or create configuration
     */
    public static function updateConfig($type, array $data)
    {
        return self::updateOrCreate(
            ['type' => $type],
            $data
        );
    }
}
