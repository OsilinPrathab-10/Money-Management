<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentAdvance extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'amount',
        'date',
        'description',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
