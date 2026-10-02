<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionBid extends Model
{
    protected $fillable = ['auction_id', 'member_id', 'bid_amount', 'is_winner'];

    protected $casts = ['bid_amount' => 'decimal:2', 'is_winner' => 'boolean'];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'member_id');
    }
}
