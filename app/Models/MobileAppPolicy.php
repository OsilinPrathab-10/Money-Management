<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileAppPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_type',
        'type',
        'title',
        'version',
        'effective_date',
        'content',
        'status',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];
}
