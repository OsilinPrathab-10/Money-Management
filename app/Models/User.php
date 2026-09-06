<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Client;
use App\Models\KycDocument;
use App\Models\UserContact;
use App\Models\UserCallLog;
use App\Models\UserSmsMessage;
use App\Models\UserDevice;
use App\Models\Concerns\HasObfuscatedRouteKey;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, HasObfuscatedRouteKey;

    /*
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'role_id',
        'phone',
        'email',
        'password',
        'plain_password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function client()
    {
        return $this->hasOne(Client::class);
    }

    public function agent()
    {
        return $this->hasOne(Agent::class);
    }

    public function userDevice()
    {
      return $this->hasMany(UserDevice::class);
    }

    public function contacts()
    {
        return $this->hasMany(UserContact::class);
    }

    public function callLogs()
    {
        return $this->hasMany(UserCallLog::class);
    }

    public function loans()
    {
        return $this->hasMany(LoanApplication::class, 'assigned_to');
    }

    public function smsMessages()
    {
        return $this->hasMany(UserSmsMessage::class);
    }

    /**
     * Get the user's profile photo URL.
     *
     * @return string
     */
    public function getProfilePhotoUrlAttribute()
    {
        // If you have an avatar column in the future, use it
        // return $this->avatar ? asset('storage/' . $this->avatar) : asset('assets/img/avatars/1.png');

        // For now, return a default avatar
        return asset('assets/img/avatars/1.png');
    }

}
