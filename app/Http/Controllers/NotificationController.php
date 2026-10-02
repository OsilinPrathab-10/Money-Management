<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Get all notifications for admin
     */
    public function index(Request $request): View
    {
        $query = AdminNotification::orderByDesc('created_at');

        // 1. Category Filter (Client/KYC, Loan, Chit, Ticket/Support, FD)
        if ($request->filled('category') && $request->category !== 'all') {
            $category = $request->category;
            if ($category === 'client') {
                $query->where(function ($q) {
                    $q->whereIn('type', ['user_registration', 'kyc_approved', 'kyc_rejected', 'client', 'client_assigned'])
                      ->orWhere('type', 'like', '%client%')
                      ->orWhere('type', 'like', '%kyc%')
                      ->orWhere('type', 'like', '%user%')
                      ->orWhere('title', 'like', '%client%')
                      ->orWhere('title', 'like', '%kyc%')
                      ->orWhere('title', 'like', '%user%');
                });
            } elseif ($category === 'loan') {
                $query->where(function ($q) {
                    $q->whereIn('type', ['new_loan_application', 'loan_application_approved', 'loan_application_rejected', 'loan_disbursed', 'payment_received', 'loan', 'undo_payment'])
                      ->orWhere('type', 'like', '%loan%')
                      ->orWhere('type', 'like', '%emi%')
                      ->orWhere('type', 'like', '%payment%')
                      ->orWhere('title', 'like', '%loan%')
                      ->orWhere('title', 'like', '%emi%')
                      ->orWhere('title', 'like', '%payment%');
                });
            } elseif ($category === 'chit') {
                $query->where(function ($q) {
                    $q->whereIn('type', ['new_chit_application', 'chit_application_approved', 'chit_application_rejected', 'chit_installment_collected', 'chit_settlement_paid', 'chit'])
                      ->orWhere('type', 'like', '%chit%')
                      ->orWhere('title', 'like', '%chit%');
                });
            } elseif ($category === 'ticket') {
                $query->where(function ($q) {
                    $q->whereIn('type', ['support_ticket', 'support_ticket_reply', 'ticket', 'support'])
                      ->orWhere('type', 'like', '%ticket%')
                      ->orWhere('type', 'like', '%support%')
                      ->orWhere('title', 'like', '%ticket%')
                      ->orWhere('title', 'like', '%support%');
                });
            } elseif ($category === 'fd') {
                $query->where(function ($q) {
                    $q->whereIn('type', ['new_fd_application', 'fd_application_approved', 'fd_application_rejected', 'fd_booked', 'fd', 'fixed_deposit'])
                      ->orWhere('type', 'like', '%fd%')
                      ->orWhere('type', 'like', '%deposit%')
                      ->orWhere('title', 'like', '%fd%')
                      ->orWhere('title', 'like', '%fixed deposit%');
                });
            }
        }

        // 2. Specific Type Filter
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // 3. Status Filter (unread / read)
        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'unread') {
                $query->unread();
            } elseif ($request->status === 'read') {
                $query->read();
            }
        }

        // 4. Search Filter (Client Name, Title, Message, etc.)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('link', 'like', "%{$search}%");
            });
        }

        // 5. Date Filter (Preset or Custom Range)
        if ($request->filled('date_preset') && $request->date_preset !== 'all') {
            $preset = $request->date_preset;
            if ($preset === 'today') {
                $query->whereDate('created_at', \Carbon\Carbon::today());
            } elseif ($preset === 'yesterday') {
                $query->whereDate('created_at', \Carbon\Carbon::yesterday());
            } elseif ($preset === 'this_week') {
                $query->whereBetween('created_at', [\Carbon\Carbon::now()->startOfWeek(), \Carbon\Carbon::now()->endOfWeek()]);
            } elseif ($preset === 'this_month') {
                $query->whereMonth('created_at', \Carbon\Carbon::now()->month)->whereYear('created_at', \Carbon\Carbon::now()->year);
            } elseif ($preset === 'custom') {
                if ($request->filled('start_date')) {
                    $query->whereDate('created_at', '>=', $request->start_date);
                }
                if ($request->filled('end_date')) {
                    $query->whereDate('created_at', '<=', $request->end_date);
                }
            }
        } else {
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
        }

        $notifications = $query->paginate(20)->withQueryString();

        $unreadCount = AdminNotification::unread()->count();
        $notificationTypes = AdminNotification::select('type')
            ->distinct()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->pluck('type');

        return view('admin.notifications.index', compact('notifications', 'unreadCount', 'notificationTypes'));
    }

    /**
     * Get latest notifications (for dropdown)
     */
    public function getLatest(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->input('limit', 10);
            
            $notifications = AdminNotification::orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'link' => $notification->link,
                        'icon' => $notification->icon_class ?? 'ri-notification-3-line',
                        'badge_color' => $notification->badge_color ?? 'secondary',
                        'is_read' => (bool) $notification->is_read,
                        'created_at' => $notification->created_at ? $notification->created_at->diffForHumans() : '',
                        'created_at_formatted' => $notification->created_at ? $notification->created_at->format('d-m-Y h:i A') : '',
                    ];
                });

            $unreadCount = AdminNotification::unread()->count();

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Error in NotificationController@getLatest: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'notifications' => [],
                'unread_count' => 0,
            ], 200);
        }
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $count = AdminNotification::unread()->count();

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'count' => 0,
            ], 200);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id): JsonResponse
    {
        try {
            $notification = AdminNotification::findOrFail($id);
            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            AdminNotification::unread()->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy($id): JsonResponse
    {
        try {
            $notification = AdminNotification::findOrFail($id);
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
            ], 500);
        }
    }

    /**
     * Clear all read notifications
     */
    public function clearRead(): JsonResponse
    {
        try {
            AdminNotification::read()->delete();

            return response()->json([
                'success' => true,
                'message' => 'All read notifications cleared',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear notifications',
            ], 500);
        }
    }
}
