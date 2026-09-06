<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasObfuscatedRouteKey;

class LoanProduct extends Model
{
    use HasFactory, SoftDeletes, HasObfuscatedRouteKey;

    protected $table = 'loan_products';

    protected $fillable = [
        'loan_name',
        'loan_type_id',
        'loan_code',
        'loan_amount_min',
        'loan_amount_max',
        'interest_rate',
        'interest_type',
        'term_unit',
        'min_tenture',
        'max_tenture',
        'processing_fee',
        'document_charges',
        'other_charges',
        'penalty_rate',
        'grace_period_days',
        'require_collateral',
        'default_term',
        'description',
        'status',
    ];

    protected $appends = [
        'loan_type_icon_url',
        'loan_type_image_url',
        'loan_type_banner_url',
    ];

    public function applications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loanType()
    {
        return $this->belongsTo(LoanType::class, 'loan_type_id');
    }

    public function getLoanTypeIconUrlAttribute()
    {
        return $this->loanType && $this->loanType->loan_type_icon
            ? asset('storage/' . $this->loanType->loan_type_icon)
            : null;
    }

    public function getLoanTypeImageUrlAttribute()
    {
        return $this->loanType && $this->loanType->loan_type_image
            ? asset('storage/' . $this->loanType->loan_type_image)
            : null;
    }

    public function getLoanTypeBannerUrlAttribute()
    {
        return $this->loanType && $this->loanType->loan_type_banner
            ? asset('storage/' . $this->loanType->loan_type_banner)
            : null;
    }
}
