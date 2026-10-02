<?php

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountDeletionRequestController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total' => AccountDeletionRequest::count(),
            'pending' => AccountDeletionRequest::where('status', 'pending')->count(),
            'under_review' => AccountDeletionRequest::where('status', 'under_review')->count(),
            'completed' => AccountDeletionRequest::where('status', 'completed')->count(),
        ];

        return view('admin.account-deletion.index', compact('stats'));
    }

    public function getData(Request $request): JsonResponse
    {
        $columns = [
            0 => 'id',
            1 => 'request_number',
            2 => 'full_name',
            3 => 'status',
            4 => 'created_at',
        ];

        $query = AccountDeletionRequest::with(['client', 'reviewer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $totalData = AccountDeletionRequest::count();
        $totalFiltered = (clone $query)->count();

        $limit = $request->input('length', 10);
        $start = $request->input('start', 0);
        $orderCol = $columns[$request->input('order.0.column', 4)] ?? 'created_at';
        $dir = $request->input('order.0.dir', 'desc');

        if ($search = $request->input('search.value')) {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
            $totalFiltered = (clone $query)->count();
        }

        $requests = $query->offset($start)
            ->limit($limit)
            ->orderBy($orderCol, $dir)
            ->get();

        $data = $requests->map(function (AccountDeletionRequest $item) {
            $clientLink = $item->client
                ? '<a href="' . route('client-view-account', $item->client->getRouteKey()) . '" class="text-primary">' . e($item->client->client_name) . '</a>'
                : '<span class="text-muted">Not linked</span>';

            return [
                'id' => $item->id,
                'request_number' => $item->request_number,
                'full_name' => e($item->full_name),
                'email' => e($item->email),
                'mobile' => e($item->mobile),
                'client_link' => $clientLink,
                'status' => $item->status,
                'status_badge' => $item->status_badge,
                'created_at' => $item->created_at->format('d M Y, h:i A'),
                'reason' => e($item->reason ?: '—'),
                'admin_notes' => e($item->admin_notes ?: ''),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $item = AccountDeletionRequest::with(['client', 'reviewer'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'request_number' => $item->request_number,
                'full_name' => $item->full_name,
                'email' => $item->email,
                'mobile' => $item->mobile,
                'reason' => $item->reason,
                'status' => $item->status,
                'admin_notes' => $item->admin_notes,
                'client_name' => $item->client?->client_name,
                'client_id' => $item->client_id,
                'client_url' => $item->client
                    ? route('client-view-account', $item->client->getRouteKey())
                    : null,
                'reviewer_name' => $item->reviewer?->name,
                'reviewed_at' => $item->reviewed_at?->format('d M Y, h:i A'),
                'created_at' => $item->created_at->format('d M Y, h:i A'),
                'ip_address' => $item->ip_address,
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,under_review,approved,rejected,completed',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $item = AccountDeletionRequest::findOrFail($id);
        $item->status = $validated['status'];
        $item->admin_notes = $validated['admin_notes'] ?? $item->admin_notes;
        $item->reviewed_by = Auth::id();
        $item->reviewed_at = now();
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Request status updated successfully.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = AccountDeletionRequest::findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deletion request removed.',
        ]);
    }
}
