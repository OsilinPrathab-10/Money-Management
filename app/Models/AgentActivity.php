<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasObfuscatedRouteKey;

class AgentActivity extends Model
{
    use HasObfuscatedRouteKey;
     protected $fillable = [
        'emi_id',
        'agent_id',
        'type', // call, visit, payment, note
        'description',
        'method',
        'reference',
        'remarks',
        'action_at',
    ];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function emi()
    {
        return $this->belongsTo(Emi::class);
    }
}
