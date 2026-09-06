<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EmploymentInformationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Employment Type
            'employment_type' => $this->employment_type,

            // Salaried Fields
            'company_name' => $this->company_name,
            'job_type' => $this->job_type,
            'monthly_salary' => $this->monthly_salary,
            'salary_credit_bank' => $this->salary_credit_bank,
            'work_experience' => $this->work_experience,

            // Payslips (URLs)
            'payslip_documents' => collect($this->payslip_documents ?? [])
                ->map(fn ($path) => Storage::disk('public')->url($path))
                ->values(),

            // Self-employed Fields
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'business_category' => $this->business_category,
            'years_in_business' => $this->years_in_business,
            'monthly_turnover' => $this->monthly_turnover,
            'business_address' => $this->business_address,
            'gst_number' => $this->gst_number,

            // Proofs (URLs)
            'business_proof_documents' => collect($this->business_proof_documents ?? [])
                ->map(fn ($path) => Storage::disk('public')->url($path))
                ->values(),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
