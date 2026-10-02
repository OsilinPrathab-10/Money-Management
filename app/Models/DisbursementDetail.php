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
        'internal_bank_account_id',
        'collateral_document',
        'other_document',
        'additional_documents',
    ];

    protected $casts = [
        'disbursement_amount' => 'decimal:2',
        'disburse_at' => 'datetime',
        'additional_documents' => 'array',
    ];

    public function application()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function internalBankAccount()
    {
        return $this->belongsTo(\App\Models\Account\BankAccount::class, 'internal_bank_account_id');
    }
}
