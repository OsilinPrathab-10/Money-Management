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
        return [
          'client_name' => $this->client_name,
          'client_email' => $this->client_email,
          'client-phone' => $this->client_phone,
          'client_address' => $this->address
        ];
    }
}
