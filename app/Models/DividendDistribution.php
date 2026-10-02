<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DividendDistribution extends Model
{
    protected $fillable = [
        'dividend_id', 'member_id', 'amount', 'status',
        'payment_mode', 'reference_no', 'paid_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function dividend()
    {
        return $this->belongsTo(Dividend::class);
    }

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'member_id');
    }
}
