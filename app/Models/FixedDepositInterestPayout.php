<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDepositInterestPayout extends Model
{
    protected $fillable = [
        'fixed_deposit_id',
        'payout_month',
        'payout_year',
        'period_from',
        'period_to',
        'interest_amount',
        'wallet_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'interest_amount' => 'decimal:2',
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function fixedDeposit()
    {
        return $this->belongsTo(FixedDeposit::class);
    }

    public function walletTransaction()
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
