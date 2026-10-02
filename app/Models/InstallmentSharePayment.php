<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-owner payment record against a chit installment.
 *
 * Without this, a shared seat only stores one aggregate `paid_amount`, so there is
 * no way to tell how much each co-owner still owes.
 */
class InstallmentSharePayment extends Model
{
    protected $fillable = [
        'installment_id',
        'group_member_id',
        'client_id',
        'amount',
        'ownership_percentage',
        'payment_mode',
        'reference_no',
        'remarks',
        'paid_date',
        'collected_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'ownership_percentage' => 'decimal:2',
        'paid_date' => 'date',
    ];

    public function installment()
    {
        return $this->belongsTo(Installment::class, 'installment_id');
    }

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
