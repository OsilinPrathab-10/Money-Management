<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Concerns\HasObfuscatedRouteKey;

class Agent extends Model
{
    use HasFactory, SoftDeletes, HasApiTokens, HasObfuscatedRouteKey;

    protected $fillable = [
        'user_id',
        'agent_name',
        'agent_email',
        'agent_phone',
        'agent_code',
        'address',
        'city',
        'district',
        'state',
        'pincode',
        'location_id',
        'salary_amount',
        'salary_details',
        'status',
        'is_deleted',
    ];

    protected $casts = [
        'salary_details' => 'array',
        'salary_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

     public function emi()
    {
        return $this->belongsTo(Emi::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'assigned_to', 'id');
    }

    public function emiAssignments()
    {
        return $this->hasMany(EmiAgentAssignment::class);
    }

    public function activeEmis()
    {
        return $this->emiAssignments()
            ->whereIn('status', ['assigned', 'visited']);
    }

    public function recoveries()
    {
        return $this->hasMany(Recovery::class);
    }

    public function followups()
    {
        return $this->hasMany(EmiFollowup::class);
    }

    public function visitLogs()
    {
        return $this->hasMany(AgentVisitLog::class);
    }

    public function expenses()
    {
        return $this->hasMany(AgentExpense::class);
    }

    public function advances()
    {
        return $this->hasMany(AgentAdvance::class);
    }

    public function agentDevice()
    {
        return $this->hasMany(UserDevice::class, 'user_id', 'user_id')
            ->where('user_type', 'Agent');
    }

    public function attendances()
    {
        return $this->hasMany(AgentAttendance::class);
    }

    protected static function booted()

    {
        static::creating(function ($agent) {
            if (!isset($agent->user_id)) {
                try {
                    $user = User::firstOrCreate(
                        ['phone' => $agent->agent_phone],
                        [
                            'name' => $agent->agent_name ?? 'Guest',
                            'email' => $agent->agent_email ?? null,
                        ]
                    );

                    $agent->user_id = $user->id;
                } catch (\Throwable $e) {
                    // If user creation fails, try to get existing user
                    $user = User::where('phone', $agent->agent_phone)->first();
                    if ($user) {
                        $agent->user_id = $user->id;
                    } else {
                        throw $e;
                    }
                }
            }
        });
    }
}
