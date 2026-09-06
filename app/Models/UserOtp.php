<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserOtp extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'otp_code',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Polymorphic relation to Client or Agent.
     */
    public function user()
    {
        return $this->morphTo();
    }

    /**
     * Check if OTP is expired.
     */
    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }
}
