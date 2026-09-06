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
        return [
          'aadhaar_number' => $this->aadhaar_number,
          'pan_number' => $this->pan_number,
          'account_holder_name' => $this->account_holder_name,
          'account_number' => $this->account_number,
          'ifsc_code' => $this->ifsc_code,
          'bank_name' => $this->bank_name,
          'selfie_image' => $this->selfie_image
            ? url('storage/' . $this->selfie_image)
            : null,
        ];
    }
}
