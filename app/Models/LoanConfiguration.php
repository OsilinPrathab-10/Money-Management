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
        'penalty_charge_type',
        'is_active',
        'prefix',
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
        'penalty_charge_type' => 'string',
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
     * Get loan account prefix configuration
     */
    public static function getAccountPrefixConfig()
    {
        return self::firstOrCreate(
            ['type' => 'account_prefix'],
            [
                'prefix' => 'SDS',
                'is_active' => true
            ]
        );
    }

    /**
     * Resolve the penalty payable on an overdue EMI using this penalty configuration.
     *
     * A percentage penalty is charged on the principal portion of the overdue EMI.
     * Interest-only cycles carry no principal, so those fall back to the loan's
     * remaining principal balance to keep the penalty from collapsing to zero.
     */
    public function calculatePenaltyForEmi(Emi $emi, ?LoanAccount $loanAccount = null): float
    {
        $loanAccount = $loanAccount ?: $emi->loanAccount;

        if (($this->penalty_charge_type ?? 'fixed') !== 'percentage') {
            $fixed = ((float) $this->charge_value > 0)
                ? (float) $this->charge_value
                : (float) ($loanAccount->penalty ?? 0);

            return round($fixed, 2);
        }

        $rate = ((float) $this->charge_value > 0)
            ? (float) $this->charge_value
            : (float) ($loanAccount->penalty ?? 0);

        $base = (float) ($emi->principal_amount ?? 0);
        if ($base <= 0 && $loanAccount) {
            $base = (float) $loanAccount->remaining_principal_balance;
        }

        return round(($base * $rate) / 100, 2);
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
