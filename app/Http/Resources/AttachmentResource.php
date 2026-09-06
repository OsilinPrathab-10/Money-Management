<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'reply_id' => $this->reply_id,
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'url' => $this->url,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y h:i A') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y h:i A') : null,
        ];
    }
}
