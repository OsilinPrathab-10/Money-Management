<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AgentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationControllerApi extends Controller
{
    /**
     * Get all notifications for authenticated user (admin)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $type = $request->input('type'); // 'admin', 'agent'

            if ($type === 'agent') {
                // Agent notifications
                $notifications = AgentNotification::where('agent_id', Auth::id())
                    ->orderByDesc('created_at')
                    ->paginate($limit, ['*'], 'page', $page);
                
                // Get unread count for agent
                $unreadCount = AgentNotification::where('agent_id', Auth::id())
                    ->whereNull('read_at')
                    ->count();
            } else {
                // Admin notifications (default)
                $notifications = AdminNotification::orderByDesc('created_at')
                    ->paginate($limit, ['*'], 'page', $page);
                
                // Get unread count for admin
                $unreadCount = AdminNotification::where('is_read', false)
                    ->count();
            }

            return response()->json([
                'success' => true,
                'data' => $notifications->items(),
                'notification_count' => [
                    'unread' => $unreadCount,
                    'total' => $notifications->total(),
                ],
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Get latest notifications (for dropdown/quick view)
     */
    public function getLatest(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 10);
            $type = $request->input('type', 'admin'); // 'admin' or 'agent'

            if ($type === 'agent') {
                $notifications = AgentNotification::where('agent_id', Auth::id())
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(function ($notification) {
                        return [
                            'id' => $notification->id,
                            'type' => $notification->notification_type,
                            'title' => $notification->title,
                            'message' => $notification->message,
                            'icon' => $notification->icon,
                            'priority' => $notification->priority,
                            'is_read' => !empty($notification->read_at),
                            'created_at' => $notification->created_at->diffForHumans(),
                            'created_at_formatted' => $notification->created_at->format('d-m-Y h:i A'),
                        ];
                    });
            } else {
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
                            'icon' => $notification->icon_class,
                            'badge_color' => $notification->badge_color,
                            'is_read' => $notification->is_read,
                            'created_at' => $notification->created_at->diffForHumans(),
                            'created_at_formatted' => $notification->created_at->format('d-m-Y h:i A'),
                        ];
                    });
            }

            $unreadCount = $this->getUnreadNotificationCount($type);

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching latest notifications', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'admin');
            $count = $this->getUnreadNotificationCount($type);

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching unread count', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unread count'
            ], 500);
        }
    }

    /**
     * Get single notification details
     */
    public function show($id, Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'admin');

            if ($type === 'agent') {
                $notification = AgentNotification::where('agent_id', Auth::id())
                    ->findOrFail($id);
                $formattedData = [
                    'id' => $notification->id,
                    'type' => $notification->notification_type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'icon' => $notification->icon,
                    'priority' => $notification->priority,
                    'action_data' => $notification->action_data,
                    'is_read' => !empty($notification->read_at),
                    'created_at' => $notification->created_at,
                ];
            } else {
                $notification = AdminNotification::findOrFail($id);
                $formattedData = [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'link' => $notification->link,
                    'icon' => $notification->icon_class,
                    'badge_color' => $notification->badge_color,
                    'is_read' => $notification->is_read,
                    'created_at' => $notification->created_at,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formattedData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notification', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id, Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'admin');

            if ($type === 'agent') {
                $notification = AgentNotification::where('agent_id', Auth::id())
                    ->findOrFail($id);
                if (!$notification->read_at) {
                    $notification->update(['read_at' => now()]);
                }
            } else {
                $notification = AdminNotification::findOrFail($id);
                if (!$notification->is_read) {
                    $notification->markAsRead();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'admin');

            if ($type === 'agent') {
                AgentNotification::where('agent_id', Auth::id())
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            } else {
                AdminNotification::unread()->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy($id, Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'admin');

            if ($type === 'agent') {
                $notification = AgentNotification::where('agent_id', Auth::id())
                    ->findOrFail($id);
                $notification->delete();
            } else {
                $notification = AdminNotification::findOrFail($id);
                $notification->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting notification', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
            ], 500);
        }
    }

    /**
     * Clear all read notifications
     */
    public function clearRead(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'admin');

            if ($type === 'agent') {
                AgentNotification::where('agent_id', Auth::id())
                    ->whereNotNull('read_at')
                    ->delete();
            } else {
                AdminNotification::read()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'All read notifications cleared',
            ]);
        } catch (\Exception $e) {
            Log::error('Error clearing read notifications', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear notifications',
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'admin');

            if ($type === 'agent') {
                $total = AgentNotification::where('agent_id', Auth::id())->count();
                $unread = AgentNotification::where('agent_id', Auth::id())
                    ->whereNull('read_at')
                    ->count();
            } else {
                $total = AdminNotification::count();
                $unread = AdminNotification::unread()->count();
            }

            return response()->json([
                'success' => true,
                'stats' => [
                    'total' => $total,
                    'unread' => $unread,
                    'read' => $total - $unread,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notification stats', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }

    /**
     * Helper method to get unread notification count
     */
    private function getUnreadNotificationCount($type)
    {
        if ($type === 'agent') {
            return AgentNotification::where('agent_id', Auth::id())
                ->whereNull('read_at')
                ->count();
        }
        return AdminNotification::unread()->count();
    }
}
