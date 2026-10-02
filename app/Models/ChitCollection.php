<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChitCollection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'installment_id',
        'group_id',
        'member_id',
        'client_id',
        'agent_id',
        'amount',
        'share_percentage',
        'payment_method',
        'payment_type',
        'payment_reference',
        'status',
        'collected_at',
        'verified_by',
        'verified_at',
        'remarks',
        'rejected_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'share_percentage' => 'decimal:2',
        'collected_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function installment()
    {
        return $this->belongsTo(Installment::class, 'installment_id');
    }

    public function group()
    {
        return $this->belongsTo(ChitGroup::class, 'group_id');
    }

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'member_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'in_progress');
    }
}
