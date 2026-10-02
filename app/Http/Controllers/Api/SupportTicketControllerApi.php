<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketReply;
use App\Http\Resources\SupportTicketResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SupportTicketControllerApi extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $client = $user->client
            ?? \App\Models\Client::where('user_id', $user->id)->first()
            ?? \App\Models\Client::where('client_phone', $user->phone ?? '')->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404);
        }

        $allTickets = SupportTicket::with([
            'attachments',
            'replies.user',
            'replies.client',
            'replies.attachments'
        ])
        ->where('client_id', $client->id)
        ->orderByDesc('created_at')
        ->get();

        $pendingTickets = $allTickets->where('status', 'pending')->values();
        $openTickets = $allTickets->where('status', 'open')->values();
        $closedTickets = $allTickets->where('status', 'closed')->values();

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Support tickets fetched successfully.',
            'data' => SupportTicketResource::collection($allTickets),
            'tickets' => SupportTicketResource::collection($allTickets),
            'pending_tickets' => SupportTicketResource::collection($pendingTickets),
            'open_tickets' => SupportTicketResource::collection($openTickets),
            'closed_tickets' => SupportTicketResource::collection($closedTickets),
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'attachments' => 'nullable',
            'attachments.*' => 'file|max:5120',
        ]);

        $user = Auth::user();
        $client = $user->client
            ?? \App\Models\Client::where('user_id', $user->id)->first()
            ?? \App\Models\Client::where('client_phone', $user->phone ?? '')->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Client profile not found for this user.',
            ], 404);
        }

        $ticketNumber = 'TKT-' . strtoupper(Str::random(8));

        // Auto-assign to staff with least active tickets
        $staffUsers = collect();
        try {
            $staffUsers = \App\Models\User::role('Staff')->get();
        } catch (\Throwable $e) {
            Log::warning('Staff role search issue: ' . $e->getMessage());
        }
        $assignedTo = null;

        if ($staffUsers->isNotEmpty()) {
            $minTickets = -1;
            foreach ($staffUsers as $staff) {
                $activeTickets = SupportTicket::where('assigned_to', $staff->id)
                    ->where('status', '!=', 'closed')
                    ->count();

                if ($minTickets === -1 || $activeTickets < $minTickets) {
                    $minTickets = $activeTickets;
                    $assignedTo = $staff->id;
                }
            }
        }

        $ticket = SupportTicket::create([
            'ticket_number' => $ticketNumber,
            'client_id' => $client->id,
            'subject' => $validated['subject'],
            'priority' => 'medium',
            'message' => $validated['message'],
            'status' => 'pending',
            'assigned_to' => $assignedTo,
        ]);

        $this->handleUploadedAttachments($request, $ticket, null);

        $ticket->load(['attachments', 'replies.attachments', 'replies.user', 'replies.client']);

        // Dispatch notifications
        app(\App\Services\AppNotificationService::class)->newSupportTicket($ticket, 'customer');

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Support ticket created successfully.',
            'data' => new SupportTicketResource($ticket),
        ], 200);
    }

    public function show($id)
    {
        $user = Auth::user();
        $client = $user->client
            ?? \App\Models\Client::where('user_id', $user->id)->first();

        $realId = \App\Support\HashId::decode((string) $id) ?? $id;

        $ticket = SupportTicket::with([
            'attachments',
            'replies' => function ($q) {
                $q->orderBy('created_at', 'asc');
            },
            'replies.user',
            'replies.client',
            'replies.attachments'
        ])
        ->where('client_id', $client->id)
        ->where(function ($q) use ($id, $realId) {
            $q->where('id', $realId)->orWhere('ticket_number', $id);
        })
        ->firstOrFail();

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Support ticket details fetched successfully.',
            'data' => new SupportTicketResource($ticket),
            'ticket' => new SupportTicketResource($ticket),
        ]);
    }

    public function reply(Request $request, $id)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'attachments' => 'nullable',
            'attachments.*' => 'file|max:5120',
        ]);

        $user = Auth::user();
        $client = $user->client
            ?? \App\Models\Client::where('user_id', $user->id)->first();

        $realId = \App\Support\HashId::decode((string) $id) ?? $id;

        $ticket = SupportTicket::where('client_id', $client->id)
            ->where(function ($q) use ($id, $realId) {
                $q->where('id', $realId)->orWhere('ticket_number', $id);
            })
            ->firstOrFail();

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'client_id' => $client->id,
            'user_id' => null,
            'message' => $validated['message'],
        ]);

        $this->handleUploadedAttachments($request, $ticket, $reply->id);

        // Re-open ticket if it was closed or pending
        $ticket->update(['status' => 'open']);

        // Send push & in-app notification to Admin
        app(\App\Services\AppNotificationService::class)->supportTicketReply($ticket, $reply, 'customer');

        $reply->load(['user', 'client', 'attachments']);

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Reply added successfully.',
            'data' => new \App\Http\Resources\ReplyResource($reply),
        ]);
    }

    /**
     * Process file uploads or base64 attachments for tickets and replies
     */
    protected function handleUploadedAttachments(Request $request, SupportTicket $ticket, ?int $replyId = null): void
    {
        $fileInputs = [];

        if ($request->hasFile('attachments')) {
            $files = $request->file('attachments');
            if (is_array($files)) {
                $fileInputs = array_merge($fileInputs, $files);
            } else {
                $fileInputs[] = $files;
            }
        }

        foreach (['attachment', 'file', 'image', 'document'] as $key) {
            if ($request->hasFile($key)) {
                $fileInputs[] = $request->file($key);
            }
        }

        foreach ($fileInputs as $file) {
            if ($file && $file->isValid()) {
                $path = $file->store('support_attachments', 'public');

                SupportTicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'reply_id' => $replyId,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        foreach (['attachment_base64', 'image_base64', 'file_base64', 'attachments_base64'] as $b64Key) {
            $b64Data = $request->input($b64Key);
            if ($b64Data) {
                $items = is_array($b64Data) ? $b64Data : [$b64Data];
                foreach ($items as $item) {
                    if (is_string($item) && ! empty($item)) {
                        $this->saveBase64Attachment($item, $ticket, $replyId);
                    }
                }
            }
        }
    }

    /**
     * Store raw base64 string as attachment
     */
    protected function saveBase64Attachment(string $base64, SupportTicket $ticket, ?int $replyId = null): void
    {
        try {
            if (preg_match('/^data:([^;]+);base64,(.*)$/', $base64, $matches)) {
                $mimeType = $matches[1];
                $data = base64_decode($matches[2]);
            } else {
                $mimeType = 'image/jpeg';
                $data = base64_decode($base64);
            }

            if (! $data) {
                return;
            }

            $extension = explode('/', $mimeType)[1] ?? 'jpg';
            $fileName = 'attachment_' . time() . '_' . Str::random(5) . '.' . $extension;
            $filePath = 'support_attachments/' . $fileName;

            \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $data);

            SupportTicketAttachment::create([
                'ticket_id' => $ticket->id,
                'reply_id' => $replyId,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_type' => $mimeType,
                'file_size' => strlen($data),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Base64 attachment processing error: ' . $e->getMessage());
        }
    }
}
