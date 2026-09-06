<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
        'order',
    ];

    /**
     * Get FAQs ordered by order column
     */
    public static function getOrdered()
    {
        return self::orderBy('order', 'asc')->orderBy('id', 'asc')->get();
    }
}
