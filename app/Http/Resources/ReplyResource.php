<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReplyResource extends JsonResource
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
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'message' => $this->message,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y h:i A') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y h:i A') : null,

            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
        ];

    }
}
