<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasObfuscatedRouteKey;  

class LoanAccount extends Model
{
    use HasFactory, HasObfuscatedRouteKey, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'loan_application_id',
        'client_id',
        'account_number',
        'customer_loan_account_number',
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
        'foreclosure_interest_amount',
        'foreclosure_charges_amount',
        'foreclosure_discount_percentage',
        'foreclosure_discount_amount',
        'foreclosure_payment_method',
        'foreclosure_bank_account_id',
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
        'foreclosure_interest_amount' => 'decimal:2',
        'foreclosure_charges_amount' => 'decimal:2',
        'foreclosure_discount_percentage' => 'decimal:2',
        'foreclosure_discount_amount' => 'decimal:2',
    ];

    protected $appends = [
        'remaining_principal_balance',
        'principal_allocated',
        'principal_pending',
        'frequency',
        'frequency_label',
        'present_loan_amount',
        'present_loan_amount_formatted',
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
            // Set a temporary account number to satisfy NOT NULL constraints
            if (empty($model->account_number)) {
                $model->account_number = 'TEMP_' . uniqid();
            }
            if (empty($model->customer_loan_account_number)) {
                $model->customer_loan_account_number = 'TEMP_' . uniqid();
            }
        });

        static::created(function ($model) {
            // Determine the loan type prefix (MC, DC, WC)
            $typeCode = 'MC'; // Default is Monthly (MC)
            $application = $model->loanApplication;
            
            if ($application) {
                $termUnit = strtolower((string)$application->term_unit);
                
                if (in_array($termUnit, ['week', 'weeks', 'weekly'])) {
                    $typeCode = 'WC';
                } elseif (in_array($termUnit, ['day', 'days', 'daily'])) {
                    $typeCode = 'DC';
                }
            }
            $config = \App\Models\LoanConfiguration::getAccountPrefixConfig();
            $systemPrefix = ($config && $config->is_active) ? $config->prefix : '';
            
            $prefix = $systemPrefix . $typeCode;

            // Format the loan amount without decimal points (point-wise values) using the helper
            $formattedAmount = self::formatAmountForAccountNumber((int) $model->loan_amount);

            // Generate Serial Number using the auto-generated ID (4 digits)
            $serialNumber = str_pad($model->id, 4, '0', STR_PAD_LEFT);

            // Combine into final account number: e.g. WC20K0021
            $finalAccountNumber = $prefix . $formattedAmount . $serialNumber;

            $model->account_number = $finalAccountNumber;
            $model->customer_loan_account_number = $finalAccountNumber;
            $model->saveQuietly();
        });
    }

    /**
     * Format the loan amount for the account number prefix without decimal points.
     */
    public static function formatAmountForAccountNumber(int $amountVal): string
    {
        // Round dynamically to ensure clean integers and avoid decimals
        if ($amountVal >= 1000000) {
            // >= 10 Lakhs: round to nearest Lakh (100,000)
            $amountVal = (int) (round($amountVal / 100000) * 100000);
        } elseif ($amountVal >= 10000) {
            // >= 10 Thousands: round to nearest Thousand (1,000)
            $amountVal = (int) (round($amountVal / 1000) * 1000);
        } else {
            // < 10 Thousands: round to nearest Hundred (100)
            $amountVal = (int) (round($amountVal / 100) * 100);
        }

        // Map to suffix notation (Crores, Lakhs, Thousands, Hundreds)
        if ($amountVal >= 10000000 && $amountVal % 10000000 === 0) {
            return (int)($amountVal / 10000000) . 'C';
        } elseif ($amountVal >= 100000 && $amountVal % 100000 === 0) {
            return (int)($amountVal / 100000) . 'L';
        } elseif ($amountVal >= 1000 && $amountVal % 1000 === 0) {
            return (int)($amountVal / 1000) . 'K';
        } elseif ($amountVal >= 100 && $amountVal % 100 === 0) {
            return str_pad((int)($amountVal / 100), 2, '0', STR_PAD_LEFT) . 'H';
        } else {
            // Fallback: use K representation
            return (int)round($amountVal / 1000) . 'K';
        }
    }

    /**
     * Relationships
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_code', 'loan_code');
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_code', 'loan_code');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function foreclosureBankAccount()
    {
        return $this->belongsTo(\App\Models\Account\BankAccount::class, 'foreclosure_bank_account_id');
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
     * Instalments the borrower actually owes right now: still unsettled and due
     * on or before the end of the current month. Overdue instalments from past
     * months stay in the list; settled and future-month instalments drop out.
     *
     * Used by the public schedule links, which show dues only.
     */
    public function currentlyDueEmis(?\Carbon\Carbon $asOf = null): \Illuminate\Support\Collection
    {
        $endOfMonth = ($asOf ?? \Carbon\Carbon::now())->copy()->endOfMonth();

        $emis = $this->relationLoaded('emis') ? $this->emis : $this->emis()->get();

        return $emis
            ->filter(function ($emi) use ($endOfMonth) {
                if (in_array(strtolower((string) $emi->status), ['paid', 'waived'], true)) {
                    return false;
                }

                // Settled by amount even if the status was never flipped.
                if (((float) $emi->total_amount - (float) $emi->paid_amount) <= 0.05) {
                    return false;
                }

                // No due date means we cannot call it a future instalment.
                if (! $emi->due_date) {
                    return true;
                }

                return \Carbon\Carbon::parse($emi->due_date)->startOfDay()->lte($endOfMonth);
            })
            ->sortBy('instalment_number')
            ->values();
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

    /**
     * Principal already applied on EMIs, plus in-progress agent principal collections.
     */
    public function collectedPrincipalTotal(bool $includeInProgress = true): float
    {
        $applied = $this->relationLoaded('emis')
            ? (float) $this->emis->sum(fn ($emi) => (float) ($emi->principal_amount ?? 0))
            : (float) $this->emis()->sum('principal_amount');

        if (!$includeInProgress) {
            return round($applied, 2);
        }

        $pendingQuery = \App\Models\EmiCollection::query()
            ->where('payment_type', 'principal')
            ->where('status', 'in_progress');

        if ($this->relationLoaded('emis') && $this->emis->isNotEmpty()) {
            $pendingQuery->whereIn('emi_id', $this->emis->pluck('id')->filter());
        } else {
            $pendingQuery->whereHas('emi', fn ($q) => $q->where('loan_account_id', $this->id));
        }

        return round($applied + (float) $pendingQuery->sum('amount'), 2);
    }

    public function isOpenLoan(): bool
    {
        return ($this->loan_mode ?? '') === 'interest_only' || (int) ($this->tenure ?? 0) === 0;
    }

    public function openLoanRemainingPrincipal(): float
    {
        return max(0.00, (float) $this->loan_amount - $this->collectedPrincipalTotal(true));
    }

    public function getRemainingPrincipalBalanceAttribute()
    {
        if ((bool) $this->is_foreclosed) {
            return 0.00;
        }

        if ($this->isOpenLoan()) {
            return $this->openLoanRemainingPrincipal();
        }

        if ($this->isSettled()) {
            return 0.00;
        }

        return (float) $this->outstanding_amount;
    }

    /**
     * Foreclosed, closed or otherwise fully settled - nothing further is owed.
     * Open loans stay open until remaining principal is actually cleared.
     */
    public function isSettled(): bool
    {
        if ((bool) $this->is_foreclosed) {
            return true;
        }

        if ($this->isOpenLoan()) {
            return $this->openLoanRemainingPrincipal() <= 0.05;
        }

        return in_array(strtolower((string) $this->status), ['closed', 'foreclosed', 'completed'], true);
    }

    /**
     * Display/API closed flag. Paid interest cycles after bulk verify do not
     * close an Open Loan while principal is still outstanding.
     */
    public function isEffectivelyClosed(): bool
    {
        if ((bool) $this->is_foreclosed) {
            return true;
        }

        if ($this->isOpenLoan()) {
            return $this->openLoanRemainingPrincipal() <= 0.05;
        }

        $status = strtolower((string) $this->status);
        if (in_array($status, ['closed', 'completed', 'foreclosed'], true)) {
            return true;
        }

        if ((float) $this->outstanding_amount <= 0.05 && $this->disbursed_at !== null) {
            return true;
        }

        if ((float) $this->total_payable > 0 && (float) $this->paid_amount + 0.05 >= (float) $this->total_payable) {
            return true;
        }

        return $this->relationLoaded('emis')
            && $this->emis->isNotEmpty()
            && $this->emis->whereNotIn('status', ['paid', 'closed'])->isEmpty();
    }

    public function getPrincipalAllocatedAttribute()
    {
        return $this->collectedPrincipalTotal(true);
    }

    public function getPrincipalPendingAttribute()
    {
        return $this->remaining_principal_balance;
    }

    /**
     * Total collected against this loan (account total, falling back to EMI receipts).
     */
    public function getTotalPaidAttribute(): float
    {
        $stored = (float) ($this->attributes['paid_amount'] ?? 0);
        if ($this->relationLoaded('emis')) {
            $fromEmis = (float) $this->emis->sum(function ($emi) {
                return (float) ($emi->paid_amount ?? 0);
            });

            return round(max($stored, $fromEmis), 2);
        }

        return round($stored, 2);
    }

    /**
     * Normalized frequency of the loan: weekly / daily / monthly.
     */
    public function getFrequencyAttribute(): string
    {
        $termUnit = strtolower((string) (
            optional($this->loanApplication)->term_unit 
            ?? optional(optional($this->loanApplication)->product)->term_unit 
            ?? optional($this->product)->term_unit 
            ?? ''
        ));

        if (in_array($termUnit, ['day', 'days', 'daily'], true)) {
            return 'daily';
        }
        if (in_array($termUnit, ['week', 'weeks', 'weekly'], true)) {
            return 'weekly';
        }
        if (in_array($termUnit, ['month', 'months', 'monthly'], true)) {
            return 'monthly';
        }

        $accNo = strtoupper((string) ($this->account_number ?? $this->customer_loan_account_number ?? ''));
        if (str_contains($accNo, 'DC')) {
            return 'daily';
        }
        if (str_contains($accNo, 'WC')) {
            return 'weekly';
        }

        return 'monthly';
    }

    /**
     * Capitalized frequency label: Weekly / Daily / Monthly.
     */
    public function getFrequencyLabelAttribute(): string
    {
        return ucfirst($this->frequency);
    }

    /**
     * Present loan amount (installment/EMI according to frequency - weekly / daily / monthly).
     */
    public function getPresentLoanAmountAttribute(): float
    {
        $amount = (float) ($this->emi_amount ?? 0);
        if ($amount <= 0 && $this->total_payable > 0 && $this->tenure > 0) {
            $amount = round((float) $this->total_payable / (float) $this->tenure, 2);
        }
        return $amount;
    }

    /**
     * Formatted present loan amount with currency symbol.
     */
    public function getPresentLoanAmountFormattedAttribute(): string
    {
        return '₹' . number_format($this->present_loan_amount, 2);
    }

    /**
     * Safe sanctioned amount accessor with fallback to loan_amount.
     */
    public function getSanctionedAmountAttribute(): float
    {
        return (float) ($this->attributes['sanctioned_amount'] ?? $this->loan_amount ?? 0);
    }
}
