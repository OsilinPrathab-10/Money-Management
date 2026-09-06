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

    public function getLoanTypeIconUrlAttribute()
    {
        return $this->loan_type_icon
            ? asset('storage/' . $this->loan_type_icon)
            : null;
    }

    public function getLoanTypeImageUrlAttribute()
    {
        return $this->loan_type_image
            ? asset('storage/' . $this->loan_type_image)
            : null;
    }

    public function getLoanTypeBannerUrlAttribute()
    {
        return $this->loan_type_banner
            ? asset('storage/' . $this->loan_type_banner)
            : null;
    }

    protected $casts = [
        'status' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(LoanProduct::class, 'loan_type_id');
    }
}
