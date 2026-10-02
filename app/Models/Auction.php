<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $fillable = [
        'group_id', 'month_number', 'auction_date', 'auction_time', 'duration_minutes', 'bid_type', 'location',
        'min_bid', 'max_bid', 'winning_bid', 'discount',
        'winner_member_id', 'status', 'remarks', 'conducted_by',
        'started_at', 'ended_at',
    ];

    protected $casts = [
        'auction_date' => 'date',
        'min_bid' => 'decimal:2',
        'max_bid' => 'decimal:2',
        'winning_bid' => 'decimal:2',
        'discount' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(ChitGroup::class, 'group_id');
    }

    public function winner()
    {
        return $this->belongsTo(GroupMember::class, 'winner_member_id');
    }

    public function bids()
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function dividend()
    {
        return $this->hasOne(Dividend::class);
    }

    public function payout()
    {
        return $this->hasOne(Payout::class);
    }

    public function conductedBy()
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'completed'  => 'success',
            'open'       => 'warning',
            'scheduled'  => 'info',
            'cancelled'  => 'danger',
            default      => 'secondary',
        };
    }
}
