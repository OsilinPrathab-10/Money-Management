<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDepositRenewal extends Model
{
    protected $fillable = [
        'old_fd_id',
        'new_fd_id',
        'renewal_type',
        'principal_carried',
        'interest_carried',
        'renewed_at',
        'renewed_by',
        'remarks',
    ];

    protected $casts = [
        'principal_carried' => 'decimal:2',
        'interest_carried' => 'decimal:2',
        'renewed_at' => 'datetime',
    ];

    public function oldDeposit()
    {
        return $this->belongsTo(FixedDeposit::class, 'old_fd_id');
    }

    public function newDeposit()
    {
        return $this->belongsTo(FixedDeposit::class, 'new_fd_id');
    }

    public function renewedByUser()
    {
        return $this->belongsTo(User::class, 'renewed_by');
    }
}
