<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardWallet extends Model
{
    use HasFactory;

    protected $table = 'credit_card_wallets';

    protected $fillable = [
        'wallet_name',
        'wallet_code',
        'wallet_type',
        'opening_balance',
        'current_balance',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditCardWalletTransaction::class, 'wallet_id')->latest('id');
    }

    public function swipeTransactions(): HasMany
    {
        return $this->hasMany(CardCashSwipeTransaction::class, 'wallet_id');
    }

    public function billPayments(): HasMany
    {
        return $this->hasMany(CardCashBillPayment::class, 'wallet_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
