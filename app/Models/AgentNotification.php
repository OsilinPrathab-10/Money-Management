<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'notification_type',
        'notification_id',
        'title',
        'message',
        'notification_type_label',
        'icon',
        'priority',
        'action_data',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'action_data' => 'array',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
