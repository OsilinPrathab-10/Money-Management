<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteTestimonial extends Model
{
    protected $fillable = [
        'name', 'role', 'location', 'content', 'rating', 'avatar_path', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
