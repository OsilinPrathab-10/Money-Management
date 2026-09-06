<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAuditLog extends Model
{
    protected $fillable = [
        'client_name',
        'loan_code_name',
        'loan_code',
        'receipt_number',
        'payment_mode',
        'payment_type',
        'payment_amount',
        'payment_date',
        'payment_time',
        'payment_status',
        'payment_remark',
        'payment_created_by',
        'payment_created_at',
        'payment_updated_at',
        'payment_deleted_at',
        'emi_id',
        'reason_to_undo',
        'loan_account_id',
        'admin_id',
        'admin_name',
        'action_type',
        'previous_payment_data'
    ];

    protected $casts = [
        'previous_payment_data' => 'array'
    ];

    public function emi()
    {
        return $this->belongsTo(Emi::class, 'emi_id');
    }

    public function loanAccount()
    {
        return $this->belongsTo(LoanAccount::class, 'loan_account_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
