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
      $client = $user->client;

      $pendingTickets = SupportTicket::where('client_id', $client->id)
        ->where('status', 'pending')
        ->orderByDesc('created_at')
        ->get();

      $openTickets = SupportTicket::where('client_id', $client->id)
          ->where('status', 'open')
          ->orderByDesc('created_at')
          ->get();

      $closedTickets = SupportTicket::where('client_id', $client->id)
          ->where('status', 'closed')
          ->orderByDesc('created_at')
          ->get();

      return response()->json([
          'status' => true,
          'message' => 'Support tickets fetched successfully.',
          'pending_tickets' => SupportTicketResource::collection($pendingTickets),
          'open_tickets' => SupportTicketResource::collection($openTickets),
          'closed_tickets' => SupportTicketResource::collection($closedTickets),
      ]);
  }

public function send(Request $request)
{
      $validated = $request->validate([
          'subject' => 'required|string|max:255',
          'priority' => 'required|in:low,medium,high,urgent',
          'message' => 'required|string',
          'attachments'   => 'nullable|array',
          'attachments.*' => 'file|max:5120', // up to 2MB per file
      ]);

      $user = Auth::user();
      $client = $user->client;

      if (!$client) {
          return response()->json([
              'status' => false,
              'message' => 'Client profile not found for this user.',
          ], 404);
      }

      $ticketNumber = 'TKT-' . strtoupper(Str::random(8));

      // Auto-assign to staff with least active tickets
      $staffUsers = collect();
      try {
          $staffUsers = \App\Models\User::role('Staff')->get();
      } catch (\Exception $e) {
          // Handle case where Staff role doesn't exist
          Log::warning('Staff role not found for assignment: ' . $e->getMessage());
      }
      $assignedTo = null;

      if ($staffUsers->isNotEmpty()) {
          $minTickets = -1;
          foreach ($staffUsers as $staff) {
              // Count active tickets (not closed)
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
          'priority' => $validated['priority'],
          'message' => $validated['message'],
          'assigned_to' => $assignedTo,
      ]);

      if ($request->hasFile('attachments')) {
          foreach ($request->file('attachments') as $file) {
              $path = $file->store('support_attachments', 'public');

              SupportTicketAttachment::create([
                  'ticket_id' => $ticket->id,
                  'reply_id' => null,
                  'file_name' => $file->getClientOriginalName(),
                  'file_path' => $path,
                  'file_type' => $file->getClientMimeType(),
                  'file_size' => $file->getSize(),
              ]);
          }
      }

      return response()->json([
          'status' => true,
          'message' => 'Support ticket created successfully.',
      ], 200);
  }

  public function show($id)
  {
      $ticket = SupportTicket::with([
          'attachments',
          'replies' => function ($q) {
              $q->orderBy('created_at', 'asc');
          },
          'replies.attachments'
      ])
      ->where('client_id', Auth::user()->client->id)
      ->findOrFail($id);

      return response()->json([
          'status' => true,
          // 'ticket' => $ticket,
          'ticket' => new SupportTicketResource($ticket),
      ]);
  }

  public function reply(Request $request, $id)
  {
      $validated = $request->validate([
          'message' => 'required|string',
          'attachments'   => 'nullable|array',
          'attachments.*' => 'file|max:5120',
      ]);

      $ticket = SupportTicket::where('client_id', Auth::user()->client->id)
                  ->where('id', $id)
                  ->firstOrFail();

      $reply = SupportTicketReply::create([
          'ticket_id' => $ticket->id,
          'client_id' => Auth::user()->client->id,
          'user_id' => null,
          'message' => $validated['message'],
      ]);

      if ($request->hasFile('attachments')) {
          foreach ($request->file('attachments') as $file) {
              $path = $file->store('support_attachments', 'public');

              SupportTicketAttachment::create([
                  'ticket_id' => $ticket->id,
                  'reply_id' => $reply->id,
                  'file_name' => $file->getClientOriginalName(),
                  'file_path' => $path,
                  'file_type' => $file->getClientMimeType(),
                  'file_size' => $file->getSize(),
              ]);
          }
      }

      return response()->json([
          'status' => true,
          'message' => 'Reply added successfully.',
      ]);
  }

}
