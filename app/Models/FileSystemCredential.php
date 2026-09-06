<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class FileSystemCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'is_enabled',
        'access_key_id',
        'secret_access_key',
        'region',
        'bucket',
        'url',
        'endpoint',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Encrypt access_key_id when setting
     */
    protected function accessKeyId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    /**
     * Encrypt secret_access_key when setting
     */
    protected function secretAccessKey(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    /**
     * Get the S3 configuration instance
     */
    public static function getS3Config()
    {
        return static::where('provider', 's3')->first() ?? new static(['provider' => 's3']);
    }
}
