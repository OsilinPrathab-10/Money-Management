<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'notification_type',
        'notification_id',
        'title',
        'message',
        'notification_type_label',
        'icon',
        'priority',
        'action_data',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'action_data' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
