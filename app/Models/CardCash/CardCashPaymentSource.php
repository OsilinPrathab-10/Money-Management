<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardCashPaymentSource extends Model
{
    use HasFactory;

    protected $table = 'card_cash_payment_sources';

    protected $fillable = [
        'source_name',
        'source_type',
        'account_number_or_reference',
        'wallet_id',
        'status',
        'remarks',
        'created_by',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CreditCardWallet::class, 'wallet_id');
    }

    public function billPayments(): HasMany
    {
        return $this->hasMany(CardCashBillPayment::class, 'payment_source_id');
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
