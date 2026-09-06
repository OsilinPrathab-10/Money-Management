<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Appearance extends Model
{
    use HasFactory;

    protected $fillable = [
        'primary_color',
        'secondary_color',
        'title',
        'subtitle',
        'logo',
        'logo_dark',
        'favicon',
        'type',
        'footer_text',
    ];

    protected function logo(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->normalizeAssetPathForRead($value),
            set: fn ($value) => $this->normalizeAssetPathForWrite($value),
        );
    }

    protected function logoDark(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->normalizeAssetPathForRead($value),
            set: fn ($value) => $this->normalizeAssetPathForWrite($value),
        );
    }

    protected function favicon(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->normalizeAssetPathForRead($value),
            set: fn ($value) => $this->normalizeAssetPathForWrite($value),
        );
    }

    private function normalizeAssetPathForRead(?string $value): ?string
    {
        $path = $this->normalizeAssetPath($value);

        return $path !== '' ? $path : null;
    }

    private function normalizeAssetPathForWrite(?string $value): string
    {
        return $this->normalizeAssetPath($value);
    }

    private function normalizeAssetPath(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $path = trim($value);
        if ($path === '') {
            return '';
        }

        // Handle absolute storage URL values that may already be saved in DB.
        $storageUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');
        if ($storageUrl !== '' && str_starts_with($path, $storageUrl.'/')) {
            $path = substr($path, strlen($storageUrl) + 1);
        }

        // Normalize legacy prefixes so views can safely do asset('storage/' . $path).
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        } elseif (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        return $path;
    }
}
