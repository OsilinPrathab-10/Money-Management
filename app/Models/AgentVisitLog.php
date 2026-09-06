<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasObfuscatedRouteKey;

class AgentVisitLog extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $table = 'agent_visit_logs';

    protected $fillable = [
        'agent_id',
        'emi_id',
        'start_latitude',
        'start_longitude',
        'started_at',
        'end_latitude',
        'end_longitude',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
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
