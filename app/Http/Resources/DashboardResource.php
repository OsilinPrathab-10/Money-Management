<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\KycDetail;
use App\Models\EmployeeInformation;
use App\Models\Nominee;
use App\Http\Resources\LoanProductResource;
use Illuminate\Support\Facades\Storage;


class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    public function __construct($resource, $overdueCount)
    {
        parent::__construct($resource);
        $this->overdueCount = $overdueCount;
    }

    public function toArray(Request $request): array
    {
        $kyc = $this->kycDetail;
        $nominee = $this->nominee;
        $employeeInfo = $this->employeeInformation;

        $panVerified = !empty($kyc?->pan_number);
        $aadhaarVerified = !empty($kyc?->aadhaar_number);
        $bankVerified = !empty($kyc?->account_number);
        $selfieImage = !empty($kyc?->selfie_image);
        $nominee = !empty($nominee);
        $employeeInfo = !empty($employeeInfo);

        // KYC is complete only if all 3 exist
        $kycCompleted = $panVerified && $aadhaarVerified && $bankVerified && $selfieImage && $nominee && $employeeInfo && in_array($kyc->status, ['verified', 'pending']);

        return [
            'client' => [
                'name' => $this->user->name ?? null,
                'email' => $this->user->email ?? null,
                'phone' => $this->user->phone ?? null,
                'profile_img' => $this->profile_image
                  ? url(Storage::url($this->profile_image))
                  : null,
            ],
            'kyc_details' => [
                'pan_verified' => $panVerified,
                'aadhaar_verified' => $aadhaarVerified,
                'bank_verified' => $bankVerified,
                'selfieImage' => $selfieImage,
                'nomineeDetails' => $nominee,
                'employeeInfo' => $employeeInfo,
                'kyc_completed' => $kycCompleted,
                'kyc_status' => $kyc?->status
            ],
            'loan_products' => LoanProductResource::collection(
                $this->whenLoaded('loanProducts')
            ),
            'emi_status' => [
                'overdue_count' => $this->overdueCount,
                'has_overdue' => $this->overdueCount > 0,
            ],
        ];
    }
}
