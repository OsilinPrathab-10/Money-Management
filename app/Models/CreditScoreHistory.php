<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditScoreHistory extends Model
{
    protected $fillable = [
        'client_id',
        'linked_at',
        'linked_by',
        'applicant_name',
        'pan_number',
        'aadhar_number',
        'phone',
        'gender',
        'email',
        'date_of_birth',
        'score',
        'rating',
        'report_json',
        'status',
        'provider',
        'is_mock',
        'doc_id',
        'credit_report_link',
        'link_expires_at',
        'consolidated_pdf_url',
        'pdf_path',
        'pdf_source',
        'pdf_status',
        'pdf_error',
        'pdf_saved_at',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'report_json' => 'array',
        'date_of_birth' => 'date',
        'is_mock' => 'boolean',
        'link_expires_at' => 'datetime',
        'linked_at' => 'datetime',
        'pdf_saved_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function linkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
