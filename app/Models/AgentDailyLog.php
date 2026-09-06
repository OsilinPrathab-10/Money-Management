<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgentDailyLog extends Model
{
    use HasFactory;

    protected $table = 'agent_daily_logs';

    protected $fillable = [
        'agent_id',
        'check_in_at',
        'check_out_at',
        'check_in_lat',
        'check_in_long',
        'check_out_lat',
        'check_out_long',
        'notes',
        'status',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function getTotalHoursAttribute()
    {
        if (!$this->check_in_at) {
            return null;
        }

        if (!$this->check_out_at) {
            return null;
        }

        $diff = $this->check_in_at->diff($this->check_out_at);
        return sprintf('%02d:%02d', $diff->h + ($diff->days * 24), $diff->i);
    }

    public function getTotalMinutesAttribute()
    {
        if (!$this->check_in_at || !$this->check_out_at) {
            return 0;
        }

        return $this->check_in_at->diffInMinutes($this->check_out_at);
    }
}
