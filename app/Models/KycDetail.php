<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Client;

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
        'status',
        'rejected_reason',
        'attempt_no',
    ];


    // Archive old KYC record
    public function archive()
    {
        $archived = $this->replicate(); // copy current record
        $archived->archived_at = now();
        $archived->status = 'archived';
        $archived->save();

        return $archived;
    }

    /**
     * Relationship: A bank detail belongs to one client.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
