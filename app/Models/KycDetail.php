<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Client;
use App\Models\User;

class KycDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'aadhaar_number',
        'aadhaar_name',
        'aadhaar_image',
        'aadhaar_image_back',
        'selfie_image',
        'pan_number',
        'pan_name',
        'pan_image',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'account_type',
        'bank_name',
        'branch_name',
        'bank_statement',
        'rc_book_image',
        'driving_licence_image',
        'vehicle_number',
        'home_loan_document',
        'additional_documents',
        'status',
        'rejected_reason',
        'attempt_no',
        'aadhaar_verified',
        'pan_verified',
        'bank_verified',
        'kyc_skipped',
        'kyc_skipped_by',
        'kyc_skipped_at',
        'kyc_skip_remarks',
    ];

    protected $casts = [
        'aadhaar_verified' => 'boolean',
        'pan_verified' => 'boolean',
        'bank_verified' => 'boolean',
        'kyc_skipped' => 'boolean',
        'kyc_skipped_at' => 'datetime',
        'additional_documents' => 'array',
    ];


    // Archive old KYC record
    // The snapshot is soft deleted so it stays out of active KYC lookups
    // while remaining available as an audit trail of the previous attempt.
    public function archive()
    {
        $archived = $this->replicate();
        $archived->archived_at = now();

        $timestamp = time() . '_' . rand(1000, 9999);
        if (!empty($archived->pan_number)) {
            $archived->pan_number = $archived->pan_number . '_arc_' . $timestamp;
        }
        if (!empty($archived->aadhaar_number)) {
            $archived->aadhaar_number = $archived->aadhaar_number . '_arc_' . $timestamp;
        }
        if (!empty($archived->account_number)) {
            $archived->account_number = $archived->account_number . '_arc_' . $timestamp;
        }

        $archived->save();
        $archived->delete();

        return $archived;
    }

    /**
     * Relationship: A bank detail belongs to one client.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function skippedBy()
    {
        return $this->belongsTo(User::class, 'kyc_skipped_by');
    }
}
