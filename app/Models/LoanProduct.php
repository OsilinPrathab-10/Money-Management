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
        'banking_charges',
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

    public const MANUAL_OPEN_LOAN_CODE = 'MANUAL-OPEN-LOAN';

    /**
     * Placeholder product for open loans whose amount and interest are entered manually.
     * Kept inactive so it never appears in product pickers.
     */
    public static function manualOpenLoanProduct(): self
    {
        $product = static::withTrashed()->where('loan_code', self::MANUAL_OPEN_LOAN_CODE)->first();

        if ($product) {
            if ($product->trashed()) {
                $product->restore();
            }
            return $product;
        }

        $loanTypeId = LoanType::where('status', 1)->orderBy('id')->value('id')
            ?? LoanType::orderBy('id')->value('id');

        if (! $loanTypeId) {
            throw new \RuntimeException('Create at least one loan type before applying for a manual open loan.');
        }

        return static::create([
            'loan_name' => 'Manual Open Loan',
            'loan_type_id' => $loanTypeId,
            'loan_code' => self::MANUAL_OPEN_LOAN_CODE,
            'loan_amount_min' => 1,
            'loan_amount_max' => 9999999999,
            'interest_rate' => 0,
            'interest_type' => 'flat',
            'term_unit' => 'months',
            'min_tenture' => 0,
            'max_tenture' => 0,
            'processing_fee' => 0,
            'document_charges' => 0,
            'other_charges' => 0,
            'banking_charges' => 0,
            'description' => 'System product for open loans with manually entered amount and interest.',
            'status' => 'inactive',
        ]);
    }

    public function applications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loanAccounts()
    {
        return $this->hasMany(LoanAccount::class, 'loan_code', 'loan_code');
    }

    public function loanType()
    {
        return $this->belongsTo(LoanType::class, 'loan_type_id');
    }

    public function getLoanTypeIconUrlAttribute()
    {
        return $this->loanType ? $this->loanType->loan_type_icon_url : null;
    }

    public function getLoanTypeImageUrlAttribute()
    {
        return $this->loanType ? $this->loanType->loan_type_image_url : null;
    }

    public function getLoanTypeBannerUrlAttribute()
    {
        return $this->loanType ? $this->loanType->loan_type_banner_url : null;
    }
}
