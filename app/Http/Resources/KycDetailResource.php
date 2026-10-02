<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycDetailResource extends JsonResource
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

        return [
          'aadhaar_number' => $this->aadhaar_number,
          'aadhaar_name' => $this->aadhaar_name,
          'aadhaar_image' => $resolveUrl($this->aadhaar_image),
          'aadhaar_front_image' => $resolveUrl($this->aadhaar_image),
          'aadhaar_image_back' => $resolveUrl($this->aadhaar_image_back),
          'aadhaar_back_image' => $resolveUrl($this->aadhaar_image_back),
          'pan_number' => $this->pan_number,
          'pan_name' => $this->pan_name,
          'pan_image' => $resolveUrl($this->pan_image),
          'account_holder_name' => $this->account_holder_name,
          'account_number' => $this->account_number,
          'ifsc_code' => $this->ifsc_code,
          'bank_name' => $this->bank_name,
          'selfie_image' => $resolveUrl($this->selfie_image),
        ];
    }
}
