<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedDepositScheme extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'scheme_code',
        'name',
        'deposit_type',
        'min_deposit_amount',
        'max_deposit_amount',
        'interest_rate',
        'interest_frequency',
        'min_tenure',
        'max_tenure',
        'tenure_type',
        'premature_withdrawal_allowed',
        'premature_penalty_type',
        'premature_penalty_value',
        'auto_renewal',
        'renewal_type',
        'default_payout_option',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        'min_deposit_amount' => 'decimal:2',
        'max_deposit_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'premature_withdrawal_allowed' => 'boolean',
        'premature_penalty_value' => 'decimal:2',
        'auto_renewal' => 'boolean',
    ];

    public function deposits()
    {
        return $this->hasMany(FixedDeposit::class, 'scheme_id');
    }

    public function applications()
    {
        return $this->hasMany(FixedDepositApplication::class, 'scheme_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public static function generateCode(): string
    {
        $last = self::withTrashed()->latest('id')->first();
        $next = $last ? $last->id + 1 : 1;

        return 'FDS' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function getDepositTypeLabelAttribute(): string
    {
        return match ($this->deposit_type) {
            'simple_interest' => 'Simple Interest',
            'compound_interest' => 'Compound Interest',
            default => ucfirst(str_replace('_', ' ', (string) $this->deposit_type)),
        };
    }

    public function getInterestFrequencyLabelAttribute(): string
    {
        return match ($this->interest_frequency) {
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'half_yearly' => 'Half-Yearly',
            'yearly' => 'Yearly',
            default => ucfirst(str_replace('_', ' ', (string) $this->interest_frequency)),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->status === 'active' ? 'success' : 'secondary';
    }

    public static function depositTypes(): array
    {
        return [
            'simple_interest' => 'Simple Interest',
            'compound_interest' => 'Compound Interest',
        ];
    }

    public static function frequencies(): array
    {
        return [
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'half_yearly' => 'Half-Yearly',
            'yearly' => 'Yearly',
        ];
    }

    public static function tenureTypes(): array
    {
        return [
            'months' => 'Months',
            'years' => 'Years',
        ];
    }

    public static function payoutOptions(): array
    {
        return [
            'wallet' => 'Wallet',
            'chit' => 'Chit',
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
        ];
    }

    public static function renewalTypes(): array
    {
        return [
            'principal_only' => 'Principal Only',
            'principal_interest' => 'Principal + Interest',
        ];
    }
}
