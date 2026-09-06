<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'date',
        'status',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
