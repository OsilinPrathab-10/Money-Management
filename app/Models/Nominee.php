<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Client;

class Nominee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'nominee1_name',
        'nominee1_relationship',
        'nominee1_mobile',
        'nominee2_name',
        'nominee2_relationship',
        'nominee2_mobile',
    ];

    /**
     * Relationship: A nominee record belongs to a client.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
