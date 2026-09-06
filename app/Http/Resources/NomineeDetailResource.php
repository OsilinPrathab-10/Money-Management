<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NomineeDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
         return [
            'nominee1_name' => $this->nominee1_name,
            'nominee1_relationship' => $this->nominee1_relationship,
            'nominee1_mobile' => $this->nominee1_mobile,
            'nominee2_name' => $this->nominee2_name,
            'nominee2_relationship' => $this->nominee2_relationship,
            'nominee2_mobile' => $this->nominee2_mobile,
        ];
    }
}
