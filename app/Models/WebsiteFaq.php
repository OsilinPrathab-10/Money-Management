<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteFaq extends Model
{
    protected $fillable = ['category', 'question', 'answer', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
