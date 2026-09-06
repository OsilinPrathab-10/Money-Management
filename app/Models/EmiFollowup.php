<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasObfuscatedRouteKey;
use App\Models\Emi;
use App\Models\Agent;

class EmiFollowup extends Model
{
    use HasObfuscatedRouteKey;
    protected $fillable = [
      'emi_id',
      'agent_id',
      'status',
      'followup_at',
      'remarks'
    ];

    protected $casts = [
        'followup_at' => 'datetime',
    ];

    public function emi()
    {
        return $this->belongsTo(Emi::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
