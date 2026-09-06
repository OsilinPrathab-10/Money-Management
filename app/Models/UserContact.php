<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserContact extends Model
{
    use HasFactory;

    protected $table = 'user_contacts';

    protected $fillable = [
        'user_id',
        'device_contact_id',
        'name',
        'phone_number',
        'email',
    ];

    /**
     * Relationship: Contact belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
