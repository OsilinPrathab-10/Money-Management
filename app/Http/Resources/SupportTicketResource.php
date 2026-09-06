<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
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
          'ticket_number' => $this->ticket_number,
          'client_id' => $this->client_id,
          'subject' => $this->subject,
          'priority' => $this->priority,
          'message' => $this->message,
          'status' => $this->status,
          'assigned_to' => $this->assigned_to,
          'created_at' => $this->created_at ? $this->created_at->format('d-m-Y h:i A') : null,
          'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y h:i A') : null,

          'ticket_attachments' => AttachmentResource::collection(
              $this->attachments()->whereNull('reply_id')->get()
          ),

          'replies' => ReplyResource::collection(
              $this->whenLoaded('replies')
          ),
      ];
    }
}
