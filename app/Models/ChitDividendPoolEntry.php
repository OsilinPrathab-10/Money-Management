<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChitDividendPoolEntry extends Model
{
    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';

    protected $table = 'chit_dividend_pool_entries';

    protected $fillable = [
        'group_id',
        'payout_id',
        'month_number',
        'entry_type',
        'amount',
        'chit_value',
        'payout_amount',
        'balance_after',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'chit_value' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function group()
    {
        return $this->belongsTo(ChitGroup::class, 'group_id');
    }

    public function payout()
    {
        return $this->belongsTo(Payout::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getEntryTypeLabelAttribute(): string
    {
        return $this->entry_type === self::TYPE_CREDIT ? 'Surplus → Pool' : 'Pool → Excess Payout';
    }

    public function getEntryTypeBadgeAttribute(): string
    {
        return $this->entry_type === self::TYPE_CREDIT ? 'success' : 'warning';
    }
}
