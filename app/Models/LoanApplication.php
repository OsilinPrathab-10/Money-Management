<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasObfuscatedRouteKey;

class LoanApplication extends Model
{
    use HasFactory, HasObfuscatedRouteKey;
 
    protected $fillable = [
        'application_number',
        'client_id',
        'loan_code',
        'loan_mode',
        'loan_amount_min',
        'loan_amount_max',
        'loan_amount',
        'credit_limit',
        'tenure_min',
        'tenure_max',
        'tenure',
        'emi_day',
        'emi_start_month',
        'emi_start_year',
        'emi_start_week',
        'emi_start_day',
        'payment_method',
        'payment_gateway',
        'status',
        'assigned_to',
        'assigned_at',
        'loan_code_video',
        'loan_agreement_pdf',
        'interest_rate',
        'total_payable',
        'term_unit',
        'remarks',
        'approved_at',
        'disbursed_at',
        'live_photo',
        'cash_photo'
    ];

    protected $casts = [
        'loan_amount_min' => 'integer',
        'loan_amount_max' => 'integer',
        'loan_amount' => 'integer',
        'interest_rate' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_code', 'loan_code');
    }

    public function applicationDetail()
    {
        return $this->hasOne(LoanApplicationDetail::class);
    }

    public function loanAccount()
    {
        return $this->hasOne(LoanAccount::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function disbursementDetail()
    {
        return $this->hasOne(DisbursementDetail::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $datePart = now()->format('Ymd');
            $count = self::whereDate('created_at', now()->toDateString())->count();
            $nextSeq = $count + 1;

            // Ensure uniqueness in case of race conditions
            while (self::where('application_number', 'APP' . $datePart . str_pad($nextSeq, 4, '0', STR_PAD_LEFT))->exists()) {
                $nextSeq++;
            }

            $model->application_number = 'APP' . $datePart . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        });
    }
}
