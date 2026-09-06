<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditScoreHistory extends Model
{
    protected $fillable = [
        'client_id',
        'applicant_name',
        'pan_number',
        'aadhar_number',
        'phone',
        'email',
        'date_of_birth',
        'score',
        'rating',
        'report_json',
        'status',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'report_json' => 'array',
        'date_of_birth' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
