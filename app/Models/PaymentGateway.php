<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway',
        'name',
        'enabled',
        'api_key',
        'api_secret',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'metadata' => 'array',
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
    ];

    /**
     * Get the api_key attribute safely.
     */
    protected function apiKey(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (is_null($value)) return null;
                try {
                    return decrypt($value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return null;
                }
            }
        );
    }

    /**
     * Get the api_secret attribute safely.
     */
    protected function apiSecret(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (is_null($value)) return null;
                try {
                    return decrypt($value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return null;
                }
            }
        );
    }
}
