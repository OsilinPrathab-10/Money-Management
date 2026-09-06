<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'loan_account_id',
        'emi_id',
        'order_id',
        'payment_id',
        'payment_type',
        'signature',
        'amount',
        'amount_paise',
        'gateway',
        'status',
        'payload'
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
