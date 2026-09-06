<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CollectionLog extends Model
{
    use HasFactory;

    protected $table = 'collection_logs';

    protected $fillable = [
        'client_name',
        'loan_number',
        'emi_number',
        'collected_amount',
        'payment_mode',
        'collected_by_name',
        'ip_address',
        'collected_at',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'collected_amount' => 'decimal:2',
    ];
}
