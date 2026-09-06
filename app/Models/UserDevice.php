<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class UserDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'device_id',
        'device_token',
        'device_model',
        'device_name',
        'latitude',
        'longitude',
        'ip_address',
        'login_at',
        'logout_at',
    ];

    /**
     * Relation to User.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
