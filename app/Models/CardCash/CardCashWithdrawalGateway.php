<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardCashWithdrawalGateway extends Model
{
    use HasFactory;

    protected $table = 'card_cash_withdrawal_gateways';

    protected $fillable = [
        'gateway_name',
        'gateway_code',
        'company_id',
        'status',
        'wallet_supported',
        'debit_percentage',
        'credit_percentage',
        'prepaid_percentage',
        'business_percentage',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'wallet_supported' => 'boolean',
        'debit_percentage' => 'float',
        'credit_percentage' => 'float',
        'prepaid_percentage' => 'float',
        'business_percentage' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CardCashCompany::class, 'company_id');
    }

    public function swipeTransactions(): HasMany
    {
        return $this->hasMany(CardCashSwipeTransaction::class, 'withdrawal_gateway_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getDisplayLabelAttribute(): string
    {
        $company = optional($this->company)->company_name;
        if ($company) {
            return $company . ' – ' . $this->gateway_name;
        }

        return $this->gateway_name;
    }
}
