<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dividend extends Model
{
    protected $fillable = [
        'group_id', 'auction_id', 'month_number', 'chit_value',
        'discount', 'commission_amount', 'net_dividend',
        'per_member_dividend', 'status', 'processed_at',
    ];

    protected $casts = [
        'chit_value' => 'decimal:2',
        'discount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_dividend' => 'decimal:2',
        'per_member_dividend' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(ChitGroup::class, 'group_id');
    }

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function distributions()
    {
        return $this->hasMany(DividendDistribution::class);
    }
}
