<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\LoanAccount;
use App\Models\Client;
use App\Models\User;
use App\Models\Account\BankAccount;

class LoanTopup extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_account_id',
        'client_id',
        'topup_amount',
        'previous_loan_amount',
        'new_loan_amount',
        'previous_outstanding_amount',
        'new_outstanding_amount',
        'previous_emi_amount',
        'new_emi_amount',
        'interest_rate',
        'previous_tenure',
        'new_tenure',
        'additional_tenure',
        'frequency',
        'emi_day',
        'processing_fee',
        'disbursed_amount',
        'payment_method',
        'internal_bank_account_id',
        'utr_number',
        'disbursed_at',
        'next_due_date',
        'processed_by',
        'remarks',
    ];

    protected $casts = [
        'topup_amount' => 'decimal:2',
        'previous_loan_amount' => 'decimal:2',
        'new_loan_amount' => 'decimal:2',
        'previous_outstanding_amount' => 'decimal:2',
        'new_outstanding_amount' => 'decimal:2',
        'previous_emi_amount' => 'decimal:2',
        'new_emi_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'previous_tenure' => 'integer',
        'new_tenure' => 'integer',
        'additional_tenure' => 'integer',
        'processing_fee' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'next_due_date' => 'date',
    ];

    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function internalBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'internal_bank_account_id');
    }
}
