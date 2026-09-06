<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guarantor extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'phone',
        'relationship',
        'address',
        'type'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
