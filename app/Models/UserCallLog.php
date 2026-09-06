<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCallLog extends Model
{
    use HasFactory;

    protected $table = 'user_call_logs';

    protected $fillable = [
        'user_id',
        'device_log_id',
        'name',
        'phone_number',
        'call_type',
        'duration',
        'call_time',
        'phone_account_id',
    ];

    protected $casts = [
        'call_time' => 'datetime',
    ];

    /**
     * Relationship: Call log belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
