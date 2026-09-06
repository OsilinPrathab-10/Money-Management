<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSmsMessage extends Model
{
    use HasFactory;

    protected $table = 'user_sms_messages';

    protected $fillable = [
        'user_id',
        'address',
        'body',
        'type',
        'date',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    /**
     * Relationship: SMS message belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
