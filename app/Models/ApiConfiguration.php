<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'label',
        'credentials',
        'is_enabled',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_enabled' => 'boolean',
    ];

    /**
     * Get the credentials attribute safely.
     * Handles DecryptException (e.g. if APP_KEY changed).
     */
    protected function credentials(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (is_null($value)) return [];
                try {
                    // Laravel automatically decrypts via the cast, 
                    // but we need to trigger it here if it hasn't been already.
                    // Actually, if it's already an array (due to cast), it's fine.
                    // But the exception happens during the cast's 'get'.
                    return $this->castAttribute('credentials', $value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to decrypt ApiConfiguration credentials: " . $e->getMessage());
                    return [];
                } catch (\Exception $e) {
                    return is_array($value) ? $value : [];
                }
            }
        );
    }
}
