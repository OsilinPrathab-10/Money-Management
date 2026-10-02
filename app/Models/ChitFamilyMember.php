<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChitFamilyMember extends Model
{
    protected $fillable = [
        'family_id',
        'client_id',
        'relationship',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function family()
    {
        return $this->belongsTo(ChitFamily::class, 'family_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
