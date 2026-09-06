<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;

class EmployeeInformation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'employment_type',
        'company_name',
        'employee_id',
        'job_type',
        'monthly_salary',
        'work_experience',
        'salary_credit_bank',
        'payslip_documents',

        'business_name',
        'business_type',
        'business_category',
        'years_in_business',
        'monthly_turnover',
        'business_address',
        'gst_number',
        'business_proof_documents',
    ];

    protected $appends = [
        'payslip_urls',
        'proof_urls'
    ];

    protected $casts = [
        'payslip_documents' => 'array',
        'business_proof_documents' => 'array',
    ];

    /**
     * Relationship: A client employment record belongs to a client.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function getPayslipUrlsAttribute()
    {
        if (!$this->payslip_documents) {
            return [];
        }

        return collect($this->payslip_documents)->map(function ($path) {
            return Storage::disk('public')->url($path);
        })->values();
    }

    public function getProofUrlsAttribute()
    {
        if (!$this->business_proof_documents) {
            return [];
        }

        return collect($this->business_proof_documents)->map(function ($path) {
            return Storage::disk('public')->url($path);
        })->values();
    }
}
