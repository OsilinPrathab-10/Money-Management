<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'loan_amount',
        'loan_type',
        'interest_rate',
        'tenure',
        'status',
        'approved_at',
        'disbursed_at',
    ];

    protected $casts = [
        'loan_amount' => 'integer',
        'interest_rate' => 'decimal:2',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
