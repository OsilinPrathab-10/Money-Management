<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolveUrl = function (?string $path) {
            if (empty($path)) {
                return null;
            }
            if (filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, 'data:')) {
                return $path;
            }
            return url('storage/' . ltrim($path, '/'));
        };

        $kyc = $this->kycDetail;
        $nominee = $this->nominee;

        $aadhaarFrontUrl = $resolveUrl($kyc?->aadhaar_image ?: $this->aadhaar_photo_path);
        $aadhaarBackUrl = $resolveUrl($kyc?->aadhaar_image_back);
        $panImageUrl = $resolveUrl($kyc?->pan_image);
        $selfieImageUrl = $resolveUrl($kyc?->selfie_image ?: $this->profile_image);

        $scheduleDetails = method_exists($this->resource, 'getPublicScheduleDetails')
            ? $this->resource->getPublicScheduleDetails()
            : [
                'has_account' => false,
                'public_url' => null,
                'view_schedule_url' => null,
                'message' => "You don't have an active loan, chit, or fixed deposit account.",
            ];

        $data = [
            'id' => $this->id,
            'customer_id' => $this->displayCustomerId(),
            'client_name' => $this->client_name,
            'nickname' => $this->nickname,
            'client_email' => (! empty($this->client_email) && ! str_ends_with(strtolower(trim($this->client_email)), '@client.app') && ! str_ends_with(strtolower(trim($this->client_email)), '@shanmugafinance.local')) ? trim($this->client_email) : null,
            'client_phone' => $this->client_phone,
            'client_address' => $this->address,
            'profile_image' => $this->profile_image_url,
            'mpin' => $this->mpin,
            'mpin_set' => (bool) $this->mpin_hash,
            'mpin_status' => (bool) $this->mpin_hash,
            'kyc_status' => method_exists($this->resource, 'kycStatus') ? $this->kycStatus() : ($kyc?->status ?: 'unverified'),
            'status' => $this->status ?: 'pending',
            'is_active' => method_exists($this->resource, 'canAccessCustomerApp')
                ? $this->canAccessCustomerApp()
                : ($this->status !== 'inactive'),
            'view_schedule_url' => $scheduleDetails['view_schedule_url'],
            'public_schedule_url' => $scheduleDetails['public_url'],
            'schedule_info' => $scheduleDetails,

            'aadhaar_front_image' => $aadhaarFrontUrl,
            'aadhaar_back_image' => $aadhaarBackUrl,
            'pan_image' => $panImageUrl,
            'selfie_image' => $selfieImageUrl,

            'documents' => [
                'aadhaar_front_image' => $aadhaarFrontUrl,
                'aadhaar_back_image' => $aadhaarBackUrl,
                'pan_image' => $panImageUrl,
                'selfie_image' => $selfieImageUrl,
            ],

            'nominee_details' => $nominee ? [
                'nominee1_name' => $nominee->nominee1_name,
                'nominee1_relationship' => $nominee->nominee1_relationship,
                'nominee1_mobile' => $nominee->nominee1_mobile,
                'nominee2_name' => $nominee->nominee2_name,
                'nominee2_relationship' => $nominee->nominee2_relationship,
                'nominee2_mobile' => $nominee->nominee2_mobile,
            ] : null,

            'nominee' => $nominee ? [
                'nominee1_name' => $nominee->nominee1_name,
                'nominee1_relationship' => $nominee->nominee1_relationship,
                'nominee1_mobile' => $nominee->nominee1_mobile,
                'nominee2_name' => $nominee->nominee2_name,
                'nominee2_relationship' => $nominee->nominee2_relationship,
                'nominee2_mobile' => $nominee->nominee2_mobile,
            ] : null,
        ];

        if ($kyc) {
            $data['aadhaar_details'] = [
                'aadhaar_number' => $kyc->aadhaar_number ?: $this->aadhaar_number,
                'aadhaar_name' => $kyc->aadhaar_name ?: $this->client_name,
                'is_verified' => (bool) $kyc->aadhaar_verified,
                'aadhaar_front_image' => $aadhaarFrontUrl,
                'aadhaar_back_image' => $aadhaarBackUrl,
            ];

            $data['pan_details'] = [
                'pan_number' => $kyc->pan_number,
                'pan_name' => $kyc->pan_name ?: $this->client_name,
                'is_verified' => (bool) $kyc->pan_verified,
                'pan_image' => $panImageUrl,
            ];

            $data['bank_details'] = [
                'account_holder_name' => $kyc->account_holder_name,
                'account_number' => $kyc->account_number,
                'ifsc_code' => $kyc->ifsc_code,
                'account_type' => $kyc->account_type,
                'bank_name' => $kyc->bank_name,
                'branch_name' => $kyc->branch_name,
                'is_verified' => (bool) $kyc->bank_verified,
            ];
        }

        return $data;
    }
}
