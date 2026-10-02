<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationApiHit extends Model
{
    protected $fillable = [
        'service',
        'endpoint',
        'success',
        'http_status',
        'request_payload',
        'response_message',
        'user_id',
        'ip_address',
    ];

    protected $casts = [
        'success' => 'boolean',
        'request_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeService($query, string $service)
    {
        return $query->where('service', $service);
    }
}
