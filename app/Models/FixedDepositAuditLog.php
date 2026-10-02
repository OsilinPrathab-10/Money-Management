<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDepositAuditLog extends Model
{
    protected $fillable = [
        'fixed_deposit_id',
        'scheme_id',
        'action',
        'user_id',
        'role',
        'previous_values',
        'updated_values',
        'ip_address',
        'remarks',
    ];

    protected $casts = [
        'previous_values' => 'array',
        'updated_values' => 'array',
    ];

    public function fixedDeposit()
    {
        return $this->belongsTo(FixedDeposit::class);
    }

    public function scheme()
    {
        return $this->belongsTo(FixedDepositScheme::class, 'scheme_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
