<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\LoanApplication;

class DisbursementDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id',
        'transaction_id',
        'utr_number',
        'bank_account_number',
        'ifsc_code',
        'holder_name',
        'account_type',
        'bank_name',
        'disbursement_amount',
        'disburse_at',
    ];

    public function application()
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
