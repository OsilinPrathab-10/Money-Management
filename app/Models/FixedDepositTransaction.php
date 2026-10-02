<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDepositTransaction extends Model
{
    protected $fillable = [
        'fixed_deposit_id',
        'transaction_type',
        'amount',
        'principal_amount',
        'interest_amount',
        'penalty_amount',
        'payment_mode',
        'reference',
        'description',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function fixedDeposit()
    {
        return $this->belongsTo(FixedDeposit::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
