<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMemberShare extends Model
{
    protected $fillable = [
        'group_member_id',
        'client_id',
        'ownership_percentage',
        'share_amount',
        'is_primary',
    ];

    protected $casts = [
        'ownership_percentage' => 'decimal:2',
        'share_amount' => 'decimal:2',
        'is_primary' => 'boolean',
    ];

    public function membership()
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function allocate(float $amount): float
    {
        return round($amount * ((float) $this->ownership_percentage / 100), 2);
    }
}
