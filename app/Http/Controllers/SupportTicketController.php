<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\SupportTicketAttachment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:support.update')->only(['updateStatus']);
        $this->middleware('permission:support.delete')->only(['destroy']);
    }

    /**
     * Display support tickets index
     */
    public function index(): View
    {
        $stats = [
            'total_tickets' => SupportTicket::count(),
            'open_tickets' => SupportTicket::where('status', 'open')->count(),
            'pending_tickets' => SupportTicket::where('status', 'pending')->count(),
            'closed_tickets' => SupportTicket::where('status', 'closed')->count(),
        ];

        return view('admin.support.support-tickets', compact('stats'));
    }

    /**
     * Get tickets data for DataTable
     */
    public function getData(Request $request): JsonResponse
    {
        $columns = [
            0 => 'id',
            1 => 'ticket_number',
            2 => 'priority',
            3 => 'status',
            4 => 'created_at',
        ];

        // Build query
        $query = SupportTicket::with(['client', 'assignedUser']);

        // Filter by status
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority') && $request->priority != '') {
            $query->where('priority', $request->priority);
        }

        // Get total counts
        $totalData = $query->count();
        $totalFiltered = $totalData;

        // DataTables parameters
        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')] ?? 'created_at';
        $dir = $request->input('order.0.dir') ?? 'desc';

        // Search handling
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'LIKE', "%{$search}%")
                  ->orWhere('subject', 'LIKE', "%{$search}%")
                  ->orWhere('priority', 'LIKE', "%{$search}%")
                  ->orWhere('status', 'LIKE', "%{$search}%")
                  ->orWhereHas('client', function($q) use ($search) {
                      $q->where('client_name', 'LIKE', "%{$search}%");
                  });
            });

            $totalFiltered = $query->count();
        }

        // Apply pagination and ordering
        $tickets = $query->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        $data = $tickets->map(function ($ticket, $index) use ($start) {
            return [
                'id' => $ticket->id,
                'sno' => $start + $index + 1,
                'ticket_number' => $ticket->ticket_number,
                'client_name' => $ticket->client->client_name ?? 'N/A',
                'priority' => $ticket->priority,
                'priority_badge' => $this->getPriorityBadge($ticket->priority),
                'status' => $ticket->status,
                'status_badge' => $this->getStatusBadge($ticket->status),
                'created_at' => $ticket->created_at->format('d-m-Y'),
                'replies_count' => $ticket->replies()->count(),
            ];
        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    /**
     * Show ticket details
     */
    public function show($id): View
    {
        $ticket = SupportTicket::with(['client', 'assignedUser', 'replies.user', 'replies.client', 'replies.attachments', 'attachments'])
            ->findOrFail($id);

        $clients = Client::select('id', 'client_name')->get();
        $users = User::select('id', 'name')->get();

        return view('admin.support.ticket-details', compact('ticket', 'clients', 'users'));
    }

    /**
     * Store a new ticket
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'subject' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high,urgent',
            'message' => 'required|string',
            'attachments.*' => 'nullable|file|max:10240', // 10MB max per file
        ]);

        // Generate unique ticket number
        $ticketNumber = 'TK-' . strtoupper(Str::random(6));
        while (SupportTicket::where('ticket_number', $ticketNumber)->exists()) {
            $ticketNumber = 'TK-' . strtoupper(Str::random(6));
        }

        $ticket = SupportTicket::create([
            'ticket_number' => $ticketNumber,
            'client_id' => $request->client_id,
            'subject' => $request->subject,
            'priority' => $request->priority,
            'message' => $request->message,
            'status' => 'open',
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->store('support_tickets', 'public');

                SupportTicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully',
            'ticket_id' => $ticket->id,
        ]);
    }

    /**
     * Update ticket status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:open,pending,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully',
        ]);
    }

    /**
     * Assign ticket to user
     */
    public function assign(Request $request, $id): JsonResponse
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->update(['assigned_to' => $request->assigned_to]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully',
        ]);
    }

    /**
     * Add reply to ticket
     */
    public function addReply(Request $request, $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'attachments.*' => 'nullable|file|max:10240',
        ]);

        $ticket = SupportTicket::findOrFail($id);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $request->message,
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->store('support_tickets', 'public');

                SupportTicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'reply_id' => $reply->id,
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        // Update ticket status to pending if it was closed
        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'pending']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully',
            'reply' => [
                'id' => $reply->id,
                'message' => $reply->message,
                'user_name' => $reply->user->name ?? 'N/A',
                'created_at' => $reply->created_at->format('d-m-Y h:i A'),
                'attachments' => $reply->attachments,
            ],
        ]);
    }

    /**
     * Get users for assignment dropdown
     */
    public function getUsers(): JsonResponse
    {
        $users = User::select('id', 'name')->get();
        return response()->json($users);
    }

    /**
     * Delete ticket
     */
    public function destroy($id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);

        // Delete all attachments from storage
        foreach ($ticket->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ticket deleted successfully',
        ]);
    }

    /**
     * Get priority badge HTML
     */
    private function getPriorityBadge($priority): string
    {
        $badges = [
            'low' => '<span class="badge bg-label-info">Low</span>',
            'medium' => '<span class="badge bg-label-primary">Medium</span>',
            'high' => '<span class="badge bg-label-warning">High</span>',
            'urgent' => '<span class="badge bg-label-danger">Urgent</span>',
        ];

        return $badges[$priority] ?? '<span class="badge bg-label-secondary">Unknown</span>';
    }

    /**
     * Get status badge HTML
     */
    private function getStatusBadge($status): string
    {
        $badges = [
            'open' => '<span class="badge bg-label-success">Open</span>',
            'pending' => '<span class="badge bg-label-warning">Pending</span>',
            'closed' => '<span class="badge bg-label-secondary">Closed</span>',
        ];

        return $badges[$status] ?? '<span class="badge bg-label-secondary">Unknown</span>';
    }
}
