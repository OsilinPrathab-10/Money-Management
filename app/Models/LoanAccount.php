<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasObfuscatedRouteKey;  

class LoanAccount extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'loan_application_id',
        'client_id',
        'account_number',
        'application_number',
        'loan_code',
        'loan_mode',
        'loan_amount',
        'disbursed_amount',
        'interest_rate',
        'tenure',
        'emi_amount',
        'emi_day',
        'payment_method',
        'total_payable',
        'paid_amount',
        'outstanding_amount',
        'penalty',
        'penalty_type',
        'grace_period_days',
        'transaction_id',
        'utr_number',
        'status',
        'disbursed_at',
        'closed_at',
        'foreclosure_eligibility_months',
        'foreclosure_charges_percentage',
        'is_foreclosed',
        'foreclosure_amount',
        'foreclosure_notes',
        'foreclosure_processed_by',
        'prepayment_amount',
        'prepayment_eligibility_months',
        'prepayment_charges_percentage',
    ];

    protected $casts = [
        'loan_amount' => 'integer',
        'disbursed_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'emi_amount' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'prepayment_amount' => 'decimal:2',
        'prepayment_eligibility_months' => 'integer',
        'prepayment_charges_percentage' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'closed_at' => 'datetime',
        'foreclosure_eligibility_months' => 'integer',
        'foreclosure_charges_percentage' => 'decimal:2',
        'is_foreclosed' => 'boolean',
        'foreclosure_amount' => 'decimal:2',
    ];

    protected $appends = [
        'remaining_principal_balance',
        'principal_allocated',
        'principal_pending',
    ];

    /**
     * Loans that still require collection / agent follow-up activity.
     */
    public function scopeActiveForCollection($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Boot method to generate account number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Set a temporary account number to satisfy NOT NULL constraint
            if (empty($model->account_number)) {
                $model->account_number = 'TEMP_' . uniqid();
            }
        });

        static::created(function ($model) {
            // Get frequency from application
            $frequencyChar = 'M';
            $application = $model->loanApplication;
            if ($application) {
                $termUnit = strtolower((string)$application->term_unit);
                if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
                    $frequencyChar = 'W';
                } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
                    $frequencyChar = 'D';
                }
            }

            // Calculate amount prefix (e.g., 1000 -> 01, 10000 -> 10)
            $amountPrefix = str_pad(floor($model->loan_amount / 1000), 2, '0', STR_PAD_LEFT);
            
            // Generate Serial Number (4 digits)
            $serialNumber = str_pad($model->id, 4, '0', STR_PAD_LEFT);

            $model->account_number = 'S' . $amountPrefix . $frequencyChar . $serialNumber;
            $model->saveQuietly();
        });
    }

    /**
     * Relationships
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function emis()
    {
        return $this->hasMany(Emi::class, 'loan_account_id', 'id');
    }

    public function clientLoanDocuments()
    {
        return $this->hasMany(ClientLoanDocument::class, 'loan_account_id', 'id');
    }


    /**
     * Get effective foreclosure eligibility months/weeks/days
     * Priority: Global Config (Database) > System Default (half tenure)
     */
    public function getForeclosureEligibilityMonths()
    {
        // Priority 1: Global configuration from database
        $foreclosureConfig = \App\Models\LoanConfiguration::getForeclosureConfig();
        if ($foreclosureConfig) {
            $application = $this->loanApplication;
            $termUnit = $application ? strtolower((string)$application->term_unit) : 'monthly';

            if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
                if ($foreclosureConfig->eligibility_weeks !== null) {
                    return (int) $foreclosureConfig->eligibility_weeks;
                }
                // Fallback to scaled monthly
                if ($foreclosureConfig->eligibility_months !== null) {
                    return (int) $foreclosureConfig->eligibility_months * 4;
                }
            } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
                if ($foreclosureConfig->eligibility_days !== null) {
                    return (int) $foreclosureConfig->eligibility_days;
                }
                // Fallback to scaled monthly
                if ($foreclosureConfig->eligibility_months !== null) {
                    return (int) $foreclosureConfig->eligibility_months * 30;
                }
            } else {
                if ($foreclosureConfig->eligibility_months !== null) {
                    return (int) $foreclosureConfig->eligibility_months;
                }
            }
        }

        // Priority 2: System default (half tenure)
        return (int) ceil($this->tenure / 2);
    }

    /**
     * Get effective foreclosure charges percentage
     * Priority: Global Config (Database) > System Default (0)
     */
    public function getForeclosureChargesPercentage()
    {
        // Get from database configuration
        $foreclosureConfig = \App\Models\LoanConfiguration::getForeclosureConfig();
        if ($foreclosureConfig) {
            $application = $this->loanApplication;
            $termUnit = $application ? strtolower((string)$application->term_unit) : 'monthly';

            if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
                if ($foreclosureConfig->charges_percentage_weekly !== null) {
                    return (float) $foreclosureConfig->charges_percentage_weekly;
                }
            } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
                if ($foreclosureConfig->charges_percentage_daily !== null) {
                    return (float) $foreclosureConfig->charges_percentage_daily;
                }
            }

            return (float) ($foreclosureConfig->charges_percentage ?? 0);
        }

        // System default
        return 0;
    }

    /**
     * Get effective prepayment eligibility months/weeks/days
     * Priority: Global Config (Database) > System Default (half tenure)
     */
    public function getPrepaymentEligibilityMonths()
    {
        $prepaymentConfig = \App\Models\LoanConfiguration::getPrepaymentConfig();
        if ($prepaymentConfig) {
            $application = $this->loanApplication;
            $termUnit = $application ? strtolower((string)$application->term_unit) : 'monthly';

            if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
                if ($prepaymentConfig->eligibility_weeks !== null) {
                    return (int) $prepaymentConfig->eligibility_weeks;
                }
                // Fallback to scaled monthly
                if ($prepaymentConfig->eligibility_months !== null) {
                    return (int) $prepaymentConfig->eligibility_months * 4;
                }
            } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
                if ($prepaymentConfig->eligibility_days !== null) {
                    return (int) $prepaymentConfig->eligibility_days;
                }
                // Fallback to scaled monthly
                if ($prepaymentConfig->eligibility_months !== null) {
                    return (int) $prepaymentConfig->eligibility_months * 30;
                }
            } else {
                if ($prepaymentConfig->eligibility_months !== null) {
                    return (int) $prepaymentConfig->eligibility_months;
                }
            }
        }

        return (int) ceil($this->tenure / 2);
    }

    /**
     * Get effective prepayment charges percentage
     * Priority: Global Config (Database) > System Default (0)
     */
    public function getPrepaymentChargesPercentage()
    {
        $prepaymentConfig = \App\Models\LoanConfiguration::getPrepaymentConfig();
        if ($prepaymentConfig) {
            $application = $this->loanApplication;
            $termUnit = $application ? strtolower((string)$application->term_unit) : 'monthly';

            if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
                if ($prepaymentConfig->charge_value_weekly !== null) {
                    return (float) $prepaymentConfig->charge_value_weekly;
                }
            } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
                if ($prepaymentConfig->charge_value_daily !== null) {
                    return (float) $prepaymentConfig->charge_value_daily;
                }
            }

            return (float) ($prepaymentConfig->charge_value ?? 0);
        }

        return 0;
    }

    public function getRemainingPrincipalBalanceAttribute()
    {
        if ($this->loan_mode === 'interest_only') {
            $principalPaid = (float) $this->emis()->sum('principal_amount');
            return max(0.00, (float)$this->loan_amount - $principalPaid);
        }
        return (float) $this->outstanding_amount;
    }

    public function getPrincipalAllocatedAttribute()
    {
        return (float) $this->emis()->sum('principal_amount');
    }

    public function getPrincipalPendingAttribute()
    {
        return $this->remaining_principal_balance;
    }
}
