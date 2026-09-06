<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $table = 'policy_pages';

    protected $fillable = [
        'title', 'slug', 'content', 'status'
    ];
}
