<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'identifier',
        'subject',
        'email_body',
        'image_path',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}
