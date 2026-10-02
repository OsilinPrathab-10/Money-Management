<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDepositChitAllocation extends Model
{
    protected $fillable = [
        'fixed_deposit_id',
        'fd_transaction_id',
        'chit_group_id',
        'allocation_type',
        'amount',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function fixedDeposit()
    {
        return $this->belongsTo(FixedDeposit::class);
    }

    public function transaction()
    {
        return $this->belongsTo(FixedDepositTransaction::class, 'fd_transaction_id');
    }

    public function chitGroup()
    {
        return $this->belongsTo(ChitGroup::class, 'chit_group_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
