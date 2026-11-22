<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteBanner extends Model
{
    protected $fillable = [
        'placement', 'title', 'subtitle', 'cta_text', 'cta_url', 'image_path', 'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return LoanType::formatImageUrl($this->image_path);
    }
}
