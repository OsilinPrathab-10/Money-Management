<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'loan_type_icon',
        'loan_type_image',
        'loan_type_banner',
        'status'
    ];

    protected $appends = [
        'loan_type_icon_url',
        'loan_type_image_url',
        'loan_type_banner_url',
    ];

    public static function formatImageUrl(?string $path): ?string
    {
        if ($path === null || trim((string) $path) === '') {
            return null;
        }

        $trimmed = trim((string) $path);

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        $cleanPath = ltrim($trimmed, '/');

        if (str_starts_with($cleanPath, 'app/public/')) {
            $cleanPath = substr($cleanPath, strlen('app/public/'));
        }

        if (str_starts_with($cleanPath, 'public/storage/')) {
            $cleanPath = substr($cleanPath, strlen('public/storage/'));
        } elseif (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, strlen('storage/'));
        }

        if (str_starts_with($cleanPath, 'uploads/')) {
            return route('uploads.serve', ['path' => substr($cleanPath, strlen('uploads/'))], true);
        }

        if (str_starts_with($cleanPath, 'assets/') || str_starts_with($cleanPath, 'images/') || file_exists(public_path($cleanPath))) {
            return asset($cleanPath);
        }

        return asset('storage/' . ltrim($cleanPath, '/'));
    }

    public function getLoanTypeIconUrlAttribute()
    {
        return self::formatImageUrl($this->loan_type_icon);
    }

    public function getLoanTypeImageUrlAttribute()
    {
        return self::formatImageUrl($this->loan_type_image);
    }

    public function getLoanTypeBannerUrlAttribute()
    {
        return self::formatImageUrl($this->loan_type_banner);
    }

    public function products()
    {
        return $this->hasMany(LoanProduct::class, 'loan_type_id');
    }

    protected $casts = [
        'status' => 'boolean',
    ];
}
