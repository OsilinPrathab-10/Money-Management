<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'identifier',
        'template_id',
        'sms_body',
        'status',
    ];
}
